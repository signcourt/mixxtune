import {
    Head,
    router,
} from '@inertiajs/react';

import {
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    withdrawals = {},
    counts = {},
    filters = {},
}) {
    const [note, setNote] =
        useState('');

    const [reference, setReference] =
        useState('');

    const [activeId, setActiveId] =
        useState(null);

    const [processingId, setProcessingId] =
        useState(null);

    const [actionError, setActionError] =
        useState('');

    const rows =
        withdrawals.data ?? [];

    const currentStatus =
        filters.status ?? 'pending';

    const currentMonth =
        filters.month ?? '';

    const goToFilters = (
        status,
        month = currentMonth
    ) => {
        router.get(
            '/v2/admin/withdrawals',
            {
                status,
                month,
            },
            {
                preserveState: false,
                preserveScroll: true,
            }
        );
    };

    const postAction = (
        endpoint,
        payload,
        itemId
    ) => {
        if (processingId !== null) {
            return;
        }

        setActionError('');
        setProcessingId(itemId);

        router.post(
            endpoint,
            payload,
            {
                preserveScroll: true,
                preserveState: false,

                onSuccess: () => {
                    setNote('');
                    setReference('');
                    setActiveId(null);
                },

                onError: (errors) => {
                    const message =
                        Object.values(
                            errors ?? {}
                        )[0] ??
                        'Request failed.';

                    setActionError(
                        String(message)
                    );
                },

                onFinish: () => {
                    setProcessingId(null);
                },
            }
        );
    };

    const deleteWithdrawal = (
        item
    ) => {
        if (
            processingId !== null
        ) {
            return;
        }

        const approved =
            window.confirm(
                `Delete withdrawal ${item.request_number}?\n\nThe reserved amount will be returned to the wallet.`
            );

        if (!approved) {
            return;
        }

        setActionError('');
        setProcessingId(item.id);

        router.delete(
            `/v2/admin/withdrawals/${item.id}`,
            {
                preserveScroll: true,
                preserveState: false,

                onError: (errors) => {
                    const message =
                        Object.values(
                            errors ?? {}
                        )[0] ??
                        'Delete failed.';

                    setActionError(
                        String(message)
                    );
                },

                onFinish: () => {
                    setProcessingId(null);
                    setActiveId(null);
                },
            }
        );
    };

    const exportUrl =
        `/v2/admin/withdrawals/export?status=${encodeURIComponent(
            currentStatus
        )}&month=${encodeURIComponent(
            currentMonth
        )}`;

    return (
        <PanelLayout
            role={role}
            title="Withdrawal Requests"
            subtitle="Review, filter and export payout requests"
        >
            <Head title="Withdrawal Requests" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-4">
                    {[
                        ['pending', 'Pending'],
                        ['approved', 'Approved'],
                        ['paid', 'Paid'],
                        ['rejected', 'Rejected'],
                    ].map(([status, label]) => (
                        <button
                            key={status}
                            type="button"
                            onClick={() =>
                                goToFilters(
                                    status
                                )
                            }
                            className={[
                                'rounded-2xl border bg-white p-5 text-left shadow-sm',
                                currentStatus ===
                                status
                                    ? 'border-violet-500 ring-2 ring-violet-100'
                                    : 'border-slate-200',
                            ].join(' ')}
                        >
                            <div className="text-sm text-slate-500">
                                {label}
                            </div>

                            <div className="mt-2 text-3xl font-bold text-slate-900">
                                {counts[status] ??
                                    0}
                            </div>
                        </button>
                    ))}
                </div>

                <div className="flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div>
                        <label className="mb-1 block text-xs font-semibold uppercase text-slate-500">
                            Month
                        </label>

                        <div className="flex flex-wrap gap-2">
                            <input
                                type="month"
                                value={
                                    currentMonth
                                }
                                onChange={(
                                    event
                                ) =>
                                    goToFilters(
                                        currentStatus,
                                        event.target
                                            .value
                                    )
                                }
                                className="rounded-xl border border-slate-300 px-4 py-2 text-sm"
                            />

                            {currentMonth && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        goToFilters(
                                            currentStatus,
                                            ''
                                        )
                                    }
                                    className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold"
                                >
                                    All Months
                                </button>
                            )}
                        </div>
                    </div>

                    <a
                        href={exportUrl}
                        className="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white"
                    >
                        Download Finance Excel
                    </a>
                </div>

                <div className="space-y-4">
                    {rows.length === 0 && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                            No withdrawal requests found for this filter.
                        </div>
                    )}

                    {rows.map((item) => (
                        <section
                            key={item.id}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div className="grid gap-5 xl:grid-cols-[1fr_320px]">
                                <div>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h2 className="font-semibold text-slate-900">
                                                {
                                                    item.request_number
                                                }
                                            </h2>

                                            <p className="mt-1 text-sm text-slate-500">
                                                {
                                                    item.user
                                                        ?.name
                                                }{' '}
                                                •{' '}
                                                {
                                                    item.user
                                                        ?.email
                                                }
                                            </p>
                                        </div>

                                        <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold capitalize text-violet-700">
                                            {
                                                item.status
                                            }
                                        </span>
                                    </div>

                                    <div className="mt-5 grid gap-3 sm:grid-cols-4">
                                        <Info
                                            label="Amount"
                                            value={`₹${item.amount}`}
                                        />

                                        <Info
                                            label="Method"
                                            value={
                                                item.payment_method
                                            }
                                        />

                                        <Info
                                            label="KYC"
                                            value={
                                                item.payout_profile
                                                    ?.kyc_status ??
                                                'Unknown'
                                            }
                                        />

                                        <Info
                                            label="Account"
                                            value={
                                                item.payout_profile
                                                    ?.masked_bank_account ??
                                                item.payout_profile
                                                    ?.upi_id ??
                                                '—'
                                            }
                                        />
                                    </div>
                                </div>

                                <div>
                                    {activeId ===
                                    item.id ? (
                                        <div className="space-y-3">
                                            <textarea
                                                value={
                                                    note
                                                }
                                                onChange={(
                                                    event
                                                ) =>
                                                    setNote(
                                                        event
                                                            .target
                                                            .value
                                                    )
                                                }
                                                placeholder="Manager note or rejection reason"
                                                className="min-h-24 w-full rounded-xl border border-slate-300 p-3 text-sm"
                                            />

                                            {item.status ===
                                                'approved' && (
                                                <input
                                                    value={
                                                        reference
                                                    }
                                                    onChange={(
                                                        event
                                                    ) =>
                                                        setReference(
                                                            event
                                                                .target
                                                                .value
                                                        )
                                                    }
                                                    placeholder="Payment reference / UTR"
                                                    className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                                                />
                                            )}

                                            {actionError && (
                                                <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
                                                    {
                                                        actionError
                                                    }
                                                </div>
                                            )}

                                            <div className="flex flex-wrap gap-2">
                                                {item.status ===
                                                    'pending' && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                processingId ===
                                                                item.id
                                                            }
                                                            onClick={() =>
                                                                postAction(
                                                                    `/v2/admin/withdrawals/${item.id}/approve`,
                                                                    {
                                                                        admin_note:
                                                                            note,
                                                                    },
                                                                    item.id
                                                                )
                                                            }
                                                            className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                                        >
                                                            Approve
                                                        </button>

                                                        <button
                                                            type="button"
                                                            disabled={
                                                                processingId ===
                                                                    item.id ||
                                                                note.trim()
                                                                    .length <
                                                                    3
                                                            }
                                                            onClick={() =>
                                                                postAction(
                                                                    `/v2/admin/withdrawals/${item.id}/reject`,
                                                                    {
                                                                        rejection_reason:
                                                                            note,
                                                                    },
                                                                    item.id
                                                                )
                                                            }
                                                            className="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                                        >
                                                            Reject
                                                        </button>
                                                    </>
                                                )}

                                                {item.status ===
                                                    'approved' && (
                                                    <button
                                                        type="button"
                                                        disabled={
                                                            processingId ===
                                                                item.id ||
                                                            reference
                                                                .trim()
                                                                .length <
                                                                3
                                                        }
                                                        onClick={() =>
                                                            postAction(
                                                                `/v2/admin/withdrawals/${item.id}/paid`,
                                                                {
                                                                    payment_reference:
                                                                        reference,
                                                                    admin_note:
                                                                        note,
                                                                },
                                                                item.id
                                                            )
                                                        }
                                                        className="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                                    >
                                                        Mark Paid
                                                    </button>
                                                )}

                                                {[
                                                    'pending',
                                                    'approved',
                                                ].includes(
                                                    item.status
                                                ) && (
                                                    <button
                                                        type="button"
                                                        disabled={
                                                            processingId ===
                                                            item.id
                                                        }
                                                        onClick={() =>
                                                            deleteWithdrawal(
                                                                item
                                                            )
                                                        }
                                                        className="rounded-lg bg-red-700 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                                    >
                                                        Delete
                                                    </button>
                                                )}

                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setActiveId(
                                                            null
                                                        )
                                                    }
                                                    className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setActionError(
                                                    ''
                                                );

                                                setActiveId(
                                                    item.id
                                                );
                                            }}
                                            className="w-full rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white"
                                        >
                                            Process Request
                                        </button>
                                    )}
                                </div>
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </PanelLayout>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-3">
            <div className="text-xs font-semibold uppercase text-slate-500">
                {label}
            </div>

            <div className="mt-1 break-all text-sm font-semibold capitalize text-slate-900">
                {value}
            </div>
        </div>
    );
}
