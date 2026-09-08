<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecoupmentExpense extends Model
{
    protected $fillable = [
        'recoupment_plan_id',
        'category',
        'reference_number',
        'title',
        'description',
        'amount',
        'is_recoverable',
        'expense_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'is_recoverable' => 'boolean',
        'expense_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            RecoupmentPlan::class,
            'recoupment_plan_id'
        );
    }

    public function documents(): HasMany
    {
        return $this->hasMany(
            RecoupmentDocument::class,
            'recoupment_expense_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
