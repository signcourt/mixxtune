<?php

namespace App\Models\LabelAccess;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelTeamScope extends Model
{
    protected $fillable = [
        'label_team_member_id',
        'scope_type',
        'scope_id',
    ];

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(
            LabelTeamMember::class,
            'label_team_member_id'
        );
    }
}
