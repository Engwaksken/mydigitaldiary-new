<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessInvoice extends Model
{
    protected $fillable = [
        'user_id', 'template_id', 'invoice_number', 'business_name', 'business_address',
        'business_email', 'business_phone', 'customer_name', 'customer_email',
        'line_items', 'currency', 'total', 'issue_date', 'due_date', 'status', 'sent_at', 'paid_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BusinessInvoiceTemplate::class, 'template_id');
    }

    public function income(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Income::class, 'business_invoice_id');
    }

    protected function casts(): array
    {
        return [
            'line_items' => 'array',
            'total' => 'decimal:2',
            'issue_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
