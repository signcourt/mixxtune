<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;

/* W127 MIXXTUNE_OFFICE: one run of the Start/Pause timer on a task */
class TaskTimer extends Model
{
    protected $table = 'office_task_timers';

    protected $fillable = ['task_id', 'user_id', 'started_at', 'ended_at', 'seconds'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function task()
    {
        return $this->belongsTo(OfficeTask::class, 'task_id');
    }
}
