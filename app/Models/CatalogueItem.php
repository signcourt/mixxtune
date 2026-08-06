<?php

namespace App\Models;

use App\Models\Distribution\Release;
use Illuminate\Database\Eloquent\Model;

class CatalogueItem extends Model
{
    protected $fillable = [
        'public_id',
        'release_id',
        'artist_id',
        'label_id',
        'title',
        'release_type',
        'primary_artist_name',
        'primary_artists',
        'featuring_artists',
        'label_name',
        'upc',
        'catalog_number',
        'language',
        'primary_genre',
        'sub_genre',
        'artwork_path',
        'digital_release_date',
        'release_status',
        'track_count',
        'isrc_assigned_count',
        'store_ids',
        'delivery_summary',
        'is_visible',
        'last_synced_at',
    ];

    protected $casts = [
        'primary_artists' => 'array',
        'featuring_artists' => 'array',
        'store_ids' => 'array',
        'delivery_summary' => 'array',
        'digital_release_date' => 'date',
        'is_visible' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function release()
    {
        return $this->belongsTo(
            Release::class
        );
    }
}
