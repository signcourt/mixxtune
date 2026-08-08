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
        'track_type',
        'primary_artist_name',
        'featuring_artist_name',
        'author_name',
        'composer_name',
        'arranger_name',
        'producer_name',
        'music_director_name',
        'publisher_name',
        'p_line',
        'release_year',
        'isrc',
        'isrc_is_auto_generated',
        'isrc_assigned_at',
        'isrc_assigned_by',
        'language',
        'title_language',
        'lyrics_language',
        'genre',
        'sub_genre',
        'is_explicit',
        'parental_advisory',
        'price_tier',
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
        'audio_metadata',
        'audio_codec',
        'audio_sample_rate',
        'audio_bit_depth',
        'audio_channels',
        'audio_channel_layout',
        'audio_duration_seconds',
        'audio_peak_db',
        'audio_mean_volume_db',
        'audio_silence_start_seconds',
        'audio_silence_end_seconds',
        'audio_validated_at',
        'audio_validated_by',
    ];

    protected function casts(): array
    {
        return [
            'disc_number' => 'integer',
            'track_number' => 'integer',
            'isrc_is_auto_generated' => 'boolean',
            'isrc_assigned_at' => 'datetime',
            'isrc_assigned_by' => 'integer',
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


    public function contributorCredits()
    {
        return $this->hasMany(
            TrackContributor::class
        );
    }



    protected $casts = [
        'audio_validation_errors' => 'array',
        'audio_metadata' => 'array',
        'audio_sample_rate' => 'integer',
        'audio_bit_depth' => 'integer',
        'audio_channels' => 'integer',
        'audio_duration_seconds' => 'float',
        'audio_peak_db' => 'float',
        'audio_mean_volume_db' => 'float',
        'audio_silence_start_seconds' => 'float',
        'audio_silence_end_seconds' => 'float',
        'audio_validated_at' => 'datetime',
    ];

}
