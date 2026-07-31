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

const month = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-IN', {
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

export default function RoyaltyLedgers({
    ledgers = {},
    summary = {},
    filterOptions = {},
    filters = {},
}) {
    const [form, setForm] = useState({
        reporting_month: filters.reporting_month ?? '',
        sale_month: filters.sale_month ?? '',
        status: filters.status ?? '',
        label_id: filters.label_id ?? '',
        search: filters.search ?? '',
    });

    const applyFilters = (event) => {
        event.preventDefault();

        router.get('/royalty-ledgers', form, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setForm({
            reporting_month: '',
            sale_month: '',
            status: '',
            label_id: '',
            search: '',
        });

        router.get('/royalty-ledgers');
    };

    const rows = ledgers?.data ?? [];

    return (
        <AdminLayout title="Royalty Ledger">
            <Head title="Royalty Ledger" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        Royalty Ledger
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Reporting Month, Sale Month, payable amounts and ledger status.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat
                        label="Total Ledgers"
                        value={number(summary.total_ledgers)}
                    />

                    <Stat
                        label="Net Revenue"
                        value={money(
                            summary.net_revenue,
                            summary.currency
                        )}
                    />

                    <Stat
                        label="Payable Amount"
                        value={money(
                            summary.payable_amount,
                            summary.currency
                        )}
                    />

                    <Stat
                        label="Total Streams"
                        value={number(summary.total_streams)}
                    />
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
                                setForm({
                                    ...form,
                                    search: event.target.value,
                                })
                            }
                            placeholder="Ledger number or label"
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
                            <option value="">
                                All Reporting Months
                            </option>

                            {(filterOptions.reportingMonths ?? []).map(
                                (item) => (
                                    <option
                                        key={item.value}
                                        value={item.value}
                                    >
                                        {item.label}
                                    </option>
                                )
                            )}
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
                            <option value="">
                                All Sale Months
                            </option>

                            {(filterOptions.saleMonths ?? []).map(
                                (item) => (
                                    <option
                                        key={item.value}
                                        value={item.value}
                                    >
                                        {item.label}
                                    </option>
                                )
                            )}
                        </select>

                        <select
                            value={form.label_id}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    label_id: event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All Labels</option>

                            {(filterOptions.labels ?? []).map(
                                (label) => (
                                    <option
                                        key={label.id}
                                        value={label.id}
                                    >
                                        {label.name}
                                    </option>
                                )
                            )}
                        </select>

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

                            {(filterOptions.statuses ?? []).map(
                                (status) => (
                                    <option
                                        key={status}
                                        value={status}
                                    >
                                        {status}
                                    </option>
                                )
                            )}
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
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="font-semibold text-slate-900">
                            Ledger Entries
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">
                                        Ledger No.
                                    </th>
                                    <th className="px-4 py-3">
                                        Reporting Month
                                    </th>
                                    <th className="px-4 py-3">
                                        Sale Month
                                    </th>
                                    <th className="px-4 py-3">
                                        Label
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Streams
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Net Revenue
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Share %
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Payable
                                    </th>
                                    <th className="px-4 py-3">
                                        Status
                                    </th>
                                    <th className="px-4 py-3">
                                        Created
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.map((ledger) => (
                                    <tr key={ledger.id}>
                                        <td className="px-4 py-4 font-semibold">
                                            <Link
                                                href={`/royalty-ledgers/${ledger.id}`}
                                                className="text-violet-600 hover:text-violet-700 hover:underline"
                                            >
                                                {ledger.ledger_number || '—'}
                                            </Link>
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {month(
                                                ledger.reporting_month
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {month(ledger.sale_month)}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {ledger.label_name ||
                                                'Unknown Label'}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {number(ledger.streams)}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {money(
                                                ledger.net_amount,
                                                ledger.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {Number(
                                                ledger.split_percentage ?? 0
                                            ).toFixed(2)}
                                            %
                                        </td>

                                        <td className="px-4 py-4 text-right font-semibold text-slate-900">
                                            {money(
                                                ledger.payable_amount,
                                                ledger.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4">
                                            <StatusBadge
                                                status={ledger.status}
                                            />
                                        </td>

                                        <td className="px-4 py-4 text-slate-600">
                                            {ledger.created_at || '—'}
                                        </td>
                                    </tr>
                                ))}

                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="10"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No royalty ledgers found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {(ledgers?.links ?? []).length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
                            <div className="text-sm text-slate-500">
                                Showing {ledgers.from ?? 0} to{' '}
                                {ledgers.to ?? 0} of{' '}
                                {ledgers.total ?? 0}
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {(ledgers.links ?? []).map(
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
        draft: 'bg-slate-100 text-slate-700',
        posted: 'bg-blue-50 text-blue-700',
        approved: 'bg-emerald-50 text-emerald-700',
        paid: 'bg-violet-50 text-violet-700',
        cancelled: 'bg-rose-50 text-rose-700',
    };

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                classes[status] ??
                'bg-slate-100 text-slate-700'
            }`}
        >
            {status ?? 'unknown'}
        </span>
    );
}
