<?php

namespace App\Models\Catalogue;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyCatalogueImport extends Model
{
    protected $fillable = [
        'public_id',
        'original_filename',
        'stored_path',
        'status',
        'total_rows',
        'ready_rows',
        'blocked_rows',
        'warning_rows',
        'imported_rows',
        'failed_rows',
        'summary',
        'failure_message',
        'uploaded_by',
        'validated_at',
        'import_started_at',
        'import_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'validated_at' => 'datetime',
            'import_started_at' => 'datetime',
            'import_completed_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(
            LegacyCatalogueImportRow::class
        );
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
        );
    }
}
