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
        LabelFinancialContextService $financialContext,
        WithdrawalService $withdrawalService
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
                    $withdrawalService->minimumAmountFor(
                        $financialOwner
                    ),

                'withdrawalEnabled' =>
                    $withdrawalService->withdrawalEnabledFor(
                        $financialOwner
                    ),

                'withdrawals' =>
                    WithdrawalRequest::query()
                        ->with([
                            'invoice:id,withdrawal_id,invoice_number,invoice_type,tax_treatment,status,tds_amount,net_payable',
                        ])
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
        /* W59: money is never requested on someone's behalf through "Login as" */
        if ($request->hasSession() && $request->session()->has('impersonator_user_id')) {
            return back()->withErrors(['amount' => 'Withdrawals cannot be requested while using "Login as". The owner must do this from their own login.']);
        }

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:1',
            ],

            'payment_method' => [
                'required',
                'string',
                'in:bank', // MIXXTUNE_BANK_ONLY_PAYOUTS_V1: payouts only to a bank account
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
