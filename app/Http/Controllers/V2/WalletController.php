<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\LabelAccess\LabelFinancialContextService;
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
        WithdrawalService $withdrawalService
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
