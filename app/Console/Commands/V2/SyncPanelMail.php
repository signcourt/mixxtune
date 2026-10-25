<?php

namespace App\Console\Commands\V2;

use App\Models\Mail\MailAccount;
use App\Services\V2\Mail\MailSync;
use Illuminate\Console\Command;
use Throwable;

/* W95 MIXXTUNE_PANEL_MAIL: read new mail of every active mailbox (scheduled every 2 minutes) */
class SyncPanelMail extends Command
{
    protected $signature = 'mail:sync {--account= : only this mailbox id}';

    protected $description = 'Read new mail of the domain mailboxes into the panel';

    public function handle(MailSync $sync): int
    {
        $q = MailAccount::query()->where('status', 'active');
        if ($this->option('account')) {
            $q->whereKey((int) $this->option('account'));
        }
        foreach ($q->get() as $account) {
            try {
                $n = $sync->sync($account);
                $this->line("{$account->address}: {$n['inbox']} new in inbox, {$n['sent']} new in sent");
            } catch (Throwable $e) {
                $account->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                $this->warn("{$account->address}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
