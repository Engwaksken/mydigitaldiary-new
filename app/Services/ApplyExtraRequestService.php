<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\IoTecSubscriptionTransaction;
use App\Models\User;
use App\Models\UserExtraRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ApplyExtraRequestService
{
    /** Days purchased extra minutes stay usable, counted from when they are applied. */
    public const QUOTA_VALIDITY_DAYS = 30;

    public function __construct(
        private readonly IoTecPayService $iotecPayService
    ) {}

    public function createRequest(User $user, array $data): UserExtraRequest
    {
        return DB::transaction(function () use ($user, $data) {
            $quotaAmount = (int) ($data['quota_amount'] ?? 60);
            $amount = (float) ($data['amount'] ?? ($quotaAmount * 100)); // default rate per minute or package
            $currency = strtoupper($data['currency'] ?? $user->preferredCurrencyCode());

            return UserExtraRequest::create([
                'user_id' => $user->id,
                'request_type' => $data['request_type'] ?? 'extra_recording_quota',
                'description' => $data['description'] ?? "Extra {$quotaAmount} minutes recording quota",
                'status' => 'pending',
                'amount' => $amount,
                'currency' => $currency,
                'quota_amount' => $quotaAmount,
                'quota_used' => 0,
                'expires_at' => now()->addDays(self::QUOTA_VALIDITY_DAYS),
            ]);
        });
    }

    public function initiatePayment(UserExtraRequest $request, array $paymentData): array
    {
        $user = $request->user;
        $externalId = 'ex-req-' . $request->id . '-' . Str::random(8);

        $payload = [
            'external_id' => $externalId,
            'amount' => (float) $request->amount,
            'currency' => $request->currency,
            'payer' => $paymentData['payer'] ?? $paymentData['phone_number'] ?? '',
            'payer_name' => $paymentData['payer_name'] ?? $user->name,
            'payer_note' => "Extra quota payment for request #{$request->id}",
            'payee_note' => 'My Digital Diary Extra Quota',
            'redirect_url' => $paymentData['redirect_url'] ?? route('extra-requests.show', $request),
        ];

        $channel = strtolower($paymentData['channel'] ?? $paymentData['payment_channel'] ?? 'mobile_money');

        if ($channel === 'card' && $this->iotecPayService->supportsCard()) {
            $response = $this->iotecPayService->collectCard($payload);
        } else {
            $response = $this->iotecPayService->collectMobileMoney($payload);
        }

        $iotecRequestId = $response['id'] ?? $response['requestId'] ?? null;
        $status = strtolower($response['status'] ?? 'pending');
        $cardRedirectUrl = $response['redirectUrl'] ?? $response['card_redirect_url'] ?? null;

        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $user->id,
            'external_id' => $externalId,
            'iotec_request_id' => $iotecRequestId,
            'payment_channel' => $channel,
            'payer' => $payload['payer'],
            'amount' => $request->amount,
            'currency' => $request->currency,
            'status' => $status,
            'status_code' => $response['statusCode'] ?? null,
            'status_message' => $response['statusMessage'] ?? null,
            'card_redirect_url' => $cardRedirectUrl,
            'gateway_response' => $response,
            'paid_at' => $status === 'success' ? now() : null,
        ]);

        $request->update([
            'iotec_transaction_id' => $transaction->id,
        ]);

        return [
            'request' => $request->fresh(),
            'transaction' => $transaction,
            'card_redirect_url' => $cardRedirectUrl,
            'gateway_response' => $response,
        ];
    }

    public function handleWebhook(array $payload): UserExtraRequest
    {
        $externalId = (string) ($payload['externalId'] ?? $payload['external_id'] ?? '');
        $requestId = (string) ($payload['id'] ?? $payload['requestId'] ?? '');

        $transaction = IoTecSubscriptionTransaction::query()
            ->when($externalId !== '', fn($q) => $q->where('external_id', $externalId))
            ->when($externalId === '' && $requestId !== '', fn($q) => $q->where('iotec_request_id', $requestId))
            ->first();

        if (! $transaction) {
            throw new RuntimeException('Transaction not found for ioTec webhook callback.');
        }

        $status = strtolower((string) ($payload['status'] ?? 'pending'));

        $transaction->forceFill([
            'iotec_request_id' => $requestId ?: $transaction->iotec_request_id,
            'status' => $status,
            'status_code' => $payload['statusCode'] ?? null,
            'status_message' => $payload['statusMessage'] ?? null,
            'gateway_response' => $payload,
            'paid_at' => $status === 'success' ? ($transaction->paid_at ?: now()) : $transaction->paid_at,
        ])->save();

        $extraRequest = UserExtraRequest::where('iotec_transaction_id', $transaction->id)->first();

        if ($extraRequest && $status === 'success' && $extraRequest->status !== 'applied') {
            $this->applyQuota($extraRequest);
        }

        return $extraRequest?->fresh() ?? UserExtraRequest::first();
    }

    public function applyQuota(UserExtraRequest $request): UserExtraRequest
    {
        return DB::transaction(function () use ($request) {
            /*
             * Re-fetch the request inside the transaction with a row lock
             * so concurrent webhook deliveries or a webhook + admin "apply"
             * cannot both pass the outer status check and double-grant.
             */
            /** @var UserExtraRequest $lockedRequest */
            $lockedRequest = UserExtraRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($lockedRequest->status === 'applied') {
                return $lockedRequest->fresh();
            }

            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($lockedRequest->user_id);

            $currentQuota = (int) ($user->extra_recording_quota_minutes ?? 0);
            $newQuota = $currentQuota + $lockedRequest->remainingQuota();

            /*
             * Determine the new extra_quota_expires_at value.
             *
             * Purchased minutes are valid for QUOTA_VALIDITY_DAYS counted
             * from when they are applied, not from when the request was
             * created: an admin applying an older request used to hand out
             * minutes that had already expired, so transcription still
             * reported "exceeds the per-meeting transcription limit".
             *
             * - Current expiry null ("never expires" / lifetime): keep null
             *   so this purchase does not shrink the entitlement.
             * - Current expiry in the future: take the later date.
             * - Current expiry in the past (stale): use the new grant expiry.
             */
            $grantExpiresAt = now()->addDays(self::QUOTA_VALIDITY_DAYS);

            if (is_null($user->extra_quota_expires_at)) {
                $expiresAt = null;
            } elseif ($user->extra_quota_expires_at->gt($grantExpiresAt)) {
                $expiresAt = $user->extra_quota_expires_at;
            } else {
                $expiresAt = $grantExpiresAt;
            }

            $user->forceFill([
                'extra_recording_quota_minutes' => $newQuota,
                'extra_quota_expires_at' => $expiresAt,
            ])->save();

            $lockedRequest->update([
                'status' => 'applied',
                'applied_at' => now(),
                'quota_used' => 0,
                'expires_at' => $grantExpiresAt,
            ]);

            return $lockedRequest->fresh();
        });
    }
}
