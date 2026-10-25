<?php

namespace App\Console\Commands\V2;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\User;
use App\Services\V2\Mail\MailSync;
use App\Services\V2\StaffAlerts;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Throwable;

/* W95 MIXXTUNE_PANEL_MAIL: read new mail of every active mailbox (scheduled every 2 minutes) */
class SyncPanelMail extends Command
{
    protected $signature = 'mail:sync {--account= : only this mailbox id}';

    protected $description = 'Read new mail of the domain mailboxes into the panel';

    public function handle(MailSync $sync, StaffAlerts $alerts): int
    {
        $q = MailAccount::query()->where('status', 'active');
        if ($this->option('account')) {
            $q->whereKey((int) $this->option('account'));
        }
        foreach ($q->get() as $account) {
            try {
                /* W96: a bell for new mail - not for the first read of a mailbox */
                $known = DB::table('mail_folders')->where('mail_account_id', $account->id)->where('kind', 'inbox')->where('last_uid', '>', 0)->exists();
                $before = (int) MailMessage::query()->where('mail_account_id', $account->id)->where('folder', 'inbox')->max('id');
                $n = $sync->sync($account);
                if ($known && $n['inbox'] > 0) {
                    try {
                        $this->bell($alerts, $account, $before);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
                $this->line("{$account->address}: {$n['inbox']} new in inbox, {$n['sent']} new in sent");
            } catch (Throwable $e) {
                $account->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                $this->warn("{$account->address}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    /* the people the mailbox is given to; Super Admins only when it is given to nobody */
    private function bell(StaffAlerts $alerts, MailAccount $account, int $afterId): void
    {
        $new = MailMessage::query()->where('mail_account_id', $account->id)->where('folder', 'inbox')->where('id', '>', $afterId)->where('seen', false)->orderByDesc('id')->get(['id', 'subject', 'from_name', 'from_email']);
        if ($new->isEmpty()) {
            return;
        }
        $users = User::query()->whereIn('id', DB::table('mail_account_user')->where('mail_account_id', $account->id)->pluck('user_id'))->where('account_status', 'active')->get();
        if ($users->isEmpty()) {
            $users = $alerts->superAdmins();
        }
        $top = $new->first();
        $from = $top->from_name ?: $top->from_email;
        $title = $new->count() === 1 ? "New mail in {$account->address}" : "{$new->count()} new mails in {$account->address}";
        $message = $new->take(3)->map(fn ($m) => ($m->from_name ?: $m->from_email).': '.($m->subject ?: '(no subject)'))->implode("\n");
        $url = '/mail?account='.$account->id.($new->count() === 1 ? '&m='.$top->id : '');
        $alerts->bell($users, 'mail.new', $title, $message, $url, 'info', ['account' => $account->id, 'from' => $from]);
    }
}
