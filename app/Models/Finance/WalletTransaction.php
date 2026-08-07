<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'public_id',
        'wallet_id',
        'user_id',
        'artist_id',
        'label_id',
        'transaction_type',
        'direction',
        'amount',
        'currency',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'status',
        'effective_at',
        'posted_at',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'effective_at' => 'datetime',
        'posted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function wallet()
    {
        return $this->belongsTo(
            WalletAccount::class,
            'wallet_id'
        );
    }
}
