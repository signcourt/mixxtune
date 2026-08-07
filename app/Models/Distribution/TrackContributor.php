<?php

namespace App\Models\Distribution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackContributor extends Model
{
    protected $fillable = [
        'public_id',
        'track_id',
        'contributor_id',
        'role',
        'credited_name',
        'is_primary',
        'is_featured',
        'display_order',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_featured' => 'boolean',
            'display_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(
            Track::class
        );
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(
            Contributor::class
        );
    }
}
