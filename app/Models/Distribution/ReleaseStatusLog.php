<?php

namespace App\Models\Distribution;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReleaseStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'release_id',
        'old_status',
        'new_status',
        'action',
        'remarks',
        'ip_address',
        'user_agent',
        'changed_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReleaseStatusLog $log): void {
            $log->public_id ??= (string) Str::ulid();
        });
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
