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

        return Inertia::render(
            'V2/Royalties/Index',
            [
                'role' =>
                    $role,

                'summary' =>
                    $summary,

                'statements' =>
                    $query
                        ->orderByDesc(
                            'statement_month'
                        )
                        ->paginate(25),
            ]
        );
    }
}
