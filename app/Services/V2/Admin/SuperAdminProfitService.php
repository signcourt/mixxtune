<?php

namespace App\Services\V2\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SuperAdminProfitService
{
public function baseQuery(
        ?string $month = null,
        ?string $platform = null,
        ?string $ownerType = null,
        ?int $ownerId = null
    ): Builder {
        $query = DB::table('report_rows')
            ->whereNotNull('revenue_owner_type')
            ->whereNotNull('revenue_owner_id');

        if ($month !== null && $month !== '') {
            /*
             * reporting_month is authoritative.
             *
             * Legacy/manual rows created before the
             * reporting-month contract may have only
             * sale_month populated. Those rows must
             * remain selectable in the correct month.
             */
            $query->where(
                function ($builder) use ($month) {
                    $builder
                        ->where(
                            'reporting_month',
                            $month
                        )
                        ->orWhere(
                            function ($legacy) use ($month) {
                                $legacy
                                    ->where(
                                        function ($missing) {
                                            $missing
                                                ->whereNull(
                                                    'reporting_month'
                                                )
                                                ->orWhere(
                                                    'reporting_month',
                                                    ''
                                                );
                                        }
                                    )
                                    ->where(
                                        'sale_month',
                                        $month
                                    );
                            }
                        );
                }
            );
        }

        if ($platform !== null && $platform !== '') {
            $query->where(
                'platform',
                $platform
            );
        }

        if (
            $ownerType !== null &&
            in_array(
                $ownerType,
                ['label', 'artist'],
                true
            )
        ) {
            $query->where(
                'revenue_owner_type',
                $ownerType
            );
        }

        if ($ownerId !== null) {
            $query->where(
                'revenue_owner_id',
                $ownerId
            );
        }

        return $query;
    }

    /*
     * W92 MIXXTUNE_PROFIT_PUBLISHED_ONLY
     * stage = published : rows of published reports (and old rows that were
     *                     never part of a report import) - this is the money
     *                     owners already see in their wallets;
     * stage = review    : rows of reports not published yet (waiting for
     *                     review / approved and scheduled);
     * stage = all       : everything (the old behaviour).
     */
    public function withStage(Builder $query, string $stage): Builder
    {
        if (!in_array($stage, ['published', 'review'], true)
            || !\Illuminate\Support\Facades\Schema::hasColumn('report_imports', 'financial_status')) {
            return $query;
        }

        $published = DB::table('report_imports')->select('id')->where('financial_status', 'published');

        if ($stage === 'published') {
            return $query->where(function ($q) use ($published) {
                $q->whereNull('report_rows.report_import_id')
                    ->orWhereIn('report_rows.report_import_id', $published);
            });
        }

        return $query->whereNotNull('report_rows.report_import_id')
            ->whereNotIn('report_rows.report_import_id', $published);
    }

    public function calculateRow(
        object $row
    ): array {
        /*
         * MIXX TUNE IMMUTABLE FINANCIAL SNAPSHOT
         * ======================================
         *
         * Revenue is calculated once during import.
         *
         * report_rows.collected_revenue
         *     = source CSV Collected Revenue
         *
         * report_rows.label_rate
         *     = Applied Rate snapshot in decimal form
         *       0.80 = 80%
         *       1.00 = 100%
         *
         * report_rows.earnings
         *     = final Label / Artist Earning
         *
         * Super Admin Platform Margin:
         *
         *     Collected Revenue - Earning
         *
         * Negative rows are imported at 100%, therefore:
         *
         *     -100 - (-100) = 0 platform margin
         *
         * Never recalculate historical rows using the
         * account's current commercial rate.
         */

        $collected = round(
            (float) (
                $row->collected_revenue
                ?? 0
            ),
            8
        );

        $storedRate = round(
            (float) (
                $row->label_rate
                ?? 0
            ),
            8
        );

        $userEarning = round(
            (float) (
                $row->earnings
                ?? 0
            ),
            8
        );

        /*
         * Profit cannot be created from a negative
         * adjustment.
         *
         * New imported negative rows already contain:
         *
         * collected = negative amount
         * rate      = 1.00
         * earning   = full negative amount
         *
         * So their margin naturally resolves to zero.
         */
        $profit =
            $collected < 0
                ? 0.0
                : round(
                    $collected
                    - $userEarning,
                    8
                );

        return [
            'collected_revenue' =>
                $collected,

            /*
             * Existing API field name is retained for
             * backward compatibility.
             *
             * Value is now the stored decimal snapshot:
             * 0.80 = 80%, 1.00 = 100%.
             */
            /*
             * Public Profit API keeps percentage semantics
             * for backward-compatible UI/display:
             *
             * stored label_rate 0.80 => assigned_rate 80.0
             */
            'assigned_rate' =>
                round(
                    $storedRate * 100,
                    4
                ),

            'stored_rate' =>
                $storedRate,

            'user_earning' =>
                $userEarning,

            'super_admin_profit' =>
                $profit,
        ];
    }

