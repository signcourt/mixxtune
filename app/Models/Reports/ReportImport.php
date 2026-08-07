<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

class ReportImport extends Model
{
    protected $fillable = [
        'public_id',
        'original_filename',
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
    ];

    protected $casts = [
        'column_map' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function rows()
    {
        return $this->hasMany(
            ReportRow::class
        );
    }
}
