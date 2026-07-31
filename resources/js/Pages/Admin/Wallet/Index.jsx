import { Head, router } from '@inertiajs/react';
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

export default function WalletIndex({
    wallets = {},
    transactions = {},
    summary = {},
    filterOptions = {},
    filters = {},
}) {
    const [form, setForm] = useState({
        search: filters.search ?? '',
        label_id: filters.label_id ?? '',
        currency: filters.currency ?? '',
    });

    const applyFilters = (event) => {
        event.preventDefault();

        router.get('/wallet', form, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setForm({
            search: '',
            label_id: '',
            currency: '',
        });

        router.get('/wallet');
    };

    const walletRows = wallets?.data ?? [];
    const transactionRows = transactions?.data ?? [];

    return (
        <AdminLayout title="Wallet">
            <Head title="Wallet" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        Wallet
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Label balances, lifetime earnings and wallet transactions.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Stat
                        label="Total Wallets"
                        value={number(summary.total_wallets)}
                    />

                    <Stat
                        label="Available Balance"
                        value={money(
                            summary.available_balance,
                            summary.currency
                        )}
                    />

                    <Stat
                        label="Pending Balance"
                        value={money(
                            summary.pending_balance,
                            summary.currency
                        )}
                    />

                    <Stat
                        label="Lifetime Credits"
                        value={money(
                            summary.lifetime_credits,
                            summary.currency
                        )}
                    />

                    <Stat
                        label="Lifetime Debits"
                        value={money(
                            summary.lifetime_debits,
                            summary.currency
                        )}
                    />
                </div>

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
                            placeholder="Label or wallet ID"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

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
                            value={form.currency}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    currency: event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All Currencies</option>

                            {(filterOptions.currencies ?? []).map(
                                (currency) => (
                                    <option
                                        key={currency}
                                        value={currency}
                                    >
                                        {currency}
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
                            Label Wallets
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">
                                        Label
                                    </th>
                                    <th className="px-4 py-3">
                                        Currency
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Available
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Pending
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Lifetime Credits
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Lifetime Debits
                                    </th>
                                    <th className="px-4 py-3">
                                        Status
                                    </th>
                                    <th className="px-4 py-3">
                                        Updated
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {walletRows.map((wallet) => (
                                    <tr key={wallet.id}>
                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {wallet.label_name ||
                                                'Unknown Label'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {wallet.currency}
                                        </td>

                                        <td className="px-4 py-4 text-right font-semibold text-slate-900">
                                            {money(
                                                wallet.available_balance,
                                                wallet.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {money(
                                                wallet.pending_balance,
                                                wallet.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-right text-emerald-700">
                                            {money(
                                                wallet.lifetime_credits,
                                                wallet.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-right text-rose-700">
                                            {money(
                                                wallet.lifetime_debits,
                                                wallet.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4">
                                            <StatusBadge
                                                status={wallet.status}
                                            />
                                        </td>

                                        <td className="px-4 py-4 text-slate-600">
                                            {wallet.updated_at || '—'}
                                        </td>
                                    </tr>
                                ))}

                                {walletRows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="8"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No wallets found.
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
                            Transaction History
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">
                                        Label
                                    </th>
                                    <th className="px-4 py-3">
                                        Type
                                    </th>
                                    <th className="px-4 py-3">
                                        Direction
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Amount
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Before
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        After
                                    </th>
                                    <th className="px-4 py-3">
                                        Description
                                    </th>
                                    <th className="px-4 py-3">
                                        Status
                                    </th>
                                    <th className="px-4 py-3">
                                        Posted
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {transactionRows.map((transaction) => (
                                    <tr key={transaction.id}>
                                        <td className="px-4 py-4 font-semibold text-slate-900">
                                            {transaction.label_name ||
                                                'Unknown Label'}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {transaction.transaction_type}
                                        </td>

                                        <td className="px-4 py-4">
                                            <DirectionBadge
                                                direction={
                                                    transaction.direction
                                                }
                                            />
                                        </td>

                                        <td className="px-4 py-4 text-right font-semibold text-slate-900">
                                            {money(
                                                transaction.amount,
                                                transaction.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {money(
                                                transaction.balance_before,
                                                transaction.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-right text-slate-700">
                                            {money(
                                                transaction.balance_after,
                                                transaction.currency
                                            )}
                                        </td>

                                        <td className="px-4 py-4 text-slate-700">
                                            {transaction.description || '—'}
                                        </td>

                                        <td className="px-4 py-4">
                                            <StatusBadge
                                                status={
                                                    transaction.status
                                                }
                                            />
                                        </td>

                                        <td className="px-4 py-4 text-slate-600">
                                            {transaction.posted_at ||
                                                transaction.created_at ||
                                                '—'}
                                        </td>
                                    </tr>
                                ))}

                                {transactionRows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="9"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No wallet transactions found.
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
    return (
        <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
            {status || 'unknown'}
        </span>
    );
}

function DirectionBadge({ direction }) {
    const isCredit = direction === 'credit';

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                isCredit
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-rose-50 text-rose-700'
            }`}
        >
            {direction || 'unknown'}
        </span>
    );
}
