<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\PermissionService;
use App\Services\V2\RoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        $filters = [
            'search' => trim(
                (string) $request->input(
                    'search',
                    ''
                )
            ),

            'month' => trim(
                (string) $request->input(
                    'month',
                    ''
                )
            ),

            'status' => trim(
                (string) $request->input(
                    'status',
                    ''
                )
            ),
        ];

        $query = DB::table(
            'royalty_statements as statements'
        )
            ->leftJoin(
                'artists',
                'artists.id',
                '=',
                'statements.artist_id'
            )
            ->leftJoin(
                'labels',
                'labels.id',
                '=',
                'statements.label_id'
            );

        if ($role === 'admin') {
            $query->where(function ($builder) use (
                $request
            ) {
                $builder
                    ->where(
                        'artists.assigned_admin_id',
                        $request->user()->id
                    )
                    ->orWhere(
                        'labels.assigned_admin_id',
                        $request->user()->id
                    );
            });
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use (
                $search
            ) {
                $builder
                    ->where(
                        'artists.stage_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'artists.legal_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'labels.name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'statements.public_id',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($filters['month'] !== '') {
            $query->where(
                'statements.statement_month',
                $filters['month']
            );
        }

        if ($filters['status'] !== '') {
            $query->where(
                'statements.status',
                $filters['status']
            );
        }

        $summaryQuery = clone $query;

        $summary = [
            'gross' => (float) (
                (clone $summaryQuery)
                    ->sum(
                        'statements.gross_earnings'
                    )
            ),

            'pending' => (float) (
                (clone $summaryQuery)
                    ->where(
                        'statements.status',
                        'pending'
                    )
                    ->sum(
                        'statements.net_payable'
                    )
            ),

            'approved' => (float) (
                (clone $summaryQuery)
                    ->where(
                        'statements.status',
                        'approved'
                    )
                    ->sum(
                        'statements.net_payable'
                    )
            ),

            'available' => (float) (
                (clone $summaryQuery)
                    ->where(
                        'statements.status',
                        'available'
                    )
                    ->sum(
                        'statements.net_payable'
                    )
            ),

            'paid' => (float) (
                (clone $summaryQuery)
                    ->where(
                        'statements.status',
                        'paid'
                    )
                    ->sum(
                        'statements.net_payable'
                    )
            ),
        ];

        $statements = $query
            ->select([
                'statements.*',

                DB::raw(
                    "COALESCE(
                        artists.stage_name,
                        artists.legal_name,
                        'Unknown Artist'
                    ) as artist_name"
                ),

                DB::raw(
                    "COALESCE(
                        labels.name,
                        'Independent Artist'
                    ) as label_name"
                ),
            ])
            ->orderByDesc(
                'statements.statement_month'
            )
            ->orderByDesc(
                'statements.id'
            )
            ->paginate(30)
            ->withQueryString();

        $months = DB::table(
            'royalty_statements'
        )
            ->whereNotNull(
                'statement_month'
            )
            ->distinct()
            ->orderByDesc(
                'statement_month'
            )
            ->pluck(
                'statement_month'
            );

        return Inertia::render(
            'V2/Admin/Finance/Index',
            [
                'role' => $role,
                'summary' => $summary,
                'statements' => $statements,
                'filters' => $filters,
                'months' => $months,
            ]
        );
    }

    public function generate(
        Request $request,
        PermissionService $permissions,
        RoyaltyService $royalties
    ): RedirectResponse {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            $role === 'super_admin',
            403,
            'Super Admin access required.'
        );

        $validated = $request->validate([
            'month' => [
                'required',
                'date_format:Y-m',
            ],

            'commission_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        $result = $royalties
            ->generateMonthlyStatements(
                $validated['month'],
                (float) (
                    $validated[
                        'commission_percent'
                    ] ?? 0
                )
            );

        return back()->with(
            'success',
            "Statements generated. Created: {$result['created']}, updated: {$result['updated']}, failed: {$result['failed_count']}."
        );
    }

    public function approve(
        Request $request,
        RoyaltyStatement $statement,
        PermissionService $permissions,
        RoyaltyService $royalties
    ): RedirectResponse {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403
        );

        $royalties->approve(
            $statement,
            $request->user()
        );

        return back()->with(
            'success',
            'Royalty statement approved and added to pending wallet balance.'
        );
    }

    public function makeAvailable(
        Request $request,
        RoyaltyStatement $statement,
        PermissionService $permissions,
        RoyaltyService $royalties
    ): RedirectResponse {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403
        );

        $royalties->makeAvailable(
            $statement,
            $request->user()
        );

        return back()->with(
            'success',
            'Royalty amount moved to available wallet balance.'
        );
    }
}
