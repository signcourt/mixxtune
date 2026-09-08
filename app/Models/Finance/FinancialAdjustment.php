<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAdjustment extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_transaction_id',
        'reversal_of_id',
        'adjustment_number',
        'idempotency_key',
        'type',
        'direction',
        'amount',
        'reference_number',
        'reason',
        'internal_note',
        'effective_date',
        'status',
        'created_by',
        'reversed_by',
        'reversed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'effective_date' => 'date',
        'reversed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransaction::class,
            'wallet_transaction_id'
        );
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'reversal_of_id'
        );
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(
            self::class,
            'reversal_of_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
