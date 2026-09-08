<?php

namespace App\Http\Controllers\V3\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V3\Finance\ReverseFinancialAdjustmentRequest;
use App\Http\Requests\V3\Finance\StoreFinancialAdjustmentRequest;
use App\Models\Finance\FinancialAdjustment;
use App\Models\User;
use App\Services\V3\FinancialAdjustmentService;
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

        $this->service->post(
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

        return back()->with(
            'success',
            'Financial adjustment posted.'
        );
    }

    public function reverse(
        ReverseFinancialAdjustmentRequest $request,
        FinancialAdjustment $adjustment
    ): RedirectResponse {
        $this->service->reverse(
            $adjustment,
            $request->validated()['reason'],
            Auth::user()
        );

        return back()->with(
            'success',
            'Financial adjustment reversed.'
        );
    }
}
