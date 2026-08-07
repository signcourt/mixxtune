<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
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
        WalletService $walletService
    ): Response {
        $permissions->authorize(
            $request->user(),
            'wallet.view'
        );

        $wallet = $walletService->account(
            $request->user()
        );

        return Inertia::render(
            'V2/Wallet/Index',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

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
