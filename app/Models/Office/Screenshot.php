<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;

/* W128 MIXXTUNE_OFFICE_MONITOR */
class Screenshot extends Model
{
    public $timestamps = false;

    protected $table = 'office_screenshots';

    protected $fillable = ['device_id', 'user_id', 'work_date', 'path', 'foreground_app', 'taken_at', 'created_at'];

    protected $casts = ['work_date' => 'date', 'taken_at' => 'datetime', 'created_at' => 'datetime'];
}
