<?php

namespace App\Console\Commands\V2;

use App\Services\V2\StaffAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/*
 * W96 MIXXTUNE_STAFF_ALERTS
 * Every morning: did the backups run? The backup scripts (run by root) write one status
 * line ("OK 20261025-031500 ..." or "FAILED 20261025-031500 reason") into BACKUP_STATUS_DIR.
 * A failed, missing or too old backup is reported to every Super Admin (bell + e-mail).
 */
class CheckBackups extends Command
{
    public static string $dir = '/var/lib/mixxtune/backup-status';

    /* file => [name, max age in hours] (daily runs 03:15 UTC, weekly full Sunday 04:00 UTC) */
    public const CHECKS = [
        'offsite.last' => ['Daily backup to Cloudflare R2', 30],
        'full.last' => ['Weekly full website backup', 8 * 24 + 6],
    ];

    protected $signature = 'backups:check {--quiet-ok : print nothing when all is fine}';

    protected $description = 'Warn the Super Admins when a backup failed or did not run';

    public static function read(string $file, string $name, int $maxHours, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $path = rtrim(self::$dir, '/').'/'.$file;
        if (!is_readable($path)) {
            return ['ok' => false, 'name' => $name, 'text' => "{$name}: no status found (the backup never ran, or {$path} cannot be read)."];
        }
        $line = trim((string) @file_get_contents($path));
        if (!preg_match('/^(OK|FAILED)\s+(\d{8}-\d{6})\s*(.*)$/s', $line, $m)) {
            return ['ok' => false, 'name' => $name, 'text' => "{$name}: status file is unreadable (".mb_substr($line, 0, 120).').'];
        }
        $at = CarbonImmutable::createFromFormat('Ymd-His', $m[2], 'UTC');
        $when = $at->timezone('Asia/Kolkata')->format('d M Y, h:i A').' (India)';
        if ($m[1] === 'FAILED') {
            return ['ok' => false, 'name' => $name, 'text' => "{$name} FAILED on {$when}: ".mb_substr(trim($m[3]), 0, 300)];
        }
        $hours = (int) floor($at->diffInMinutes($now, true) / 60);
        if ($at->lt($now->subHours($maxHours))) {
            return ['ok' => false, 'name' => $name, 'text' => "{$name}: last good run was {$when}, {$hours} hours ago - it has not run since."];
        }

        return ['ok' => true, 'name' => $name, 'text' => "{$name}: OK, last run {$when}."];
    }

    public function handle(StaffAlerts $alerts): int
    {
        $results = [];
        foreach (self::CHECKS as $file => [$name, $hours]) {
            $results[] = self::read($file, $name, $hours);
        }
        $bad = array_values(array_filter($results, fn ($r) => !$r['ok']));
        foreach ($results as $r) {
            if (!$r['ok'] || !$this->option('quiet-ok')) {
                $this->line(($r['ok'] ? 'OK      ' : 'PROBLEM ').$r['text']);
            }
        }
        if ($bad === []) {
            return self::SUCCESS;
        }
        /* the same problem is reported once a day */
        $key = 'w96:backup-alert:'.now('Asia/Kolkata')->toDateString().':'.md5(implode('|', array_column($bad, 'text')));
        if (!Cache::add($key, 1, now()->addDay())) {
            $this->line('already reported today');

            return self::SUCCESS;
        }
        $lines = array_column($bad, 'text');
        $lines[] = 'What to do: send the last lines of /var/log/mixxtune-offsite-backup.log (or mixxtune-full-backup.log) to your developer.';
        $admins = $alerts->superAdmins();
        $alerts->bell($admins, 'backup.problem', 'Backup problem: '.implode(', ', array_column($bad, 'name')), implode("\n", array_column($bad, 'text')), null, 'danger');
        $to = $alerts->email($admins, 'backup problem - please check', $lines);
        $this->line('reported to: '.(implode(', ', $to) ?: 'bell only'));

        return self::SUCCESS;
    }
}
