<?php

namespace App\Services\V2;

use App\Models\Core\Label;
use Illuminate\Support\Facades\DB;

class MasterRevenueVisibilityService
{
    public function summary(
        Label $label,
        ?string $statementMonth = null
    ): array {
        $isMaster =
            $label->parent_label_id === null;

        $own = $this->statementTotals(
            'label',
            $label->id,
            $statementMonth
        );

        if (! $isMaster) {
            $share = $this->activeShare(
                'label',
                $label->id
            );

            return [
                'is_master' => false,

                'managed_revenue' =>
                    (float) $own['net'],

                'allocated_revenue' =>
                    (float) $own['net'],

                'retained_revenue' =>
                    0.0,

                'payable_revenue' =>
                    (float) $own['net'],

                'gross_statement_revenue' =>
                    (float) $own['gross'],

                'share_percent' =>
                    $share
                    && (bool)
                        $share->show_revenue_share
                        ? (float)
                            $share
                                ->revenue_share_percent
                        : null,

                'share_visible' =>
                    $share
                        ? (bool)
                            $share
                                ->show_revenue_share
                        : false,

                'children' => [],
            ];
        }

        $children = [];
        $allocated = 0.0;
        $managed = 0.0;

        /*
         * Recursive hierarchy.
         *
         * The master manages its complete catalogue
         * tree. Revenue rows are NOT duplicated;
         * descendants are discovered only for
         * visibility and beneficiary summaries.
         */
        $hierarchy = app(
            LabelHierarchyService::class
        );

        $treeIds = $hierarchy
            ->descendantIds(
                (int) $label->id,
                true
            );

        $descendantIds = $treeIds
            ->reject(
                fn ($id) =>
                    (int) $id === (int) $label->id
            )
            ->values();

        $childLabels = $descendantIds->isEmpty()
            ? collect()
            : DB::table('labels')
                ->whereIn(
                    'id',
                    $descendantIds
                )
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get([
                    'id',
                    'name',
                    'parent_label_id',
                ]);

        foreach ($childLabels as $child) {
            $row = $this->beneficiarySummary(
                $label->id,
                'label',
                (int) $child->id,
                (string) $child->name,
                $statementMonth
            );

            $children[] = $row;

            $allocated +=
                $row['allocated_revenue'];

            $managed +=
                $row['managed_revenue'];
        }

        /*
         * Artists anywhere inside the master's
         * recursive catalogue tree are visible
         * to the master.
         */
        $directArtists = DB::table('artists')
            ->whereIn(
                'label_id',
                $treeIds
            )
            ->whereNull('deleted_at')
            ->get([
                'id',
                'stage_name',
                'label_id',
            ]);

        foreach ($directArtists as $artist) {
            $row = $this->beneficiarySummary(
                $label->id,
                'artist',
                (int) $artist->id,
                (string) $artist->stage_name,
                $statementMonth
            );

            $children[] = $row;

            $allocated +=
                $row['allocated_revenue'];

            $managed +=
                $row['managed_revenue'];
        }

        /*
         * The master's own statement is the
         * authoritative retained/payable amount.
         *
         * Never add managed revenue to payable.
         * Managed revenue is informational and
         * includes source revenue reconstructed
         * from beneficiary allocations.
         */
        $masterPayable =
            (float) $own['net'];

        return [
            'is_master' => true,

            'managed_revenue' =>
                round($managed, 8),

            'allocated_revenue' =>
                round($allocated, 8),

            'retained_revenue' =>
                $masterPayable,

            'payable_revenue' =>
                $masterPayable,

            'gross_statement_revenue' =>
                (float) $own['gross'],

            'share_percent' => null,
            'share_visible' => false,

            'children' => $children,
        ];
    }

