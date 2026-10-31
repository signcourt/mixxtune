<?php

namespace App\Console\Commands\V2;

use App\Services\V2\Office\OfficeMonitor;
use Illuminate\Console\Command;

/* W128 MIXXTUNE_OFFICE_MONITOR: tell the Super Admins when a clocked-in staff member goes idle */
class OfficeIdleCheck extends Command
{
    protected $signature = 'office:idle-check';

    protected $description = 'Alert Super Admins about idle office staff (desktop agent)';

    public function handle(OfficeMonitor $monitor): int
    {
        $n = $monitor->idleCheck();
        $this->info('Idle alerts sent: '.$n);

        return self::SUCCESS;
    }
}
