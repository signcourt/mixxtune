import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const money = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const number = (value) =>
    new Intl.NumberFormat('en-IN').format(Number(value ?? 0));

export default function TrackReport({
    track = {},
    stores = [],
    countries = [],
    months = [],
}) {
    return (
        <AdminLayout title="Track Report">
            <Head title={track.track_title ?? 'Track Report'} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <Link
                            href="/reports"
                            className="text-sm font-semibold text-violet-600"
                        >
                            ← Back to Reports
                        </Link>

                        <h1 className="mt-2 text-3xl font-bold text-slate-900">
                            {track.track_title || 'Untitled'}
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            {track.artist_name || 'Unknown Artist'} · {track.isrc}
                        </p>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                        <div className="text-xs font-medium uppercase text-slate-500">
                            UPC
                        </div>
                        <div className="mt-1 font-semibold text-slate-900">
                            {track.upc || '—'}
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat label="Net Revenue" value={money(track.net_amount)} />
                    <Stat label="Gross Revenue" value={money(track.gross_amount)} />
                    <Stat label="Streams" value={number(track.streams)} />
                    <Stat label="Quantity" value={number(track.quantity)} />
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <Section title="Store Breakdown">
                        <Rows
                            rows={stores}
                            labelKey="store_name"
                        />
                    </Section>

                    <Section title="Country Breakdown">
                        <Rows
                            rows={countries}
                            labelKey="country_code"
                        />
                    </Section>

                    <Section title="Monthly Breakdown">
                        <Rows
                            rows={months}
                            labelKey="sale_month"
                        />
                    </Section>
                </div>
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

function Rows({ rows = [], labelKey }) {
    return (
        <div className="divide-y divide-slate-100">
            {rows.length === 0 ? (
                <div className="py-6 text-center text-sm text-slate-500">
                    No data available
                </div>
            ) : (
                rows.map((row, index) => (
                    <div
                        key={`${row[labelKey] ?? 'unknown'}-${index}`}
                        className="flex items-center justify-between gap-4 py-4"
                    >
                        <div>
                            <div className="font-semibold text-slate-900">
                                {row[labelKey] || 'Unknown'}
                            </div>
                            <div className="text-xs text-slate-500">
                                {number(row.streams)} streams
                            </div>
                        </div>

                        <div className="font-semibold text-slate-900">
                            {money(row.net_amount)}
                        </div>
                    </div>
                ))
            )}
        </div>
    );
}
