<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class RoyaltyAllocation extends Model
{
    protected $fillable = [
        'public_id',
        'royalty_statement_id',
        'report_row_id',
        'release_id',
        'track_id',
        'gross_amount',
        'net_amount',
        'share_percentage',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:8',
        'net_amount' => 'decimal:8',
        'share_percentage' => 'decimal:4',
    ];

    public function statement()
    {
        return $this->belongsTo(
            RoyaltyStatement::class,
            'royalty_statement_id'
        );
    }
}
