<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\AdminFinancialAccessService;
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
        PermissionService $permissions,
        AdminFinancialAccessService $financialAccess
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

        $financialAccess
            ->applyFinancialOwnerScope(
                $query,
                $request->user(),
                'statements.label_id',
                'statements.artist_id'
            );

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

        /*
         * MIXXTUNE_FINANCE_IMPORT_PAID_FLAG_V1
         *
         * Statements materialized from a report import are paid by
         * ReportImportScheduledPublisher. They are never "pending
         * approval" and must not be offered the legacy wallet actions.
         */
        $importJournal = function ($sub) {
            $sub->select(DB::raw(1))
                ->from('report_import_postings as rip')
                ->whereColumn(
                    'rip.royalty_statement_id',
                    'statements.id'
                );
        };

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
                    ->whereNotExists($importJournal)
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
                    ->whereNotExists($importJournal)
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

        /*
         * MIXXTUNE_FINANCE_IMPORT_STATUS_AWARE_V2
         *
         * Split import-journal statements by their posting status:
         * posted/completed = credited, held_unclaimed = held,
         * anything else = materialized but not yet published.
         */
        $importSum = function (array $statuses, bool $exclude = false) use ($summaryQuery) {
            return (float) (clone $summaryQuery)
                ->whereExists(function ($sub) use ($statuses, $exclude) {
                    $sub->select(DB::raw(1))
                        ->from('report_import_postings as rip')
                        ->whereColumn('rip.royalty_statement_id', 'statements.id');

                    if ($exclude) {
                        $sub->whereNotIn('rip.status', $statuses);
                    } else {
                        $sub->whereIn('rip.status', $statuses);
                    }
                })
                ->sum('statements.net_payable');
        };

        $summary['paid_via_import'] = $importSum(['posted', 'completed']);
        $summary['held_via_import'] = $importSum(['held_unclaimed']);
        $summary['scheduled_via_import'] = $importSum(['posted', 'completed', 'held_unclaimed'], true);

        $statements = $query
            ->select([
                'statements.*',

                DB::raw(
                    'EXISTS (SELECT 1 FROM report_import_postings rip WHERE rip.royalty_statement_id = statements.id) as paid_via_import'
                ),

                DB::raw(
                    '(SELECT MIN(rip2.status) FROM report_import_postings rip2 WHERE rip2.royalty_statement_id = statements.id) as import_posting_status'
                ),

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
