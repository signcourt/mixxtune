<?php

namespace App\Models\Finance;

use App\Models\Finance\WithdrawalRequest;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'public_id',
        'invoice_number',
        'user_id',
        'royalty_statement_id',
        'invoice_type',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_amount',
        'tds_amount',
        'total_amount',
        'status',
        'billing_details',
        'company_details',
        'notes',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:8',
        'tax_amount' => 'decimal:8',
        'tds_amount' => 'decimal:8',
        'total_amount' => 'decimal:8',
        'billing_details' => 'array',
        'company_details' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function statement()
    {
        return $this->belongsTo(
            RoyaltyStatement::class,
            'royalty_statement_id'
        );
    }

    public function items()
    {
        return $this->hasMany(
            InvoiceItem::class
        );
    }

    public function withdrawal()
    {
        return $this->belongsTo(
            WithdrawalRequest::class,
            'withdrawal_id'
        );
    }

}
