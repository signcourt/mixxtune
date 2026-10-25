<?php

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/* W94 MIXXTUNE_ACCOUNT_AGREEMENTS */
class AccountAgreement extends Model
{
    protected $fillable = [
        'user_id',
        'signed_on',
        'term_months',
        'ends_on',
        'notice_days',
        'status',
        'document_path',
        'document_name',
        'notes',
        'renewed_from_id',
        'closed_at',
        'closed_by',
        'close_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'signed_on' => 'date',
        'ends_on' => 'date',
        'closed_at' => 'datetime',
        'term_months' => 'integer',
        'notice_days' => 'integer',
    ];

    protected $hidden = ['document_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
