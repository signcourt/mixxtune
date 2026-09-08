<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoupmentDocument extends Model
{
    protected $fillable = [
        'recoupment_plan_id',
        'recoupment_expense_id',
        'document_type',
        'original_name',
        'storage_disk',
        'storage_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            RecoupmentPlan::class,
            'recoupment_plan_id'
        );
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(
            RecoupmentExpense::class,
            'recoupment_expense_id'
        );
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
