import {
    Head,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    wallet = {},
    transactions = {},
}) {
    return (
        <PanelLayout
            role={role}
            title="Wallet"
            subtitle="Available balance and transaction history"
        >
            <Head title="Wallet" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Balance
                        label="Available"
                        value={
                            wallet.available_balance
                        }
                    />

                    <Balance
                        label="Pending"
                        value={
                            wallet.pending_balance
                        }
                    />

                    <Balance
                        label="On Hold"
                        value={
                            wallet.hold_balance
                        }
                    />

                    <Balance
                        label="Withdrawn"
                        value={
                            wallet.withdrawn_balance
                        }
                    />

                    <Balance
                        label="Lifetime Earnings"
                        value={
                            wallet.lifetime_earnings
                        }
                    />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'Date',
                                    'Type',
                                    'Category',
                                    'Description',
                                    'Amount',
                                    'Balance',
                                ].map((heading) => (
                                    <th
                                        key={heading}
                                        className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {(transactions.data ?? []).map(
                                (item) => (
                                    <tr key={item.id}>
                                        <Cell>
                                            {item.created_at}
                                        </Cell>

                                        <Cell>
                                            {item.type}
                                        </Cell>

                                        <Cell>
                                            {item.category}
                                        </Cell>

                                        <Cell>
                                            {item.description ||
                                                '—'}
                                        </Cell>

                                        <Cell>
                                            <span
                                                className={
                                                    item.type ===
                                                    'credit'
                                                        ? 'font-semibold text-emerald-600'
                                                        : 'font-semibold text-red-600'
                                                }
                                            >
                                                {item.type ===
                                                'credit'
                                                    ? '+'
                                                    : '-'}
                                                ₹{item.amount}
                                            </span>
                                        </Cell>

                                        <Cell>
                                            ₹
                                            {item.balance_after}
                                        </Cell>
                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </PanelLayout>
    );
}

function Balance({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-2xl font-bold text-slate-900">
                ₹
                {Number(value ?? 0).toFixed(
                    2
                )}
            </div>
        </div>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
