<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/* W127 MIXXTUNE_OFFICE: a job role in the office (Designer, Video Editor, Distribution ...) */
class FunctionalRole extends Model
{
    use SoftDeletes;

    protected $table = 'office_functional_roles';

    protected $fillable = ['public_id', 'name', 'slug', 'description', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function templates()
    {
        return $this->hasMany(TaskTemplate::class, 'functional_role_id');
    }
}
