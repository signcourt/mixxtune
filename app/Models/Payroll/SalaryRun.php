<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/* W115: the salary sheet of one month */
class SalaryRun extends Model
{
    protected $fillable = ['month', 'status', 'note', 'created_by', 'approved_by', 'approved_at'];

    protected $casts = ['approved_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(SalaryItem::class);
    }
}
