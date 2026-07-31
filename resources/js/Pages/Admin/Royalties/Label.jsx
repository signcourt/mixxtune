import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const money = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const number = (value) =>
    new Intl.NumberFormat('en-IN').format(Number(value ?? 0));

export default function LabelRoyalty({
    labelName = '',
    summary = {},
    tracks = {},
    topStores = [],
    monthly = [],
}) {
    const allTracks = tracks?.data ?? [];

    return (
        <AdminLayout title={`${labelName} Royalties`}>
            <Head title={`${labelName} Royalties`} />

            <div className="space-y-6">
                <div>
                    <Link
                        href="/royalties"
                        className="text-sm font-semibold text-violet-600"
                    >
                        ← Back to Royalties
                    </Link>

                    <h1 className="mt-2 text-3xl font-bold text-slate-900">
                        {labelName}
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Songs, revenue and top performance for this label.
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
                        label="Total Tracks"
                        value={number(summary.unique_tracks)}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Section title="All Tracks">
                        <div className="max-h-[620px] overflow-y-auto">
                            <CompactTracks
                                rows={allTracks}
                                currency={summary.currency}
                                showView
                            />
                        </div>
                    </Section>

                    <Section title="Top Stores">
                        <div className="max-h-[620px] overflow-y-auto">
                            <StoreRows
                                rows={topStores}
                                currency={summary.currency}
                            />
                        </div>
                    </Section>
                </div>

                <Section title="Monthly Performance">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {(monthly ?? []).map((row, index) => (
                            <div
                                key={`${row.sale_month}-${index}`}
                                className="rounded-xl border border-slate-200 p-4"
                            >
                                <div className="text-sm font-semibold text-slate-900">
                                    {row.sale_month}
                                </div>

                                <div className="mt-1 text-xs text-slate-500">
                                    {number(row.streams)} streams
                                </div>

                                <div className="mt-3 font-semibold text-slate-900">
                                    {money(
                                        row.net_revenue,
                                        summary.currency
                                    )}
                                </div>
                            </div>
                        ))}

                        {(monthly ?? []).length === 0 && (
                            <div className="py-6 text-sm text-slate-500">
                                No monthly data available.
                            </div>
                        )}
                    </div>
                </Section>
            </div>
        </AdminLayout>
    );
}

function CompactTracks({
    rows = [],
    currency = 'INR',
    showView = false,
}) {
    return (
        <div className="divide-y divide-slate-100">
            {rows.map((track, index) => (
                <div
                    key={`${track.isrc}-${index}`}
                    className="flex items-center justify-between gap-4 py-4"
                >
                    <div className="min-w-0 flex-1">
                        <div className="truncate font-semibold text-slate-900">
                            {index + 1}. {track.track_title || 'Untitled'}
                        </div>

                        <div className="mt-1 truncate text-xs text-slate-500">
                            {track.artist_name || 'Unknown Artist'} ·{' '}
                            {track.isrc}
                        </div>

                        <div className="mt-1 text-xs text-slate-500">
                            {number(track.streams)} streams
                        </div>
                    </div>

                    <div className="shrink-0 text-right">
                        <div className="font-semibold text-slate-900">
                            {money(track.net_revenue, currency)}
                        </div>

                        {showView && (
                            <Link
                                href={`/reports/tracks/${track.isrc}`}
                                className="mt-2 inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                View
                            </Link>
                        )}
                    </div>
                </div>
            ))}

            {rows.length === 0 && (
                <div className="py-8 text-center text-sm text-slate-500">
                    No tracks found.
                </div>
            )}
        </div>
    );
}

function StoreRows({ rows = [], currency = 'INR' }) {
    return (
        <div className="divide-y divide-slate-100">
            {rows.map((row, index) => (
                <div
                    key={`${row.store_name ?? 'unknown'}-${index}`}
                    className="flex items-center justify-between gap-4 py-4"
                >
                    <div>
                        <div className="font-semibold text-slate-900">
                            {row.store_name || 'Unknown Store'}
                        </div>

                        <div className="mt-1 text-xs text-slate-500">
                            {number(row.streams)} streams
                        </div>
                    </div>

                    <div className="font-semibold text-slate-900">
                        {money(row.net_revenue, currency)}
                    </div>
                </div>
            ))}

            {rows.length === 0 && (
                <div className="py-8 text-center text-sm text-slate-500">
                    No store data available.
                </div>
            )}
        </div>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-2xl font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function Section({ title, children }) {
    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-5 py-4">
                <h2 className="font-semibold text-slate-900">
                    {title}
                </h2>
            </div>

            <div className="p-5">
                {children}
            </div>
        </section>
    );
}
