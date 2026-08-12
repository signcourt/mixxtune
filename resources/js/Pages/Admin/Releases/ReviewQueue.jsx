import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

const badgeStyles = {
    submitted: 'bg-blue-50 text-blue-700 ring-blue-200',
    approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    rejected: 'bg-red-50 text-red-700 ring-red-200',
    changes_requested: 'bg-amber-50 text-amber-700 ring-amber-200',
};

function formatDate(value) {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export default function ReviewQueue({
    releases,
    filters = {},
    statusCounts = {},
    flash = {},
    errors: pageErrors = {},
}) {
    const rows = releases?.data ?? [];
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedRelease, setSelectedRelease] = useState(null);
    const [actionType, setActionType] = useState('');

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        rejection_reason: '',
        review_notes: '',
    });

    const applyFilters = (nextStatus = filters.status ?? '') => {
        router.get(
            '/release-reviews',
            {
                status: nextStatus || undefined,
                search: search || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const submitSearch = (event) => {
        event.preventDefault();
        applyFilters();
    };

    const approve = (release) => {
        if (!window.confirm(`Approve "${release.title}"?`)) {
            return;
        }

        router.post(`/releases/${release.id}/approve`, {}, {
            preserveScroll: true,
        });
    };

    const openAction = (release, type) => {
        reset();
        setSelectedRelease(release);
        setActionType(type);
    };

    const closeAction = () => {
        reset();
        setSelectedRelease(null);
        setActionType('');
    };

    const submitAction = (event) => {
        event.preventDefault();

        if (!selectedRelease) {
            return;
        }

        const endpoint =
            actionType === 'reject'
                ? `/releases/${selectedRelease.id}/reject`
                : `/releases/${selectedRelease.id}/request-changes`;

        post(endpoint, {
            preserveScroll: true,
            onSuccess: closeAction,
        });
    };

    return (
        <AdminLayout title="Release Reviews">
            <Head title="Release Reviews" />

            <div className="space-y-6">
                {flash?.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {(pageErrors?.review || pageErrors?.submission) && (
                    <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
                        {pageErrors.review || pageErrors.submission}
                    </div>
                )}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Release Review Queue
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Review submitted releases, metadata, audio and delivery settings.
                        </p>
                    </div>

                    <Link
                        href="/releases"
                        className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Back to Catalogue
                    </Link>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-wrap gap-2">
                            {[
                                ['all', 'All'],
                                ['submitted', 'Submitted'],
                                ['changes_requested', 'Changes Requested'],
                                ['approved', 'Approved'],
                                ['rejected', 'Rejected'],
                            ].map(([value, label]) => {
                                const active =
                                    value === 'all'
                                        ? !filters.status
                                        : filters.status === value;

                                return (
                                    <button
                                        key={value}
                                        type="button"
                                        onClick={() =>
                                            applyFilters(
                                                value === 'all' ? '' : value
                                            )
                                        }
                                        className={`rounded-xl px-4 py-2 text-sm font-semibold ${
                                            active
                                                ? 'bg-slate-900 text-white'
                                                : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                                        }`}
                                    >
                                        {label}
                                        <span className="ml-2 opacity-70">
                                            {statusCounts[value] ?? 0}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        <form
                            onSubmit={submitSearch}
                            className="flex flex-col gap-3 sm:flex-row"
                        >
                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search title, artist, catalogue or UPC..."
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />

                            <button
                                type="submit"
                                className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                            >
                                Search
                            </button>

                            {(filters.search || filters.status) && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearch('');
                                        router.get('/release-reviews');
                                    }}
                                    className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                                >
                                    Reset
                                </button>
                            )}
                        </form>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="font-semibold text-slate-900">
                            Submitted Releases
                        </h2>
                        <p className="mt-1 text-xs text-slate-500">
                            {releases?.total ?? 0} review items
                        </p>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <div className="text-4xl">✓</div>
                            <h3 className="mt-4 text-lg font-semibold text-slate-900">
                                Review queue is empty
                            </h3>
                            <p className="mt-2 text-sm text-slate-500">
                                Submitted releases will appear here.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {rows.map((release) => (
                                <div
                                    key={release.id}
                                    className="grid gap-4 px-5 py-5 lg:grid-cols-[minmax(220px,1.7fr)_minmax(140px,1fr)_110px_100px_minmax(250px,1.4fr)] lg:items-center"
                                >
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-semibold text-slate-900">
                                            {release.title}
                                        </div>
                                        <div className="mt-1 truncate text-xs text-slate-500">
                                            {release.primary_artist_name || 'Unknown Artist'}
                                        </div>
                                    </div>

                                    <div className="min-w-0">
                                        <div className="truncate text-sm text-slate-700">
                                            {release.label?.name ?? 'No Label'}
                                        </div>
                                        <div className="mt-1 text-xs text-slate-500">
                                            {release.release_type}
                                        </div>
                                    </div>

                                    <div className="text-sm text-slate-600">
                                        {release.tracks?.length ?? 0} Tracks
                                    </div>

                                    <div>
                                        <span
                                            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ring-1 ${
                                                badgeStyles[release.status] ??
                                                'bg-slate-100 text-slate-700 ring-slate-200'
                                            }`}
                                        >
                                            {String(release.status).replaceAll('_', ' ')}
                                        </span>
                                        <div className="mt-2 text-xs text-slate-400">
                                            {formatDate(release.submitted_at)}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-2 lg:justify-end">
                                        <Link
                                            href={`/releases/${release.id}/edit`}
                                            className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Edit Release
                                        </Link>

                                        {release.artwork_path && (
                                            <a
                                                href={`/releases/${release.id}/download-artwork`}
                                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Download Artwork
                                            </a>
                                        )}

                                        {(release.tracks ?? []).map((track) =>
                                            track.audio_path ? (
                                                <a
                                                    key={track.id}
                                                    href={`/tracks/${track.id}/download-audio`}
                                                    className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                    title={track.title}
                                                >
                                                    WAV {track.track_number ?? ''}
                                                </a>
                                            ) : null
                                        )}

                                        {release.status === 'submitted' && (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={() => approve(release)}
                                                    className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700"
                                                >
                                                    Approve
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        openAction(
                                                            release,
                                                            'changes'
                                                        )
                                                    }
                                                    className="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-600"
                                                >
                                                    Request Changes
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        openAction(
                                                            release,
                                                            'reject'
                                                        )
                                                    }
                                                    className="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700"
                                                >
                                                    Reject
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    <Pagination links={releases?.links ?? []} />
                </div>
            </div>

            {selectedRelease && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
                    <div className="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="text-xl font-semibold text-slate-900">
                                    {actionType === 'reject'
                                        ? 'Reject Release'
                                        : 'Request Changes'}
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    {selectedRelease.title}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={closeAction}
                                className="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={submitAction} className="mt-6">
                            <label className="mb-2 block text-sm font-medium text-slate-700">
                                {actionType === 'reject'
                                    ? 'Rejection Reason'
                                    : 'Review Notes'}
                            </label>

                            <textarea
                                value={
                                    actionType === 'reject'
                                        ? data.rejection_reason
                                        : data.review_notes
                                }
                                onChange={(event) =>
                                    setData(
                                        actionType === 'reject'
                                            ? 'rejection_reason'
                                            : 'review_notes',
                                        event.target.value
                                    )
                                }
                                className="min-h-40 w-full rounded-xl border-slate-300"
                                placeholder={
                                    actionType === 'reject'
                                        ? 'Explain why this release is being rejected...'
                                        : 'Explain what needs to be corrected...'
                                }
                            />

                            {(errors.rejection_reason ||
                                errors.review_notes) && (
                                <p className="mt-2 text-sm text-red-600">
                                    {errors.rejection_reason ||
                                        errors.review_notes}
                                </p>
                            )}

                            <div className="mt-6 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={closeAction}
                                    className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className={`rounded-xl px-5 py-3 text-sm font-semibold text-white disabled:opacity-50 ${
                                        actionType === 'reject'
                                            ? 'bg-red-600 hover:bg-red-700'
                                            : 'bg-amber-500 hover:bg-amber-600'
                                    }`}
                                >
                                    {processing
                                        ? 'Saving...'
                                        : actionType === 'reject'
                                          ? 'Reject Release'
                                          : 'Send Back for Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

function Pagination({ links = [] }) {
    if (!Array.isArray(links) || links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-center gap-2 border-t border-slate-200 px-5 py-5">
            {links.map((link, index) => {
                const label = String(link.label ?? '')
                    .replace('&laquo;', '‹')
                    .replace('&raquo;', '›');

                if (!link.url) {
                    return (
                        <span
                            key={`${label}-${index}`}
                            className="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-400"
                        >
                            {label}
                        </span>
                    );
                }

                return (
                    <button
                        key={`${label}-${index}`}
                        type="button"
                        onClick={() =>
                            router.get(
                                link.url,
                                {},
                                {
                                    preserveState: true,
                                    preserveScroll: true,
                                }
                            )
                        }
                        className={`rounded-lg border px-3 py-2 text-sm font-semibold ${
                            link.active
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                        }`}
                    >
                        {label}
                    </button>
                );
            })}
        </div>
    );
}
