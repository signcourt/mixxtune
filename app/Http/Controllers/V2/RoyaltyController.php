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
}
