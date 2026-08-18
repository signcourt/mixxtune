<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\RoyaltyAllocation;
use App\Models\Finance\RoyaltyStatement;
use App\Models\Reports\ReportRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoyaltyService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly OwnershipRoyaltyService $ownershipRoyalty
    ) {
    }

    public function generateMonthlyStatements(
        string $month,
        float $commissionPercent = 0,
        string $currency = 'INR'
    ): array {
        /*
         * Canonical V2 royalty engine.
         *
         * Catalogue ownership remains independent
         * from beneficiary revenue allocation.
         *
         * Supports:
         * - canonical label owner
         * - direct artist beneficiary
         * - direct sub-label beneficiary
         * - master retained difference
         * - effective-dated revenue shares
         */
        return $this
            ->ownershipRoyalty
            ->generateMonthlyStatements(
                $month,
                $commissionPercent,
                $currency
            );
    }

    public function approve(
        RoyaltyStatement $statement,
        User $admin
    ): RoyaltyStatement {
        abort_unless(
            in_array(
                $statement->status,
                ['pending', 'generated'],
                true
            ),
            422,
            'Only pending statements can be approved.'
        );

        $owner = $this->ownerUser(
            $statement
        );

        abort_unless(
            $owner,
            422,
            'Statement owner user is missing.'
        );

        DB::transaction(
            function () use (
                $statement,
                $admin,
                $owner
            ) {
                $statement->update([
                    'status' =>
                        'approved',

                    'approved_at' =>
                        now(),

                    'approved_by' =>
                        $admin->id,
                ]);

                $this->wallet->creditPending(
                    $owner,
                    (float) $statement
                        ->net_payable,
                    'royalty_statement',
                    [
                        'currency' =>
                            $statement->currency,

                        'reference_type' =>
                            RoyaltyStatement::class,

                        'reference_id' =>
                            $statement->id,

                        'reference_code' =>
                            $statement->public_id,

                        'description' =>
                            "Royalty statement {$statement->statement_month}",

                        'created_by' =>
                            $admin->id,
                    ]
                );
            }
        );

        return $statement->fresh();
    }

    public function makeAvailable(
        RoyaltyStatement $statement,
        User $admin
    ): RoyaltyStatement {
        abort_unless(
            $statement->status ===
                'approved',
            422,
            'Statement must be approved first.'
        );

        $owner = $this->ownerUser(
            $statement
        );

        abort_unless(
            $owner,
            422,
            'Statement owner user is missing.'
        );

        DB::transaction(
            function () use (
                $statement,
                $owner,
                $admin
            ) {
                $this->wallet->releasePending(
                    $owner,
                    (float) $statement
                        ->net_payable,
                    [
                        'currency' =>
                            $statement->currency,

                        'reference_type' =>
                            RoyaltyStatement::class,

                        'reference_id' =>
                            $statement->id,

                        'reference_code' =>
                            $statement->public_id,

                        'description' =>
                            "Royalty available for {$statement->statement_month}",

                        'created_by' =>
                            $admin->id,
                    ]
                );

                $statement->update([
                    'status' =>
                        'available',

                    'available_at' =>
                        now(),
                ]);
            }
        );

        return $statement->fresh();
    }

    private function generateArtistStatement(
        int $artistId,
        string $month,
        float $commissionPercent,
        string $currency
    ): RoyaltyStatement {
        return DB::transaction(function () use (
            $artistId,
            $month,
            $commissionPercent,
            $currency
        ) {
            $baseQuery = ReportRow::query()
                ->where('reporting_month', $month)
                ->where('artist_id', $artistId);

            $gross = round(
                (float) (clone $baseQuery)->sum('earnings'),
                8
            );

            $commission = round(
                $gross * ($commissionPercent / 100),
                8
            );

            $net = round(
                $gross - $commission,
                8
            );

            $existing = RoyaltyStatement::query()
                ->where('artist_id', $artistId)
                ->whereNull('label_id')
                ->where('statement_month', $month)
                ->where('currency', strtoupper($currency))
                ->first();

            $statement = RoyaltyStatement::query()
                ->updateOrCreate(
                    [
                        'artist_id' => $artistId,
                        'label_id' => null,
                        'statement_month' => $month,
                        'currency' => strtoupper($currency),
                    ],
                    [
                        'public_id' => $existing?->public_id
                            ?: (string) Str::ulid(),
                        'gross_earnings' => $gross,
                        'commission_amount' => $commission,
                        'tax_amount' => 0,
                        'other_deductions' => 0,
                        'net_payable' => $net,
                        'status' => 'pending',
                    ]
                );

            RoyaltyAllocation::query()
                ->where(
                    'royalty_statement_id',
                    $statement->id
                )
                ->delete();

            $now = now();

            $baseQuery
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
                        $statement,
                        $commissionPercent,
                        $now
                    ) {
                        $payload = [];

                        foreach ($rows as $row) {
                            $grossAmount =
                                (float) $row->earnings;

                            $payload[] = [
                                'public_id' =>
                                    (string) Str::ulid(),

                                'royalty_statement_id' =>
                                    $statement->id,

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
                                    $now,

                                'updated_at' =>
                                    $now,
                            ];
                        }

                        if ($payload !== []) {
                            DB::table(
                                'royalty_allocations'
                            )->insert($payload);
                        }
                    }
                );

            $wasRecentlyCreated =
                $statement->wasRecentlyCreated;

            $statement = $statement->fresh();

            $statement->wasRecentlyCreated =
                $wasRecentlyCreated;

            return $statement;
        });
    }

    private function ownerUser(
        RoyaltyStatement $statement
    ): ?User {
        if ($statement->artist_id) {
            $artist = Artist::query()
                ->find(
                    $statement->artist_id
                );

            return $artist?->user_id
                ? User::query()->find(
                    $artist->user_id
                )
                : null;
        }

        if ($statement->label_id) {
            $label = Label::query()
                ->find(
                    $statement->label_id
                );

            return $label?->user_id
                ? User::query()->find(
                    $label->user_id
                )
                : null;
        }

        return null;
    }
}
