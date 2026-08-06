import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import {
    useMemo,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statuses = [
    'processing',
    'delivered',
    'live',
    'failed',
    'takedown_requested',
    'taken_down',
];

const statusClasses = {
    pending:
        'bg-slate-100 text-slate-700',

    processing:
        'bg-blue-100 text-blue-700',

    delivered:
        'bg-indigo-100 text-indigo-700',

    live:
        'bg-emerald-100 text-emerald-700',

    failed:
        'bg-red-100 text-red-700',

    takedown_requested:
        'bg-amber-100 text-amber-700',

    taken_down:
        'bg-slate-800 text-white',
};

export default function Show({
    role = 'admin',
    release,
    deliveries = [],
    summary = {},
}) {
    const [
        selected,
        setSelected,
    ] = useState([]);

    const [
        bulkStatus,
        setBulkStatus,
    ] = useState('processing');

    const [
        bulkNote,
        setBulkNote,
    ] = useState('');

    const [
        bulkError,
        setBulkError,
    ] = useState('');

    const [
        processing,
        setProcessing,
    ] = useState(false);

    const allSelected =
        deliveries.length > 0 &&
        selected.length ===
            deliveries.length;

    const toggleAll = () => {
        setSelected(
            allSelected
                ? []
                : deliveries.map(
                      (item) =>
                          item.id
                  )
        );
    };

    const toggleOne = (id) => {
        setSelected(
            (current) =>
                current.includes(id)
                    ? current.filter(
                          (item) =>
                              item !== id
                      )
                    : [
                          ...current,
                          id,
                      ]
        );
    };

    const bulkUpdate = () => {
        if (
            selected.length === 0
        ) {
            return;
        }

        setProcessing(true);

        router.patch(
            `/v2/admin/distribution/${release.id}/bulk`,
            {
                delivery_ids:
                    selected,

                status:
                    bulkStatus,

                delivery_note:
                    bulkNote,

                error_message:
                    bulkStatus ===
                    'failed'
                        ? bulkError
                        : null,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setSelected([]);
                    setBulkNote('');
                    setBulkError('');
                },

                onFinish: () =>
                    setProcessing(
                        false
                    ),
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="DSP Delivery"
            subtitle={release.title}
        >
            <Head
                title={`DSP Delivery - ${release.title}`}
            />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/v2/admin/distribution"
                        className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                    >
                        ← Distribution Queue
                    </Link>

                    <span className="rounded-full bg-blue-100 px-4 py-2 text-sm font-semibold capitalize text-blue-700">
                        {release.status.replaceAll(
                            '_',
                            ' '
                        )}
                    </span>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    {[
                        'total',
                        'pending',
                        'processing',
                        'delivered',
                        'live',
                        'failed',
                    ].map(
                        (status) => (
                            <div
                                key={status}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div className="text-xs font-semibold uppercase text-slate-500">
                                    {status}
                                </div>

                                <div className="mt-2 text-3xl font-bold text-slate-900">
                                    {summary[
                                        status
                                    ] ?? 0}
                                </div>
                            </div>
                        )
                    )}
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Bulk Update
                    </h2>

                    <div className="mt-4 grid gap-3 lg:grid-cols-4">
                        <select
                            value={
                                bulkStatus
                            }
                            onChange={(
                                event
                            ) =>
                                setBulkStatus(
                                    event.target
                                        .value
                                )
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            {statuses.map(
                                (
                                    status
                                ) => (
                                    <option
                                        key={
                                            status
                                        }
                                        value={
                                            status
                                        }
                                    >
                                        {status.replaceAll(
                                            '_',
                                            ' '
                                        )}
                                    </option>
                                )
                            )}
                        </select>

                        <input
                            value={bulkNote}
                            onChange={(
                                event
                            ) =>
                                setBulkNote(
                                    event.target
                                        .value
                                )
                            }
                            placeholder="Delivery note"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <input
                            value={
                                bulkError
                            }
                            onChange={(
                                event
                            ) =>
                                setBulkError(
                                    event.target
                                        .value
                                )
                            }
                            disabled={
                                bulkStatus !==
                                'failed'
                            }
                            placeholder="Failure reason"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm disabled:bg-slate-100"
                        />

                        <button
                            type="button"
                            disabled={
                                processing ||
                                selected.length ===
                                    0 ||
                                (
                                    bulkStatus ===
                                    'failed' &&
                                    bulkError
                                        .trim()
                                        .length < 3
                                )
                            }
                            onClick={
                                bulkUpdate
                            }
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing
                                ? 'Updating...'
                                : `Update ${selected.length} Selected`}
                        </button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="text-lg font-semibold text-slate-900">
                            Store Deliveries
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-5 py-4 text-left">
                                        <input
                                            type="checkbox"
                                            checked={
                                                allSelected
                                            }
                                            onChange={
                                                toggleAll
                                            }
                                        />
                                    </th>

                                    {[
                                        'Store',
                                        'Status',
                                        'Reference',
                                        'Note',
                                        'Error',
                                        'Updated',
                                    ].map(
                                        (
                                            heading
                                        ) => (
                                            <th
                                                key={
                                                    heading
                                                }
                                                className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
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
                                {deliveries.length >
                                0 ? (
                                    deliveries.map(
                                        (
                                            delivery
                                        ) => (
                                            <tr
                                                key={
                                                    delivery.id
                                                }
                                            >
                                                <td className="px-5 py-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selected.includes(
                                                            delivery.id
                                                        )}
                                                        onChange={() =>
                                                            toggleOne(
                                                                delivery.id
                                                            )
                                                        }
                                                    />
                                                </td>

                                                <td className="px-5 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                                                            {delivery
                                                                .store
                                                                ?.logo_path ? (
                                                                <img
                                                                    src={
                                                                        delivery.store.logo_path.startsWith(
                                                                            'http'
                                                                        )
                                                                            ? delivery
                                                                                  .store
                                                                                  .logo_path
                                                                            : `/storage/${delivery.store.logo_path}`
                                                                    }
                                                                    alt={
                                                                        delivery
                                                                            .store
                                                                            ?.name
                                                                    }
                                                                    className="h-full w-full object-contain p-1"
                                                                />
                                                            ) : (
                                                                <span className="font-bold text-slate-500">
                                                                    {delivery
                                                                        .store
                                                                        ?.name
                                                                        ?.charAt(
                                                                            0
                                                                        ) ??
                                                                        '?'}
                                                                </span>
                                                            )}
                                                        </div>

                                                        <span className="font-semibold text-slate-900">
                                                            {delivery
                                                                .store
                                                                ?.name ||
                                                                'Unknown Store'}
                                                        </span>
                                                    </div>
                                                </td>

                                                <td className="px-5 py-4">
                                                    <span
                                                        className={[
                                                            'rounded-full px-3 py-1 text-xs font-semibold capitalize',
                                                            statusClasses[
                                                                delivery
                                                                    .status
                                                            ] ??
                                                                statusClasses.pending,
                                                        ].join(
                                                            ' '
                                                        )}
                                                    >
                                                        {delivery.status.replaceAll(
                                                            '_',
                                                            ' '
                                                        )}
                                                    </span>
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {delivery.external_reference ||
                                                        '—'}
                                                </td>

                                                <td className="max-w-64 px-5 py-4 text-sm text-slate-600">
                                                    {delivery.delivery_note ||
                                                        '—'}
                                                </td>

                                                <td className="max-w-64 px-5 py-4 text-sm text-red-600">
                                                    {delivery.error_message ||
                                                        '—'}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-500">
                                                    {delivery.updated_at ||
                                                        '—'}
                                                </td>
                                            </tr>
                                        )
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            DSP deliveries
                                            have not been
                                            initialized.
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
