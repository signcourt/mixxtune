<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoupmentRecovery extends Model
{
    protected $fillable = [
        'recoupment_plan_id',
        'royalty_statement_id',
        'wallet_transaction_id',
        'source_amount',
        'base_percentage',
        'recovery_percentage',
        'calculated_recovery_amount',
        'applied_recovery_amount',
        'outstanding_before',
        'outstanding_after',
        'reporting_month',
        'reference',
        'idempotency_key',
    ];

    protected $casts = [
        'source_amount' => 'decimal:8',
        'base_percentage' => 'decimal:4',
        'recovery_percentage' => 'decimal:4',
        'calculated_recovery_amount' => 'decimal:8',
        'applied_recovery_amount' => 'decimal:8',
        'outstanding_before' => 'decimal:8',
        'outstanding_after' => 'decimal:8',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            RecoupmentPlan::class,
            'recoupment_plan_id'
        );
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(
            RoyaltyStatement::class,
            'royalty_statement_id'
        );
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransaction::class,
            'wallet_transaction_id'
        );
    }
}
