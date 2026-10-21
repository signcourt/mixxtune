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


        $summary = [
            'gross' =>
                (float) (
                    (clone $query)
                        ->sum(
                            'gross_earnings'
                        )
                ),

            'pending' =>
                (float) (
                    (clone $query)
                        ->where(
                            'status',
                            'pending'
                        )
                        ->whereNotExists($importJournal)
                        ->sum(
                            'net_payable'
                        )
                ),

            'approved' =>
                (float) (
                    (clone $query)
                        ->where(
                            'status',
                            'approved'
                        )
                        ->whereNotExists($importJournal)
                        ->sum(
                            'net_payable'
                        )
                ),

            'available' =>
                (float) (
                    (clone $query)
                        ->where(
                            'status',
                            'available'
                        )
                        ->sum(
                            'net_payable'
                        )
                ),

            'paid' =>
                (float) (
                    (clone $query)
                        ->where(
                            'status',
                            'paid'
                        )
                        ->sum(
                            'net_payable'
                        )
                ),
        ];
        $summary['paid_via_import'] = (float) (
            (clone $query)
                ->whereExists($importCredited)
                ->sum('net_payable')
        );

        $summary['held_via_import'] = (float) (
            (clone $query)
                ->whereExists($importHeld)
                ->sum('net_payable')
        );


        return Inertia::render(
            'V2/Royalties/Index',
            [
                'role' =>
                    $role,

                'summary' =>
                    $summary,

                'statements' =>
                    $query
                        ->select('royalty_statements.*')
                        ->addSelect([
                            'import_posting_status' => DB::table('report_import_postings as rip')
                                ->select('rip.status')
                                ->whereColumn(
                                    'rip.royalty_statement_id',
                                    'royalty_statements.id'
                                )
                                ->limit(1),
                        ])
                        ->orderByDesc(
                            'statement_month'
                        )
                        ->paginate(25),
            ]
        );
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
