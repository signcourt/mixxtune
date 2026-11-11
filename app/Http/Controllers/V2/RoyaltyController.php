<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\AdminFinancialAccessService;
use App\Services\V2\PermissionService;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoyaltyController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        AdminFinancialAccessService $financialAccess
    ): Response {
        $permissions->authorize(
            $request->user(),
            'royalties.view'
        );

        $role = $permissions->role(
            $request->user()
        );

        /*
         * W54: admins / super admin see only the rights royalty tools here
         * (statements live on Finance & Royalties and Statements), so the
         * statement totals + list are not queried for them at all.
         */
        if (in_array($role, ['admin', 'super_admin'], true)) {
            return Inertia::render('V2/Royalties/Index', [
                'role' => $role,
                'summary' => [],
                'statements' => ['data' => []],
                'rightsImports' => $role === 'super_admin' ? $this->rightsImports() : [],
            ]);
        }

        $query =
            RoyaltyStatement::query();

        if ($role === 'artist') {
            $artistId = DB::table('artists')
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->value('id');

            $query->where(
                'artist_id',
                $artistId ?: 0
            );
        } elseif ($role === 'label') {
            $teamAccess = app(
                LabelTeamAccessService::class
            );

            $labelIds = $teamAccess
                ->accessibleLabelIds(
                    $request->user()
                );

            $artistIds = $teamAccess
                ->accessibleArtistIds(
                    $request->user()
                );

            if (
                $labelIds->isEmpty()
                && $artistIds->isEmpty()
            ) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(
                    function ($builder) use (
                        $labelIds,
                        $artistIds
                    ) {
                        if ($labelIds->isNotEmpty()) {
                            $builder->whereIn(
                                'label_id',
                                $labelIds
                            );
                        }

                        if ($artistIds->isNotEmpty()) {
                            if ($labelIds->isNotEmpty()) {
                                $builder->orWhereIn(
                                    'artist_id',
                                    $artistIds
                                );
                            } else {
                                $builder->whereIn(
                                    'artist_id',
                                    $artistIds
                                );
                            }
                        }
                    }
                );
            }
        } elseif ($role === 'admin') {
            $financialAccess
                ->applyFinancialOwnerScope(
                    $query,
                    $request->user(),
                    'label_id',
                    'artist_id'
                );
        }

        /*
         * MIXXTUNE_ROYALTIES_IMPORT_STATUS_AWARE_V1
         *
         * Statements created by a report import are settled by
         * ReportImportScheduledPublisher (wallet credit or unclaimed hold).
         * Their legacy status stays "pending", so they are excluded from the
         * pending/approved totals and reported separately.
         */
        $importJournal = function ($sub) {
            $sub->select(DB::raw(1))
                ->from('report_import_postings as rip')
                ->whereColumn(
                    'rip.royalty_statement_id',
                    'royalty_statements.id'
                );
        };

        $importCredited = function ($sub) use ($importJournal) {
            $importJournal($sub);
            $sub->whereIn('rip.status', ['posted', 'completed']);
        };

        $importHeld = function ($sub) use ($importJournal) {
            $importJournal($sub);
            $sub->where('rip.status', 'held_unclaimed');
        };


        /*
         * W72 MIXXTUNE_ROYALTIES_V3: totals ignore cancelled statements,
         * the monthly chart and the status counts come from ALL statements
         * (not only the 25 on the page), and the status filter / month
         * search run on the server so they cover every page.
         */
        $live = fn () => (clone $query)->where('royalty_statements.status', '!=', 'cancelled');
        $notImport = fn ($q) => $q->whereNotExists($importJournal);

        $summary = [
            'gross' => (float) $live()->sum('gross_earnings'),
            'deductions' => (float) $live()->sum(DB::raw('COALESCE(commission_amount, 0) + COALESCE(tax_amount, 0) + COALESCE(other_deductions, 0)')),
            'net' => (float) $live()->sum('net_payable'),
            'pending' => (float) $notImport((clone $query)->where('status', 'pending'))->sum('net_payable'),
            'approved' => (float) $notImport((clone $query)->where('status', 'approved'))->sum('net_payable'),
            'available' => (float) (clone $query)->where('status', 'available')->sum('net_payable'),
            'paid' => (float) (clone $query)->where('status', 'paid')->sum('net_payable'),
            'paid_via_import' => (float) (clone $query)->whereExists($importCredited)->sum('net_payable'),
            'held_via_import' => (float) (clone $query)->whereExists($importHeld)->sum('net_payable'),
        ];

        $statusFilters = [
            'pending' => fn ($q) => $notImport($q->where('royalty_statements.status', 'pending')),
            'approved' => fn ($q) => $notImport($q->where('royalty_statements.status', 'approved')),
            'available' => fn ($q) => $q->where('royalty_statements.status', 'available'),
            'paid' => fn ($q) => $q->where('royalty_statements.status', 'paid'),
            'import_paid' => fn ($q) => $q->whereExists($importCredited),
            'import_held' => fn ($q) => $q->whereExists($importHeld),
            'cancelled' => fn ($q) => $q->where('royalty_statements.status', 'cancelled'),
        ];

        $counts = ['all' => (clone $query)->count()];
        foreach ($statusFilters as $key => $apply) {
            $counts[$key] = $apply(clone $query)->count();
        }

        $monthly = $live()
            ->whereNotNull('statement_month')
            ->where('statement_month', '!=', '')
            ->toBase()
            ->reorder()
            /* statement_month may be "2026-07" or "2026-07-01": group by the month part */
            ->selectRaw('SUBSTR(statement_month, 1, 7) as ym')
            ->selectRaw('COALESCE(SUM(net_payable), 0) as net')
            ->selectRaw('COALESCE(SUM(gross_earnings), 0) as gross')
            ->groupByRaw('SUBSTR(statement_month, 1, 7)')
            ->orderByDesc('ym')
            ->limit(12)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($r) => ['month' => (string) $r->ym, 'net' => round((float) $r->net, 2), 'gross' => round((float) $r->gross, 2)])
            ->all();

        $status = (string) $request->query('status', '');
        if (!array_key_exists($status, $statusFilters)) {
            $status = '';
        }
        $search = trim((string) $request->query('search', ''));

        $list = clone $query;
        if ($status !== '') {
            $statusFilters[$status]($list);
        }
        if ($search !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $list->where(function ($w) use ($like) {
                $w->whereRaw("royalty_statements.statement_month LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("royalty_statements.public_id LIKE ? ESCAPE '!'", [$like]);
            });
        }

        return Inertia::render(
            'V2/Royalties/Index',
            [
                'role' => $role,
                'summary' => $summary,
                'counts' => $counts,
                'monthly' => $monthly,
                'filters' => ['status' => $status, 'search' => $search],
                'statements' => $list
                    ->select('royalty_statements.*')
                    ->addSelect([
                        'import_posting_status' => DB::table('report_import_postings as rip')
                            ->select('rip.status')
                            ->whereColumn('rip.royalty_statement_id', 'royalty_statements.id')
                            ->limit(1),
                    ])
                    ->orderByDesc('statement_month')
                    ->orderByDesc('royalty_statements.id')
                    ->paginate(25)
                    ->withQueryString(),
            ]
        );
    }

    /*
     * W150: owner-facing Publishing dashboard.
     *
     * Shows the published rights/performing royalties (IPRS / PRS / TPL) that
     * were matched to THIS owner's catalogue by ISRC. Read-only - money still
     * reaches the wallet through the separate admin "credit wallet" step.
     * Rights ledger lines are the ones with source_type = rights_royalty_allocation.
     */
    public function publishing(
        Request $request,
        PermissionService $permissions
    ): Response {
        $permissions->authorize(
            $request->user(),
            'royalties.view'
        );

        $role = $permissions->role($request->user());

        $base = DB::table('royalty_ledgers as rl')
            ->where('rl.source_type', 'rights_royalty_allocation');

        if ($role === 'artist') {
            $artistId = DB::table('artists')
                ->where('user_id', $request->user()->id)
                ->value('id');

            $base->where('rl.artist_id', $artistId ?: 0);
        } elseif ($role === 'label') {
            $teamAccess = app(LabelTeamAccessService::class);

            $labelIds = $teamAccess->accessibleLabelIds($request->user());
            $artistIds = $teamAccess->accessibleArtistIds($request->user());

            if ($labelIds->isEmpty() && $artistIds->isEmpty()) {
                $base->whereRaw('1 = 0');
            } else {
                $base->where(function ($builder) use ($labelIds, $artistIds) {
                    if ($labelIds->isNotEmpty()) {
                        $builder->whereIn('rl.label_id', $labelIds);
                    }

                    if ($artistIds->isNotEmpty()) {
                        if ($labelIds->isNotEmpty()) {
                            $builder->orWhereIn('rl.artist_id', $artistIds);
                        } else {
                            $builder->whereIn('rl.artist_id', $artistIds);
                        }
                    }
                });
            }
        } else {
            /* Admin / super admin review publishing under Rights Royalties. */
            return Inertia::render('V2/Royalties/Publishing', [
                'role' => $role,
                'isOwner' => false,
                'summary' => ['total_gross' => 0, 'total_payable' => 0, 'lines' => 0, 'currency' => 'INR'],
                'monthly' => [],
                'rows' => ['data' => []],
                'filters' => ['month' => ''],
            ]);
        }

        $summary = [
            'total_gross' => round((float) (clone $base)->sum('rl.gross_amount'), 2),
            'total_payable' => round((float) (clone $base)->sum('rl.payable_amount'), 2),
            'lines' => (clone $base)->count(),
            'currency' => (string) ((clone $base)->value('rl.currency') ?: 'INR'),
        ];

        $monthly = (clone $base)
            ->whereNotNull('rl.statement_month')
            ->where('rl.statement_month', '!=', '')
            ->selectRaw('SUBSTR(rl.statement_month, 1, 7) as ym')
            ->selectRaw('COALESCE(SUM(rl.payable_amount), 0) as payable')
            ->groupByRaw('SUBSTR(rl.statement_month, 1, 7)')
            ->orderByDesc('ym')
            ->limit(12)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($r) => ['month' => (string) $r->ym, 'payable' => round((float) $r->payable, 2)])
            ->all();

        $month = trim((string) $request->query('month', ''));

        $list = clone $base;
        if ($month !== '') {
            $list->where('rl.statement_month', 'like', $month.'%');
        }

        $rows = $list
            ->leftJoin('tracks as t', 't.id', '=', 'rl.track_id')
            ->orderByDesc('rl.statement_month')
            ->orderByDesc('rl.id')
            ->select([
                'rl.public_id',
                'rl.statement_month',
                'rl.territory',
                'rl.currency',
                'rl.gross_amount',
                'rl.payable_amount',
                'rl.status',
                't.title as track_title',
                't.isrc as track_isrc',
            ])
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('V2/Royalties/Publishing', [
            'role' => $role,
            'isOwner' => true,
            'summary' => $summary,
            'monthly' => $monthly,
            'rows' => $rows,
            'filters' => ['month' => $month],
        ]);
    }

    /* W54: list of uploaded rights royalty reports (newest first) */
    private function rightsImports(): array
    {
        return \App\Models\Finance\RightsRoyaltyImport::query()
            ->select([
                'public_id', 'source', 'statement_month', 'currency', 'original_filename', 'status',
                'total_rows', 'mapped_rows', 'unmapped_rows', 'gross_amount', 'mapped_amount',
                'unmapped_amount', 'processed_at', 'approved_at', 'published_at', 'created_at',
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($i) => [
                'public_id' => $i->public_id,
                'source' => $i->source,
                'statement_month' => $i->statement_month,
                'currency' => $i->currency,
                'file' => $i->original_filename,
                'status' => $i->status,
                'total_rows' => (int) $i->total_rows,
                'mapped_rows' => (int) $i->mapped_rows,
                'unmapped_rows' => (int) $i->unmapped_rows,
                'gross_amount' => round((float) $i->gross_amount, 2),
                'mapped_amount' => round((float) $i->mapped_amount, 2),
                'unmapped_amount' => round((float) $i->unmapped_amount, 2),
                'uploaded' => optional($i->created_at)?->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'approved' => $i->approved_at ? \Illuminate\Support\Carbon::parse($i->approved_at)->timezone('Asia/Kolkata')->format('d M Y') : null,
                'published' => $i->published_at ? \Illuminate\Support\Carbon::parse($i->published_at)->timezone('Asia/Kolkata')->format('d M Y') : null,
            ])
            ->all();
    }
}
