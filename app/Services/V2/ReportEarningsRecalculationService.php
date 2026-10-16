<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReportEarningsRecalculationService
{
    /**
     * Recalculate existing mapped report rows for one
     * catalogue label using its current canonical rate.
     *
     * This method NEVER posts wallet transactions,
     * royalty statements or allocations.
     */
    public function recalculateForLabel(
        Label $label
    ): array {
        $rate = $label->revenue_share_percentage;

        if ($rate === null) {
            return [
                'status' => 'rate_pending',
                'label_id' => (int) $label->id,
                'rate' => null,
                'positive_rows' => 0,
                'negative_rows' => 0,
                'rows' => 0,
            ];
        }

        $rate = (float) $rate;

        if ($rate < 0 || $rate > 100) {
            throw new InvalidArgumentException(
                'Revenue share percentage must be between 0 and 100.'
            );
        }

        $decimal = round($rate / 100, 8);

        return DB::transaction(
            function () use (
                $label,
                $rate,
                $decimal
            ): array {
                /*
                 * FF383-B: Financial lifecycle freeze.
                 *
                 * Commercial-rate changes may recalculate only
                 * rows whose parent revenue import is still in
                 * manual review.
                 *
                 * approved_scheduled / published imports are
                 * immutable financial snapshots and must never
                 * be rewritten by a later account-rate change.
                 *
                 * Legacy rows without report_import_id are left
                 * untouched by this workflow.
                 */
                $base = DB::table('report_rows')
                    ->where(
                        'label_id',
                        (int) $label->id
                    )
                    ->where(
                        'mapping_status',
                        'mapped'
                    )
                    ->whereNotNull(
                        'report_import_id'
                    )
                    ->whereExists(
                        function ($query) {
                            $query
                                ->selectRaw('1')
                                ->from('report_imports')
                                ->whereColumn(
                                    'report_imports.id',
                                    'report_rows.report_import_id'
                                )
                                ->where(
                                    'report_imports.financial_status',
                                    'review_pending'
                                );
                        }
                    );

                $rows = (clone $base)->count();

                $positiveRows = (clone $base)
                    ->where(
                        'collected_revenue',
                        '>=',
                        0
                    )
                    ->update([
                        'label_rate' => $decimal,
                        'earnings' => DB::raw(
                            'ROUND(collected_revenue * '
                            .$decimal.
                            ', 8)'
                        ),
                        'updated_at' => now(),
                    ]);

                $negativeRows = (clone $base)
                    ->where(
                        'collected_revenue',
                        '<',
                        0
                    )
                    ->update([
                        'label_rate' => 1.0,
                        'earnings' => DB::raw(
                            'ROUND(collected_revenue, 8)'
                        ),
                        'updated_at' => now(),
                    ]);

                return [
                    'status' => 'recalculated',
                    'label_id' =>
                        (int) $label->id,
                    'rate' => $rate,
                    'rows' => $rows,
                    'positive_rows' =>
                        $positiveRows,
                    'negative_rows' =>
                        $negativeRows,
                ];
            }
        );
    }

    /**
     * Recalculate existing mapped report rows for one
     * catalogue artist using its current canonical rate.
     *
     * This method NEVER posts wallet transactions,
     * royalty statements or allocations.
     */
    public function recalculateForArtist(
        Artist $artist
    ): array {
        $rate = $artist->revenue_share_percentage;

        if ($rate === null) {
            return [
                'status' => 'rate_pending',
                'artist_id' => (int) $artist->id,
                'rate' => null,
                'positive_rows' => 0,
                'negative_rows' => 0,
                'rows' => 0,
            ];
        }

        $rate = (float) $rate;

        if ($rate < 0 || $rate > 100) {
            throw new InvalidArgumentException(
                'Revenue share percentage must be between 0 and 100.'
            );
        }

        $decimal = round($rate / 100, 8);

        return DB::transaction(
            function () use (
                $artist,
                $rate,
                $decimal
            ): array {
                /*
                 * FF383-B: Same financial freeze for
                 * artist-owned report rows.
                 */
                $base = DB::table('report_rows')
                    ->where(
                        'artist_id',
                        (int) $artist->id
                    )
                    ->where(
                        'mapping_status',
                        'mapped'
                    )
                    ->whereNull('label_id')
                    ->whereNotNull(
                        'report_import_id'
                    )
                    ->whereExists(
                        function ($query) {
                            $query
                                ->selectRaw('1')
                                ->from('report_imports')
                                ->whereColumn(
                                    'report_imports.id',
                                    'report_rows.report_import_id'
                                )
                                ->where(
                                    'report_imports.financial_status',
                                    'review_pending'
                                );
                        }
                    );

                $rows = (clone $base)->count();

                $positiveRows = (clone $base)
                    ->where(
                        'collected_revenue',
                        '>=',
                        0
                    )
                    ->update([
                        'label_rate' => $decimal,
                        'earnings' => DB::raw(
                            'ROUND(collected_revenue * '
                            .$decimal.
                            ', 8)'
                        ),
                        'updated_at' => now(),
                    ]);

                $negativeRows = (clone $base)
                    ->where(
                        'collected_revenue',
                        '<',
                        0
                    )
                    ->update([
                        'label_rate' => 1.0,
                        'earnings' => DB::raw(
                            'ROUND(collected_revenue, 8)'
                        ),
                        'updated_at' => now(),
                    ]);

                return [
                    'status' => 'recalculated',
                    'artist_id' =>
                        (int) $artist->id,
                    'rate' => $rate,
                    'rows' => $rows,
                    'positive_rows' =>
                        $positiveRows,
                    'negative_rows' =>
                        $negativeRows,
                ];
            }
        );
    }


    /*
     * MIXXTUNE_IMPORT_SCOPED_EARNINGS_RECALC_V1
     *
     * Refresh/re-detect must never recalculate another import.
     * These methods are intentionally scoped to exactly one
     * review_pending report_import_id.
     *
     * No wallet posting.
     * No publication.
     */
    public function recalculateForLabelInImport(
        Label $label,
        int $reportImportId
    ): array {
        $rate = $label->revenue_share_percentage;

        if ($rate === null) {
            return [
                'status' => 'rate_pending',
                'label_id' => (int) $label->id,
                'report_import_id' => $reportImportId,
                'rate' => null,
                'positive_rows' => 0,
                'negative_rows' => 0,
                'rows' => 0,
            ];
        }

        $rate = (float) $rate;

        if ($rate < 0 || $rate > 100) {
            throw new InvalidArgumentException(
                'Revenue share percentage must be between 0 and 100.'
            );
        }

        $decimal = round($rate / 100, 8);

        return DB::transaction(
            function () use (
                $label,
                $reportImportId,
                $rate,
                $decimal
            ): array {
                $base = DB::table('report_rows')
                    ->where(
                        'report_import_id',
                        $reportImportId
                    )
                    ->where(
                        'label_id',
                        (int) $label->id
                    )
                    ->where(
                        'mapping_status',
                        'mapped'
                    )
                    ->whereExists(
                        function ($query) use (
                            $reportImportId
                        ) {
                            $query
                                ->selectRaw('1')
                                ->from('report_imports')
                                ->whereColumn(
                                    'report_imports.id',
                                    'report_rows.report_import_id'
                                )
                                ->where(
                                    'report_imports.id',
                                    $reportImportId
                                )
                                ->where(
                                    'report_imports.financial_status',
                                    'review_pending'
                                );
                        }
                    );

                $rows = (clone $base)->count();

                $positiveRows = (clone $base)
                    ->where(
                        'collected_revenue',
                        '>=',
                        0
                    )
                    ->update([
                        'label_rate' => $decimal,
                        'earnings' => DB::raw(
                            'ROUND(collected_revenue * '.
                            $decimal.
                            ', 8)'
                        ),
                        'updated_at' => now(),
                    ]);

                $negativeRows = (clone $base)
                    ->where(
                        'collected_revenue',
                        '<',
                        0
                    )
                    ->update([
                        'label_rate' => 1.0,
                        'earnings' => DB::raw(
                            'ROUND(collected_revenue, 8)'
                        ),
                        'updated_at' => now(),
                    ]);

                return [
                    'status' => 'recalculated',
                    'label_id' => (int) $label->id,
                    'report_import_id' =>
                        $reportImportId,
                    'rate' => $rate,
                    'rows' => $rows,
                    'positive_rows' =>
                        $positiveRows,
                    'negative_rows' =>
                        $negativeRows,
                ];
            }
        );
    }

    public function recalculateForArtistInImport(
        Artist $artist,
        int $reportImportId
    ): array {
        $rate = $artist->revenue_share_percentage;

        if ($rate === null) {
            return [
                'status' => 'rate_pending',
                'artist_id' => (int) $artist->id,
                'report_import_id' => $reportImportId,
                'rate' => null,
                'positive_rows' => 0,
                'negative_rows' => 0,
                'rows' => 0,
            ];
        }

        $rate = (float) $rate;

        if ($rate < 0 || $rate > 100) {
            throw new InvalidArgumentException(
                'Revenue share percentage must be between 0 and 100.'
            );
        }

        $decimal = round($rate / 100, 8);

        return DB::transaction(
            function () use (
                $artist,
                $reportImportId,
                $rate,
                $decimal
            ): array {
                $base = DB::table('report_rows')
                    ->where(
                        'report_import_id',
                        $reportImportId
                    )
                    ->where(
                        'artist_id',
                        (int) $artist->id
                    )
                    ->whereNull('label_id')
                    ->where(
                        'mapping_status',
                        'mapped'
                    )
                    ->whereExists(
                        function ($query) use (
                            $reportImportId
                        ) {
                            $query
                                ->selectRaw('1')
                                ->from('report_imports')
                                ->whereColumn(
                                    'report_imports.id',
                                    'report_rows.report_import_id'
                                )
                                ->where(
                                    'report_imports.id',
                                    $reportImportId
                                )
                                ->where(
                                    'report_imports.financial_status',
                                    'review_pending'
                                );
                        }
                    );

                $rows = (clone $base)->count();

                $positiveRows = (clone $base)
                    ->where(
                        'collected_revenue',
                        '>=',
                        0
                    )
                    ->update([
                        'label_rate' => $decimal,
                        'earnings' => DB::raw(
                            'ROUND(collected_revenue * '.
                            $decimal.
                            ', 8)'
                        ),
                        'updated_at' => now(),
                    ]);

                $negativeRows = (clone $base)
                    ->where(
                        'collected_revenue',
                        '<',
                        0
                    )
                    ->update([
                        'label_rate' => 1.0,
                        'earnings' => DB::raw(
                            'ROUND(collected_revenue, 8)'
                        ),
                        'updated_at' => now(),
                    ]);

                return [
                    'status' => 'recalculated',
                    'artist_id' => (int) $artist->id,
                    'report_import_id' =>
                        $reportImportId,
                    'rate' => $rate,
                    'rows' => $rows,
                    'positive_rows' =>
                        $positiveRows,
                    'negative_rows' =>
                        $negativeRows,
                ];
            }
        );
    }

}
