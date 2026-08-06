#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/processing-ui/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Admin/Processing \
    v2/runtime/state

echo "=============================================="
echo "INSTALLING V2 PROCESSING ENGINE UI"
echo "=============================================="

for FILE in \
    routes/web.php \
    app/Http/Controllers/V2/Admin/ProcessingController.php \
    resources/js/Pages/V2/Admin/Processing/Index.jsx \
    resources/js/Pages/V2/Admin/Processing/Show.jsx
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[1/6] Creating Processing Controller..."

cat > app/Http/Controllers/V2/Admin/ProcessingController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\AudioValidationService;
use App\Services\V2\DeliveryWorkflowService;
use App\Services\V2\IsrcService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseValidationService;
use App\Services\V2\UpcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProcessingController extends Controller
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
                'approved'
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
            ->withCount('tracks')
            ->whereIn(
                'status',
                [
                    'approved',
                    'processing',
                    'delivered',
                    'failed',
                ]
            );

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
            'V2/Admin/Processing/Index',
            [
                'role' => $role,

                'filters' => [
                    'status' => $status,
                    'search' => $search,
                ],

                'counts' => [
                    'approved' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'approved'
                            )
                            ->count(),

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

                    'failed' =>
                        (clone $countQuery)
                            ->where(
                                'status',
                                'failed'
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
        ReleaseValidationService $validator,
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

        $release->load([
            'tracks' => function ($query) {
                $query
                    ->orderBy('disc_number')
                    ->orderBy('track_number')
                    ->orderBy('id');
            },
        ]);

        $tracks = $release->tracks;

        $audioPassed = $tracks->isNotEmpty()
            && $tracks->every(
                fn ($track) =>
                    $track->audio_validation_status
                    === 'passed'
            );

        $allIsrcAssigned = $tracks->isNotEmpty()
            && $tracks->every(
                fn ($track) =>
                    filled($track->isrc)
            );

        $submissionChecklist =
            $validator->checklist($release);

        $deliverySummary =
            $delivery->summary($release);

        return Inertia::render(
            'V2/Admin/Processing/Show',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'release' => $release,

                'checks' => [
                    'metadata' =>
                        (bool) (
                            $submissionChecklist[
                                'checks'
                            ]['metadata']
                            ?? false
                        ),

                    'artists' =>
                        (bool) (
                            $submissionChecklist[
                                'checks'
                            ]['artists']
                            ?? false
                        ),

                    'tracks' =>
                        (bool) (
                            $submissionChecklist[
                                'checks'
                            ]['tracks']
                            ?? false
                        ),

                    'distribution' =>
                        (bool) (
                            $submissionChecklist[
                                'checks'
                            ]['distribution']
                            ?? false
                        ),

                    'audio' =>
                        $audioPassed,

                    'isrc' =>
                        $allIsrcAssigned,

                    'upc' =>
                        filled($release->upc),

                    'delivery_initialised' =>
                        ($deliverySummary['total']
                            ?? 0) > 0,
                ],

                'validationErrors' =>
                    $submissionChecklist[
                        'errors'
                    ] ?? [],

                'deliverySummary' =>
                    $deliverySummary,
            ]
        );
    }

    public function generateIdentifiers(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        IsrcService $isrc,
        UpcService $upc
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $release->load('tracks');

        $isrcCount = 0;
        $upcCreated = false;

        foreach ($release->tracks as $track) {
            if (!$track->isrc) {
                $isrc->generate(
                    $track,
                    $request->user()
                );

                $isrcCount++;
            }
        }

        if (!$release->upc) {
            $upc->generate(
                $release,
                $request->user()
            );

            $upcCreated = true;
        }

        return back()->with(
            'success',
            "{$isrcCount} ISRC generated. "
            . (
                $upcCreated
                    ? 'UPC generated.'
                    : 'UPC already available.'
            )
        );
    }

    public function validateAudio(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        AudioValidationService $validator
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $tracks = $release
            ->tracks()
            ->whereNotNull('audio_path')
            ->get();

        abort_if(
            $tracks->isEmpty(),
            422,
            'No uploaded audio found.'
        );

        $result = $validator->validateMany(
            $tracks,
            $request->user()
        );

        return back()->with(
            $result['failed'] > 0
                ? 'warning'
                : 'success',
            "Audio validation completed. "
            . "Passed: {$result['passed']}, "
            . "Failed: {$result['failed']}."
        );
    }

    public function initialiseDelivery(
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

        abort_unless(
            in_array(
                $release->status,
                [
                    'approved',
                    'processing',
                ],
                true
            ),
            422,
            'Only approved or processing releases can start delivery.'
        );

        $delivery->initialise(
            $release,
            $request->user()
        );

        return redirect()
            ->route(
                'v2.admin.delivery.index',
                $release
            )
            ->with(
                'success',
                'DSP delivery records initialised.'
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


echo "[2/6] Creating Processing Queue Page..."

cat > resources/js/Pages/V2/Admin/Processing/Index.jsx <<'JSX'
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusClasses = {
    approved:
        'bg-emerald-100 text-emerald-700',

    processing:
        'bg-blue-100 text-blue-700',

    delivered:
        'bg-indigo-100 text-indigo-700',

    failed:
        'bg-red-100 text-red-700',
};

export default function Index({
    role = 'admin',
    releases = {},
    counts = {},
    filters = {},
}) {
    const rows = releases.data ?? [];

    const openStatus = (status) => {
        router.get(
            '/v2/admin/processing',
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
            title="Release Processing"
            subtitle="Prepare approved releases for DSP delivery"
        >
            <Head title="Release Processing" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        [
                            'approved',
                            'Approved',
                        ],
                        [
                            'processing',
                            'Processing',
                        ],
                        [
                            'delivered',
                            'Delivered',
                        ],
                        [
                            'failed',
                            'Failed',
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
                                Processing Queue
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Generate identifiers,
                                validate audio and prepare
                                DSP delivery.
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
                                        '/v2/admin/processing',
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
                                                        href={`/v2/admin/processing/${release.id}`}
                                                        className="rounded-lg border border-violet-300 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"
                                                    >
                                                        Process
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
                                            No releases
                                            found.
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


echo "[3/6] Creating Processing Detail Page..."

cat > resources/js/Pages/V2/Admin/Processing/Show.jsx <<'JSX'
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import {
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'admin',
    release,
    checks = {},
    validationErrors = {},
    deliverySummary = {},
}) {
    const [
        processing,
        setProcessing,
    ] = useState('');

    const runAction = (
        name,
        endpoint
    ) => {
        setProcessing(name);

        router.post(
            endpoint,
            {},
            {
                preserveScroll: true,

                onFinish: () =>
                    setProcessing(''),
            }
        );
    };

    const checklist = [
        [
            'Metadata',
            checks.metadata,
        ],
        [
            'Artists',
            checks.artists,
        ],
        [
            'Tracks',
            checks.tracks,
        ],
        [
            'Stores & Territory',
            checks.distribution,
        ],
        [
            'Audio Validation',
            checks.audio,
        ],
        [
            'ISRC Assigned',
            checks.isrc,
        ],
        [
            'UPC Assigned',
            checks.upc,
        ],
        [
            'DSP Delivery Created',
            checks.delivery_initialised,
        ],
    ];

    return (
        <PanelLayout
            role={role}
            title="Process Release"
            subtitle={release.title}
        >
            <Head
                title={`Process ${release.title}`}
            />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/v2/admin/processing"
                        className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                    >
                        ← Processing Queue
                    </Link>

                    <span className="rounded-full bg-blue-100 px-4 py-2 text-sm font-semibold capitalize text-blue-700">
                        {release.status.replaceAll(
                            '_',
                            ' '
                        )}
                    </span>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                    <div className="space-y-6">
                        <Section title="Processing Checklist">
                            <div className="grid gap-3 sm:grid-cols-2">
                                {checklist.map(
                                    ([
                                        label,
                                        passed,
                                    ]) => (
                                        <div
                                            key={
                                                label
                                            }
                                            className={[
                                                'flex items-center justify-between rounded-xl border p-4',
                                                passed
                                                    ? 'border-emerald-200 bg-emerald-50'
                                                    : 'border-amber-200 bg-amber-50',
                                            ].join(
                                                ' '
                                            )}
                                        >
                                            <span className="text-sm font-semibold text-slate-800">
                                                {
                                                    label
                                                }
                                            </span>

                                            <span
                                                className={[
                                                    'flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold text-white',
                                                    passed
                                                        ? 'bg-emerald-600'
                                                        : 'bg-amber-500',
                                                ].join(
                                                    ' '
                                                )}
                                            >
                                                {passed
                                                    ? '✓'
                                                    : '!'}
                                            </span>
                                        </div>
                                    )
                                )}
                            </div>
                        </Section>

                        <Section title="Tracks">
                            <div className="space-y-3">
                                {(release.tracks ??
                                    []).map(
                                    (
                                        track,
                                        index
                                    ) => (
                                        <div
                                            key={
                                                track.id
                                            }
                                            className="rounded-xl border border-slate-200 p-4"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <div className="font-semibold text-slate-900">
                                                        {index +
                                                            1}
                                                        .{' '}
                                                        {
                                                            track.title
                                                        }
                                                    </div>

                                                    <div className="mt-1 text-xs text-slate-500">
                                                        ISRC:{' '}
                                                        {track.isrc ||
                                                            'Pending'}
                                                    </div>
                                                </div>

                                                <span
                                                    className={[
                                                        'rounded-full px-3 py-1 text-xs font-semibold capitalize',
                                                        track.audio_validation_status ===
                                                        'passed'
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : track.audio_validation_status ===
                                                                'failed'
                                                              ? 'bg-red-100 text-red-700'
                                                              : 'bg-amber-100 text-amber-700',
                                                    ].join(
                                                        ' '
                                                    )}
                                                >
                                                    Audio:{' '}
                                                    {track.audio_validation_status ||
                                                        'pending'}
                                                </span>
                                            </div>
                                        </div>
                                    )
                                )}
                            </div>
                        </Section>

                        {Object.keys(
                            validationErrors
                        ).length > 0 && (
                            <Section title="Validation Errors">
                                <div className="space-y-2">
                                    {Object.entries(
                                        validationErrors
                                    ).map(
                                        ([
                                            field,
                                            message,
                                        ]) => (
                                            <div
                                                key={
                                                    field
                                                }
                                                className="rounded-xl border border-red-200 bg-red-50 p-4"
                                            >
                                                <div className="text-xs font-semibold uppercase text-red-600">
                                                    {
                                                        field
                                                    }
                                                </div>

                                                <div className="mt-1 text-sm text-red-700">
                                                    {
                                                        message
                                                    }
                                                </div>
                                            </div>
                                        )
                                    )}
                                </div>
                            </Section>
                        )}

                        <Section title="DSP Delivery Summary">
                            <div className="grid gap-3 sm:grid-cols-3">
                                {[
                                    'total',
                                    'pending',
                                    'processing',
                                    'delivered',
                                    'live',
                                    'failed',
                                ].map(
                                    (
                                        status
                                    ) => (
                                        <div
                                            key={
                                                status
                                            }
                                            className="rounded-xl bg-slate-50 p-4"
                                        >
                                            <div className="text-xs font-semibold uppercase text-slate-500">
                                                {
                                                    status
                                                }
                                            </div>

                                            <div className="mt-2 text-2xl font-bold text-slate-900">
                                                {deliverySummary[
                                                    status
                                                ] ??
                                                    0}
                                            </div>
                                        </div>
                                    )
                                )}
                            </div>
                        </Section>
                    </div>

                    <aside>
                        <div className="sticky top-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 className="font-semibold text-slate-900">
                                Processing Actions
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                Complete each step before
                                starting DSP delivery.
                            </p>

                            <div className="mt-5 space-y-3">
                                <Action
                                    label="Generate ISRC & UPC"
                                    loading={
                                        processing ===
                                        'identifiers'
                                    }
                                    onClick={() =>
                                        runAction(
                                            'identifiers',
                                            `/v2/admin/processing/${release.id}/generate-identifiers`
                                        )
                                    }
                                />

                                <Action
                                    label="Validate All Audio"
                                    loading={
                                        processing ===
                                        'audio'
                                    }
                                    onClick={() =>
                                        runAction(
                                            'audio',
                                            `/v2/admin/processing/${release.id}/validate-audio`
                                        )
                                    }
                                />

                                <Action
                                    label={
                                        checks.delivery_initialised
                                            ? 'Open DSP Delivery'
                                            : 'Initialize DSP Delivery'
                                    }
                                    loading={
                                        processing ===
                                        'delivery'
                                    }
                                    className="bg-emerald-600 hover:bg-emerald-700"
                                    onClick={() => {
                                        if (
                                            checks.delivery_initialised
                                        ) {
                                            window.location.href =
                                                `/v2/admin/releases/${release.id}/delivery`;

                                            return;
                                        }

                                        runAction(
                                            'delivery',
                                            `/v2/admin/processing/${release.id}/initialise-delivery`
                                        );
                                    }}
                                />
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </PanelLayout>
    );
}

function Section({
    title,
    children,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="mb-4 text-lg font-semibold text-slate-900">
                {title}
            </h2>

            {children}
        </section>
    );
}

function Action({
    label,
    loading,
    onClick,
    className =
        'bg-violet-600 hover:bg-violet-700',
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={loading}
            className={[
                'w-full rounded-xl px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50',
                className,
            ].join(' ')}
        >
            {loading
                ? 'Processing...'
                : label}
        </button>
    );
}
JSX


echo "[4/6] Adding Processing Routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.admin.processing.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/processing',
        [\App\Http\Controllers\V2\Admin\ProcessingController::class, 'index']
    )
    ->name('v2.admin.processing.index');
""",

    "v2.admin.processing.show": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/processing/{release}',
        [\App\Http\Controllers\V2\Admin\ProcessingController::class, 'show']
    )
    ->name('v2.admin.processing.show');
""",

    "v2.admin.processing.identifiers": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/processing/{release}/generate-identifiers',
        [\App\Http\Controllers\V2\Admin\ProcessingController::class, 'generateIdentifiers']
    )
    ->name('v2.admin.processing.identifiers');
""",

    "v2.admin.processing.audio": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/processing/{release}/validate-audio',
        [\App\Http\Controllers\V2\Admin\ProcessingController::class, 'validateAudio']
    )
    ->name('v2.admin.processing.audio');
""",

    "v2.admin.processing.delivery": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/processing/{release}/initialise-delivery',
        [\App\Http\Controllers\V2\Admin\ProcessingController::class, 'initialiseDelivery']
    )
    ->name('v2.admin.processing.delivery');
""",
}

count = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        count += 1

path.write_text(text)

print(f"{count} processing routes added.")
PY


echo "[5/6] Running checks and build..."

php -l \
app/Http/Controllers/V2/Admin/ProcessingController.php

php -l routes/web.php

npm run build

php artisan optimize:clear


echo "[6/6] Saving installation state..."

printf '{\n  "module": "ProcessingUI",\n  "installed": true,\n  "version": "3.7.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/processing-ui-installed.json

echo ""
echo "===== PROCESSING ROUTES ====="

php artisan route:list | grep \
"v2/admin/processing"

echo ""
echo "=============================================="
echo "PROCESSING UI INSTALLED"
echo "=============================================="

cat \
v2/runtime/state/processing-ui-installed.json

echo ""
echo "Open:"
echo "https://admin.mixxtune.com/v2/admin/processing"

echo ""
echo "Backup:"
echo "$BACKUP"
