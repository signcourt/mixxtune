<?php

namespace App\Models\LabelAccess;

use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabelTeamMember extends Model
{
    protected $fillable = [
        'label_id',
        'user_id',
        'created_by',
        'permission_level',
        'scope_level',
        'status',
        'permissions',
        'last_access_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_access_at' => 'datetime',
    ];

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(
            LabelTeamScope::class,
            'label_team_member_id'
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAdvanced(): bool
    {
        return $this->permission_level === 'advanced';
    }
}
