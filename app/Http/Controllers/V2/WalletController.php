<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\WalletAccount;
use App\Services\V2\LabelAccess\LabelFinancialContextService;
use App\Services\V2\AdminFinancialAccessService;
use App\Services\V2\PermissionService;
use App\Services\V2\WalletService;
use App\Services\V2\WithdrawalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        WalletService $walletService,
        LabelFinancialContextService $financialContext,
        WithdrawalService $withdrawalService,
        AdminFinancialAccessService $adminFinancialAccess
    ): Response {
        /*
         * FF165_ADMIN_ALIAS_GUARD_WALLET
         * The shared controller serves both normal account
         * routes and /v2/admin aliases.
         *
         * Only the admin alias requires admin/super_admin.
         */
        if (
            $request->route()
            && str_starts_with(
                (string) $request->route()->getName(),
                'v2.admin.wallet.'
            )
        ) {
            $adminAliasRole =
                $permissions->role(
                    $request->user()
                );

            abort_unless(
                in_array(
                    $adminAliasRole,
                    [
                        'admin',
                        'super_admin',
                    ],
                    true
                ),
                403,
                'Admin access required.'
            );
        }

        $actor = $request->user();
        $role = $permissions->role($actor);

        /*
         * Authorization is always evaluated against the
         * logged-in actor.
         *
         * Financial ownership is resolved separately.
         */
        $permissions->authorize(
            $actor,
            'wallet.view'
        );

        /*
         * Admin wallet is an aggregate read-only view of
         * wallets belonging to assigned Labels / Artists.
         *
         * IMPORTANT:
         * Never call WalletService::account() for an Admin
         * here because account() may firstOrCreate() a wallet
         * for the Admin user itself.
         */
        if (
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            )
        ) {
            $walletQuery =
                WalletAccount::query();

            $adminFinancialAccess
                ->applyFinancialOwnerScope(
                    $walletQuery,
                    $actor
                );

            $walletIds =
                (clone $walletQuery)
                    ->pluck('id');

            $currency =
                (clone $walletQuery)
                    ->value('currency')
                ?? 'INR';

            $wallet = [
                'currency' =>
                    $currency,

                'available_balance' =>
                    (float) (
                        (clone $walletQuery)
                            ->sum(
                                'available_balance'
                            )
                    ),

                'pending_balance' =>
                    (float) (
                        (clone $walletQuery)
                            ->sum(
                                'pending_balance'
                            )
                    ),

                'lifetime_credits' =>
                    (float) (
                        (clone $walletQuery)
                            ->sum(
                                'lifetime_credits'
                            )
                    ),

                'lifetime_debits' =>
                    (float) (
                        (clone $walletQuery)
                            ->sum(
                                'lifetime_debits'
                            )
                    ),
            ];

            $wallet['lifetime_earnings'] =
                $wallet[
                    'lifetime_credits'
                ];

            /*
             * MIXXTUNE_WALLET_V2 (W44): "On hold" was always 0. Money in
             * withdrawal requests (asked / approved, not yet paid) is now
             * shown on its own; "pending" = royalties approved but not yet
             * released (pending balance minus the withdrawal holds).
             */
            $inWithdrawal = (float) \Illuminate\Support\Facades\DB::table('withdrawals')
                ->whereIn('wallet_id', $walletIds)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('amount');

            $wallet['hold_balance'] = round($inWithdrawal, 2);
            $wallet['in_withdrawal'] = round($inWithdrawal, 2);
            $wallet['royalty_pending'] = round(max(0, $wallet['pending_balance'] - $inWithdrawal), 2);
            $wallet['owners_with_money'] = (clone $walletQuery)->where('available_balance', '>', 0)->count();
            $wallet['wallet_count'] = $walletIds->count();

            /* history: one owner (wallet=ID, inside scope) or everybody */
            $onlyWallet = (int) $request->input('wallet', 0);
            if ($onlyWallet && ! $walletIds->contains($onlyWallet)) {
                $onlyWallet = 0;
            }

            $transactions = $this->history(
                \App\Models\Finance\WalletTransaction::query()
                    ->whereIn('wallet_id', $onlyWallet ? [$onlyWallet] : $walletIds),
                true
            );

            /*
             * MIXXTUNE_FINANCE_CLEANUP_V1: one row per owner wallet
             * (same scope as the totals above), searchable by name / e-mail.
             */
            $ownerSearch = mb_substr(trim((string) $request->input('owner_search', '')), 0, 100);

            $holds = \Illuminate\Support\Facades\DB::table('withdrawals')
                ->select('wallet_id', \Illuminate\Support\Facades\DB::raw('SUM(amount) as held'))
                ->whereIn('status', ['pending', 'approved'])
                ->groupBy('wallet_id');

            $ownersQuery = WalletAccount::query()
                ->whereIn('wallets.id', $walletIds)
                ->leftJoin('users', 'users.id', '=', 'wallets.user_id')
                ->leftJoinSub($holds, 'h', 'h.wallet_id', '=', 'wallets.id')
                ->select([
                    'wallets.id',
                    'wallets.user_id',
                    'wallets.currency',
                    'wallets.available_balance',
                    'wallets.pending_balance',
                    'wallets.lifetime_credits',
                    'wallets.lifetime_debits',
                    'wallets.updated_at',
                    'users.name as owner_name',
                    'users.email as owner_email',
                    'users.role as owner_role',
                    'users.client_id as owner_client_id',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(h.held, 0) as in_withdrawal'),
                ]);

            if ($ownerSearch !== '') {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $ownerSearch).'%';

                $ownersQuery->where(function ($builder) use ($term) {
                    $builder->where('users.name', 'like', $term)->orWhere('users.email', 'like', $term)->orWhere('users.client_id', 'like', $term);
                });
            }

            $sort = in_array($request->input('sort'), ['available', 'pending', 'earned', 'recent'], true) ? $request->input('sort') : 'available';
            $ownersQuery->orderByDesc(match ($sort) {
                'pending' => 'wallets.pending_balance',
                'earned' => 'wallets.lifetime_credits',
                'recent' => 'wallets.updated_at',
                default => 'wallets.available_balance',
            })->orderBy('wallets.id');

            $owners = $ownersQuery
                ->paginate(25, ['*'], 'owners_page')
                ->withQueryString()
                ->through(fn ($w) => [
                    'id' => $w->id,
                    'user_id' => $w->user_id,
                    'owner_name' => $w->owner_name,
                    'owner_email' => $w->owner_email,
                    'owner_role' => $w->owner_role,
                    'client_id' => $w->owner_client_id,
                    'available_balance' => round((float) $w->available_balance, 2),
                    'in_withdrawal' => round((float) $w->in_withdrawal, 2),
                    'royalty_pending' => round(max(0, (float) $w->pending_balance - (float) $w->in_withdrawal), 2),
                    'lifetime_credits' => round((float) $w->lifetime_credits, 2),
                    'lifetime_debits' => round((float) $w->lifetime_debits, 2),
                    'updated' => $w->updated_at ? \Illuminate\Support\Carbon::parse($w->updated_at)->timezone('Asia/Kolkata')->format('d M Y') : null,
                ]);

            return Inertia::render(
                'V2/Wallet/Index',
                [
                    'role' =>
                        $role,

                    /*
                     * Admin wallet is monitoring only.
                     * Withdrawal eligibility belongs to
                     * the financial owner account.
                     */
                    'minimumWithdrawal' =>
                        0,

                    'withdrawalEnabled' =>
                        true,

                    'wallet' =>
                        $wallet,

                    'transactions' =>
                        $transactions,

                    'owners' =>
                        $owners,

                    'ownerSearch' =>
                        $ownerSearch,

                    'historyWallet' => $onlyWallet ?: null,
                    'historyOwner' => $onlyWallet ? WalletAccount::query()->leftJoin('users', 'users.id', '=', 'wallets.user_id')->where('wallets.id', $onlyWallet)->value('users.name') : null,
                    'sort' => $sort,
                ]
            );
        }

        $financialOwner = $financialContext->owner(
            $actor
        );

        $wallet = $walletService->account(
            $financialOwner
        );


        /*
         * Use the same canonical minimum-withdrawal
         * resolver as the withdrawal backend.
         *
         * For Label Team users, $financialOwner is the
         * effective label owner.
         */
        $minimumWithdrawal =
            $withdrawalService->minimumAmountFor(
                $financialOwner
            );

        return Inertia::render(
            'V2/Wallet/Index',
            [
                'role' =>
                    $role,

                'minimumWithdrawal' =>
                    $minimumWithdrawal,

                'withdrawalEnabled' =>
                    $withdrawalService->withdrawalEnabledFor(
                        $financialOwner
                    ),

                'wallet' =>
                    $wallet,

                'transactions' =>
                    $this->history($wallet->transactions()->getQuery(), false),

                /* MIXXTUNE_WALLET_V2: money in withdrawal requests (was "on hold" 0) */
                'inWithdrawal' => round((float) \Illuminate\Support\Facades\DB::table('withdrawals')
                    ->where('wallet_id', $wallet->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->sum('amount'), 2),

                /* MIXXTUNE_RECOUPMENT_V2: advance still being repaid */
                'advances' =>
                    \App\Models\Finance\RecoupmentPlan::query()
                        ->where('user_id', $financialOwner->id)
                        ->where('status', 'active')
                        ->where('outstanding_amount', '>', 0)
                        ->orderBy('id')
                        ->get()
                        ->map(function ($p) {
                            $recovery = max(0, (float) $p->recovery_uplift_percentage);
                            if ($p->maximum_recovery_percentage !== null) {
                                $recovery = min($recovery, max(0, (float) $p->maximum_recovery_percentage));
                            }
                            $recovery = min($recovery, max(0, (float) $p->base_percentage));

                            return [
                                'plan_number' => $p->plan_number,
                                'title' => $p->title,
                                'total' => round((float) $p->total_recoverable_amount, 2),
                                'recovered' => round((float) $p->total_recovered_amount, 2),
                                'outstanding' => round((float) $p->outstanding_amount, 2),
                                'recovery' => round($recovery, 2),
                            ];
                        })
                        ->values(),
            ]
        );
    }
    /*
     * MIXXTUNE_WALLET_V2 (W44): history rows with readable date (IST), type
     * name, owner (staff view), paging ("tx_page"). Before: raw ISO dates,
     * raw type codes, no paging links, no owner on the staff page.
     */
    private function history($query, bool $staff)
    {
        $labels = [
            'royalty_credit' => 'Royalty (pending)',
            'royalty_import' => 'Royalty',
            'available_credit' => 'Royalty released',
            'withdrawal_hold' => 'Withdrawal requested',
            'withdrawal_release' => 'Withdrawal returned',
            'withdrawal_paid' => 'Withdrawal paid',
            'manual_adjustment' => 'Manual adjustment',
            'adjustment_reversal' => 'Adjustment reversed',
            'recoupment' => 'Advance recovery',
        ];

        $page = $query
            ->orderByDesc('id')
            ->paginate(30, ['*'], 'tx_page')
            ->withQueryString();

        $names = $staff
            ? \Illuminate\Support\Facades\DB::table('users')->whereIn('id', $page->getCollection()->pluck('user_id')->filter())->pluck('name', 'id')
            : collect();

        return $page->through(function ($t) use ($labels, $names, $staff) {
            $type = (string) ($t->transaction_type ?? '');
            $when = $t->posted_at ?? $t->created_at;

            return [
                'id' => $t->id,
                'date' => $when ? \Illuminate\Support\Carbon::parse($when)->timezone('Asia/Kolkata')->format('d M Y, h:i A') : '—',
                'type' => $labels[$type] ?? ucfirst(str_replace('_', ' ', $type ?: 'entry')),
                'direction' => $t->direction,
                'amount' => round((float) $t->amount, 2),
                'balance_after' => $t->balance_after !== null ? round((float) $t->balance_after, 2) : null,
                'description' => $t->description,
                'month' => $t->financial_month ? \Illuminate\Support\Carbon::parse($t->financial_month)->format('M Y') : null,
                'owner' => $staff ? ($names[$t->user_id] ?? null) : null,
                'wallet_id' => $t->wallet_id,
            ];
        });
    }

    /* MIXXTUNE_WALLET_V2: owner balances as CSV (same scope as the page) */
    public function export(
        Request $request,
        PermissionService $permissions,
        AdminFinancialAccessService $adminFinancialAccess
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        abort_unless(in_array($permissions->role($request->user()), ['admin', 'super_admin'], true), 403);

        $walletQuery = WalletAccount::query();
        $adminFinancialAccess->applyFinancialOwnerScope($walletQuery, $request->user());
        $ids = $walletQuery->pluck('id');

        $holds = \Illuminate\Support\Facades\DB::table('withdrawals')->whereIn('wallet_id', $ids)->whereIn('status', ['pending', 'approved'])
            ->groupBy('wallet_id')->select('wallet_id', \Illuminate\Support\Facades\DB::raw('SUM(amount) as held'))->pluck('held', 'wallet_id');

        $rows = WalletAccount::query()->whereIn('wallets.id', $ids)->leftJoin('users', 'users.id', '=', 'wallets.user_id')
            ->orderByDesc('wallets.available_balance')
            ->get(['wallets.*', 'users.name as owner_name', 'users.email as owner_email', 'users.role as owner_role', 'users.client_id as owner_client_id']);

        \App\Services\V2\FinanceAudit::log('finance.wallets_exported', 'Owner wallet balances exported (CSV).', ['wallets' => $rows->count()]);

        return response()->streamDownload(function () use ($rows, $holds) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Owner', 'E-mail', 'Role', 'Client ID', 'Currency', 'Available', 'Royalty pending', 'In withdrawal', 'Lifetime credits', 'Lifetime debits', 'Last change']);
            foreach ($rows as $w) {
                $held = (float) ($holds[$w->id] ?? 0);
                fputcsv($out, [
                    $w->owner_name, $w->owner_email, $w->owner_role, $w->owner_client_id, $w->currency,
                    number_format((float) $w->available_balance, 2, '.', ''),
                    number_format(max(0, (float) $w->pending_balance - $held), 2, '.', ''),
                    number_format($held, 2, '.', ''),
                    number_format((float) $w->lifetime_credits, 2, '.', ''),
                    number_format((float) $w->lifetime_debits, 2, '.', ''),
                    optional($w->updated_at)->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, 'owner-wallets-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