    public function summary(
        Builder $query
    ): array {
        $summary = [
            'collected_revenue' => 0.0,
            'user_earning' => 0.0,
            'super_admin_profit' => 0.0,
            'positive_revenue' => 0.0,
            'negative_adjustments' => 0.0,
            'rows' => 0,
        ];

        foreach (
            (clone $query)
                ->orderBy('id')
                ->cursor()
            as $row
        ) {
            $calc =
                $this->calculateRow($row);

            $summary[
                'collected_revenue'
            ] +=
                $calc['collected_revenue'];

            $summary[
                'user_earning'
            ] +=
                $calc['user_earning'];

            $summary[
                'super_admin_profit'
            ] +=
                $calc['super_admin_profit'];

            if (
                $calc[
                    'collected_revenue'
                ] < 0
            ) {
                $summary[
                    'negative_adjustments'
                ] +=
                    $calc[
                        'collected_revenue'
                    ];
            } else {
                $summary[
                    'positive_revenue'
                ] +=
                    $calc[
                        'collected_revenue'
                    ];
            }

            $summary['rows']++;
        }

        foreach (
            [
                'collected_revenue',
                'user_earning',
                'super_admin_profit',
                'positive_revenue',
                'negative_adjustments',
            ]
            as $field
        ) {
            $summary[$field] =
                round(
                    $summary[$field],
                    8
                );
        }

        return $summary;
    }

    public function breakdown(
        Builder $query
    ): Collection {
        $grouped = [];

        foreach (
            (clone $query)
                ->orderBy('id')
                ->cursor()
            as $row
        ) {
            $type =
                (string)
                    $row
                        ->revenue_owner_type;

            $id =
                (int)
                    $row
                        ->revenue_owner_id;

            $key =
                $type.':'.$id;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'type' => $type,
                    'id' => $id,
                    'name' =>
                        $this->ownerName(
                            $type,
                            $id
                        ),
                    'assigned_rate' =>
                        null,
                    'rate_values' =>
                        [],
                    'collected_revenue' =>
                        0.0,
                    'user_earning' =>
                        0.0,
                    'super_admin_profit' =>
                        0.0,
                    'rows' => 0,
                ];
            }

            $calc =
                $this->calculateRow($row);

            $grouped[$key][
                'rate_values'
            ][
                number_format(
                    (float)
                        $calc['assigned_rate'],
                    4,
                    '.',
                    ''
                )
            ] = true;

            foreach (
                [
                    'collected_revenue',
                    'user_earning',
                    'super_admin_profit',
                ]
                as $field
            ) {
                $grouped[
                    $key
                ][$field] +=
                    $calc[$field];
            }

