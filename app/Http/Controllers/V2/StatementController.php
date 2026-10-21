<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\PermissionService;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StatementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        /* MIXXTUNE_FINANCE_CLEANUP_V1: only known roles; any other role sees nothing */
        abort_unless(
            in_array($role, ['artist', 'label', 'admin', 'super_admin'], true),
            403
        );

        $query =
            RoyaltyStatement::query();

        $this->applyScope(
            $query,
            $request,
            $role
        );

        /*
         * Search and status now run on the server over every statement
         * (before: only inside the 25 rows of the current page).
         */
        $filters = [
            'search' => mb_substr(trim((string) $request->input('search', '')), 0, 100),
            'status' => in_array($request->input('status'), ['pending', 'generated', 'approved', 'available', 'paid', 'cancelled'], true)
                ? (string) $request->input('status')
                : '',
        ];

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['search'] !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']).'%';

            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('statement_month', 'like', $term)
                    ->orWhere('public_id', 'like', $term)
                    ->orWhere('currency', 'like', $term)
                    ->orWhereIn('label_id', DB::table('labels')->where('name', 'like', $term)->select('id'))
                    ->orWhereIn('artist_id', DB::table('artists')->where(function ($artists) use ($term) {
                        $artists->where('stage_name', 'like', $term)->orWhere('legal_name', 'like', $term);
                    })->select('id'));
            });
        }

        /* MIXXTUNE_STATEMENTS_V2 (W43): month filter (month list keeps every month) */
        $monthsQuery = clone $query;
        $filters['month'] = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month', '')) ? (string) $request->input('month') : '';
        if ($filters['month'] !== '') {
            $query->where('statement_month', 'like', $filters['month'].'%');
        }

        /*
         * MIXXTUNE_STATEMENTS_V2 (W43)
         * Totals were added up in the browser from the 25 rows on screen only,
         * and "Wallet Available" was the sum of statements marked available -
         * not the wallet. Now: totals over every statement that matches the
         * filters, the real wallet for owners, "waiting for approval" for staff.
         */
        $sum = (clone $query)->toBase()->reorder()->selectRaw(
            'COUNT(*) as n, COALESCE(SUM(gross_earnings),0) as gross, '
            .'COALESCE(SUM(COALESCE(commission_amount,0) + COALESCE(tax_amount,0) + COALESCE(other_deductions,0)),0) as deductions, '
            .'COALESCE(SUM(net_payable),0) as net, '
            ."COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END),0) as pending_n, "
            ."COALESCE(SUM(CASE WHEN status = 'pending' THEN net_payable ELSE 0 END),0) as pending_net"
        )->first();

        $staff = in_array($role, ['admin', 'super_admin'], true);
        $wallet = null;
        if (! $staff) {
            try {
                $owner = app(\App\Services\V2\LabelAccess\LabelFinancialContextService::class)->owner($request->user());
                $acc = $owner ? app(\App\Services\V2\WalletService::class)->account($owner) : null;
                $wallet = $acc ? round((float) $acc->available_balance, 2) : 0.0;
            } catch (\Throwable $e) {
                report($e);
                $wallet = 0.0;
            }
        }

        $summary = [
            'count' => (int) ($sum->n ?? 0),
            'gross' => round((float) ($sum->gross ?? 0), 2),
            'deductions' => round((float) ($sum->deductions ?? 0), 2),
            'net' => round((float) ($sum->net ?? 0), 2),
            'pending_count' => (int) ($sum->pending_n ?? 0),
            'pending_net' => round((float) ($sum->pending_net ?? 0), 2),
            'wallet' => $wallet,
        ];

        $page = $query
            ->orderByDesc('statement_month')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $labels = DB::table('labels')->whereIn('id', $page->getCollection()->pluck('label_id')->filter())->pluck('name', 'id');
        $artists = DB::table('artists')->whereIn('id', $page->getCollection()->pluck('artist_id')->filter())->get(['id', 'stage_name', 'legal_name'])->keyBy('id');
        $invoices = \Illuminate\Support\Facades\Schema::hasTable('invoices')
            ? DB::table('invoices')->whereIn('royalty_statement_id', $page->getCollection()->pluck('id'))->pluck('id', 'royalty_statement_id')
            : collect();

        $page->getCollection()->transform(function (RoyaltyStatement $s) use ($labels, $artists, $invoices) {
            $artist = $s->artist_id ? ($artists[$s->artist_id] ?? null) : null;
            $s->setAttribute('owner_name', $s->label_id ? ($labels[$s->label_id] ?? 'Label #'.$s->label_id) : ($artist ? ($artist->stage_name ?: $artist->legal_name) : ($s->artist_id ? 'Artist #'.$s->artist_id : '—')));
            $s->setAttribute('owner_type', $s->label_id ? 'Label' : ($s->artist_id ? 'Artist' : '—'));
            $s->setAttribute('invoice_id', $invoices[$s->id] ?? null);

            return $s;
        });

        $months = $monthsQuery->toBase()->reorder()->select('statement_month')->distinct()
            ->orderByDesc('statement_month')->limit(36)->pluck('statement_month')
            ->map(fn ($m) => substr((string) $m, 0, 7))->unique()->values();

        return Inertia::render(
            'V2/Statements/Index',
            [
                'role' => $role,
                'filters' => $filters,
                'summary' => $summary,
                'months' => $months,
                'statements' => $page,
                'pipeline' => $staff ? $this->pipeline() : [],
            ]
        );
    }

    /*
     * MIXXTUNE_STATEMENTS_V2 (W45): where each monthly report is on its way
     * to statements (upload -> import -> review -> approve & schedule ->
     * publish). Statements only exist after "published", so an empty page
     * now says which step is waiting and links to it.
     */
    private function pipeline(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('report_imports')) {
            return [];
        }

        $hasFin = \Illuminate\Support\Facades\Schema::hasColumn('report_imports', 'financial_status');

        return DB::table('report_imports')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(function ($r) use ($hasFin) {
                $fin = $hasFin ? (string) ($r->financial_status ?? '') : '';
                $publishAt = isset($r->publish_at) && $r->publish_at ? \Illuminate\Support\Carbon::parse($r->publish_at) : null;

                if (in_array($r->status, ['queued', 'pending', 'processing'], true)) {
                    [$step, $todo] = ['importing', 'The file is still being read. Refresh in a few minutes.'];
                } elseif ($r->status === 'failed') {
                    [$step, $todo] = ['failed', 'Import failed - open it, fix the file and upload again.'];
                } elseif ($fin === 'published' || (isset($r->published_at) && $r->published_at)) {
                    [$step, $todo] = ['published', 'Published - statements are made from this report.'];
                } elseif ($fin === 'approved_scheduled') {
                    $late = $publishAt && $publishAt->lt(now()->subMinutes(15));
                    [$step, $todo] = $late
                        ? ['late', 'Should have been published on '.$publishAt->timezone('Asia/Kolkata')->format('d M Y, h:i A').' - the automatic publisher (cron) may not be running.']
                        : ['scheduled', 'Approved - publishes automatically'.($publishAt ? ' on '.$publishAt->timezone('Asia/Kolkata')->format('d M Y, h:i A') : '').'.'];
                } else {
                    [$step, $todo] = ['review', 'Waiting for your review: check unmapped rows, then "Approve & schedule".'];
                }

                $month = $r->reporting_month ?? null;

                return [
                    'id' => $r->id,
                    'file' => $r->original_filename ?? ('Import #'.$r->id),
                    'month' => $month ? \Illuminate\Support\Carbon::parse(strlen((string) $month) === 7 ? $month.'-01' : $month)->format('F Y') : null,
                    'rows' => (int) ($r->total_rows ?? $r->review_row_count ?? 0),
                    'unmapped' => (int) ($r->review_unmapped_rows ?? 0),
                    'earnings' => isset($r->review_earnings) ? round((float) $r->review_earnings, 2) : null,
                    'step' => $step,
                    'todo' => $todo,
                    'url' => '/v2/admin/reports/imports/'.$r->id.'/view',
                ];
            })
            ->values()
            ->all();
    }

    public function download(
        Request $request,
        RoyaltyStatement $statement,
        PermissionService $permissions
    ): HttpResponse {
        $role = $permissions->role(
            $request->user()
        );

        $query = RoyaltyStatement::query()
            ->where(
                'id',
                $statement->id
            );

        $this->applyScope(
            $query,
            $request,
            $role
        );

        abort_unless(
            $query->exists(),
            403
        );

        /* MIXXTUNE_OWNER_STATEMENT_V1: readable owner statement PDF. */
        $service = app(\App\Services\V2\OwnerStatementService::class);

        return response($service->pdf($statement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$service->fileName($statement).'"',
        ]);
    }

    private function applyScope(
        $query,
        Request $request,
        string $role
    ): void {
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
        }

        if ($role === 'label') {
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
        }

        if ($role === 'admin') {
            $assignments = app(
                AdminAssignmentService::class
            );

            $artistIds =
                $assignments->artistIds(
                    $request->user()
                );

            $labelIds =
                $assignments->labelIds(
                    $request->user()
                );

            if (
                $artistIds->isEmpty()
                && $labelIds->isEmpty()
            ) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(
                    function ($builder) use (
                        $artistIds,
                        $labelIds
                    ) {
                        if ($artistIds->isNotEmpty()) {
                            $builder->whereIn(
                                'artist_id',
                                $artistIds
                            );
                        }

                        if ($labelIds->isNotEmpty()) {
                            if ($artistIds->isNotEmpty()) {
                                $builder->orWhereIn(
                                    'label_id',
                                    $labelIds
                                );
                            } else {
                                $builder->whereIn(
                                    'label_id',
                                    $labelIds
                                );
                            }
                        }
                    }
                );
            }
        }
    }
}
