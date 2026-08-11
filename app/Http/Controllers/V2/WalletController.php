<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\LabelAccess\LabelFinancialContextService;
use App\Services\V2\PermissionService;
use App\Services\V2\WalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        WalletService $walletService,
        LabelFinancialContextService $financialContext
    ): Response {
        $actor = $request->user();

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

        return Inertia::render(
            'V2/Wallet/Index',
            [
                'role' =>
                    $permissions->role($actor),

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
