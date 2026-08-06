#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/catalogue-ui/$STAMP"

mkdir -p \
    "$BACKUP" \
    resources/js/Pages/V2/Catalogue \
    v2/runtime/state

CONTROLLER="app/Http/Controllers/V2/CatalogueController.php"

echo "=============================================="
echo "INSTALLING V2 CATALOGUE UI"
echo "=============================================="

for FILE in \
    "$CONTROLLER" \
    routes/web.php \
    resources/js/Pages/V2/Catalogue/Index.jsx \
    resources/js/Pages/V2/Catalogue/Show.jsx
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[1/5] Creating Catalogue Controller..."

cat > "$CONTROLLER" <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\CatalogueItem;
use App\Services\V2\CatalogueService;
use App\Services\V2\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogueController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        CatalogueService $catalogue
    ): Response|JsonResponse {
        $permissions->authorize(
            $request->user(),
            'catalogue.view'
        );

        $filters = [
            'search' => trim(
                (string) $request->input(
                    'search',
                    ''
                )
            ),

            'status' => trim(
                (string) $request->input(
                    'status',
                    ''
                )
            ),

            'visibility' => trim(
                (string) $request->input(
                    'visibility',
                    ''
                )
            ),

            'sort' => trim(
                (string) $request->input(
                    'sort',
                    'latest'
                )
            ),
        ];

        $baseQuery = fn () =>
            $catalogue->scopedQuery(
                $request->user(),
                $permissions
            );

        $query = $baseQuery();

        if ($filters['search'] !== '') {
            $search = $filters['search'];

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
                            'label_name',
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
                        )
                        ->orWhereHas(
                            'release.tracks',
                            function ($trackQuery) use ($search) {
                                $trackQuery
                                    ->where(
                                        'title',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'isrc',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                }
            );
        }

        if ($filters['status'] !== '') {
            $query->where(
                'release_status',
                $filters['status']
            );
        }

        if (
            $filters['visibility']
            === 'visible'
        ) {
            $query->where(
                'is_visible',
                true
            );
        }

        if (
            $filters['visibility']
            === 'hidden'
        ) {
            $query->where(
                'is_visible',
                false
            );
        }

        match ($filters['sort']) {
            'oldest' =>
                $query->orderBy('id'),

            'title_asc' =>
                $query->orderBy('title'),

            'title_desc' =>
                $query->orderByDesc('title'),

            'release_date' =>
                $query->orderByDesc(
                    'digital_release_date'
                ),

            default =>
                $query->orderByDesc('id'),
        };

        $counts = [
            'total' =>
                $baseQuery()->count(),

            'visible' =>
                $baseQuery()
                    ->where(
                        'is_visible',
                        true
                    )
                    ->count(),

            'approved' =>
                $baseQuery()
                    ->where(
                        'release_status',
                        'approved'
                    )
                    ->count(),

            'processing' =>
                $baseQuery()
                    ->where(
                        'release_status',
                        'processing'
                    )
                    ->count(),

            'delivered' =>
                $baseQuery()
                    ->where(
                        'release_status',
                        'delivered'
                    )
                    ->count(),

            'live' =>
                $baseQuery()
                    ->where(
                        'release_status',
                        'live'
                    )
                    ->count(),
        ];

        $items = $query
            ->paginate(24)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'filters' => $filters,
                'counts' => $counts,
                'catalogue' => $items,
            ]);
        }

        return Inertia::render(
            'V2/Catalogue/Index',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'filters' => $filters,
                'counts' => $counts,
                'catalogue' => $items,
            ]
        );
    }

    public function show(
        Request $request,
        CatalogueItem $catalogueItem,
        PermissionService $permissions,
        CatalogueService $catalogue
    ): Response|JsonResponse {
        $permissions->authorize(
            $request->user(),
            'catalogue.view'
        );

        $allowed = $catalogue
            ->scopedQuery(
                $request->user(),
                $permissions
            )
            ->where(
                'catalogue_items.id',
                $catalogueItem->id
            )
            ->exists();

        abort_unless(
            $allowed,
            403,
            'You cannot access this catalogue item.'
        );

        $catalogueItem->load([
            'release.tracks' => function ($query) {
                $query
                    ->orderBy('disc_number')
                    ->orderBy('track_number')
                    ->orderBy('id');
            },
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'catalogue_item' =>
                    $catalogueItem,
            ]);
        }

        return Inertia::render(
            'V2/Catalogue/Show',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'catalogueItem' =>
                    $catalogueItem,
            ]
        );
    }
}
PHP


