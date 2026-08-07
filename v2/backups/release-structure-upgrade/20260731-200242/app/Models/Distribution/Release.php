<?php

namespace App\Models\Distribution;

use App\Models\ReleaseStoreDelivery;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\RoyaltyLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Release extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'public_id',
        'catalog_number',
        'artist_id',
        'label_id',
        'release_type',
        'title',
        'version',
        'primary_artist_name',
        'featuring_artist_name',
        'language',
        'primary_genre',
        'sub_genre',
        'upc',
        'upc_is_auto_generated',
        'original_release_date',
        'digital_release_date',
        'copyright_owner',
        'copyright_year',
        'phonographic_owner',
        'phonographic_year',
        'artwork_path',
        'status',
        'review_notes',
        'rejection_reason',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'stores',
        'territories',
        'worldwide',
        'release_timezone',
        'pre_order',
        'wizard_step',
        'completion_percentage',
        'submitted_at',
        'approved_at',
        'delivered_at',
        'live_at',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'upc_is_auto_generated' => 'boolean',
            'original_release_date' => 'date',
            'digital_release_date' => 'date',
            'submitted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'approved_at' => 'datetime',
            'delivered_at' => 'datetime',
            'live_at' => 'datetime',
            'archived_at' => 'datetime',
            'wizard_step' => 'integer',
            'completion_percentage' => 'integer',
            'deleted_at' => 'datetime',
            'stores' => 'array',
            'territories' => 'array',
            'worldwide' => 'boolean',
            'pre_order' => 'boolean',
        ];
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }


    public function storeDeliveries(): HasMany
    {
        return $this->hasMany(
            ReleaseStoreDelivery::class,
            'release_id'
        );
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    public function royaltyLedgers(): HasMany
    {
        return $this->hasMany(RoyaltyLedger::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReleaseStatusLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
