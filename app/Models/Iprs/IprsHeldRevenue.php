<?php

namespace App\Models\Iprs;

use Illuminate\Database\Eloquent\Model;

class IprsHeldRevenue extends Model
{
    protected $fillable = [
        'public_id',
        'iprs_upload_id',
        'iprs_line_id',
        'work_int_no',
        'owner_type',
        'owner_id',
        'user_id',
        'track_id',
        'release_id',
        'isrc',
        'gross_amount',
        'share_percentage',
        'net_amount',
        'royalty_type',
        'reason',
        'status',
        'royalty_ledger_id',
        'released_at',
        'released_by',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:8',
        'share_percentage' => 'decimal:4',
        'net_amount' => 'decimal:8',
        'released_at' => 'datetime',
    ];
}
