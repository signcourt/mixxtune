<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;

/* W127 MIXXTUNE_OFFICE: one work day for one staff member */
class Attendance extends Model
{
    protected $table = 'office_attendance';

    protected $fillable = [
        'user_id', 'work_date', 'clock_in_at', 'clock_out_at', 'active_seconds', 'break_seconds',
        'task_seconds', 'tasks_done', 'tasks_submitted', 'last_ping_at', 'on_break', 'confirmed_at',
    ];

    protected $casts = [
        'work_date' => 'date', 'clock_in_at' => 'datetime', 'clock_out_at' => 'datetime',
        'last_ping_at' => 'datetime', 'confirmed_at' => 'datetime', 'on_break' => 'boolean',
    ];

    public function breaks()
    {
        return $this->hasMany(BreakSession::class, 'attendance_id');
    }
}
