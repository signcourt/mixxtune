<?php

namespace App\Models;

use App\Models\Distribution\Release;
use Illuminate\Database\Eloquent\Model;

class UpcCode extends Model
{
    protected $fillable = [
        'public_id',
        'code',
        'prefix',
        'status',
        'release_id',
        'assigned_by',
        'assigned_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function release()
    {
        return $this->belongsTo(
            Release::class
        );
    }
}
