<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;

/* W127 MIXXTUNE_OFFICE */
class TaskChecklistItem extends Model
{
    protected $table = 'office_task_checklist_items';

    protected $fillable = ['task_id', 'text', 'weight', 'is_done', 'done_at', 'sort'];

    protected $casts = ['is_done' => 'boolean', 'done_at' => 'datetime'];
}
