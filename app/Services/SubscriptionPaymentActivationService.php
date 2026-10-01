<?php

namespace App\Services;

use App\Models\IoTecSubscriptionTransaction;
use App\Models\BillingEventLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SubscriptionPaymentActivationService
{
    public function activateFromIoTec(
        IoTecSubscriptionTransaction $transaction
    ): User {
        if (strtolower((string) $transaction->status) !== 'success') {
            throw new RuntimeException(
                'Subscription cannot be activated before payment is successful.'
            );
        }

        return DB::transaction(function () use ($transaction) {
            /*
             * The subscription goes to the beneficiary when the payer paid
             * on someone else's behalf; the payment, invoice and receipt
             * always stay with the payer (transaction->user_id).
             */
            $recipientId = $this->recipientId($transaction);

            /** @var User $user */
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($recipientId);

            $expiresAt = $this->resolveExpiry(
                $user,
                $transaction->subscription_plan_id
            );

            $updates = [
                'subscription_status' => 'active',
                'trial_ends_at' => null,
                'subscription_started_at' => now(),
                'subscription_expires_at' => $expiresAt,
            ];

            if (
                $transaction->subscription_plan_id
                && Schema::hasColumn('users', 'subscription_plan_id')
            ) {
                $updates['subscription_plan_id'] =
                    $transaction->subscription_plan_id;
            }

            $user->forceFill($updates)->save();

            $payer = (int) $user->id === (int) $transaction->user_id
                ? $user
                : User::query()->findOrFail($transaction->user_id);

            $payment = $this->syncPaymentRecord(
                $transaction,
                $payer,
                $recipientId,
                $expiresAt
            );

            $transactionUpdates = ['activated_at' => now()];

            if (Schema::hasColumn('iotec_subscription_transactions', 'payment_id')) {
                $transactionUpdates['payment_id'] = $payment?->id;
            }

            $transaction->forceFill($transactionUpdates)->save();

            /*
             * Grant the plan's included recording minutes. The
             * activation_event_key is unique so replaying the same
             * transaction id is idempotent.
             */
            if (
                class_exists(\App\Services\SubscriptionRecordingQuotaGrantService::class)
                && Schema::hasTable('subscription_recording_extra_grants')
            ) {
                $grantService = app(\App\Services\SubscriptionRecordingQuotaGrantService::class);
                $plan = SubscriptionPlan::query()->find($transaction->subscription_plan_id);
                $grantService->grantIncludedMinutes(
                    $user,
                    $plan,
                    $grantService->key('payment', $transaction->id),
                    $expiresAt,
                    $payment,
                    'payment_activation'
                );
            }

            app(SubscriptionAdminNotificationService::class)->notify($user);

            return $user->fresh();
        });
    }

    /**
     * The beneficiary when the transaction was paid for another user who
     * still exists, otherwise the payer.
     */
    private function recipientId(IoTecSubscriptionTransaction $transaction): int
    {
        if (
            Schema::hasColumn('iotec_subscription_transactions', 'beneficiary_user_id')
            && $transaction->beneficiary_user_id
            && User::query()->whereKey($transaction->beneficiary_user_id)->exists()
        ) {
            return (int) $transaction->beneficiary_user_id;
        }

        return (int) $transaction->user_id;
    }

    private function syncPaymentRecord(
        IoTecSubscriptionTransaction $transaction,
        User $user,
        int $recipientId,
        ?Carbon $expiresAt
    ): ?Payment {
        if (! Schema::hasTable('payments')) {
            return null;
        }

        $reference = $this->paymentReference($transaction);
        $payment = null;

        if (
            Schema::hasColumn('iotec_subscription_transactions', 'payment_id')
            && $transaction->payment_id
        ) {
            $payment = Payment::query()->find($transaction->payment_id);
        }

        if (! $payment && $reference !== '') {
            $payment = Payment::query()
                ->where('user_id', $user->id)
                ->where('reference', $reference)
                ->first();
        }

        $payment ??= new Payment();

        $paymentData = [
            'user_id' => $user->id,
            'payment_gateway_id' => $this->iotecPaymentGatewayId(),
            'subscription_plan_id' => $transaction->subscription_plan_id,
            'method' => $transaction->payment_channel === 'card'
                ? 'card'
                : 'mobile_money',
            'amount' => $transaction->amount,
            'currency' => $transaction->currency ?: 'UGX',
            'status' => 'completed',
            'reference' => $reference,
            'notes' => 'Confirmed automatically from ioTec gateway status.',
        ];

        if (Schema::hasColumn('payments', 'gateway_transaction_id')) {
            $paymentData['gateway_transaction_id'] = $reference;
        }

        if (Schema::hasColumn('payments', 'beneficiary_user_id')) {
            $paymentData['beneficiary_user_id'] = $recipientId !== (int) $user->id
                ? $recipientId
                : null;
        }

        $payment->forceFill($paymentData)->save();

        $payment->assignReceiptNumber();
        $invoice = $this->syncInvoice($payment, $transaction, $user, $expiresAt);

        if (Schema::hasTable('billing_event_logs')) {
            BillingEventLog::record('payment_status_changed', $user->id, [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice?->id,
                'details' => 'completed automatically from ioTec gateway status',
            ]);
        }

        return $payment->fresh();
    }

    private function syncInvoice(
        Payment $payment,
        IoTecSubscriptionTransaction $transaction,
        User $user,
        ?Carbon $expiresAt
    ): ?Invoice {
        if (! Schema::hasTable('invoices')) {
            return null;
        }

        $invoice = Invoice::query()
            ->where('payment_id', $payment->id)
            ->first();

        if (! $invoice) {
            $invoice = new Invoice();
            $invoice->invoice_number = Invoice::generateInvoiceNumber();
            $invoice->payment_id = $payment->id;
        }

        $invoice->forceFill([
            'user_id' => $user->id,
            'subscription_plan_id' => $transaction->subscription_plan_id,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency ?: 'UGX',
            'status' => 'paid',
            'billing_period_start' => now()->toDateString(),
            'billing_period_end' => $expiresAt?->toDateString(),
            'due_date' => now()->toDateString(),
        ])->save();

        return $invoice->fresh();
    }

    private function paymentReference(IoTecSubscriptionTransaction $transaction): string
    {
        return trim((string) (
            $transaction->iotec_request_id
            ?: $transaction->external_id
            ?: ('iotec-'.$transaction->id)
        ));
    }

    private function iotecPaymentGatewayId(): ?int
    {
        if (! Schema::hasTable('payment_gateways')) {
            return null;
        }

        $gateway = PaymentGateway::query()
            ->where('gateway_code', 'iotec')
            ->orWhere(function ($query) {
                $query->whereIn('type', ['aggregator', 'mobile_money'])
                    ->where('is_default', true);
            })
            ->orderByDesc('is_default')
            ->first();

        return $gateway?->id;
    }

    /**
     * Resolve the subscription expiry for the given user and plan.
     *
     * Returns null for lifetime plans (duration_months is null and no
     * term-based billing cycle), meaning the subscription never expires.
     * For term-based plans, returns a Carbon date at end-of-day after
     * adding the plan's duration to the base date.
     *
     * @return Carbon|null null when the plan is a lifetime plan
     */
    private function resolveExpiry(
        User $user,
        ?int $planId
    ): ?Carbon {
        $base = now();

        /*
         * If the user already has a paid subscription extending into the
         * future, renew from the current paid expiry rather than losing days.
         */
        if ($user->subscription_expires_at) {
            $existing = Carbon::parse($user->subscription_expires_at);

            if ($existing->isFuture()) {
                $base = $existing;
            }
        }

        $months = 1;

        if (
            $planId
            && Schema::hasTable('subscription_plans')
        ) {
            $plan = DB::table('subscription_plans')
                ->where('id', $planId)
                ->first();

            if ($plan) {
                foreach ([
                    'duration_months',
                    'billing_months',
                    'months',
                ] as $column) {
                    if (
                        Schema::hasColumn('subscription_plans', $column)
                        && isset($plan->{$column})
                        && (int) $plan->{$column} > 0
                    ) {
                        $months = (int) $plan->{$column};
                        break;
                    }
                }

                if (
                    $months === 1
                    && Schema::hasColumn('subscription_plans', 'billing_cycle')
                    && isset($plan->billing_cycle)
                ) {
                    $cycle = strtolower((string) $plan->billing_cycle);

                    $months = match ($cycle) {
                        'annual', 'yearly', 'year' => 12,
                        'quarterly', 'quarter' => 3,
                        'semiannual', 'semi-annual', 'half-year' => 6,
                        default => 1,
                    };
                }

                // Lifetime plan: no fixed duration and no billing cycle
                // that indicates a term-based subscription.
                $hasDuration = (int) ($plan->duration_months ?? 0) > 0;

                $hasTermCycle = false;
                if (Schema::hasColumn('subscription_plans', 'billing_cycle') && isset($plan->billing_cycle)) {
                    $cycle = strtolower((string) $plan->billing_cycle);
                    $hasTermCycle = in_array($cycle, [
                        'monthly', 'annual', 'yearly', 'year',
                        'quarterly', 'quarter', 'semiannual',
                        'semi-annual', 'half-year',
                    ], true);
                }

                if (! $hasDuration && ! $hasTermCycle) {
                    return null;
                }
            }
        }

        return $base->copy()->addMonthsNoOverflow($months)->endOfDay();
    }
}