    public function artistSummary(
        int $artistId,
        ?string $statementMonth = null
    ): array {
        $artist = DB::table('artists')
            ->where('id', $artistId)
            ->whereNull('deleted_at')
            ->first([
                'id',
                'label_id',
            ]);

        $own = $this->statementTotals(
            'artist',
            $artistId,
            $statementMonth
        );

        if (! $artist) {
            return [
                'is_master' => false,

                'managed_revenue' => 0.0,
                'allocated_revenue' => 0.0,
                'retained_revenue' => 0.0,
                'payable_revenue' => 0.0,
                'gross_statement_revenue' => 0.0,

                'share_percent' => null,
                'share_visible' => false,

                'children' => [],
            ];
        }

        /*
         * A direct artist's label_id identifies the
         * master account that owns the beneficiary
         * revenue-share contract.
         *
         * The beneficiary always sees its payable
         * amount. Percentage visibility is controlled
         * exclusively by show_revenue_share.
         */
        $share = $artist->label_id
            ? $this->activeShare(
                'artist',
                $artistId,
                (int) $artist->label_id
            )
            : null;

        return [
            'is_master' => false,

            'managed_revenue' =>
                (float) $own['net'],

            'allocated_revenue' =>
                (float) $own['net'],

            'retained_revenue' =>
                0.0,

            'payable_revenue' =>
                (float) $own['net'],

            'gross_statement_revenue' =>
                (float) $own['gross'],

            'share_percent' =>
                $share
                && (bool)
                    $share->show_revenue_share
                    ? (float)
                        $share->revenue_share_percent
                    : null,

            'share_visible' =>
                $share
                    ? (bool)
                        $share->show_revenue_share
                    : false,

            'children' => [],
        ];
    }

    private function beneficiarySummary(
        int $masterLabelId,
        string $type,
        int $id,
        string $name,
        ?string $statementMonth
    ): array {
        $totals = $this->statementTotals(
            $type,
            $id,
            $statementMonth
        );

        $allocated =
            (float) $totals['net'];

        $share = $this->activeShare(
            $type,
            $id,
            $masterLabelId
        );

        $percent =
            $share
                ? (float)
                    $share->revenue_share_percent
                : 0.0;

        /*
         * Beneficiary statement already contains
         * the beneficiary's allocated amount.
         *
         * Example:
         * 80 payable at 80% = 100 managed source.
         */
        $managed =
            $percent > 0
                ? $allocated /
                    ($percent / 100)
                : 0.0;

        return [
            'id' => $id,

            'type' => $type,

            'name' => $name,

            'allocated_revenue' =>
                round($allocated, 8),

            'managed_revenue' =>
                round($managed, 8),

            'master_retained' =>
                round(
                    max(
                        0,
                        $managed - $allocated
                    ),
                    8
                ),

            'share_percent' =>
                $percent,

            /*
             * Master is allowed to know the
             * configured split because it owns
             * and manages the revenue contract.
             *
             * show_revenue_share controls
             * beneficiary-side visibility.
             */
            'share_visible' =>
                $share
                    ? (bool)
                        $share->show_revenue_share
                    : false,
        ];
    }

    private function activeShare(
        string $beneficiaryType,
        int $beneficiaryId,
        ?int $masterLabelId = null
    ): ?object {
        $query = DB::table(
            'label_revenue_shares'
        )
            ->where(
                'beneficiary_type',
                $beneficiaryType
            )
            ->where(
                'beneficiary_id',
                $beneficiaryId
            )
            ->where(
                'is_active',
                true
            );

        if ($masterLabelId !== null) {
            $query->where(
                'master_label_id',
                $masterLabelId
            );
        }

        return $query
            ->orderByDesc('id')
            ->first();
    }

    private function statementTotals(
        string $payeeType,
        int $payeeId,
        ?string $statementMonth = null
    ): array {
        if (
            ! DB::getSchemaBuilder()
                ->hasTable(
                    'royalty_statements'
                )
        ) {
            return [
                'gross' => 0.0,
                'net' => 0.0,
            ];
        }

        $ownerColumn = match ($payeeType) {
            'artist' => 'artist_id',
            'label' => 'label_id',
            default => null,
        };

        if ($ownerColumn === null) {
            return [
                'gross' => 0.0,
                'net' => 0.0,
            ];
        }

        $query = DB::table(
            'royalty_statements'
        )->where(
            $ownerColumn,
            $payeeId
        );

        if (
            $statementMonth !== null
            && $statementMonth !== ''
        ) {
            $query->where(
                'statement_month',
                $statementMonth
            );
        }

        return [
            'gross' =>
                (float)
                    (clone $query)
                        ->sum(
                            'gross_earnings'
                        ),

            'net' =>
                (float)
                    (clone $query)
                        ->sum(
                            'net_payable'
                        ),
        ];
    }
}
