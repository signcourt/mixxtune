<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WalletAccount extends Model
{
    protected $table = 'wallets';

    protected $fillable = [
        'public_id',
        'user_id',
        'artist_id',
        'label_id',
        'currency',
        'available_balance',
        'pending_balance',
        'lifetime_credits',
        'lifetime_debits',
        'status',
    ];

    protected $casts = [
        'available_balance' => 'decimal:8',
        'pending_balance' => 'decimal:8',
        'lifetime_credits' => 'decimal:8',
        'lifetime_debits' => 'decimal:8',
    ];

    protected $appends = [
        'lifetime_earnings',
        'withdrawn_balance',
        'hold_balance',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function transactions()
    {
        return $this->hasMany(
            WalletTransaction::class,
            'wallet_id'
        );
    }

    public function getLifetimeEarningsAttribute(): string
    {
        return (string) (
            $this->attributes['lifetime_credits']
            ?? 0
        );
    }

    public function getWithdrawnBalanceAttribute(): string
    {
        return (string) (
            $this->attributes['lifetime_debits']
            ?? 0
        );
    }

    public function getHoldBalanceAttribute(): string
    {
        return '0.00000000';
    }
}
