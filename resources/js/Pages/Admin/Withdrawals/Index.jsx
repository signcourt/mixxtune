import { Head, router, useForm } from '@inertiajs/react';
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

export default function WithdrawalsIndex({
    withdrawals = {},
    wallets = [],
    summary = {},
    filterOptions = {},
    filters = {},
}) {
    const [filterForm, setFilterForm] = useState({
        search: filters.search ?? '',
        label_id: filters.label_id ?? '',
        status: filters.status ?? '',
    });

    const requestForm = useForm({
        wallet_id: '',
        amount: '',
        payment_method: '',
        note: '',
    });

    const rows = withdrawals?.data ?? [];

    const applyFilters = (event) => {
        event.preventDefault();

        router.get('/withdrawals', filterForm, {
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

        router.get('/withdrawals');
    };

    const submitRequest = (event) => {
        event.preventDefault();

        requestForm.post('/withdrawals', {
            preserveScroll: true,
            onSuccess: () => {
                requestForm.reset();
            },
        });
    };

    const approveWithdrawal = (id) => {
        if (!confirm('Is withdrawal ko approve karna hai?')) return;

        router.post(`/withdrawals/${id}/approve`, {}, {
            preserveScroll: true,
        });
    };

    const markProcessing = (id) => {
        if (!confirm('Is withdrawal ko processing me bhejna hai?')) return;

        router.post(`/withdrawals/${id}/processing`, {}, {
            preserveScroll: true,
        });
    };

    const rejectWithdrawal = (id) => {
        const note = prompt('Rejection note likhiye (optional):') ?? '';

        router.post(`/withdrawals/${id}/reject`, { note }, {
            preserveScroll: true,
        });
    };

    const markPaid = (id, currentMethod = '') => {
        const payment_reference = prompt(
            'Payment reference / UTR number likhiye:'
        );

        if (!payment_reference) return;

        const payment_method =
            prompt(
                'Payment method:',
                currentMethod || 'bank_transfer'
            ) || currentMethod || 'bank_transfer';

        router.post(
            `/withdrawals/${id}/paid`,
            {
                payment_reference,
                payment_method,
            },
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <AdminLayout title="Withdrawals">
            <Head title="Withdrawals" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        Withdrawals
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Create and manage wallet withdrawal requests.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat
                        label="Total Requests"
                        value={number(summary.total_requests)}
                    />

                    <Stat
                        label="Pending Amount"
                        value={money(
                            summary.pending_amount,
                            summary.currency
                        )}
                    />

                    <Stat
                        label="Approved Amount"
                        value={money(
                            summary.approved_amount,
                            summary.currency
                        )}
                    />

                    <Stat
                        label="Paid Amount"
                        value={money(
                            summary.paid_amount,
                            summary.currency
                        )}
                    />
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-4">
                        <h2 className="text-lg font-semibold text-slate-900">
                            Create Withdrawal Request
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Select wallet and enter amount.
                        </p>
                    </div>

                    <form
                        onSubmit={submitRequest}
                        className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    >
                        <div>
                            <label className="mb-2 block text-sm font-semibold text-slate-700">
                                Wallet
                            </label>

                            <select
                                value={requestForm.data.wallet_id}
                                onChange={(event) =>
                                    requestForm.setData(
                                        'wallet_id',
                                        event.target.value
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="">Select wallet</option>

                                {wallets.map((wallet) => (
                                    <option
                                        key={wallet.id}
                                        value={wallet.id}
                                    >
                                        {wallet.label_name || 'Unknown Label'} —{' '}
                                        {money(
                                            wallet.available_balance,
                                            wallet.currency
                                        )}
                                    </option>
                                ))}
                            </select>

                            {requestForm.errors.wallet_id && (
                                <p className="mt-1 text-xs text-rose-600">
                                    {requestForm.errors.wallet_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-semibold text-slate-700">
                                Amount
                            </label>

                            <input
                                type="number"
                                min="1"
                                step="0.01"
                                value={requestForm.data.amount}
                                onChange={(event) =>
                                    requestForm.setData(
                                        'amount',
                                        event.target.value
                                    )
                                }
                                placeholder="Enter amount"
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />

                            {requestForm.errors.amount && (
                                <p className="mt-1 text-xs text-rose-600">
                                    {requestForm.errors.amount}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-semibold text-slate-700">
                                Payment Method
                            </label>

                            <select
                                value={requestForm.data.payment_method}
                                onChange={(event) =>
                                    requestForm.setData(
                                        'payment_method',
                                        event.target.value
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="">Select method</option>
                                <option value="bank_transfer">
                                    Bank Transfer
                                </option>
                                <option value="upi">UPI</option>
                                <option value="paypal">PayPal</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-semibold text-slate-700">
                                Note
                            </label>

                            <input
                                type="text"
                                value={requestForm.data.note}
                                onChange={(event) =>
                                    requestForm.setData(
                                        'note',
                                        event.target.value
                                    )
                                }
                                placeholder="Optional note"
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />
                        </div>

                        <div className="md:col-span-2 xl:col-span-4">
                            <button
                                type="submit"
                                disabled={requestForm.processing}
                                className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                {requestForm.processing
                                    ? 'Creating...'
                                    : 'Create Request'}
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
                            placeholder="Withdrawal no., label or reference"
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
                                <option
                                    key={label.id}
                                    value={label.id}
                                >
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
                                <option
                                    key={status}
                                    value={status}
                                >
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
                            Withdrawal Requests
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">
                                        Withdrawal No.
                                    </th>
                                    <th className="px-4 py-3">Label</th>
                                    <th className="px-4 py-3 text-right">
                                        Amount
                                    </th>
                                    <th className="px-4 py-3">
                                        Method
                                    </th>
                                    <th className="px-4 py-3">
                                        Reference
                                    </th>
                                    <th className="px-4 py-3">
                                        Status
                                    </th>
                                    <th className="px-4 py-3">
                                        Requested
                                    </th>
                                    <th className="px-4 py-3">
                                        Paid
                                    </th>
                                    <th className="px-4 py-3">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {row.withdrawal_number}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {row.label_name ||
                                                'Unknown Label'}
                                        </td>

                                        <td className="px-4 py-4 text-right font-semibold text-slate-900">
                                            {money(
                                                row.amount,
                                                row.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {row.payment_method || '—'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {row.payment_reference || '—'}
                                        </td>

                                        <td className="px-4 py-4">
                                            <StatusBadge
                                                status={row.status}
                                            />
                                        </td>

                                        <td className="px-4 py-4 text-slate-600">
                                            {row.requested_at ||
                                                row.created_at ||
                                                '—'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-600">
                                            {row.paid_at || '—'}
                                        </td>

                                        <td className="px-4 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                {row.status === 'pending' && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            approveWithdrawal(
                                                                row.id
                                                            )
                                                        }
                                                        className="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white"
                                                    >
                                                        Approve
                                                    </button>
                                                )}

                                                {row.status === 'approved' && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            markProcessing(
                                                                row.id
                                                            )
                                                        }
                                                        className="rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white"
                                                    >
                                                        Processing
                                                    </button>
                                                )}

                                                {['approved', 'processing'].includes(
                                                    row.status
                                                ) && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            markPaid(
                                                                row.id,
                                                                row.payment_method
                                                            )
                                                        }
                                                        className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white"
                                                    >
                                                        Mark Paid
                                                    </button>
                                                )}

                                                {[
                                                    'pending',
                                                    'approved',
                                                    'processing',
                                                ].includes(row.status) && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            rejectWithdrawal(
                                                                row.id
                                                            )
                                                        }
                                                        className="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700"
                                                    >
                                                        Reject
                                                    </button>
                                                )}

                                                {![
                                                    'pending',
                                                    'approved',
                                                    'processing',
                                                ].includes(row.status) && (
                                                    <span className="text-xs text-slate-400">
                                                        No actions
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}

                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="9"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No withdrawal requests found.
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
        processing: 'bg-violet-50 text-violet-700',
        paid: 'bg-emerald-50 text-emerald-700',
        rejected: 'bg-rose-50 text-rose-700',
        cancelled: 'bg-slate-100 text-slate-700',
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
