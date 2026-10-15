<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'business_invoice_id', 'source', 'amount', 'category', 'frequency', 'received_at', 'notes', 'is_archived',];

    protected $casts = [
        'received_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function businessInvoice()
    {
        return $this->belongsTo(BusinessInvoice::class);
    }
}
