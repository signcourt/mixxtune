<?php

namespace App\Models\Finance;

use App\Models\Payroll\SalaryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* W115: one bank transfer sent through RazorpayX (a withdrawal or a salary) */
class BankPayout extends Model
{
    /* not finished yet at RazorpayX */
    public const OPEN = ['creating', 'error', 'pending', 'queued', 'scheduled', 'processing'];

    /* the money did not go (or came back) */
    public const FAILED = ['failed', 'reversed', 'rejected', 'cancelled'];

    protected $fillable = [
        'public_id', 'purpose', 'withdrawal_id', 'salary_item_id', 'payee_name', 'account_hint', 'amount_paise', 'mode', 'live', 'status',
        'idempotency_key', 'razorpay_payout_id', 'fund_account_id', 'details_hash', 'deduction', 'utr', 'failure_reason', 'attempts', 'payout_requested_at', 'last_requested_at', 'settled', 'settled_at',
        'reversed_after_paid', 'created_by', 'last_checked_at', 'last_response',
    ];

    protected $casts = [
        'live' => 'boolean',
        'settled' => 'boolean',
        'reversed_after_paid' => 'boolean',
        'settled_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'payout_requested_at' => 'datetime',
        'last_requested_at' => 'datetime',
        'last_response' => 'array',
        'deduction' => 'array',
        'amount_paise' => 'integer',
        'attempts' => 'integer',
    ];

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class, 'withdrawal_id');
    }

    public function salaryItem(): BelongsTo
    {
        return $this->belongsTo(SalaryItem::class, 'salary_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function rupees(): float
    {
        return round($this->amount_paise / 100, 2);
    }
}
