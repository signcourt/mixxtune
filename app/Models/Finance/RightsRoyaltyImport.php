<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RightsRoyaltyImport extends Model
{
    protected $fillable = [
        'public_id',
        'source',
        'statement_month',
        'currency',
        'original_filename',
        'stored_path',
        'uploaded_by',
        'total_rows',
        'mapped_rows',
        'unmapped_rows',
        'gross_amount',
        'mapped_amount',
        'unmapped_amount',
        'status',
        'processed_at',
        'approved_at',
        'approved_by',
        'published_at',
        'published_by',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:8',
        'mapped_amount' => 'decimal:8',
        'unmapped_amount' => 'decimal:8',
        'processed_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(
            RightsRoyaltyRow::class,
            'rights_royalty_import_id'
        );
    }
}
