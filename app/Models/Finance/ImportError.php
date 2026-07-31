<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportError extends Model
{
    protected $fillable = [
        'revenue_import_id',
        'revenue_row_id',
        'source_row_number',
        'error_type',
        'message',
        'row_data',
        'is_resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'row_data' => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function revenueImport(): BelongsTo
    {
        return $this->belongsTo(RevenueImport::class);
    }

    public function revenueRow(): BelongsTo
    {
        return $this->belongsTo(RevenueRow::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
