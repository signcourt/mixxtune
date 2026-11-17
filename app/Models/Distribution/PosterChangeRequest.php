<?php

namespace App\Models\Distribution;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 * W195 MIXXTUNE_POSTER_CHANGE_REQUEST: a pending poster swap a label/artist asked for,
 * waiting for a super-admin/admin to approve or reject.
 */
class PosterChangeRequest extends Model
{
    protected $fillable = [
        'release_id',
        'proposed_path',
        'requested_by',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
