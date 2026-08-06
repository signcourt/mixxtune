<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillReportRows extends Command
{
    protected $signature = 'reports:backfill-mapping
                            {--batch=500 : Number of report rows per batch}';

    protected $description =
        'Backfill track, release, artist and label IDs in report rows using normalized ISRC matching';

    public function handle(): int
    {
        $batchSize = max(
            1,
            (int) $this->option('batch')
        );

        $totalUpdated = 0;
        $batchNumber = 0;

        $this->info(
            'Starting report row mapping backfill...'
        );

        while (true) {
            $ids = DB::table('report_rows as rr')
                ->join('tracks as t', function ($join) {
                    $join->on(
                        DB::raw("
                            REPLACE(
                                REPLACE(
                                    UPPER(TRIM(t.isrc)),
                                    '-',
                                    ''
                                ),
                                ' ',
                                ''
                            )
                        "),
                        '=',
                        DB::raw("
                            REPLACE(
                                REPLACE(
                                    UPPER(TRIM(rr.isrc)),
                                    '-',
                                    ''
                                ),
                                ' ',
                                ''
                            )
                        ")
                    );
                })
                ->join(
                    'releases as r',
                    'r.id',
                    '=',
                    't.release_id'
                )
                ->whereNull('rr.track_id')
                ->orderBy('rr.id')
                ->limit($batchSize)
                ->pluck('rr.id');

            if ($ids->isEmpty()) {
                break;
            }

            $idList = $ids
                ->map(fn ($id) => (int) $id)
                ->implode(',');

            $updated = DB::affectingStatement("
                UPDATE report_rows rr
                INNER JOIN tracks t
                    ON REPLACE(
                        REPLACE(
                            UPPER(TRIM(t.isrc)),
                            '-',
                            ''
                        ),
                        ' ',
                        ''
                    )
                    =
                    REPLACE(
                        REPLACE(
                            UPPER(TRIM(rr.isrc)),
                            '-',
                            ''
                        ),
                        ' ',
                        ''
                    )
                INNER JOIN releases r
                    ON r.id = t.release_id
                SET
                    rr.track_id = t.id,
                    rr.release_id = r.id,
                    rr.artist_id = r.artist_id,
                    rr.label_id = r.label_id,
                    rr.updated_at = NOW()
                WHERE rr.id IN ({$idList})
            ");

            $batchNumber++;
            $totalUpdated += $updated;

            $this->line(
                'Batch '.$batchNumber
                .' | Selected: '.$ids->count()
                .' | Updated: '.$updated
                .' | Total updated: '.$totalUpdated
            );

            if ($updated === 0) {
                $this->warn(
                    'No rows updated in this batch. Stopping to avoid an infinite loop.'
                );

                break;
            }
        }

        $remaining = DB::table('report_rows')
            ->whereNull('track_id')
            ->count();

        $mapped = DB::table('report_rows')
            ->whereNotNull('track_id')
            ->count();

        $this->newLine();
        $this->info('Backfill completed.');
        $this->line(
            'Total updated: '.$totalUpdated
        );
        $this->line(
            'Mapped rows: '.$mapped
        );
        $this->line(
            'Track NULL rows remaining: '.$remaining
        );

        return self::SUCCESS;
    }
}
