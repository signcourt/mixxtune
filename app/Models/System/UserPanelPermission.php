<?php

namespace App\Models\System;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPanelPermission extends Model
{
    protected $fillable = [
        'user_id',
        'can_view_catalogue',
        'can_create_releases',
        'can_manage_releases',
        'can_view_reports',
        'can_view_royalties',
        'can_manage_wallet',
        'can_manage_withdrawals',
        'can_manage_users',
        'can_manage_support',
        'can_manage_settings',
        'can_manage_delivery',
        'can_manage_identifiers',
        'updated_by',
    ];

    protected $casts = [
        'can_view_catalogue' => 'boolean',
        'can_create_releases' => 'boolean',
        'can_manage_releases' => 'boolean',
        'can_view_reports' => 'boolean',
        'can_view_royalties' => 'boolean',
        'can_manage_wallet' => 'boolean',
        'can_manage_withdrawals' => 'boolean',
        'can_manage_users' => 'boolean',
        'can_manage_support' => 'boolean',
        'can_manage_settings' => 'boolean',
        'can_manage_delivery' => 'boolean',
        'can_manage_identifiers' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }
}
