import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const money = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const number = (value) =>
    new Intl.NumberFormat('en-IN').format(Number(value ?? 0));

const month = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-IN', {
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

export default function RoyaltyLedgerShow({
    ledger = {},
    trackRows = {},
}) {
    const rows = trackRows?.data ?? [];

    const approveLedger = () => {
        if (!confirm('Is royalty ledger ko approve karna hai?')) return;

        router.post(`/royalty-ledgers/${ledger.id}/approve`, {}, {
            preserveScroll: true,
        });
    };

    const creditWallet = () => {
        if (!confirm('Approved royalty ko wallet me credit karna hai?')) return;

        router.post(`/royalty-ledgers/${ledger.id}/credit-wallet`, {}, {
            preserveScroll: true,
        });
    };

    const cancelLedger = () => {
        if (!confirm('Is royalty ledger ko cancel karna hai?')) return;

        router.post(`/royalty-ledgers/${ledger.id}/cancel`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="Royalty Ledger Detail">
            <Head
                title={
                    ledger.ledger_number
                        ? `Ledger ${ledger.ledger_number}`
                        : 'Royalty Ledger Detail'
                }
            />

            <div className="space-y-6">
                <div>
                    <Link
                        href="/royalty-ledgers"
                        className="text-sm font-semibold text-violet-600 hover:text-violet-700"
                    >
                        ← Back to Royalty Ledger
                    </Link>

                    <div className="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-slate-900">
                                {ledger.ledger_number || 'Royalty Ledger'}
                            </h1>

                            <p className="mt-1 text-sm text-slate-500">
                                {ledger.label_name ||
                                    ledger.revenue_label_name ||
                                    'Unknown Label'}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <StatusBadge status={ledger.status} />

                            {['draft', 'posted'].includes(ledger.status) && (
                                <button
                                    type="button"
                                    onClick={approveLedger}
                                    className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Approve
                                </button>
                            )}

                            {ledger.status === 'approved' && (
                                <button
                                    type="button"
                                    onClick={creditWallet}
                                    className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Credit Wallet
                                </button>
                            )}

                            {!['wallet_credited', 'paid', 'cancelled'].includes(
                                ledger.status
                            ) && (
                                <button
                                    type="button"
                                    onClick={cancelLedger}
                                    className="rounded-xl border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700"
                                >
                                    Cancel
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <InfoCard
                        label="Reporting Month"
                        value={month(ledger.reporting_month)}
                    />

                    <InfoCard
                        label="Sale Month"
                        value={month(ledger.sale_month)}
                    />

                    <InfoCard
                        label="Created"
                        value={ledger.created_at || '—'}
                    />

                    <InfoCard
                        label="Currency"
                        value={ledger.currency || 'INR'}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <Stat
                        label="Gross Revenue"
                        value={money(
                            ledger.gross_amount,
                            ledger.currency
                        )}
                    />

                    <Stat
                        label="Net Revenue"
                        value={money(
                            ledger.net_amount,
                            ledger.currency
                        )}
                    />

                    <Stat
                        label="Share %"
                        value={`${Number(
                            ledger.split_percentage ?? 0
                        ).toFixed(2)}%`}
                    />

                    <Stat
                        label="Payable"
                        value={money(
                            ledger.payable_amount,
                            ledger.currency
                        )}
                    />

                    <Stat
                        label="Total Streams"
                        value={number(
                            ledger.source_streams ??
                                ledger.streams
                        )}
                    />

                    <Stat
                        label="Total Tracks"
                        value={number(ledger.total_tracks)}
                    />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="font-semibold text-slate-900">
                                Track Breakdown
                            </h2>

                            <p className="mt-1 text-xs text-slate-500">
                                Revenue rows included in this ledger.
                            </p>
                        </div>

                        <div className="text-sm text-slate-500">
                            {number(trackRows.total ?? rows.length)} rows
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">ISRC</th>
                                    <th className="px-4 py-3">Track</th>
                                    <th className="px-4 py-3">Artist</th>
                                    <th className="px-4 py-3">Store</th>
                                    <th className="px-4 py-3">Country</th>
                                    <th className="px-4 py-3 text-right">
                                        Streams
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Net Revenue
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.map((row, index) => (
                                    <tr
                                        key={`${row.isrc}-${row.store_name}-${row.country_code}-${index}`}
                                    >
                                        <td className="px-4 py-4 text-slate-700">
                                            {row.isrc || '—'}
                                        </td>

                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {row.track_title ||
                                                'Unknown Track'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {row.artist_name || '—'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {row.store_name || '—'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {row.country_code || '—'}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {number(row.streams)}
                                        </td>

                                        <td className="px-4 py-4 text-right font-semibold text-slate-900">
                                            {money(
                                                row.net_amount,
                                                ledger.currency
                                            )}
                                        </td>
                                    </tr>
                                ))}

                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No track rows found for this ledger.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {(trackRows?.links ?? []).length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
                            <div className="text-sm text-slate-500">
                                Showing {trackRows.from ?? 0} to{' '}
                                {trackRows.to ?? 0} of{' '}
                                {trackRows.total ?? 0}
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {(trackRows.links ?? []).map(
                                    (link, index) => (
                                        <button
                                            key={`${link.label}-${index}`}
                                            type="button"
                                            disabled={!link.url}
                                            onClick={() => {
                                                if (!link.url) return;

                                                router.visit(
                                                    link.url,
                                                    {
                                                        preserveState: true,
                                                        preserveScroll: true,
                                                    }
                                                );
                                            }}
                                            className={`rounded-lg border px-3 py-2 text-sm font-semibold ${
                                                link.active
                                                    ? 'border-violet-600 bg-violet-600 text-white'
                                                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                                            } disabled:cursor-not-allowed disabled:opacity-40`}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    )
                                )}
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}

function InfoCard({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-lg font-semibold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-xl font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function StatusBadge({ status }) {
    const classes = {
        draft: 'bg-slate-100 text-slate-700',
        posted: 'bg-blue-50 text-blue-700',
        approved: 'bg-emerald-50 text-emerald-700',
        wallet_credited: 'bg-violet-50 text-violet-700',
        paid: 'bg-violet-50 text-violet-700',
        cancelled: 'bg-rose-50 text-rose-700',
    };

    return (
        <span
            className={`inline-flex w-fit rounded-full px-4 py-2 text-sm font-semibold ${
                classes[status] ??
                'bg-slate-100 text-slate-700'
            }`}
        >
            {status ?? 'unknown'}
        </span>
    );
}
