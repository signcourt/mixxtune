<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/* W115 MIXXTUNE_STAFF_SALARY: a person on the Mixx Tune payroll */
class StaffMember extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'designation', 'email', 'phone', 'user_id', 'account_holder_name', 'bank_account_number', 'ifsc_code', 'bank_name',
        'pan_number', 'monthly_salary', 'joined_on', 'status', 'notes', 'created_by', 'updated_by',
    ];

    protected $hidden = ['bank_account_number', 'pan_number'];

    protected $appends = ['masked_bank_account'];

    protected $casts = [
        'bank_account_number' => 'encrypted',
        'pan_number' => 'encrypted',
        'monthly_salary' => 'decimal:2',
        'joined_on' => 'date',
    ];

    public function getMaskedBankAccountAttribute(): ?string
    {
        $n = (string) ($this->bank_account_number ?? '');

        return $n === '' ? null : '••••'.substr($n, -4);
    }

    public function bankReady(): bool
    {
        return (string) $this->bank_account_number !== '' && (string) $this->ifsc_code !== '' && trim((string) ($this->account_holder_name ?: $this->name)) !== '';
    }
}
