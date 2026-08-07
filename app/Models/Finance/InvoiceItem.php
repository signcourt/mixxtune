<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'rate',
        'amount',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'rate' => 'decimal:8',
        'amount' => 'decimal:8',
        'meta' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(
            Invoice::class
        );
    }
}
