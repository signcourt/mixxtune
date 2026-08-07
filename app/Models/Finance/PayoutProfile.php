<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PayoutProfile extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'account_holder_name',
        'bank_account_number',
        'bank_name',
        'ifsc_code',
        'branch_name',
        'upi_id',
        'pan_number',
        'gst_number',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'kyc_status',
        'kyc_notes',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'bank_account_number' => 'encrypted',
        'pan_number' => 'encrypted',
        'verified_at' => 'datetime',
    ];

    protected $hidden = [
        'bank_account_number',
        'pan_number',
    ];

    protected $appends = [
        'masked_bank_account',
        'masked_pan',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function withdrawals()
    {
        return $this->hasMany(
            WithdrawalRequest::class
        );
    }

    public function getMaskedBankAccountAttribute(): ?string
    {
        $number = $this->bank_account_number;

        if (!$number) {
            return null;
        }

        return str_repeat(
            '•',
            max(
                strlen($number) - 4,
                0
            )
        ) . substr($number, -4);
    }

    public function getMaskedPanAttribute(): ?string
    {
        $pan = $this->pan_number;

        if (!$pan) {
            return null;
        }

        return substr($pan, 0, 2)
            . '******'
            . substr($pan, -2);
    }
}
