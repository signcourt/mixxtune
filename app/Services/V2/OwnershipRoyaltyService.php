<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OwnershipRoyaltyService
{
    public function generateMonthlyStatements(
        string $month,
        float $commissionPercent = 0,
        string $currency = 'INR'
    ): array {
        $owners = DB::table('report_rows')
            ->where('sale_month', $month)
            ->where('mapping_status', 'mapped')
            ->whereNotNull(
                'revenue_owner_type'
            )
            ->whereNotNull(
                'revenue_owner_id'
            )
            ->select([
                'revenue_owner_type',
                'revenue_owner_id',
            ])
            ->distinct()
            ->get();

        $created = 0;
        $updated = 0;
        $failed = [];

        foreach ($owners as $owner) {
            try {
                $existing = DB::table(
                    'royalty_statements'
                )
                    ->where(
                        'statement_month',
                        $month
                    )
                    ->where(
                        'currency',
                        strtoupper($currency)
                    )
                    ->when(
                        $owner->revenue_owner_type
                            === 'label',
                        fn ($query) =>
                            $query
                                ->where(
                                    'label_id',
                                    $owner
                                        ->revenue_owner_id
                                )
                                ->whereNull(
                                    'artist_id'
                                ),
                        fn ($query) =>
                            $query
                                ->where(
                                    'artist_id',
                                    $owner
                                        ->revenue_owner_id
                                )
                                ->whereNull(
                                    'label_id'
                                )
                    )
                    ->first();

                $this->generateOwnerStatement(
                    $month,
                    $owner->revenue_owner_type,
                    (int) $owner->revenue_owner_id,
                    $commissionPercent,
                    $currency,
                    $existing
                );

                $existing
                    ? $updated++
                    : $created++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'owner_type' =>
                        $owner->revenue_owner_type,

                    'owner_id' =>
                        $owner->revenue_owner_id,

                    'message' =>
                        $exception->getMessage(),
                ];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed_count' =>
                count($failed),
            'failed' => $failed,
        ];
    }

    private function generateOwnerStatement(
        string $month,
        string $ownerType,
        int $ownerId,
        float $commissionPercent,
        string $currency,
        ?object $existing
    ): void {
        DB::transaction(function () use (
            $month,
            $ownerType,
            $ownerId,
            $commissionPercent,
            $currency,
            $existing
        ) {
            $query = DB::table('report_rows')
                ->where(
                    'sale_month',
                    $month
                )
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->where(
                    'revenue_owner_type',
                    $ownerType
                )
                ->where(
                    'revenue_owner_id',
                    $ownerId
                );

            $gross = round(
                (float) (clone $query)
                    ->sum('earnings'),
                8
            );

            $commission = round(
                $gross
                * ($commissionPercent / 100),
                8
            );

            $net = round(
                $gross - $commission,
                8
            );

            $statementData = [
                'public_id' =>
                    $existing?->public_id
                    ?: (string) Str::ulid(),

                'artist_id' =>
                    $ownerType === 'artist'
                        ? $ownerId
                        : null,

                'label_id' =>
                    $ownerType === 'label'
                        ? $ownerId
                        : null,

                'statement_month' =>
                    $month,

                'currency' =>
                    strtoupper($currency),

                'gross_earnings' =>
                    $gross,

                'commission_amount' =>
                    $commission,

                'tax_amount' =>
                    0,

                'other_deductions' =>
                    0,

                'net_payable' =>
                    $net,

                'status' =>
                    'pending',

                'updated_at' =>
                    now(),
            ];

            if ($existing) {
                DB::table(
                    'royalty_statements'
                )
                    ->where(
                        'id',
                        $existing->id
                    )
                    ->update(
                        $statementData
                    );

                $statementId =
                    $existing->id;
            } else {
                $statementData[
                    'created_at'
                ] = now();

                $statementId =
                    DB::table(
                        'royalty_statements'
                    )->insertGetId(
                        $statementData
                    );
            }

            DB::table('royalty_allocations')
                ->where(
                    'royalty_statement_id',
                    $statementId
                )
                ->delete();

            $query
                ->select([
                    'id',
                    'release_id',
                    'track_id',
                    'earnings',
                ])
                ->orderBy('id')
                ->chunkById(
                    1000,
                    function ($rows) use (
                        $statementId,
                        $commissionPercent
                    ) {
                        $payload = [];

                        foreach ($rows as $row) {
                            $grossAmount =
                                (float) $row->earnings;

                            $payload[] = [
                                'public_id' =>
                                    (string) Str::ulid(),

                                'royalty_statement_id' =>
                                    $statementId,

                                'report_row_id' =>
                                    $row->id,

                                'release_id' =>
                                    $row->release_id,

                                'track_id' =>
                                    $row->track_id,

                                'gross_amount' =>
                                    $grossAmount,

                                'net_amount' =>
                                    round(
                                        $grossAmount
                                        * (
                                            1
                                            - (
                                                $commissionPercent
                                                / 100
                                            )
                                        ),
                                        8
                                    ),

                                'share_percentage' =>
                                    100,

                                'created_at' =>
                                    now(),

                                'updated_at' =>
                                    now(),
                            ];
                        }

                        if ($payload !== []) {
                            DB::table(
                                'royalty_allocations'
                            )->insert($payload);
                        }
                    }
                );
        });
    }
}
