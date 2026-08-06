<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'public_id',
        'ticket_number',
        'user_id',
        'assigned_admin_id',
        'subject',
        'category',
        'priority',
        'status',
        'last_reply_at',
        'last_reply_by',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(
            User::class,
            'assigned_admin_id'
        );
    }

    public function messages()
    {
        return $this->hasMany(
            SupportTicketMessage::class
        );
    }
}
