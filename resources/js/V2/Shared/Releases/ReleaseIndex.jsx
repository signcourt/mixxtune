import { Head, Link, router, usePage } from '@inertiajs/react';

import {
    useEffect,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import StatusBadge from '@/V2/Shared/Components/StatusBadge';
import EmptyState from '@/V2/Shared/Components/EmptyState';
import PageMessages from '@/V2/Shared/Components/PageMessages';

const formatDate = (value) => {
    if (!value) {
        return 'Not scheduled';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const normaliseStatus = (status) =>
    String(status || 'draft')
        .trim()
        .toLowerCase();

const statusBorder = {
    draft: 'border-slate-200',
    submitted: 'border-blue-200',
    approved: 'border-emerald-200',
    rejected: 'border-rose-200',
    changes_requested: 'border-amber-200',
    processing: 'border-amber-200',
    delivered: 'border-violet-200',
    live: 'border-green-200',
    failed: 'border-red-200',
};


export default function ReleaseIndex({
    role = 'artist',
    permissions = [],
    releases = {
        data: [],
        links: [],
    },
    filters = {},
    statusCounts = {},
    labelOptions = [],
    filterOptions = {},
}) {
    const rows = Array.isArray(releases?.data)
        ? releases.data
        : [];

    const page = usePage();

    const accountId =
        page?.props?.auth?.user?.id
        ?? page?.props?.artist?.id
        ?? page?.props?.label?.id
        ?? 'guest';

    const viewStorageKey =
        `mixxtune-release-view:${role}:${accountId}`;

    const [viewMode, setViewMode] =
        useState('list');

    const [searchValue, setSearchValue] =
        useState(filters.search ?? '');

    /* W77: advanced filters panel (open by itself when one of them is set) */
    const advancedCount = ['label_id', 'artist', 'language', 'genre'].filter((key) => Boolean(filters[key])).length;
    const [advancedOpen, setAdvancedOpen] = useState(advancedCount > 0);

    
    /* W76: artists used "/releases", which does not exist (every filter change gave a 404) */
    const basePath = '/v2/releases';

    const canCreate =
        role === 'artist'
        || permissions.includes(
            'releases.create'
        );

    useEffect(() => {
        let saved = null;
        try {
            saved = window.localStorage.getItem(viewStorageKey);
        } catch (e) {
            saved = null;
        }

        if (
            saved === 'grid'
            || saved === 'list'
        ) {
            setViewMode(saved);
        }
    }, [viewStorageKey]);

    const changeView = (mode) => {
        setViewMode(mode);

        try {
            window.localStorage.setItem(viewStorageKey, mode);
        } catch (e) {
            /* storage blocked - the view still changes */
        }
    };

    const updateFilters = (
        changes,
        options = {}
    ) => {
        /* W76: only filters that are set go into the link (no "?artist=&genre=&..." clutter) */
        const merged = { ...filters, ...changes };
        const clean = Object.fromEntries(
            Object.entries(merged).filter(([key, value]) => value !== '' && value !== null && value !== undefined && !(key === 'sort' && value === 'latest'))
        );

        router.get(
            basePath,
            clean,
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                ...options,
            }
        );
    };

    const submitSearch = () => {
        updateFilters({
            search: searchValue.trim(),
        });
    };

    const resetFilters = () => {
        setSearchValue('');

        router.get(
            basePath,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const hasFilters =
        Boolean(filters.search)
        || Boolean(filters.status)
        || Boolean(filters.label_id)
        || Boolean(filters.release_type)
        || Boolean(filters.artist)
        || Boolean(filters.language)
        || Boolean(filters.genre)
        || (
            filters.sort
            && filters.sort !== 'latest'
        );


    /* MIXXTUNE_RELEASES_LIST_V2: clean header + one toolbar (status cards removed) */
    /* W63: the title follows the menu item (My Drafts, In Review ...) instead of always "All Releases" */
    const STATUS_TITLES = {
        draft: role === 'artist' ? 'Drafts' : 'My Drafts',
        submitted: 'In Review',
        changes_requested: 'Changes Requested',
        approved: 'Approved',
        processing: 'Processing',
        delivered: 'Delivered',
        live: 'Live',
        rejected: 'Rejected',
        failed: 'Failed',
    };
    const STATUS_SUBTITLES = {
        draft: 'Releases you started and have not sent for review yet. Only you can see your drafts.',
        submitted: 'Sent for review - waiting for the Mixx Tune team.',
        changes_requested: 'The team asked for changes - open, fix and send again.',
        rejected: 'Not accepted - open a release to see the reason.',
    };
    const pageTitle =
        STATUS_TITLES[filters.status]
        ?? (role === 'artist'
            ? 'My Releases'
            : 'All Releases');
    const pageSubtitle =
        STATUS_SUBTITLES[filters.status]
        ?? 'Every release in every stage: drafts, review, approved and live';

    const selectClass =
        /* MIXXTUNE_MOBILE_LIST_V1: on phones two filters per row, full text visible */
        'h-11 min-w-[9.5rem] flex-1 rounded-xl border border-slate-200 bg-white pl-3 pr-8 text-sm font-semibold text-slate-700 outline-none transition focus:border-violet-500 sm:flex-none';

    return (
        <PanelLayout
            role={role}
            title={pageTitle}
            subtitle={pageSubtitle}
        >
            <Head title={pageTitle} />
            <PageMessages />

            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-baseline gap-3">
                        <h1 className="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-[28px]">
                            {pageTitle}
                        </h1>

                        <span className="text-sm text-slate-500">
                            {Number(
                                releases?.total
                                ?? statusCounts.total
                                ?? 0
                            ).toLocaleString('en-IN')}
                            {' '}
                            releases
                        </span>
                    </div>

                    {canCreate && (
                        <Link
                            href={`${basePath}/create`}
                            className="inline-flex h-11 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white transition hover:bg-violet-700"
                        >
                            <span className="text-lg leading-none">
                                +
                            </span>
                            Create Release
                        </Link>
                    )}
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <label className="flex h-11 min-w-[240px] flex-1 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 focus-within:border-violet-500">
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#64748b"
                                strokeWidth="2"
                                aria-hidden="true"
                            >
                                <circle cx="11" cy="11" r="7" />
                                <path d="M20 20l-4-4" />
                            </svg>

                            <span className="sr-only">
                                Search releases
                            </span>

                            <input
                                type="search"
                                value={searchValue}
                                onChange={(event) =>
                                    setSearchValue(
                                        event.target
                                            .value
                                    )
                                }
                                onKeyDown={(event) => {
                                    if (
                                        event.key
                                        === 'Enter'
                                    ) {
                                        submitSearch();
                                    }
                                }}
                                placeholder="Search title, artist, label, UPC or ISRC"
                                className="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-800 outline-none focus:ring-0"
                            />
                        </label>

                        {/* W77: Label / Artist / Language / Genre live under "Advanced" */}
                        <button
                            type="button"
                            onClick={() => setAdvancedOpen((open) => !open)}
                            aria-expanded={advancedOpen}
                            aria-controls="release-advanced-filters"
                            className={`inline-flex h-11 items-center gap-2 rounded-xl border px-3.5 text-sm font-semibold transition ${advancedOpen || advancedCount > 0 ? 'border-violet-300 bg-violet-50 text-violet-700' : 'border-slate-200 bg-white text-slate-700 hover:border-violet-300'}`}
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                <path d="M4 6h16M7 12h10M10 18h4" />
                            </svg>
                            Advanced
                            {advancedCount > 0 && <span className="rounded-full bg-violet-600 px-1.5 text-[11px] font-bold text-white">{advancedCount}</span>}
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true" className={`transition-transform ${advancedOpen ? 'rotate-180' : ''}`}>
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </button>

                        <select
                            aria-label="Status"
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                updateFilters({
                                    status:
                                        event.target
                                            .value,
                                })
                            }
                            className={selectClass}
                        >
                            <option value="">Status: All</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">In Review</option>
                            <option value="approved">Approved</option>
                            <option value="changes_requested">Changes Requested</option>
                            <option value="rejected">Rejected</option>
                            <option value="processing">Processing</option>
                            <option value="delivered">Delivered</option>
                            <option value="live">Live</option>
                            <option value="failed">Failed</option>
                        </select>


                        <select
                            aria-label="Type"
                            value={filters.release_type ?? ''}
                            onChange={(event) =>
                                updateFilters({
                                    release_type:
                                        event.target
                                            .value,
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
                                updateFilters({
                                    sort:
                                        event.target
                                            .value,
                                })
                            }
                            className={selectClass}
                        >
                            <option value="latest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                            <option value="title_asc">Title A–Z</option>
                            <option value="title_desc">Title Z–A</option>
                            <option value="release_newest">Release date: newest</option>
                            <option value="release_oldest">Release date: oldest</option>
                        </select>

                        <div className="flex h-11 overflow-hidden rounded-xl border border-slate-200">
                            <button
                                type="button"
                                aria-label="List view"
                                onClick={() =>
                                    changeView('list')
                                }
                                className={`flex w-11 items-center justify-center ${
                                    viewMode === 'list'
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
                                    changeView('grid')
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
                                onClick={resetFilters}
                                className="h-11 rounded-xl px-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                            >
                                Clear
                            </button>
                        )}
                    </div>

                    {advancedOpen && (
                        <div id="release-advanced-filters" className="mt-3 grid gap-2.5 border-t border-slate-100 pt-3 sm:grid-cols-2 lg:grid-cols-4">
                        {labelOptions.length > 0 && (
                            <select
                                aria-label="Label"
                                value={filters.label_id ?? ''}
                                onChange={(event) =>
                                    updateFilters({
                                        label_id:
                                            event.target
                                                .value,
                                    })
                                }
                                className={`${selectClass} w-full`}
                            >
                                <option value="">Label: All</option>
                                {labelOptions.map(
                                    (label) => (
                                        <option
                                            key={label.id}
                                            value={label.id}
                                        >
                                            {label.name}
                                        </option>
                                    )
                                )}
                            </select>
                        )}

                        {(filterOptions.artists?.length ?? 0) > 0 && (
                            <select aria-label="Artist" value={filters.artist ?? ''} onChange={(event) => updateFilters({ artist: event.target.value })} className={`${selectClass} w-full`}>
                                <option value="">Artist: All</option>
                                {filterOptions.artists.map((name) => (
                                    <option key={name} value={name}>{name}</option>
                                ))}
                            </select>
                        )}

                        {(filterOptions.languages?.length ?? 0) > 0 && (
                            <select aria-label="Language" value={filters.language ?? ''} onChange={(event) => updateFilters({ language: event.target.value })} className={`${selectClass} w-full`}>
                                <option value="">Language: All</option>
                                {filterOptions.languages.map((name) => (
                                    <option key={name} value={name}>{name}</option>
                                ))}
                            </select>
                        )}

                        {(filterOptions.genres?.length ?? 0) > 0 && (
                            <select aria-label="Genre" value={filters.genre ?? ''} onChange={(event) => updateFilters({ genre: event.target.value })} className={`${selectClass} w-full`}>
                                <option value="">Genre: All</option>
                                {filterOptions.genres.map((name) => (
                                    <option key={name} value={name}>{name}</option>
                                ))}
                            </select>
                        )}

                            {labelOptions.length === 0 && (filterOptions.artists?.length ?? 0) === 0 && (
                                <p className="text-sm text-slate-500">No releases yet to filter by label or artist.</p>
                            )}
                        </div>
                    )}
                </section>

                {rows.length === 0 ? (
                    <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="p-8">
                            <EmptyState
                                title="No releases found"
                                description={
                                    hasFilters && filters.status && !filters.search && !filters.label_id && !filters.release_type && !filters.artist && !filters.language && !filters.genre
                                        ? `Nothing in "${pageTitle}" right now.`
                                        : hasFilters
                                        ? 'No release matches the selected filters. Clear the filters and try again.'
                                        : 'Create your first release and distribute your music worldwide.'
                                }
                                action={
                                    hasFilters ? (
                                        <button
                                            type="button"
                                            onClick={
                                                resetFilters
                                            }
                                            className="inline-flex rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white"
                                        >
                                            Clear Filters
                                        </button>
                                    ) : canCreate ? (
                                        <Link
                                            href={`${basePath}/create`}
                                            className="inline-flex rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white"
                                        >
                                            Create Release
                                        </Link>
                                    ) : null
                                }
                            />
                        </div>
                    </section>
                ) : viewMode === 'grid' ? (
                    <section className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                        {rows.map((release) => (
                            <ReleaseCard
                                key={
                                    release.id
                                }
                                release={
                                    release
                                }
                                basePath={
                                    basePath
                                }
                            />
                        ))}
                    </section>
                ) : (
                    <>
                    {/* MIXXTUNE_MOBILE_LIST_V1: phones get stacked cards instead of a wide table */}
                    <section className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:hidden">
                        {rows.map((release) => (
                            <ReleaseRow
                                key={release.id}
                                release={release}
                                basePath={basePath}
                                variant="card"
                            />
                        ))}
                    </section>

                    <section className="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr className="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
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

                                <tbody className="divide-y divide-slate-100">
                                    {rows.map(
                                        (
                                            release
                                        ) => (
                                            <ReleaseRow
                                                key={
                                                    release.id
                                                }
                                                release={
                                                    release
                                                }
                                                basePath={
                                                    basePath
                                                }
                                            />
                                        )
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                    </>
                )}

                <Pagination
                    links={releases?.links}
                />
            </div>
        </PanelLayout>
    );
}

function ReleaseCard({
    release,
    basePath,
}) {
    const status =
        normaliseStatus(release.status);

    const isDraft =
        status === 'draft';

    const editable =
        status === 'draft'
        || status === 'changes_requested';

    const trackCount = Math.max(
        Number(release.track_count ?? 0),
        0
    );

    const openPath = editable
        ? `${basePath}/${release.id}/edit`
        : `${basePath}/${release.id}`;

    const deleteDraft = () => {
        if (!isDraft) {
            return;
        }

        if (
            !window.confirm(
                `Delete draft "${release.title || 'Untitled Release'}"?\n\nThis action will remove the draft from My Releases.`
            )
        ) {
            return;
        }

        router.delete(
            `${basePath}/${release.id}`,
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <article
            className={`group overflow-hidden rounded-3xl border bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl ${
                statusBorder[status]
                ?? 'border-slate-200'
            }`}
        >
            <Link
                href={openPath}
                className="block"
            >
                <div className="relative aspect-square overflow-hidden bg-slate-100">
                    {release.artwork_path ? (
                        <img
                            src={`/storage/${release.artwork_path}`}
                            alt={release.title}
                            className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                        />
                    ) : (
                        <div className="flex h-full items-center justify-center">
                            <div className="flex h-24 w-24 items-center justify-center rounded-3xl bg-white/80 text-5xl text-violet-600 shadow-lg backdrop-blur">
                                ♫
                            </div>
                        </div>
                    )}

                    <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent p-5 pt-16">
                        <div className="flex items-end justify-between gap-4">
                            <div className="min-w-0">
                                <p className="truncate text-xl font-bold text-white">
                                    {release.title
                                        || 'Untitled Release'}
                                </p>

                                <p className="mt-1 truncate text-sm text-white/75">
                                    {release.primary_artist_name
                                        || 'Artist'}
                                </p>
                            </div>

                            <StatusBadge
                                status={status}
                            />
                        </div>
                    </div>
                </div>
            </Link>

            <div className="p-5">
                <div className="grid grid-cols-2 gap-3">
                    <InfoBox
                        label="Tracks"
                        value={`${trackCount} ${
                            trackCount === 1
                                ? 'Track'
                                : 'Tracks'
                        }`}
                    />

                    <InfoBox
                        label="Release Date"
                        value={formatDate(
                            release.digital_release_date
                        )}
                    />

                    <InfoBox
                        label="UPC"
                        value={
                            release.upc
                            || 'Pending'
                        }
                    />

                    <InfoBox
                        label="Status"
                        value={status}
                    />
                </div>

                <div className="mt-4 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <Link
                        href={openPath}
                        title={
                            editable
                                ? 'Edit release'
                                : 'View release'
                        }
                        aria-label={
                            editable
                                ? 'Edit release'
                                : 'View release'
                        }
                        className={`inline-flex h-10 w-10 items-center justify-center rounded-xl border transition ${
                            editable
                                ? 'border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700'
                        }`}
                    >
                        {editable ? (
                            <PencilIcon />
                        ) : (
                            <EyeIcon />
                        )}
                    </Link>

                    {isDraft && (
                        <button
                            type="button"
                            onClick={deleteDraft}
                            title="Delete draft"
                            aria-label="Delete draft"
                            className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100"
                        >
                            <TrashIcon />
                        </button>
                    )}
                </div>
            </div>
        </article>
    );
}


function ReleaseRow({
    release,
    basePath,
    variant = 'row',
}) {
    const status =
        normaliseStatus(release.status);

    const isDraft =
        status === 'draft';

    const editable =
        status === 'draft'
        || status === 'changes_requested';

    const trackCount = Math.max(
        Number(release.track_count ?? 0),
        0
    );

    const artwork =
        release.artwork_url
        || release.artwork_path
        || release.cover_art_url
        || release.cover_art_path
        || release.cover_path
        || null;

    const artworkSrc =
        artwork
            ? (
                String(artwork).startsWith('http://')
                || String(artwork).startsWith('https://')
                || String(artwork).startsWith('/')
                    ? artwork
                    : `/storage/${artwork}`
            )
            : null;

    const displayReleaseDate =
        release.digital_release_date
        || release.original_release_date
        || null;

    const viewPath =
        `${basePath}/${release.id}`;

    const editPath =
        `${basePath}/${release.id}/edit`;

    const deleteDraft = () => {
        if (!isDraft) {
            return;
        }

        if (
            !window.confirm(
                `Delete draft "${release.title || 'Untitled Release'}"?\n\nThis action will remove the draft from My Releases.`
            )
        ) {
            return;
        }

        router.delete(
            `${basePath}/${release.id}`,
            {
                preserveScroll: true,
            }
        );
    };

    const storesTotal = Math.max(
        Number(release.stores_total ?? 0),
        0
    );

    const storesLive = Math.min(
        Math.max(
            Number(release.stores_live ?? 0),
            0
        ),
        storesTotal
    );

    if (variant === 'card') {
        return (
            <div className="flex gap-3 p-3.5">
                <Link
                    href={viewPath}
                    className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 shadow-sm ring-1 ring-slate-200"
                >
                    {artworkSrc ? (
                        <img src={artworkSrc} alt={release.title || 'Release artwork'} className="h-full w-full object-cover" loading="lazy" />
                    ) : (
                        <span className="text-2xl text-violet-600" aria-hidden="true">♫</span>
                    )}
                </Link>

                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <Link href={viewPath} className="min-w-0 truncate text-[15px] font-bold text-slate-900">
                            {release.title || 'Untitled Release'}
                        </Link>
                        <StatusPill status={status} />
                    </div>

                    <p className="mt-0.5 truncate text-[13px] text-slate-500">
                        {release.primary_artist_name || 'Artist'} · <span className="capitalize">{release.release_type || 'single'}</span> · {trackCount} {trackCount === 1 ? 'track' : 'tracks'}
                    </p>

                    <p className="mt-0.5 truncate text-xs text-slate-500">
                        {release.label_name || '—'} · {displayReleaseDate ? formatDate(displayReleaseDate) : 'Not scheduled'}
                    </p>

                    <div className="mt-2 flex items-end justify-between gap-2">
                        <div className="min-w-0">
                            <p className="truncate font-mono text-xs text-slate-600">
                                {release.upc ? `UPC ${release.upc}` : 'UPC pending'}
                            </p>
                            {storesTotal > 0 && (
                                <p className="mt-0.5 text-xs text-slate-500">
                                    {storesLive} / {storesTotal} stores live
                                </p>
                            )}
                        </div>

                        <div className="flex shrink-0 items-center gap-1.5">
                            {editable && (
                                <Link href={editPath} aria-label="Edit release" className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-violet-200 bg-violet-50 text-violet-700">
                                    <PencilIcon />
                                </Link>
                            )}
                            {isDraft && (
                                <button type="button" onClick={deleteDraft} aria-label="Delete draft" className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600">
                                    <TrashIcon />
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <tr className="transition hover:bg-slate-50">
            <td className="px-5 py-3">
                <div className="flex min-w-[300px] items-center gap-3.5">
                    <Link
                        href={viewPath}
                        className="flex h-[60px] w-[60px] shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 shadow-sm ring-1 ring-slate-200"
                    >
                        {artworkSrc ? (
                            <img
                                src={artworkSrc}
                                alt={
                                    release.title
                                    || 'Release artwork'
                                }
                                className="h-full w-full object-cover"
                                loading="lazy"
                            />
                        ) : (
                            <span className="text-2xl text-violet-600" aria-hidden="true">
                                ♫
                            </span>
                        )}
                    </Link>

                    <div className="min-w-0">
                        <Link
                            href={viewPath}
                            className="block max-w-[280px] truncate text-[15px] font-bold text-slate-900 transition hover:text-violet-700"
                            title={
                                release.title
                                || 'Untitled Release'
                            }
                        >
                            {release.title
                                || 'Untitled Release'}
                        </Link>

                        <p className="mt-1 max-w-[280px] truncate text-[13px] text-slate-500">
                            {release.primary_artist_name
                                || 'Artist'}
                            {' · '}
                            <span className="capitalize">
                                {release.release_type
                                    || 'single'}
                            </span>
                            {' · '}
                            {trackCount}
                            {' '}
                            {trackCount === 1
                                ? 'track'
                                : 'tracks'}
                        </p>
                    </div>
                </div>
            </td>

            <td className="px-5 py-3">
                <div
                    title={release.label_name || ''}
                    className="max-w-[200px] truncate text-sm text-slate-700"
                >
                    {release.label_name || '—'}
                </div>
            </td>

            <td className="px-5 py-3">
                <span className="whitespace-nowrap text-sm text-slate-700">
                    {displayReleaseDate
                        ? formatDate(
                            displayReleaseDate
                        )
                        : 'Not scheduled'}
                </span>
            </td>

            <td className="px-5 py-3">
                {release.upc ? (
                    <span className="whitespace-nowrap font-mono text-[13px] text-slate-700">
                        {release.upc}
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
                                className={`h-full rounded-full ${
                                    storesLive === storesTotal
                                        ? 'bg-emerald-600'
                                        : 'bg-blue-600'
                                }`}
                                style={{
                                    width: `${Math.round(
                                        (storesLive / storesTotal) * 100
                                    )}%`,
                                }}
                            />
                        </div>
                        <p className="mt-1.5 text-xs text-slate-500">
                            {storesLive} / {storesTotal} stores
                        </p>
                    </div>
                ) : (
                    <span className="text-sm text-slate-400">
                        —
                    </span>
                )}
            </td>

            <td className="px-5 py-3">
                <StatusPill status={status} />
            </td>

            <td className="px-5 py-3">
                <div className="flex items-center justify-end gap-1.5">
                    <Link
                        href={viewPath}
                        title="View release"
                        aria-label="View release"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700"
                    >
                        <EyeIcon />
                    </Link>

                    {editable && (
                        <Link
                            href={editPath}
                            title="Edit release"
                            aria-label="Edit release"
                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-violet-200 bg-violet-50 text-violet-700 transition hover:bg-violet-100"
                        >
                            <PencilIcon />
                        </Link>
                    )}

                    {isDraft && (
                        <button
                            type="button"
                            onClick={deleteDraft}
                            title="Delete draft"
                            aria-label="Delete draft"
                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100"
                        >
                            <TrashIcon />
                        </button>
                    )}
                </div>
            </td>
        </tr>
    );
}

function ReleaseStatusIcon({
    status,
}) {
    const value =
        normaliseStatus(status);

    if (
        [
            'live',
            'approved',
            'delivered',
        ].includes(value)
    ) {
        return (
            <span
                title={value}
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
            'submitted',
            'processing',
            'under_review',
            'in_review',
        ].includes(value)
    ) {
        return (
            <span
                title={value}
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

    if (
        value === 'draft'
    ) {
        return (
            <span
                title="draft"
                className="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
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
            'changes_requested',
            'takedown',
            'taken_down',
        ].includes(value)
    ) {
        return (
            <span
                title={value}
                className="flex h-9 w-9 items-center justify-center rounded-full bg-rose-500 text-white shadow-sm"
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
            title={value || 'status'}
            aria-label={value || 'status'}
            className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-500"
        >
            <span className="h-2.5 w-2.5 rounded-full bg-current" />
        </span>
    );
}

function EyeIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            className="h-4 w-4"
            aria-hidden="true"
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"
            />
            <circle
                cx="12"
                cy="12"
                r="2.75"
            />
        </svg>
    );
}


function PencilIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            className="h-4 w-4"
            aria-hidden="true"
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="m14.7 5.3 4 4M4.5 19.5l4.2-.9L19 8.3a2.1 2.1 0 0 0-3-3L5.7 15.6l-1.2 3.9Z"
            />
        </svg>
    );
}


function TrashIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            className="h-4 w-4"
            aria-hidden="true"
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M4.5 7.5h15M9 7.5V4.75h6V7.5M7 7.5l.75 12h8.5L17 7.5M9.75 10.5v6M14.25 10.5v6"
            />
        </svg>
    );
}


function InfoBox({
    label,
    value,
}) {
    return (
        <div className="min-w-0 rounded-xl bg-slate-50 p-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                {label}
            </p>

            <p className="mt-1 truncate text-sm font-semibold capitalize text-slate-800">
                {value}
            </p>
        </div>
    );
}

function Pagination({
    links,
}) {
    if (
        !Array.isArray(links)
        || links.length <= 3
    ) {
        return null;
    }

    return (
        <nav className="flex flex-wrap justify-center gap-2">
            {links.map(
                (link, index) => (
                    <Link
                        key={index}
                        href={link.url || '#'}
                        preserveScroll
                        className={`rounded-xl px-4 py-2.5 text-sm transition ${
                            link.active
                                ? 'bg-violet-600 font-semibold text-white shadow-md shadow-violet-100'
                                : 'border border-slate-200 bg-white font-medium text-slate-600 hover:border-violet-300 hover:text-violet-700'
                        } ${
                            !link.url
                                ? 'pointer-events-none opacity-40'
                                : ''
                        }`}
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

/* MIXXTUNE_RELEASES_LIST_V2 */
const statusPillMeta = {
    live: ['Live', 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
    delivered: ['Delivered', 'bg-teal-50 text-teal-800 ring-teal-200'],
    approved: ['Approved', 'bg-blue-50 text-blue-800 ring-blue-200'],
    submitted: ['In Review', 'bg-amber-50 text-amber-800 ring-amber-200'],
    processing: ['Processing', 'bg-amber-50 text-amber-800 ring-amber-200'],
    changes_requested: ['Changes Requested', 'bg-orange-50 text-orange-800 ring-orange-200'],
    rejected: ['Rejected', 'bg-rose-50 text-rose-800 ring-rose-200'],
    failed: ['Failed', 'bg-rose-50 text-rose-800 ring-rose-200'],
    takedown_requested: ['Takedown Requested', 'bg-slate-100 text-slate-700 ring-slate-200'],
    taken_down: ['Taken Down', 'bg-slate-100 text-slate-700 ring-slate-200'],
    draft: ['Draft', 'bg-slate-100 text-slate-700 ring-slate-200'],
};

function StatusPill({
    status,
}) {
    const value = normaliseStatus(status);
    const [label, classes] =
        statusPillMeta[value]
        ?? [
            value.replace(/_/g, ' '),
            'bg-slate-100 text-slate-700 ring-slate-200',
        ];

    return (
        <span
            className={`inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold capitalize ring-1 ring-inset ${classes}`}
        >
            {label}
        </span>
    );
}
