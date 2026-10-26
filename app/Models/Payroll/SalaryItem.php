<?php

namespace App\Models\Payroll;

use App\Models\Finance\BankPayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* W115: one person's salary in a month's sheet */
class SalaryItem extends Model
{
    protected $fillable = ['salary_run_id', 'staff_member_id', 'salary', 'additions', 'deductions', 'net', 'note', 'status', 'utr', 'paid_how', 'paid_at', 'paid_by', 'bank_payout_id', 'snapshot'];

    protected $casts = [
        'salary' => 'decimal:2',
        'additions' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net' => 'decimal:2',
        'paid_at' => 'datetime',
        'snapshot' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SalaryRun::class, 'salary_run_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'staff_member_id')->withTrashed();
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(BankPayout::class, 'bank_payout_id');
    }
}
