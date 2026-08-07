#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/dsp-delivery-ui/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Admin/Delivery \
    v2/runtime/state

echo "=============================================="
echo "INSTALLING DSP DELIVERY MANAGEMENT UI"
echo "=============================================="

for FILE in \
    routes/web.php \
    app/Http/Controllers/V2/Admin/DeliveryManagementController.php \
    resources/js/Pages/V2/Admin/Delivery/Index.jsx \
    resources/js/Pages/V2/Admin/Delivery/Show.jsx \
    resources/js/V2/Shared/Config/panelRoutes.js
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[1/6] Creating Delivery Management Controller..."

cat > app/Http/Controllers/V2/Admin/DeliveryManagementController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\ReleaseStoreDelivery;
use App\Services\V2\DeliveryWorkflowService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryManagementController extends Controller
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
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );

        $status = trim(
            (string) $request->input(
                'status',
                'processing'
            )
        );

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $query = Release::query()
            ->whereNull('deleted_at')
            ->whereIn(
                'status',
                [
                    'approved',
                    'processing',
                    'delivered',
                    'live',
                    'failed',
                    'takedown_requested',
                    'taken_down',
                ]
            )
            ->withCount([
                'tracks',
            ]);

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

            $query->whereIn(
                'artist_id',
                $artistIds
            );
        }

        if ($status !== '') {
            $query->where(
                'status',
                $status
            );
        }

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'primary_artist_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'upc',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'catalog_number',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        $countQuery = Release::query()
            ->whereNull('deleted_at');

        return Inertia::render(
            'V2/Admin/Delivery/Index',
            [
                'role' => $role,

                'filters' => [
                    'status' => $status,
                    'search' => $search,
                ],

                'counts' => [
                    'processing' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'processing'
                            )
                            ->count(),

                    'delivered' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'delivered'
                            )
                            ->count(),

                    'live' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'live'
                            )
                            ->count(),

                    'failed' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'failed'
                            )
                            ->count(),

                    'takedown_requested' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'takedown_requested'
                            )
                            ->count(),
                ],

                'releases' =>
                    $query
                        ->orderByDesc('id')
                        ->paginate(25)
                        ->withQueryString(),
            ]
        );
    }

    public function show(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): Response {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $deliveries =
            $delivery->deliveries(
                $release
            );

        return Inertia::render(
            'V2/Admin/Delivery/Show',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'release' => [
                    'id' =>
                        $release->id,

                    'title' =>
                        $release->title,

                    'primary_artist_name' =>
                        $release
                            ->primary_artist_name,

                    'status' =>
                        $release->status,

                    'upc' =>
                        $release->upc,

                    'catalog_number' =>
                        $release
                            ->catalog_number,
                ],

                'summary' =>
                    $delivery->summary(
                        $release
                    ),

                'deliveries' =>
                    $deliveries,
            ]
        );
    }

    public function initialise(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $delivery->initialise(
            $release,
            $request->user()
        );

        return redirect()
            ->route(
                'v2.admin.delivery-management.show',
                $release
            )
            ->with(
                'success',
                'DSP delivery records created.'
            );
    }

    public function update(
        Request $request,
        ReleaseStoreDelivery $deliveryRecord,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $deliveryRecord->loadMissing(
            'release'
        );

        abort_unless(
            $deliveryRecord->release,
            404,
            'Release not found.'
        );

        $access->authorizeView(
            $request->user(),
            $deliveryRecord->release
        );

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:processing,delivered,live,failed,takedown_requested,taken_down',
            ],

            'delivery_note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'error_message' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'external_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $delivery->transition(
            $deliveryRecord,
            $validated['status'],
            $request->user(),
            $validated
        );

        return back()->with(
            'success',
            'DSP delivery status updated.'
        );
    }

    public function bulkUpdate(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $validated = $request->validate([
            'delivery_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'delivery_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:release_store_deliveries,id',
            ],

            'status' => [
                'required',
                'string',
                'in:processing,delivered,live,failed,takedown_requested,taken_down',
            ],

            'delivery_note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'error_message' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'external_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $delivery->bulkTransition(
            $release,
            $validated['delivery_ids'],
            $validated['status'],
            $request->user(),
            $validated
        );

        return back()->with(
            'success',
            'Selected DSP deliveries updated.'
        );
    }

    private function authorizeAdmin(
        Request $request,
        PermissionService $permissions
    ): void {
        $role = $permissions->role(
            $request->user()
        );

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
    }
}
PHP


