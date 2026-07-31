<?php

namespace App\Models\Finance;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueMatch extends Model
{
    protected $fillable = [
        'revenue_row_id',
        'track_id',
        'release_id',
        'artist_id',
        'label_id',
        'match_type',
        'confidence',
        'is_confirmed',
        'confirmed_by',
        'confirmed_at',
        'metadata',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'is_confirmed' => 'boolean',
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function revenueRow(): BelongsTo
    {
        return $this->belongsTo(RevenueRow::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
