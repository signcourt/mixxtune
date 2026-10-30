<?php

namespace App\Models\Office;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/* W127 MIXXTUNE_OFFICE: a task given to a staff member */
class OfficeTask extends Model
{
    use SoftDeletes;

    protected $table = 'office_tasks';

    protected $fillable = [
        'public_id', 'title', 'description', 'template_id', 'functional_role_id', 'assigned_to', 'created_by',
        'release_id', 'priority', 'status', 'progress', 'estimated_minutes', 'worked_seconds', 'due_at',
        'accepted_at', 'started_at', 'submitted_at', 'verified_at', 'reviewed_by', 'blocker_note', 'review_note', 'last_reminded_at',
    ];

    protected $casts = [
        'due_at' => 'datetime', 'accepted_at' => 'datetime', 'started_at' => 'datetime',
        'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'last_reminded_at' => 'datetime',
    ];

    public const OPEN = ['assigned', 'accepted', 'in_progress', 'blocked', 'changes_requested'];

    public function items()
    {
        return $this->hasMany(TaskChecklistItem::class, 'task_id')->orderBy('sort')->orderBy('id');
    }

    public function proofs()
    {
        return $this->hasMany(TaskProof::class, 'task_id')->orderByDesc('id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
