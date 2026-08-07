<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoyaltyController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        AdminAssignmentService $assignments
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
            $labelId = DB::table('labels')
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->value('id');

            $query->where(
                'label_id',
                $labelId ?: 0
            );
        } elseif ($role === 'admin') {
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