echo "[2/5] Creating Catalogue Index Page..."

cat > resources/js/Pages/V2/Catalogue/Index.jsx <<'JSX'
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

    live:
        'bg-green-100 text-green-700',

    takedown_requested:
        'bg-amber-100 text-amber-700',

    taken_down:
        'bg-slate-200 text-slate-700',
};

export default function Index({
    role = 'artist',
    catalogue = {},
    counts = {},
    filters = {},
}) {
    const rows =
        catalogue.data ?? [];

    const updateFilter = (
        changes
    ) => {
        router.get(
            '/v2/catalogue',
            {
                ...filters,
                ...changes,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const title =
        role === 'artist'
            ? 'My Catalogue'
            : role === 'label'
              ? 'Label Catalogue'
              : 'Catalogue';

    return (
        <PanelLayout
            role={role}
            title={title}
            subtitle="Released music, identifiers and DSP delivery status"
        >
            <Head title={title} />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    {[
                        ['total', 'Total'],
                        ['visible', 'Visible'],
                        ['approved', 'Approved'],
                        ['processing', 'Processing'],
                        ['delivered', 'Delivered'],
                        ['live', 'Live'],
                    ].map(
                        ([status, label]) => (
                            <button
                                key={status}
                                type="button"
                                onClick={() =>
                                    updateFilter({
                                        status:
                                            status ===
                                                'total' ||
                                            status ===
                                                'visible'
                                                ? ''
                                                : status,

                                        visibility:
                                            status ===
                                            'visible'
                                                ? 'visible'
                                                : '',
                                    })
                                }
                                className="rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-violet-300"
                            >
                                <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
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

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="grid gap-3 lg:grid-cols-[1fr_180px_180px_auto]">
                        <input
                            type="search"
                            defaultValue={
                                filters.search ??
                                ''
                            }
                            placeholder="Search release, artist, UPC, ISRC..."
                            onKeyDown={(
                                event
                            ) => {
                                if (
                                    event.key ===
                                    'Enter'
                                ) {
                                    updateFilter({
                                        search:
                                            event
                                                .currentTarget
                                                .value,
                                    });
                                }
                            }}
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500"
                        />

                        <select
                            value={
                                filters.status ??
                                ''
                            }
                            onChange={(
                                event
                            ) =>
                                updateFilter({
                                    status:
                                        event
                                            .target
                                            .value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">
                                All Statuses
                            </option>

                            <option value="approved">
                                Approved
                            </option>

                            <option value="processing">
                                Processing
                            </option>

                            <option value="delivered">
                                Delivered
                            </option>

                            <option value="live">
                                Live
                            </option>

                            <option value="takedown_requested">
                                Takedown Requested
                            </option>

                            <option value="taken_down">
                                Taken Down
                            </option>
                        </select>

                        <select
                            value={
                                filters.sort ??
                                'latest'
                            }
                            onChange={(
                                event
                            ) =>
                                updateFilter({
                                    sort:
                                        event
                                            .target
                                            .value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="latest">
                                Latest
                            </option>

                            <option value="oldest">
                                Oldest
                            </option>

                            <option value="title_asc">
                                Title A–Z
                            </option>

                            <option value="title_desc">
                                Title Z–A
                            </option>

                            <option value="release_date">
                                Release Date
                            </option>
                        </select>

                        <button
                            type="button"
                            onClick={() =>
                                router.get(
                                    '/v2/catalogue'
                                )
                            }
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Clear
                        </button>
                    </div>
                </section>

                {rows.length > 0 ? (
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        {rows.map(
                            (item) => (
                                <CatalogueCard
                                    key={
                                        item.id
                                    }
                                    item={
                                        item
                                    }
                                />
                            )
                        )}
                    </div>
                ) : (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center shadow-sm">
                        <div className="text-lg font-semibold text-slate-900">
                            No catalogue releases found
                        </div>

                        <p className="mt-2 text-sm text-slate-500">
                            Delivered and live
                            releases will appear
                            here automatically.
                        </p>
                    </div>
                )}

                {catalogue.links && (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        {catalogue.links.map(
                            (link, index) => (
                                <Link
                                    key={index}
                                    href={
                                        link.url ??
                                        '#'
                                    }
                                    preserveScroll
                                    className={[
                                        'rounded-lg border px-3 py-2 text-sm',
                                        link.active
                                            ? 'border-violet-600 bg-violet-600 text-white'
                                            : 'border-slate-300 bg-white text-slate-700',
                                        !link.url
                                            ? 'pointer-events-none opacity-40'
                                            : '',
                                    ].join(
                                        ' '
                                    )}
                                    dangerouslySetInnerHTML={{
                                        __html:
                                            link.label,
                                    }}
                                />
                            )
                        )}
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}

function CatalogueCard({
    item,
}) {
    const delivery =
        item.delivery_summary ?? {};

    return (
        <Link
            href={`/v2/catalogue/${item.id}`}
            className="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:border-violet-300 hover:shadow-lg"
        >
            <div className="aspect-square bg-slate-100">
                {item.artwork_path ? (
                    <img
                        src={
                            item.artwork_path.startsWith(
                                'http'
                            )
                                ? item.artwork_path
                                : `/storage/${item.artwork_path}`
                        }
                        alt={item.title}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <div className="flex h-full items-center justify-center text-5xl text-slate-300">
                        ♪
                    </div>
                )}
            </div>

            <div className="p-5">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h2 className="truncate font-semibold text-slate-900 group-hover:text-violet-700">
                            {item.title}
                        </h2>

                        <p className="mt-1 truncate text-sm text-slate-500">
                            {item.primary_artist_name ||
                                'Unknown Artist'}
                        </p>
                    </div>

                    <span
                        className={[
                            'shrink-0 rounded-full px-3 py-1 text-[11px] font-semibold capitalize',
                            statusClasses[
                                item
                                    .release_status
                            ] ??
                                'bg-slate-100 text-slate-700',
                        ].join(' ')}
                    >
                        {item.release_status.replaceAll(
                            '_',
                            ' '
                        )}
                    </span>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-3">
                    <Stat
                        label="Tracks"
                        value={
                            item.track_count ??
                            0
                        }
                    />

                    <Stat
                        label="ISRC"
                        value={`${item.isrc_assigned_count ?? 0}/${item.track_count ?? 0}`}
                    />

                    <Stat
                        label="DSP Live"
                        value={
                            delivery.live ??
                            0
                        }
                    />

                    <Stat
                        label="DSP Total"
                        value={
                            delivery.total ??
                            0
                        }
                    />
                </div>

                <div className="mt-4 border-t border-slate-100 pt-4">
                    <div className="text-xs text-slate-500">
                        UPC
                    </div>

                    <div className="mt-1 truncate text-sm font-semibold text-slate-800">
                        {item.upc ||
                            'Pending'}
                    </div>
                </div>
            </div>
        </Link>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-3">
            <div className="text-[10px] font-semibold uppercase text-slate-500">
                {label}
            </div>

            <div className="mt-1 text-lg font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}
JSX


echo "[3/5] Creating Catalogue Detail Page..."

cat > resources/js/Pages/V2/Catalogue/Show.jsx <<'JSX'
import {
    Head,
    Link,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'artist',
    catalogueItem,
}) {
    const release =
        catalogueItem.release ?? {};

    const tracks =
        release.tracks ?? [];

    const delivery =
        catalogueItem.delivery_summary ??
        {};

    return (
        <PanelLayout
            role={role}
            title="Catalogue Release"
            subtitle={catalogueItem.title}
        >
            <Head
                title={
                    catalogueItem.title
                }
            />

            <div className="space-y-6">
                <Link
                    href="/v2/catalogue"
                    className="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                >
                    ← Back to Catalogue
                </Link>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-6 md:grid-cols-[220px_1fr]">
                        <div className="aspect-square overflow-hidden rounded-2xl bg-slate-100">
                            {catalogueItem.artwork_path ? (
                                <img
                                    src={
                                        catalogueItem.artwork_path.startsWith(
                                            'http'
                                        )
                                            ? catalogueItem.artwork_path
                                            : `/storage/${catalogueItem.artwork_path}`
                                    }
                                    alt={
                                        catalogueItem.title
                                    }
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <div className="flex h-full items-center justify-center text-6xl text-slate-300">
                                    ♪
                                </div>
                            )}
                        </div>

                        <div>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h1 className="text-3xl font-bold text-slate-900">
                                        {
                                            catalogueItem.title
                                        }
                                    </h1>

                                    <p className="mt-2 text-lg text-slate-500">
                                        {catalogueItem.primary_artist_name ||
                                            'Unknown Artist'}
                                    </p>
                                </div>

                                <span className="rounded-full bg-emerald-100 px-4 py-2 text-sm font-semibold capitalize text-emerald-700">
                                    {catalogueItem.release_status.replaceAll(
                                        '_',
                                        ' '
                                    )}
                                </span>
                            </div>

                            <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <Info
                                    label="UPC"
                                    value={
                                        catalogueItem.upc ||
                                        'Pending'
                                    }
                                />

                                <Info
                                    label="Catalogue Number"
                                    value={
                                        catalogueItem.catalog_number ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Release Date"
                                    value={
                                        catalogueItem.digital_release_date ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Label"
                                    value={
                                        catalogueItem.label_name ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Language"
                                    value={
                                        catalogueItem.language ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Genre"
                                    value={
                                        catalogueItem.primary_genre ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Release Type"
                                    value={
                                        catalogueItem.release_type ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Tracks"
                                    value={
                                        catalogueItem.track_count ??
                                        tracks.length
                                    }
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        DSP Delivery Summary
                    </h2>

                    <div className="mt-4 grid gap-4 sm:grid-cols-3 xl:grid-cols-6">
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
                                        {delivery[
                                            status
                                        ] ??
                                            0}
                                    </div>
                                </div>
                            )
                        )}
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h2 className="text-lg font-semibold text-slate-900">
                            Tracks
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        '#',
                                        'Track',
                                        'Artist',
                                        'ISRC',
                                        'Language',
                                        'Genre',
                                        'Audio',
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
                                {tracks.length >
                                0 ? (
                                    tracks.map(
                                        (
                                            track,
                                            index
                                        ) => (
                                            <tr
                                                key={
                                                    track.id
                                                }
                                            >
                                                <td className="px-5 py-4 text-sm text-slate-500">
                                                    {index +
                                                        1}
                                                </td>

                                                <td className="px-5 py-4">
                                                    <div className="font-semibold text-slate-900">
                                                        {
                                                            track.title
                                                        }
                                                    </div>

                                                    {track.version && (
                                                        <div className="mt-1 text-xs text-slate-500">
                                                            {
                                                                track.version
                                                            }
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {track.primary_artist_name ||
                                                        '—'}
                                                </td>

                                                <td className="px-5 py-4 font-mono text-sm text-slate-700">
                                                    {track.isrc ||
                                                        'Pending'}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {track.language ||
                                                        '—'}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {track.genre ||
                                                        '—'}
                                                </td>

                                                <td className="px-5 py-4">
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
                                                        {track.audio_validation_status ||
                                                            'pending'}
                                                    </span>
                                                </td>
                                            </tr>
                                        )
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="px-5 py-14 text-center text-sm text-slate-500"
                                        >
                                            No tracks
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

function Info({
    label,
    value,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 truncate text-sm font-semibold capitalize text-slate-900">
                {value}
            </div>
        </div>
    );
}
JSX


echo "[4/5] Checking Catalogue routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.catalogue.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/catalogue',
        [\App\Http\Controllers\V2\CatalogueController::class, 'index']
    )
    ->name('v2.catalogue.index');
""",

    "v2.catalogue.show": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/catalogue/{catalogueItem}',
        [\App\Http\Controllers\V2\CatalogueController::class, 'show']
    )
    ->name('v2.catalogue.show');
""",
}

added = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        added += 1

path.write_text(text)

print(f"{added} catalogue routes added.")
PY


echo "[5/5] Running checks and build..."

php -l "$CONTROLLER"
php -l routes/web.php

npm run build

php artisan optimize:clear

printf '{\n  "module": "CatalogueUI",\n  "installed": true,\n  "version": "3.9.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/catalogue-ui-installed.json

echo ""
echo "===== CATALOGUE ROUTES ====="

php artisan route:list | grep \
"v2/catalogue"

echo ""
echo "=============================================="
echo "CATALOGUE UI INSTALLED"
echo "=============================================="

cat \
v2/runtime/state/catalogue-ui-installed.json

echo ""
echo "Open:"
echo "https://admin.mixxtune.com/v2/catalogue"
echo "https://artist.mixxtune.com/v2/catalogue"

echo ""
echo "Backup:"
echo "$BACKUP"
