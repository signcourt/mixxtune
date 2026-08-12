import {
    Head,
    Link,
    useForm,
    usePage,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const money = (amount) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(amount || 0));

const statusStyles = {
    pending:
        'bg-amber-50 text-amber-700 ring-amber-600/20',
    approved:
        'bg-blue-50 text-blue-700 ring-blue-600/20',
    processing:
        'bg-violet-50 text-violet-700 ring-violet-600/20',
    paid:
        'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    rejected:
        'bg-rose-50 text-rose-700 ring-rose-600/20',
};

export default function Index({
    role = 'artist',
    wallet = {},
    profile = null,
    withdrawals = {},
    minimumAmount = 0,
}) {
    const { flash = {} } = usePage().props;

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        amount: '',
        payment_method: 'bank',
        request_note: '',
    });

    const availableBalance = Number(
        wallet.available_balance ?? 0
    );

    const minimum = Number(
        minimumAmount ?? 0
    );

    const profileExists = Boolean(profile);

    const isVerified =
        profile?.kyc_status === 'verified';

    const hasBank =
        Boolean(profile?.masked_bank_account) &&
        Boolean(profile?.ifsc_code);

    const hasUpi = Boolean(profile?.upi_id);

    const selectedMethodReady =
        data.payment_method === 'bank'
            ? hasBank
            : hasUpi;

    const balanceReady =
        availableBalance >= minimum;

    const amountValue = Number(
        data.amount || 0
    );

    const amountReady =
        amountValue >= minimum &&
        amountValue <= availableBalance;

    const canWithdraw =
        profileExists &&
        isVerified &&
        selectedMethodReady &&
        balanceReady &&
        amountReady;

    const submit = (event) => {
        event.preventDefault();

        post('/v2/withdrawals', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const blockers = [];

    if (!profileExists) {
        blockers.push({
            title: 'Payout profile missing',
            detail:
                'Add bank, UPI, PAN and address details.',
            action: 'Add payout details',
        });
    } else if (!isVerified) {
        blockers.push({
            title: 'KYC verification pending',
            detail:
                'Admin verification is required before withdrawal.',
            action: 'Review KYC profile',
        });
    }

    if (!balanceReady) {
        blockers.push({
            title: 'Minimum balance not reached',
            detail:
                `${money(
                    minimum - availableBalance
                )} more is required.`,
            action: null,
        });
    }

    return (
        <PanelLayout
            role={role}
            title="Withdrawals"
            subtitle="Request and track royalty payouts"
        >
            <Head title="Withdrawals" />

            <div className="space-y-6">
                {flash.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                        {flash.success}
                    </div>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Available Balance"
                        value={money(
                            availableBalance
                        )}
                        note="Ready for withdrawal"
                    />

                    <SummaryCard
                        label="Pending Balance"
                        value={money(
                            wallet.pending_balance
                        )}
                        note="Reserved or processing"
                    />

                    <SummaryCard
                        label="Minimum Withdrawal"
                        value={money(minimum)}
                        note="Configured payout limit"
                    />

                    <SummaryCard
                        label="KYC Status"
                        value={
                            profile?.kyc_status ??
                            'Not submitted'
                        }
                        note={
                            isVerified
                                ? 'Withdrawal enabled'
                                : 'Verification required'
                        }
                        capitalize
                    />
                </section>

                <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h2 className="font-bold text-slate-900">
                            Withdrawal History
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Track payout requests and
                            payment references.
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-[850px] w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'Request',
                                        'Requested',
                                        'Amount',
                                        'Method',
                                        'Status',
                                        'Reference',
                                        'Document',
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
                                {(withdrawals.data ??
                                    []).map((item) => (
                                    <tr
                                        key={item.id}
                                        className="hover:bg-slate-50/60"
                                    >
                                        <Cell>
                                            <span className="font-semibold text-slate-900">
                                                {
                                                    item.request_number
                                                }
                                            </span>
                                        </Cell>

                                        <Cell>
                                            {formatDate(
                                                item.requested_at ??
                                                item.created_at
                                            )}
                                        </Cell>

                                        <Cell>
                                            <span className="font-bold text-slate-900">
                                                {money(
                                                    item.amount
                                                )}
                                            </span>
                                        </Cell>

                                        <Cell>
                                            <span className="capitalize">
                                                {
                                                    item.payment_method
                                                }
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
                                            {item.payment_reference ||
                                                '—'}
                                        </Cell>

                                        <Cell>
                                            {item.invoice ? (
                                                <div className="flex min-w-[190px] flex-col items-start gap-2">
                                                    <div>
                                                        <p className="font-semibold text-slate-900">
                                                            {
                                                                item.invoice
                                                                    .invoice_number
                                                            }
                                                        </p>

                                                        <p className="mt-0.5 text-xs text-slate-500">
                                                            {documentLabel(
                                                                item.invoice
                                                                    .invoice_type
                                                            )}
                                                        </p>
                                                    </div>

                                                    <a
                                                        href={`/v2/invoices/${item.invoice.id}/download`}
                                                        className="inline-flex items-center rounded-lg bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 transition hover:bg-violet-100"
                                                    >
                                                        Download PDF
                                                    </a>
                                                </div>
                                            ) : (
                                                <span className="text-slate-400">
                                                    —
                                                </span>
                                            )}
                                        </Cell>
                                    </tr>
                                ))}

                                {(withdrawals.data ?? [])
                                    .length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="px-6 py-14 text-center"
                                        >
                                            <p className="font-semibold text-slate-700">
                                                No withdrawal
                                                requests yet
                                            </p>

                                            <p className="mt-1 text-sm text-slate-500">
                                                Eligible
                                                requests will
                                                appear here.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}

function SummaryCard({
    label,
    value,
    note,
    capitalize = false,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </p>

            <p
                className={[
                    'mt-3 text-2xl font-bold text-slate-900',
                    capitalize
                        ? 'capitalize'
                        : '',
                ].join(' ')}
            >
                {value}
            </p>

            <p className="mt-2 text-xs text-slate-500">
                {note}
            </p>
        </div>
    );
}

function Detail({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl bg-white/5 p-4">
            <p className="text-xs font-semibold uppercase text-slate-400">
                {label}
            </p>

            <p className="mt-1 break-all text-sm font-semibold capitalize">
                {value}
            </p>
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

function StatusBadge({ status }) {
    const normalized =
        status ?? 'pending';

    return (
        <span
            className={[
                'inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ring-1 ring-inset',
                statusStyles[normalized] ??
                    statusStyles.pending,
            ].join(' ')}
        >
            {normalized.replaceAll('_', ' ')}
        </span>
    );
}

function documentLabel(type) {
    if (type === 'royalty_payment_statement') {
        return 'Royalty Payment Statement';
    }

    if (type === 'gst_tax_invoice') {
        return 'GST Tax Invoice';
    }

    if (type === 'tax_invoice') {
        return 'Tax Invoice';
    }

    return 'Financial Document';
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(
        'en-IN',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }
    );
}
