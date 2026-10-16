<?php

namespace App\Console\Commands;

use App\Services\V2\ReportImportScheduledPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * MIXXTUNE_PUBLISH_DUE_REPORT_IMPORTS_V1
 */
class PublishDueReportImports extends Command
{
    protected $signature =
        'reports:publish-due-imports {--limit=25}';

    protected $description =
        'Publish approved report imports whose publish_at is due';

    public function handle(
        ReportImportScheduledPublisher $publisher
    ): int {
        $limit = max(
            1,
            min(
                100,
                (int) $this->option('limit')
            )
        );

        $ids = DB::table('report_imports')
            ->where(
                'financial_status',
                'approved_scheduled'
            )
            ->whereNotNull('publish_at')
            ->where(
                'publish_at',
                '<=',
                now()
            )
            ->orderBy('publish_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info(
                'No due report imports.'
            );

            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $result =
                    $publisher->publish(
                        (int) $id
                    );

                $success++;

                $this->info(
                    sprintf(
                        'Import #%d: %s',
                        $id,
                        $result['status']
                            ?? 'completed'
                    )
                );
            } catch (Throwable $e) {
                $failed++;

                $this->error(
                    sprintf(
                        'Import #%d FAILED: %s',
                        $id,
                        $e->getMessage()
                    )
                );

                report($e);
            }
        }

        $this->line(
            sprintf(
                'Processed=%d Success=%d Failed=%d',
                $ids->count(),
                $success,
                $failed
            )
        );

        /*
         * One failed import must not prevent another
         * due import from being processed.
         *
         * Return failure so monitoring can detect it.
         */
        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
