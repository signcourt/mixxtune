<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;

class ReportCurrencyResolutionService
{
    /*
     * MIXXTUNE_CURRENCY_RESOLUTION
     *
     * Financial currency priority:
     *
     * 1. Existing report_rows.currency from source CSV.
     * 2. Missing currency only:
     *    resolve from the mapped financial owner.
     *
     * This service NEVER overwrites a currency supplied
     * by the source report.
     *
     * It also NEVER mutates approved/published imports.
     */
    public function resolveForImport(
        int $reportImportId
    ): array {
        return DB::transaction(
            function () use ($reportImportId) {
                $import =
                    DB::table('report_imports')
                        ->where(
                            'id',
                            $reportImportId
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $import) {
                    throw new \RuntimeException(
                        'Report import not found.'
                    );
                }

                if (
                    $import->financial_status
                    !== 'review_pending'
                ) {
                    throw new \RuntimeException(
                        'Currency can only be resolved '
                        .'for review_pending imports.'
                    );
                }

                /*
                 * LABEL OWNERS
                 */
                $labelRows =
                    DB::table('report_rows as rr')
                        ->join(
                            'labels as l',
                            function ($join) {
                                $join
                                    ->on(
                                        'l.id',
                                        '=',
                                        'rr.revenue_owner_id'
                                    )
                                    ->where(
                                        'rr.revenue_owner_type',
                                        '=',
                                        'label'
                                    );
                            }
                        )
                        ->where(
                            'rr.report_import_id',
                            $reportImportId
                        )
                        ->where(
                            'rr.mapping_status',
                            'mapped'
                        )
                        ->where(function ($q) {
                            $q->whereNull(
                                'rr.currency'
                            )->orWhere(
                                'rr.currency',
                                ''
                            );
                        })
                        ->whereNotNull(
                            'l.currency'
                        )
                        ->where(
                            'l.currency',
                            '<>',
                            ''
                        )
                        ->lockForUpdate()
                        ->pluck(
                            'l.currency',
                            'rr.id'
                        );

                $labelUpdated = 0;

                foreach ($labelRows as $id => $currency) {
                    $labelUpdated +=
                        DB::table('report_rows')
                            ->where('id', $id)
                            ->where(
                                'report_import_id',
                                $reportImportId
                            )
                            ->where(function ($q) {
                                $q->whereNull(
                                    'currency'
                                )->orWhere(
                                    'currency',
                                    ''
                                );
                            })
                            ->update([
                                'currency' =>
                                    strtoupper(
                                        trim(
                                            (string)
                                            $currency
                                        )
                                    ),
                                'updated_at' =>
                                    now(),
                            ]);
                }

                /*
                 * ARTIST OWNERS
                 */
                $artistRows =
                    DB::table('report_rows as rr')
                        ->join(
                            'artists as a',
                            function ($join) {
                                $join
                                    ->on(
                                        'a.id',
                                        '=',
                                        'rr.revenue_owner_id'
                                    )
                                    ->where(
                                        'rr.revenue_owner_type',
                                        '=',
                                        'artist'
                                    );
                            }
                        )
                        ->where(
                            'rr.report_import_id',
                            $reportImportId
                        )
                        ->where(
                            'rr.mapping_status',
                            'mapped'
                        )
                        ->where(function ($q) {
                            $q->whereNull(
                                'rr.currency'
                            )->orWhere(
                                'rr.currency',
                                ''
                            );
                        })
                        ->whereNotNull(
                            'a.currency'
                        )
                        ->where(
                            'a.currency',
                            '<>',
                            ''
                        )
                        ->lockForUpdate()
                        ->pluck(
                            'a.currency',
                            'rr.id'
                        );

                $artistUpdated = 0;

                foreach ($artistRows as $id => $currency) {
                    $artistUpdated +=
                        DB::table('report_rows')
                            ->where('id', $id)
                            ->where(
                                'report_import_id',
                                $reportImportId
                            )
                            ->where(function ($q) {
                                $q->whereNull(
                                    'currency'
                                )->orWhere(
                                    'currency',
                                    ''
                                );
                            })
                            ->update([
                                'currency' =>
                                    strtoupper(
                                        trim(
                                            (string)
                                            $currency
                                        )
                                    ),
                                'updated_at' =>
                                    now(),
                            ]);
                }

                $missingMapped =
                    DB::table('report_rows')
                        ->where(
                            'report_import_id',
                            $reportImportId
                        )
                        ->where(
                            'mapping_status',
                            'mapped'
                        )
                        ->where(function ($q) {
                            $q->whereNull(
                                'currency'
                            )->orWhere(
                                'currency',
                                ''
                            );
                        })
                        ->count();

                return [
                    'label_rows_updated' =>
                        $labelUpdated,

                    'artist_rows_updated' =>
                        $artistUpdated,

                    'total_rows_updated' =>
                        $labelUpdated
                        + $artistUpdated,

                    'missing_mapped_currency_rows' =>
                        $missingMapped,
                ];
            }
        );
    }
}
