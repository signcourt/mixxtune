<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/* W95 MIXXTUNE_PANEL_MAIL: a domain mailbox used inside the panel (password encrypted with the app key) */
class MailAccount extends Model
{
    protected $fillable = [
        'address', 'display_name', 'aliases', 'username', 'password',
        'imap_host', 'imap_port', 'imap_encryption', 'smtp_host', 'smtp_port', 'smtp_encryption',
        'sent_folder', 'signature', 'signature_html', 'signature_scale', 'signature_transparent', 'signature_bg', 'signature_bg_color', 'signature_bg_padding', 'signature_bg_radius', 'status', 'is_private', 'last_synced_at', 'last_error', 'created_by',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'imap_port' => 'integer',
        'smtp_port' => 'integer',
        'last_synced_at' => 'datetime',
        'is_private' => 'boolean',
        'aliases' => 'array',
        'signature_scale' => 'integer',
        'signature_transparent' => 'boolean',
        'signature_bg_padding' => 'integer',
        'signature_bg_radius' => 'integer',
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

    /* W101: the addresses this mailbox may send from - its own first, then its aliases */
    public function fromAddresses(): array
    {
        $list = [['address' => $this->address, 'name' => (string) ($this->display_name ?? '')]];
        foreach ((array) ($this->aliases ?? []) as $a) {
            $addr = strtolower(trim((string) ($a['address'] ?? '')));
            if ($addr !== '' && $addr !== $this->address) {
                $list[] = ['address' => $addr, 'name' => (string) ($a['name'] ?? '') ?: (string) ($this->display_name ?? '')];
            }
        }

        return $list;
    }
}
