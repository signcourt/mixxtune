import {
    Head,
    Link,
    router,
} from '@inertiajs/react';
import {
    ChevronRight,
    Clock3,
    ReceiptText,
    WalletCards,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    wallet = {},
    transactions = {},
    minimumWithdrawal = 0,
    withdrawalEnabled = true,
    owners = null,
    ownerSearch = '',
    advances = [],
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

    const isAdminMonitoring =
        role === 'admin' ||
        role === 'super_admin';

    const canRequestPayment =
        withdrawalEnabled &&
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
                    {/* MIXXTUNE_FINANCE_CLEANUP_V1: admin wording + links */}
                    <h1 className="text-[28px] font-semibold tracking-tight text-slate-950 sm:text-[32px]">
                        {isAdminMonitoring
                            ? 'All owners: wallet balances'
                            : 'My available balance'}
                    </h1>

                    <p className="mt-3 max-w-5xl text-[15px] leading-7 text-slate-500 sm:text-base">
                        {isAdminMonitoring
                            ? 'Totals of every owner wallet you can see. The table below shows each owner separately.'
                            : 'Your available balance is calculated according to your royalties and any amounts currently being processed.'}
                    </p>

                    <Link
                        href={isAdminMonitoring ? '/v2/admin/finance' : '/v2/royalties'}
                        className="mt-1 inline-block text-[15px] font-medium text-blue-600 underline underline-offset-2 transition hover:text-blue-700"
                    >
                        {isAdminMonitoring
                            ? 'Open Finance & Royalties'
                            : 'Find out more about your royalties'}
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

                            {!isAdminMonitoring &&
                                (canRequestPayment ? (
                                    <Link
                                        href="/v2/withdrawals"
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

                                        {!withdrawalEnabled && (
                                            <div className="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                                                <p className="text-sm font-semibold text-amber-800">
                                                    Withdrawals suspended
                                                </p>
                                                <p className="mt-1 text-xs leading-5 text-amber-700">
                                                    New withdrawal requests are currently disabled for this account.
                                                </p>
                                            </div>
                                        )}

                                        {withdrawalEnabled && (
                                            <>
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
                                            </>
                                        )}
                                    </div>
                                ))}
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
                                        href={isAdminMonitoring && role === 'super_admin' ? '/v2/admin/invoices' : '/v2/invoices'}
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

                {!isAdminMonitoring && advances.length > 0 && (
                    <AdvanceCard advances={advances} />
                )}

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
                        label={
                            isAdminMonitoring
                                ? 'Lifetime Credits'
                                : 'Lifetime Earnings'
                        }
                        value={formatMoney(
                            wallet.lifetime_earnings ??
                                wallet.lifetime_credits ??
                                0
                        )}
                    />
                </div>

                {isAdminMonitoring && owners && (
                    <OwnerBalances
                        owners={owners}
                        initialSearch={ownerSearch}
                        formatMoney={formatMoney}
                    />
                )}
            </div>
        </PanelLayout>
    );
}

/* MIXXTUNE_FINANCE_CLEANUP_V1: per-owner wallet list for admins */
function OwnerBalances({
    owners,
    initialSearch = '',
    formatMoney,
}) {
    const [search, setSearch] = useState(initialSearch ?? '');
    const firstRun = useRef(true);
    const rows = owners.data ?? [];

    useEffect(() => {
        if (firstRun.current) {
            firstRun.current = false;
            return undefined;
        }

        const timer = window.setTimeout(() => {
            router.get(
                window.location.pathname,
                { owner_search: search.trim() || undefined },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        }, 400);

        return () => window.clearTimeout(timer);
    }, [search]);

    return (
        <section className="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">
                        Balance per owner
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                        {Number(owners.total ?? rows.length).toLocaleString('en-IN')} wallets · highest available balance first
                    </p>
                </div>

                <input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search owner name or e-mail..."
                    className="h-11 w-full rounded-xl border border-slate-300 px-4 text-sm sm:w-72"
                />
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full">
                    <thead>
                        <tr className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-6 py-3">Owner</th>
                            <th className="px-6 py-3 text-right">Available</th>
                            <th className="px-6 py-3 text-right">Pending / on hold</th>
                            <th className="px-6 py-3 text-right">Lifetime credits</th>
                            <th className="px-6 py-3 text-right">Lifetime debits</th>
                            <th className="px-6 py-3">Last change</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t border-slate-100 text-sm text-slate-700">
                                <td className="px-6 py-3">
                                    <div className="font-semibold text-slate-900">{row.owner_name || 'User #' + row.user_id}</div>
                                    <div className="text-xs text-slate-500">{row.owner_email || '—'} · {row.owner_role || '—'}</div>
                                </td>
                                <td className="px-6 py-3 text-right font-semibold text-slate-900">{formatMoney(row.available_balance)}</td>
                                <td className="px-6 py-3 text-right">{formatMoney(row.pending_balance)}</td>
                                <td className="px-6 py-3 text-right">{formatMoney(row.lifetime_credits)}</td>
                                <td className="px-6 py-3 text-right">{formatMoney(row.lifetime_debits)}</td>
                                <td className="px-6 py-3 text-xs text-slate-500">{row.updated_at ? String(row.updated_at).slice(0, 10) : '—'}</td>
                            </tr>
                        ))}

                        {rows.length === 0 && (
                            <tr>
                                <td colSpan="6" className="px-6 py-10 text-center text-sm text-slate-500">
                                    No owner wallets found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {(owners.links ?? []).length > 3 && (
                <div className="flex flex-wrap gap-2 border-t border-slate-100 px-6 py-4">
                    {owners.links.map((link, index) => (
                        <Link
                            key={index}
                            href={link.url || '#'}
                            preserveScroll
                            className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-violet-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'} ${link.url ? '' : 'pointer-events-none opacity-40'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </section>
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

/* MIXXTUNE_RECOUPMENT_V2: the owner sees the advance being repaid */
function AdvanceCard({ advances }) {
    const inr = (v) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(v || 0));

    return (
        <section className="mt-5 rounded-xl border border-amber-200 bg-amber-50/60 px-6 py-5 sm:px-8">
            <h2 className="text-[16px] font-semibold text-slate-950">Advance being repaid</h2>
            <p className="mt-1 text-sm text-slate-600">
                A part of your royalties goes to repay the advance / costs below until they are fully recovered. Questions? Open a support ticket.
            </p>
            <div className="mt-4 space-y-3">
                {advances.map((a) => {
                    const pct = a.total > 0 ? Math.min(100, Math.round((a.recovered / a.total) * 100)) : 0;
                    return (
                        <div key={a.plan_number} className="rounded-lg border border-amber-200 bg-white px-4 py-3">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <div className="text-sm font-semibold text-slate-900">{a.title} <span className="font-normal text-slate-500">· {a.plan_number}</span></div>
                                <div className="text-sm font-semibold text-amber-800">Still to repay {inr(a.outstanding)}</div>
                            </div>
                            <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div className="h-full rounded-full bg-emerald-500" style={{ width: `${pct}%` }} />
                            </div>
                            <div className="mt-1.5 flex flex-wrap justify-between gap-2 text-xs text-slate-500">
                                <span>Repaid {inr(a.recovered)} of {inr(a.total)} ({pct}%)</span>
                                <span>{a.recovery}% of revenue used for repayment</span>
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
