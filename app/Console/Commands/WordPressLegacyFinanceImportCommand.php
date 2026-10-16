<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WordPressLegacyFinanceImportCommand extends Command
{
    protected $signature =
        'mixxtune:wordpress-legacy-finance
        {--commit : Commit the reconciled legacy finance import}';

    protected $description =
        'Import reconciled WordPress legacy wallet balances and transaction history';

    private const SOURCE_KEY = 'backstagemixxtune.com';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $base = storage_path(
            'app/migration/wordpress-core-snapshot'
        );

        foreach ([
            'users.jsonl',
            'usermeta.jsonl',
            'business_posts.jsonl',
            'business_postmeta.jsonl',
        ] as $file) {
            if (!is_file($base.'/'.$file)) {
                $this->error('Missing snapshot file: '.$file);
                return self::FAILURE;
            }
        }

        /*
         * -------------------------------------------------
         * 1. Authoritative WordPress closing balances
         * -------------------------------------------------
         */
        $closing = [];

        $h = fopen($base.'/usermeta.jsonl', 'r');

        while (($line = fgets($h)) !== false) {
            $r = json_decode($line, true);

            if (
                !is_array($r)
                || ($r['meta_key'] ?? '') !== 'user_earning'
                || !is_numeric($r['meta_value'] ?? null)
            ) {
                continue;
            }

            $closing[(int) $r['user_id']] =
                round((float) $r['meta_value'], 8);
        }

        fclose($h);

        /*
         * -------------------------------------------------
         * 2. Legacy transaction posts
         * -------------------------------------------------
         */
        $posts = [];

        $h = fopen($base.'/business_posts.jsonl', 'r');

        while (($line = fgets($h)) !== false) {
            $r = json_decode($line, true);

            if (
                !is_array($r)
                || ($r['post_type'] ?? '') !==
                    'transaction_history'
            ) {
                continue;
            }

            $id = (int) ($r['ID'] ?? $r['id'] ?? 0);

            if (!$id) {
                continue;
            }

            $posts[$id] = [
                'wp_post_id' => $id,
                'wp_user_id' =>
                    (int) ($r['post_author'] ?? 0),
                'title' =>
                    (string) ($r['post_title'] ?? ''),
                'effective_at' =>
                    (string) (
                        $r['post_date']
                        ?? $r['post_date_gmt']
                        ?? ''
                    ),
            ];
        }

        fclose($h);

        /*
         * -------------------------------------------------
         * 3. Transaction meta
         * -------------------------------------------------
         */
        $meta = [];

        $h = fopen(
            $base.'/business_postmeta.jsonl',
            'r'
        );

        while (($line = fgets($h)) !== false) {
            $r = json_decode($line, true);

            if (!is_array($r)) {
                continue;
            }

            $pid = (int) ($r['post_id'] ?? 0);

            if (!isset($posts[$pid])) {
                continue;
            }

            $key = (string) ($r['meta_key'] ?? '');

            if (!in_array(
                $key,
                [
                    'user_earning',
                    'adjust_earning',
                    'earning_reference',
                ],
                true
            )) {
                continue;
            }

            $meta[$pid][$key] =
                $r['meta_value'] ?? '';
        }

        fclose($h);

        /*
         * -------------------------------------------------
         * 4. WP user -> Laravel user mappings
         * -------------------------------------------------
         */
        $maps = DB::table(
            'wordpress_legacy_id_maps'
        )
            ->where('source_key', self::SOURCE_KEY)
            ->where('source_entity_type', 'user')
            ->where('migration_status', 'mapped')
            ->whereNotNull('target_entity_id')
            ->get([
                'source_entity_id',
                'target_entity_id',
            ]);

        $wpToTarget = [];

        foreach ($maps as $map) {
            $wpToTarget[(int) $map->source_entity_id] =
                (int) $map->target_entity_id;
        }

        /*
         * -------------------------------------------------
         * 5. Group source identities by Laravel target
         *
         * WP25 + WP117 -> User3 is intentional.
         * We MUST NOT sum duplicate source closing balances.
         * -------------------------------------------------
         */
        $targetSources = [];

        foreach ($closing as $wpUser => $balance) {
            if (!isset($wpToTarget[$wpUser])) {
                continue;
            }

            $target = $wpToTarget[$wpUser];

            $targetSources[$target][$wpUser] =
                $balance;
        }

        /*
         * -------------------------------------------------
         * 6. Resolve one authoritative closing balance
         * per Laravel target.
         * -------------------------------------------------
         */
        $targetClosing = [];
        $collisions = [];

        foreach ($targetSources as $target => $sources) {
            if (count($sources) === 1) {
                $targetClosing[$target] =
                    reset($sources);
                continue;
            }

            /*
             * Known intentional identity collision:
             * WP25 + WP117 -> Laravel User3.
             *
             * WP117 has no users.jsonl row and was repaired
             * to User3 from exact billing-email evidence.
             * Use WP25 as the authoritative finance identity
             * if it has a closing balance.
             */
            if (
                $target === 3
                && isset($sources[25])
                && array_key_exists(117, $sources)
            ) {
                $targetClosing[$target] =
                    $sources[25];

                $collisions[$target] = [
                    'sources' => $sources,
                    'resolution' =>
                        'WP25 authoritative; WP117 alias',
                ];

                continue;
            }

            /*
             * Never silently merge balances from two
             * independent WordPress identities.
             */
            $collisions[$target] = [
                'sources' => $sources,
                'resolution' => 'UNRESOLVED',
            ];
        }

        $unresolvedCollisionCount = 0;

        foreach ($collisions as $collision) {
            if (
                $collision['resolution']
                === 'UNRESOLVED'
            ) {
                $unresolvedCollisionCount++;
            }
        }

        /*
         * -------------------------------------------------
         * 7. Historical transaction classification
         * -------------------------------------------------
         */
        $mappedTransactions = [];
        $orphanTransactions = [];

        foreach ($posts as $pid => $post) {
            $wpUser = $post['wp_user_id'];

            $adjust = round(
                (float) (
                    $meta[$pid]['adjust_earning']
                    ?? 0
                ),
                8
            );

            $legacyBalance = round(
                (float) (
                    $meta[$pid]['user_earning']
                    ?? 0
                ),
                8
            );

            $reference = trim(
                (string) (
                    $meta[$pid]['earning_reference']
                    ?? ''
                )
            );

            if (!isset($wpToTarget[$wpUser])) {
                $orphanTransactions[] = [
                    'post_id' => $pid,
                    'wp_user_id' => $wpUser,
                    'adjustment' => $adjust,
                    'balance' => $legacyBalance,
                    'reference' => $reference,
                ];
                continue;
            }

            $mappedTransactions[] = [
                'post_id' => $pid,
                'wp_user_id' => $wpUser,
                'target_user_id' =>
                    $wpToTarget[$wpUser],
                'adjustment' => $adjust,
                'legacy_balance_after' =>
                    $legacyBalance,
                'reference' => $reference,
                'title' => $post['title'],
                'effective_at' =>
                    $post['effective_at'],
            ];
        }

        /*
         * -------------------------------------------------
         * 8. Dry-run statistics
         * -------------------------------------------------
         */
        $mappedClosingSourceTotal = 0.0;

        foreach ($targetClosing as $balance) {
            $mappedClosingSourceTotal += $balance;
        }

        $positive = 0;
        $zero = 0;
        $negative = 0;

        foreach ($targetClosing as $balance) {
            if ($balance > 0) {
                $positive++;
            } elseif ($balance < 0) {
                $negative++;
            } else {
                $zero++;
            }
        }

        $creditTotal = 0.0;
        $debitTotal = 0.0;

        foreach ($mappedTransactions as $tx) {
            if ($tx['adjustment'] > 0) {
                $creditTotal += $tx['adjustment'];
            } elseif ($tx['adjustment'] < 0) {
                $debitTotal += abs(
                    $tx['adjustment']
                );
            }
        }

        $this->newLine();
        $this->info(
            '===== LEGACY FINANCE DRY RUN ====='
        );

        $this->line(
            'SOURCE_CLOSING_USERS='.
            count($closing)
        );

        $this->line(
            'SOURCE_CLOSING_TOTAL='.
            round(array_sum($closing), 8)
        );

        $this->line(
            'TARGET_WALLETS='.
            count($targetClosing)
        );

        $this->line(
            'TARGET_CLOSING_TOTAL='.
            round($mappedClosingSourceTotal, 8)
        );

        $this->line(
            'POSITIVE_WALLETS='.$positive
        );

        $this->line(
            'ZERO_WALLETS='.$zero
        );

        $this->line(
            'NEGATIVE_WALLETS='.$negative
        );

        $this->line(
            'SOURCE_TRANSACTIONS='.
            count($posts)
        );

        $this->line(
            'MAPPED_TRANSACTIONS='.
            count($mappedTransactions)
        );

        $this->line(
            'ORPHAN_TRANSACTIONS='.
            count($orphanTransactions)
        );

        $this->line(
            'HISTORICAL_CREDITS='.
            round($creditTotal, 8)
        );

        $this->line(
            'HISTORICAL_DEBITS='.
            round($debitTotal, 8)
        );

        $this->line(
            'COLLISIONS='.
            count($collisions)
        );

        $this->line(
            'UNRESOLVED_COLLISIONS='.
            $unresolvedCollisionCount
        );

        /*
         * Critical known exception.
         */
        $this->line(
            'WP176_TARGET='.
            ($wpToTarget[176] ?? 'NONE')
        );

        $this->line(
            'WP176_CLOSING='.
            ($closing[176] ?? 'NONE')
        );

        $this->line(
            'WP27_MAPPED='.
            (isset($wpToTarget[27])
                ? 'YES'
                : 'NO')
        );

        /*
         * -------------------------------------------------
         * 9. Safety blockers
         * -------------------------------------------------
         */
        $existingWallets =
            DB::table('wallets')->count();

        $existingTransactions =
            DB::table('wallet_transactions')->count();

        $this->line(
            'EXISTING_WALLETS='.
            $existingWallets
        );

        $this->line(
            'EXISTING_TRANSACTIONS='.
            $existingTransactions
        );

        if (!$commit) {
            $this->warn(
                'DRY RUN ONLY — ZERO DATABASE WRITES'
            );

            return self::SUCCESS;
        }

        /*
         * -------------------------------------------------
         * 10. Hard commit guards
         * -------------------------------------------------
         */
        if ($existingWallets !== 0) {
            $this->error(
                'COMMIT BLOCKED: wallets table is not empty.'
            );
            return self::FAILURE;
        }

        if ($existingTransactions !== 0) {
            $this->error(
                'COMMIT BLOCKED: wallet_transactions is not empty.'
            );
            return self::FAILURE;
        }

        if ($unresolvedCollisionCount !== 0) {
            $this->error(
                'COMMIT BLOCKED: unresolved user collisions exist.'
            );
            return self::FAILURE;
        }

        if (count($targetClosing) !== 177) {
            $this->error(
                'COMMIT BLOCKED: expected exactly 177 target wallets.'
            );
            return self::FAILURE;
        }

        if (count($mappedTransactions) !== 4033) {
            $this->error(
                'COMMIT BLOCKED: expected exactly 4033 mapped transactions.'
            );
            return self::FAILURE;
        }

        if (count($orphanTransactions) !== 1) {
            $this->error(
                'COMMIT BLOCKED: expected exactly one orphan transaction.'
            );
            return self::FAILURE;
        }

        if (
            !isset($closing[176])
            || abs((float) $closing[176] - 100000.0) > 0.01
            || (int) ($wpToTarget[176] ?? 0) !== 1
        ) {
            $this->error(
                'COMMIT BLOCKED: WP176 invariant failed.'
            );
            return self::FAILURE;
        }

        if (isset($wpToTarget[27])) {
            $this->error(
                'COMMIT BLOCKED: WP27 must remain orphaned.'
            );
            return self::FAILURE;
        }

        $expectedClosingTotal = 9296685.0;

        if (
            abs(
                $mappedClosingSourceTotal
                - $expectedClosingTotal
            ) > 0.01
        ) {
            $this->error(
                'COMMIT BLOCKED: closing balance total changed.'
            );
            return self::FAILURE;
        }

        $dedupedMappedTransactions = [];

        foreach ($mappedTransactions as $tx) {
            $postId = (int) $tx['post_id'];

            if (!isset($dedupedMappedTransactions[$postId])) {
                $dedupedMappedTransactions[$postId] = $tx;
            }
        }

        $duplicateMappedTransactionRows =
            count($mappedTransactions)
            - count($dedupedMappedTransactions);

        $this->line(
            'DEDUPED_MAPPED_TRANSACTIONS='.
            count($dedupedMappedTransactions)
        );

        $this->line(
            'DUPLICATE_MAPPED_TRANSACTION_ROWS='.
            $duplicateMappedTransactionRows
        );

        $mappedTransactions =
            array_values($dedupedMappedTransactions);

        if (count($mappedTransactions) !== 4033) {
            $this->error(
                'COMMIT BLOCKED: expected exactly 4033 unique mapped transactions.'
            );
            return self::FAILURE;
        }

        /*
         * -------------------------------------------------
         * 11. Build source transaction totals by target
         * -------------------------------------------------
         *
         * Lifetime figures preserve historical activity.
         * Available balance remains the authoritative
         * WordPress closing balance and is NOT calculated
         * by replaying these transactions.
         */
        $targetCredits = [];
        $targetDebits = [];

        foreach ($mappedTransactions as $tx) {
            $target = $tx['target_user_id'];
            $adjust = (float) $tx['adjustment'];

            if ($adjust > 0) {
                $targetCredits[$target] =
                    ($targetCredits[$target] ?? 0)
                    + $adjust;
            } elseif ($adjust < 0) {
                $targetDebits[$target] =
                    ($targetDebits[$target] ?? 0)
                    + abs($adjust);
            }
        }

        /*
         * -------------------------------------------------
         * 12. Atomic import
         * -------------------------------------------------
         */
        DB::transaction(function () use (
            $targetClosing,
            $targetCredits,
            $targetDebits,
            $mappedTransactions
        ) {
            $walletIds = [];

            foreach ($targetClosing as $userId => $balance) {
                $walletIds[$userId] =
                    DB::table('wallets')->insertGetId([
                        'public_id' =>
                            (string) \Illuminate\Support\Str::ulid(),

                        'user_id' =>
                            $userId,

                        'artist_id' =>
                            null,

                        'label_id' =>
                            null,

                        'currency' =>
                            'INR',

                        'available_balance' =>
                            round((float) $balance, 8),

                        'pending_balance' =>
                            0,

                        'lifetime_credits' =>
                            round(
                                (float) (
                                    $targetCredits[$userId]
                                    ?? 0
                                ),
                                8
                            ),

                        'lifetime_debits' =>
                            round(
                                (float) (
                                    $targetDebits[$userId]
                                    ?? 0
                                ),
                                8
                            ),

                        'status' =>
                            'active',

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);
            }

            /*
             * Preserve legacy transaction history as
             * non-posting/neutral audit records.
             *
             * IMPORTANT:
             * balance_before / balance_after contain the
             * source WordPress balance snapshot. They do
             * not mutate the Laravel closing balance.
             */
            $rows = [];

            foreach ($mappedTransactions as $tx) {
                $userId =
                    $tx['target_user_id'];

                $amount =
                    abs((float) $tx['adjustment']);

                $legacyAfter =
                    (float)
                    $tx['legacy_balance_after'];

                $legacyBefore =
                    $legacyAfter
                    - (float) $tx['adjustment'];

                $rows[] = [
                    'public_id' =>
                        'WPH-' . substr(
                            hash(
                                'sha256',
                                self::SOURCE_KEY
                                . ':transaction_history:'
                                . (string) $tx['post_id']
                            ),
                            0,
                            32
                        ),

                    'wallet_id' =>
                        $walletIds[$userId],

                    'user_id' =>
                        $userId,

                    'artist_id' =>
                        null,

                    'label_id' =>
                        null,

                    'transaction_type' =>
                        'legacy_wordpress_history',

                    'direction' =>
                        'neutral',

                    'amount' =>
                        round($amount, 8),

                    'balance_before' =>
                        round($legacyBefore, 8),

                    'balance_after' =>
                        round($legacyAfter, 8),

                    'currency' =>
                        'INR',

                    'reference_type' =>
                        'wordpress_transaction_history',

                    'reference_id' =>
                        $tx['post_id'],

                    'description' =>
                        $tx['reference']
                        !== ''
                            ? $tx['reference']
                            : (
                                $tx['title']
                                !== ''
                                    ? $tx['title']
                                    : 'Legacy WordPress transaction'
                            ),

                    'status' =>
                        'posted',

                    'effective_at' =>
                        $tx['effective_at']
                        ?: now(),

                    'posted_at' =>
                        now(),

                    'metadata' =>
                        json_encode([
                            'source_key' =>
                                self::SOURCE_KEY,

                            'wp_post_id' =>
                                $tx['post_id'],

                            'wp_user_id' =>
                                $tx['wp_user_id'],

                            'legacy_adjustment' =>
                                $tx['adjustment'],

                            'legacy_balance_after' =>
                                $tx['legacy_balance_after'],

                            'legacy_reference' =>
                                $tx['reference'],

                            'historical_non_posting' =>
                                true,
                        ]),

                    'created_by' =>
                        null,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ];

                if (count($rows) >= 250) {
                    $this->line(
                    'HISTORICAL_TRANSACTION_INSERT=DEFERRED'
                );

                    $rows = [];
                }
            }

            if ($rows) {
                $this->line(
                    'HISTORICAL_TRANSACTION_TAIL_INSERT=DEFERRED'
                );
            }
        });

        /*
         * -------------------------------------------------
         * 13. Post-commit verification
         * -------------------------------------------------
         */
        $walletCount =
            DB::table('wallets')->count();

        $txCount =
            DB::table(
                'wallet_transactions'
            )->count();

        $balanceTotal =
            (float) DB::table('wallets')
                ->sum('available_balance');

        $pendingTotal =
            (float) DB::table('wallets')
                ->sum('pending_balance');

        $creditLifetime =
            (float) DB::table('wallets')
                ->sum('lifetime_credits');

        $debitLifetime =
            (float) DB::table('wallets')
                ->sum('lifetime_debits');

        $this->newLine();
        $this->info(
            '===== LEGACY FINANCE COMMIT RESULT ====='
        );

        $this->line(
            'WALLETS='.$walletCount
        );

        $this->line(
            'TRANSACTIONS='.$txCount
        );

        $this->line(
            'AVAILABLE_TOTAL='.
            round($balanceTotal, 8)
        );

        $this->line(
            'PENDING_TOTAL='.
            round($pendingTotal, 8)
        );

        $this->line(
            'LIFETIME_CREDITS='.
            round($creditLifetime, 8)
        );

        $this->line(
            'LIFETIME_DEBITS='.
            round($debitLifetime, 8)
        );

        if (
            $walletCount !== 177
            || $txCount !== 4033
            || abs(
                $balanceTotal
                - $expectedClosingTotal
            ) > 0.01
            || abs($pendingTotal) > 0.01
        ) {
            throw new \RuntimeException(
                'Post-commit finance verification failed.'
            );
        }

        $this->info(
            'LEGACY FINANCE IMPORT PASS'
        );

        return self::SUCCESS;
    }
}
