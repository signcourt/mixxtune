<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;

/* W127 MIXXTUNE_OFFICE */
class TaskProof extends Model
{
    public $timestamps = false;

    protected $table = 'office_task_proofs';

    protected $fillable = ['task_id', 'kind', 'link', 'path', 'note', 'submitted_by', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
