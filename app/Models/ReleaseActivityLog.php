<?php

namespace App\Models;

use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use Illuminate\Database\Eloquent\Model;

class ReleaseActivityLog extends Model
{
    protected $fillable = [
        'public_id',
        'release_id',
        'track_id',
        'user_id',
        'action',
        'category',
        'title',
        'description',
        'old_values',
        'new_values',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta' => 'array',
    ];

    public function release()
    {
        return $this->belongsTo(
            Release::class
        );
    }

    public function track()
    {
        return $this->belongsTo(
            Track::class
        );
    }

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }
}
