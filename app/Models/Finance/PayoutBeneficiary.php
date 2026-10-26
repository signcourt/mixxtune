<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

/* W115: a person's bank account as known at RazorpayX (contact + fund account) */
class PayoutBeneficiary extends Model
{
    protected $fillable = ['owner_type', 'owner_id', 'live', 'details_hash', 'contact_id', 'fund_account_id'];

    protected $casts = ['live' => 'boolean'];
}
