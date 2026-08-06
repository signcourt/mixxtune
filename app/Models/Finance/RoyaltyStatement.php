<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class RoyaltyStatement extends Model
{
    protected $fillable = [
        'public_id',
        'artist_id',
        'label_id',
        'statement_month',
        'currency',
        'gross_earnings',
        'commission_amount',
        'tax_amount',
        'other_deductions',
        'net_payable',
        'status',
        'approved_at',
        'available_at',
        'paid_at',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'gross_earnings' => 'decimal:8',
        'commission_amount' => 'decimal:8',
        'tax_amount' => 'decimal:8',
        'other_deductions' => 'decimal:8',
        'net_payable' => 'decimal:8',
        'approved_at' => 'datetime',
        'available_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function allocations()
    {
        return $this->hasMany(
            RoyaltyAllocation::class
        );
    }
}
