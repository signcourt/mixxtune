<?php

namespace App\Jobs;

use App\Models\Reports\ReportImport;
use App\Services\V2\ReportImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportRevenueReportJob implements ShouldQueue
{
    use Queueable;

    /*
     * Current real-world 66k-row report took about 9 minutes.
     * Worker command currently allows 900 seconds.
     */
    /*
     * Large revenue reports may legitimately run for 10+ minutes.
     * Worker timeout remains the outer hard limit.
     */
    public int $timeout = 3600;

    /*
     * Allow one controlled retry for infrastructure/transient failures.
     * Import row_hash/idempotency protection prevents duplicate report rows.
     */
    public int $tries = 2;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $importId
    ) {
        $this->onQueue('default');
    }

    public function handle(
        ReportImportService $service
    ): void {
        $import =
            ReportImport::query()
                ->findOrFail(
                    $this->importId
                );

        /*
         * processQueuedImport() contains the atomic
         * queued -> processing claim, so duplicate job
         * delivery cannot start the same import twice.
         */
        $result =
            $service->processQueuedImport(
                $import
            );

        Log::info(
            'Revenue report background import finished',
            [
                'import_id' =>
                    $result->id,

                'status' =>
                    $result->status,

                'total_rows' =>
                    $result->total_rows,

                'imported_rows' =>
                    $result->imported_rows,

                'duplicate_rows' =>
                    $result->duplicate_rows,

                'failed_rows' =>
                    $result->failed_rows,
            ]
        );
    }

    public function failed(
        ?Throwable $exception
    ): void {
        ReportImport::query()
            ->whereKey(
                $this->importId
            )
            ->whereNotIn(
                'status',
                [
                    'completed',
                    'completed_with_errors',
                    'failed',
                ]
            )
            ->update([
                'status' =>
                    'failed',

                'completed_at' =>
                    now(),

                'error_message' =>
                    mb_substr(
                        $exception?->getMessage()
                            ?? 'Background report import failed.',
                        0,
                        65000
                    ),
            ]);

        Log::error(
            'Revenue report background import failed',
            [
                'import_id' =>
                    $this->importId,

                'exception' =>
                    $exception,
            ]
        );
    }
}
