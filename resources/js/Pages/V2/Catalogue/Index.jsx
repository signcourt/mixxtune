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

const statusClasses = {
    draft:
        'bg-slate-100 text-slate-700 ring-slate-200',

    submitted:
        'bg-amber-50 text-amber-700 ring-amber-200',

    approved:
        'bg-emerald-50 text-emerald-700 ring-emerald-200',

    processing:
        'bg-blue-50 text-blue-700 ring-blue-200',

    delivered:
        'bg-indigo-50 text-indigo-700 ring-indigo-200',

    live:
        'bg-green-50 text-green-700 ring-green-200',

    rejected:
        'bg-rose-50 text-rose-700 ring-rose-200',

    takedown_requested:
        'bg-orange-50 text-orange-700 ring-orange-200',

    taken_down:
        'bg-slate-200 text-slate-700 ring-slate-300',
};


const formatDate = (value) => {
    if (!value) {
        return 'Not scheduled';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-IN',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }
    ).format(date);
};

const artworkUrl = (path) => {
    if (!path) {
        return null;
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://')
    ) {
        return path;
    }

    if (path.startsWith('/storage/')) {
        return path;
    }

    return `/storage/${path}`;
};

const normaliseStatus = (status) =>
    String(status || 'pending')
        .replaceAll('_', ' ');

const copyValue = async (value) => {
    if (!value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(
            String(value)
        );
    } catch {
        // Clipboard may be unavailable on HTTP.
    }
};