            $grouped[$key]['rows']++;
        }

        foreach (
            $grouped as &$item
        ) {
            foreach (
                [
                    'collected_revenue',
                    'user_earning',
                    'super_admin_profit',
                ]
                as $field
            ) {
                $item[$field] =
                    round(
                        $item[$field],
                        8
                    );
            }

            $rates =
                array_keys(
                    $item[
                        'rate_values'
                    ]
                );

            $item['assigned_rate'] =
                count($rates) === 1
                    ? (float) $rates[0]
                    : null;

            $item['rate_is_mixed'] =
                count($rates) > 1;

            unset(
                $item[
                    'rate_values'
                ]
            );
        }

        unset($item);

        return collect(
            array_values($grouped)
        )
            ->sortByDesc(
                'super_admin_profit'
            )
            ->values();
    }

    public function monthly(
        $query
    ): Collection {
        /*
         * Month-wise business analytics uses exactly the
         * same immutable financial snapshots as summary()
         * and breakdown().
         *
         * No current Label / Artist rate is recalculated.
         */
        $grouped = [];

        foreach (
            $query
                ->orderBy('id')
                ->cursor()
            as $row
        ) {
            $month =
                $this->effectiveMonth(
                    $row
                );

            if ($month === '') {
                continue;
            }

            if (! isset($grouped[$month])) {
                $grouped[$month] = [
                    'month' =>
                        $month,

                    'collected_revenue' =>
                        0.0,

                    'user_earning' =>
                        0.0,

                    'platform_margin' =>
                        0.0,

                    /*
                     * Compatibility with existing internal
                     * Profit naming.
                     */
                    'super_admin_profit' =>
                        0.0,

                    'negative_adjustments' =>
                        0.0,

                    'rows' =>
                        0,
                ];
            }

            $calc =
                $this->calculateRow(
                    $row
                );

            $grouped[$month][
                'collected_revenue'
            ] +=
                $calc[
                    'collected_revenue'
                ];

            $grouped[$month][
                'user_earning'
            ] +=
                $calc[
                    'user_earning'
                ];

            $grouped[$month][
                'platform_margin'
            ] +=
                $calc[
                    'super_admin_profit'
                ];

            $grouped[$month][
                'super_admin_profit'
            ] +=
                $calc[
                    'super_admin_profit'
                ];

            if (
                $calc[
                    'collected_revenue'
                ] < 0
            ) {
                $grouped[$month][
                    'negative_adjustments'
                ] +=
                    $calc[
                        'collected_revenue'
                    ];
            }

            $grouped[$month]['rows']++;
        }

        foreach (
            $grouped as &$item
        ) {
            foreach (
                [
                    'collected_revenue',
                    'user_earning',
                    'platform_margin',
                    'super_admin_profit',
                    'negative_adjustments',
                ]
                as $field
            ) {
                $item[$field] =
                    round(
                        $item[$field],
                        8
                    );
            }
        }

        unset($item);

        return collect(
            array_values($grouped)
        )
            ->sortByDesc('month')
            ->values();
    }

    public function months(): Collection
    {
        /*
         * reporting_month remains authoritative.
         * sale_month is used only for legacy rows
         * whose reporting_month is missing.
         */
        return DB::table('report_rows')
            ->selectRaw(
                "CASE
                    WHEN reporting_month IS NOT NULL
                         AND reporting_month <> ''
                    THEN reporting_month
                    ELSE sale_month
                 END AS effective_month"
            )
            ->where(function ($query) {
                $query
                    ->where(function ($current) {
                        $current
                            ->whereNotNull(
                                'reporting_month'
                            )
                            ->where(
                                'reporting_month',
                                '<>',
                                ''
                            );
                    })
                    ->orWhere(function ($legacy) {
                        $legacy
                            ->where(function ($missing) {
                                $missing
                                    ->whereNull(
                                        'reporting_month'
                                    )
                                    ->orWhere(
                                        'reporting_month',
                                        ''
                                    );
                            })
                            ->whereNotNull(
                                'sale_month'
                            )
                            ->where(
                                'sale_month',
                                '<>',
                                ''
                            );
                    });
            })
            ->distinct()
            ->orderByDesc(
                'effective_month'
            )
            ->pluck(
                'effective_month'
            );
    }

    public function platforms(): Collection
    {
        return DB::table('report_rows')
            ->whereNotNull('platform')
            ->where('platform', '<>', '')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform');
    }

    public function owners(): Collection
    {
        return DB::table('report_rows')
            ->whereNotNull(
                'revenue_owner_type'
            )
            ->whereNotNull(
                'revenue_owner_id'
            )
            ->whereIn(
                'revenue_owner_type',
                [
                    'label',
                    'artist',
                ]
            )
            ->select([
                'revenue_owner_type',
                'revenue_owner_id',
            ])
            ->distinct()
            ->get()
            ->map(
                function (object $row): array {
                    $type =
                        (string)
                            $row
                                ->revenue_owner_type;

                    $id =
                        (int)
                            $row
                                ->revenue_owner_id;

                    return [
                        'type' =>
                            $type,
                        'id' =>
                            $id,
                        'name' =>
                            $this->ownerName(
                                $type,
                                $id
                            ),
                    ];
                }
            )
            ->sortBy(
                fn (array $owner) =>
                    strtolower(
                        (string)
                            $owner['name']
                    )
            )
            ->values();
    }

    public function effectiveMonth(
        object $row
    ): string {
        $reportingMonth =
            trim(
                (string)
                    ($row->reporting_month ?? '')
            );

        if ($reportingMonth !== '') {
            return $reportingMonth;
        }

        return trim(
            (string)
                ($row->sale_month ?? '')
        );
    }

    private function ownerName(
        string $type,
        int $id
    ): string {
        if ($type === 'label') {
            return (string) (
                DB::table('labels')
                    ->where('id', $id)
                    ->value('name')
                ?: 'Unknown Label'
            );
        }

        if ($type === 'artist') {
            return (string) (
                DB::table('artists')
                    ->where('id', $id)
                    ->value('stage_name')
                ?: DB::table('artists')
                    ->where('id', $id)
                    ->value('legal_name')
                ?: 'Unknown Artist'
            );
        }

        return 'Unknown Account';
    }
}
