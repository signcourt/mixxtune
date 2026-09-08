<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\RoyaltyAllocation;
use App\Models\Finance\RoyaltyStatement;
use App\Models\Finance\RecoupmentPlan;
use App\Services\V3\RecoupmentManagementService;
use App\Models\Reports\ReportRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoyaltyService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly OwnershipRoyaltyService $ownershipRoyalty,
        private readonly RecoupmentManagementService $recoupment
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
        DB::transaction(
            function () use (
                $statement,
                $admin
            ) {
                /*
                 * Lock and re-read the statement inside the
                 * financial transaction.
                 *
                 * This prevents concurrent approval requests
                 * from crediting the wallet twice.
                 */
                $lockedStatement =
                    RoyaltyStatement::query()
                        ->whereKey($statement->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                abort_unless(
                    in_array(
                        $lockedStatement->status,
                        ['pending', 'generated'],
                        true
                    ),
                    422,
                    'Only pending statements can be approved.'
                );

                $owner = $this->ownerUser(
                    $lockedStatement
                );

                abort_unless(
                    $owner,
                    422,
                    'Statement owner user is missing.'
                );

                /*
                 * Recoupment is scoped to the exact financial
                 * beneficiary represented by this statement.
                 *
                 * Priority:
                 * 1. exact artist plan for artist statement
                 * 2. exact label plan for label statement
                 * 3. account-level plan with no entity scope
                 *
                 * createPlan() currently allows only one active
                 * plan per user, but keeping this lookup explicit
                 * prevents accidental cross-entity recovery if
                 * that rule changes later.
                 */
                $planQuery =
                    RecoupmentPlan::query()
                        ->where(
                            'user_id',
                            $owner->id
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->where(
                            'outstanding_amount',
                            '>',
                            0
                        );

                if ($lockedStatement->artist_id) {
                    $plan = (clone $planQuery)
                        ->where(
                            'artist_id',
                            $lockedStatement->artist_id
                        )
                        ->whereNull('label_id')
                        ->first();

                    if (! $plan) {
                        $plan = (clone $planQuery)
                            ->whereNull('artist_id')
                            ->whereNull('label_id')
                            ->first();
                    }
                } elseif (
                    $lockedStatement->label_id
                ) {
                    $plan = (clone $planQuery)
                        ->where(
                            'label_id',
                            $lockedStatement->label_id
                        )
                        ->whereNull('artist_id')
                        ->first();

                    if (! $plan) {
                        $plan = (clone $planQuery)
                            ->whereNull('artist_id')
                            ->whereNull('label_id')
                            ->first();
                    }
                } else {
                    $plan = (clone $planQuery)
                        ->whereNull('artist_id')
                        ->whereNull('label_id')
                        ->first();
                }

                $recovery = null;

                if ($plan) {
                    $recovery =
                        $this->recoupment
                            ->applyStatementRecovery(
                                $plan,
                                (float)
                                $lockedStatement
                                    ->gross_earnings,
                                (float)
                                $lockedStatement
                                    ->net_payable,
                                [
                                    'royalty_statement_id' =>
                                        $lockedStatement->id,

                                    'reporting_month' =>
                                        $lockedStatement
                                            ->statement_month,

                                    'reference' =>
                                        'Royalty statement '
                                        .$lockedStatement
                                            ->public_id,

                                    'idempotency_key' =>
                                        'royalty_statement:'
                                        .$lockedStatement->id
                                        .':approval',
                                ]
                            );
                }

                if ($recovery) {
                    $recoveryAmount = round(
                        (float)
                        $recovery
                            ->applied_recovery_amount,
                        8
                    );

                    $lockedStatement->update([
                        'other_deductions' =>
                            round(
                                (float)
                                $lockedStatement
                                    ->other_deductions
                                + $recoveryAmount,
                                8
                            ),

                        'net_payable' =>
                            round(
                                max(
                                    0,
                                    (float)
                                    $lockedStatement
                                        ->net_payable
                                    - $recoveryAmount
                                ),
                                8
                            ),
                    ]);

                    $lockedStatement->refresh();
                }

                /*
                 * WalletService rejects zero-value credits.
                 *
                 * A fully recouped statement may legitimately
                 * have zero payable, so only create a wallet
                 * transaction when money remains payable.
                 */
                $walletTransaction = null;

                if (
                    (float)
                    $lockedStatement->net_payable
                    > 0
                ) {
                    $walletTransaction =
                        $this->wallet
                            ->creditPending(
                                $owner,
                                (float)
                                $lockedStatement
                                    ->net_payable,
                                'royalty_statement',
                                [
                                    'currency' =>
                                        $lockedStatement
                                            ->currency,

                                    'reference_type' =>
                                        RoyaltyStatement::class,

                                    'reference_id' =>
                                        $lockedStatement->id,

                                    'reference_code' =>
                                        $lockedStatement
                                            ->public_id,

                                    'description' =>
                                        "Royalty statement {$lockedStatement->statement_month}",

                                    'created_by' =>
                                        $admin->id,
                                ]
                            );
                }

                if (
                    $recovery
                    && $walletTransaction
                ) {
                    $recovery->update([
                        'wallet_transaction_id' =>
                            $walletTransaction->id,
                    ]);
                }

                /*
                 * Mark approved only after all financial
                 * deductions and wallet posting succeed.
                 */
                $lockedStatement->update([
                    'status' =>
                        'approved',

                    'approved_at' =>
                        now(),

                    'approved_by' =>
                        $admin->id,
                ]);
            }
        );

        return $statement->fresh();
    }

    public function makeAvailable(
        RoyaltyStatement $statement,
        User $admin
    ): RoyaltyStatement {
        DB::transaction(
            function () use (
                $statement,
                $admin
            ) {
                /*
                 * Lock and re-read the statement inside the
                 * transaction.
                 *
                 * Two concurrent make-available requests must
                 * never release the same pending royalty twice.
                 */
                $lockedStatement =
                    RoyaltyStatement::query()
                        ->whereKey($statement->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                abort_unless(
                    $lockedStatement->status ===
                        'approved',
                    422,
                    'Statement must be approved first.'
                );

                $owner = $this->ownerUser(
                    $lockedStatement
                );

                abort_unless(
                    $owner,
                    422,
                    'Statement owner user is missing.'
                );

                /*
                 * A fully recouped statement may legitimately
                 * have zero payable.
                 *
                 * WalletService rejects zero-value releases,
                 * so only move pending funds when money is
                 * actually payable.
                 */
                if (
                    (float) $lockedStatement
                        ->net_payable
                    > 0
                ) {
                    $this->wallet->releasePending(
                        $owner,
                        (float) $lockedStatement
                            ->net_payable,
                        [
                            'currency' =>
                                $lockedStatement->currency,

                            'reference_type' =>
                                RoyaltyStatement::class,

                            'reference_id' =>
                                $lockedStatement->id,

                            'reference_code' =>
                                $lockedStatement->public_id,

                            'description' =>
                                "Royalty available for {$lockedStatement->statement_month}",

                            'created_by' =>
                                $admin->id,
                        ]
                    );
                }

                $lockedStatement->update([
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