export default function Index({
    role = 'artist',
    catalogue = {},
    counts = {},
    filters = {},
    labelOptions = [],
}) {
    const rows = catalogue.data ?? [];

    const [viewMode, setViewMode] =
        useState('table');

    const [searchValue, setSearchValue] =
        useState(filters.search ?? '');

    const [showAdvanced, setShowAdvanced] =
        useState(false);

    const title =
        role === 'artist'
            ? 'My Catalogue'
            : role === 'label'
              ? 'Label Catalogue'
              : 'Catalogue';

    const pageSummary = useMemo(
        () => ({
            from:
                catalogue.from ??
                (rows.length > 0 ? 1 : 0),

            to:
                catalogue.to ??
                rows.length,

            total:
                catalogue.total ??
                rows.length,
        }),
        [catalogue, rows.length]
    );

    const updateFilter = (
        changes = {}
    ) => {
        const nextFilters = {
            ...filters,
            ...changes,
        };

        Object.keys(nextFilters).forEach(
            (key) => {
                if (
                    nextFilters[key] === '' ||
                    nextFilters[key] === null ||
                    typeof nextFilters[key] ===
                        'undefined'
                ) {
                    delete nextFilters[key];
                }
            }
        );

        router.get(
            '/v2/catalogue',
            nextFilters,
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const clearFilters = () => {
        setSearchValue('');

        router.get(
            '/v2/catalogue',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const applySearch = () => {
        updateFilter({
            search: searchValue.trim(),
        });
    };

    /* MIXXTUNE_CATALOGUE_LIST_V2: v2 list design (count cards removed) */
    const monthValue =
        filters.date_from
        && /^\d{4}-\d{2}-01$/.test(filters.date_from)
            ? filters.date_from.slice(0, 7)
            : '';

    const changeMonth = (value) => {
        if (!value) {
            updateFilter({
                date_from: '',
                date_to: '',
            });

            return;
        }

        const [year, month] = value
            .split('-')
            .map(Number);

        const lastDay = new Date(
            year,
            month,
            0
        ).getDate();

        updateFilter({
            date_from: `${value}-01`,
            date_to: `${value}-${String(lastDay).padStart(2, '0')}`,
        });
    };

    const hasFilters = Boolean(
        filters.search
        || filters.status
        || filters.label
        || filters.product_type
        || filters.date_from
        || filters.date_to
        || filters.artist
        || filters.visibility
    );

    const selectClass =
        /* MIXXTUNE_MOBILE_LIST_V1: on phones two filters per row, full text visible */
        'h-11 min-w-[9.5rem] flex-1 rounded-xl border border-slate-200 bg-white pl-3 pr-8 text-sm font-semibold text-slate-700 outline-none transition focus:border-violet-500 sm:flex-none';

    return (
        <PanelLayout
            role={role}
            title={title}
            subtitle="Finished releases (approved, delivered, live) with artwork, label and store status"
        >
            <Head title={title} />

            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-baseline gap-3">
                        <h1 className="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-[28px]">
                            All Catalogue
                        </h1>

                        <span className="text-sm text-slate-500">
                            {Number(
                                pageSummary.total
                            ).toLocaleString('en-IN')}
                            {' '}
                            releases
                        </span>
                    </div>

                    <Link
                        href="/v2/releases/create"
                        className="inline-flex h-11 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white transition hover:bg-violet-700"
                    >
                        <span className="text-lg leading-none">
                            +
                        </span>
                        Create Release
                    </Link>
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center gap-2.5 border-b border-slate-200 p-3 sm:p-4">
                        <label className="flex h-11 min-w-[240px] flex-1 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 focus-within:border-violet-500">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" strokeWidth="2" aria-hidden="true">
                                <circle cx="11" cy="11" r="7" />
                                <path d="M20 20l-4-4" />
                            </svg>

                            <span className="sr-only">
                                Search releases
                            </span>

                            <input
                                type="search"
                                value={searchValue}
                                placeholder="Search title, artist, label, UPC or ISRC"
                                onChange={(event) =>
                                    setSearchValue(
                                        event.target.value
                                    )
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        applySearch();
                                    }
                                }}
                                className="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-800 outline-none focus:ring-0"
                            />
                        </label>

                        <select
                            aria-label="Status"
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                updateFilter({
                                    status: event.target.value,
                                })
                            }
                            className={selectClass}
                        >
                            <option value="">Status: All</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Under Review</option>
                            <option value="approved">Approved</option>
                            <option value="processing">Processing</option>
                            <option value="delivered">Delivered</option>
                            <option value="live">Live</option>
                            <option value="rejected">Rejected</option>
                            <option value="takedown_requested">Takedown Requested</option>
                            <option value="taken_down">Taken Down</option>
                        </select>

                        {labelOptions.length > 1 && (
                            <select
                                aria-label="Label"
                                value={filters.label ?? ''}
                                onChange={(event) =>
                                    updateFilter({
                                        label: event.target.value,
                                    })
                                }
                                className={`${selectClass} max-w-[220px]`}
                            >
                                <option value="">Label: All</option>
                                {labelOptions.map((name) => (
                                    <option key={name} value={name}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                        )}

                        <label className="flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                            <span>Release date</span>
                            <input
                                type="month"
                                value={monthValue}
                                onChange={(event) =>
                                    changeMonth(
                                        event.target.value
                                    )
                                }
                                className="border-0 bg-transparent p-0 text-sm text-slate-700 outline-none focus:ring-0"
                            />
                        </label>

                        <select
                            aria-label="Type"
                            value={filters.product_type ?? ''}
                            onChange={(event) =>
                                updateFilter({
                                    product_type: event.target.value,
                                })
                            }
                            className={selectClass}
                        >
                            <option value="">Type: All</option>
                            <option value="single">Single</option>
                            <option value="ep">EP</option>
                            <option value="album">Album</option>
                        </select>

                        <select
                            aria-label="Sort"
                            value={filters.sort ?? 'latest'}
                            onChange={(event) =>
                                updateFilter({
                                    sort: event.target.value,
                                })
                            }
                            className={selectClass}
                        >
                            <option value="latest">Latest added</option>
                            <option value="release_date">Release date</option>
                            <option value="oldest">Oldest added</option>
                            <option value="title_asc">Title A–Z</option>
                            <option value="title_desc">Title Z–A</option>
                        </select>

                        <div className="flex h-11 overflow-hidden rounded-xl border border-slate-200">
                            <button
                                type="button"
                                aria-label="List view"
                                onClick={() =>
                                    setViewMode('table')
                                }
                                className={`flex w-11 items-center justify-center ${
                                    viewMode === 'table'
                                        ? 'bg-violet-100 text-violet-700'
                                        : 'bg-white text-slate-500'
                                }`}
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                    <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                                </svg>
                            </button>

                            <button
                                type="button"
                                aria-label="Grid view"
                                onClick={() =>
                                    setViewMode('grid')
                                }
                                className={`flex w-11 items-center justify-center border-l border-slate-200 ${
                                    viewMode === 'grid'
                                        ? 'bg-violet-100 text-violet-700'
                                        : 'bg-white text-slate-500'
                                }`}
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                    <rect x="3" y="3" width="7" height="7" />
                                    <rect x="14" y="3" width="7" height="7" />
                                    <rect x="3" y="14" width="7" height="7" />
                                    <rect x="14" y="14" width="7" height="7" />
                                </svg>
                            </button>
                        </div>

                        {hasFilters && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="h-11 rounded-xl px-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                            >
                                Clear
                            </button>
                        )}
                    </div>

                    {rows.length === 0 ? (
                        <EmptyState
                            clearFilters={
                                clearFilters
                            }
                        />
                    ) : viewMode === 'grid' ? (
                        <div className="grid gap-5 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                            {rows.map((item) => (
                                <CatalogueCard
                                    key={item.id}
                                    item={item}
                                />
                            ))}
                        </div>
                    ) : (
                        <CatalogueTable
                            rows={rows}
                            canEdit={role === 'super_admin'}
                        />
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3.5 text-sm text-slate-500">
                        <span>
                            Showing {pageSummary.from}–{pageSummary.to} of{' '}
                            {Number(
                                pageSummary.total
                            ).toLocaleString('en-IN')}
                        </span>

                        <Pagination
                            links={
                                catalogue.links ??
                                []
                            }
                        />
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}

function CatalogueTable({
    rows,
    canEdit = false,
}) {
    return (
        <>
        {/* MIXXTUNE_MOBILE_LIST_V1: phones get stacked cards instead of a wide table */}
        <div className="divide-y divide-slate-100 md:hidden">
            {rows.map((item) => (
                <CatalogueRow
                    key={item.id}
                    item={item}
                    canEdit={canEdit}
                    variant="card"
                />
            ))}
        </div>

        <div className="hidden overflow-x-auto md:block">
            <table className="w-full min-w-[1100px] border-collapse">
                <thead>
                    <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                        <th className="px-5 py-3.5">Release</th>
                        <th className="px-5 py-3.5">Label</th>
                        <th className="px-5 py-3.5">Release date</th>
                        <th className="px-5 py-3.5">UPC</th>
                        <th className="px-5 py-3.5">Stores</th>
                        <th className="px-5 py-3.5">Status</th>
                        <th className="px-5 py-3.5 text-right">
                            <span className="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {rows.map((item) => (
                        <CatalogueRow
                            key={item.id}
                            item={item}
                            canEdit={canEdit}
                        />
                    ))}
                </tbody>
            </table>
        </div>
        </>
    );
}

