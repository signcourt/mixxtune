<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/* W95 MIXXTUNE_PANEL_MAIL: a domain mailbox used inside the panel (password encrypted with the app key) */
class MailAccount extends Model
{
    protected $fillable = [
        'address', 'display_name', 'username', 'password',
        'imap_host', 'imap_port', 'imap_encryption', 'smtp_host', 'smtp_port', 'smtp_encryption',
        'sent_folder', 'signature', 'status', 'last_synced_at', 'last_error', 'created_by',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'imap_port' => 'integer',
        'smtp_port' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = ['password'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'mail_account_user')->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(MailMessage::class);
    }
}
