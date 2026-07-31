import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

const money = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

export default function InvoicesIndex({
    invoices = {},
    paidWithdrawals = [],
    filterOptions = {},
    filters = {},
}) {
    const [filterForm, setFilterForm] = useState({
        search: filters.search ?? '',
        label_id: filters.label_id ?? '',
        status: filters.status ?? '',
    });

    const invoiceForm = useForm({
        withdrawal_id: '',
        gst_percentage: '0',
        tds_percentage: '0',
    });

    const rows = invoices?.data ?? [];

    const applyFilters = (event) => {
        event.preventDefault();

        router.get('/invoices', filterForm, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setFilterForm({
            search: '',
            label_id: '',
            status: '',
        });

        router.get('/invoices');
    };

    const generateInvoice = (event) => {
        event.preventDefault();

        invoiceForm.post('/invoices', {
            preserveScroll: true,
            onSuccess: () => {
                invoiceForm.setData({
                    withdrawal_id: '',
                    gst_percentage: '0',
                    tds_percentage: '0',
                });
            },
        });
    };

    return (
        <AdminLayout title="Invoices">
            <Head title="Invoices" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        Invoices
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Generate invoices from paid withdrawals.
                    </p>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Generate Invoice
                    </h2>

                    <form
                        onSubmit={generateInvoice}
                        className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    >
                        <div className="md:col-span-2">
                            <label className="mb-2 block text-sm font-semibold text-slate-700">
                                Paid Withdrawal
                            </label>

                            <select
                                value={invoiceForm.data.withdrawal_id}
                                onChange={(event) =>
                                    invoiceForm.setData(
                                        'withdrawal_id',
                                        event.target.value
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="">
                                    Select paid withdrawal
                                </option>

                                {paidWithdrawals.map((withdrawal) => (
                                    <option
                                        key={withdrawal.id}
                                        value={withdrawal.id}
                                    >
                                        {withdrawal.withdrawal_number} —{' '}
                                        {withdrawal.label_name ||
                                            'Unknown Label'} —{' '}
                                        {money(
                                            withdrawal.amount,
                                            withdrawal.currency
                                        )}
                                    </option>
                                ))}
                            </select>

                            {invoiceForm.errors.withdrawal_id && (
                                <p className="mt-1 text-xs text-rose-600">
                                    {invoiceForm.errors.withdrawal_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-semibold text-slate-700">
                                GST %
                            </label>

                            <input
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                value={invoiceForm.data.gst_percentage}
                                onChange={(event) =>
                                    invoiceForm.setData(
                                        'gst_percentage',
                                        event.target.value
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-semibold text-slate-700">
                                TDS %
                            </label>

                            <input
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                value={invoiceForm.data.tds_percentage}
                                onChange={(event) =>
                                    invoiceForm.setData(
                                        'tds_percentage',
                                        event.target.value
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />
                        </div>

                        <div className="md:col-span-2 xl:col-span-4">
                            <button
                                type="submit"
                                disabled={invoiceForm.processing}
                                className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                {invoiceForm.processing
                                    ? 'Generating...'
                                    : 'Generate Invoice'}
                            </button>
                        </div>
                    </form>
                </section>

                <form
                    onSubmit={applyFilters}
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="grid gap-3 md:grid-cols-3">
                        <input
                            type="search"
                            value={filterForm.search}
                            onChange={(event) =>
                                setFilterForm({
                                    ...filterForm,
                                    search: event.target.value,
                                })
                            }
                            placeholder="Invoice no., label or withdrawal"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <select
                            value={filterForm.label_id}
                            onChange={(event) =>
                                setFilterForm({
                                    ...filterForm,
                                    label_id: event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All Labels</option>

                            {(filterOptions.labels ?? []).map((label) => (
                                <option key={label.id} value={label.id}>
                                    {label.name}
                                </option>
                            ))}
                        </select>

                        <select
                            value={filterForm.status}
                            onChange={(event) =>
                                setFilterForm({
                                    ...filterForm,
                                    status: event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All Statuses</option>

                            {(filterOptions.statuses ?? []).map((status) => (
                                <option key={status} value={status}>
                                    {status}
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

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="font-semibold text-slate-900">
                            Invoice Records
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Invoice No.</th>
                                    <th className="px-4 py-3">Label</th>
                                    <th className="px-4 py-3">Withdrawal</th>
                                    <th className="px-4 py-3">Date</th>
                                    <th className="px-4 py-3 text-right">
                                        Subtotal
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        GST
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        TDS
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Net Payable
                                    </th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Action</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.map((invoice) => (
                                    <tr key={invoice.id}>
                                        <td className="px-4 py-4 font-semibold">
                                            <Link
                                                href={`/invoices/${invoice.id}`}
                                                className="text-violet-600 hover:underline"
                                            >
                                                {invoice.invoice_number}
                                            </Link>
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {invoice.label_name ||
                                                invoice.billing_name ||
                                                'Unknown Label'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {invoice.withdrawal_number || '—'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-600">
                                            {invoice.invoice_date || '—'}
                                        </td>

                                        <td className="px-4 py-4 text-right">
                                            {money(
                                                invoice.subtotal,
                                                invoice.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-right">
                                            {money(
                                                invoice.gst_amount,
                                                invoice.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-right">
                                            {money(
                                                invoice.tds_amount,
                                                invoice.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-right font-semibold">
                                            {money(
                                                invoice.net_payable,
                                                invoice.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4">
                                            <StatusBadge
                                                status={invoice.status}
                                            />
                                        </td>

                                        <td className="px-4 py-4">
                                            <Link
                                                href={`/invoices/${invoice.id}`}
                                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700"
                                            >
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}

                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="10"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No invoices found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AdminLayout>
    );
}

function StatusBadge({ status }) {
    const classes = {
        draft: 'bg-slate-100 text-slate-700',
        issued: 'bg-blue-50 text-blue-700',
        paid: 'bg-emerald-50 text-emerald-700',
        cancelled: 'bg-rose-50 text-rose-700',
    };

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                classes[status] ??
                'bg-slate-100 text-slate-700'
            }`}
        >
            {status || 'unknown'}
        </span>
    );
}
