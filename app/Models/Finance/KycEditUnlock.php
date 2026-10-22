<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/* W80 MIXXTUNE_KYC_EDIT_LOCK */
class KycEditUnlock extends Model
{
    protected $fillable = [
        'user_id',
        'unlocked_by',
        'reason',
        'document_path',
        'document_name',
        'expires_at',
        'used_at',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['document_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unlocker()
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public function isActive(): bool
    {
        return $this->used_at === null && $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
