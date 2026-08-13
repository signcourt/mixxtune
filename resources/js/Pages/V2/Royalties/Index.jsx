import {
    Head,
    Link,
} from '@inertiajs/react';

import {
    useMemo,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const summaryCards = [
    {
        key: 'gross',
        label: 'Gross Earnings',
        description: 'Total royalty generated',
        symbol: '₹',
        tone:
            'border-violet-200 bg-violet-50 text-violet-700',
    },
    {
        key: 'pending',
        label: 'Pending',
        description: 'Awaiting approval',
        symbol: '⌛',
        tone:
            'border-amber-200 bg-amber-50 text-amber-700',
    },
    {
        key: 'approved',
        label: 'Approved',
        description: 'Approved for processing',
        symbol: '✓',
        tone:
            'border-blue-200 bg-blue-50 text-blue-700',
    },
    {
        key: 'available',
        label: 'Available',
        description: 'Ready in wallet',
        symbol: '₹',
        tone:
            'border-emerald-200 bg-emerald-50 text-emerald-700',
    },
    {
        key: 'paid',
        label: 'Paid',
        description: 'Successfully settled',
        symbol: '✓',
        tone:
            'border-green-200 bg-green-50 text-green-700',
    },
];

const statusClasses = {
    pending:
        'bg-amber-50 text-amber-700 ring-amber-200',

    approved:
        'bg-blue-50 text-blue-700 ring-blue-200',

    available:
        'bg-emerald-50 text-emerald-700 ring-emerald-200',

    paid:
        'bg-green-50 text-green-700 ring-green-200',

    cancelled:
        'bg-rose-50 text-rose-700 ring-rose-200',
};

const currencySymbols = {
    INR: '₹',
    USD: '$',
    EUR: '€',
    GBP: '£',
};

const formatAmount = (
    value,
    currency = 'INR'
) => {
    const numericValue =
        Number(value ?? 0);

    try {
        return new Intl.NumberFormat(
            'en-IN',
            {
                style: 'currency',
                currency:
                    currency || 'INR',
                maximumFractionDigits: 2,
            }
        ).format(numericValue);
    } catch {
        return `${
            currencySymbols[
                currency
            ] ?? currency
        } ${numericValue.toFixed(2)}`;
    }
};

const formatMonth = (value) => {
    if (!value) {
        return 'Unknown Month';
    }

    const date = new Date(
        `${value}-01T00:00:00`
    );

    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-IN',
        {
            month: 'long',
            year: 'numeric',
        }
    ).format(date);
};

const formatDateTime = (value) => {
    if (!value) {
        return 'Not available';
    }

    const date = new Date(value);

    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-IN',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }
    ).format(date);
};

