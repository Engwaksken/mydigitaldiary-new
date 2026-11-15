<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'user_id', 'enterprise_inquiry_id', 'subscription_plan_id', 'payment_id',
        'description', 'amount', 'currency', 'status', 'billing_period_start', 'billing_period_end', 'due_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'due_date' => 'date',
    ];

    /**
     * A "quotation" isn't a separate document type - it's this same
     * model with status='quote', sent before a deal has closed and no
     * user/payment necessarily exists yet. Once accepted, the same row
     * flips to 'unpaid' (see AdminEnterpriseInquiryController::
     * convertToInvoice()) and becomes a real invoice rather than a
     * second record being created.
     */
    public function isQuote(): bool
    {
        return $this->status === 'quote';
    }

    /**
     * Only a quotation that was never accepted, or an invoice that was
     * never paid, is safe to delete - a 'paid' record is a real
     * financial transaction that has to stay for audit/accounting
     * purposes no matter how old or cluttered the list gets, and
     * 'cancelled' already reflects a deliberate decision worth keeping
     * a trail of.
     */
    public function isDeletable(): bool
    {
        return in_array($this->status, ['quote', 'unpaid'], true);
    }

    public function enterpriseInquiry()
    {
        return $this->belongsTo(EnterpriseInquiry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Contact number shown in admin billing tables. Enterprise documents
     * use the inquiry phone; self-service invoices fall back to the phone
     * captured by the related mobile-money transaction when available.
     */
    public function getContactPhoneAttribute(): ?string
    {
        return $this->enterpriseInquiry?->phone
            ?: $this->payment?->contact_phone;
    }

    /**
     * Same YEAR-0001 scheme as Payment::assignReceiptNumber(), kept as
     * its own independent sequence - invoices and receipts aren't the
     * same document and numbering them together would be misleading.
     */
    public static function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $lastNumber = static::where('invoice_number', 'like', 'INV-' . $year . '-%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, -4)) + 1 : 1;

        return 'INV-' . $year . '-' . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }
}
