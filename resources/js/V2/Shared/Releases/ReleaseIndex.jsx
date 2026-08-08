import { Head, Link, router, usePage } from '@inertiajs/react';

import {
    useEffect,
    useMemo,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageHeader from '@/V2/Shared/Components/PageHeader';
import StatusBadge from '@/V2/Shared/Components/StatusBadge';
import EmptyState from '@/V2/Shared/Components/EmptyState';

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

const statusCardMeta = {
    total: {
        label: 'Total Releases',
        icon: '♫',
        description: 'All catalogue releases',
        background:
            'from-slate-900 via-slate-800 to-slate-700',
    },

    draft: {
        label: 'Draft',
        icon: '✎',
        description: 'Continue unfinished releases',
        background:
            'from-violet-600 via-purple-600 to-indigo-600',
    },

    submitted: {
        label: 'In Review',
        icon: '⌛',
        description: 'Submitted for approval',
        background:
            'from-blue-600 via-cyan-600 to-sky-500',
    },

    approved: {
        label: 'Approved',
        icon: '✓',
        description: 'Ready for distribution',
        background:
            'from-emerald-600 via-teal-600 to-cyan-600',
    },

    rejected: {
        label: 'Needs Attention',
        icon: '!',
        description: 'Review requested changes',
        background:
            'from-rose-600 via-red-600 to-orange-500',
    },
};

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
        useState('grid');

    const [searchValue, setSearchValue] =
        useState(filters.search ?? '');

    
    const basePath =
        role === 'artist'
            ? '/releases'
            : '/v2/releases';

    const canCreate =
        role === 'artist'
        || permissions.includes(
            'releases.create'
        );

    useEffect(() => {
        const saved =
            window.localStorage.getItem(
                viewStorageKey
            );

        if (
            saved === 'grid'
            || saved === 'list'
        ) {
            setViewMode(saved);
        }
    }, [viewStorageKey]);

    const changeView = (mode) => {
        setViewMode(mode);

        window.localStorage.setItem(
            viewStorageKey,
            mode
        );
    };

    const updateFilters = (
        changes,
        options = {}
    ) => {
        router.get(
            basePath,
            {
                ...filters,
                ...changes,
            },
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
        || (
            filters.sort
            && filters.sort !== 'latest'
        );

    const cards = useMemo(
        () => [
            {
                key: 'total',
                value:
                    statusCounts.total ?? 0,
            },
            {
                key: 'draft',
                value:
                    statusCounts.draft ?? 0,
            },
            {
                key: 'submitted',
                value:
                    statusCounts.submitted
                    ?? 0,
            },
            {
                key: 'approved',
                value:
                    statusCounts.approved
                    ?? 0,
            },
            {
                key: 'rejected',
                value:
                    statusCounts.rejected
                    ?? 0,
            },
        ],
        [statusCounts]
    );

    return (
        <PanelLayout
            role={role}
            title="My Releases"
            subtitle="Manage your complete music catalogue"
        >
            <Head title="My Releases" />

            <div className="space-y-6">
                <PageHeader
                    title="My Releases"
                    subtitle="Create, continue and monitor every release from one place."
                    actions={
                        canCreate ? (
                            <Link
                                href={`${basePath}/create`}
                                className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-200 transition hover:-translate-y-0.5 hover:bg-violet-700"
                            >
                                <span className="text-lg leading-none">
                                    +
                                </span>

                                Create Release
                            </Link>
                        ) : null
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {cards.map((card) => {
                        const meta =
                            statusCardMeta[
                                card.key
                            ];

                        const active =
                            card.key === 'total'
                                ? !filters.status
                                : filters.status
                                    === card.key;

                        return (
                            <button
                                key={card.key}
                                type="button"
                                onClick={() =>
                                    updateFilters({
                                        status:
                                            card.key
                                            === 'total'
                                                ? ''
                                                : card.key,
                                    })
                                }
                                className={`group relative overflow-hidden rounded-2xl bg-gradient-to-br p-5 text-left text-white shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-xl ${
                                    meta.background
                                } ${
                                    active
                                        ? 'ring-4 ring-violet-100'
                                        : ''
                                }`}
                            >
                                <div className="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10 transition group-hover:scale-125" />

                                <div className="relative flex items-start justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-white/80">
                                            {
                                                meta.label
                                            }
                                        </p>

                                        <p className="mt-2 text-3xl font-black">
                                            {
                                                card.value
                                            }
                                        </p>
                                    </div>

                                    <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 text-xl backdrop-blur-sm">
                                        {
                                            meta.icon
                                        }
                                    </span>
                                </div>

                                <p className="relative mt-4 text-xs text-white/70">
                                    {
                                        meta.description
                                    }
                                </p>
                            </button>
                        );
                    })}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                    <div className="flex flex-col gap-4 xl:flex-row xl:items-center">
                        <div className="relative min-w-0 flex-1">
                            <span className="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">
                                ⌕
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
                                placeholder="Search by release title, artist or UPC..."
                                className="w-full rounded-xl border border-slate-300 py-3 pl-11 pr-4 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
                            />
                        </div>

                        <button
                            type="button"
                            onClick={submitSearch}
                            className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                        >
                            Search
                        </button>

                        <select
                            value={
                                filters.status
                                ?? ''
                            }
                            onChange={(event) =>
                                updateFilters({
                                    status:
                                        event.target
                                            .value,
                                })
                            }
                            className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 outline-none focus:border-violet-500"
                        >
                            <option value="">
                                All statuses
                            </option>

                            <option value="draft">
                                Draft
                            </option>

                            <option value="submitted">
                                Submitted
                            </option>

                            <option value="approved">
                                Approved
                            </option>

                            <option value="changes_requested">
                                Changes Requested
                            </option>

                            <option value="rejected">
                                Rejected
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

                            <option value="failed">
                                Failed
                            </option>
                        </select>

                        <select
                            value={
                                filters.sort
                                ?? 'latest'
                            }
                            onChange={(event) =>
                                updateFilters({
                                    sort:
                                        event.target
                                            .value,
                                })
                            }
                            className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 outline-none focus:border-violet-500"
                        >
                            <option value="latest">
                                Latest first
                            </option>

                            <option value="oldest">
                                Oldest first
                            </option>

                            <option value="title_asc">
                                Title A–Z
                            </option>

                            <option value="title_desc">
                                Title Z–A
                            </option>
                        </select>

                        <div className="flex rounded-xl border border-slate-300 bg-slate-50 p-1">
                            <button
                                type="button"
                                onClick={() =>
                                    changeView(
                                        'grid'
                                    )
                                }
                                className={`rounded-lg px-4 py-2 text-sm font-semibold transition ${
                                    viewMode
                                    === 'grid'
                                        ? 'bg-white text-violet-700 shadow-sm'
                                        : 'text-slate-500'
                                }`}
                            >
                                Grid
                            </button>

                            <button
                                type="button"
                                onClick={() =>
                                    changeView(
                                        'list'
                                    )
                                }
                                className={`rounded-lg px-4 py-2 text-sm font-semibold transition ${
                                    viewMode
                                    === 'list'
                                        ? 'bg-white text-violet-700 shadow-sm'
                                        : 'text-slate-500'
                                }`}
                            >
                                List
                            </button>
                        </div>

                        {hasFilters && (
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="rounded-xl px-4 py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                            >
                                Clear
                            </button>
                        )}
                    </div>
                </section>

                {rows.length === 0 ? (
                    <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div className="p-8">
                            <EmptyState
                                title="No releases found"
                                description={
                                    hasFilters
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
                    <section className="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
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
                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                                            <th className="px-5 py-4">
                                                Tracks
                                            </th>

                                            <th className="px-5 py-4">
                                                Status
                                            </th>

                                            <th className="px-5 py-4">
                                                Release
                                            </th>

                                            <th className="px-5 py-4">
                                                Release Date
                                            </th>

                                            <th className="px-5 py-4">
                                                UPC
                                            </th>

                                            <th className="px-5 py-4 text-right">
                                                Action
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
            `/v2/releases/${release.id}`,
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
                <div className="relative aspect-[16/10] overflow-hidden bg-gradient-to-br from-slate-100 to-slate-200">
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
            `/v2/releases/${release.id}`,
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <tr className="transition hover:bg-slate-50">
            <td className="px-5 py-4">
                <div className="inline-flex items-center gap-2 whitespace-nowrap">
                    <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-sm text-violet-600">
                        ♫
                    </span>

                    <span className="text-sm font-semibold text-slate-700">
                        {trackCount}
                        {' '}
                        {trackCount === 1
                            ? 'Track'
                            : 'Tracks'}
                    </span>
                </div>
            </td>

            <td className="px-5 py-4">
                <StatusBadge
                    status={status}
                />
            </td>

            <td className="px-5 py-4">
                <div className="flex items-center gap-4">
                    <Link
                        href={openPath}
                        className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100"
                    >
                        {release.artwork_path ? (
                            <img
                                src={`/storage/${release.artwork_path}`}
                                alt={release.title}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <span className="text-2xl text-violet-600">
                                ♫
                            </span>
                        )}
                    </Link>

                    <div className="min-w-0">
                        <Link
                            href={openPath}
                            className="block max-w-64 truncate font-semibold text-slate-900 transition hover:text-violet-700"
                        >
                            {release.title
                                || 'Untitled Release'}
                        </Link>

                        <div className="mt-1 max-w-64 truncate text-sm text-slate-500">
                            {release.primary_artist_name
                                || 'Artist'}

                            {' · '}

                            {release.release_type
                                || 'release'}
                        </div>
                    </div>
                </div>
            </td>

            <td className="px-5 py-4 text-sm text-slate-600">
                {formatDate(
                    release.digital_release_date
                )}
            </td>

            <td className="px-5 py-4 text-sm font-medium text-slate-600">
                {release.upc || 'Pending'}
            </td>

            <td className="px-5 py-4">
                <div className="flex items-center justify-end gap-2">
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
                        className={`inline-flex h-9 w-9 items-center justify-center rounded-lg border transition ${
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
