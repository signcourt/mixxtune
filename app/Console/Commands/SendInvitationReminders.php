<?php

namespace App\Console\Commands;

use App\Services\V2\InvitationReminderService;
use Illuminate\Console\Command;

/**
 * MIXXTUNE_INVITATION_REMINDERS_V1
 * Runs daily from the scheduler. --dry-run only lists who would get a reminder.
 */
class SendInvitationReminders extends Command
{
    protected $signature = 'invitations:send-reminders {--dry-run}';

    protected $description = 'E-mail a reminder (fresh link) to people who have not accepted their invitation';

    public function handle(InvitationReminderService $reminders): int
    {
        $dry = (bool) $this->option('dry-run');
        $result = $reminders->run($dry);

        if ($result['skipped']) {
            $this->info('Invitation reminders are switched off in Master Settings.');

            return self::SUCCESS;
        }

        foreach ($result['people'] as $line) {
            $this->line(($dry ? 'would remind: ' : 'reminded: ').$line);
        }

        $this->info($dry
            ? 'DRY RUN: '.count($result['people']).' reminder(s) due, nothing sent.'
            : "Reminders sent: {$result['sent']}, failed: {$result['failed']}.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
