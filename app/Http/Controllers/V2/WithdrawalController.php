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
    ): Response|RedirectResponse {
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

        /*
         * MIXXTUNE_KYC_BEFORE_WITHDRAWAL_V1: no one reaches the withdrawal form
         * until their KYC is verified. This is the server-side gate that catches
         * the Wallet button, the Artist wallet button and a typed /withdrawals
         * URL alike. The actual request creation in WithdrawalService::create()
         * already blocks unverified KYC too, so money can never move; this just
         * sends the owner straight to their KYC page instead of a dead form.
         */
        if (! $profile || $profile->kyc_status !== 'verified') {
            $role = $permissions->role($financialOwner);
            $kycUrl = $role === 'artist' ? '/artist/kyc' : '/kyc-profile';

            return redirect($kycUrl)->with(
                'info',
                'Please complete your KYC verification before requesting a withdrawal.'
            );
        }

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

        /*
         * MIXXTUNE_PAYOUT_TIMING_V1 (W185a): give the owner a clear confirmation —
         * the reference number plus a processing/timing message built on the 20th
         * rule. The status itself is unchanged ('pending'); this is only the
         * owner-facing message. 'payout' is structured so the page can render it
         * nicely; 'success' stays as a plain string for older views.
         */
        $timing = $withdrawals->payoutTimingMessage(
            $withdrawal->requested_at
        );

        return back()
            ->with(
                'success',
                'Payment request '
                . $withdrawal->withdrawal_number
                . ' placed. '
                . $timing['message']
            )
            ->with('payout', [
                'reference' =>
                    $withdrawal->withdrawal_number,
                'status' =>
                    $withdrawal->status,
                'payable_after' =>
                    $timing['payable_after'],
                'message' =>
                    $timing['message'],
            ]);
    }
}
