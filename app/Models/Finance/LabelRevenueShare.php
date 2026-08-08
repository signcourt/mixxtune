<?php

namespace App\Models\Finance;

use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelRevenueShare extends Model
{
    protected $fillable = [
        'master_label_id',
        'beneficiary_type',
        'beneficiary_id',
        'revenue_share_percent',
        'show_revenue_share',
        'is_active',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'revenue_share_percent' =>
                'decimal:4',

            'show_revenue_share' =>
                'boolean',

            'is_active' =>
                'boolean',

            'effective_from' =>
                'date',

            'effective_to' =>
                'date',
        ];
    }

    public function masterLabel(): BelongsTo
    {
        return $this->belongsTo(
            Label::class,
            'master_label_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
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
