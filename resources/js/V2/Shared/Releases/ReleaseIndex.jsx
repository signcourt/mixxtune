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

const progressClass = {
    draft:
        'from-violet-500 to-indigo-500',
    submitted:
        'from-blue-500 to-cyan-500',
    approved:
        'from-emerald-500 to-teal-500',
    rejected:
        'from-rose-500 to-orange-500',
    changes_requested:
        'from-amber-500 to-orange-500',
    processing:
        'from-amber-500 to-yellow-500',
    delivered:
        'from-violet-500 to-purple-500',
    live:
        'from-green-500 to-emerald-500',
    failed:
        'from-red-500 to-rose-500',
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
                                            Release
                                        </th>

                                        <th className="px-5 py-4">
                                            UPC
                                        </th>

                                        <th className="px-5 py-4">
                                            Release Date
                                        </th>

                                        <th className="px-5 py-4">
                                            Status
                                        </th>

                                        <th className="px-5 py-4">
                                            Progress
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

    const editable = [
        'draft',
        'changes_requested',
        'rejected',
    ].includes(status);

    const percentage = Math.min(
        Math.max(
            Number(
                release.completion_percentage
                ?? 0
            ),
            0
        ),
        100
    );

    return (
        <article
            className={`group overflow-hidden rounded-3xl border bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl ${
                statusBorder[status]
                ?? 'border-slate-200'
            }`}
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

            <div className="space-y-5 p-5">
                <div className="grid grid-cols-2 gap-3">
                    <InfoBox
                        label="Release Type"
                        value={
                            release.release_type
                            || 'Release'
                        }
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
                        label="Release ID"
                        value={
                            release.public_id
                            || `#${release.id}`
                        }
                    />
                </div>

                <div>
                    <div className="mb-2 flex items-center justify-between text-xs">
                        <span className="font-semibold text-slate-500">
                            Release completion
                        </span>

                        <span className="font-bold text-slate-900">
                            {percentage}%
                        </span>
                    </div>

                    <div className="h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div
                            className={`h-full rounded-full bg-gradient-to-r transition-all duration-500 ${
                                progressClass[
                                    status
                                ]
                                ?? 'from-violet-500 to-indigo-500'
                            }`}
                            style={{
                                width:
                                    `${percentage}%`,
                            }}
                        />
                    </div>
                </div>

                <div className="flex items-center gap-3 border-t border-slate-100 pt-4">
                    {editable ? (
                        <Link
                            href={
                            editable
                                ? `${basePath}/${release.id}/edit`
                                : `${basePath}/${release.id}`
                        }
                            className="inline-flex flex-1 items-center justify-center rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-violet-700"
                        >
                            Continue Release
                        </Link>
                    ) : (
                        <Link
                            href={`${basePath}/${release.id}`}
                            className="inline-flex flex-1 items-center justify-center rounded-xl bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
                        >
                            View Details
                        </Link>
                    )}

                    <Link
                        href={
                            editable
                                ? `${basePath}/${release.id}/edit`
                                : `${basePath}/${release.id}`
                        }
                        className="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
                        title="Open release"
                    >
                        →
                    </Link>
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

    const editable = [
        'draft',
        'changes_requested',
        'rejected',
    ].includes(status);

    const percentage = Math.min(
        Math.max(
            Number(
                release.completion_percentage
                ?? 0
            ),
            0
        ),
        100
    );

    return (
        <tr className="transition hover:bg-slate-50">
            <td className="px-5 py-4">
                <div className="flex items-center gap-4">
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                        {release.artwork_path ? (
                            <img
                                src={`/storage/${release.artwork_path}`}
                                alt={
                                    release.title
                                }
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <span className="text-2xl text-violet-600">
                                ♫
                            </span>
                        )}
                    </div>

                    <div className="min-w-0">
                        <div className="max-w-64 truncate font-semibold text-slate-900">
                            {release.title
                                || 'Untitled Release'}
                        </div>

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

            <td className="px-5 py-4 text-sm font-medium text-slate-600">
                {release.upc || 'Pending'}
            </td>

            <td className="px-5 py-4 text-sm text-slate-600">
                {formatDate(
                    release.digital_release_date
                )}
            </td>

            <td className="px-5 py-4">
                <StatusBadge
                    status={status}
                />
            </td>

            <td className="px-5 py-4">
                <div className="min-w-36">
                    <div className="mb-1.5 flex justify-between text-xs text-slate-500">
                        <span>
                            Completion
                        </span>

                        <span className="font-semibold text-slate-700">
                            {percentage}%
                        </span>
                    </div>

                    <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                            className={`h-full rounded-full bg-gradient-to-r ${
                                progressClass[
                                    status
                                ]
                                ?? 'from-violet-500 to-indigo-500'
                            }`}
                            style={{
                                width:
                                    `${percentage}%`,
                            }}
                        />
                    </div>
                </div>
            </td>

            <td className="px-5 py-4 text-right">
                {editable ? (
                    <Link
                        href={`${basePath}/${release.id}/edit`}
                        className="inline-flex rounded-xl bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-700 transition hover:bg-violet-100"
                    >
                        Continue
                    </Link>
                ) : (
                    <Link
                        href={`${basePath}/${release.id}`}
                        className="inline-flex rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200"
                    >
                        View
                    </Link>
                )}
            </td>
        </tr>
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
