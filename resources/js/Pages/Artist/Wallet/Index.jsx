import ArtistLayout from '@/Layouts/ArtistLayout';
import { Head, Link } from '@inertiajs/react';

const money = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

export default function WalletIndex({
    artist = {},
    wallet = {},
    transactions = {},
}) {
    const currency = wallet.currency || artist.currency || 'INR';
    const rows = transactions?.data ?? [];

    const cards = [
        {
            title: 'Available Balance',
            value: money(wallet.available_balance, currency),
            note: 'Ready for withdrawal',
        },
        {
            title: 'Pending Balance',
            value: money(wallet.pending_balance, currency),
            note: 'Awaiting settlement',
        },
        {
            title: 'Lifetime Credits',
            value: money(wallet.lifetime_credits, currency),
            note: 'Total credited',
        },
        {
            title: 'Lifetime Debits',
            value: money(wallet.lifetime_debits, currency),
            note: 'Total withdrawn',
        },
    ];

    return (
        <ArtistLayout
            title="Wallet"
            subtitle="Manage your earnings and transaction history"
        >
            <Head title="Artist Wallet" />

            <section className="overflow-hidden rounded-3xl bg-[#0d1526] px-7 py-8 text-white shadow-xl lg:px-10">
                <div className="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                    <div>
                        <p className="text-sm font-medium text-slate-400">
                            ARTIST WALLET
                        </p>

                        <h2 className="mt-2 text-3xl font-bold lg:text-4xl">
                            {money(wallet.available_balance, currency)}
                        </h2>

                        <p className="mt-3 text-sm text-slate-300">
                            Available balance for{' '}
                            {artist.stage_name || 'your artist account'}.
                        </p>
                    </div>

                    <Link
                        href="/withdrawals"
                        className="w-fit rounded-xl bg-white px-6 py-3 text-sm font-semibold text-[#0d1526]"
                    >
                        Request Withdrawal
                    </Link>
                </div>
            </section>

            <section className="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map((card) => (
                    <article
                        key={card.title}
                        className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <p className="text-sm font-medium text-slate-500">
                            {card.title}
                        </p>

                        <p className="mt-3 text-2xl font-bold text-slate-900">
                            {card.value}
                        </p>

                        <p className="mt-3 text-xs text-slate-400">
                            {card.note}
                        </p>
                    </article>
                ))}
            </section>

            <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 className="text-lg font-bold text-slate-900">
                            Transaction History
                        </h3>

                        <p className="mt-1 text-sm text-slate-500">
                            Credits, debits and balance changes.
                        </p>
                    </div>

                    <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                        {wallet.status || 'active'}
                    </span>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-6 py-4">Date</th>
                                <th className="px-6 py-4">Description</th>
                                <th className="px-6 py-4">Type</th>
                                <th className="px-6 py-4">Direction</th>
                                <th className="px-6 py-4 text-right">
                                    Amount
                                </th>
                                <th className="px-6 py-4 text-right">
                                    Balance
                                </th>
                                <th className="px-6 py-4">Status</th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {rows.map((transaction) => (
                                <tr key={transaction.id}>
                                    <td className="whitespace-nowrap px-6 py-4 text-slate-600">
                                        {formatDate(
                                            transaction.posted_at ||
                                                transaction.effective_at ||
                                                transaction.created_at
                                        )}
                                    </td>

                                    <td className="px-6 py-4 font-medium text-slate-900">
                                        {transaction.description ||
                                            transaction.transaction_type}
                                    </td>

                                    <td className="px-6 py-4 capitalize text-slate-600">
                                        {transaction.transaction_type ||
                                            'transaction'}
                                    </td>

                                    <td className="px-6 py-4">
                                        <span
                                            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ${
                                                transaction.direction ===
                                                'credit'
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-rose-50 text-rose-700'
                                            }`}
                                        >
                                            {transaction.direction || 'debit'}
                                        </span>
                                    </td>

                                    <td
                                        className={`px-6 py-4 text-right font-bold ${
                                            transaction.direction === 'credit'
                                                ? 'text-emerald-600'
                                                : 'text-rose-600'
                                        }`}
                                    >
                                        {transaction.direction === 'credit'
                                            ? '+'
                                            : '-'}
                                        {money(
                                            transaction.amount,
                                            transaction.currency || currency
                                        )}
                                    </td>

                                    <td className="px-6 py-4 text-right font-semibold text-slate-900">
                                        {money(
                                            transaction.balance_after,
                                            transaction.currency || currency
                                        )}
                                    </td>

                                    <td className="px-6 py-4">
                                        <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">
                                            {transaction.status || 'posted'}
                                        </span>
                                    </td>
                                </tr>
                            ))}

                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan="7"
                                        className="px-6 py-12 text-center text-slate-500"
                                    >
                                        No wallet transactions yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </ArtistLayout>
    );
}
