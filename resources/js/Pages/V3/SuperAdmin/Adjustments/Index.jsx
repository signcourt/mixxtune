import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    History,
    Plus,
    RotateCcw,
    WalletCards,
    X,
} from 'lucide-react';
import {
    useState,
} from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const money = (value) =>
    new Intl.NumberFormat(
        'en-IN',
        {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 2,
        }
    ).format(
        Number(value || 0)
    );

export default function Index({
    adjustments,
    accounts = [],
    summary = {},
}) {
    const [showAdjust, setShowAdjust] =
        useState(false);

    const [
        reverseAdjustment,
        setReverseAdjustment,
    ] = useState(null);

    return (
        <PanelLayout>
            <Head title="Financial Adjustments" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Financial Adjustments
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Immutable manual wallet credits,
                            debits and reversal history.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() =>
                            setShowAdjust(true)
                        }
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"
                    >
                        <Plus className="h-4 w-4" />
                        Adjust Balance
                    </button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={ArrowUpCircle}
                        label="Posted Credits"
                        value={money(
                            summary.credits
                        )}
                    />

                    <StatCard
                        icon={ArrowDownCircle}
                        label="Posted Debits"
                        value={money(
                            summary.debits
                        )}
                    />

                    <StatCard
                        icon={WalletCards}
                        label="Posted Entries"
                        value={
                            summary.posted || 0
                        }
                    />

                    <StatCard
                        icon={History}
                        label="Reversed Entries"
                        value={
                            summary.reversed || 0
                        }
                    />
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="font-bold text-slate-900">
                            Adjustment History
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>
                                        Adjustment
                                    </Th>
                                    <Th>
                                        Account
                                    </Th>
                                    <Th>
                                        Direction
                                    </Th>
                                    <Th>
                                        Amount
                                    </Th>
                                    <Th>
                                        Balance
                                    </Th>
                                    <Th>
                                        Reason
                                    </Th>
                                    <Th>
                                        Status
                                    </Th>
                                    <Th>
                                        Created By
                                    </Th>
                                    <Th>
                                        Action
                                    </Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {adjustments?.data?.length
                                    ? adjustments.data.map(
                                          (
                                              adjustment
                                          ) => (
                                              <AdjustmentRow
                                                  key={
                                                      adjustment.id
                                                  }
                                                  adjustment={
                                                      adjustment
                                                  }
                                                  onReverse={() =>
                                                      setReverseAdjustment(
                                                          adjustment
                                                      )
                                                  }
                                              />
                                          )
                                      )
                                    : (
                                        <tr>
                                            <td
                                                colSpan="9"
                                                className="px-5 py-12 text-center text-slate-500"
                                            >
                                                No financial
                                                adjustments
                                                recorded yet.
                                            </td>
                                        </tr>
                                    )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        links={
                            adjustments?.links
                        }
                    />
                </div>
            </div>

            {showAdjust && (
                <AdjustmentModal
                    accounts={accounts}
                    onClose={() =>
                        setShowAdjust(false)
                    }
                />
            )}

            {reverseAdjustment && (
                <ReverseModal
                    adjustment={
                        reverseAdjustment
                    }
                    onClose={() =>
                        setReverseAdjustment(
                            null
                        )
                    }
                />
            )}
        </PanelLayout>
    );
}

