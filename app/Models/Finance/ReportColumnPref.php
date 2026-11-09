<?php

namespace App\Models\Finance;

use App\Models\Core\Label;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* W137 MIXXTUNE_REPORT_COLUMN_PREFS */
class ReportColumnPref extends Model
{
    protected $fillable = [
        'label_id',
        'columns',
        'show_label_rate',
        'show_earning',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'show_label_rate' => 'boolean',
            'show_earning' => 'boolean',
        ];
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }
}
