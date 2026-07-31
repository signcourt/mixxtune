<?php

namespace App\Models;

use App\Models\Distribution\Release;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseStoreDelivery extends Model
{
    protected $fillable = [
        'release_id',
        'distribution_store_id',
        'status',
        'store_release_id',
        'store_url',
        'delivery_notes',
        'error_message',
        'delivered_at',
        'live_at',
        'failed_at',
        'takedown_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'live_at' => 'datetime',
            'failed_at' => 'datetime',
            'takedown_at' => 'datetime',
        ];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(
            DistributionStore::class,
            'distribution_store_id'
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
