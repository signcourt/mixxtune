<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_public',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];
}
