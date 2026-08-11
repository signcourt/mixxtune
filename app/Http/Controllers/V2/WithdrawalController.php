<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Services\V2\PermissionService;
use App\Services\V2\WalletService;
use App\Services\V2\WithdrawalService;
use App\Services\V2\LabelAccess\LabelFinancialContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        WalletService $walletService,
        LabelFinancialContextService $financialContext
    ): Response {
        $actor = $request->user();

        $financialOwner =
            $financialContext->owner(
                $actor
            );

        $wallet = $walletService->account(
            $financialOwner
        );

        $profile = PayoutProfile::query()
            ->where(
                'user_id',
                $financialOwner->id
            )
            ->first();

        return Inertia::render(
            'V2/Withdrawals/Index',
            [
                'role' =>
                    $permissions->role(
                        $financialOwner
                    ),

                'wallet' =>
                    $wallet,

                'profile' =>
                    $profile,

                'minimumAmount' =>
                    WithdrawalService::MINIMUM_AMOUNT,

                'withdrawals' =>
                    WithdrawalRequest::query()
                        ->where(
                            'user_id',
                            $financialOwner->id
                        )
                        ->orderByDesc('id')
                        ->paginate(25),
            ]
        );
    }

    public function store(
        Request $request,
        WithdrawalService $withdrawals,
        LabelFinancialContextService $financialContext
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:1',
            ],

            'payment_method' => [
                'required',
                'string',
                'in:bank,upi',
            ],

            'request_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $actor = $request->user();

        $financialOwner =
            $financialContext->owner(
                $actor
            );

        $withdrawal = $withdrawals->create(
            $financialOwner,
            (float) $validated['amount'],
            $validated['payment_method'],
            $validated['request_note']
                ?? null,
            $actor
        );

        return back()->with(
            'success',
            'Withdrawal request '
            . $withdrawal->withdrawal_number
            . ' submitted.'
        );
    }
}
