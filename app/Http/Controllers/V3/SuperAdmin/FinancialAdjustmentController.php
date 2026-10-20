<?php

namespace App\Http\Controllers\V3\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V3\Finance\ReverseFinancialAdjustmentRequest;
use App\Http\Requests\V3\Finance\StoreFinancialAdjustmentRequest;
use App\Models\Finance\FinancialAdjustment;
use App\Models\User;
use App\Services\V3\FinancialAdjustmentService;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\FinanceAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinancialAdjustmentController extends Controller
{
    public function __construct(
        private FinancialAdjustmentService $service
    ) {
    }

    public function index(): Response
    {
        $adjustments =
            FinancialAdjustment::query()
                ->with([
                    'user:id,name,email,role',
                    'walletTransaction:id,transaction_type,direction,amount,currency,balance_before,balance_after',
                    'creator:id,name,email',
                    'reversedBy:id,name,email',
                    'reversalOf:id,adjustment_number,direction,amount,status',
                ])
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();

        $accounts = DB::table('users')
            ->whereIn(
                'role',
                [
                    'label',
                    'artist',
                ]
            )
            ->select([
                'id',
                'name',
                'email',
                'role',
            ])
            ->orderBy('name')
            ->limit(500)
            ->get();

        $summary = [
            'credits' =>
                (float) FinancialAdjustment::query()
                    ->where(
                        'status',
                        'posted'
                    )
                    ->where(
                        'direction',
                        'credit'
                    )
                    ->sum('amount'),

            'debits' =>
                (float) FinancialAdjustment::query()
                    ->where(
                        'status',
                        'posted'
                    )
                    ->where(
                        'direction',
                        'debit'
                    )
                    ->sum('amount'),

            'posted' =>
                FinancialAdjustment::query()
                    ->where(
                        'status',
                        'posted'
                    )
                    ->count(),

            'reversed' =>
                FinancialAdjustment::query()
                    ->where(
                        'status',
                        'reversed'
                    )
                    ->count(),
        ];

        return Inertia::render(
            'V3/SuperAdmin/Adjustments/Index',
            [
                'adjustments' =>
                    $adjustments,

                'accounts' =>
                    $accounts,

                'summary' =>
                    $summary,
            ]
        );
    }

    public function store(
        StoreFinancialAdjustmentRequest $request
    ): RedirectResponse {
        $data = $request->validated();

        $user = User::query()
            ->findOrFail(
                $data['user_id']
            );

        /*
         * MIXXTUNE_FINANCE_SAFETY_V1: a wallet credit / debit changes an
         * owner's balance directly, so it needs the e-mailed code (one code
         * covers exactly this owner, direction and amount).
         */
        $amountText = number_format((float) $data['amount'], 2, '.', '');

        app(ActionOtpGuard::class)->require(
            Auth::user(),
            'adjustment.post:'.$user->id.':'.$data['direction'].':'.$amountText,
            ($data['direction'] === 'debit' ? 'debit' : 'credit')
                .' '.$amountText.' '.strtoupper($data['currency'] ?? 'INR')
                .' '.($data['direction'] === 'debit' ? 'from' : 'to')
                .' the wallet of '.($user->name ?: $user->email)
        );

        $adjustment = $this->service->post(
            $user,
            $data['direction'],
            (float) $data['amount'],
            $data['reason'],
            [
                'reference_number' =>
                    $data[
                        'reference_number'
                    ]
                    ?? null,

                'idempotency_key' =>
                    $data[
                        'idempotency_key'
                    ],

                'internal_note' =>
                    $data[
                        'internal_note'
                    ]
                    ?? null,

                'effective_date' =>
                    $data[
                        'effective_date'
                    ]
                    ?? null,

                'type' =>
                    $data['type']
                    ?? 'manual',

                'currency' =>
                    strtoupper(
                        $data['currency']
                        ?? 'INR'
                    ),
            ],
            Auth::user()
        );

        FinanceAudit::log(
            'finance.adjustment_posted',
            'Wallet '.$data['direction'].' of '.$amountText.' for user #'.$user->id.' ('.($user->email ?? '-').'): '.$data['reason'],
            FinanceAudit::pick($adjustment instanceof FinancialAdjustment ? $adjustment : null, [
                'id', 'adjustment_number', 'user_id', 'direction', 'amount', 'type', 'reason', 'reference_number', 'status',
            ]) ?: ['user_id' => $user->id, 'direction' => $data['direction'], 'amount' => $amountText],
            $adjustment instanceof FinancialAdjustment ? $adjustment : $user
        );

        return back()->with(
            'success',
            'Financial adjustment posted.'
        );
    }

    public function reverse(
        ReverseFinancialAdjustmentRequest $request,
        FinancialAdjustment $adjustment
    ): RedirectResponse {
        app(ActionOtpGuard::class)->require(
            Auth::user(),
            'adjustment.reverse:'.$adjustment->id,
            'reverse adjustment '.($adjustment->adjustment_number ?: '#'.$adjustment->id)
        );

        $before = FinanceAudit::pick($adjustment, ['id', 'adjustment_number', 'user_id', 'direction', 'amount', 'status']);

        $this->service->reverse(
            $adjustment,
            $request->validated()['reason'],
            Auth::user()
        );

        FinanceAudit::log(
            'finance.adjustment_reversed',
            'Adjustment '.($adjustment->adjustment_number ?: '#'.$adjustment->id).' reversed: '.$request->validated()['reason'],
            FinanceAudit::pick($adjustment->fresh(), ['id', 'status', 'reversed_by', 'reversed_at']),
            $adjustment,
            $before
        );

        return back()->with(
            'success',
            'Financial adjustment reversed.'
        );
    }
}
