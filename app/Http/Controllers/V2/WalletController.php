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

            $wallet['hold_balance'] =
                0.0;

            $transactions =
                \App\Models\Finance\WalletTransaction::query()
                    ->whereIn(
                        'wallet_id',
                        $walletIds
                    )
                    ->orderByDesc('id')
                    ->paginate(30);

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

                    'wallet' =>
                        $wallet,

                    'transactions' =>
                        $transactions,
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

                'wallet' =>
                    $wallet,

                'transactions' =>
                    $wallet
                        ->transactions()
                        ->orderByDesc('id')
                        ->paginate(30),
            ]
        );
    }
}
