<?php

namespace App\Models;

use App\Models\Distribution\Track;
use Illuminate\Database\Eloquent\Model;

class IsrcCode extends Model
{
    protected $fillable = [
        'public_id',
        'code',
        'country_code',
        'registrant_code',
        'reference_year',
        'designation_code',
        'status',
        'track_id',
        'assigned_by',
        'assigned_at',
        'notes',
    ];

    protected $casts = [
        'reference_year' => 'integer',
        'designation_code' => 'integer',
        'assigned_at' => 'datetime',
    ];

    public function track()
    {
        return $this->belongsTo(
            Track::class
        );
    }
}
