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
            $query->where(
                'sale_month',
                $month
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

        $storedRate = $this->accountRate(
            (string) $row->revenue_owner_type,
            (int) $row->revenue_owner_id
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
                        $this->accountRate(
                            $type,
                            $id
                        ),
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
        return DB::table('report_rows')
            ->whereNotNull('sale_month')
            ->where('sale_month', '<>', '')
            ->distinct()
            ->orderByDesc('sale_month')
            ->pluck('sale_month');
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
