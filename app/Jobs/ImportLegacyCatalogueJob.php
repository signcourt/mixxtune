<?php

namespace App\Jobs;

use App\Models\Catalogue\LegacyCatalogueImport;
use App\Models\User;
use App\Services\V2\LegacyCatalogueImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportLegacyCatalogueJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 840;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $importId,
        public int $userId
    ) {
        $this->onQueue('default');
    }

    public function handle(
        LegacyCatalogueImportService $service
    ): void {
        $import =
            LegacyCatalogueImport::query()
                ->findOrFail($this->importId);

        $user =
            User::query()
                ->findOrFail($this->userId);

        /*
         * Idempotency guard.
         *
         * Only validated imports are allowed to start.
         */
        $claimed =
            LegacyCatalogueImport::query()
                ->whereKey($this->importId)
                ->where('status', 'queued')
                ->update([
                    'status' => 'processing',
                    'import_started_at' => now(),
                    'import_completed_at' => null,
                    'failure_message' => null,
                ]);

        if ($claimed !== 1) {
            Log::warning(
                'Legacy catalogue import job skipped',
                [
                    'import_id' => $this->importId,
                    'status' => $import->fresh()->status,
                ]
            );

            return;
        }

        $import = $import->fresh();

        try {
            /*
             * Service remains the single source of truth.
             * It performs the catalogue write atomically.
             */
            $result =
                $service->importValidatedCatalogue(
                    $import->fresh(),
                    $user
                );

            $result->forceFill([
                'import_completed_at' => now(),
                'failure_message' => null,
            ])->save();

        } catch (Throwable $e) {

            /*
             * The service transaction rolls catalogue writes back.
             * Record failure outside that transaction.
             */
            LegacyCatalogueImport::query()
                ->whereKey($this->importId)
                ->update([
                    'status' => 'failed',
                    'import_completed_at' => now(),
                    'failure_message' =>
                        mb_substr(
                            $e->getMessage(),
                            0,
                            5000
                        ),
                ]);

            Log::error(
                'Legacy catalogue background import failed',
                [
                    'import_id' => $this->importId,
                    'user_id' => $this->userId,
                    'exception' => $e,
                ]
            );

            throw $e;
        }
    }

    public function failed(
        ?Throwable $exception
    ): void {
        /*
         * W56: the database queue can hand a long job out a second time
         * (retry_after shorter than the run). That second copy "fails" with
         * MaxAttemptsExceeded while the first run is still working - it
         * must not mark the import failed under the running copy.
         */
        if (
            $exception instanceof \Illuminate\Queue\MaxAttemptsExceededException
            && LegacyCatalogueImport::query()
                ->whereKey($this->importId)
                ->where('status', 'processing')
                ->where('import_started_at', '>=', now()->subSeconds($this->timeout + 60))
                ->exists()
        ) {
            return;
        }

        LegacyCatalogueImport::query()
            ->whereKey($this->importId)
            ->whereNotIn(
                'status',
                ['imported', 'failed']
            )
            ->update([
                'status' => 'failed',
                'import_completed_at' => now(),
                'failure_message' =>
                    mb_substr(
                        $exception?->getMessage()
                            ?? 'Queue job failed.',
                        0,
                        5000
                    ),
            ]);
    }
}
