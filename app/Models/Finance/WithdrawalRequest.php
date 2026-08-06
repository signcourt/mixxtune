<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    protected $table = 'withdrawals';

    protected $fillable = [
        'public_id',
        'withdrawal_number',
        'wallet_id',
        'user_id',
        'artist_id',
        'label_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_reference',
        'note',
        'requested_at',
        'approved_at',
        'paid_at',
        'rejected_at',
        'approved_by',
        'created_by',
        'updated_by',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'request_number',
        'wallet_account_id',
        'request_note',
        'admin_note',
        'rejection_reason',
        'processed_by',
        'fee_amount',
        'tax_amount',
        'net_amount',
        'payout_profile_id',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function wallet()
    {
        return $this->belongsTo(
            WalletAccount::class,
            'wallet_id'
        );
    }

    public function approver()
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    public function getRequestNumberAttribute(): ?string
    {
        return $this->withdrawal_number;
    }

    public function getWalletAccountIdAttribute(): ?int
    {
        return $this->wallet_id
            ? (int) $this->wallet_id
            : null;
    }

    public function getRequestNoteAttribute(): ?string
    {
        return $this->note;
    }

    public function getAdminNoteAttribute(): ?string
    {
        return $this->metadata['admin_note']
            ?? null;
    }

    public function getRejectionReasonAttribute(): ?string
    {
        return $this->metadata['rejection_reason']
            ?? (
                $this->status === 'rejected'
                    ? $this->note
                    : null
            );
    }

    public function getProcessedByAttribute(): ?int
    {
        return $this->updated_by
            ? (int) $this->updated_by
            : null;
    }

    public function getFeeAmountAttribute(): string
    {
        return '0.00000000';
    }

    public function getTaxAmountAttribute(): string
    {
        return '0.00000000';
    }

    public function getNetAmountAttribute(): string
    {
        return (string) $this->amount;
    }

    public function getPayoutProfileIdAttribute(): ?int
    {
        $id = $this->metadata['payout_profile_id']
            ?? null;

        return $id !== null
            ? (int) $id
            : null;
    }
}
