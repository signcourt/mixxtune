<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

/* W115 MIXXTUNE_RAZORPAY_PAYOUTS: the RazorpayX keys (secrets encrypted, never sent to the page) */
class PaymentGatewaySetting extends Model
{
    protected $fillable = ['provider', 'enabled', 'key_id', 'key_secret', 'webhook_secret', 'account_number', 'narration', 'updated_by'];

    protected $hidden = ['key_secret', 'webhook_secret', 'account_number'];

    protected $casts = [
        'enabled' => 'boolean',
        'key_secret' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'account_number' => 'encrypted',
    ];

    public static function razorpay(): self
    {
        return static::query()->firstOrCreate(['provider' => 'razorpayx'], ['enabled' => false, 'narration' => 'Mixx Tune']);
    }
}
