import {
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/react';

import {
    BadgeIndianRupee,
    FileText,
    Download,
    History,
    Plus,
    ReceiptIndianRupee,
    Search,
    TrendingDown,
    Upload,
    WalletCards,
    X,
} from 'lucide-react';

import { useMemo, useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const money = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

const percentage = (value) =>
    `${Number(value || 0).toFixed(2)}%`;

const dateLabel = (value) => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-IN',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }
    ).format(new Date(value));
};

const statusClass = {
    active:
        'bg-amber-50 text-amber-700 ring-amber-600/20',
    completed:
        'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    cancelled:
        'bg-slate-100 text-slate-700 ring-slate-600/20',
};

export default function Index({
    plans = {},
    summary = {},
    filters = {},
    accounts = [],
}) {
    const { flash = {} } = usePage().props;

    const [search, setSearch] = useState(
        filters.search ?? ''
    );

    const [status, setStatus] = useState(
        filters.status ?? ''
    );

    const [createOpen, setCreateOpen] =
        useState(false);

    const [expensePlan, setExpensePlan] =
        useState(null);

    const [detailsPlan, setDetailsPlan] =
        useState(null);

    const [detailsLoadingId, setDetailsLoadingId] =
        useState(null);

    const openDetails = async (plan) => {
        setDetailsLoadingId(plan.id);

        try {
            const response = await fetch(
                `/super-admin/finance/recoupment/${plan.id}`,
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With':
                            'XMLHttpRequest',
                    },
                }
            );

            if (!response.ok) {
                throw new Error(
                    `Unable to load details (${response.status}).`
                );
            }

            const payload =
                await response.json();

            setDetailsPlan(
                payload.data ?? null
            );
        } catch (error) {
            window.alert(
                error?.message ??
                    'Unable to load recoupment details.'
            );
        } finally {
            setDetailsLoadingId(null);
        }
    };

    const applyFilters = (event) => {
        event.preventDefault();

        router.get(
            '/super-admin/finance/recoupment',
            {
                search: search || undefined,
                status: status || undefined,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('');

        router.get(
            '/super-admin/finance/recoupment',
            {},
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    return (
        <PanelLayout>
            <Head title="Advances & Recoupment" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p className="text-sm font-semibold uppercase tracking-[0.18em] text-violet-600">
                            Finance V3
                        </p>

                        <h1 className="mt-1 text-2xl font-bold text-slate-900">
                            Advances & Recoupment
                        </h1>

                        <p className="mt-1 max-w-3xl text-sm text-slate-500">
                            Manage advances, recoverable promotion costs,
                            recovery percentages, supporting documents and
                            recoupment history.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => setCreateOpen(true)}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700"
                    >
                        <Plus className="h-4 w-4" />
                        Add Advance
                    </button>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Total Advances / Costs"
                        value={money(
                            summary.total_recoverable
                        )}
                        icon={ReceiptIndianRupee}
                    />

                    <SummaryCard
                        label="Total Recovered"
                        value={money(
                            summary.total_recovered
                        )}
                        icon={BadgeIndianRupee}
                    />

                    <SummaryCard
                        label="Outstanding"
                        value={money(
                            summary.outstanding
                        )}
                        icon={TrendingDown}
                    />

                    <SummaryCard
                        label="Active Plans"
                        value={Number(
                            summary.active_plans || 0
                        ).toLocaleString('en-IN')}
                        icon={WalletCards}
                    />
                </div>

                <form
                    onSubmit={applyFilters}
                    className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:flex-row"
                >
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />

                        <input
                            value={search}
                            onChange={(event) =>
                                setSearch(
                                    event.target.value
                                )
                            }
                            placeholder="Search plan, label, artist or email..."
                            className="w-full rounded-xl border-slate-300 pl-10 text-sm focus:border-violet-500 focus:ring-violet-500"
                        />
                    </div>

                    <select
                        value={status}
                        onChange={(event) =>
                            setStatus(
                                event.target.value
                            )
                        }
                        className="rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    >
                        <option value="">
                            All Statuses
                        </option>
                        <option value="active">
                            Active
                        </option>
                        <option value="completed">
                            Recovered
                        </option>
                        <option value="cancelled">
                            Cancelled
                        </option>
                    </select>

                    <button
                        type="submit"
                        className="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Apply
                    </button>

                    {(search || status) && (
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="inline-flex items-center justify-center gap-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            <X className="h-4 w-4" />
                            Clear
                        </button>
                    )}
                </form>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th className="px-5 py-4">
                                        Account
                                    </th>
                                    <th className="px-5 py-4">
                                        Plan
                                    </th>
                                    <th className="px-5 py-4">
                                        Recovery Rate
                                    </th>
                                    <th className="px-5 py-4">
                                        Recoverable
                                    </th>
                                    <th className="px-5 py-4">
                                        Recovered
                                    </th>
                                    <th className="px-5 py-4">
                                        Outstanding
                                    </th>
                                    <th className="px-5 py-4">
                                        Status
                                    </th>
                                    <th className="px-5 py-4 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {(plans.data ?? []).length ===
                                0 ? (
                                    <tr>
                                        <td
                                            colSpan="8"
                                            className="px-6 py-16 text-center"
                                        >
                                            <WalletCards className="mx-auto h-10 w-10 text-slate-300" />

                                            <div className="mt-3 font-semibold text-slate-700">
                                                No recoupment plans found
                                            </div>

                                            <div className="mt-1 text-sm text-slate-500">
                                                Add an advance or recoverable
                                                expense to begin tracking.
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    (plans.data ?? []).map(
                                        (plan) => {
                                            const accountName =
                                                plan.label?.name ??
                                                plan.artist
                                                    ?.stage_name ??
                                                plan.artist
                                                    ?.legal_name ??
                                                plan.user?.name ??
                                                'Unknown';

                                            const baseShare =
                                                Number(
                                                    plan.base_percentage ||
                                                        0
                                                );

                                            const rawRecoveryUplift =
                                                Number(
                                                    plan.recovery_uplift_percentage ||
                                                        0
                                                );

                                            const maximumRecovery =
                                                plan.maximum_recovery_percentage ===
                                                null
                                                    ? null
                                                    : Number(
                                                          plan.maximum_recovery_percentage
                                                      );

                                            const recoveryUplift =
                                                Math.min(
                                                    baseShare,
                                                    rawRecoveryUplift,
                                                    maximumRecovery ===
                                                        null
                                                        ? rawRecoveryUplift
                                                        : maximumRecovery
                                                );

                                            const effectiveShare =
                                                plan.status ===
                                                'active' &&
                                                Number(
                                                    plan.outstanding_amount ||
                                                        0
                                                ) > 0
                                                    ? Math.max(
                                                          0,
                                                          baseShare -
                                                              recoveryUplift
                                                      )
                                                    : baseShare;

                                            return (
                                                <tr
                                                    key={
                                                        plan.id
                                                    }
                                                    className="align-top hover:bg-slate-50/70"
                                                >
                                                    <td className="px-5 py-4">
                                                        <div className="font-semibold text-slate-900">
                                                            {
                                                                accountName
                                                            }
                                                        </div>

                                                        <div className="mt-1 text-xs text-slate-500">
                                                            {plan.user
                                                                ?.email ??
                                                                '—'}
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <div className="font-medium text-slate-800">
                                                            {plan.title ??
                                                                plan.plan_number}
                                                        </div>

                                                        <div className="mt-1 text-xs text-slate-500">
                                                            {
                                                                plan.plan_number
                                                            }
                                                            {' · '}
                                                            {dateLabel(
                                                                plan.starts_on
                                                            )}
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <div className="text-sm font-semibold text-amber-700">
                                                            Recovery{' '}
                                                            {percentage(
                                                                recoveryUplift
                                                            )}
                                                        </div>

                                                        <div className="mt-1 text-xs text-slate-500">
                                                            Base share{' '}
                                                            {percentage(
                                                                baseShare
                                                            )}
                                                        </div>

                                                        <div className="mt-1 text-xs font-medium text-violet-700">
                                                            Effective share{' '}
                                                            {percentage(
                                                                effectiveShare
                                                            )}
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-4 font-medium text-slate-800">
                                                        {money(
                                                            plan.total_recoverable_amount
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-4 font-medium text-emerald-700">
                                                        {money(
                                                            plan.total_recovered_amount
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-4 font-semibold text-amber-700">
                                                        {money(
                                                            plan.outstanding_amount
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <span
                                                            className={[
                                                                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1 ring-inset',
                                                                statusClass[
                                                                    plan
                                                                        .status
                                                                ] ??
                                                                    statusClass.cancelled,
                                                            ].join(
                                                                ' '
                                                            )}
                                                        >
                                                            {
                                                                plan.status
                                                            }
                                                        </span>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <div className="flex justify-end gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setExpensePlan(
                                                                        plan
                                                                    )
                                                                }
                                                                className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                            >
                                                                <Plus className="h-3.5 w-3.5" />
                                                                Expense
                                                            </button>

                                                            <button
                                                                type="button"
                                                                disabled={
                                                                    detailsLoadingId ===
                                                                    plan.id
                                                                }
                                                                onClick={() =>
                                                                    openDetails(
                                                                        plan
                                                                    )
                                                                }
                                                                title="Documents"
                                                                className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-200 disabled:opacity-50"
                                                            >
                                                                <FileText className="h-3.5 w-3.5" />
                                                                {plan.documents_count ??
                                                                    0}
                                                            </button>

                                                            <button
                                                                type="button"
                                                                disabled={
                                                                    detailsLoadingId ===
                                                                    plan.id
                                                                }
                                                                onClick={() =>
                                                                    openDetails(
                                                                        plan
                                                                    )
                                                                }
                                                                title="Recovery History"
                                                                className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-2 text-xs font-medium text-slate-600 hover:bg-slate-200 disabled:opacity-50"
                                                            >
                                                                <History className="h-3.5 w-3.5" />
                                                                {plan.recoveries_count ??
                                                                    0}
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        }
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        links={plans.links ?? []}
                    />
                </div>
            </div>

            {createOpen && (
                <CreatePlanModal
                    accounts={accounts}
                    onClose={() =>
                        setCreateOpen(false)
                    }
                />
            )}

            {expensePlan && (
                <ExpenseModal
                    plan={expensePlan}
                    onClose={() =>
                        setExpensePlan(null)
                    }
                />
            )}


            {detailsPlan && (
                <DetailsModal
                    plan={detailsPlan}
                    onClose={() =>
                        setDetailsPlan(null)
                    }
                    onRefresh={() =>
                        openDetails(
                            detailsPlan
                        )
                    }
                />
            )}
        </PanelLayout>
    );
}

function SummaryCard({
    label,
    value,
    icon: Icon,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <div className="text-sm font-medium text-slate-500">
                        {label}
                    </div>

                    <div className="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                        {value}
                    </div>
                </div>

                <div className="rounded-xl bg-violet-50 p-3 text-violet-600">
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </div>
    );
}

function CreatePlanModal({
    accounts,
    onClose,
}) {
    const form = useForm({
        user_id: '',
        title: '',
        base_percentage: '',
        recovery_uplift_percentage: '',
        maximum_recovery_percentage: '',
        starts_on: new Date()
            .toISOString()
            .slice(0, 10),
        notes: '',
        initial_advance_amount: '',
    });

    const selectedAccount = useMemo(
        () =>
            accounts.find(
                (account) =>
                    String(account.id) ===
                    String(form.data.user_id)
            ),
        [accounts, form.data.user_id]
    );

    const submit = (event) => {
        event.preventDefault();

        form.post(
            '/super-admin/finance/recoupment',
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
            }
        );
    };

    return (
        <Modal
            title="Add Advance / Recovery Plan"
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <Field label="Account">
                    <select
                        required
                        value={form.data.user_id}
                        onChange={(event) =>
                            form.setData(
                                'user_id',
                                event.target.value
                            )
                        }
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    >
                        <option value="">
                            Select label or artist
                        </option>

                        {accounts.map((account) => (
                            <option
                                key={account.id}
                                value={account.id}
                            >
                                {account.name} ·{' '}
                                {account.role} ·{' '}
                                {account.email}
                            </option>
                        ))}
                    </select>
                </Field>

                {selectedAccount && (
                    <div className="rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                        Selected:{' '}
                        <strong>
                            {selectedAccount.name}
                        </strong>
                    </div>
                )}

                <Field label="Plan Title">
                    <input
                        required
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData(
                                'title',
                                event.target.value
                            )
                        }
                        placeholder="e.g. Artist Advance 2026"
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                </Field>

                <Field label="Initial Advance Amount">
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={
                            form.data
                                .initial_advance_amount
                        }
                        onChange={(event) =>
                            form.setData(
                                'initial_advance_amount',
                                event.target.value
                            )
                        }
                        placeholder="100000"
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                </Field>

                <div className="grid gap-4 md:grid-cols-3">
                    <Field label="Base Share %">
                        <input
                            required
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={
                                form.data
                                    .base_percentage
                            }
                            onChange={(event) =>
                                form.setData(
                                    'base_percentage',
                                    event.target.value
                                )
                            }
                            className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                        />
                    </Field>

                    <Field label="Recovery Uplift %">
                        <input
                            required
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={
                                form.data
                                    .recovery_uplift_percentage
                            }
                            onChange={(event) =>
                                form.setData(
                                    'recovery_uplift_percentage',
                                    event.target.value
                                )
                            }
                            className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                        />
                    </Field>

                    <Field label="Maximum Recovery %">
                        <input
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={
                                form.data
                                    .maximum_recovery_percentage
                            }
                            onChange={(event) =>
                                form.setData(
                                    'maximum_recovery_percentage',
                                    event.target.value
                                )
                            }
                            className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                        />
                    </Field>
                </div>

                <Field label="Start Date">
                    <input
                        type="date"
                        required
                        value={form.data.starts_on}
                        onChange={(event) =>
                            form.setData(
                                'starts_on',
                                event.target.value
                            )
                        }
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                </Field>

                <Field label="Internal Notes">
                    <textarea
                        rows="3"
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData(
                                'notes',
                                event.target.value
                            )
                        }
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                </Field>

                <Errors errors={form.errors} />

                <ModalActions
                    processing={form.processing}
                    onClose={onClose}
                    submitLabel="Create Plan"
                />
            </form>
        </Modal>
    );
}

function ExpenseModal({
    plan,
    onClose,
}) {
    const form = useForm({
        category: 'advance',
        title: '',
        amount: '',
        is_recoverable: true,
        expense_date: new Date()
            .toISOString()
            .slice(0, 10),
        reference_number: '',
        description: '',
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            `/super-admin/finance/recoupment/${plan.id}/expenses`,
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
            }
        );
    };

    return (
        <Modal
            title={`Add Expense · ${plan.plan_number}`}
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <Field label="Category">
                    <select
                        value={form.data.category}
                        onChange={(event) =>
                            form.setData(
                                'category',
                                event.target.value
                            )
                        }
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    >
                        <option value="advance">
                            Advance
                        </option>
                        <option value="youtube_promotion">
                            YouTube Promotion
                        </option>
                        <option value="meta_ads">
                            Meta / Instagram Ads
                        </option>
                        <option value="google_ads">
                            Google Ads
                        </option>
                        <option value="music_video_promotion">
                            Music Video Promotion
                        </option>
                        <option value="influencer_promotion">
                            Influencer Promotion
                        </option>
                        <option value="pr_media">
                            PR / Media
                        </option>
                        <option value="artwork_production">
                            Artwork / Production
                        </option>
                        <option value="marketing">
                            Marketing
                        </option>
                        <option value="distribution_expense">
                            Distribution Expense
                        </option>
                        <option value="legal_expense">
                            Legal Expense
                        </option>
                        <option value="other">
                            Other
                        </option>
                    </select>
                </Field>

                <Field label="Title">
                    <input
                        required
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData(
                                'title',
                                event.target.value
                            )
                        }
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                </Field>

                <Field label="Amount">
                    <input
                        required
                        type="number"
                        min="0.01"
                        step="0.01"
                        value={form.data.amount}
                        onChange={(event) =>
                            form.setData(
                                'amount',
                                event.target.value
                            )
                        }
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                </Field>

                <label className="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                    <input
                        type="checkbox"
                        checked={
                            form.data.is_recoverable
                        }
                        onChange={(event) =>
                            form.setData(
                                'is_recoverable',
                                event.target.checked
                            )
                        }
                        className="rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                    />

                    <div>
                        <div className="text-sm font-semibold text-slate-800">
                            Recoverable expense
                        </div>
                        <div className="text-xs text-slate-500">
                            Adds this amount to the outstanding
                            recoupment balance.
                        </div>
                    </div>
                </label>

                <Field label="Expense Date">
                    <input
                        type="date"
                        required
                        value={
                            form.data.expense_date
                        }
                        onChange={(event) =>
                            form.setData(
                                'expense_date',
                                event.target.value
                            )
                        }
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                </Field>

                <Field label="Reference / Invoice">
                    <input
                        value={form.data.reference_number}
                        onChange={(event) =>
                            form.setData(
                                'reference_number',
                                event.target.value
                            )
                        }
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                </Field>

                <Field label="Description">
                    <textarea
                        rows="3"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData(
                                'description',
                                event.target.value
                            )
                        }
                        className="w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500"
                    />
                </Field>

                <Errors errors={form.errors} />

                <ModalActions
                    processing={form.processing}
                    onClose={onClose}
                    submitLabel="Add Expense"
                />
            </form>
        </Modal>
    );
}

function DetailsModal({
    plan,
    onClose,
    onRefresh,
}) {
    const form = useForm({
        document_type: 'advance_agreement',
        recoupment_expense_id: '',
        document: null,
    });

    const submitDocument = (event) => {
        event.preventDefault();

        form.post(
            `/super-admin/finance/recoupment/${plan.id}/documents`,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    form.reset('document');
                    onRefresh();
                },
            }
        );
    };

    const accountName =
        plan.label?.name ??
        plan.artist?.stage_name ??
        plan.artist?.legal_name ??
        plan.user?.name ??
        'Account';

    const baseShare =
        Number(
            plan.base_percentage ?? 0
        );

    const uplift =
        Math.min(
            baseShare,
            Number(
                plan.recovery_uplift_percentage ??
                    0
            ),
            plan.maximum_recovery_percentage ===
                null
                ? Number(
                      plan.recovery_uplift_percentage ??
                          0
                  )
                : Number(
                      plan.maximum_recovery_percentage
                  )
        );

    const effectiveShare =
        plan.status === 'active' &&
        Number(
            plan.outstanding_amount ?? 0
        ) > 0
            ? Math.max(
                  0,
                  baseShare - uplift
              )
            : baseShare;

    return (
        <Modal
            title={`Plan Details · ${plan.plan_number}`}
            onClose={onClose}
            size="xl"
        >
            <div className="space-y-7">
                <section>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <DetailStat
                            label="Account"
                            value={accountName}
                        />

                        <DetailStat
                            label="Recoverable"
                            value={money(
                                plan.total_recoverable_amount
                            )}
                        />

                        <DetailStat
                            label="Recovered"
                            value={money(
                                plan.total_recovered_amount
                            )}
                        />

                        <DetailStat
                            label="Outstanding"
                            value={money(
                                plan.outstanding_amount
                            )}
                        />
                    </div>

                    <div className="mt-3 grid gap-3 sm:grid-cols-3">
                        <DetailStat
                            label="Base Share"
                            value={percentage(
                                baseShare
                            )}
                        />

                        <DetailStat
                            label="Recovery Uplift"
                            value={percentage(
                                uplift
                            )}
                        />

                        <DetailStat
                            label="Effective Share"
                            value={percentage(
                                effectiveShare
                            )}
                        />
                    </div>

                    {plan.notes && (
                        <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Internal Notes
                            </div>

                            <div className="mt-1 whitespace-pre-wrap text-sm text-slate-700">
                                {plan.notes}
                            </div>
                        </div>
                    )}
                </section>

                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-sm font-bold text-slate-900">
                            Expenses
                        </h3>

                        <span className="text-xs text-slate-500">
                            {(plan.expenses ?? []).length}{' '}
                            entries
                        </span>
                    </div>

                    {(plan.expenses ?? []).length ===
                    0 ? (
                        <EmptyDetail>
                            No expenses recorded.
                        </EmptyDetail>
                    ) : (
                        <div className="space-y-2">
                            {plan.expenses.map(
                                (expense) => (
                                    <div
                                        key={expense.id}
                                        className="rounded-xl border border-slate-200 p-4"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div className="font-semibold text-slate-900">
                                                    {expense.title}
                                                </div>

                                                <div className="mt-1 text-xs capitalize text-slate-500">
                                                    {String(
                                                        expense.category ??
                                                            ''
                                                    ).replaceAll(
                                                        '_',
                                                        ' '
                                                    )}
                                                    {' · '}
                                                    {expense.expense_date ??
                                                        '—'}
                                                </div>

                                                {expense.reference_number && (
                                                    <div className="mt-1 text-xs text-slate-500">
                                                        Ref:{' '}
                                                        {
                                                            expense.reference_number
                                                        }
                                                    </div>
                                                )}
                                            </div>

                                            <div className="text-right">
                                                <div className="font-bold text-slate-900">
                                                    {money(
                                                        expense.amount
                                                    )}
                                                </div>

                                                <div
                                                    className={[
                                                        'mt-1 text-xs font-semibold',
                                                        expense.is_recoverable
                                                            ? 'text-amber-700'
                                                            : 'text-slate-500',
                                                    ].join(
                                                        ' '
                                                    )}
                                                >
                                                    {expense.is_recoverable
                                                        ? 'Recoverable'
                                                        : 'Non-recoverable'}
                                                </div>
                                            </div>
                                        </div>

                                        {expense.description && (
                                            <div className="mt-3 text-sm text-slate-600">
                                                {
                                                    expense.description
                                                }
                                            </div>
                                        )}
                                    </div>
                                )
                            )}
                        </div>
                    )}
                </section>

                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-sm font-bold text-slate-900">
                            Documents
                        </h3>

                        <span className="text-xs text-slate-500">
                            {(plan.documents ?? []).length}{' '}
                            files
                        </span>
                    </div>

                    <form
                        onSubmit={submitDocument}
                        className="mb-4 space-y-3 rounded-xl border border-violet-100 bg-violet-50/40 p-4"
                    >
                        <div className="grid gap-3 md:grid-cols-2">
                            <Field label="Document Type">
                                <select
                                    value={
                                        form.data
                                            .document_type
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'document_type',
                                            event.target.value
                                        )
                                    }
                                    className="w-full rounded-xl border-slate-300 bg-white text-sm focus:border-violet-500 focus:ring-violet-500"
                                >
                                    <option value="signed_agreement">
                                        Signed Agreement
                                    </option>

                                    <option value="advance_agreement">
                                        Advance Agreement
                                    </option>

                                    <option value="invoice">
                                        Invoice
                                    </option>

                                    <option value="payment_receipt">
                                        Payment Receipt
                                    </option>

                                    <option value="bank_proof">
                                        Bank Proof
                                    </option>

                                    <option value="utr_proof">
                                        UTR Proof
                                    </option>

                                    <option value="addendum">
                                        Addendum
                                    </option>

                                    <option value="other">
                                        Other
                                    </option>
                                </select>
                            </Field>

                            <Field label="Attach To">
                                <select
                                    value={
                                        form.data
                                            .recoupment_expense_id
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'recoupment_expense_id',
                                            event.target.value
                                        )
                                    }
                                    className="w-full rounded-xl border-slate-300 bg-white text-sm focus:border-violet-500 focus:ring-violet-500"
                                >
                                    <option value="">
                                        Entire Plan
                                    </option>

                                    {(plan.expenses ?? []).map(
                                        (expense) => (
                                            <option
                                                key={
                                                    expense.id
                                                }
                                                value={
                                                    expense.id
                                                }
                                            >
                                                {expense.title}
                                            </option>
                                        )
                                    )}
                                </select>
                            </Field>
                        </div>

                        <Field label="File">
                            <input
                                required
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                onChange={(event) =>
                                    form.setData(
                                        'document',
                                        event.target
                                            .files?.[0] ??
                                            null
                                    )
                                }
                                className="block w-full rounded-xl border border-slate-300 bg-white p-2 text-sm"
                            />
                        </Field>

                        <div className="text-xs text-slate-500">
                            PDF/JPG/PNG/WEBP · maximum
                            10 MB · stored privately.
                        </div>

                        <Errors
                            errors={form.errors}
                        />

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            <Upload className="h-4 w-4" />

                            {form.processing
                                ? 'Uploading...'
                                : 'Upload Document'}
                        </button>
                    </form>

                    {(plan.documents ?? []).length ===
                    0 ? (
                        <EmptyDetail>
                            No documents uploaded.
                        </EmptyDetail>
                    ) : (
                        <div className="space-y-2">
                            {plan.documents.map(
                                (document) => (
                                    <div
                                        key={document.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3"
                                    >
                                        <div className="min-w-0">
                                            <div className="truncate text-sm font-semibold text-slate-900">
                                                {
                                                    document.original_name
                                                }
                                            </div>

                                            <div className="mt-1 text-xs capitalize text-slate-500">
                                                {String(
                                                    document.document_type ??
                                                        ''
                                                ).replaceAll(
                                                    '_',
                                                    ' '
                                                )}

                                                {document.file_size
                                                    ? ` · ${formatFileSize(
                                                          document.file_size
                                                      )}`
                                                    : ''}
                                            </div>
                                        </div>

                                        <a
                                            href={`/super-admin/finance/recoupment/${plan.id}/documents/${document.id}/download`}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            <Download className="h-3.5 w-3.5" />
                                            Download
                                        </a>
                                    </div>
                                )
                            )}
                        </div>
                    )}
                </section>

                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-sm font-bold text-slate-900">
                            Recovery History
                        </h3>

                        <span className="text-xs text-slate-500">
                            {(plan.recoveries ?? []).length}{' '}
                            entries
                        </span>
                    </div>

                    {(plan.recoveries ?? []).length ===
                    0 ? (
                        <EmptyDetail>
                            No recovery entries yet.
                        </EmptyDetail>
                    ) : (
                        <div className="space-y-2">
                            {plan.recoveries.map(
                                (recovery) => (
                                    <div
                                        key={recovery.id}
                                        className="rounded-xl border border-slate-200 p-4"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-4">
                                            <div>
                                                <div className="font-semibold text-slate-900">
                                                    Recovery{' '}
                                                    {percentage(
                                                        recovery.recovery_percentage
                                                    )}
                                                </div>

                                                <div className="mt-1 text-xs text-slate-500">
                                                    Reporting month:{' '}
                                                    {recovery.reporting_month ??
                                                        '—'}
                                                </div>

                                                {recovery.reference && (
                                                    <div className="mt-1 text-xs text-slate-500">
                                                        Ref:{' '}
                                                        {
                                                            recovery.reference
                                                        }
                                                    </div>
                                                )}
                                            </div>

                                            <div className="text-right">
                                                <div className="font-bold text-emerald-700">
                                                    {money(
                                                        recovery.applied_recovery_amount
                                                    )}
                                                </div>

                                                <div className="mt-1 text-xs text-slate-500">
                                                    Source:{' '}
                                                    {money(
                                                        recovery.source_amount
                                                    )}
                                                </div>

                                                <div className="mt-1 text-xs text-slate-500">
                                                    Outstanding:{' '}
                                                    {money(
                                                        recovery.outstanding_before
                                                    )}
                                                    {' → '}
                                                    {money(
                                                        recovery.outstanding_after
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )
                            )}
                        </div>
                    )}
                </section>
            </div>
        </Modal>
    );
}

function DetailStat({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <div className="text-xs font-medium text-slate-500">
                {label}
            </div>

            <div className="mt-1 truncate text-sm font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function EmptyDetail({
    children,
}) {
    return (
        <div className="rounded-xl border border-dashed border-slate-300 px-4 py-5 text-center text-sm text-slate-500">
            {children}
        </div>
    );
}

function formatFileSize(bytes) {
    const value = Number(bytes || 0);

    if (!value) {
        return '0 B';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1024 * 1024) {
        return `${(
            value / 1024
        ).toFixed(1)} KB`;
    }

    return `${(
        value /
        (1024 * 1024)
    ).toFixed(1)} MB`;
}

function Field({
    label,
    children,
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">
                {label}
            </span>
            {children}
        </label>
    );
}

function Errors({
    errors,
}) {
    const messages = Object.values(
        errors || {}
    );

    if (!messages.length) {
        return null;
    }

    return (
        <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {messages.map((message, index) => (
                <div key={index}>
                    {message}
                </div>
            ))}
        </div>
    );
}

function Modal({
    title,
    onClose,
    children,
    size = 'default',
}) {
    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4">
            <div
                className={[
                    'max-h-[92vh] w-full overflow-y-auto rounded-2xl bg-white shadow-2xl',
                    size === 'xl'
                        ? 'max-w-5xl'
                        : 'max-w-2xl',
                ].join(' ')}
            >
                <div className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
                    <h2 className="text-lg font-bold text-slate-900">
                        {title}
                    </h2>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="p-6">
                    {children}
                </div>
            </div>
        </div>
    );
}

function ModalActions({
    processing,
    onClose,
    submitLabel,
}) {
    return (
        <div className="flex justify-end gap-3 border-t border-slate-200 pt-4">
            <button
                type="button"
                onClick={onClose}
                className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Cancel
            </button>

            <button
                disabled={processing}
                type="submit"
                className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {processing
                    ? 'Saving...'
                    : submitLabel}
            </button>
        </div>
    );
}

function Pagination({
    links,
}) {
    if (!Array.isArray(links) ||
        links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-center gap-1 border-t border-slate-200 px-5 py-4">
            {links.map((link, index) => (
                <Link
                    key={index}
                    href={link.url || '#'}
                    preserveScroll
                    className={[
                        'rounded-lg px-3 py-2 text-sm font-medium',
                        link.active
                            ? 'bg-violet-600 text-white'
                            : 'text-slate-600 hover:bg-slate-100',
                        !link.url
                            ? 'pointer-events-none opacity-40'
                            : '',
                    ].join(' ')}
                    dangerouslySetInnerHTML={{
                        __html: link.label,
                    }}
                />
            ))}
        </div>
    );
}
