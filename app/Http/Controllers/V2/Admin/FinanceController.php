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

        /*
         * W55: "Pending" / "Approved" mean the hand-approval path only;
         * statements paid by a report import keep the old "pending" word in
         * the database but are shown and filtered as import statements.
         */
        $postingStatus = function (array $statuses) {
            return function ($sub) use ($statuses) {
                $sub->select(DB::raw(1))
                    ->from('report_import_postings as rip_f')
                    ->whereColumn('rip_f.royalty_statement_id', 'statements.id')
                    ->whereIn('rip_f.status', $statuses);
            };
        };

        if ($filters['status'] === 'import_paid') {
            $query->whereExists($postingStatus(['posted', 'completed']));
        } elseif ($filters['status'] === 'import_held') {
            $query->whereExists($postingStatus(['held_unclaimed']));
        } elseif ($filters['status'] !== '') {
            $query->where(
                'statements.status',
                $filters['status']
            );

            if (in_array($filters['status'], ['pending', 'approved'], true)) {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('report_import_postings as rip_n')
                        ->whereColumn('rip_n.royalty_statement_id', 'statements.id');
                });
            }
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

                // MIXXTUNE_FINANCE_HOLD_RELEASE_V1
                DB::raw(
                    "(SELECT MIN(ruh.id) FROM report_unclaimed_revenues ruh WHERE ruh.royalty_statement_id = statements.id AND ruh.status = 'held') as unclaimed_hold_id"
                ),

                DB::raw(
                    'COALESCE(labels.user_id, artists.user_id) as owner_user_id'
                ),

                /* W55: label statements are named by the label, not "Unknown Artist" */
                DB::raw(
                    "CASE WHEN statements.artist_id IS NULL AND statements.label_id IS NOT NULL THEN 'label' ELSE 'artist' END as owner_type"
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

        /* MIXXTUNE_FINANCE_SAFETY_V1: blocked months show a message instead of an error page. */
        try {
            $result = $royalties
                ->generateMonthlyStatements(
                    $validated['month'],
                    (float) (
                        $validated[
                            'commission_percent'
                        ] ?? 0
                    )
                );
        } catch (\RuntimeException $exception) {
            return back()->withErrors([
                'generate' => str_contains($exception->getMessage(), 'cutover')
                    ? 'Months before July 2026 are already included in the opening balances from the old system, so statements cannot be generated for '.$validated['month'].'. Choose July 2026 or later.'
                    : 'Statements could not be generated: '.$exception->getMessage(),
            ]);
        }

        \App\Services\V2\FinanceAudit::log(
            'finance.statements_generated',
            'Statements generated for '.$validated['month'].'.',
            ['month' => $validated['month'], 'created' => $result['created'] ?? null, 'updated' => $result['updated'] ?? null, 'skipped' => $result['skipped'] ?? null, 'failed' => $result['failed_count'] ?? null]
        );

        $skipped = (int) ($result['skipped'] ?? 0);

        $message = 'Statements for '.$validated['month'].': created '.$result['created'].', rebuilt '.$result['updated']
            .($skipped ? ', left unchanged '.$skipped.' (already approved / paid / paid via report import)' : '')
            .($result['failed_count'] ? ', failed '.$result['failed_count'] : '').'.';

        if (($result['created'] + $result['updated'] + $skipped + $result['failed_count']) === 0) {
            $message = 'Nothing to generate for '.$validated['month'].': no published report has revenue for that month yet.';
        }

        return back()->with('success', $message);
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

        /* MIXXTUNE_FINANCE_SAFETY_V1: e-mailed code + audit before money moves to a wallet. */
        app(\App\Services\V2\ActionOtpGuard::class)->require(
            $request->user(),
            'statement.approve:'.$statement->id,
            'approve royalty statement #'.$statement->id.' ('.number_format((float) $statement->net_payable, 2).' '.$statement->currency.')'
        );

        $before = \App\Services\V2\FinanceAudit::pick($statement, ['id', 'status', 'net_payable', 'currency', 'statement_month', 'artist_id', 'label_id']);

        try {
            $royalties->approve(
                $statement,
                $request->user()
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors([
                'approve' => $exception->getMessage(),
            ]);
        }

        \App\Services\V2\FinanceAudit::log(
            'finance.statement_approved',
            'Royalty statement #'.$statement->id.' approved (pending wallet balance).',
            \App\Services\V2\FinanceAudit::pick($statement->fresh(), ['id', 'status', 'net_payable', 'approved_at', 'approved_by']),
            $statement,
            $before
        );

        return back()->with(
            'success',
            'Royalty statement approved and added to pending wallet balance.'
        );
    }

    /*
     * MIXXTUNE_FINANCE_HOLD_RELEASE_V1
     *
     * Super-admin releases an unclaimed import hold to the owner's
     * canonical wallet once the label / artist is linked to an active
     * user. All money rules live in ReportUnclaimedRevenueReleaser.
     */
    public function releaseHold(
        Request $request,
        int $hold,
        PermissionService $permissions,
        \App\Services\V2\ReportUnclaimedRevenueReleaser $releaser
    ): RedirectResponse {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403
        );

        /* MIXXTUNE_FINANCE_SAFETY_V1 */
        app(\App\Services\V2\ActionOtpGuard::class)->require(
            $request->user(),
            'hold.release:'.$hold,
            'release held revenue #'.$hold.' to the owner wallet'
        );

        try {
            $result = $releaser->release(
                $hold,
                (int) $request->user()->id,
                'Released from Finance & Royalties page'
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors([
                'release' => $e->getMessage(),
            ]);
        }

        \App\Services\V2\FinanceAudit::log(
            'finance.hold_released',
            'Held revenue #'.$hold.' of '.number_format((float) ($result['amount'] ?? 0), 2).' released to the owner wallet.',
            is_array($result) ? array_filter($result, fn ($v) => is_scalar($v) || $v === null) : [],
        );

        return back()->with(
            'success',
            'Held revenue of '.number_format((float) $result['amount'], 2).' released to the owner wallet.'
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

        /* MIXXTUNE_FINANCE_SAFETY_V1 */
        app(\App\Services\V2\ActionOtpGuard::class)->require(
            $request->user(),
            'statement.available:'.$statement->id,
            'make royalty statement #'.$statement->id.' available for withdrawal ('.number_format((float) $statement->net_payable, 2).' '.$statement->currency.')'
        );

        $before = \App\Services\V2\FinanceAudit::pick($statement, ['id', 'status', 'net_payable', 'currency']);

        try {
            $royalties->makeAvailable(
                $statement,
                $request->user()
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors([
                'available' => $exception->getMessage(),
            ]);
        }

        \App\Services\V2\FinanceAudit::log(
            'finance.statement_made_available',
            'Royalty statement #'.$statement->id.' moved to available wallet balance.',
            \App\Services\V2\FinanceAudit::pick($statement->fresh(), ['id', 'status', 'net_payable', 'available_at']),
            $statement,
            $before
        );

        return back()->with(
            'success',
            'Royalty amount moved to available wallet balance.'
        );
    }
}
