<?php

namespace App\Console\Commands\V2;

use App\Models\Office\OfficeTask;
use App\Models\User;
use App\Services\V2\Office\OfficeService;
use App\Services\V2\StaffAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/*
 * W127 MIXXTUNE_OFFICE: task reminders (bell + e-mail), run every 15 minutes.
 * - due within the next hour, and not reminded in the last 3 hours;
 * - overdue, and not reminded in the last 3 hours;
 * - "blocked" tasks: the Super Admins are told once.
 */
class OfficeReminders extends Command
{
    protected $signature = 'office:reminders';

    protected $description = 'Remind staff about tasks due soon / overdue (office)';

    public function handle(StaffAlerts $alerts): int
    {
        $cut = now()->subHours(3);
        $soon = OfficeTask::query()->whereIn('status', OfficeTask::OPEN)
            ->whereNotNull('due_at')->where('due_at', '>', now())->where('due_at', '<=', now()->addHour())
            ->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<', $cut))->get();
        $over = OfficeTask::query()->whereIn('status', OfficeTask::OPEN)
            ->whereNotNull('due_at')->where('due_at', '<', now())
            ->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<', $cut))->get();

        $n = 0;
        foreach ($soon as $t) {
            $this->tell($alerts, $t, 'office.task_due', 'Task due soon: '.$t->title, 'Due at '.$t->due_at->timezone(OfficeService::TZ)->format('h:i A').'.', 'warning');
            $n++;
        }
        foreach ($over as $t) {
            $this->tell($alerts, $t, 'office.task_overdue', 'Overdue: '.$t->title, 'This task is past its time. Please update it.', 'warning');
            $n++;
        }
        $this->info('Office reminders sent: '.$n);

        return self::SUCCESS;
    }

    private function tell(StaffAlerts $alerts, OfficeTask $t, string $type, string $title, string $msg, string $sev): void
    {
        $user = User::query()->find($t->assigned_to);
        if (!$user) {
            return;
        }
        try {
            $alerts->bell([$user], $type, $title, $msg, '/office', $sev);
        } catch (\Throwable) {
        }
        $t->forceFill(['last_reminded_at' => now()])->save();
    }
}