function AdjustmentRow({
    adjustment,
    onReverse,
}) {
    const tx =
        adjustment.wallet_transaction;

    const canReverse =
        adjustment.status === 'posted'
        && adjustment.type !== 'reversal'
        && !adjustment.reversal_of_id;

    return (
        <tr className="align-top hover:bg-slate-50/70">
            <Td>
                <div className="font-semibold text-slate-900">
                    {
                        adjustment
                            .adjustment_number
                    }
                </div>

                {adjustment.reference_number && (
                    <div className="mt-1 text-xs text-slate-500">
                        Ref:{' '}
                        {
                            adjustment
                                .reference_number
                        }
                    </div>
                )}
            </Td>

            <Td>
                <div className="font-semibold text-slate-900">
                    {adjustment.user?.name
                        || '—'}
                </div>

                <div className="mt-1 text-xs text-slate-500">
                    {adjustment.user?.email
                        || ''}
                </div>

                <div className="mt-1 text-xs uppercase text-slate-400">
                    {adjustment.user?.role
                        || ''}
                </div>
            </Td>

            <Td>
                <DirectionBadge
                    direction={
                        adjustment.direction
                    }
                />

                {adjustment.type ===
                    'reversal' && (
                    <div className="mt-2 text-xs text-slate-500">
                        Reversal
                    </div>
                )}
            </Td>

            <Td>
                <div className="font-bold text-slate-900">
                    {money(
                        adjustment.amount
                    )}
                </div>
            </Td>

            <Td>
                {tx ? (
                    <>
                        <div className="font-medium text-slate-700">
                            {money(
                                tx.balance_before
                            )}
                            {' → '}
                            {money(
                                tx.balance_after
                            )}
                        </div>

                        <div className="mt-1 text-xs text-slate-500">
                            {tx.currency
                                || 'INR'}
                        </div>
                    </>
                ) : (
                    '—'
                )}
            </Td>

            <Td>
                <div className="max-w-xs whitespace-normal text-slate-700">
                    {adjustment.reason}
                </div>

                {adjustment.internal_note && (
                    <div className="mt-1 max-w-xs whitespace-normal text-xs text-slate-500">
                        {
                            adjustment
                                .internal_note
                        }
                    </div>
                )}
            </Td>

            <Td>
                <StatusBadge
                    status={
                        adjustment.status
                    }
                />
            </Td>

            <Td>
                <div className="text-slate-700">
                    {adjustment.creator?.name
                        || 'System'}
                </div>

                {adjustment.reversed_by && (
                    <div className="mt-1 text-xs text-slate-500">
                        Reversed by{' '}
                        {
                            adjustment
                                .reversed_by
                                .name
                        }
                    </div>
                )}
            </Td>

            <Td>
                {canReverse ? (
                    <button
                        type="button"
                        onClick={onReverse}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50"
                    >
                        <RotateCcw className="h-3.5 w-3.5" />
                        Reverse
                    </button>
                ) : (
                    <span className="text-xs text-slate-400">
                        —
                    </span>
                )}
            </Td>
        </tr>
    );
}

function newAdjustmentIdempotencyKey() {
    if (
        typeof crypto !== 'undefined'
        && typeof crypto.randomUUID ===
            'function'
    ) {
        return crypto.randomUUID();
    }

    return [
        'adj',
        Date.now(),
        Math.random()
            .toString(36)
            .slice(2),
    ].join('-');
}

function AdjustmentModal({
    accounts,
    onClose,
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        idempotency_key:
            newAdjustmentIdempotencyKey(),
        user_id: '',
        direction: 'credit',
        amount: '',
        reason: '',
        reference_number: '',
        internal_note: '',
        effective_date: '',
        type: 'manual',
        currency: 'INR',
    });

    const submit = (event) => {
        event.preventDefault();

        post(
            '/super-admin/finance/adjustments',
            {
                preserveScroll: true,
                onSuccess: onClose,
            }
        );
    };

    return (
        <Modal
            title="Adjust Wallet Balance"
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <Errors errors={errors} />

                <Field label="Account">
                    <select
                        required
                        value={data.user_id}
                        onChange={(event) =>
                            setData(
                                'user_id',
                                event.target.value
                            )
                        }
                        className={inputClass}
                    >
                        <option value="">
                            Select label or artist
                        </option>

                        {accounts.map(
                            (account) => (
                                <option
                                    key={
                                        account.id
                                    }
                                    value={
                                        account.id
                                    }
                                >
                                    {account.name}
                                    {' · '}
                                    {account.role}
                                    {account.email
                                        ? ` · ${account.email}`
                                        : ''}
                                </option>
                            )
                        )}
                    </select>
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Direction">
                        <select
                            value={
                                data.direction
                            }
                            onChange={(event) =>
                                setData(
                                    'direction',
                                    event.target.value
                                )
                            }
                            className={
                                inputClass
                            }
                        >
                            <option value="credit">
                                Credit — Add Money
                            </option>
                            <option value="debit">
                                Debit — Deduct Money
                            </option>
                        </select>
                    </Field>

                    <Field label="Amount">
                        <input
                            required
                            min="0.01"
                            step="0.01"
                            type="number"
                            value={data.amount}
                            onChange={(event) =>
                                setData(
                                    'amount',
                                    event.target.value
                                )
                            }
                            className={
                                inputClass
                            }
                            placeholder="0.00"
                        />
                    </Field>
                </div>

                <Field label="Reason">
                    <textarea
                        required
                        rows="3"
                        value={data.reason}
                        onChange={(event) =>
                            setData(
                                'reason',
                                event.target.value
                            )
                        }
                        className={inputClass}
                        placeholder="Why is this adjustment being made?"
                    />
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Reference Number">
                        <input
                            value={
                                data.reference_number
                            }
                            onChange={(event) =>
                                setData(
                                    'reference_number',
                                    event.target.value
                                )
                            }
                            className={
                                inputClass
                            }
                            placeholder="Optional"
                        />
                    </Field>

                    <Field label="Effective Date">
                        <input
                            type="date"
                            value={
                                data.effective_date
                            }
                            onChange={(event) =>
                                setData(
                                    'effective_date',
                                    event.target.value
                                )
                            }
                            className={
                                inputClass
                            }
                        />
                    </Field>
                </div>

                <Field label="Internal Note">
                    <textarea
                        rows="3"
                        value={
                            data.internal_note
                        }
                        onChange={(event) =>
                            setData(
                                'internal_note',
                                event.target.value
                            )
                        }
                        className={inputClass}
                        placeholder="Optional internal audit note"
                    />
                </Field>

                <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    This posts an immutable
                    financial ledger entry.
                    Corrections must be made
                    through Reverse.
                </div>

                <ModalActions
                    processing={processing}
                    onClose={onClose}
                    submitLabel={
                        data.direction ===
                        'credit'
                            ? 'Post Credit'
                            : 'Post Debit'
                    }
                />
            </form>
        </Modal>
    );
}

