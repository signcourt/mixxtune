<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/* W127 MIXXTUNE_OFFICE: a ready-made task (with a checklist) for a role */
class TaskTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'office_task_templates';

    protected $fillable = ['public_id', 'functional_role_id', 'title', 'description', 'priority', 'sla_minutes', 'proof_type', 'checklist', 'is_active', 'created_by'];

    protected $casts = ['checklist' => 'array', 'is_active' => 'boolean'];

    public function role()
    {
        return $this->belongsTo(FunctionalRole::class, 'functional_role_id');
    }
}
