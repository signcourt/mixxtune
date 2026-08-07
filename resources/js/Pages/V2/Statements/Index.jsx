import {
    Head,
    Link,
} from '@inertiajs/react';

import {
    CheckCircle2,
    Clock3,
    Download,
    FileText,
    WalletCards,
} from 'lucide-react';

import {
    useMemo,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusStyles = {
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

const formatCurrency = (
    value,
    currency = 'INR'
) => {
    try {
        return new Intl.NumberFormat(
            'en-IN',
            {
                style: 'currency',
                currency:
                    currency || 'INR',
                maximumFractionDigits: 2,
            }
        ).format(
            Number(value ?? 0)
        );
    } catch {
        return `${currency} ${Number(
            value ?? 0
        ).toFixed(2)}`;
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

const formatDate = (value) => {
    if (!value) {
        return '—';
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
    statements = {},
}) {
    const rows =
        statements.data ?? [];

    const [search, setSearch] =
        useState('');

    const [status, setStatus] =
        useState('');

    const filteredRows =
        useMemo(() => {
            const searchValue =
                search
                    .trim()
                    .toLowerCase();

            return rows.filter(
                (item) => {
                    const matchesStatus =
                        !status ||
                        item.status === status;

                    const matchesSearch =
                        !searchValue ||
                        String(
                            item.statement_month ??
                                ''
                        )
                            .toLowerCase()
                            .includes(
                                searchValue
                            ) ||
                        String(
                            item.public_id ??
                                ''
                        )
                            .toLowerCase()
                            .includes(
                                searchValue
                            ) ||
                        String(
                            item.currency ??
                                ''
                        )
                            .toLowerCase()
                            .includes(
                                searchValue
                            );

                    return (
                        matchesStatus &&
                        matchesSearch
                    );
                }
            );
        }, [
            rows,
            search,
            status,
        ]);

    const totals =
        useMemo(() => {
            return rows.reduce(
                (result, item) => {
                    result.gross +=
                        Number(
                            item.gross_earnings ??
                                0
                        );

                    result.deductions +=
                        Number(
                            item.commission_amount ??
                                0
                        ) +
                        Number(
                            item.tax_amount ??
                                0
                        ) +
                        Number(
                            item.other_deductions ??
                                0
                        );

                    result.net +=
                        Number(
                            item.net_payable ??
                                0
                        );

                    if (
                        item.status ===
                        'available'
                    ) {
                        result.available +=
                            Number(
                                item.net_payable ??
                                    0
                            );
                    }

                    return result;
                },
                {
                    gross: 0,
                    deductions: 0,
                    net: 0,
                    available: 0,
                }
            );
        }, [rows]);

    const title =
        role === 'artist'
            ? 'My Statements'
            : role === 'label'
              ? 'Label Statements'
              : 'Royalty Statements';

    return (
        <PanelLayout
            role={role}
            title={title}
            subtitle="Monthly royalty statements and downloadable payment records"
        >
            <Head title={title} />

            <div className="space-y-6">
                <header className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="grid gap-6 p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-violet-700">
                                    Mixx Tune Finance
                                </span>

                                <span className="text-xs font-medium text-slate-500">
                                    Statement Centre
                                </span>
                            </div>

                            <h1 className="mt-4 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
                                {title}
                            </h1>

                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                Review gross earnings,
                                deductions, net payable
                                amounts and statement
                                payment status.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <Link
                                href="/v2/royalties"
                                className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-violet-700"
                            >
                                View Royalties
                            </Link>

                            <Link
                                href="/v2/wallet"
                                className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                            >
                                Open Wallet
                            </Link>
                        </div>
                    </div>
                </header>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Gross Earnings"
                        value={formatCurrency(
                            totals.gross
                        )}
                        description="Before deductions"
                        icon={
                            <FileText
                                size={20}
                            />
                        }
                        tone="violet"
                    />

                    <SummaryCard
                        label="Total Deductions"
                        value={formatCurrency(
                            totals.deductions
                        )}
                        description="Commission, tax and adjustments"
                        icon={
                            <Clock3
                                size={20}
                            />
                        }
                        tone="amber"
                    />

                    <SummaryCard
                        label="Net Payable"
                        value={formatCurrency(
                            totals.net
                        )}
                        description="Total payable amount"
                        icon={
                            <CheckCircle2
                                size={20}
                            />
                        }
                        tone="blue"
                    />

                    <SummaryCard
                        label="Wallet Available"
                        value={formatCurrency(
                            totals.available
                        )}
                        description="Ready for withdrawal"
                        icon={
                            <WalletCards
                                size={20}
                            />
                        }
                        tone="emerald"
                    />
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_220px_auto]">
                        <input
                            type="search"
                            value={search}
                            onChange={(event) =>
                                setSearch(
                                    event.target.value
                                )
                            }
                            placeholder="Search month, currency or statement ID..."
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <select
                            value={status}
                            onChange={(event) =>
                                setStatus(
                                    event.target.value
                                )
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
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

                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                setStatus('');
                            }}
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                        >
                            Clear Filters
                        </button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="text-lg font-bold text-slate-950">
                            Statement History
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            {filteredRows.length}{' '}
                            statements displayed
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'Statement',
                                        'Gross',
                                        'Deductions',
                                        'Net Payable',
                                        'Currency',
                                        'Status',
                                        'Approved',
                                        'PDF',
                                    ].map(
                                        (heading) => (
                                            <th
                                                key={
                                                    heading
                                                }
                                                className="whitespace-nowrap px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                            >
                                                {
                                                    heading
                                                }
                                            </th>
                                        )
                                    )}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {filteredRows.length >
                                0 ? (
                                    filteredRows.map(
                                        (item) => {
                                            const deductions =
                                                Number(
                                                    item.commission_amount ??
                                                        0
                                                ) +
                                                Number(
                                                    item.tax_amount ??
                                                        0
                                                ) +
                                                Number(
                                                    item.other_deductions ??
                                                        0
                                                );

                                            return (
                                                <tr
                                                    key={
                                                        item.id
                                                    }
                                                    className="transition hover:bg-slate-50"
                                                >
                                                    <Cell>
                                                        <div>
                                                            <p className="font-bold text-slate-900">
                                                                {formatMonth(
                                                                    item.statement_month
                                                                )}
                                                            </p>

                                                            <p className="mt-1 text-xs text-slate-400">
                                                                {item.public_id ||
                                                                    `#${item.id}`}
                                                            </p>
                                                        </div>
                                                    </Cell>

                                                    <Cell>
                                                        {formatCurrency(
                                                            item.gross_earnings,
                                                            item.currency
                                                        )}
                                                    </Cell>

                                                    <Cell>
                                                        {formatCurrency(
                                                            deductions,
                                                            item.currency
                                                        )}
                                                    </Cell>

                                                    <Cell>
                                                        <span className="font-black text-slate-950">
                                                            {formatCurrency(
                                                                item.net_payable,
                                                                item.currency
                                                            )}
                                                        </span>
                                                    </Cell>

                                                    <Cell>
                                                        {item.currency ||
                                                            'INR'}
                                                    </Cell>

                                                    <Cell>
                                                        <StatusBadge
                                                            status={
                                                                item.status
                                                            }
                                                        />
                                                    </Cell>

                                                    <Cell>
                                                        {formatDate(
                                                            item.approved_at
                                                        )}
                                                    </Cell>

                                                    <Cell>
                                                        <a
                                                            href={`/v2/statements/${item.id}/download`}
                                                            className="inline-flex items-center gap-2 rounded-xl bg-violet-50 px-3 py-2 text-sm font-bold text-violet-700 transition hover:bg-violet-100"
                                                        >
                                                            <Download
                                                                size={
                                                                    16
                                                                }
                                                            />

                                                            PDF
                                                        </a>
                                                    </Cell>
                                                </tr>
                                            );
                                        }
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="8"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            No statements
                                            match the selected
                                            filters.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {statements.links && (
                    <div className="flex flex-wrap justify-center gap-2">
                        {statements.links.map(
                            (link, index) => (
                                <Link
                                    key={index}
                                    href={
                                        link.url ??
                                        '#'
                                    }
                                    preserveScroll
                                    preserveState
                                    className={[
                                        'rounded-xl border px-3 py-2 text-sm font-semibold',
                                        link.active
                                            ? 'border-violet-600 bg-violet-600 text-white'
                                            : 'border-slate-300 bg-white text-slate-700',
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
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}

function SummaryCard({
    label,
    value,
    description,
    icon,
    tone,
}) {
    const tones = {
        violet:
            'bg-violet-50 text-violet-700 ring-violet-100',

        amber:
            'bg-amber-50 text-amber-700 ring-amber-100',

        blue:
            'bg-blue-50 text-blue-700 ring-blue-100',

        emerald:
            'bg-emerald-50 text-emerald-700 ring-emerald-100',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-slate-500">
                        {label}
                    </p>

                    <p className="mt-2 text-2xl font-black tracking-tight text-slate-950">
                        {value}
                    </p>

                    <p className="mt-1 text-xs leading-5 text-slate-400">
                        {description}
                    </p>
                </div>

                <div
                    className={[
                        'flex h-11 w-11 items-center justify-center rounded-xl ring-1',
                        tones[tone] ??
                            tones.violet,
                    ].join(' ')}
                >
                    {icon}
                </div>
            </div>
        </div>
    );
}

function StatusBadge({
    status = 'pending',
}) {
    const normalized =
        String(status)
            .toLowerCase();

    return (
        <span
            className={[
                'inline-flex rounded-full px-3 py-1 text-xs font-bold capitalize ring-1 ring-inset',
                statusStyles[
                    normalized
                ] ??
                    'bg-slate-50 text-slate-700 ring-slate-200',
            ].join(' ')}
        >
            {normalized.replaceAll(
                '_',
                ' '
            )}
        </span>
    );
}

function Cell({
    children,
}) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
