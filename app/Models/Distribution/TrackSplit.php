<?php

namespace App\Models\Distribution;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackSplit extends Model
{
    protected $fillable = [
        'public_id',
        'track_id',
        'contributor_id',
        'split_type',
        'percentage',
        'is_recoupable',
        'effective_from',
        'effective_to',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'is_recoupable' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}
