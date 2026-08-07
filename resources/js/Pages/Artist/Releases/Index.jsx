import ArtistLayout from '@/Layouts/ArtistLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

export default function Index({
    releases = {},
    filters = {},
    statusCounts = {},
}) {
    const [form, setForm] = useState({
        search: filters.search ?? '',
        status: filters.status ?? '',
        sort: filters.sort ?? 'latest',
    });

    const rows = releases?.data ?? [];

    const applyFilters = (event) => {
        event.preventDefault();

        router.get('/releases', form, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setForm({
            search: '',
            status: '',
            sort: 'latest',
        });

        router.get('/releases');
    };

    return (
        <ArtistLayout
            title="My Releases"
            subtitle="Manage all your music submissions"
        >
            <Head title="My Releases" />

            <div className="space-y-6">
                <section className="overflow-hidden rounded-3xl bg-[#0d1526] px-7 py-8 text-white shadow-xl lg:px-10">
                    <div className="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                        <div>
                            <p className="text-sm font-medium text-slate-400">
                                RELEASE MANAGEMENT
                            </p>

                            <h2 className="mt-2 text-3xl font-bold">
                                My Releases
                            </h2>

                            <p className="mt-3 text-sm text-slate-300">
                                Review drafts, submissions and approved releases.
                            </p>
                        </div>

                        <Link
                            href="/releases/create"
                            className="w-fit rounded-xl bg-white px-6 py-3 text-sm font-semibold text-[#0d1526]"
                        >
                            + Create Release
                        </Link>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        ['Total', statusCounts.total ?? 0],
                        ['Draft', statusCounts.draft ?? 0],
                        ['Submitted', statusCounts.submitted ?? 0],
                        ['Approved', statusCounts.approved ?? 0],
                        ['Rejected', statusCounts.rejected ?? 0],
                    ].map(([label, value]) => (
                        <article
                            key={label}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <p className="text-sm text-slate-500">{label}</p>
                            <p className="mt-2 text-2xl font-bold text-slate-900">
                                {Number(value ?? 0).toLocaleString('en-IN')}
                            </p>
                        </article>
                    ))}
                </section>

                <form
                    onSubmit={applyFilters}
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="grid gap-3 md:grid-cols-3">
                        <input
                            type="search"
                            value={form.search}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    search: event.target.value,
                                })
                            }
                            placeholder="Search title, artist or UPC"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <select
                            value={form.status}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    status: event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="live">Live</option>
                        </select>

                        <select
                            value={form.sort}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    sort: event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="latest">Latest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="title_asc">Title A–Z</option>
                            <option value="title_desc">Title Z–A</option>
                        </select>
                    </div>

                    <div className="mt-4 flex gap-3">
                        <button
                            type="submit"
                            className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white"
                        >
                            Apply Filters
                        </button>

                        <button
                            type="button"
                            onClick={resetFilters}
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-5 py-4">Release</th>
                                    <th className="px-5 py-4">Type</th>
                                    <th className="px-5 py-4">UPC</th>
                                    <th className="px-5 py-4">Release Date</th>
                                    <th className="px-5 py-4">Progress</th>
                                    <th className="px-5 py-4">Status</th>
                                    <th className="px-5 py-4">Action</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.map((release) => (
                                    <tr key={release.id}>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                {release.artwork_path ? (
                                                    <img
                                                        src={`/storage/${release.artwork_path}`}
                                                        alt=""
                                                        className="h-12 w-12 rounded-lg object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100 text-lg">
                                                        ♫
                                                    </div>
                                                )}

                                                <div className="min-w-0">
                                                    <p className="max-w-xs truncate font-semibold text-slate-900">
                                                        {release.title}
                                                    </p>

                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {release.primary_artist_name}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <td className="px-5 py-4 capitalize text-slate-600">
                                            {release.release_type}
                                        </td>

                                        <td className="px-5 py-4 text-slate-600">
                                            {release.upc || 'Pending'}
                                        </td>

                                        <td className="px-5 py-4 text-slate-600">
                                            {formatDate(
                                                release.digital_release_date
                                            )}
                                        </td>

                                        <td className="px-5 py-4">
                                            <div className="w-28">
                                                <div className="mb-1 text-xs text-slate-500">
                                                    {Number(
                                                        release.completion_percentage ??
                                                            0
                                                    )}
                                                    %
                                                </div>

                                                <div className="h-2 rounded-full bg-slate-100">
                                                    <div
                                                        className="h-2 rounded-full bg-violet-600"
                                                        style={{
                                                            width: `${Math.min(
                                                                100,
                                                                Number(
                                                                    release.completion_percentage ??
                                                                        0
                                                                )
                                                            )}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        </td>

                                        <td className="px-5 py-4">
                                            <StatusBadge
                                                status={release.status}
                                            />
                                        </td>

                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                <Link
                                                    href={`/releases/${release.id}/edit`}
                                                    className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700"
                                                >
                                                    {release.status === 'draft'
                                                        ? 'Continue'
                                                        : 'View'}
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}

                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="px-5 py-12 text-center text-slate-500"
                                        >
                                            No releases found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </ArtistLayout>
    );
}

function StatusBadge({ status }) {
    const classes = {
        draft: 'bg-slate-100 text-slate-700',
        submitted: 'bg-amber-50 text-amber-700',
        approved: 'bg-emerald-50 text-emerald-700',
        rejected: 'bg-rose-50 text-rose-700',
        live: 'bg-blue-50 text-blue-700',
    };

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ${
                classes[status] ?? 'bg-slate-100 text-slate-700'
            }`}
        >
            {status || 'draft'}
        </span>
    );
}
