import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

const statusStyles = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-blue-50 text-blue-700',
    changes_requested: 'bg-amber-50 text-amber-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-700',
};

export default function Catalogue({
    releases,
    filters = {},
    counts = {},
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (status = filters.status ?? '') => {
        router.get(
            '/catalogue',
            {
                search: search || undefined,
                status: status || undefined,
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

    const resetFilters = () => {
        setSearch('');
        router.get('/catalogue');
    };

    return (
        <AdminLayout title="Catalogue">
            <Head title="Catalogue" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            Catalogue
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Search and manage releases, tracks and delivery status.
                        </p>
                    </div>

                    <Link
                        href="/releases/create"
                        className="inline-flex rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white"
                    >
                        + Create Release
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Stat label="Total Releases" value={counts.all ?? 0} />
                    <Stat label="Draft" value={counts.draft ?? 0} />
                    <Stat label="Submitted" value={counts.submitted ?? 0} />
                    <Stat label="Approved" value={counts.approved ?? 0} />
                    <Stat label="Rejected" value={counts.rejected ?? 0} />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-wrap gap-2">
                            {[
                                ['', 'All'],
                                ['draft', 'Draft'],
                                ['submitted', 'Submitted'],
                                ['changes_requested', 'Changes Requested'],
                                ['approved', 'Approved'],
                                ['rejected', 'Rejected'],
                            ].map(([value, label]) => (
                                <button
                                    key={label}
                                    type="button"
                                    onClick={() => applyFilters(value)}
                                    className={`rounded-xl px-4 py-2 text-sm font-semibold ${
                                        (filters.status ?? '') === value
                                            ? 'bg-slate-900 text-white'
                                            : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>

                        <form
                            onSubmit={submitSearch}
                            className="flex flex-col gap-3 lg:flex-row"
                        >
                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search release, artist, UPC, catalogue number or ISRC..."
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />

                            <button
                                type="submit"
                                className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white"
                            >
                                Search
                            </button>

                            {(filters.search || filters.status) && (
                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                                >
                                    Reset
                                </button>
                            )}
                        </form>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h2 className="font-semibold text-slate-900">
                            Music Catalogue
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            {releases?.total ?? 0} catalogue entries
                        </p>
                    </div>

                    {(releases?.data ?? []).length === 0 ? (
                        <div className="px-6 py-20 text-center">
                            <div className="text-5xl">♫</div>

                            <h3 className="mt-5 text-lg font-semibold text-slate-900">
                                No catalogue entries found
                            </h3>

                            <p className="mt-2 text-sm text-slate-500">
                                Try changing your search or filters.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {releases.data.map((release) => {
                                const deliveryTotal =
                                    release.store_deliveries_count ?? 0;

                                const delivered =
                                    (release.delivered_store_count ?? 0) +
                                    (release.live_store_count ?? 0);

                                const progress = deliveryTotal
                                    ? Math.round(
                                          (delivered / deliveryTotal) * 100
                                      )
                                    : 0;

                                return (
                                    <div
                                        key={release.id}
                                        className="grid gap-5 px-6 py-5 xl:grid-cols-[minmax(260px,1.6fr)_minmax(180px,1fr)_130px_160px_180px_auto] xl:items-center"
                                    >
                                        <div className="flex min-w-0 items-center gap-4">
                                            <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                                {release.artwork_path ? (
                                                    <img
                                                        src={`/storage/${release.artwork_path}`}
                                                        alt=""
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <span className="text-xl">
                                                        ♫
                                                    </span>
                                                )}
                                            </div>

                                            <div className="min-w-0">
                                                <div className="truncate font-semibold text-slate-900">
                                                    {release.title}
                                                </div>

                                                <div className="mt-1 truncate text-sm text-slate-500">
                                                    {release.primary_artist_name ||
                                                        release.artist?.name ||
                                                        'Artist not assigned'}
                                                </div>

                                                <div className="mt-1 text-xs capitalize text-slate-400">
                                                    {release.release_type ??
                                                        release.type ??
                                                        'Release'}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="min-w-0 text-sm">
                                            <div className="truncate font-medium text-slate-700">
                                                {release.label?.name ||
                                                    'No label'}
                                            </div>

                                            <div className="mt-1 text-xs text-slate-500">
                                                Cat#:{' '}
                                                {release.catalog_number || '—'}
                                            </div>

                                            <div className="mt-1 text-xs text-slate-500">
                                                UPC: {release.upc || 'Pending'}
                                            </div>
                                        </div>

                                        <div>
                                            <div className="font-semibold text-slate-900">
                                                {release.tracks_count ?? 0}
                                            </div>

                                            <div className="text-xs text-slate-500">
                                                Tracks
                                            </div>
                                        </div>

                                        <div>
                                            <span
                                                className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ${
                                                    statusStyles[
                                                        release.status
                                                    ] ??
                                                    'bg-slate-100 text-slate-700'
                                                }`}
                                            >
                                                {String(
                                                    release.status ?? 'draft'
                                                ).replaceAll('_', ' ')}
                                            </span>
                                        </div>

                                        <div>
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="text-slate-500">
                                                    DSP Delivery
                                                </span>

                                                <span className="font-semibold text-slate-700">
                                                    {delivered}/{deliveryTotal}
                                                </span>
                                            </div>

                                            <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                                <div
                                                    className="h-full rounded-full bg-violet-600"
                                                    style={{
                                                        width: `${progress}%`,
                                                    }}
                                                />
                                            </div>

                                            <div className="mt-1 text-right text-xs text-slate-500">
                                                {progress}%
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap gap-2 xl:justify-end">
                                            <Link
                                                href={`/releases/${release.id}/edit`}
                                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Edit
                                            </Link>

                                            {release.status === 'approved' && (
                                                <Link
                                                    href={`/delivery-status/${release.id}`}
                                                    className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white"
                                                >
                                                    Delivery
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    <Pagination links={releases?.links ?? []} />
                </div>
            </div>
        </AdminLayout>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-3xl font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function Pagination({ links = [] }) {
    if (!Array.isArray(links) || links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-center gap-2 border-t border-slate-200 px-5 py-5">
            {links.map((link, index) => (
                <button
                    key={`${link.label}-${index}`}
                    type="button"
                    disabled={!link.url}
                    onClick={() =>
                        link.url &&
                        router.get(
                            link.url,
                            {},
                            {
                                preserveState: true,
                                preserveScroll: true,
                            }
                        )
                    }
                    className={`rounded-lg border px-3 py-2 text-sm ${
                        link.active
                            ? 'border-slate-900 bg-slate-900 text-white'
                            : 'border-slate-300 bg-white text-slate-700'
                    } disabled:cursor-not-allowed disabled:opacity-40`}
                    dangerouslySetInnerHTML={{
                        __html: link.label,
                    }}
                />
            ))}
        </div>
    );
}
