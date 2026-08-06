<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentManagementController extends Controller
{
    public function admins(
        Request $request,
        PermissionService $permissions
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $admins = User::query()
            ->whereIn(
                'role',
                ['admin', 'super_admin']
            )
            ->withCount([
                'assignedArtists',
                'assignedLabels',
            ])
            ->orderBy('name')
            ->paginate(25);

        return Inertia::render(
            'V2/Admin/Admins/Index',
            [
                'role' => 'super_admin',
                'admins' => $admins,
            ]
        );
    }

    public function showAdmin(
        Request $request,
        User $admin,
        PermissionService $permissions
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        abort_unless(
            in_array(
                $admin->role,
                ['admin', 'super_admin'],
                true
            ),
            404,
            'Admin not found.'
        );

        $admin->load([
            'assignedArtists:id,stage_name,email,account_status',
            'assignedLabels:id,name,email,status',
        ]);

        return Inertia::render(
            'V2/Admin/Admins/Show',
            [
                'role' => 'super_admin',

                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'account_status' =>
                        $admin->account_status,
                ],

                'artists' =>
                    Artist::query()
                        ->select([
                            'id',
                            'stage_name',
                            'email',
                            'account_status',
                        ])
                        ->orderBy('stage_name')
                        ->get(),

                'labels' =>
                    Label::query()
                        ->select([
                            'id',
                            'name',
                            'email',
                            'status',
                        ])
                        ->orderBy('name')
                        ->get(),

                'assignedArtistIds' =>
                    $admin
                        ->assignedArtists
                        ->pluck('id'),

                'assignedLabelIds' =>
                    $admin
                        ->assignedLabels
                        ->pluck('id'),
            ]
        );
    }

    public function updateAssignments(
        Request $request,
        User $admin,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        abort_unless(
            in_array(
                $admin->role,
                ['admin', 'super_admin'],
                true
            ),
            404
        );

        $validated = $request->validate([
            'artist_ids' => [
                'nullable',
                'array',
            ],

            'artist_ids.*' => [
                'integer',
                'distinct',
                'exists:artists,id',
            ],

            'label_ids' => [
                'nullable',
                'array',
            ],

            'label_ids.*' => [
                'integer',
                'distinct',
                'exists:labels,id',
            ],

            'assignment_role' => [
                'required',
                'string',
                'in:manager,reviewer,finance,support,custom',
            ],

            'can_view' => [
                'required',
                'boolean',
            ],

            'can_edit' => [
                'required',
                'boolean',
            ],

            'can_manage_releases' => [
                'required',
                'boolean',
            ],

            'can_manage_team' => [
                'required',
                'boolean',
            ],

            'can_manage_splits' => [
                'required',
                'boolean',
            ],
        ]);

        $pivot = [
            'assignment_role' =>
                $validated['assignment_role'],

            'can_view' =>
                $validated['can_view'],

            'can_edit' =>
                $validated['can_edit'],

            'can_manage_releases' =>
                $validated[
                    'can_manage_releases'
                ],

            'can_manage_team' =>
                $validated[
                    'can_manage_team'
                ],

            'can_manage_splits' =>
                $validated[
                    'can_manage_splits'
                ],

            'assigned_by' =>
                $request->user()->id,
        ];

        $artistSync = [];

        foreach (
            $validated['artist_ids'] ?? []
            as $artistId
        ) {
            $artistSync[$artistId] = $pivot;
        }

        $labelSync = [];

        foreach (
            $validated['label_ids'] ?? []
            as $labelId
        ) {
            $labelSync[$labelId] = $pivot;
        }

        DB::transaction(
            function () use (
                $admin,
                $artistSync,
                $labelSync
            ) {
                $admin
                    ->assignedArtists()
                    ->sync($artistSync);

                $admin
                    ->assignedLabels()
                    ->sync($labelSync);
            }
        );

        return back()->with(
            'success',
            'Admin assignments updated.'
        );
    }

    public function artists(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $query = Artist::query()
            ->with([
                'label:id,name',
                'assignedAdmins:id,name,email',
            ])
            ->withCount('releases');

        if ($role === 'admin') {
            $query->whereHas(
                'assignedAdmins',
                fn ($builder) =>
                    $builder->where(
                        'users.id',
                        $request->user()->id
                    )
            );
        }

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'stage_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'legal_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        return Inertia::render(
            'V2/Admin/Artists/Index',
            [
                'role' => $role,
                'search' => $search,

                'artists' =>
                    $query
                        ->orderBy('stage_name')
                        ->paginate(25)
                        ->withQueryString(),
            ]
        );
    }

    public function labels(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $query = Label::query()
            ->with([
                'assignedAdmins:id,name,email',
            ])
            ->withCount([
                'artists',
                'releases',
            ]);

        if ($role === 'admin') {
            $query->whereHas(
                'assignedAdmins',
                fn ($builder) =>
                    $builder->where(
                        'users.id',
                        $request->user()->id
                    )
            );
        }

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'legal_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        return Inertia::render(
            'V2/Admin/Labels/Index',
            [
                'role' => $role,
                'search' => $search,

                'labels' =>
                    $query
                        ->orderBy('name')
                        ->paginate(25)
                        ->withQueryString(),
            ]
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

    private function authorizeAdmin(
        Request $request,
        PermissionService $permissions
    ): string {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );

        return $role;
    }
}