function ReverseModal({
    adjustment,
    onClose,
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        reason: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(
            `/super-admin/finance/adjustments/${adjustment.id}/reverse`,
            {
                preserveScroll: true,
                onSuccess: onClose,
            }
        );
    };

    return (
        <Modal
            title={`Reverse ${adjustment.adjustment_number}`}
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <Errors errors={errors} />

                <div className="rounded-xl border border-red-200 bg-red-50 p-4">
                    <div className="text-sm font-semibold text-red-900">
                        Reversal amount
                    </div>

                    <div className="mt-1 text-xl font-bold text-red-700">
                        {money(
                            adjustment.amount
                        )}
                    </div>

                    <div className="mt-2 text-sm text-red-700">
                        A compensating wallet
                        transaction will be
                        created. The original
                        financial record will
                        remain in history.
                    </div>
                </div>

                <Field label="Reversal Reason">
                    <textarea
                        required
                        rows="4"
                        value={data.reason}
                        onChange={(event) =>
                            setData(
                                'reason',
                                event.target.value
                            )
                        }
                        className={inputClass}
                        placeholder="Explain why this adjustment is being reversed."
                    />
                </Field>

                <ModalActions
                    processing={processing}
                    onClose={onClose}
                    submitLabel="Confirm Reversal"
                    danger
                />
            </form>
        </Modal>
    );
}

function StatCard({
    icon: Icon,
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center gap-3">
                <div className="rounded-xl bg-slate-100 p-2.5 text-slate-700">
                    <Icon className="h-5 w-5" />
                </div>

                <div>
                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {label}
                    </div>

                    <div className="mt-1 text-xl font-bold text-slate-900">
                        {value}
                    </div>
                </div>
            </div>
        </div>
    );
}

function DirectionBadge({
    direction,
}) {
    const credit =
        direction === 'credit';

    return (
        <span
            className={[
                'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                credit
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-red-100 text-red-700',
            ].join(' ')}
        >
            {credit
                ? 'CREDIT'
                : 'DEBIT'}
        </span>
    );
}

function StatusBadge({
    status,
}) {
    return (
        <span
            className={[
                'inline-flex rounded-full px-2.5 py-1 text-xs font-bold uppercase',
                status === 'posted'
                    ? 'bg-blue-100 text-blue-700'
                    : 'bg-slate-200 text-slate-600',
            ].join(' ')}
        >
            {status || 'unknown'}
        </span>
    );
}

function Th({
    children,
}) {
    return (
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
            {children}
        </th>
    );
}

function Td({
    children,
}) {
    return (
        <td className="whitespace-nowrap px-5 py-4">
            {children}
        </td>
    );
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
    const messages =
        Object.values(
            errors || {}
        );

    if (!messages.length) {
        return null;
    }

    return (
        <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {messages.map(
                (
                    message,
                    index
                ) => (
                    <div key={index}>
                        {message}
                    </div>
                )
            )}
        </div>
    );
}

function Modal({
    title,
    onClose,
    children,
}) {
    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4">
            <div className="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
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
    danger = false,
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
                className={[
                    'rounded-xl px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50',
                    danger
                        ? 'bg-red-600 hover:bg-red-700'
                        : 'bg-violet-600 hover:bg-violet-700',
                ].join(' ')}
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
    if (
        !Array.isArray(links)
        || links.length <= 3
    ) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-center gap-1 border-t border-slate-200 px-5 py-4">
            {links.map(
                (
                    link,
                    index
                ) => (
                    <Link
                        key={index}
                        href={
                            link.url
                            || '#'
                        }
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
                            __html:
                                link.label,
                        }}
                    />
                )
            )}
        </div>
    );
}

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-100';
