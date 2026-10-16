<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

class ReportImport extends Model
{
    protected $fillable = [
        'public_id',
        'original_filename',
        'reporting_month',
        'stored_path',
        'status',
        'total_rows',
        'imported_rows',
        'duplicate_rows',
        'failed_rows',
        'error_file_path',
        'column_map',
        'error_message',
        'uploaded_by',
        'started_at',
        'completed_at',
        'financial_status',
        'publish_at',
        'reviewed_at',
        'reviewed_by',
        'approved_at',
        'approved_by',
        'published_at',
        'published_by',
        'review_row_count',
        'review_mapped_rows',
        'review_unmapped_rows',
        'review_collected_revenue',
        'review_earnings',
        'review_note',
    ];

    protected $casts = [
        'column_map' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    
        'publish_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'review_collected_revenue' => 'decimal:8',
        'review_earnings' => 'decimal:8',
    ];

    public function rows()
    {
        return $this->hasMany(
            ReportRow::class
        );
    }
}
