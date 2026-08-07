<?php

namespace App\Models;

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
        'severity',
        'related_type',
        'related_id',
        'data',
        'read_at',
        'dismissed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function scopeUnread($query)
    {
        return $query
            ->whereNull('read_at')
            ->whereNull('dismissed_at');
    }
}
