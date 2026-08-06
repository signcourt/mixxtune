import {
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/react';

import { useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusStyles = {
    pending:
        'bg-amber-50 text-amber-700 ring-amber-600/20',
    generated:
        'bg-slate-100 text-slate-700 ring-slate-600/20',
    approved:
        'bg-blue-50 text-blue-700 ring-blue-600/20',
    available:
        'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    paid:
        'bg-violet-50 text-violet-700 ring-violet-600/20',
};

const money = (amount, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(amount || 0));

const monthName = (value) => {
    if (!value) {
        return '—';
    }

    const [year, month] = value.split('-');

    return new Intl.DateTimeFormat('en-IN', {
        month: 'short',
        year: 'numeric',
    }).format(
        new Date(
            Number(year),
            Number(month) - 1,
            1
        )
    );
};

export default function Index({
    role = 'super_admin',
    summary = {},
    statements = {},
    filters = {},
    months = [],
}) {
    const { flash = {}, errors = {} } =
        usePage().props;

    const [search, setSearch] = useState(
        filters.search ?? ''
    );

    const [month, setMonth] = useState(
        filters.month ?? ''
    );

    const [status, setStatus] = useState(
        filters.status ?? ''
    );

    const [actionId, setActionId] =
        useState(null);

    const {
        data,
        setData,
        post,
        processing,
        reset,
    } = useForm({
        month: '',
        commission_percent: 0,
    });

    const generate = (event) => {
        event.preventDefault();

        post(
            '/v2/admin/finance/statements/generate',
            {
                preserveScroll: true,
                onSuccess: () => reset(),
            }
        );
    };

    const applyFilters = (event) => {
        event.preventDefault();

        router.get(
            '/v2/admin/finance',
            {
                search,
                month,
                status,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const clearFilters = () => {
        setSearch('');
        setMonth('');
        setStatus('');

        router.get(
            '/v2/admin/finance',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const run = (
        endpoint,
        statementId
    ) => {
        setActionId(statementId);

        router.post(
            endpoint,
            {},
            {
                preserveScroll: true,
                onFinish: () =>
                    setActionId(null),
            }
        );
    };

    const cards = [
        {
            label: 'Gross Royalties',
            value: summary.gross,
            note: 'Total generated earnings',
        },
        {
            label: 'Pending Approval',
            value: summary.pending,
            note: 'Awaiting finance approval',
        },
        {
            label: 'Approved',
            value: summary.approved,
            note: 'Held in pending wallet',
        },
        {
            label: 'Available',
            value: summary.available,
            note: 'Ready for withdrawal',
        },
        {
            label: 'Paid',
            value: summary.paid,
            note: 'Completed payouts',
        },
    ];

    return (
        <PanelLayout
            role={role}
            title="Finance & Royalties"
            subtitle="Generate, approve and release royalty statements"
        >
            <Head title="Finance & Royalties" />

            <div className="space-y-6">
                {flash.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                        {flash.success}
                    </div>
                )}

                {Object.keys(errors).length >
                    0 && (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
                        {Object.values(errors).map(
                            (message) => (
                                <div key={message}>
                                    {message}
                                </div>
                            )
                        )}
                    </div>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {cards.map((card) => (
                        <div
                            key={card.label}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {card.label}
                            </p>

                            <p className="mt-3 text-2xl font-bold text-slate-900">
                                {money(card.value)}
                            </p>

                            <p className="mt-2 text-xs text-slate-500">
                                {card.note}
                            </p>
                        </div>
                    ))}
                </section>

                {role === 'super_admin' && (
                    <form
                        onSubmit={generate}
                        className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div className="mb-4">
                            <h2 className="text-base font-bold text-slate-900">
                                Generate Statements
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Create or rebuild monthly
                                royalty statements.
                            </p>
                        </div>

                        <div className="grid gap-3 md:grid-cols-[190px_190px_auto]">
                            <input
                                type="month"
                                value={data.month}
                                onChange={(event) =>
                                    setData(
                                        'month',
                                        event.target.value
                                    )
                                }
                                className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                                required
                            />

                            <input
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                value={
                                    data.commission_percent
                                }
                                onChange={(event) =>
                                    setData(
                                        'commission_percent',
                                        event.target.value
                                    )
                                }
                                placeholder="Commission %"
                                className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />

                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {processing
                                    ? 'Generating...'
                                    : 'Generate Statements'}
                            </button>
                        </div>
                    </form>
                )}

                <form
                    onSubmit={applyFilters}
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="grid gap-3 lg:grid-cols-[minmax(240px,1fr)_180px_180px_auto_auto]">
                        <input
                            type="search"
                            value={search}
                            onChange={(event) =>
                                setSearch(
                                    event.target.value
                                )
                            }
                            placeholder="Search artist, label or statement ID"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <select
                            value={month}
                            onChange={(event) =>
                                setMonth(
                                    event.target.value
                                )
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">
                                All months
                            </option>

                            {months.map((item) => (
                                <option
                                    key={item}
                                    value={item}
                                >
                                    {monthName(item)}
                                </option>
                            ))}
                        </select>

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
                                All statuses
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
                        </select>

                        <button
                            type="submit"
                            className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                        >
                            Apply
                        </button>

                        <button
                            type="button"
                            onClick={clearFilters}
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Clear
                        </button>
                    </div>
                </form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="font-bold text-slate-900">
                            Royalty Statements
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            {statements.total ?? 0}{' '}
                            statement(s)
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-[1180px] w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'Month',
                                        'Owner',
                                        'Gross',
                                        'Commission',
                                        'Tax',
                                        'Net Payable',
                                        'Status',
                                        'Dates',
                                        'Actions',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {(statements.data ??
                                    []).map((item) => (
                                    <tr
                                        key={item.id}
                                        className="align-top hover:bg-slate-50/60"
                                    >
                                        <Cell>
                                            <div className="font-semibold text-slate-900">
                                                {monthName(
                                                    item.statement_month
                                                )}
                                            </div>

                                            <div className="mt-1 text-xs text-slate-400">
                                                #{item.public_id}
                                            </div>
                                        </Cell>

                                        <Cell>
                                            <div className="font-semibold text-slate-900">
                                                {item.artist_name}
                                            </div>

                                            <div className="mt-1 text-xs text-slate-500">
                                                {item.label_name}
                                            </div>
                                        </Cell>

                                        <Cell>
                                            {money(
                                                item.gross_earnings,
                                                item.currency
                                            )}
                                        </Cell>

                                        <Cell>
                                            {money(
                                                item.commission_amount,
                                                item.currency
                                            )}
                                        </Cell>

                                        <Cell>
                                            {money(
                                                item.tax_amount,
                                                item.currency
                                            )}
                                        </Cell>

                                        <Cell>
                                            <span className="font-bold text-slate-900">
                                                {money(
                                                    item.net_payable,
                                                    item.currency
                                                )}
                                            </span>
                                        </Cell>

                                        <Cell>
                                            <StatusBadge
                                                status={
                                                    item.status
                                                }
                                            />
                                        </Cell>

                                        <Cell>
                                            <DateRow
                                                label="Approved"
                                                value={
                                                    item.approved_at
                                                }
                                            />

                                            <DateRow
                                                label="Available"
                                                value={
                                                    item.available_at
                                                }
                                            />

                                            <DateRow
                                                label="Paid"
                                                value={
                                                    item.paid_at
                                                }
                                            />
                                        </Cell>

                                        <Cell>
                                            <div className="flex flex-wrap gap-2">
                                                <a
                                                    href={`/v2/statements/${item.id}/download`}
                                                    className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                                >
                                                    PDF
                                                </a>

                                                {role ===
                                                    'super_admin' &&
                                                    item.status ===
                                                        'pending' && (
                                                        <ActionButton
                                                            busy={
                                                                actionId ===
                                                                item.id
                                                            }
                                                            onClick={() =>
                                                                run(
                                                                    `/v2/admin/finance/statements/${item.id}/approve`,
                                                                    item.id
                                                                )
                                                            }
                                                            className="bg-emerald-600 hover:bg-emerald-700"
                                                        >
                                                            Approve
                                                        </ActionButton>
                                                    )}

                                                {role ===
                                                    'super_admin' &&
                                                    item.status ===
                                                        'approved' && (
                                                        <ActionButton
                                                            busy={
                                                                actionId ===
                                                                item.id
                                                            }
                                                            onClick={() =>
                                                                run(
                                                                    `/v2/admin/finance/statements/${item.id}/available`,
                                                                    item.id
                                                                )
                                                            }
                                                            className="bg-violet-600 hover:bg-violet-700"
                                                        >
                                                            Make Available
                                                        </ActionButton>
                                                    )}

                                                {![
                                                    'pending',
                                                    'approved',
                                                ].includes(
                                                    item.status
                                                ) && (
                                                    <span className="px-2 py-2 text-xs text-slate-400">
                                                        No action required
                                                    </span>
                                                )}
                                            </div>
                                        </Cell>
                                    </tr>
                                ))}

                                {(statements.data ?? [])
                                    .length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="9"
                                            className="px-6 py-16 text-center"
                                        >
                                            <p className="font-semibold text-slate-700">
                                                No royalty statements found
                                            </p>

                                            <p className="mt-1 text-sm text-slate-500">
                                                Change the filters or
                                                generate statements for a
                                                reporting month.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {(statements.links ?? []).length >
                        3 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
                            <p className="text-sm text-slate-500">
                                Showing{' '}
                                {statements.from ?? 0}–
                                {statements.to ?? 0} of{' '}
                                {statements.total ?? 0}
                            </p>

                            <div className="flex flex-wrap gap-2">
                                {statements.links.map(
                                    (link, index) => (
                                        <Link
                                            key={`${link.label}-${index}`}
                                            href={
                                                link.url ??
                                                '#'
                                            }
                                            preserveScroll
                                            className={[
                                                'rounded-lg border px-3 py-2 text-xs font-semibold',
                                                link.active
                                                    ? 'border-violet-600 bg-violet-600 text-white'
                                                    : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50',
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
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}

function StatusBadge({ status }) {
    const normalized = status || 'pending';

    return (
        <span
            className={[
                'inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ring-1 ring-inset',
                statusStyles[normalized] ??
                    statusStyles.generated,
            ].join(' ')}
        >
            {normalized.replaceAll('_', ' ')}
        </span>
    );
}

function DateRow({ label, value }) {
    return (
        <div className="whitespace-nowrap text-xs text-slate-500">
            <span className="font-medium">
                {label}:
            </span>{' '}
            {value
                ? new Date(value).toLocaleDateString(
                      'en-IN'
                  )
                : '—'}
        </div>
    );
}

function ActionButton({
    children,
    busy,
    onClick,
    className,
}) {
    return (
        <button
            type="button"
            disabled={busy}
            onClick={onClick}
            className={[
                'rounded-lg px-3 py-2 text-xs font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-50',
                className,
            ].join(' ')}
        >
            {busy ? 'Processing...' : children}
        </button>
    );
}
