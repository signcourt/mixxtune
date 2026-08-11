<?php

namespace App\Models\Catalogue;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyCatalogueImportRow extends Model
{
    protected $fillable = [
        'legacy_catalogue_import_id',
        'row_number',

        'label_name',
        'release_title',
        'release_type',
        'primary_artist',
        'featuring_artists',
        'track_title',
        'disc_number',
        'track_number',

        'isrc',
        'upc',

        'language',
        'primary_genre',
        'sub_genre',

        'original_release_date',
        'digital_release_date',

        'copyright_owner',
        'copyright_year',
        'phonographic_owner',
        'phonographic_year',

        'artwork_filename',
        'territory',

        'validation_status',
        'validation_errors',
        'validation_warnings',

        'resolved_label_id',
        'resolved_artist_id',
        'release_id',
        'track_id',

        'import_status',
        'is_excluded',
        'exclusion_reason',
        'excluded_by',
        'excluded_at',
        'import_error',
        'raw_data',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'validation_errors' => 'array',
            'validation_warnings' => 'array',
            'raw_data' => 'array',

            'is_excluded' => 'boolean',
            'excluded_at' => 'datetime',

            'original_release_date' => 'date',
            'digital_release_date' => 'date',
            'imported_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(
            LegacyCatalogueImport::class,
            'legacy_catalogue_import_id'
        );
    }

    public function resolvedLabel(): BelongsTo
    {
        return $this->belongsTo(
            Label::class,
            'resolved_label_id'
        );
    }

    public function resolvedArtist(): BelongsTo
    {
        return $this->belongsTo(
            Artist::class,
            'resolved_artist_id'
        );
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(
            Release::class
        );
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(
            Track::class
        );
    }

    public function isReady(): bool
    {
        return $this->validation_status === 'ready';
    }

    public function isBlocked(): bool
    {
        return $this->validation_status === 'blocked';
    }
}
