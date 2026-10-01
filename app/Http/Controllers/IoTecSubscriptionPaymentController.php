<?php

namespace App\Http\Controllers;

use App\Models\IoTecSubscriptionTransaction;
use App\Models\SiteSetting;
use App\Models\SubscriptionPlan;
use App\Services\IoTecPayService;
use App\Services\SubscriptionBeneficiaryService;
use App\Services\SubscriptionPaymentActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class IoTecSubscriptionPaymentController extends Controller
{
    public function __construct(
        private readonly IoTecPayService $iotec,
        private readonly SubscriptionPaymentActivationService $activation,
        private readonly SubscriptionBeneficiaryService $beneficiaries
    ) {}

    public function initiate(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'integer'],
            'payment_channel' => [
                'required',
                Rule::in(['mobile_money', 'card']),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'card_brand' => [
                'nullable',
                Rule::in(['visa', 'mastercard']),
            ],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'beneficiary_email' => ['nullable', 'email', 'max:255'],
        ]);

        $beneficiary = $this->beneficiaries->resolveForPayment(
            $request->user(),
            $data['beneficiary_email'] ?? null
        );

        if (! Schema::hasTable('subscription_plans')) {
            return $this->error(
                $request,
                'Subscription plans are not configured.',
                422
            );
        }

        /*
         * IMPORTANT:
         *
         * Use the SAME pricing calculation as the normal Subscription
         * controller and plan cards. Many plans do not store their final
         * payable amount directly in a simple `price` column; the amount is
         * calculated from Site Settings + plan configuration.
         *
         * Using DB::table() plus a list of guessed amount columns caused valid
         * plans such as Family & Small Team to resolve to 0 and stopped the
         * request before an ioTec transaction could even be created.
         */
        $plan = SubscriptionPlan::query()
            ->whereKey((int) $data['subscription_plan_id'])
            ->where('is_enabled', true)
            ->first();

        if (! $plan) {
            return $this->error(
                $request,
                'Selected subscription plan was not found or is no longer available.',
                422
            );
        }

        $settings = SiteSetting::current();

        $baseMonthlyPrice = (float) (
            $settings->monthly_price ?? 0
        );

        $amount = (float) $plan->computedPrice(
            $baseMonthlyPrice
        );

        $currency = strtoupper(
            trim(
                (string) (
                    $settings->default_currency_code
                    ?? 'UGX'
                )
            )
        );

        if ($currency === '') {
            $currency = 'UGX';
        }

        if (! is_finite($amount) || $amount <= 0) {
            \Illuminate\Support\Facades\Log::warning(
                'ioTec subscription plan resolved to invalid amount',
                [
                    'subscription_plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'duration_months' => $plan->duration_months,
                    'base_monthly_price' => $baseMonthlyPrice,
                    'computed_amount' => $amount,
                    'currency' => $currency,
                ]
            );

            return $this->error(
                $request,
                'The selected subscription plan currently calculates to a zero payment amount. Please review the Subscription Plan and Site Settings pricing.',
                422
            );
        }

        /*
         * Keep the gateway amount predictable. The normal Subscription page
         * displays the same computed value. UGX is normally zero-decimal, but
         * we retain two decimals here only when the calculation genuinely
         * contains them; the ioTec service serialises it as a numeric amount.
         */
        $amount = round($amount, 2);

        $user = $request->user();
        $channel = $data['payment_channel'];
        $externalId = (string) Str::uuid();

        if (
            $channel === 'mobile_money'
            && blank($data['phone'] ?? null)
        ) {
            return $this->error(
                $request,
                'Enter the mobile money number.',
                422
            );
        }

        $payerEmail = trim(
            (string) (
                $data['payer_email']
                ?? $user->email
                ?? ''
            )
        );

        $payerName = trim(
            (string) (
                $data['payer_name']
                ?? $user->name
                ?? ''
            )
        );

        if (
            $channel === 'card'
            && $payerEmail === ''
        ) {
            return $this->error(
                $request,
                'Enter a valid billing email before continuing to Visa/MasterCard payment.',
                422
            );
        }

        $transaction = IoTecSubscriptionTransaction::query()->create([
            'user_id' => $user->id,
            'beneficiary_user_id' => $beneficiary?->id,
            'subscription_plan_id' => (int) $plan->id,
            'external_id' => $externalId,
            'payment_channel' => $channel,
            'card_brand' => $channel === 'card'
                ? ($data['card_brand'] ?? null)
                : null,
            'payer' => $channel === 'card'
                ? $payerEmail
                : trim((string) $data['phone']),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
        ]);

        try {
            $payload = [
                'external_id' => $externalId,
                'payer' => $transaction->payer,
                'payer_name' => $payerName !== ''
                    ? $payerName
                    : $user->name,
                'channel' => 'Web',
                'amount' => $amount,
                'currency' => $currency,
                'payer_note' => 'My Digital Diary subscription',
                'payee_note' => 'Subscription — '.$plan->name,
                'redirect_url' => route(
                    'subscription.pay.iotec.callback'
                ),
            ];

            $response = $channel === 'card'
                ? $this->iotec->collectCard($payload)
                : $this->iotec->collectMobileMoney($payload);

            $requestId = (string) (
                $response['id']
                ?? $response['requestId']
                ?? ''
            );

            $gatewayStatus = strtolower(
                (string) ($response['status'] ?? 'pending')
            );

            $transaction->forceFill([
                'iotec_request_id' => $requestId ?: null,
                'status' => $gatewayStatus,
                'status_code' => $response['statusCode'] ?? null,
                'status_message' => $response['statusMessage'] ?? null,
                'card_redirect_url' =>
                    $response['cardRedirectUrl']
                    ?? $response['card_redirect_url']
                    ?? data_get($response, 'data.cardRedirectUrl'),
                'gateway_response' => $response,
            ])->save();

            /*
             * Rare but valid: if ioTec returns Success immediately,
             * activate now. Otherwise callback/status confirmation will
             * activate later.
             */
            if ($gatewayStatus === 'success') {
                $transaction->forceFill([
                    'paid_at' => now(),
                ])->save();

                $this->activation->activateFromIoTec(
                    $transaction->fresh()
                );
            }

            if ($channel === 'card') {
                /*
                 * ioTec documents cardRedirectUrl as the hosted PegPay/ioTec
                 * page that MUST be opened for the customer to enter card
                 * number, expiry date, CVV and complete bank verification.
                 */
                $redirect = trim(
                    (string) (
                        $response['cardRedirectUrl']
                        ?? $response['card_redirect_url']
                        ?? data_get(
                            $response,
                            'data.cardRedirectUrl',
                            ''
                        )
                    )
                );

                if (
                    $redirect === ''
                    || ! filter_var($redirect, FILTER_VALIDATE_URL)
                ) {
                    \Illuminate\Support\Facades\Log::warning(
                        'ioTec card initiation returned no cardRedirectUrl',
                        [
                            'transaction_id' => $transaction->id,
                            'external_id' => $externalId,
                            'iotec_request_id' => $requestId,
                            'card_endpoint' =>
                                $this->iotec->cardCollectionUrl(),
                            'gateway_response' => $response,
                        ]
                    );

                    return $this->error(
                        $request,
                        'ioTec accepted the card request but did not return its secure card checkout URL. Please check the ioTec card API configuration or contact the administrator.',
                        502
                    );
                }

                $transaction->forceFill([
                    'card_redirect_url' => $redirect,
                ])->save();

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'payment_channel' => 'card',
                        'transaction_id' => $transaction->id,
                        'status' => $transaction->status,
                        'redirect_url' => $redirect,
                        'beneficiary' => $this->beneficiaries->describe($beneficiary),
                    ]);
                }

                /*
                 * Do NOT return back() and do NOT close a local modal here.
                 * The browser must leave My Digital Diary and open ioTec's
                 * hosted card page.
                 */
                return redirect()->away($redirect);
            }

            return response()->json([
                'success' => true,
                'payment_channel' => 'mobile_money',
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
                'beneficiary' => $this->beneficiaries->describe($beneficiary),
                'message' => 'Payment request sent. Approve the prompt on your phone.',
            ]);
        } catch (Throwable $e) {
            report($e);

            $transaction->forceFill([
                'status' => 'failed',
                'status_message' => $e->getMessage(),
            ])->save();

            return $this->error(
                $request,
                'Could not start the ioTec payment. Please try again.',
                502
            );
        }
    }

    public function callback(
        Request $request
    ): RedirectResponse {
        $requestId = (string) (
            $request->query('id')
            ?? $request->query('requestId')
            ?? ''
        );

        if ($requestId === '') {
            return redirect()
                ->route('subscription.show')
                ->with('error', 'Payment reference was not returned.');
        }

        $transaction = IoTecSubscriptionTransaction::query()
            ->where('iotec_request_id', $requestId)
            ->first();

        if (! $transaction) {
            return redirect()
                ->route('subscription.show')
                ->with('error', 'Payment transaction was not found.');
        }

        try {
            /*
             * Do not trust browser query-string "status".
             * Confirm directly against ioTec.
             */
            $result = $this->iotec->status($requestId);

            $this->finalise(
                $transaction,
                $result
            );

            $transaction->refresh();

            if ($transaction->status === 'success') {
                return redirect()
                    ->route('subscription.show')
                    ->with(
                        'success',
                        'Payment successful. Your subscription is now active.'
                    );
            }

            return redirect()
                ->route('subscription.show')
                ->with(
                    'error',
                    $transaction->status_message
                        ?: 'Payment was not successful.'
                );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('subscription.show')
                ->with(
                    'error',
                    'We could not confirm your card payment yet. Please refresh your subscription page shortly.'
                );
        }
    }

    public function status(
        Request $request,
        IoTecSubscriptionTransaction $transaction
    ): JsonResponse {
        abort_unless(
            $transaction->user_id === $request->user()->id,
            403
        );

        if (
            $transaction->status !== 'success'
            && $transaction->iotec_request_id
        ) {
            try {
                $result = $this->iotec->status(
                    $transaction->iotec_request_id
                );

                $this->finalise(
                    $transaction,
                    $result
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        $transaction->refresh();

        return response()->json([
            'success' => true,
            'status' => $transaction->status,
            'activated' => $transaction->activated_at !== null,
            'subscription_status' => strtolower(
                (string) $request->user()->fresh()->subscription_status
            ),
            'beneficiary' => $this->beneficiaries->describe(
                $transaction->beneficiary
            ),
        ]);
    }

    private function finalise(
        IoTecSubscriptionTransaction $transaction,
        array $gateway
    ): void {
        $status = strtolower(
            (string) ($gateway['status'] ?? 'pending')
        );

        $transaction->forceFill([
            'status' => $status,
            'status_code' => $gateway['statusCode'] ?? null,
            'status_message' => $gateway['statusMessage'] ?? null,
            'gateway_response' => $gateway,
            'paid_at' => $status === 'success'
                ? ($transaction->paid_at ?: now())
                : $transaction->paid_at,
        ])->save();

        if (
            $status === 'success'
            && ! $transaction->activated_at
        ) {
            $this->activation->activateFromIoTec(
                $transaction->fresh()
            );
        }
    }

    private function error(
        Request $request,
        string $message,
        int $code
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $code);
        }

        return back()->with('error', $message);
    }
}
