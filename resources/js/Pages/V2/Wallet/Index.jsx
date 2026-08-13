import {
    Head,
    Link,
} from '@inertiajs/react';
import {
    ChevronRight,
    Clock3,
    ReceiptText,
    WalletCards,
} from 'lucide-react';
import { useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    wallet = {},
    transactions = {},
    minimumWithdrawal = 0,
}) {
    const [historyOpen, setHistoryOpen] =
        useState(false);

    const currency =
        wallet.currency ?? 'INR';

    const available =
        Number(
            wallet.available_balance ?? 0
        );

    const minimumWithdrawalAmount =
        Number(minimumWithdrawal ?? 0);

    const canRequestPayment =
        available >= minimumWithdrawalAmount &&
        minimumWithdrawalAmount > 0;

    const pending =
        Number(
            wallet.pending_balance ?? 0
        );

    const hold =
        Number(
            wallet.hold_balance ?? 0
        );

    const upcoming =
        pending + hold;

    const transactionRows =
        transactions.data ?? [];

    const formatMoney = (value) => {
        const amount = Number(value ?? 0);

        try {
            return new Intl.NumberFormat(
                'en-IN',
                {
                    style: 'currency',
                    currency,
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }
            ).format(amount);
        } catch {
            return `${currency} ${amount.toFixed(2)}`;
        }
    };

    const formattedDate =
        new Intl.DateTimeFormat(
            'en-GB',
            {
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            }
        ).format(new Date());

    return (
        <PanelLayout
            role={role}
            title="Wallet"
            subtitle="Balance, payments and transaction history"
        >
            <Head title="Wallet" />

            <div className="mx-auto w-full max-w-[1500px]">
                <section>
                    <h1 className="text-[28px] font-semibold tracking-tight text-slate-950 sm:text-[32px]">
                        My available balance
                    </h1>

                    <p className="mt-3 max-w-5xl text-[15px] leading-7 text-slate-500 sm:text-base">
                        Your available balance is calculated
                        according to your royalties and any
                        amounts currently being processed.
                    </p>

                    <Link
                        href="/v2/royalties"
                        className="mt-1 inline-block text-[15px] font-medium text-blue-600 underline underline-offset-2 transition hover:text-blue-700"
                    >
                        Find out more about your royalties
                    </Link>
                </section>

                <section className="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="grid gap-8 px-6 py-7 sm:px-8 lg:grid-cols-[1fr_auto] lg:items-start lg:px-10 lg:py-8">
                        <div>
                            <div className="flex items-start gap-3">
                                <div className="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                    <WalletCards
                                        size={21}
                                        strokeWidth={1.8}
                                    />
                                </div>

                                <div>
                                    <h2 className="text-[18px] font-semibold text-slate-950">
                                        Wallet Account
                                    </h2>

                                    <p className="mt-1 text-sm text-slate-500">
                                        Currency :{' '}
                                        <span className="font-medium text-slate-700">
                                            {currency}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            {canRequestPayment ? (
                                <Link
                                    href="/v2/invoices"
                                    className="mt-8 inline-flex min-h-12 items-center justify-center rounded-lg bg-violet-600 px-6 text-[15px] font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus:ring-4 focus:ring-violet-100"
                                >
                                    Request my payment
                                </Link>
                            ) : (
                                <div className="mt-8">
                                    <button
                                        type="button"
                                        disabled
                                        className="inline-flex min-h-12 cursor-not-allowed items-center justify-center rounded-lg bg-slate-200 px-6 text-[15px] font-semibold text-slate-500"
                                    >
                                        Request my payment
                                    </button>

                                    <p className="mt-2 text-sm text-slate-500">
                                        Minimum withdrawal amount is{' '}
                                        <span className="font-semibold text-slate-700">
                                            {formatMoney(
                                                minimumWithdrawalAmount
                                            )}
                                        </span>
                                    </p>

                                    <p className="mt-1 text-xs text-slate-400">
                                        You need{' '}
                                        {formatMoney(
                                            Math.max(
                                                minimumWithdrawalAmount -
                                                    available,
                                                0
                                            )
                                        )}{' '}
                                        more before requesting payment.
                                    </p>
                                </div>
                            )}
                        </div>

                        <div className="min-w-[280px] lg:text-right">
                            <p className="text-sm font-medium text-slate-500">
                                Available balance on{' '}
                                {formattedDate}
                            </p>

                            <div className="mt-1 text-[34px] font-semibold tracking-tight text-slate-950 sm:text-[38px]">
                                {formatMoney(available)}
                            </div>

                            <div className="mt-2 flex items-center gap-1.5 text-sm text-slate-500 lg:justify-end">
                                <Clock3
                                    size={16}
                                    strokeWidth={1.8}
                                />

                                <span>
                                    Upcoming operations :{' '}
                                    {formatMoney(upcoming)}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="border-t border-slate-200">
                        <button
                            type="button"
                            onClick={() =>
                                setHistoryOpen(
                                    (current) => !current
                                )
                            }
                            className="flex w-full items-center justify-between px-6 py-5 text-left transition hover:bg-slate-50 sm:px-8 lg:px-10"
                        >
                            <span className="flex items-center gap-3 text-[16px] font-medium text-blue-600">
                                <ReceiptText
                                    size={19}
                                    strokeWidth={1.8}
                                />

                                Transaction history & invoices
                            </span>

                            <ChevronRight
                                size={20}
                                className={`text-blue-500 transition-transform duration-200 ${
                                    historyOpen
                                        ? 'rotate-90'
                                        : ''
                                }`}
                            />
                        </button>

                        {historyOpen && (
                            <div className="border-t border-slate-100">
                                <div className="flex flex-wrap gap-3 px-6 py-4 sm:px-8 lg:px-10">
                                    <Link
                                        href="/v2/invoices"
                                        className="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                    >
                                        View invoices
                                    </Link>

                                </div>

                                <div className="overflow-x-auto border-t border-slate-100">
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
                                                ].map(
                                                    (
                                                        heading
                                                    ) => (
                                                        <th
                                                            key={
                                                                heading
                                                            }
                                                            className="whitespace-nowrap px-6 py-3.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:px-8 lg:first:pl-10 lg:last:pr-10"
                                                        >
                                                            {
                                                                heading
                                                            }
                                                        </th>
                                                    )
                                                )}
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-slate-100 bg-white">
                                            {transactionRows.length >
                                            0 ? (
                                                transactionRows.map(
                                                    (
                                                        item
                                                    ) => (
                                                        <tr
                                                            key={
                                                                item.id
                                                            }
                                                            className="hover:bg-slate-50/70"
                                                        >
                                                            <Cell>
                                                                {item.created_at ??
                                                                    '—'}
                                                            </Cell>

                                                            <Cell>
                                                                {item.type ??
                                                                    item.direction ??
                                                                    '—'}
                                                            </Cell>

                                                            <Cell>
                                                                {item.category ??
                                                                    item.transaction_type ??
                                                                    '—'}
                                                            </Cell>

                                                            <Cell>
                                                                {item.description ||
                                                                    '—'}
                                                            </Cell>

                                                            <Cell>
                                                                <span
                                                                    className={
                                                                        (
                                                                            item.type ??
                                                                            item.direction
                                                                        ) ===
                                                                        'credit'
                                                                            ? 'font-semibold text-emerald-600'
                                                                            : 'font-semibold text-slate-800'
                                                                    }
                                                                >
                                                                    {(
                                                                        item.type ??
                                                                        item.direction
                                                                    ) ===
                                                                    'credit'
                                                                        ? '+'
                                                                        : '-'}
                                                                    {formatMoney(
                                                                        item.amount
                                                                    )}
                                                                </span>
                                                            </Cell>

                                                            <Cell>
                                                                {formatMoney(
                                                                    item.balance_after
                                                                )}
                                                            </Cell>
                                                        </tr>
                                                    )
                                                )
                                            ) : (
                                                <tr>
                                                    <td
                                                        colSpan="6"
                                                        className="px-6 py-12 text-center sm:px-8"
                                                    >
                                                        <div className="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                                            <ReceiptText
                                                                size={
                                                                    20
                                                                }
                                                            />
                                                        </div>

                                                        <p className="mt-3 text-sm font-medium text-slate-700">
                                                            No
                                                            transactions
                                                            yet
                                                        </p>

                                                        <p className="mt-1 text-sm text-slate-500">
                                                            Your wallet
                                                            transactions
                                                            will appear
                                                            here.
                                                        </p>
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                </section>

                <div className="mt-5 grid gap-4 sm:grid-cols-3">
                    <SummaryCard
                        label="Pending Balance"
                        value={formatMoney(
                            pending
                        )}
                    />

                    <SummaryCard
                        label="On Hold"
                        value={formatMoney(
                            hold
                        )}
                    />

                    <SummaryCard
                        label="Lifetime Earnings"
                        value={formatMoney(
                            wallet.lifetime_earnings ??
                                wallet.lifetime_credits ??
                                0
                        )}
                    />
                </div>
            </div>
        </PanelLayout>
    );
}

function SummaryCard({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                {label}
            </p>

            <p className="mt-2 text-lg font-semibold text-slate-900">
                {value}
            </p>
        </div>
    );
}

function Cell({ children }) {
    return (
        <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-600 sm:px-8 lg:first:pl-10 lg:last:pr-10">
            {children}
        </td>
    );
}
