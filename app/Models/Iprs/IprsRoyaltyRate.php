<?php

namespace App\Models\Iprs;

use Illuminate\Database\Eloquent\Model;

class IprsRoyaltyRate extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'royalty_type',
        'owner_share_percentage',
        'updated_by',
    ];

    protected $casts = [
        'owner_id' => 'integer',
        'owner_share_percentage' => 'decimal:2',
    ];
}
