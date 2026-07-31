<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RevenueImport extends Model
{
    protected $fillable = [
        'public_id',
        'dsp_name',
        'statement_month',
        'currency',
        'original_filename',
        'stored_file_path',
        'file_hash',
        'total_rows',
        'matched_rows',
        'unmatched_rows',
        'duplicate_rows',
        'error_rows',
        'gross_revenue',
        'net_revenue',
        'status',
        'failure_reason',
        'column_mapping',
        'metadata',
        'imported_by',
        'processing_started_at',
        'completed_at',
    ];

    protected $casts = [
        'statement_month' => 'date',
        'gross_revenue' => 'decimal:8',
        'net_revenue' => 'decimal:8',
        'column_mapping' => 'array',
        'metadata' => 'array',
        'processing_started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (RevenueImport $import): void {
            if (empty($import->public_id)) {
                $import->public_id = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(RevenueRow::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }
}
