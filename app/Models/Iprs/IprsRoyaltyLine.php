<?php

namespace App\Models\Iprs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IprsRoyaltyLine extends Model
{
    protected $fillable = [
        'public_id',
        'iprs_upload_id',
        'work_int_no',
        'title',
        'pool',
        'source',
        'source_month',
        'own_pct',
        'coll_pct',
        'amount',
        'mapping_status',
        'track_id',
        'release_id',
        'isrc',
        'upc',
        'client_id',
        'raw',
    ];

    protected $casts = [
        'source_month' => 'integer',
        'own_pct' => 'decimal:4',
        'coll_pct' => 'decimal:4',
        'amount' => 'decimal:4',
        'raw' => 'array',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(IprsUpload::class, 'iprs_upload_id');
    }
}
