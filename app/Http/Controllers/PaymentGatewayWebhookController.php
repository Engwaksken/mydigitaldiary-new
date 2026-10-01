<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use App\Models\PaymentTransactionLog;
use App\PaymentGateways\PaymentGatewayDriverFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * One webhook endpoint, parameterized by gateway_code — every
 * provider's own payload format is normalized by its driver's
 * parseWebhookPayload() (see PaymentGatewayDriverInterface); the actual
 * "mark payment completed, activate subscription" logic below is
 * provider-agnostic and never needs to change when a new aggregator is
 * added.
 */
class PaymentGatewayWebhookController extends Controller
{
    public function handle(Request $request, string $gatewayCode): Response
    {
        $gateway = PaymentGateway::where('gateway_code', $gatewayCode)->where('is_enabled', true)->first();

        if (! $gateway) {
            return response('Unknown or inactive gateway', 404);
        }

        /*
         * Every gateway must have a webhook_secret configured in its
         * encrypted config before this endpoint will process anything.
         * This FAILS CLOSED: a gateway without a secret, or a request
         * whose X-Webhook-Secret header does not match, is rejected with
         * 401 before any payload is parsed or any payment state changes.
         */
        $configuredSecret = (string) $gateway->configValue('webhook_secret', '');

        abort_unless($configuredSecret !== '', 401);

        $received = (string) $request->header('X-Webhook-Secret', '');

        abort_unless(hash_equals($configuredSecret, $received), 401);

        try {
            $driver = PaymentGatewayDriverFactory::make($gateway);
            $parsed = $driver->parseWebhookPayload($request->all());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Payment gateway webhook parsing failed', ['gateway' => $gatewayCode, 'error' => $e->getMessage()]);

            return response('Could not parse payload', 400);
        }

        if (! $parsed['external_reference']) {
            return response('Missing transaction reference', 400);
        }

        $log = PaymentTransactionLog::where('payment_gateway_id', $gateway->id)
            ->where('external_reference', $parsed['external_reference'])
            ->first();

        if (! $log) {
            // Acknowledge with 200 anyway — the provider will otherwise
            // retry indefinitely for a reference we simply don't recognize.
            return response('Unknown transaction reference — acknowledged, no action taken.', 200);
        }

        $log->update([
            'status' => $parsed['status'],
            'response_payload' => json_encode($request->all()),
        ]);

        $this->applyPaymentStatus($log, $parsed['status']);

        return response('OK', 200);
    }

    /**
     * Mirrors SubscriptionController::payWithCardCallback()'s activation
     * logic exactly, so a subscription paid via ANY aggregator behaves
     * identically to one paid by card. Duplicated rather than shared
     * since that method is private to SubscriptionController.
     */
    private function applyPaymentStatus(PaymentTransactionLog $log, string $status): void
    {
        if (! $log->payment) {
            return;
        }

        if ($status === 'completed') {
            $updates = ['status' => 'completed'];

            if (\Illuminate\Support\Facades\Schema::hasColumn('payments', 'gateway_transaction_id')) {
                $updates['gateway_transaction_id'] = $log->external_reference;
            }

            $log->payment->update($updates);
            $log->payment->assignReceiptNumber();
            $log->payment->invoice?->update(['status' => 'paid']);

            $user = $log->payment->user;
            // The subscription goes to the beneficiary when the payer paid
            // for someone else; notifications and the receipt stay with
            // the payer ($user).
            $subscriber = $log->payment->subscriptionRecipient();
            $plan = $log->payment->plan;

            if ($user) {
                $expiresAt = null;
                if ($plan && ! $plan->isLifetime()) {
                    $base = ($subscriber->subscription_expires_at && $subscriber->subscription_expires_at->isFuture())
                        ? $subscriber->subscription_expires_at
                        : now();
                    $expiresAt = $base->copy()->addMonths($plan->duration_months);
                }

                $subscriber->forceFill([
                    'subscription_status' => 'active',
                    'subscribed_at' => $subscriber->subscribed_at ?? now(),
                    'subscription_plan_id' => $plan?->id,
                    'subscription_expires_at' => $expiresAt,
                    'last_expiry_reminder_days' => null,
                ])->save();

                app(\App\Services\SubscriptionAdminNotificationService::class)->notify($subscriber);

                // Same "a Family/Team or Organization plan needs an
                // actual Organization row to manage seats against" logic
                // as SubscriptionController::ensureOrganizationForPlan() —
                // duplicated rather than shared since that method is
                // private to a different controller.
                if ($plan && ! $plan->isIndividual()) {
                    $organization = \App\Models\Organization::where('owner_user_id', $subscriber->id)->first();
                    if ($organization) {
                        $organization->update(['subscription_plan_id' => $plan->id]);
                    } else {
                        \App\Models\Organization::create([
                            'name' => $subscriber->name . "'s Organization",
                            'owner_user_id' => $subscriber->id,
                            'subscription_plan_id' => $plan->id,
                        ]);
                    }
                }

                \App\Models\BillingEventLog::record('payment_status_changed', $user->id, ['payment_id' => $log->payment->id, 'details' => 'completed via ' . $log->gateway?->gateway_code]);

                $user->notify(new \App\Notifications\PaymentSuccessfulNotification($log->payment));

                try {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\PaymentReceiptMail($log->payment));
                    \App\Models\BillingEventLog::record('receipt_emailed', $user->id, ['payment_id' => $log->payment->id, 'recipient_email' => $user->email]);
                } catch (\Throwable $e) {
                    \App\Models\BillingEventLog::record('receipt_emailed', $user->id, [
                        'payment_id' => $log->payment->id, 'recipient_email' => $user->email, 'status' => 'failed', 'details' => $e->getMessage(),
                    ]);
                }
            }
        } elseif ($status === 'failed') {
            $updates = ['status' => 'failed'];

            if (\Illuminate\Support\Facades\Schema::hasColumn('payments', 'gateway_transaction_id')) {
                $updates['gateway_transaction_id'] = $log->external_reference;
            }

            $log->payment->update($updates);
            \App\Models\BillingEventLog::record('payment_status_changed', $log->payment->user_id, ['payment_id' => $log->payment->id, 'status' => 'failed', 'details' => 'mobile money collection failed']);

            $log->payment->user?->notify(new \App\Notifications\PaymentFailedNotification($log->payment, 'Mobile money collection failed.'));
        }
    }
}
