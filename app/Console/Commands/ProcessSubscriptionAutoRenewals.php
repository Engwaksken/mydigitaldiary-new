<?php

namespace App\Console\Commands;

use App\Models\BillingEventLog;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SiteSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessSubscriptionAutoRenewals extends Command
{
    protected $signature = 'subscriptions:auto-renew {--limit=100}';

    protected $description = 'Start subscription renewal collections for opted-in users whose subscriptions have expired.';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $now = now();

        User::query()
            ->where('auto_renew_subscription', true)
            ->whereNotNull('subscription_plan_id')
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('last_auto_renew_attempt_at')
                    ->orWhere('last_auto_renew_attempt_at', '<', $now->copy()->subHours(20));
            })
            ->orderBy('subscription_expires_at')
            ->limit($limit)
            ->get()
            ->each(fn (User $user) => $this->processUser($user));

        return self::SUCCESS;
    }

    private function processUser(User $user): void
    {
        /*
         * Mark the attempt before calling the remote gateway. If the command
         * is triggered twice by overlapping cron executions, this prevents
         * duplicate renewal prompts/invoices.
         */
        $user->forceFill([
            'last_auto_renew_attempt_at' => now(),
        ])->save();

        try {
            $plan = SubscriptionPlan::query()
                ->whereKey($user->subscription_plan_id)
                ->where('is_enabled', true)
                ->first();

            if (! $plan || $plan->isLifetime()) {
                $this->disable($user, 'Plan is missing, disabled or lifetime.');
                return;
            }

            $phone = trim((string) $user->auto_renew_phone);
            $network = strtolower(trim((string) $user->auto_renew_network));

            if ($phone === '' || ! in_array($network, ['mtn', 'airtel'], true)) {
                $this->disable($user, 'Renewal phone/network is incomplete.');
                return;
            }

            $gateway = null;

            if ($user->auto_renew_payment_gateway_id) {
                $candidate = PaymentGateway::query()
                    ->whereKey($user->auto_renew_payment_gateway_id)
                    ->where('is_enabled', true)
                    ->first();

                if ($candidate && $candidate->collectsAutomatically()) {
                    $gateway = $candidate;
                }
            }

            $gateway ??= PaymentGateway::query()
                ->where('is_enabled', true)
                ->whereIn('type', ['mobile_money', 'aggregator'])
                ->get()
                ->first(fn ($candidate) => $candidate->collectsAutomatically());

            if (! $gateway) {
                BillingEventLog::record('subscription_auto_renew_failed', $user->id, [
                    'details' => 'No enabled automatic collection gateway.',
                ]);
                return;
            }

            if ($network === 'mtn' && ! $gateway->supports_mtn) {
                $this->disable($user, 'MTN is no longer enabled on the renewal gateway.');
                return;
            }

            if ($network === 'airtel' && ! $gateway->supports_airtel) {
                $this->disable($user, 'Airtel is no longer enabled on the renewal gateway.');
                return;
            }

            /*
             * Do not create another outstanding renewal when one is already
             * pending for this account/plan.
             */
            $alreadyPending = Payment::query()
                ->where('user_id', $user->id)
                ->where('subscription_plan_id', $plan->id)
                ->where('status', 'pending')
                ->where('reference', 'like', 'autorenew_%')
                ->exists();

            if ($alreadyPending) {
                return;
            }

            $settings = SiteSetting::current();
            $personalPrice = $user->effectiveSubscriptionPrice();
            $price = $personalPrice['price'] ?? $plan->computedPrice((float) $settings->monthly_price);
            $currency = $personalPrice['currency_code'] ?? $settings->default_currency_code;
            $reference = sprintf(
                'autorenew_%d_%s_%04d',
                $user->id,
                now()->format('YmdHis'),
                random_int(1000, 9999)
            );

            $payment = Payment::create([
                'user_id' => $user->id,
                'payment_gateway_id' => $gateway->id,
                'subscription_plan_id' => $plan->id,
                'method' => 'mobile_money',
                'amount' => $price,
                'currency' => $currency,
                'status' => 'pending',
                'reference' => $reference,
            ]);

            $invoice = $this->createInvoice($payment, $plan, $user);

            $driver = \App\PaymentGateways\PaymentGatewayDriverFactory::make($gateway);

            $driver->initiateCollection(
                $reference,
                $price,
                $currency,
                $phone,
                $network,
                $user->id,
                $payment->id
            );

            BillingEventLog::record('subscription_auto_renew_started', $user->id, [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'payment_gateway_id' => $gateway->id,
                'subscription_plan_id' => $plan->id,
                'phone_number' => $phone,
                'network' => $network,
            ]);

            $this->info("Auto renewal prompt sent for user {$user->id}.");
        } catch (Throwable $e) {
            Log::warning('Subscription auto renewal failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            BillingEventLog::record('subscription_auto_renew_failed', $user->id, [
                'details' => $e->getMessage(),
            ]);
        }
    }

    private function createInvoice(
        Payment $payment,
        SubscriptionPlan $plan,
        User $user
    ): \App\Models\Invoice {
        $periodStart = now();
        $periodEnd = $periodStart->copy()->addMonths($plan->duration_months);

        $invoice = \App\Models\Invoice::create([
            'invoice_number' => \App\Models\Invoice::generateInvoiceNumber(),
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'unpaid',
            'billing_period_start' => $periodStart->toDateString(),
            'billing_period_end' => $periodEnd->toDateString(),
            'due_date' => now()->toDateString(),
        ]);

        BillingEventLog::record('invoice_generated', $user->id, [
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'details' => 'auto renewal',
        ]);

        try {
            Mail::to($user->email)->send(new \App\Mail\InvoiceMail($invoice));
            BillingEventLog::record('invoice_emailed', $user->id, [
                'invoice_id' => $invoice->id,
                'recipient_email' => $user->email,
                'details' => 'auto renewal',
            ]);
        } catch (Throwable $e) {
            Log::warning('Auto renewal invoice email failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $invoice;
    }

    private function disable(User $user, string $reason): void
    {
        $user->forceFill([
            'auto_renew_subscription' => false,
            'auto_renew_disabled_at' => now(),
        ])->save();

        BillingEventLog::record('subscription_auto_renew_disabled', $user->id, [
            'details' => $reason,
        ]);
    }
}
