<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use App\Models\Core\Label;

use App\Models\Core\Artist;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'phone',
    'label_name',
    'country',
    'account_status',
    'kyc_status',
    'wallet_balance',
    'last_login_at',
    'invitation_status',
    'invitation_token',
    'invitation_sent_at',
    'invitation_expires_at',
    'invitation_accepted_at',
    'invitation_count',
    'invitation_error',
    'password_set_at',
])]
#[Hidden([
    'password',
    'remember_token',
    'invitation_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wallet_balance' => 'decimal:2',
            'last_login_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'password_set_at' => 'datetime',
            'invitation_count' => 'integer',
        ];
    }

    public function assignedLabels(): BelongsToMany
    {
        return $this->belongsToMany(
            Label::class,
            'admin_label_assignments',
            'user_id',
            'label_id'
        )
            ->withPivot([
                'assignment_role',
                'can_view',
                'can_edit',
                'can_manage_releases',
                'can_manage_team',
                'can_manage_splits',
                'assigned_by',
            ])
            ->withTimestamps();
    }

    public function assignedArtists(): BelongsToMany
    {
        return $this->belongsToMany(
            Artist::class,
            'admin_artist_assignments',
            'user_id',
            'artist_id'
        )
            ->withPivot([
                'assignment_role',
                'can_view',
                'can_edit',
                'can_manage_releases',
                'can_manage_team',
                'can_manage_splits',
                'assigned_by',
            ])
            ->withTimestamps();
    }

}