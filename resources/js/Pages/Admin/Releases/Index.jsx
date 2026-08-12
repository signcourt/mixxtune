import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const statusStyles = {
    draft: 'bg-amber-50 text-amber-700 ring-amber-200',
    submitted: 'bg-blue-50 text-blue-700 ring-blue-200',
    approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    delivered: 'bg-violet-50 text-violet-700 ring-violet-200',
    live: 'bg-green-50 text-green-700 ring-green-200',
    rejected: 'bg-red-50 text-red-700 ring-red-200',
    archived: 'bg-slate-100 text-slate-600 ring-slate-200',
};

const statusIcons = {
    draft: '◷',
    submitted: '↑',
    approved: '✓',
    delivered: '↗',
    live: '●',
    rejected: '!',
    archived: '□',
};

function formatDate(value) {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function artworkUrl(path) {
    if (!path) return null;

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/')
    ) {
        return path;
    }

    return `/storage/${path}`;
}

export default function Index({ releases }) {
    const rows = releases?.data ?? [];

    return (
        <AdminLayout title="Releases">
            <Head title="Releases" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Release Catalogue
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Review and manage your complete music catalogue.
                        </p>
                    </div>

                    <Link
                        href="/releases/create"
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        <span className="text-lg leading-none">＋</span>
                        Create Release
                    </Link>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="font-semibold text-slate-900">
                                All Releases
                            </h2>

                            <p className="mt-1 text-xs text-slate-500">
                                {releases?.total ?? 0} catalogue entries
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <input
                                type="search"
                                placeholder="Search releases..."
                                className="w-full rounded-xl border-slate-300 text-sm sm:w-64"
                            />

                            <select className="rounded-xl border-slate-300 text-sm">
                                <option value="">All statuses</option>
                                <option value="draft">Draft</option>
                                <option value="submitted">Submitted</option>
                                <option value="approved">Approved</option>
                                <option value="delivered">Delivered</option>
                                <option value="live">Live</option>
                            </select>
                        </div>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <div className="text-4xl">♫</div>

                            <h3 className="mt-4 text-lg font-semibold text-slate-900">
                                No releases found
                            </h3>

                            <p className="mt-2 text-sm text-slate-500">
                                Create your first release to begin managing your catalogue.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {rows.map((release) => {
                                const cover = artworkUrl(release.artwork_path);
                                const status = release.status ?? 'draft';

                                return (
                                    <div
                                        key={release.id}
                                        className="group grid grid-cols-[36px_52px_minmax(190px,1.6fr)_minmax(130px,1fr)_110px_90px_minmax(150px,1fr)_72px] items-center gap-4 px-5 py-3 transition hover:bg-slate-50"
                                    >
                                        <div
                                            className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ring-1 ${
                                                statusStyles[status] ??
                                                statusStyles.archived
                                            }`}
                                            title={status}
                                        >
                                            {statusIcons[status] ?? '•'}
                                        </div>

                                        <div className="h-12 w-12 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200">
                                            {cover ? (
                                                <img
                                                    src={cover}
                                                    alt={release.title}
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-full w-full items-center justify-center text-lg text-slate-400">
                                                    ♫
                                                </div>
                                            )}
                                        </div>

                                        <div className="min-w-0">
                                            <div className="truncate text-sm font-semibold text-blue-600">
                                                {release.title}
                                            </div>

                                            <div className="mt-0.5 truncate text-xs text-slate-500">
                                                By {release.primary_artist_name || 'Unknown Artist'}
                                            </div>
                                        </div>

                                        <div className="min-w-0">
                                            <div className="truncate text-sm text-slate-700">
                                                {release.label?.name ?? 'No Label'}
                                            </div>

                                            <div className="mt-0.5 text-xs capitalize text-slate-400">
                                                {release.release_type}
                                            </div>
                                        </div>

                                        <div className="text-sm text-slate-600">
                                            {formatDate(release.digital_release_date || release.original_release_date)}
                                        </div>

                                        <div className="text-sm text-slate-600">
                                            {release.tracks_count ?? 0}{' '}
                                            {(release.tracks_count ?? 0) === 1
                                                ? 'Track'
                                                : 'Tracks'}
                                        </div>

                                        <div className="min-w-0 text-xs text-slate-500">
                                            <div className="truncate">
                                                UPC: {release.upc || 'empty'}
                                            </div>

                                        </div>

                                        <div className="flex items-center justify-end gap-1">
                                            <Link
                                                href={`/releases/${release.id}/edit`}
                                                className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-200 hover:text-slate-900"
                                                title="Edit release"
                                            >
                                                ✎
                                            </Link>

                                            <button
                                                type="button"
                                                className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-red-50 hover:text-red-600"
                                                title="Delete release"
                                            >
                                                ♲
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
