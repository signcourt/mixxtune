<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RightsRoyaltyAllocation extends Model
{
    protected $fillable = [
        'public_id',
        'rights_royalty_row_id',
        'release_id',
        'track_id',
        'beneficiary_type',
        'beneficiary_id',
        'user_id',
        'label_id',
        'artist_id',
        'master_label_id',
        'allocation_type',
        'share_percentage',
        'gross_amount',
        'allocated_amount',
        'currency',
        'revenue_share_id',
        'share_effective_from',
        'share_effective_to',
        'source_key',
        'status',
        'metadata',
    ];

    protected $casts = [
        'share_percentage' => 'decimal:4',
        'gross_amount' => 'decimal:8',
        'allocated_amount' => 'decimal:8',
        'share_effective_from' => 'date',
        'share_effective_to' => 'date',
        'metadata' => 'array',
    ];

    public function row(): BelongsTo
    {
        return $this->belongsTo(
            RightsRoyaltyRow::class,
            'rights_royalty_row_id'
        );
    }
}
