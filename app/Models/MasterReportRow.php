<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterReportRow extends Model
{
    protected $fillable = [
        'batch_id',
        'source_row_number',
        'store',
        'report_month',
        'track_title',
        'version',
        'artist',
        'label',
        'isrc',
        'upc',
        'release_title',
        'country',
        'currency',
        'streams',
        'gross_revenue',
        'vendor_revenue',
        'deduction_amount',
        'deduction_percent',
        'content_type',
        'usage_type',
        'subscription_type',
        'validation_status',
        'validation_messages',
        'raw_store',
        'raw_track',
        'raw_artist',
        'raw_label',
        'raw_isrc',
        'raw_upc',
        'raw_streams',
        'raw_gross_revenue',
        'raw_vendor_revenue',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'source_row_number' => 'integer',
            'streams' => 'decimal:4',
            'gross_revenue' => 'decimal:8',
            'vendor_revenue' => 'decimal:8',
            'deduction_amount' => 'decimal:8',
            'deduction_percent' => 'decimal:6',
            'validation_messages' => 'array',
            'raw_data' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MasterReportBatch::class, 'batch_id');
    }
}
