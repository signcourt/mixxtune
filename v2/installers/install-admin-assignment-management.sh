#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/admin-assignment-management/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Admin/Admins \
    resources/js/Pages/V2/Admin/Artists \
    resources/js/Pages/V2/Admin/Labels \
    v2/runtime/state

echo "=================================================="
echo "INSTALLING ADMIN ASSIGNMENT MANAGEMENT"
echo "=================================================="

for FILE in \
    routes/web.php \
    app/Http/Controllers/V2/Admin/AssignmentManagementController.php \
    resources/js/Pages/V2/Admin/Admins/Index.jsx \
    resources/js/Pages/V2/Admin/Admins/Show.jsx \
    resources/js/Pages/V2/Admin/Artists/Index.jsx \
    resources/js/Pages/V2/Admin/Labels/Index.jsx
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[1/5] Creating controller..."

cat > app/Http/Controllers/V2/Admin/AssignmentManagementController.php <<'PHP'
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
PHP


echo "[2/5] Creating Admin pages..."

cat > resources/js/Pages/V2/Admin/Admins/Index.jsx <<'JSX'
import {
    Head,
    Link,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'super_admin',
    admins = {},
}) {
    return (
        <PanelLayout
            role={role}
            title="Admins"
            subtitle="Manage admin assignments"
        >
            <Head title="Admins" />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            {[
                                'Admin',
                                'Role',
                                'Status',
                                'Artists',
                                'Labels',
                                'Action',
                            ].map((heading) => (
                                <th
                                    key={heading}
                                    className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-slate-100">
                        {(admins.data ?? []).map(
                            (admin) => (
                                <tr key={admin.id}>
                                    <Cell>
                                        <div className="font-semibold text-slate-900">
                                            {admin.name}
                                        </div>

                                        <div className="text-xs text-slate-500">
                                            {admin.email}
                                        </div>
                                    </Cell>

                                    <Cell>
                                        {admin.role}
                                    </Cell>

                                    <Cell>
                                        {admin.account_status}
                                    </Cell>

                                    <Cell>
                                        {
                                            admin.assigned_artists_count
                                        }
                                    </Cell>

                                    <Cell>
                                        {
                                            admin.assigned_labels_count
                                        }
                                    </Cell>

                                    <Cell>
                                        <Link
                                            href={`/v2/admin/admins/${admin.id}`}
                                            className="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white"
                                        >
                                            Manage
                                        </Link>
                                    </Cell>
                                </tr>
                            )
                        )}
                    </tbody>
                </table>
            </section>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX

cat > resources/js/Pages/V2/Admin/Admins/Show.jsx <<'JSX'
import {
    Head,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'super_admin',
    admin,
    artists = [],
    labels = [],
    assignedArtistIds = [],
    assignedLabelIds = [],
}) {
    const {
        data,
        setData,
        patch,
        processing,
    } = useForm({
        artist_ids:
            assignedArtistIds.map(Number),

        label_ids:
            assignedLabelIds.map(Number),

        assignment_role: 'manager',

        can_view: true,
        can_edit: true,
        can_manage_releases: true,
        can_manage_team: false,
        can_manage_splits: false,
    });

    const toggle = (
        field,
        id
    ) => {
        const numberId = Number(id);

        setData(
            field,
            data[field].includes(numberId)
                ? data[field].filter(
                      (item) =>
                          item !== numberId
                  )
                : [
                      ...data[field],
                      numberId,
                  ]
        );
    };

    const submit = (event) => {
        event.preventDefault();

        patch(
            `/v2/admin/admins/${admin.id}/assignments`,
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Admin Assignments"
            subtitle={`${admin.name} • ${admin.email}`}
        >
            <Head title="Admin Assignments" />

            <form
                onSubmit={submit}
                className="space-y-6"
            >
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Assignment Permissions
                    </h2>

                    <div className="mt-4 grid gap-4 md:grid-cols-3">
                        <select
                            value={
                                data.assignment_role
                            }
                            onChange={(event) =>
                                setData(
                                    'assignment_role',
                                    event.target.value
                                )
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3"
                        >
                            <option value="manager">
                                Manager
                            </option>

                            <option value="reviewer">
                                Reviewer
                            </option>

                            <option value="finance">
                                Finance
                            </option>

                            <option value="support">
                                Support
                            </option>

                            <option value="custom">
                                Custom
                            </option>
                        </select>

                        {[
                            [
                                'can_view',
                                'Can View',
                            ],
                            [
                                'can_edit',
                                'Can Edit',
                            ],
                            [
                                'can_manage_releases',
                                'Manage Releases',
                            ],
                            [
                                'can_manage_team',
                                'Manage Team',
                            ],
                            [
                                'can_manage_splits',
                                'Manage Splits',
                            ],
                        ].map(
                            ([field, label]) => (
                                <label
                                    key={field}
                                    className="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                                >
                                    <input
                                        type="checkbox"
                                        checked={
                                            data[field]
                                        }
                                        onChange={(
                                            event
                                        ) =>
                                            setData(
                                                field,
                                                event
                                                    .target
                                                    .checked
                                            )
                                        }
                                    />

                                    <span className="text-sm font-semibold text-slate-700">
                                        {label}
                                    </span>
                                </label>
                            )
                        )}
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <SelectionSection
                        title="Assign Artists"
                        items={artists}
                        selected={
                            data.artist_ids
                        }
                        nameKey="stage_name"
                        statusKey="account_status"
                        onToggle={(id) =>
                            toggle(
                                'artist_ids',
                                id
                            )
                        }
                    />

                    <SelectionSection
                        title="Assign Labels"
                        items={labels}
                        selected={
                            data.label_ids
                        }
                        nameKey="name"
                        statusKey="status"
                        onToggle={(id) =>
                            toggle(
                                'label_ids',
                                id
                            )
                        }
                    />
                </div>

                <div className="sticky bottom-4 flex justify-end">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-7 py-3 text-sm font-semibold text-white shadow-lg disabled:opacity-50"
                    >
                        {processing
                            ? 'Saving...'
                            : 'Save Assignments'}
                    </button>
                </div>
            </form>
        </PanelLayout>
    );
}

function SelectionSection({
    title,
    items,
    selected,
    nameKey,
    statusKey,
    onToggle,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-slate-900">
                    {title}
                </h2>

                <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">
                    {selected.length} selected
                </span>
            </div>

            <div className="mt-4 max-h-[520px] space-y-2 overflow-y-auto">
                {items.map((item) => {
                    const checked =
                        selected.includes(
                            Number(item.id)
                        );

                    return (
                        <label
                            key={item.id}
                            className={[
                                'flex cursor-pointer items-center gap-3 rounded-xl border p-4',
                                checked
                                    ? 'border-violet-400 bg-violet-50'
                                    : 'border-slate-200 bg-white',
                            ].join(' ')}
                        >
                            <input
                                type="checkbox"
                                checked={checked}
                                onChange={() =>
                                    onToggle(item.id)
                                }
                            />

                            <div className="min-w-0 flex-1">
                                <div className="truncate font-semibold text-slate-900">
                                    {item[nameKey]}
                                </div>

                                <div className="truncate text-xs text-slate-500">
                                    {item.email ||
                                        'No email'}
                                </div>
                            </div>

                            <span className="text-xs capitalize text-slate-500">
                                {item[statusKey]}
                            </span>
                        </label>
                    );
                })}
            </div>
        </section>
    );
}
JSX


echo "[3/5] Creating Artists and Labels pages..."

cat > resources/js/Pages/V2/Admin/Artists/Index.jsx <<'JSX'
import {
    Head,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    artists = {},
    search = '',
}) {
    return (
        <PanelLayout
            role={role}
            title="Artists"
            subtitle="Assigned artist accounts"
        >
            <Head title="Artists" />

            <div className="space-y-5">
                <input
                    type="search"
                    defaultValue={search}
                    placeholder="Search artist..."
                    onKeyDown={(event) => {
                        if (
                            event.key === 'Enter'
                        ) {
                            router.get(
                                '/v2/admin/artists',
                                {
                                    search:
                                        event
                                            .currentTarget
                                            .value,
                                }
                            );
                        }
                    }}
                    className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 md:max-w-md"
                />

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'Artist',
                                    'Label',
                                    'Status',
                                    'KYC',
                                    'Releases',
                                    'Assigned Admins',
                                ].map((heading) => (
                                    <th
                                        key={heading}
                                        className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {(artists.data ?? []).map(
                                (artist) => (
                                    <tr key={artist.id}>
                                        <Cell>
                                            <div className="font-semibold text-slate-900">
                                                {
                                                    artist.stage_name
                                                }
                                            </div>

                                            <div className="text-xs text-slate-500">
                                                {artist.email}
                                            </div>
                                        </Cell>

                                        <Cell>
                                            {artist.label
                                                ?.name ||
                                                'Independent'}
                                        </Cell>

                                        <Cell>
                                            {
                                                artist.account_status
                                            }
                                        </Cell>

                                        <Cell>
                                            {artist.kyc_status}
                                        </Cell>

                                        <Cell>
                                            {
                                                artist.releases_count
                                            }
                                        </Cell>

                                        <Cell>
                                            {(
                                                artist.assigned_admins ??
                                                []
                                            )
                                                .map(
                                                    (
                                                        admin
                                                    ) =>
                                                        admin.name
                                                )
                                                .join(', ') ||
                                                'Unassigned'}
                                        </Cell>
                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX

cat > resources/js/Pages/V2/Admin/Labels/Index.jsx <<'JSX'
import {
    Head,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    labels = {},
    search = '',
}) {
    return (
        <PanelLayout
            role={role}
            title="Labels"
            subtitle="Assigned label accounts"
        >
            <Head title="Labels" />

            <div className="space-y-5">
                <input
                    type="search"
                    defaultValue={search}
                    placeholder="Search label..."
                    onKeyDown={(event) => {
                        if (
                            event.key === 'Enter'
                        ) {
                            router.get(
                                '/v2/admin/labels',
                                {
                                    search:
                                        event
                                            .currentTarget
                                            .value,
                                }
                            );
                        }
                    }}
                    className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 md:max-w-md"
                />

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'Label',
                                    'Type',
                                    'Status',
                                    'Artists',
                                    'Releases',
                                    'Assigned Admins',
                                ].map((heading) => (
                                    <th
                                        key={heading}
                                        className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {(labels.data ?? []).map(
                                (label) => (
                                    <tr key={label.id}>
                                        <Cell>
                                            <div className="font-semibold text-slate-900">
                                                {label.name}
                                            </div>

                                            <div className="text-xs text-slate-500">
                                                {label.email}
                                            </div>
                                        </Cell>

                                        <Cell>
                                            {label.label_type}
                                        </Cell>

                                        <Cell>
                                            {label.status}
                                        </Cell>

                                        <Cell>
                                            {
                                                label.artists_count
                                            }
                                        </Cell>

                                        <Cell>
                                            {
                                                label.releases_count
                                            }
                                        </Cell>

                                        <Cell>
                                            {(
                                                label.assigned_admins ??
                                                []
                                            )
                                                .map(
                                                    (
                                                        admin
                                                    ) =>
                                                        admin.name
                                                )
                                                .join(', ') ||
                                                'Unassigned'}
                                        </Cell>
                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX


echo "[4/5] Adding routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.admin.admins.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/admins',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'admins']
    )
    ->name('v2.admin.admins.index');
""",

    "v2.admin.admins.show": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/admins/{admin}',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'showAdmin']
    )
    ->name('v2.admin.admins.show');
""",

    "v2.admin.admins.assignments": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/admins/{admin}/assignments',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'updateAssignments']
    )
    ->name('v2.admin.admins.assignments');
""",

    "v2.admin.artists.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/artists',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'artists']
    )
    ->name('v2.admin.artists.index');
""",

    "v2.admin.labels.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/labels',
        [\App\Http\Controllers\V2\Admin\AssignmentManagementController::class, 'labels']
    )
    ->name('v2.admin.labels.index');
""",
}

added = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        added += 1

path.write_text(text)

print(f"{added} assignment routes added.")
PY


echo "[5/5] Running checks and build..."

php -l \
app/Http/Controllers/V2/Admin/AssignmentManagementController.php

php -l routes/web.php

npm run build

php artisan optimize:clear

printf '{\n  "module": "AdminAssignmentManagement",\n  "installed": true,\n  "version": "4.5.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/admin-assignment-management-installed.json

echo ""
echo "===== ASSIGNMENT ROUTES ====="

php artisan route:list | grep -E \
"v2/admin/(admins|artists|labels)"

echo ""
echo "=================================================="
echo "ADMIN ASSIGNMENT MANAGEMENT INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/admin-assignment-management-installed.json

echo ""
echo "Open:"
echo "https://admin.mixxtune.com/v2/admin/admins"
echo "https://admin.mixxtune.com/v2/admin/artists"
echo "https://admin.mixxtune.com/v2/admin/labels"

echo ""
echo "Backup:"
echo "$BACKUP"
