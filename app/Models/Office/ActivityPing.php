<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;

/* W128 MIXXTUNE_OFFICE_MONITOR */
class ActivityPing extends Model
{
    public $timestamps = false;

    protected $table = 'office_activity_pings';

    protected $fillable = ['device_id', 'user_id', 'work_date', 'pinged_at', 'idle_seconds', 'active', 'foreground_app'];

    protected $casts = ['work_date' => 'date', 'pinged_at' => 'datetime', 'active' => 'boolean'];
}