export default function Index({
    role = 'artist',
    summary = {},
    statements = {},
}) {
    const rows =
        statements.data ?? [];

    const [statusFilter, setStatusFilter] =
        useState('');

    const [searchValue, setSearchValue] =
        useState('');

    const filteredRows =
        useMemo(() => {
            const search =
                searchValue
                    .trim()
                    .toLowerCase();

            return rows.filter(
                (item) => {
                    const matchesStatus =
                        !statusFilter ||
                        item.status ===
                            statusFilter;

                    const matchesSearch =
                        !search ||
                        String(
                            item.statement_month ??
                                ''
                        )
                            .toLowerCase()
                            .includes(
                                search
                            ) ||
                        String(
                            item.public_id ??
                                ''
                        )
                            .toLowerCase()
                            .includes(
                                search
                            ) ||
                        String(
                            item.currency ??
                                ''
                        )
                            .toLowerCase()
                            .includes(
                                search
                            );

                    return (
                        matchesStatus &&
                        matchesSearch
                    );
                }
            );
        }, [
            rows,
            statusFilter,
            searchValue,
        ]);

    const chartRows =
        useMemo(() => {
            return [...rows]
                .sort((a, b) =>
                    String(
                        a.statement_month ??
                            ''
                    ).localeCompare(
                        String(
                            b.statement_month ??
                                ''
                        )
                    )
                )
                .slice(-12);
        }, [rows]);

    const maxChartValue =
        Math.max(
            1,
            ...chartRows.map(
                (item) =>
                    Number(
                        item.net_payable ??
                            0
                    )
            )
        );

    const title =
        role === 'artist'
            ? 'My Royalties'
            : role === 'label'
              ? 'Label Royalties'
              : 'Royalties';

    return (
        <PanelLayout
            role={role}
            title={title}
            subtitle="Monthly royalty statements, earnings and payment status"
        >
            <Head title={title} />

            <div className="space-y-6">
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {summaryCards.map(
                        (card) => (
                            <SummaryCard
                                key={
                                    card.key
                                }
                                card={
                                    card
                                }
                                value={
                                    summary[
                                        card.key
                                    ] ?? 0
                                }
                            />
                        )
                    )}
                </section>

                <section className="grid gap-6">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-bold text-slate-900">
                                    Monthly Earnings
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    Net payable amount
                                    across recent
                                    statements
                                </p>
                            </div>

                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                Last{' '}
                                {
                                    chartRows.length
                                }{' '}
                                months
                            </span>
                        </div>

                        {chartRows.length >
                        0 ? (
                            <div className="mt-8">
                                <div className="flex h-64 items-end gap-3 overflow-x-auto pb-2">
                                    {chartRows.map(
                                        (
                                            item,
                                            index
                                        ) => {
                                            const amount =
                                                Number(
                                                    item.net_payable ??
                                                        0
                                                );

                                            const height =
                                                Math.max(
                                                    6,
                                                    Math.round(
                                                        (amount /
                                                            maxChartValue) *
                                                            100
                                                    )
                                                );

                                            return (
                                                <div
                                                    key={
                                                        item.id ??
                                                        index
                                                    }
                                                    className="group flex min-w-[54px] flex-1 flex-col items-center justify-end"
                                                >
                                                    <div className="mb-2 hidden rounded-lg bg-slate-900 px-2 py-1 text-[10px] font-bold text-white group-hover:block">
                                                        {formatAmount(
                                                            amount,
                                                            item.currency
                                                        )}
                                                    </div>

                                                    <div className="flex h-48 w-full items-end rounded-xl bg-slate-50 p-1.5">
                                                        <div
                                                            className="w-full rounded-lg bg-violet-600 transition hover:bg-violet-700"
                                                            style={{
                                                                height: `${height}%`,
                                                            }}
                                                        />
                                                    </div>

                                                    <div className="mt-2 max-w-[60px] truncate text-center text-[10px] font-semibold text-slate-500">
                                                        {
                                                            item.statement_month
                                                        }
                                                    </div>
                                                </div>
                                            );
                                        }
                                    )}
                                </div>
                            </div>
                        ) : (
                            <ChartEmptyState />
                        )}
                    </div>

                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-lg font-bold text-slate-900">
                                Royalty Statements
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                {
                                    filteredRows.length
                                }{' '}
                                statement
                                {filteredRows.length ===
                                1
                                    ? ''
                                    : 's'}{' '}
                                shown on this page
                            </p>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-[minmax(220px,1fr)_180px]">
                            <input
                                type="search"
                                value={
                                    searchValue
                                }
                                onChange={(
                                    event
                                ) =>
                                    setSearchValue(
                                        event
                                            .target
                                            .value
                                    )
                                }
                                placeholder="Search month, ID or currency..."
                                className="rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                            />

                            <select
                                value={
                                    statusFilter
                                }
                                onChange={(
                                    event
                                ) =>
                                    setStatusFilter(
                                        event
                                            .target
                                            .value
                                    )
                                }
                                className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 outline-none focus:border-violet-500"
                            >
                                <option value="">
                                    All Statuses
                                </option>

                                <option value="pending">
                                    Pending
                                </option>

                                <option value="approved">
                                    Approved
                                </option>

                                <option value="available">
                                    Available
                                </option>

                                <option value="paid">
                                    Paid
                                </option>

                                <option value="cancelled">
                                    Cancelled
                                </option>
                            </select>
                        </div>
                    </div>

                    {filteredRows.length >
                    0 ? (
                        <StatementTable
                            rows={
                                filteredRows
                            }
                        />
                    ) : (
                        <EmptyState />
                    )}
                </section>

                <Pagination
                    links={
                        statements.links ??
                        []
                    }
                />
            </div>
        </PanelLayout>
    );
}

function SummaryCard({
    card,
    value,
}) {
    return (
        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md">
            <div className="flex items-start justify-between gap-3">
                <div
                    className={[
                        'flex h-10 w-10 items-center justify-center rounded-xl border text-sm font-black',
                        card.tone,
                    ].join(' ')}
                >
                    {card.symbol}
                </div>

                <span className="text-lg text-slate-300">
                    ↗
                </span>
            </div>

            <div className="mt-5 text-xs font-bold uppercase tracking-wider text-slate-500">
                {card.label}
            </div>

            <div className="mt-2 break-words text-2xl font-black tracking-tight text-slate-950">
                {formatAmount(
                    value,
                    'INR'
                )}
            </div>

            <p className="mt-2 text-xs text-slate-500">
                {card.description}
            </p>
        </article>
    );
}

function StatementTable({
    rows,
}) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-[1120px] w-full border-collapse">
                <thead>
                    <tr className="border-b border-slate-200 bg-slate-50/80">
                        <Heading>
                            Statement
                        </Heading>

                        <Heading>
                            Gross Earnings
                        </Heading>

                        <Heading>
                            Commission
                        </Heading>

                        <Heading>
                            Tax
                        </Heading>

                        <Heading>
                            Other Deductions
                        </Heading>

                        <Heading>
                            Net Payable
                        </Heading>

                        <Heading>
                            Status
                        </Heading>

                        <Heading align="right">
                            Action
                        </Heading>
                    </tr>
                </thead>

                <tbody>
                    {rows.map(
                        (item) => (
                            <StatementRow
                                key={
                                    item.id
                                }
                                item={
                                    item
                                }
                            />
                        )
                    )}
                </tbody>
            </table>
        </div>
    );
}

