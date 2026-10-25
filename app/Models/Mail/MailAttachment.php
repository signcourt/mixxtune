<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;

/* W95 MIXXTUNE_PANEL_MAIL */
class MailAttachment extends Model
{
    protected $fillable = ['mail_message_id', 'filename', 'mime', 'size', 'content_id', 'inline', 'path'];

    protected $casts = ['inline' => 'boolean', 'size' => 'integer'];

    protected $hidden = ['path'];

    public function message()
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }
}
