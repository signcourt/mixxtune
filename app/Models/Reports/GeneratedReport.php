<?php

namespace App\Models\Reports;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class GeneratedReport extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'report_type',
        'report_mode',
        'scope',
        'from_month',
        'to_month',
        'selected_columns',
        'filters',
        'currency',
        'gross_amount',
        'net_amount',
        'rows_count',
        'status',
        'file_path',
        'file_name',
        'error_message',
        'generated_at',
    ];

    protected $casts = [
        'selected_columns' => 'array',
        'filters' => 'array',

        'gross_amount' =>
            'decimal:8',

        'net_amount' =>
            'decimal:8',

        'rows_count' =>
            'integer',

        'generated_at' =>
            'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }
}
