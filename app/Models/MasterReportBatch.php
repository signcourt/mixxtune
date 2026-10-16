<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterReportBatch extends Model
{
    protected $fillable = [
        'batch_code',
        'original_filename',
        'stored_path',
        'uploaded_by',
        'total_rows',
        'total_tracks',
        'total_stores',
        'total_streams',
        'gross_revenue',
        'vendor_revenue',
        'deduction_amount',
        'deduction_percent',
        'currency',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'total_tracks' => 'integer',
            'total_stores' => 'integer',
            'total_streams' => 'decimal:4',
            'gross_revenue' => 'decimal:8',
            'vendor_revenue' => 'decimal:8',
            'deduction_amount' => 'decimal:8',
            'deduction_percent' => 'decimal:6',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(MasterReportRow::class, 'batch_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
