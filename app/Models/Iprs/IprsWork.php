<?php

namespace App\Models\Iprs;

use Illuminate\Database\Eloquent\Model;

class IprsWork extends Model
{
    protected $fillable = [
        'work_int_no',
        'title',
        'language',
        'credits',
    ];

    protected $casts = [
        'credits' => 'array',
    ];
}
