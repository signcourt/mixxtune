<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RevenueRow extends Model
{
    protected $fillable = [
        'revenue_import_id',
        'source_row_number',
        'isrc',
        'upc',
        'track_title',
        'release_title',
        'artist_name',
        'label_name',
        'store_name',
        'country_code',
        'sale_type',
        'sale_month',
        'reporting_month',
        'currency',
        'streams',
        'quantity',
        'gross_amount',
        'net_amount',
        'source_row_hash',
        'match_status',
        'raw_data',
        'metadata',
    ];

    protected $casts = [
        'sale_month' => 'date',
        'reporting_month' => 'date',
        'streams' => 'integer',
        'quantity' => 'decimal:6',
        'gross_amount' => 'decimal:8',
        'net_amount' => 'decimal:8',
        'raw_data' => 'array',
        'metadata' => 'array',
    ];

    public function revenueImport(): BelongsTo
    {
        return $this->belongsTo(RevenueImport::class);
    }

    public function match(): HasOne
    {
        return $this->hasOne(RevenueMatch::class);
    }

    public function error(): HasOne
    {
        return $this->hasOne(ImportError::class);
    }
}
