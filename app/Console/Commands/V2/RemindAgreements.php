<?php

namespace App\Console\Commands\V2;

use App\Services\V2\AccountAgreements;
use App\Services\V2\StaffAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/*
 * W96 MIXXTUNE_STAFF_ALERTS
 * Every morning (India): e-mail the Super Admins about agreements that reach 90 / 60 / 30 / 15 / 7 / 3 / 1 / 0
 * days left today, and once a week about agreements that ended and were not renewed.
 * (The dashboard pop-up still opens every day.)
 */
class RemindAgreements extends Command
{
    public const MILESTONES = [90, 60, 30, 15, 7, 3, 1, 0];

    protected $signature = 'agreements:remind';

    protected $description = 'E-mail the Super Admins about agreements to renew';

    public static function isMilestone(int $daysLeft): bool
    {
        return in_array($daysLeft, self::MILESTONES, true) || ($daysLeft < 0 && $daysLeft % 7 === 0);
    }

    public function handle(AccountAgreements $agreements, StaffAlerts $alerts): int
    {
        if (!Schema::hasTable('account_agreements')) {
            return self::SUCCESS;
        }
        $due = collect($agreements->due(500));
        $today = $due->filter(fn ($r) => self::isMilestone((int) $r['days_left']))->values();
        $this->line("to renew: {$due->count()}, reminder today: {$today->count()}");
        if ($today->isEmpty()) {
            return self::SUCCESS;
        }
        if (!Cache::add('w96:agreement-mail:'.AccountAgreements::today()->toDateString(), 1, now()->addDays(2))) {
            $this->line('already sent today');

            return self::SUCCESS;
        }
        $lines = $today->map(function ($r) {
            $end = \Carbon\CarbonImmutable::parse($r['ends_on'])->format('d M Y');
            $labels = $r['labels'] ? ' ('.implode(', ', $r['labels']).($r['more_labels'] ? ' +'.$r['more_labels'] : '').')' : '';
            $left = $r['days_left'] > 1 ? "{$r['days_left']} days left" : ($r['days_left'] === 1 ? '1 day left' : ($r['days_left'] === 0 ? 'ends TODAY' : 'ENDED '.abs($r['days_left']).' days ago, not renewed'));

            return "{$r['account']}{$labels} - ends {$end} - {$left}";
        })->all();
        if ($due->count() > $today->count()) {
            $lines[] = 'In total '.$due->count().' agreements are waiting for renewal - see the dashboard.';
        }
        $url = rtrim((string) config('app.url'), '/').'/super-admin/dashboard';
        $admins = $alerts->superAdmins();
        $title = $today->count() === 1 ? 'Agreement to renew' : $today->count().' agreements to renew';
        $alerts->bell($admins, 'agreement.reminder', $title, implode("\n", $lines), '/super-admin/dashboard', $today->contains(fn ($r) => $r['days_left'] <= 7) ? 'danger' : 'warning');
        $to = $alerts->email($admins, $title, $lines, $url);
        $this->line('sent to: '.(implode(', ', $to) ?: 'bell only'));

        return self::SUCCESS;
    }
}
