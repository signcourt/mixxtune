<?php

namespace App\Console\Commands\V2;

use App\Models\User;
use App\Models\Distribution\Release;
use App\Services\V2\IsrcService;
use App\Services\V2\UpcService;
use Illuminate\Console\Command;

class BackfillIdentifiers extends Command
{
    protected $signature = 'v2:backfill-identifiers';

    protected $description = 'Generate missing UPC and ISRC values for existing releases.';

    public function handle(
        IsrcService $isrc,
        UpcService $upc
    ): int {

        $admin = User::query()
            ->where('role','super_admin')
            ->first();

        if (!$admin) {
            $this->error('Super Admin not found.');
            return self::FAILURE;
        }

        $upcCount = 0;
        $isrcCount = 0;

        Release::with('tracks')
            ->chunk(100, function ($releases) use (
                $admin,
                $isrc,
                $upc,
                &$upcCount,
                &$isrcCount
            ) {

                foreach ($releases as $release) {

                    if (blank($release->upc)) {
                        $upc->generate($release, $admin);
                        $upcCount++;
                    }

                    foreach ($release->tracks as $track) {

                        if (blank($track->isrc)) {
                            $isrc->generate($track, $admin);
                            $isrcCount++;
                        }

                    }

                }

            });

        $this->info("UPC generated: {$upcCount}");
        $this->info("ISRC generated: {$isrcCount}");

        return self::SUCCESS;
    }
}
