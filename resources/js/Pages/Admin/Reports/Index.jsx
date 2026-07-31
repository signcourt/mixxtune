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

export default function Reports({
    summary = {},
    topTracks = [],
    storeBreakdown = [],
    monthlyBreakdown = [],
    filterOptions = {},
    filters = {},
}) {
    const [form, setForm] = useState({
        reporting_month: filters.reporting_month ?? '',
        sale_month: filters.sale_month ?? '',
        store: filters.store ?? '',
        country: filters.country ?? '',
        label: filters.label ?? '',
        search: filters.search ?? '',
    });

    const applyFilters = (event) => {
        event.preventDefault();

        router.get('/reports', form, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const exportCsv = () => {
        const params = new URLSearchParams();

        Object.entries(form).forEach(([key, value]) => {
            if (value) {
                params.set(key, value);
            }
        });

        window.location.href = `/reports/export?${params.toString()}`;
    };

    const resetFilters = () => {
        setForm({
            reporting_month: '',
            sale_month: '',
            store: '',
            country: '',
            label: '',
            search: '',
        });

        router.get('/reports');
    };

    return (
        <AdminLayout title="Reports">
            <Head title="Reports" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        Reports
                    </h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Revenue, streams, stores and top-performing tracks.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <Stat label="Net Revenue" value={money(summary.net_amount, summary.currency)} />
                    <Stat label="Gross Revenue" value={money(summary.gross_amount, summary.currency)} />
                    <Stat label="Total Streams" value={number(summary.total_streams)} />
                    <Stat label="Unique Tracks" value={number(summary.unique_tracks)} />
                    <Stat label="Matched Rows" value={number(summary.revenue_rows)} />
                    <Stat label="Quantity" value={number(summary.total_quantity)} />
                </div>

                <form
                    onSubmit={applyFilters}
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <input
                            type="search"
                            value={form.search}
                            onChange={(event) =>
                                setForm({ ...form, search: event.target.value })
                            }
                            placeholder="Track, artist, ISRC or UPC"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <select
                            value={form.reporting_month}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    reporting_month: event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All Reporting Months</option>
                            {(filterOptions.reportingMonths ?? []).map((month) => (
                                <option key={month.value} value={month.value}>
                                    {month.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={form.sale_month}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    sale_month: event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All Sale Months</option>
                            {(filterOptions.saleMonths ?? []).map((month) => (
                                <option key={month.value} value={month.value}>
                                    {month.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={form.store}
                            onChange={(event) =>
                                setForm({ ...form, store: event.target.value })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All stores</option>
                            {(filterOptions.stores ?? []).map((store) => (
                                <option key={store} value={store}>
                                    {store}
                                </option>
                            ))}
                        </select>

                        <select
                            value={form.country}
                            onChange={(event) =>
                                setForm({ ...form, country: event.target.value })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All countries</option>
                            {(filterOptions.countries ?? []).map((country) => (
                                <option key={country} value={country}>
                                    {country}
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
                            onClick={exportCsv}
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white"
                        >
                            Export CSV
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

                <div className="grid gap-6 xl:grid-cols-2">
                    <Section title="Store Breakdown">
                        <div className="divide-y divide-slate-100">
                            {(storeBreakdown ?? []).map((row) => (
                                <div
                                    key={row.store_name ?? 'unknown'}
                                    className="flex items-center justify-between gap-4 py-4"
                                >
                                    <div>
                                        <div className="font-semibold text-slate-900">
                                            {row.store_name || 'Unknown Store'}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {number(row.streams)} streams
                                        </div>
                                    </div>

                                    <div className="font-semibold text-slate-900">
                                        {money(row.net_amount, summary.currency)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Section>

                    <Section title="Monthly Breakdown">
                        <div className="divide-y divide-slate-100">
                            {(monthlyBreakdown ?? []).map((row) => (
                                <div
                                    key={row.sale_month}
                                    className="flex items-center justify-between gap-4 py-4"
                                >
                                    <div>
                                        <div className="font-semibold text-slate-900">
                                            Reporting : {row.reporting_month || '-'}
                                        </div>

                                        <div className="text-xs text-slate-500">
                                            Sale : {row.sale_month}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {number(row.streams)} streams
                                        </div>
                                    </div>

                                    <div className="font-semibold text-slate-900">
                                        {money(row.net_amount, summary.currency)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Section>
                </div>

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
                                    <th className="px-4 py-3">View</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {(topTracks ?? []).map((track) => (
                                    <tr key={track.isrc}>
                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {track.track_title || 'Untitled'}
                                        </td>
                                        <td className="px-4 py-4 text-slate-600">
                                            {track.isrc}
                                        </td>
                                        <td className="px-4 py-4 text-slate-600">
                                            {track.artist_name || '—'}
                                        </td>
                                        <td className="px-4 py-4 text-slate-600">
                                            {track.label_name || '—'}
                                        </td>
                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {number(track.streams)}
                                        </td>
                                        <td className="px-4 py-4 text-right font-semibold text-slate-900">
                                            {money(track.net_amount, summary.currency)}
                                        </td>
                                        <td className="px-4 py-4">
                                            <Link
                                                href={`/reports/tracks/${track.isrc}`}
                                                className="inline-flex rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
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