echo "[2/6] Creating Delivery Queue Page..."

cat > resources/js/Pages/V2/Admin/Delivery/Index.jsx <<'JSX'
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusClasses = {
    processing:
        'bg-blue-100 text-blue-700',

    delivered:
        'bg-indigo-100 text-indigo-700',

    live:
        'bg-emerald-100 text-emerald-700',

    failed:
        'bg-red-100 text-red-700',

    takedown_requested:
        'bg-amber-100 text-amber-700',
};

export default function Index({
    role = 'admin',
    releases = {},
    counts = {},
    filters = {},
}) {
    const rows =
        releases.data ?? [];

    const openStatus = (
        status
    ) => {
        router.get(
            '/v2/admin/distribution',
            {
                status,
                search:
                    filters.search ?? '',
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="DSP Distribution"
            subtitle="Manage store delivery and live status"
        >
            <Head title="DSP Distribution" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        [
                            'processing',
                            'Processing',
                        ],
                        [
                            'delivered',
                            'Delivered',
                        ],
                        [
                            'live',
                            'Live',
                        ],
                        [
                            'failed',
                            'Failed',
                        ],
                        [
                            'takedown_requested',
                            'Takedown',
                        ],
                    ].map(
                        ([status, label]) => (
                            <button
                                key={status}
                                type="button"
                                onClick={() =>
                                    openStatus(
                                        status
                                    )
                                }
                                className={[
                                    'rounded-2xl border bg-white p-5 text-left shadow-sm transition',
                                    filters.status ===
                                    status
                                        ? 'border-violet-500 ring-2 ring-violet-100'
                                        : 'border-slate-200 hover:border-violet-300',
                                ].join(' ')}
                            >
                                <div className="text-sm text-slate-500">
                                    {label}
                                </div>

                                <div className="mt-2 text-3xl font-bold text-slate-900">
                                    {counts[
                                        status
                                    ] ?? 0}
                                </div>
                            </button>
                        )
                    )}
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-3 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Distribution Queue
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Open a release to update
                                individual DSP statuses.
                            </p>
                        </div>

                        <input
                            type="search"
                            defaultValue={
                                filters.search ??
                                ''
                            }
                            placeholder="Search release, artist, UPC..."
                            onKeyDown={(
                                event
                            ) => {
                                if (
                                    event.key ===
                                    'Enter'
                                ) {
                                    router.get(
                                        '/v2/admin/distribution',
                                        {
                                            ...filters,
                                            search:
                                                event
                                                    .currentTarget
                                                    .value,
                                        },
                                        {
                                            preserveState:
                                                true,
                                        }
                                    );
                                }
                            }}
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500 lg:w-80"
                        />
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'Release',
                                        'Artist',
                                        'UPC',
                                        'Tracks',
                                        'Status',
                                        'Action',
                                    ].map(
                                        (
                                            heading
                                        ) => (
                                            <th
                                                key={
                                                    heading
                                                }
                                                className="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                                            >
                                                {
                                                    heading
                                                }
                                            </th>
                                        )
                                    )}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.length >
                                0 ? (
                                    rows.map(
                                        (
                                            release
                                        ) => (
                                            <tr
                                                key={
                                                    release.id
                                                }
                                            >
                                                <td className="px-5 py-4">
                                                    <div className="font-semibold text-slate-900">
                                                        {
                                                            release.title
                                                        }
                                                    </div>

                                                    <div className="mt-1 text-xs text-slate-500">
                                                        {release.catalog_number ||
                                                            'No catalogue number'}
                                                    </div>
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {release.primary_artist_name ||
                                                        '—'}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {release.upc ||
                                                        'Pending'}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {release.tracks_count ??
                                                        0}
                                                </td>

                                                <td className="px-5 py-4">
                                                    <span
                                                        className={[
                                                            'rounded-full px-3 py-1 text-xs font-semibold capitalize',
                                                            statusClasses[
                                                                release
                                                                    .status
                                                            ] ??
                                                                'bg-slate-100 text-slate-700',
                                                        ].join(
                                                            ' '
                                                        )}
                                                    >
                                                        {release.status.replaceAll(
                                                            '_',
                                                            ' '
                                                        )}
                                                    </span>
                                                </td>

                                                <td className="px-5 py-4">
                                                    <Link
                                                        href={`/v2/admin/distribution/${release.id}`}
                                                        className="rounded-lg border border-violet-300 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"
                                                    >
                                                        Manage DSPs
                                                    </Link>
                                                </td>
                                            </tr>
                                        )
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            No distribution
                                            releases found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
JSX


echo "[3/6] Creating Delivery Detail Page..."

cat > resources/js/Pages/V2/Admin/Delivery/Show.jsx <<'JSX'
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import {
    useMemo,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statuses = [
    'processing',
    'delivered',
    'live',
    'failed',
    'takedown_requested',
    'taken_down',
];

const statusClasses = {
    pending:
        'bg-slate-100 text-slate-700',

    processing:
        'bg-blue-100 text-blue-700',

    delivered:
        'bg-indigo-100 text-indigo-700',

    live:
        'bg-emerald-100 text-emerald-700',

    failed:
        'bg-red-100 text-red-700',

    takedown_requested:
        'bg-amber-100 text-amber-700',

    taken_down:
        'bg-slate-800 text-white',
};

export default function Show({
    role = 'admin',
    release,
    deliveries = [],
    summary = {},
}) {
    const [
        selected,
        setSelected,
    ] = useState([]);

    const [
        bulkStatus,
        setBulkStatus,
    ] = useState('processing');

    const [
        bulkNote,
        setBulkNote,
    ] = useState('');

    const [
        bulkError,
        setBulkError,
    ] = useState('');

    const [
        processing,
        setProcessing,
    ] = useState(false);

    const allSelected =
        deliveries.length > 0 &&
        selected.length ===
            deliveries.length;

    const toggleAll = () => {
        setSelected(
            allSelected
                ? []
                : deliveries.map(
                      (item) =>
                          item.id
                  )
        );
    };

    const toggleOne = (id) => {
        setSelected(
            (current) =>
                current.includes(id)
                    ? current.filter(
                          (item) =>
                              item !== id
                      )
                    : [
                          ...current,
                          id,
                      ]
        );
    };

    const bulkUpdate = () => {
        if (
            selected.length === 0
        ) {
            return;
        }

        setProcessing(true);

        router.patch(
            `/v2/admin/distribution/${release.id}/bulk`,
            {
                delivery_ids:
                    selected,

                status:
                    bulkStatus,

                delivery_note:
                    bulkNote,

                error_message:
                    bulkStatus ===
                    'failed'
                        ? bulkError
                        : null,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setSelected([]);
                    setBulkNote('');
                    setBulkError('');
                },

                onFinish: () =>
                    setProcessing(
                        false
                    ),
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="DSP Delivery"
            subtitle={release.title}
        >
            <Head
                title={`DSP Delivery - ${release.title}`}
            />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/v2/admin/distribution"
                        className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                    >
                        ← Distribution Queue
                    </Link>

                    <span className="rounded-full bg-blue-100 px-4 py-2 text-sm font-semibold capitalize text-blue-700">
                        {release.status.replaceAll(
                            '_',
                            ' '
                        )}
                    </span>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    {[
                        'total',
                        'pending',
                        'processing',
                        'delivered',
                        'live',
                        'failed',
                    ].map(
                        (status) => (
                            <div
                                key={status}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div className="text-xs font-semibold uppercase text-slate-500">
                                    {status}
                                </div>

                                <div className="mt-2 text-3xl font-bold text-slate-900">
                                    {summary[
                                        status
                                    ] ?? 0}
                                </div>
                            </div>
                        )
                    )}
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Bulk Update
                    </h2>

                    <div className="mt-4 grid gap-3 lg:grid-cols-4">
                        <select
                            value={
                                bulkStatus
                            }
                            onChange={(
                                event
                            ) =>
                                setBulkStatus(
                                    event.target
                                        .value
                                )
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            {statuses.map(
                                (
                                    status
                                ) => (
                                    <option
                                        key={
                                            status
                                        }
                                        value={
                                            status
                                        }
                                    >
                                        {status.replaceAll(
                                            '_',
                                            ' '
                                        )}
                                    </option>
                                )
                            )}
                        </select>

                        <input
                            value={bulkNote}
                            onChange={(
                                event
                            ) =>
                                setBulkNote(
                                    event.target
                                        .value
                                )
                            }
                            placeholder="Delivery note"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <input
                            value={
                                bulkError
                            }
                            onChange={(
                                event
                            ) =>
                                setBulkError(
                                    event.target
                                        .value
                                )
                            }
                            disabled={
                                bulkStatus !==
                                'failed'
                            }
                            placeholder="Failure reason"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm disabled:bg-slate-100"
                        />

                        <button
                            type="button"
                            disabled={
                                processing ||
                                selected.length ===
                                    0 ||
                                (
                                    bulkStatus ===
                                    'failed' &&
                                    bulkError
                                        .trim()
                                        .length < 3
                                )
                            }
                            onClick={
                                bulkUpdate
                            }
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing
                                ? 'Updating...'
                                : `Update ${selected.length} Selected`}
                        </button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="text-lg font-semibold text-slate-900">
                            Store Deliveries
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-5 py-4 text-left">
                                        <input
                                            type="checkbox"
                                            checked={
                                                allSelected
                                            }
                                            onChange={
                                                toggleAll
                                            }
                                        />
                                    </th>

                                    {[
                                        'Store',
                                        'Status',
                                        'Reference',
                                        'Note',
                                        'Error',
                                        'Updated',
                                    ].map(
                                        (
                                            heading
                                        ) => (
                                            <th
                                                key={
                                                    heading
                                                }
                                                className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                            >
                                                {
                                                    heading
                                                }
                                            </th>
                                        )
                                    )}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {deliveries.length >
                                0 ? (
                                    deliveries.map(
                                        (
                                            delivery
                                        ) => (
                                            <tr
                                                key={
                                                    delivery.id
                                                }
                                            >
                                                <td className="px-5 py-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selected.includes(
                                                            delivery.id
                                                        )}
                                                        onChange={() =>
                                                            toggleOne(
                                                                delivery.id
                                                            )
                                                        }
                                                    />
                                                </td>

                                                <td className="px-5 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                                                            {delivery
                                                                .store
                                                                ?.logo_path ? (
                                                                <img
                                                                    src={
                                                                        delivery.store.logo_path.startsWith(
                                                                            'http'
                                                                        )
                                                                            ? delivery
                                                                                  .store
                                                                                  .logo_path
                                                                            : `/storage/${delivery.store.logo_path}`
                                                                    }
                                                                    alt={
                                                                        delivery
                                                                            .store
                                                                            ?.name
                                                                    }
                                                                    className="h-full w-full object-contain p-1"
                                                                />
                                                            ) : (
                                                                <span className="font-bold text-slate-500">
                                                                    {delivery
                                                                        .store
                                                                        ?.name
                                                                        ?.charAt(
                                                                            0
                                                                        ) ??
                                                                        '?'}
                                                                </span>
                                                            )}
                                                        </div>

                                                        <span className="font-semibold text-slate-900">
                                                            {delivery
                                                                .store
                                                                ?.name ||
                                                                'Unknown Store'}
                                                        </span>
                                                    </div>
                                                </td>

                                                <td className="px-5 py-4">
                                                    <span
                                                        className={[
                                                            'rounded-full px-3 py-1 text-xs font-semibold capitalize',
                                                            statusClasses[
                                                                delivery
                                                                    .status
                                                            ] ??
                                                                statusClasses.pending,
                                                        ].join(
                                                            ' '
                                                        )}
                                                    >
                                                        {delivery.status.replaceAll(
                                                            '_',
                                                            ' '
                                                        )}
                                                    </span>
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {delivery.external_reference ||
                                                        '—'}
                                                </td>

                                                <td className="max-w-64 px-5 py-4 text-sm text-slate-600">
                                                    {delivery.delivery_note ||
                                                        '—'}
                                                </td>

                                                <td className="max-w-64 px-5 py-4 text-sm text-red-600">
                                                    {delivery.error_message ||
                                                        '—'}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-500">
                                                    {delivery.updated_at ||
                                                        '—'}
                                                </td>
                                            </tr>
                                        )
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            DSP deliveries
                                            have not been
                                            initialized.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
JSX


echo "[4/6] Adding DSP Delivery UI Routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.admin.delivery-management.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/distribution',
        [\App\Http\Controllers\V2\Admin\DeliveryManagementController::class, 'index']
    )
    ->name('v2.admin.delivery-management.index');
""",

    "v2.admin.delivery-management.show": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/distribution/{release}',
        [\App\Http\Controllers\V2\Admin\DeliveryManagementController::class, 'show']
    )
    ->name('v2.admin.delivery-management.show');
""",

    "v2.admin.delivery-management.initialise": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/distribution/{release}/initialise',
        [\App\Http\Controllers\V2\Admin\DeliveryManagementController::class, 'initialise']
    )
    ->name('v2.admin.delivery-management.initialise');
""",

    "v2.admin.delivery-management.update": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/distribution/deliveries/{deliveryRecord}',
        [\App\Http\Controllers\V2\Admin\DeliveryManagementController::class, 'update']
    )
    ->name('v2.admin.delivery-management.update');
""",

    "v2.admin.delivery-management.bulk": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/distribution/{release}/bulk',
        [\App\Http\Controllers\V2\Admin\DeliveryManagementController::class, 'bulkUpdate']
    )
    ->name('v2.admin.delivery-management.bulk');
""",
}

count = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        count += 1

path.write_text(text)

print(f"{count} DSP Delivery UI routes added.")
PY


echo "[5/6] Updating Processing UI delivery link..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "resources/js/Pages/V2/Admin/Processing/Show.jsx"
)

if path.exists():
    text = path.read_text()

    text = text.replace(
        "`/v2/admin/releases/${release.id}/delivery`",
        "`/v2/admin/distribution/${release.id}`"
    )

    path.write_text(text)

    print("Processing delivery link updated.")
else:
    print("Processing Show.jsx not found; skipped.")
PY


echo "[6/6] Running checks and build..."

php -l \
app/Http/Controllers/V2/Admin/DeliveryManagementController.php

php -l routes/web.php

npm run build

php artisan optimize:clear

printf '{\n  "module": "DspDeliveryUI",\n  "installed": true,\n  "version": "3.8.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/dsp-delivery-ui-installed.json

echo ""
echo "===== DSP DELIVERY ROUTES ====="

php artisan route:list | grep \
"v2/admin/distribution"

echo ""
echo "=============================================="
echo "DSP DELIVERY UI INSTALLED"
echo "=============================================="

cat \
v2/runtime/state/dsp-delivery-ui-installed.json

echo ""
echo "Open:"
echo "https://admin.mixxtune.com/v2/admin/distribution"

echo ""
echo "Backup:"
echo "$BACKUP"
