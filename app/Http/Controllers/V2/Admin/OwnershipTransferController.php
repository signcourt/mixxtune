<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\CatalogueOwnershipService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OwnershipTransferController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403,
            'Super Admin access required.'
        );

        return Inertia::render(
            'V2/Admin/Ownership/Index',
            [
                'releases' =>
                    DB::table('releases')
                        ->whereNull('deleted_at')
                        ->select([
                            'id',
                            'title',
                            'upc',
                            'artist_id',
                            'label_id',
                            'primary_artist_name',
                        ])
                        ->orderByDesc('id')
                        ->limit(300)
                        ->get(),

                'tracks' =>
                    DB::table('tracks')
                        ->whereNull('deleted_at')
                        ->select([
                            'id',
                            'release_id',
                            'title',
                            'isrc',
                        ])
                        ->orderByDesc('id')
                        ->limit(300)
                        ->get(),

                'artists' =>
                    DB::table('artists')
                        ->whereNull('deleted_at')
                        ->select([
                            'id',
                            'stage_name',
                            'user_id',
                            'label_id',
                        ])
                        ->orderBy('stage_name')
                        ->get(),

                'labels' =>
                    DB::table('labels')
                        ->whereNull('deleted_at')
                        ->select([
                            'id',
                            'name',
                            'user_id',
                        ])
                        ->orderBy('name')
                        ->get(),

                'users' =>
                    DB::table('users')
                        ->select([
                            'id',
                            'name',
                            'email',
                        ])
                        ->orderBy('name')
                        ->get(),

                'history' =>
                    DB::table(
                        'catalogue_transfers'
                    )
                        ->orderByDesc('id')
                        ->limit(100)
                        ->get(),
            ]
        );
    }

    public function transferRelease(
        Request $request,
        PermissionService $permissions,
        CatalogueOwnershipService $ownership
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'release_id' => [
                'required',
                'integer',
                'exists:releases,id',
            ],

            'owner_type' => [
                'required',
                'in:artist,label',
            ],

            'owner_id' => [
                'required',
                'integer',
            ],

            'scope' => [
                'required',
                'in:future_only,pending_and_future',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $ownership->transferRelease(
            (int) $validated['release_id'],
            $validated['owner_type'],
            (int) $validated['owner_id'],
            $validated['scope'],
            $validated['reason'] ?? null,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Release ownership transferred.'
        );
    }

    public function transferTrack(
        Request $request,
        PermissionService $permissions,
        CatalogueOwnershipService $ownership
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'track_id' => [
                'required',
                'integer',
                'exists:tracks,id',
            ],

            'release_id' => [
                'required',
                'integer',
                'exists:releases,id',
            ],

            'scope' => [
                'required',
                'in:future_only,pending_and_future',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $ownership->transferTrack(
            (int) $validated['track_id'],
            (int) $validated['release_id'],
            $validated['scope'],
            $validated['reason'] ?? null,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Track transferred successfully.'
        );
    }

    public function transferLabelUser(
        Request $request,
        PermissionService $permissions,
        CatalogueOwnershipService $ownership
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'label_id' => [
                'required',
                'integer',
                'exists:labels,id',
            ],

            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $ownership->transferLabelUser(
            (int) $validated['label_id'],
            (int) $validated['user_id'],
            $validated['reason'] ?? null,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Label user changed successfully.'
        );
    }

    public function transferArtistUser(
        Request $request,
        PermissionService $permissions,
        CatalogueOwnershipService $ownership
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'artist_id' => [
                'required',
                'integer',
                'exists:artists,id',
            ],

            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $ownership->transferArtistUser(
            (int) $validated['artist_id'],
            (int) $validated['user_id'],
            $validated['reason'] ?? null,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Artist user changed successfully.'
        );
    }

    private function authorizeSuperAdmin(
        Request $request,
        PermissionService $permissions
    ): void {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403,
            'Super Admin access required.'
        );
    }
}
