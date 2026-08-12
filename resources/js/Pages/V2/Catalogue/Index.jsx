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

const countCards = [
    {
        key: 'total',
        label: 'Total Releases',
        description: 'Complete catalogue',
        tone: 'bg-violet-50 text-violet-700',
    },
    {
        key: 'visible',
        label: 'Visible',
        description: 'Available to panel',
        tone: 'bg-cyan-50 text-cyan-700',
    },
    {
        key: 'approved',
        label: 'Approved',
        description: 'Ready for delivery',
        tone: 'bg-emerald-50 text-emerald-700',
    },
    {
        key: 'processing',
        label: 'Processing',
        description: 'Delivery in progress',
        tone: 'bg-blue-50 text-blue-700',
    },
    {
        key: 'delivered',
        label: 'Delivered',
        description: 'Sent to stores',
        tone: 'bg-indigo-50 text-indigo-700',
    },
    {
        key: 'live',
        label: 'Live',
        description: 'Available on DSPs',
        tone: 'bg-green-50 text-green-700',
    },
];

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

    return (
        <PanelLayout
            role={role}
            title={title}
            subtitle="Released music, identifiers and DSP delivery status"
        >
            <Head title={title} />

            <div className="space-y-6">
                <header className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="grid gap-6 p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-violet-700">
                                    Mixx Tune
                                </span>

                                <span className="text-xs font-medium text-slate-500">
                                    Catalogue Management
                                </span>
                            </div>

                            <h1 className="mt-4 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                                {title}
                            </h1>

                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                View release metadata,
                                identifiers, tracks and DSP
                                delivery progress from one
                                workspace.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <Link
                                href="/v2/releases/create"
                                className="inline-flex items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-violet-700"
                            >
                                + Create Release
                            </Link>

                            <Link
                                href="/v2/releases"
                                className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                            >
                                All Releases
                            </Link>
                        </div>
                    </div>
                </header>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    {countCards.map(
                        (card) => {
                            const active =
                                card.key ===
                                'visible'
                                    ? filters.visibility ===
                                      'visible'
                                    : card.key ===
                                        'total'
                                      ? !filters.status &&
                                        !filters.visibility
                                      : filters.status ===
                                        card.key;

                            return (
                                <button
                                    key={
                                        card.key
                                    }
                                    type="button"
                                    onClick={() =>
                                        updateFilter(
                                            {
                                                status:
                                                    card.key ===
                                                        'total' ||
                                                    card.key ===
                                                        'visible'
                                                        ? ''
                                                        : card.key,

                                                visibility:
                                                    card.key ===
                                                    'visible'
                                                        ? 'visible'
                                                        : '',
                                            }
                                        )
                                    }
                                    className={[
                                        'group rounded-2xl border bg-white p-5 text-left shadow-sm transition',
                                        active
                                            ? 'border-violet-400 ring-2 ring-violet-100'
                                            : 'border-slate-200 hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md',
                                    ].join(
                                        ' '
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div
                                            className={[
                                                'rounded-xl px-3 py-2 text-xs font-bold',
                                                card.tone,
                                            ].join(
                                                ' '
                                            )}
                                        >
                                            {card.label}
                                        </div>

                                        <span className="text-lg text-slate-300 transition group-hover:text-violet-500">
                                            ↗
                                        </span>
                                    </div>

                                    <div className="mt-5 text-3xl font-black tracking-tight text-slate-950">
                                        {counts[
                                            card.key
                                        ] ?? 0}
                                    </div>

                                    <div className="mt-1 text-xs font-medium text-slate-500">
                                        {
                                            card.description
                                        }
                                    </div>
                                </button>
                            );
                        }
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <div className="flex min-w-0 flex-1 overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:border-violet-500 focus-within:ring-2 focus-within:ring-violet-100">
                            <span className="flex items-center px-3 text-slate-400">
                                ⌕
                            </span>

                            <input
                                type="search"
                                value={searchValue}
                                placeholder="Search by release title, artist, UPC, ISRC or catalogue number"
                                onChange={(event) =>
                                    setSearchValue(
                                        event.target.value
                                    )
                                }
                                onKeyDown={(event) => {
                                    if (
                                        event.key ===
                                        'Enter'
                                    ) {
                                        applySearch();
                                    }
                                }}
                                className="min-w-0 flex-1 border-0 px-0 py-2.5 pr-3 text-sm text-slate-800 outline-none placeholder:text-slate-400"
                            />

                            <button
                                type="button"
                                onClick={applySearch}
                                className="m-1 rounded-md bg-slate-900 px-4 text-xs font-bold text-white transition hover:bg-violet-700"
                            >
                                Search
                            </button>
                        </div>

                        <button
                            type="button"
                            onClick={() =>
                                setShowAdvanced(
                                    (value) => !value
                                )
                            }
                            className={[
                                'inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-bold transition',
                                showAdvanced
                                    ? 'border-violet-300 bg-violet-50 text-violet-700'
                                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                            ].join(' ')}
                        >
                            ⚙ Advanced
                            <span className="text-[10px]">
                                {showAdvanced
                                    ? '▲'
                                    : '▼'}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() =>
                                window.print()
                            }
                            className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                        >
                            ⇩ Export
                        </button>

                        <button
                            type="button"
                            onClick={clearFilters}
                            className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                        >
                            Clear
                        </button>
                    </div>

                    {showAdvanced && (
                        <div className="mt-4 border-t border-slate-200 pt-4">
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                                <FilterField label="Status">
                                    <select
                                        value={
                                            filters.status ??
                                            ''
                                        }
                                        onChange={(event) =>
                                            updateFilter({
                                                status:
                                                    event
                                                        .target
                                                        .value,
                                            })
                                        }
                                        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-violet-500"
                                    >
                                        <option value="">
                                            All Statuses
                                        </option>
                                        <option value="draft">
                                            Draft
                                        </option>
                                        <option value="submitted">
                                            Under Review
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
                                        <option value="rejected">
                                            Rejected
                                        </option>
                                        <option value="takedown_requested">
                                            Takedown Requested
                                        </option>
                                        <option value="taken_down">
                                            Taken Down
                                        </option>
                                    </select>
                                </FilterField>

                                <FilterField label="Product Type">
                                    <select
                                        value={
                                            filters.product_type ??
                                            ''
                                        }
                                        onChange={(event) =>
                                            updateFilter({
                                                product_type:
                                                    event
                                                        .target
                                                        .value,
                                            })
                                        }
                                        className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-violet-500"
                                    >
                                        <option value="">
                                            All Types
                                        </option>
                                        <option value="single">
                                            Single
                                        </option>
                                        <option value="ep">
                                            EP
                                        </option>
                                        <option value="album">
                                            Album
                                        </option>
                                    </select>
                                </FilterField>

                                <FilterField label="Label">
                                    <input
                                        type="text"
                                        value={
                                            filters.label ??
                                            ''
                                        }
                                        placeholder="All labels"
                                        onChange={(event) =>
                                            updateFilter({
                                                label:
                                                    event
                                                        .target
                                                        .value,
                                            })
                                        }
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-violet-500"
                                    />
                                </FilterField>

                                <FilterField label="Artist">
                                    <input
                                        type="text"
                                        value={
                                            filters.artist ??
                                            ''
                                        }
                                        placeholder="All artists"
                                        onChange={(event) =>
                                            updateFilter({
                                                artist:
                                                    event
                                                        .target
                                                        .value,
                                            })
                                        }
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-violet-500"
                                    />
                                </FilterField>

                                <FilterField label="From">
                                    <input
                                        type="date"
                                        value={
                                            filters.date_from ??
                                            ''
                                        }
                                        onChange={(event) =>
                                            updateFilter({
                                                date_from:
                                                    event
                                                        .target
                                                        .value,
                                            })
                                        }
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-violet-500"
                                    />
                                </FilterField>

                                <FilterField label="To">
                                    <input
                                        type="date"
                                        value={
                                            filters.date_to ??
                                            ''
                                        }
                                        onChange={(event) =>
                                            updateFilter({
                                                date_to:
                                                    event
                                                        .target
                                                        .value,
                                            })
                                        }
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-violet-500"
                                    />
                                </FilterField>
                            </div>

                            <div className="mt-3 flex justify-end">
                                <select
                                    value={
                                        filters.sort ??
                                        'latest'
                                    }
                                    onChange={(event) =>
                                        updateFilter({
                                            sort:
                                                event.target
                                                    .value,
                                        })
                                    }
                                    className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                >
                                    <option value="latest">
                                        Latest Added
                                    </option>
                                    <option value="oldest">
                                        Oldest Added
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
                            </div>
                        </div>
                    )}
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="font-bold text-slate-900">
                                Catalogue Releases
                            </h2>

                            <p className="mt-1 text-xs text-slate-500">
                                Showing{' '}
                                {
                                    pageSummary.from
                                }
                                –
                                {
                                    pageSummary.to
                                }{' '}
                                of{' '}
                                {
                                    pageSummary.total
                                }{' '}
                                releases
                            </p>
                        </div>

                        <div className="text-xs font-semibold text-slate-500">
                            50 releases per page
                        </div>
                    </div>

                    {rows.length > 0 ? (
                        <CatalogueTable
                            rows={rows}
                        />
                    ) : (
                        <EmptyState
                            clearFilters={
                                clearFilters
                            }
                        />
                    )}
                </section>

                <Pagination
                    links={
                        catalogue.links ??
                        []
                    }
                />
            </div>
        </PanelLayout>
    );
}

function CatalogueTable({
    rows,
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[1100px] border-collapse">
                <thead>
                    <tr className="border-b border-slate-200 bg-slate-50/90 text-left">
                        <TableHeading>
                            Release
                        </TableHeading>

                        <TableHeading>
                            UPC
                        </TableHeading>

                        <TableHeading>
                            Type
                        </TableHeading>

                        <TableHeading>
                            Tracks
                        </TableHeading>

                        <TableHeading>
                            Release Date
                        </TableHeading>

                        <TableHeading>
                            Status
                        </TableHeading>

                        <TableHeading align="right">
                            Actions
                        </TableHeading>
                    </tr>
                </thead>

                <tbody>
                    {rows.map((item) => (
                        <CatalogueRow
                            key={item.id}
                            item={item}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function CatalogueRow({
    item,
}) {
    const artwork = artworkUrl(
        item.artwork_path
    );

    return (
        <tr className="group border-b border-slate-100 transition last:border-b-0 hover:bg-slate-50/80">
            <td className="px-5 py-3">
                <div className="flex min-w-[310px] items-center gap-3">
                    <Link
                        href={`/v2/catalogue/${item.id}`}
                        className="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 shadow-sm"
                    >
                        {artwork ? (
                            <img
                                src={artwork}
                                alt={
                                    item.title ??
                                    'Release artwork'
                                }
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center text-xl text-slate-300">
                                ♪
                            </div>
                        )}
                    </Link>

                    <div className="min-w-0">
                        <Link
                            href={`/v2/catalogue/${item.id}`}
                            className="block max-w-[260px] truncate text-sm font-bold text-slate-900 transition hover:text-violet-700"
                        >
                            {item.title ??
                                'Untitled Release'}
                        </Link>

                        <p className="mt-0.5 max-w-[260px] truncate text-xs font-medium text-slate-500">
                            {item.primary_artist_name ||
                                'Unknown Artist'}
                        </p>

                        {item.label_name && (
                            <p className="mt-1 max-w-[260px] truncate text-[11px] text-slate-400">
                                {item.label_name}
                            </p>
                        )}
                    </div>
                </div>
            </td>

            <td className="px-5 py-3">
                <div className="min-w-[170px]">
                    <div className="flex items-center gap-2">
                        <span className="text-[10px] font-bold uppercase text-slate-400">
                            UPC
                        </span>

                        <span className="text-xs font-semibold text-slate-700">
                            {item.upc || 'Pending'}
                        </span>
                    </div>

                </div>
            </td>

            <td className="px-5 py-3">
                <span className="inline-flex rounded-md bg-slate-100 px-2.5 py-1 text-[11px] font-bold capitalize text-slate-600">
                    {item.release_type ||
                        'Single'}
                </span>
            </td>

            <td className="px-5 py-3">
                <div className="text-sm font-bold text-slate-900">
                    {item.track_count ?? 0}
                </div>

                <div className="mt-0.5 text-[10px] text-slate-400">
                    {item.isrc_assigned_count ??
                        0}
                    /
                    {item.track_count ?? 0}{' '}
                    ISRC
                </div>
            </td>

            <td className="px-5 py-3">
                <span className="whitespace-nowrap text-xs font-semibold text-slate-700">
                    {formatDate(
                        item.digital_release_date || item.original_release_date
                    )}
                </span>
            </td>

            <td className="px-5 py-3">
                <StatusBadge
                    status={
                        item.release_status
                    }
                />
            </td>

            <td className="px-5 py-3">
                <div className="flex items-center justify-end gap-1.5">
                    <Link
                        href={`/v2/catalogue/${item.id}`}
                        title="View release"
                        aria-label="View release"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-base text-slate-500 transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
                    >
                        ◉
                    </Link>

                    <Link
                        href={`/v2/catalogue/${item.id}`}
                        title="Release details"
                        aria-label="Release details"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-sm text-slate-500 transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
                    >
                        ⋮
                    </Link>
                </div>
            </td>
        </tr>
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
