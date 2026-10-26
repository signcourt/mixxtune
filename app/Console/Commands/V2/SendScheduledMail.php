<?php

namespace App\Console\Commands\V2;

use App\Services\V2\Mail\MailScheduler;
use Illuminate\Console\Command;

/* W117 MIXXTUNE_MAIL_SCHEDULE_SEND */
class SendScheduledMail extends Command
{
    protected $signature = 'mail:send-scheduled {--limit=50}';

    protected $description = 'Send the panel mails scheduled for now or earlier';

    public function handle(MailScheduler $scheduler): int
    {
        $r = $scheduler->sendDue((int) $this->option('limit'));
        $this->line("sent {$r['sent']}, failed {$r['failed']}");

        return self::SUCCESS;
    }
}
