<?php

namespace App\Models\Distribution;

use App\Models\Finance\RoyaltyLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Track extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'public_id',
        'release_id',
        'disc_number',
        'track_number',
        'title',
        'version',
        'subtitle',
        'primary_artist_name',
        'featuring_artist_name',
        'isrc',
        'isrc_is_auto_generated',
        'language',
        'genre',
        'sub_genre',
        'is_explicit',
        'is_instrumental',
        'contains_ai_generated_content',
        'duration_seconds',
        'preview_start_seconds',
        'lyrics',
        'audio_path',
        'audio_original_name',
        'audio_mime_type',
        'audio_size_bytes',
        'sample_rate',
        'bit_depth',
        'channels',
        'audio_validation_status',
        'audio_validation_errors',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'disc_number' => 'integer',
            'track_number' => 'integer',
            'isrc_is_auto_generated' => 'boolean',
            'is_explicit' => 'boolean',
            'is_instrumental' => 'boolean',
            'contains_ai_generated_content' => 'boolean',
            'duration_seconds' => 'integer',
            'preview_start_seconds' => 'integer',
            'audio_size_bytes' => 'integer',
            'sample_rate' => 'integer',
            'bit_depth' => 'integer',
            'channels' => 'integer',
            'audio_validation_errors' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function contributors(): BelongsToMany
    {
        return $this->belongsToMany(
            Contributor::class,
            'track_contributors'
        )
            ->withPivot([
                'public_id',
                'role',
                'credited_name',
                'is_primary',
                'is_featured',
                'display_order',
                'metadata',
                'created_by',
                'updated_by',
            ])
            ->withTimestamps()
            ->orderByPivot('display_order');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TrackSplit::class);
    }

    public function royaltyLedgers(): HasMany
    {
        return $this->hasMany(RoyaltyLedger::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReleaseStatusLog::class, 'track_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function contributors()
    {
        return $this->belongsToMany(
            Contributor::class,
            'track_contributors'
        )
            ->withPivot([
                'public_id',
                'role',
                'is_primary',
                'sort_order',
                'notes',
            ])
            ->withTimestamps();
    }

    public function contributorCredits()
    {
        return $this->hasMany(
            TrackContributor::class
        );
    }

    public function splits()
    {
        return $this->hasMany(
            TrackSplit::class
        );
    }

}
