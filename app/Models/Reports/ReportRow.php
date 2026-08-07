<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

class ReportRow extends Model
{
    protected $fillable = [
        'row_hash',
        'report_import_id',
        'release_id',
        'track_id',
        'artist_id',
        'label_id',
        'track_artist',
        'album_title',
        'album_artist',
        'label_name',
        'track_title',
        'isrc',
        'upc',
        'platform',
        'currency',
        'country_code',
        'cms',
        'sale_type',
        'sale_date',
        'sale_month',
        'streams',
        'sale_units',
        'label_rate',
        'earnings',
        'raw_data',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'streams' => 'decimal:4',
        'sale_units' => 'decimal:4',
        'label_rate' => 'decimal:8',
        'earnings' => 'decimal:8',
        'raw_data' => 'array',
    ];

    public function import()
    {
        return $this->belongsTo(
            ReportImport::class,
            'report_import_id'
        );
    }
}
