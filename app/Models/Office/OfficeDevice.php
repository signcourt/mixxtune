<?php

namespace App\Models\Office;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/* W128 MIXXTUNE_OFFICE_MONITOR: one office computer's agent, paired to a staff member */
class OfficeDevice extends Model
{
    protected $table = 'office_devices';

    protected $fillable = [
        'public_id', 'user_id', 'platform', 'device_name', 'token_hash', 'pair_code', 'pair_expires_at',
        'approved', 'rustdesk_id', 'app_version', 'last_seen_at', 'last_active_at', 'idle_seconds', 'foreground_app',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'approved' => 'boolean', 'pair_expires_at' => 'datetime',
        'last_seen_at' => 'datetime', 'last_active_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