function StatementRow({
    item,
}) {
    const deductions =
        Number(
            item.commission_amount ??
                0
        ) +
        Number(
            item.tax_amount ?? 0
        ) +
        Number(
            item.other_deductions ??
                0
        );

    return (
        <tr className="group border-b border-slate-100 transition last:border-0 hover:bg-violet-50/30">
            <td className="px-5 py-4">
                <div className="min-w-[180px]">
                    <div className="font-bold text-slate-900">
                        {formatMonth(
                            item.statement_month
                        )}
                    </div>

                    <div className="mt-1 max-w-[190px] truncate text-xs font-medium text-slate-400">
                        {item.public_id ||
                            `Statement #${item.id}`}
                    </div>

                    <div className="mt-2 text-[10px] font-semibold text-slate-500">
                        Created{' '}
                        {formatDateTime(
                            item.created_at
                        )}
                    </div>
                </div>
            </td>

            <MoneyCell
                value={
                    item.gross_earnings
                }
                currency={
                    item.currency
                }
            />

            <MoneyCell
                value={
                    item.commission_amount
                }
                currency={
                    item.currency
                }
                muted
            />

            <MoneyCell
                value={
                    item.tax_amount
                }
                currency={
                    item.currency
                }
                muted
            />

            <td className="px-5 py-4">
                <div className="text-sm font-semibold text-slate-600">
                    {formatAmount(
                        deductions,
                        item.currency
                    )}
                </div>
            </td>

            <td className="px-5 py-4">
                <div className="text-base font-black text-emerald-700">
                    {formatAmount(
                        item.net_payable,
                        item.currency
                    )}
                </div>
            </td>

            <td className="px-5 py-4">
                <StatusBadge
                    status={
                        item.status
                    }
                />
            </td>

            <td className="px-5 py-4 text-right">
                <div className="flex justify-end gap-2">
                    <Link
                        href={`/v2/statements/${item.id}/download`}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
                    >
                        Download
                    </Link>
                </div>
            </td>
        </tr>
    );
}

function MoneyCell({
    value,
    currency,
    muted = false,
}) {
    return (
        <td className="px-5 py-4">
            <div
                className={[
                    'text-sm font-bold',
                    muted
                        ? 'text-slate-500'
                        : 'text-slate-900',
                ].join(' ')}
            >
                {formatAmount(
                    value,
                    currency
                )}
            </div>
        </td>
    );
}

function StatusBadge({
    status,
}) {
    const safeStatus =
        status || 'pending';

    return (
        <span
            className={[
                'inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold capitalize ring-1 ring-inset',
                statusClasses[
                    safeStatus
                ] ??
                    'bg-slate-100 text-slate-700 ring-slate-200',
            ].join(' ')}
        >
            <span className="mr-1.5 h-1.5 w-1.5 rounded-full bg-current opacity-70" />

            {safeStatus.replaceAll(
                '_',
                ' '
            )}
        </span>
    );
}

function Heading({
    children,
    align = 'left',
}) {
    return (
        <th
            className={[
                'px-5 py-3.5 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500',
                align === 'right'
                    ? 'text-right'
                    : 'text-left',
            ].join(' ')}
        >
            {children}
        </th>
    );
}

function OverviewRow({
    label,
    value,
}) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-sm font-medium text-slate-400">
                {label}
            </span>

            <span className="text-sm font-bold text-white">
                {value}
            </span>
        </div>
    );
}

function ChartEmptyState() {
    return (
        <div className="mt-8 flex h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-center">
            <div className="text-3xl text-slate-300">
                ₹
            </div>

            <h3 className="mt-3 text-sm font-bold text-slate-700">
                No monthly earnings yet
            </h3>

            <p className="mt-1 text-xs text-slate-500">
                Generated statements will
                appear in this chart.
            </p>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="px-6 py-20 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-50 text-3xl font-black text-violet-600">
                ₹
            </div>

            <h3 className="mt-5 text-lg font-bold text-slate-900">
                No royalty statements found
            </h3>

            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                No statement matches the
                current search or status
                selection.
            </p>
        </div>
    );
}

function Pagination({
    links,
}) {
    if (!links.length) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center justify-center gap-2">
            {links.map(
                (link, index) => (
                    <Link
                        key={`${link.label}-${index}`}
                        href={
                            link.url ??
                            '#'
                        }
                        preserveScroll
                        preserveState
                        className={[
                            'inline-flex min-h-10 min-w-10 items-center justify-center rounded-xl border px-3 py-2 text-sm font-bold transition',
                            link.active
                                ? 'border-violet-600 bg-violet-600 text-white shadow-sm'
                                : 'border-slate-300 bg-white text-slate-600 hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700',
                            !link.url
                                ? 'pointer-events-none opacity-40'
                                : '',
                        ].join(
                            ' '
                        )}
                        dangerouslySetInnerHTML={{
                            __html:
                                link.label,
                        }}
                    />
                )
            )}
        </nav>
    );
}