const pillMeta = {
    live: ['Live', 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
    delivered: ['Delivered', 'bg-teal-50 text-teal-800 ring-teal-200'],
    approved: ['Approved', 'bg-blue-50 text-blue-800 ring-blue-200'],
    submitted: ['In Review', 'bg-amber-50 text-amber-800 ring-amber-200'],
    processing: ['Processing', 'bg-amber-50 text-amber-800 ring-amber-200'],
    rejected: ['Rejected', 'bg-rose-50 text-rose-800 ring-rose-200'],
    takedown_requested: ['Takedown Requested', 'bg-orange-50 text-orange-800 ring-orange-200'],
    taken_down: ['Taken Down', 'bg-slate-100 text-slate-700 ring-slate-200'],
    draft: ['Draft', 'bg-slate-100 text-slate-700 ring-slate-200'],
};

function StatusPill({
    status,
}) {
    const value = String(status || 'draft').toLowerCase();
    const [label, classes] =
        pillMeta[value]
        ?? [
            value.replace(/_/g, ' '),
            'bg-slate-100 text-slate-700 ring-slate-200',
        ];

    return (
        <span className={`inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold capitalize ring-1 ring-inset ${classes}`}>
            {label}
        </span>
    );
}

function CatalogueRow({
    item,
    canEdit = false,
    variant = 'row',
}) {
    const artwork = artworkUrl(
        item.artwork_path
    );

    const trackCount =
        Number(item.track_count ?? 0);

    const delivery =
        item.delivery_summary ?? {};

    const storesTotal = Math.max(
        Number(delivery.total ?? 0),
        0
    );

    const storesLive = Math.min(
        Math.max(Number(delivery.live ?? 0), 0),
        storesTotal
    );

    const viewPath = `/v2/catalogue/${item.id}`;

    if (variant === 'card') {
        return (
            <div className="flex gap-3 p-3.5">
                <Link
                    href={viewPath}
                    className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 shadow-sm ring-1 ring-slate-200"
                >
                    {artwork ? (
                        <img src={artwork} alt={item.title ?? 'Release artwork'} className="h-full w-full object-cover" loading="lazy" />
                    ) : (
                        <span className="text-2xl font-semibold text-violet-600" aria-hidden="true">♪</span>
                    )}
                </Link>

                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <Link href={viewPath} className="min-w-0 truncate text-[15px] font-bold text-slate-900">
                            {item.title ?? 'Untitled Release'}
                        </Link>
                        <StatusPill status={item.release_status} />
                    </div>

                    <p className="mt-0.5 truncate text-[13px] text-slate-500">
                        {item.primary_artist_name || 'Unknown Artist'} · <span className="capitalize">{item.release_type || 'single'}</span> · {trackCount} {trackCount === 1 ? 'track' : 'tracks'}
                    </p>

                    <p className="mt-0.5 truncate text-xs text-slate-500">
                        {item.label_name || '—'} · {formatDate(item.digital_release_date || item.original_release_date)}
                    </p>

                    <div className="mt-2 flex items-end justify-between gap-2">
                        <div className="min-w-0">
                            <p className="truncate font-mono text-xs text-slate-600">
                                {item.upc ? `UPC ${item.upc}` : 'UPC pending'}
                            </p>
                            {storesTotal > 0 && (
                                <p className="mt-0.5 text-xs text-slate-500">
                                    {storesLive} / {storesTotal} stores live
                                </p>
                            )}
                        </div>

                        {canEdit && item.release_id && (
                            <Link
                                href={`/v2/releases/${item.release_id}/edit`}
                                aria-label="Edit release"
                                className="inline-flex h-8 shrink-0 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-2.5 text-xs font-semibold text-amber-700"
                            >
                                Edit
                            </Link>
                        )}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <tr className="border-b border-slate-100 transition last:border-b-0 hover:bg-slate-50/70">
            <td className="px-5 py-3">
                <div className="flex min-w-[300px] items-center gap-3.5">
                    <Link
                        href={viewPath}
                        className="flex h-[60px] w-[60px] shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 shadow-sm ring-1 ring-slate-200"
                    >
                        {artwork ? (
                            <img
                                src={artwork}
                                alt={item.title ?? 'Release artwork'}
                                className="h-full w-full object-cover"
                                loading="lazy"
                            />
                        ) : (
                            <span className="text-2xl font-semibold text-violet-600" aria-hidden="true">
                                ♪
                            </span>
                        )}
                    </Link>

                    <div className="min-w-0">
                        <Link
                            href={viewPath}
                            title={item.title ?? 'Untitled Release'}
                            className="block max-w-[300px] truncate text-[15px] font-bold text-slate-900 transition hover:text-violet-700"
                        >
                            {item.title ?? 'Untitled Release'}
                        </Link>

                        <p className="mt-1 max-w-[300px] truncate text-[13px] text-slate-500">
                            {item.primary_artist_name || 'Unknown Artist'}
                            {' · '}
                            <span className="capitalize">
                                {item.release_type || 'single'}
                            </span>
                            {' · '}
                            {trackCount} {trackCount === 1 ? 'track' : 'tracks'}
                        </p>
                    </div>
                </div>
            </td>

            <td className="px-5 py-3">
                <span title={item.label_name || ''} className="block max-w-[200px] truncate text-sm text-slate-700">
                    {item.label_name || '—'}
                </span>
            </td>

            <td className="px-5 py-3">
                <span className="whitespace-nowrap text-sm text-slate-700">
                    {formatDate(
                        item.digital_release_date ||
                            item.original_release_date
                    )}
                </span>
            </td>

            <td className="px-5 py-3">
                {item.upc ? (
                    <span className="whitespace-nowrap font-mono text-[13px] text-slate-700">
                        {item.upc}
                    </span>
                ) : (
                    <span className="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">
                        Pending
                    </span>
                )}
            </td>

            <td className="px-5 py-3">
                {storesTotal > 0 ? (
                    <div className="w-32">
                        <div className="h-1.5 overflow-hidden rounded-full bg-slate-200">
                            <div
                                className={`h-full rounded-full ${storesLive === storesTotal ? 'bg-emerald-600' : 'bg-blue-600'}`}
                                style={{ width: `${Math.round((storesLive / storesTotal) * 100)}%` }}
                            />
                        </div>
                        <p className="mt-1.5 text-xs text-slate-500">
                            {storesLive} / {storesTotal} stores
                        </p>
                    </div>
                ) : (
                    <span className="text-sm text-slate-400">—</span>
                )}
            </td>

            <td className="px-5 py-3">
                <StatusPill status={item.release_status} />
            </td>

            <td className="px-5 py-3">
                <div className="flex justify-end gap-2">
                    {/* MIXXTUNE_SUPER_ADMIN_FULL_EDIT */}
                    {canEdit && item.release_id && (
                        <Link
                            href={`/v2/releases/${item.release_id}/edit`}
                            title="Edit release (Super Admin): poster, details, tracks, audio"
                            aria-label="Edit release"
                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-700 transition hover:border-amber-300 hover:bg-amber-100"
                        >
                            <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
                                <path d="M4 20h4L18.5 9.5a2.12 2.12 0 0 0-3-3L5 17v3Z" strokeLinecap="round" strokeLinejoin="round" />
                                <path d="m14 8 2 2" strokeLinecap="round" />
                            </svg>
                        </Link>
                    )}
                    <Link
                        href={viewPath}
                        title="View release"
                        aria-label="View release"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700"
                    >
                        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
                            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" strokeLinecap="round" strokeLinejoin="round" />
                            <circle cx="12" cy="12" r="2.75" />
                        </svg>
                    </Link>
                </div>
            </td>
        </tr>
    );
}

function CatalogueStatusIcon({
    status,
}) {
    const value = String(
        status || 'pending'
    )
        .toLowerCase()
        .trim();

    if (
        [
            'live',
            'approved',
            'delivered',
        ].includes(value)
    ) {
        return (
            <span
                title={normaliseStatus(status)}
                className="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    className="h-5 w-5"
                    stroke="currentColor"
                    strokeWidth="2.5"
                >
                    <path
                        d="m5 12 4 4 10-10"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </span>
        );
    }

    if (
        [
            'scheduled',
            'upcoming',
        ].includes(value)
    ) {
        return (
            <span
                title={normaliseStatus(status)}
                className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    className="h-5 w-5"
                    stroke="currentColor"
                    strokeWidth="2"
                >
                    <rect
                        x="3"
                        y="5"
                        width="18"
                        height="16"
                        rx="2"
                    />
                    <path
                        d="M8 3v4M16 3v4M3 10h18"
                        strokeLinecap="round"
                    />
                </svg>
            </span>
        );
    }

    if (
        [
            'pending',
            'submitted',
            'processing',
            'under_review',
        ].includes(value)
    ) {
        return (
            <span
                title={normaliseStatus(status)}
                className="flex h-9 w-9 items-center justify-center rounded-full bg-amber-50 text-amber-500"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    className="h-5 w-5"
                    stroke="currentColor"
                    strokeWidth="2"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />
                    <path
                        d="M12 7v5l3 2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </span>
        );
    }

    if (value === 'draft') {
        return (
            <span
                title="Draft"
                className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    className="h-5 w-5"
                    stroke="currentColor"
                    strokeWidth="2"
                >
                    <path
                        d="M6 3h8l4 4v14H6V3Z"
                        strokeLinejoin="round"
                    />
                    <path
                        d="M14 3v5h5M9 13h6M9 17h5"
                        strokeLinecap="round"
                    />
                </svg>
            </span>
        );
    }

    if (
        [
            'rejected',
            'failed',
            'taken_down',
            'takedown',
        ].includes(value)
    ) {
        return (
            <span
                title={normaliseStatus(status)}
                className="flex h-9 w-9 items-center justify-center rounded-full bg-red-500 text-white shadow-sm"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    className="h-5 w-5"
                    stroke="currentColor"
                    strokeWidth="2.4"
                >
                    <path
                        d="m7 7 10 10M17 7 7 17"
                        strokeLinecap="round"
                    />
                </svg>
            </span>
        );
    }

    return (
        <span
            title={normaliseStatus(status)}
            className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-500"
        >
            <span className="h-2.5 w-2.5 rounded-full bg-current" />
        </span>
    );
}

function CatalogueCard({
    item,
}) {
    const delivery =
        item.delivery_summary ?? {};

    const total =
        Number(delivery.total ?? 0);

    const live =
        Number(delivery.live ?? 0);

    const percent =
        total > 0
            ? Math.min(
                  100,
                  Math.round(
                      (live / total) *
                          100
                  )
              )
            : 0;

    const artwork =
        artworkUrl(
            item.artwork_path
        );

    return (
        <article className="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:border-violet-300 hover:shadow-xl">
            <Link
                href={`/v2/catalogue/${item.id}`}
                className="relative block aspect-square overflow-hidden bg-slate-100"
            >
                {artwork ? (
                    <img
                        src={artwork}
                        alt={
                            item.title ??
                            'Release artwork'
                        }
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex h-full items-center justify-center text-6xl text-slate-300">
                        ♪
                    </div>
                )}

                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/70 to-transparent p-4 pt-14">
                    <StatusBadge
                        status={
                            item.release_status
                        }
                    />
                </div>
            </Link>

            <div className="p-5">
                <Link
                    href={`/v2/catalogue/${item.id}`}
                    className="block truncate text-base font-bold text-slate-900 transition hover:text-violet-700"
                >
                    {item.title ??
                        'Untitled Release'}
                </Link>

                <p className="mt-1 truncate text-sm font-medium text-slate-500">
                    {item.primary_artist_name ||
                        'Unknown Artist'}
                </p>

                <div className="mt-4 grid grid-cols-2 gap-3">
                    <MiniStat
                        label="Tracks"
                        value={
                            item.track_count ??
                            0
                        }
                    />

                    <MiniStat
                        label="ISRC"
                        value={`${item.isrc_assigned_count ?? 0}/${item.track_count ?? 0}`}
                    />

                    <MiniStat
                        label="DSP Live"
                        value={
                            live
                        }
                    />

                    <MiniStat
                        label="DSP Total"
                        value={
                            total
                        }
                    />
                </div>

                <div className="mt-4">
                    <div className="flex items-center justify-between text-[11px] font-semibold text-slate-500">
                        <span>
                            DSP Delivery
                        </span>

                        <span className="text-slate-800">
                            {percent}%
                        </span>
                    </div>

                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                            className="h-full rounded-full bg-violet-600"
                            style={{
                                width: `${percent}%`,
                            }}
                        />
                    </div>
                </div>

                <div className="mt-4 border-t border-slate-100 pt-4">
                    <Identifier
                        label="UPC"
                        value={
                            item.upc
                        }
                    />
                </div>

                <div className="mt-4 flex items-center justify-between gap-3">
                    <span className="text-xs font-medium text-slate-500">
                        {formatDate(
                            item.digital_release_date || item.original_release_date
                        )}
                    </span>

                    <Link
                        href={`/v2/catalogue/${item.id}`}
                        className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-violet-700"
                    >
                        View
                    </Link>
                </div>
            </div>
        </article>
    );
}

function Identifier({
    label,
    value,
}) {
    return (
        <div>
            <div className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                {label}
            </div>

            <div className="mt-1 flex items-center gap-2">
                <span className="max-w-[145px] truncate text-xs font-bold text-slate-700">
                    {value ||
                        'Pending'}
                </span>

                {value && (
                    <button
                        type="button"
                        onClick={() =>
                            copyValue(
                                value
                            )
                        }
                        title={`Copy ${label}`}
                        className="rounded-md px-1.5 py-1 text-[10px] text-slate-400 transition hover:bg-violet-50 hover:text-violet-700"
                    >
                        Copy
                    </button>
                )}
            </div>
        </div>
    );
}

function StatusBadge({
    status,
}) {
    const safeStatus =
        status || 'pending';

    return (
        <span
            className={[
                'inline-flex shrink-0 items-center rounded-full px-3 py-1 text-[10px] font-bold capitalize ring-1 ring-inset',
                statusClasses[
                    safeStatus
                ] ??
                    'bg-slate-100 text-slate-700 ring-slate-200',
            ].join(' ')}
        >
            <span className="mr-1.5 h-1.5 w-1.5 rounded-full bg-current opacity-70" />

            {normaliseStatus(
                safeStatus
            )}
        </span>
    );
}

function MiniStat({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border border-slate-100 bg-slate-50 p-3">
            <div className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                {label}
            </div>

            <div className="mt-1 text-sm font-black text-slate-900">
                {value}
            </div>
        </div>
    );
}

function FilterField({
    label,
    children,
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                {label}
            </span>

            {children}
        </label>
    );
}

function TableHeading({
    children,
    align = 'left',
}) {
    return (
        <th
            className={[
                'px-5 py-3.5 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500',
                align === 'right'
                    ? 'text-right'
                    : 'text-left',
            ].join(' ')}
        >
            {children}
        </th>
    );
}

function EmptyState({
    clearFilters,
}) {
    return (
        <div className="px-6 py-20 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-50 text-3xl text-violet-600">
                ♪
            </div>

            <h3 className="mt-5 text-lg font-bold text-slate-900">
                No catalogue releases found
            </h3>

            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                No release matches your current
                search and filter selection.
            </p>

            <button
                type="button"
                onClick={
                    clearFilters
                }
                className="mt-5 rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-violet-700"
            >
                Clear Filters
            </button>
        </div>
    );
}

function Pagination({
    links,
}) {
    if (!links.length) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center justify-center gap-2">
            {links.map(
                (link, index) => (
                    <Link
                        key={`${link.label}-${index}`}
                        href={
                            link.url ??
                            '#'
                        }
                        preserveScroll
                        preserveState
                        className={[
                            'inline-flex min-h-10 min-w-10 items-center justify-center rounded-xl border px-3 py-2 text-sm font-bold transition',
                            link.active
                                ? 'border-violet-600 bg-violet-600 text-white shadow-sm'
                                : 'border-slate-300 bg-white text-slate-600 hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700',
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
        </nav>
    );
}
