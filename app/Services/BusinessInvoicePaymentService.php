<?php

namespace App\Services;

use App\Models\BusinessInvoice;
use App\Models\Income;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BusinessInvoicePaymentService
{
    public function markPaid(User $user, BusinessInvoice $invoice, Carbon|string $receivedAt): void
    {
        DB::transaction(function () use ($user, $invoice, $receivedAt): void {
            $locked = BusinessInvoice::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($invoice->id);
            $income = Income::query()->where('business_invoice_id', $locked->id)->first();

            if (! $income) {
                Income::query()->create([
                    'user_id' => $user->id,
                    'business_invoice_id' => $locked->id,
                    'source' => 'Invoice '.$locked->invoice_number.' — '.$locked->customer_name,
                    'category' => 'Business income',
                    'amount' => $locked->total,
                    'frequency' => 'one_time',
                    'received_at' => $receivedAt,
                    'notes' => 'Payment received for business invoice '.$locked->invoice_number.'.',
                ]);
            }

            $locked->forceFill([
                'status' => 'paid',
                'paid_at' => $locked->paid_at ?? Carbon::parse($receivedAt)->endOfDay(),
            ])->save();
        });
    }
}
