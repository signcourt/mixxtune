<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** W221 */
class Distributor extends Model
{
    protected $table = 'distributors';

    protected $fillable = [
        'label_id', 'user_id', 'slug', 'host', 'host_verified_at', 'brand_name', 'dashboard_name',
        'logo_path', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = ['host_verified_at' => 'datetime'];
}
