import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

const money = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const number = (value) =>
    new Intl.NumberFormat('en-IN').format(Number(value ?? 0));

export default function Royalties({
    summary = {},
    labelRoyalties = {},
    artistRoyalties = [],
    topTracks = [],
    filterOptions = {},
    filters = {},
}) {
    const [form, setForm] = useState({
        month: filters.month ?? '',
        label: filters.label ?? '',
        artist: filters.artist ?? '',
        search: filters.search ?? '',
    });

    const applyFilters = (event) => {
        event.preventDefault();

        router.get('/royalties', form, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setForm({
            month: '',
            label: '',
            artist: '',
            search: '',
        });

        router.get('/royalties');
    };

    const labels = labelRoyalties?.data ?? [];

    return (
        <AdminLayout title="Royalties">
            <Head title="Royalties" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        Royalties
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Label and artist royalty summaries based on matched revenue.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <Stat
                        label="Net Revenue"
                        value={money(summary.net_revenue, summary.currency)}
                    />

                    <Stat
                        label="Total Streams"
                        value={number(summary.total_streams)}
                    />

                    <Stat
                        label="Unique Tracks"
                        value={number(summary.unique_tracks)}
                    />
                </div>

                <form
                    onSubmit={applyFilters}
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <input
                            type="search"
                            value={form.search}
                            onChange={(event) =>
                                setForm({ ...form, search: event.target.value })
                            }
                            placeholder="Track, artist, label or ISRC"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <select
                            value={form.month}
                            onChange={(event) =>
                                setForm({ ...form, month: event.target.value })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All months</option>

                            {(filterOptions.months ?? []).map((month) => (
                                <option key={month.value} value={month.value}>
                                    {month.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={form.label}
                            onChange={(event) =>
                                setForm({ ...form, label: event.target.value })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All labels</option>

                            {(filterOptions.labels ?? []).map((label) => (
                                <option key={label} value={label}>
                                    {label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={form.artist}
                            onChange={(event) =>
                                setForm({ ...form, artist: event.target.value })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All artists</option>

                            {(filterOptions.artists ?? []).map((artist) => (
                                <option key={artist} value={artist}>
                                    {artist}
                                </option>
                            ))}
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

                <Section title="Label Royalties">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Label</th>
                                    <th className="px-4 py-3 text-right">Tracks</th>
                                    <th className="px-4 py-3 text-right">Streams</th>
                                    <th className="px-4 py-3 text-right">Net Revenue</th>
                                    <th className="px-4 py-3 text-right">Share %</th>
                                    <th className="px-4 py-3 text-right">Payable</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {labels.map((row, index) => (
                                    <tr key={`${row.label_name}-${index}`}>
                                        <td className="px-4 py-4 font-semibold">
                                            <Link
                                                href={`/royalties/labels/${encodeURIComponent(row.label_name)}`}
                                                className="text-slate-900 hover:text-violet-600 hover:underline"
                                            >
                                                {row.label_name || 'Unknown Label'}
                                            </Link>
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {number(row.tracks)}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {number(row.streams)}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {money(row.net_revenue, summary.currency)}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {Number(row.royalty_percentage ?? 100).toFixed(2)}%
                                        </td>

                                        <td className="px-4 py-4 text-right font-semibold text-slate-900">
                                            {money(row.payable_amount, summary.currency)}
                                        </td>
                                    </tr>
                                ))}

                                {labels.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No royalty data found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Section>

                <Section title="Top Artists">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Artist</th>
                                    <th className="px-4 py-3 text-right">Tracks</th>
                                    <th className="px-4 py-3 text-right">Streams</th>
                                    <th className="px-4 py-3 text-right">Net Revenue</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {(artistRoyalties ?? []).map((row, index) => (
                                    <tr key={`${row.artist_name}-${index}`}>
                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {row.artist_name || 'Unknown Artist'}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {number(row.tracks)}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {number(row.streams)}
                                        </td>

                                        <td className="px-4 py-4 text-right font-semibold text-slate-900">
                                            {money(row.net_revenue, summary.currency)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Section>

                <Section title="Top Tracks">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Track</th>
                                    <th className="px-4 py-3">ISRC</th>
                                    <th className="px-4 py-3">Artist</th>
                                    <th className="px-4 py-3">Label</th>
                                    <th className="px-4 py-3 text-right">Streams</th>
                                    <th className="px-4 py-3 text-right">Revenue</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {(topTracks ?? []).map((track, index) => (
                                    <tr key={`${track.isrc}-${index}`}>
                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {track.track_title || 'Untitled'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-600">
                                            {track.isrc || '—'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-600">
                                            {track.artist_name || 'Unknown Artist'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-600">
                                            {track.label_name || 'Unknown Label'}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {number(track.streams)}
                                        </td>

                                        <td className="px-4 py-4 text-right font-semibold text-slate-900">
                                            {money(track.revenue, summary.currency)}
                                        </td>
                                    </tr>
                                ))}

                                {(topTracks ?? []).length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No tracks found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Section>

            </div>
        </AdminLayout>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-slate-500">{label}</div>
            <div className="mt-2 text-2xl font-bold text-slate-900">{value}</div>
        </div>
    );
}

function Section({ title, children }) {
    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-5 py-4">
                <h2 className="font-semibold text-slate-900">{title}</h2>
            </div>

            <div className="p-5">{children}</div>
        </section>
    );
}
