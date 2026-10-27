<?php

namespace App\Console\Commands\V2;

use App\Services\V2\ReleaseTrash;
use Illuminate\Console\Command;

/* W120 MIXXTUNE_RELEASE_TRASH: releases in the Trash longer than 90 days are removed for good */
class PurgeReleaseTrash extends Command
{
    protected $signature = 'releases:purge-trash';

    protected $description = 'Delete for good the releases that are in the Trash for more than 90 days';

    public function handle(ReleaseTrash $trash): int
    {
        $r = $trash->purgeDue();
        $this->info('Purged: '.$r['purged']);
        foreach ($r['kept'] as $k) {
            $this->warn('Kept: '.$k);
        }

        return self::SUCCESS;
    }
}
