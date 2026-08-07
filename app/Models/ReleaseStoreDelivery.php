<?php

namespace App\Models;

use App\Models\Distribution\Release;
use Illuminate\Database\Eloquent\Model;

class ReleaseStoreDelivery extends Model
{

    protected $fillable = [
        'public_id',
        'release_id',
        'distribution_store_id',
        'status',
        'delivery_note',
        'error_message',
        'external_reference',
        'delivered_at',
        'live_at',
        'failed_at',
        'taken_down_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'live_at' => 'datetime',
        'failed_at' => 'datetime',
        'taken_down_at' => 'datetime',
    ];

    public function release()
    {
        return $this->belongsTo(
            Release::class
        );
    }

    public function store()
    {
        return $this->belongsTo(
            DistributionStore::class,
            'distribution_store_id'
        );
    }
}
