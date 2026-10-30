<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;

/* W127 MIXXTUNE_OFFICE */
class BreakSession extends Model
{
    protected $table = 'office_breaks';

    protected $fillable = ['attendance_id', 'started_at', 'ended_at', 'seconds'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];
}
