<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IoTecSubscriptionTransaction;
use App\Services\IoTecPayService;
use App\Services\SubscriptionBeneficiaryService;
use App\Services\SubscriptionPaymentActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class IoTecSubscriptionController extends Controller
{
    public function __construct(
        private readonly IoTecPayService $iotec,
        private readonly SubscriptionPaymentActivationService $activation,
        private readonly SubscriptionBeneficiaryService $beneficiaries
    ) {}

    /**
     * Mobile-safe gateway capability metadata.
     *
     * Secrets and internal gateway URLs are NEVER returned.
     */
    public function options(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'gateway' => [
                    'code' => 'iotec',
                    'enabled' => $this->iotec->configured(),
                    'supports_mobile_money' => true,
                    'supports_card' => $this->iotec->supportsCard(),
                    'card_brands' => $this->iotec->supportsCard()
                        ? ['visa', 'mastercard']
                        : [],
                    'currency' => 'UGX',
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => true,
                'gateway' => [
                    'code' => 'iotec',
                    'enabled' => false,
                    'supports_mobile_money' => false,
                    'supports_card' => false,
                    'card_brands' => [],
                    'currency' => 'UGX',
                ],
            ]);
        }
    }

    /**
     * Start an ioTec Mobile Money or Visa/MasterCard subscription payment.
     */
    public function initiate(Request $request): JsonResponse
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
            // Optional: pay for another existing user's subscription.
            'beneficiary_email' => ['nullable', 'email', 'max:255'],
        ]);

        $beneficiary = $this->beneficiaries->resolveForPayment(
            $request->user(),
            $data['beneficiary_email'] ?? null
        );

        if (! Schema::hasTable('subscription_plans')) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plans are not configured.',
            ], 422);
        }

        $plan = DB::table('subscription_plans')
            ->where('id', (int) $data['subscription_plan_id'])
            ->first();

        if (! $plan) {
            return response()->json([
                'success' => false,
                'message' => 'Selected subscription plan was not found.',
            ], 422);
        }

        $amount = $this->planAmount($plan);

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'The selected subscription plan has no valid payment amount.',
            ], 422);
        }

        $user = $request->user();
        $channel = $data['payment_channel'];

        if ($channel === 'mobile_money' && blank($data['phone'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Enter the Mobile Money number.',
            ], 422);
        }

        if ($channel === 'card' && ! $this->iotec->supportsCard()) {
            return response()->json([
                'success' => false,
                'message' => 'Visa / MasterCard is not enabled for the ioTec gateway.',
            ], 422);
        }

        if ($channel === 'card' && blank($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'An email address is required for Visa / MasterCard payment.',
            ], 422);
        }

        $transaction = IoTecSubscriptionTransaction::query()->create([
            'user_id' => $user->id,
            'beneficiary_user_id' => $beneficiary?->id,
            'subscription_plan_id' => (int) $plan->id,
            'external_id' => (string) Str::uuid(),
            'payment_channel' => $channel,
            'card_brand' => $channel === 'card'
                ? ($data['card_brand'] ?? null)
                : null,
            'payer' => $channel === 'card'
                ? $user->email
                : trim((string) $data['phone']),
            'amount' => $amount,
            'currency' => 'UGX',
            'status' => 'pending',
        ]);

        try {
            $payload = [
                'external_id' => $transaction->external_id,
                'payer' => $transaction->payer,
                'payer_name' => $user->name,
                'amount' => $amount,
                'currency' => 'UGX',
                'payer_note' => 'My Digital Diary subscription',
                'payee_note' => 'Subscription plan #'.$plan->id,

                /*
                 * Mobile opens the hosted page externally, so returning to
                 * the normal public web callback is safe. Flutter also polls
                 * transaction status after the user returns to the app.
                 */
                'redirect_url' => route('subscription.pay.iotec.callback'),
            ];

            $gateway = $channel === 'card'
                ? $this->iotec->collectCard($payload)
                : $this->iotec->collectMobileMoney($payload);

            $requestId = (string) (
                $gateway['id']
                ?? $gateway['requestId']
                ?? ''
            );

            $status = strtolower(
                (string) ($gateway['status'] ?? 'pending')
            );

            $transaction->forceFill([
                'iotec_request_id' => $requestId ?: null,
                'status' => $status,
                'status_code' => $gateway['statusCode'] ?? null,
                'status_message' => $gateway['statusMessage'] ?? null,
                'card_redirect_url' => $gateway['cardRedirectUrl'] ?? null,
                'gateway_response' => $gateway,
                'paid_at' => $status === 'success' ? now() : null,
            ])->save();

            if ($status === 'success') {
                $this->activation->activateFromIoTec(
                    $transaction->fresh()
                );
            }

            return response()->json([
                'success' => true,
                'transaction_id' => $transaction->id,
                'payment_channel' => $channel,
                'status' => $transaction->fresh()->status,
                'redirect_url' => $channel === 'card'
                    ? ($gateway['cardRedirectUrl'] ?? null)
                    : null,
                'beneficiary' => $this->beneficiaries->describe($beneficiary),
                'message' => $channel === 'card'
                    ? 'Continue to the secure Visa / MasterCard checkout.'
                    : 'Payment request sent. Approve the prompt on your phone.',
            ]);
        } catch (Throwable $e) {
            report($e);

            $transaction->forceFill([
                'status' => 'failed',
                'status_message' => $e->getMessage(),
            ])->save();

            return response()->json([
                'success' => false,
                'transaction_id' => $transaction->id,
                'status' => 'failed',
                'message' => 'Could not start the ioTec payment. Please try again.',
            ], 502);
        }
    }

    /**
     * Poll an ioTec transaction from Mobile.
     *
     * This re-checks ioTec directly while payment is not final and activates
     * the subscription if the authoritative gateway status becomes Success.
     */
    public function status(
        Request $request,
        IoTecSubscriptionTransaction $transaction
    ): JsonResponse {
        abort_unless(
            (int) $transaction->user_id === (int) $request->user()->id,
            403
        );

        $finalStatuses = [
            'success',
            'failed',
            'cancelled',
            'canceled',
            'rejected',
        ];

        if (
            ! in_array(strtolower((string) $transaction->status), $finalStatuses, true)
            && filled($transaction->iotec_request_id)
        ) {
            try {
                $gateway = $this->iotec->status(
                    $transaction->iotec_request_id
                );

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
            } catch (Throwable $e) {
                /*
                 * Keep polling response usable even if a transient status
                 * request fails. The callback can still complete activation.
                 */
                report($e);
            }
        }

        $transaction->refresh();
        $user = $request->user()->fresh();

        return response()->json([
            'success' => true,
            'transaction' => [
                'id' => $transaction->id,
                'status' => strtolower((string) $transaction->status),
                'status_message' => $transaction->status_message,
                'payment_channel' => $transaction->payment_channel,
                'paid' => $transaction->paid_at !== null,
                'activated' => $transaction->activated_at !== null,
                'beneficiary' => $this->beneficiaries->describe(
                    $transaction->beneficiary
                ),
            ],
            'subscription' => [
                'status' => strtolower(
                    (string) ($user->subscription_status ?? '')
                ),
                'plan_id' => $user->subscription_plan_id ?? null,
                'started_at' => optional($user->subscription_started_at)
                    ?->toIso8601String(),
                'expires_at' => optional($user->subscription_expires_at)
                    ?->toIso8601String(),
                'trial_ends_at' => optional($user->trial_ends_at)
                    ?->toIso8601String(),
            ],
        ]);
    }

    private function planAmount(object $plan): float
    {
        foreach ([
            'price',
            'amount',
            'price_ugx',
            'monthly_price',
            'subscription_fee',
        ] as $column) {
            if (
                Schema::hasColumn('subscription_plans', $column)
                && isset($plan->{$column})
                && (float) $plan->{$column} > 0
            ) {
                return (float) $plan->{$column};
            }
        }

        return 0;
    }
}
