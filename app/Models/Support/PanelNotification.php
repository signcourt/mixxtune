<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PanelNotification extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'type',
        'title',
        'message',
        'action_url',
        'data',
        'severity',
        'category',
        'read_at',
        'starred_at',
        'dismissed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'starred_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
