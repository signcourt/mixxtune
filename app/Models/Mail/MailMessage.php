<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;

/* W95 MIXXTUNE_PANEL_MAIL */
class MailMessage extends Model
{
    protected $fillable = [
        'mail_account_id', 'folder', 'uid', 'message_id', 'in_reply_to', 'references_header', 'subject',
        'from_name', 'from_email', 'to', 'cc', 'bcc', 'reply_to', 'sent_at', 'snippet', 'body_text', 'body_html',
        'has_attachments', 'seen', 'starred', 'draft', 'size', 'sent_by',
        'orig_folder', 'trashed_at', 'snooze_until', 'woke_at', 'server_folder', 'server_uid',
    ];

    protected $casts = [
        'to' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'draft' => 'array',
        'starred' => 'boolean',
        'trashed_at' => 'datetime',
        'snooze_until' => 'datetime',
        'woke_at' => 'datetime',
        'reply_to' => 'array',
        'sent_at' => 'datetime',
        'has_attachments' => 'boolean',
        'seen' => 'boolean',
        'uid' => 'integer',
        'size' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function attachments()
    {
        return $this->hasMany(MailAttachment::class);
    }
}
