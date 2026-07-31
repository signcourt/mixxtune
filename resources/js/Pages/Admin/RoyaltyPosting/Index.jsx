import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RoyaltyPosting({
    previewRows = [],
    summary = {},
    postedLedgers = {},
    reportingMonths = [],
    saleMonths = [],
    filters = {},
}) {
    const [reportingMonth, setReportingMonth] = useState(
        filters.reporting_month ?? ''
    );

    const [saleMonth, setSaleMonth] = useState(
        filters.sale_month ?? ''
    );
    const [posting, setPosting] = useState(false);

    const applyMonth = (event) => {
        event.preventDefault();

        router.get(
            '/royalties/posting',
            {
                reporting_month: reportingMonth,
                sale_month: saleMonth,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const resetMonth = () => {
        setReportingMonth('');
        setSaleMonth('');

        router.get('/royalties/posting', {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const generateLedger = () => {
        if (!confirm('Selected preview rows ko royalty ledger me post karna hai?')) {
            return;
        }

        setPosting(true);

        router.post(
            '/royalties/posting',
            {
                reporting_month: reportingMonth || null,
                sale_month: saleMonth || null,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    router.reload({
                        only: [
                            'previewRows',
                            'summary',
                            'postedLedgers',
                        ],
                        preserveScroll: true,
                    });
                },

                onFinish: () => {
                    setPosting(false);
                },
            }
        );
    };

    return (
        <AdminLayout title="Royalty Posting">
            <Head title="Royalty Posting" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            Royalty Posting
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Preview mapped revenue and create monthly royalty ledger entries.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={generateLedger}
                        disabled={posting || previewRows.length === 0}
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {posting ? 'Posting...' : 'Generate Ledger'}
                    </button>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Stat label="Preview Rows" value={summary.rows ?? 0} />
                    <Stat label="Mapped Labels" value={summary.labels ?? 0} />
                    <Stat
                        label="Net Revenue"
                        value={formatMoney(summary.net_amount ?? 0)}
                    />
                    <Stat
                        label="Payable Amount"
                        value={formatMoney(summary.beneficiary_amount ?? 0)}
                    />
                </div>

                <form
                    onSubmit={applyMonth}
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="flex flex-col gap-3 md:flex-row md:items-end">
                        <div className="w-full md:max-w-sm">
                            <label className="mb-2 block text-sm font-semibold text-slate-700">
                                Reporting Month
                            </label>

                            <select
                                value={reportingMonth}
                                onChange={(event) =>
                                    setReportingMonth(event.target.value)
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="">All Reporting Months</option>

                                {reportingMonths.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="w-full md:max-w-sm">
                            <label className="mb-2 block text-sm font-semibold text-slate-700">
                                Sale Month
                            </label>

                            <select
                                value={saleMonth}
                                onChange={(event) =>
                                    setSaleMonth(event.target.value)
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="">All Sale Months</option>

                                {saleMonths.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <button
                            type="submit"
                            className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white"
                        >
                            Preview
                        </button>

                        <button
                            type="button"
                            onClick={resetMonth}
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="font-semibold text-slate-900">
                            Posting Preview
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">
                                        Reporting Month
                                    </th>
                                    <th className="px-4 py-3">
                                        Sale Month
                                    </th>
                                    <th className="px-4 py-3">Label</th>
                                    <th className="px-4 py-3">Tracks</th>
                                    <th className="px-4 py-3">Streams</th>
                                    <th className="px-4 py-3">Net Revenue</th>
                                    <th className="px-4 py-3">Share %</th>
                                    <th className="px-4 py-3">Payable</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {previewRows.map((row, index) => (
                                    <tr
                                        key={`${row.mapping_id}-${row.sale_month}-${index}`}
                                    >
                                        <td className="px-4 py-4 text-slate-700">
                                            {formatMonth(row.reporting_month)}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {formatMonth(row.sale_month)}
                                        </td>

                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {row.label_name}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {formatNumber(row.unique_tracks)}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {formatNumber(row.total_streams)}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {formatMoney(row.net_amount)}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {Number(row.share_percentage).toFixed(2)}%
                                        </td>

                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {formatMoney(row.beneficiary_amount)}
                                        </td>
                                    </tr>
                                ))}

                                {previewRows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="8"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No mapped revenue rows available for posting.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="font-semibold text-slate-900">
                            Posted Royalty Ledgers
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Month</th>
                                    <th className="px-4 py-3">Label</th>
                                    <th className="px-4 py-3">Net Revenue</th>
                                    <th className="px-4 py-3">Share %</th>
                                    <th className="px-4 py-3">Payable</th>
                                    <th className="px-4 py-3">Status</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {(postedLedgers?.data ?? []).map((ledger) => (
                                    <tr key={ledger.id}>
                                        <td className="px-4 py-4 text-slate-700">
                                            {formatMonth(ledger.period_start)}
                                        </td>

                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {ledger.label_name ?? 'Unknown Label'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {formatMoney(ledger.net_amount)}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {Number(ledger.share_percentage).toFixed(2)}%
                                        </td>

                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {formatMoney(ledger.beneficiary_amount)}
                                        </td>

                                        <td className="px-4 py-4">
                                            <StatusBadge status={ledger.status} />
                                        </td>
                                    </tr>
                                ))}

                                {(postedLedgers?.data ?? []).length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No royalty ledgers posted yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {(postedLedgers?.links ?? []).length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
                            <div className="text-sm text-slate-500">
                                Showing {postedLedgers.from ?? 0} to{' '}
                                {postedLedgers.to ?? 0} of{' '}
                                {postedLedgers.total ?? 0}
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {(postedLedgers.links ?? []).map((link, index) => (
                                    <button
                                        key={`${link.label}-${index}`}
                                        type="button"
                                        disabled={!link.url}
                                        onClick={() => {
                                            if (!link.url) return;

                                            router.visit(link.url, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            });
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
                                ))}
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </AdminLayout>
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

function StatusBadge({ status }) {
    const classes = {
        pending: 'bg-amber-50 text-amber-700',
        approved: 'bg-blue-50 text-blue-700',
        posted: 'bg-emerald-50 text-emerald-700',
        rejected: 'bg-rose-50 text-rose-700',
    };

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                classes[status] ?? 'bg-slate-100 text-slate-700'
            }`}
        >
            {status ?? 'unknown'}
        </span>
    );
}

function formatMoney(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function formatNumber(value) {
    return new Intl.NumberFormat('en-IN').format(Number(value ?? 0));
}

function formatMonth(value) {
    if (!value) return '-';

    return new Intl.DateTimeFormat('en-IN', {
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}
