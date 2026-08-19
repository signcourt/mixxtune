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

    public function calculateRow(
        object $row
    ): array {
        $collected = round(
            (float) ($row->earnings ?? 0),
            8
        );

        $storedRate = $this->canonicalRate(
            $row
        );

        /*
         * MIXX TUNE FINANCIAL RULE
         *
         * Positive:
         *   user = collected × assigned rate
         *   profit = collected - user
         *
         * Negative:
         *   effective rate = 100%
         *   user receives full negative adjustment
         *   super admin retained profit = 0
         */
        $effectiveRate =
            $collected < 0
                ? 100.0
                : $storedRate;

        $userEarning = round(
            $collected
            * ($effectiveRate / 100),
            8
        );

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

            'assigned_rate' =>
                $effectiveRate,

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

    private function canonicalRate(
        object $row
    ): float {
        $ownerType =
            (string)
                ($row->revenue_owner_type ?? '');

        $ownerId =
            (int)
                ($row->revenue_owner_id ?? 0);

        /*
         * MASTER LABEL BENEFICIARY RULE
         *
         * report_rows.revenue_owner_id identifies
         * the master label whose report contains the
         * revenue.
         *
         * A row can then belong to a configured direct
         * Artist or Sub-Label beneficiary.
         *
         * Resolution must match RevenueSharingController:
         *
         * 1. Artist takes precedence.
         * 2. Otherwise direct Sub-Label.
         * 3. Share must be active for the sale month/date.
         * 4. If no beneficiary share matches, fall back
         *    to the report owner's normal account rate.
         */
        if (
            $ownerType === 'label'
            && $ownerId > 0
        ) {
            $share =
                $this->beneficiaryShare(
                    $ownerId,
                    $row
                );

            if ($share !== null) {
                return $this->normalizeRate(
                    $share
                        ->revenue_share_percent
                );
            }
        }

        return $this->accountRate(
            $ownerType,
            $ownerId
        );
    }

    private function beneficiaryShare(
        int $masterLabelId,
        object $row
    ): ?object {
        $candidates = [];

        if (
            isset($row->artist_id)
            && (int) $row->artist_id > 0
        ) {
            $candidates[] = [
                'type' => 'artist',
                'id' =>
                    (int) $row->artist_id,
            ];
        }

        if (
            isset($row->label_id)
            && (int) $row->label_id > 0
        ) {
            $candidates[] = [
                'type' => 'label',
                'id' =>
                    (int) $row->label_id,
            ];
        }

        foreach ($candidates as $candidate) {
            $shares =
                DB::table(
                    'label_revenue_shares'
                )
                    ->where(
                        'master_label_id',
                        $masterLabelId
                    )
                    ->where(
                        'beneficiary_type',
                        $candidate['type']
                    )
                    ->where(
                        'beneficiary_id',
                        $candidate['id']
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->orderByDesc('id')
                    ->get();

            foreach ($shares as $share) {
                if (
                    $this->shareAppliesToRow(
                        $share,
                        $row
                    )
                ) {
                    return $share;
                }
            }
        }

        return null;
    }

    private function shareAppliesToRow(
        object $share,
        object $row
    ): bool {
        /*
         * Keep this identical to the existing
         * RevenueSharingController monthly rule.
         *
         * DSP revenue is allocated by sale month where
         * sale_month is available.
         */
        if (! empty($row->sale_month)) {
            $rowMonth = substr(
                (string) $row->sale_month,
                0,
                7
            );

            if (! empty($share->effective_from)) {
                $fromMonth = substr(
                    (string)
                        $share->effective_from,
                    0,
                    7
                );

                if ($rowMonth < $fromMonth) {
                    return false;
                }
            }

            if (! empty($share->effective_to)) {
                $toMonth = substr(
                    (string)
                        $share->effective_to,
                    0,
                    7
                );

                if ($rowMonth > $toMonth) {
                    return false;
                }
            }

            return true;
        }

        if (! empty($row->sale_date)) {
            $date = substr(
                (string) $row->sale_date,
                0,
                10
            );

            if (
                ! empty($share->effective_from)
                && $date <
                    substr(
                        (string)
                            $share->effective_from,
                        0,
                        10
                    )
            ) {
                return false;
            }

            if (
                ! empty($share->effective_to)
                && $date >
                    substr(
                        (string)
                            $share->effective_to,
                        0,
                        10
                    )
            ) {
                return false;
            }
        }

        return true;
    }

    private function accountRate(
        string $type,
        int $id
    ): float {
        if ($type === 'label') {
            $rate = DB::table('labels')
                ->where('id', $id)
                ->value(
                    'revenue_share_percentage'
                );
        } elseif ($type === 'artist') {
            $rate = DB::table('artists')
                ->where('id', $id)
                ->value(
                    'revenue_share_percentage'
                );
        } else {
            $rate = null;
        }

        if ($rate === null) {
            return 100.0;
        }

        return $this->normalizeRate(
            $rate
        );
    }

    private function normalizeRate(
        mixed $rate
    ): float {
        return round(
            max(
                0.0,
                min(
                    100.0,
                    (float) $rate
                )
            ),
            4
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
