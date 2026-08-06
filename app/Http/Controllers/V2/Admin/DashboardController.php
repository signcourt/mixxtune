<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Authentication required.'
        );

        $role = $permissions->role($user);

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

        $releaseQuery = Release::query()
            ->whereNull('deleted_at');

        /*
         * Normal Admin केवल assigned artists की
         * releases देखेगा। Super Admin सब देखेगा।
         */
        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $artistIds = DB::table('artists')
                ->where(
                    'assigned_admin_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->pluck('id');

            $releaseQuery->whereIn(
                'artist_id',
                $artistIds
            );
        }

        $recentReleases = (clone $releaseQuery)
            ->select([
                'id',
                'title',
                'primary_artist_name',
                'status',
                'upc',
                'digital_release_date',
                'created_at',
                'submitted_at',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return Inertia::render(
            'V2/Admin/Dashboard',
            [
                'role' => $role,

                'stats' => [
                    'total_releases' =>
                        (clone $releaseQuery)->count(),

                    'draft' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'draft'
                            )
                            ->count(),

                    'submitted' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'submitted'
                            )
                            ->count(),

                    'approved' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'approved'
                            )
                            ->count(),

                    'processing' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'processing'
                            )
                            ->count(),

                    'delivered' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'delivered'
                            )
                            ->count(),

                    'live' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'live'
                            )
                            ->count(),

                    'rejected' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'rejected'
                            )
                            ->count(),
                ],

                'recentReleases' =>
                    $recentReleases,
            ]
        );
    }
}
