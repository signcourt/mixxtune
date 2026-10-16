<?php

namespace App\Console\Commands;

use App\Services\Migrations\WordPressLegacySnapshotService;
use Illuminate\Console\Command;
use Throwable;

class WordPressLegacyAuditCommand extends Command
{
    protected $signature =
        'mixxtune:wordpress-legacy-audit
        {directory : Directory containing WordPress JSONL snapshots}';

    protected $description =
        'Read-only audit of a WordPress legacy migration snapshot';

    public function handle(
        WordPressLegacySnapshotService $service
    ): int {
        try {
            $result = $service->audit(
                (string) $this->argument('directory')
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(
            json_encode(
                $result,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
            )
        );

        if (!$result['ready']) {
            $this->warn(
                'SNAPSHOT_NOT_READY: required files are missing.'
            );

            return self::FAILURE;
        }

        foreach ($result['files'] as $name => $file) {
            if (!$file['valid']) {
                $this->error(
                    "SNAPSHOT_INVALID_JSON: {$name}"
                );

                return self::FAILURE;
            }
        }

        $this->info('SNAPSHOT_AUDIT_PASS');

        return self::SUCCESS;
    }
}
