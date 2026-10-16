<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RightsRoyaltyRow extends Model
{
    protected $fillable = [
        'public_id',
        'rights_royalty_import_id',
        'source_row_number',
        'isrc',
        'track_title',
        'rights_type',
        'territory',
        'gross_amount',
        'track_id',
        'release_id',
        'label_id',
        'artist_id',
        'user_id',
        'royalty_percentage',
        'beneficiary_amount',
        'company_amount',
        'mapping_status',
        'mapping_reason',
        'raw_data',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:8',
        'royalty_percentage' => 'decimal:4',
        'beneficiary_amount' => 'decimal:8',
        'company_amount' => 'decimal:8',
        'raw_data' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(
            RightsRoyaltyImport::class,
            'rights_royalty_import_id'
        );
    }
}
