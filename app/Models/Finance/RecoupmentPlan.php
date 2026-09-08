<?php

namespace App\Models\Finance;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecoupmentPlan extends Model
{
    protected $fillable = [
        'user_id',
        'label_id',
        'artist_id',
        'plan_number',
        'title',
        'base_percentage',
        'recovery_uplift_percentage',
        'maximum_recovery_percentage',
        'total_recoverable_amount',
        'total_recovered_amount',
        'outstanding_amount',
        'recovery_method',
        'status',
        'starts_on',
        'completed_on',
        'notes',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'base_percentage' => 'decimal:4',
        'recovery_uplift_percentage' => 'decimal:4',
        'maximum_recovery_percentage' => 'decimal:4',
        'total_recoverable_amount' => 'decimal:8',
        'total_recovered_amount' => 'decimal:8',
        'outstanding_amount' => 'decimal:8',
        'starts_on' => 'date',
        'completed_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(RecoupmentExpense::class);
    }

    public function recoveries(): HasMany
    {
        return $this->hasMany(RecoupmentRecovery::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RecoupmentDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && (float) $this->outstanding_amount > 0;
    }
}
