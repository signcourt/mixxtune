import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

const statusStyles = {
    pending: 'bg-slate-100 text-slate-700',
    processing: 'bg-blue-50 text-blue-700',
    delivered: 'bg-violet-50 text-violet-700',
    live: 'bg-emerald-50 text-emerald-700',
    failed: 'bg-red-50 text-red-700',
    takedown: 'bg-amber-50 text-amber-700',
};

export default function DeliveryDetails({ release }) {
    const [editing, setEditing] = useState(null);
    const [selectedDeliveryIds, setSelectedDeliveryIds] = useState([]);
    const [bulkStatus, setBulkStatus] = useState('processing');
    const [bulkNotes, setBulkNotes] = useState('');
    const [bulkProcessing, setBulkProcessing] = useState(false);

    const deliveries = release.store_deliveries ?? [];

    const allSelected =
        deliveries.length > 0 &&
        selectedDeliveryIds.length === deliveries.length;

    const statusCounts = deliveries.reduce(
        (counts, delivery) => {
            const status = delivery.status ?? 'pending';

            counts[status] = (counts[status] ?? 0) + 1;

            return counts;
        },
        {
            pending: 0,
            processing: 0,
            delivered: 0,
            live: 0,
            failed: 0,
            takedown: 0,
        }
    );

    const toggleDelivery = (deliveryId) => {
        setSelectedDeliveryIds((current) =>
            current.includes(deliveryId)
                ? current.filter((id) => id !== deliveryId)
                : [...current, deliveryId]
        );
    };

    const toggleAllDeliveries = () => {
        setSelectedDeliveryIds(
            allSelected
                ? []
                : deliveries.map((delivery) => delivery.id)
        );
    };

    const bulkUpdate = () => {
        if (selectedDeliveryIds.length === 0) {
            window.alert('Select at least one DSP.');
            return;
        }

        setBulkProcessing(true);

        router.patch(
            `/delivery-status/${release.id}/bulk-update`,
            {
                delivery_ids: selectedDeliveryIds,
                status: bulkStatus,
                delivery_notes: bulkNotes || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedDeliveryIds([]);
                    setBulkNotes('');
                },
                onFinish: () => setBulkProcessing(false),
            }
        );
    };

    return (
        <AdminLayout title="Delivery Details">
            <Head title={`Delivery - ${release.title}`} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            {release.title}
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            {release.primary_artist_name}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    `/delivery-status/${release.id}/sync`,
                                    {},
                                    {
                                        preserveScroll: true,
                                    }
                                )
                            }
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                        >
                            Sync DSPs
                        </button>

                        <Link
                            href="/delivery-status"
                            className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700"
                        >
                            Back to Delivery Status
                        </Link>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Stat
                        label="Catalogue Number"
                        value={release.catalog_number || '—'}
                    />

                    <Stat
                        label="UPC"
                        value={release.upc || 'Pending'}
                    />

                    <Stat
                        label="DSPs"
                        value={
                            release.store_deliveries?.length ?? 0
                        }
                    />
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <StatusStat
                        label="Pending"
                        value={statusCounts.pending}
                        className="bg-slate-50 text-slate-700"
                    />

                    <StatusStat
                        label="Processing"
                        value={statusCounts.processing}
                        className="bg-blue-50 text-blue-700"
                    />

                    <StatusStat
                        label="Delivered"
                        value={statusCounts.delivered}
                        className="bg-violet-50 text-violet-700"
                    />

                    <StatusStat
                        label="Live"
                        value={statusCounts.live}
                        className="bg-emerald-50 text-emerald-700"
                    />

                    <StatusStat
                        label="Failed"
                        value={statusCounts.failed}
                        className="bg-red-50 text-red-700"
                    />

                    <StatusStat
                        label="Takedown"
                        value={statusCounts.takedown}
                        className="bg-amber-50 text-amber-700"
                    />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <label className="flex cursor-pointer items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={allSelected}
                                    onChange={toggleAllDeliveries}
                                    className="rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                                />

                                <span className="text-sm font-semibold text-slate-700">
                                    Select All DSPs
                                </span>
                            </label>

                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                {selectedDeliveryIds.length} selected
                            </span>
                        </div>

                        <div className="grid gap-3 lg:grid-cols-[220px_minmax(260px,1fr)_auto]">
                            <select
                                value={bulkStatus}
                                onChange={(event) =>
                                    setBulkStatus(event.target.value)
                                }
                                className="rounded-xl border-slate-300"
                            >
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="delivered">Delivered</option>
                                <option value="live">Live</option>
                                <option value="failed">Failed</option>
                                <option value="takedown">Takedown</option>
                            </select>

                            <input
                                type="text"
                                value={bulkNotes}
                                onChange={(event) =>
                                    setBulkNotes(event.target.value)
                                }
                                placeholder="Optional note for selected DSPs..."
                                className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />

                            <button
                                type="button"
                                onClick={bulkUpdate}
                                disabled={
                                    bulkProcessing ||
                                    selectedDeliveryIds.length === 0
                                }
                                className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {bulkProcessing
                                    ? 'Updating...'
                                    : 'Update Selected'}
                            </button>
                        </div>
                    </div>
                </div>

                <div className="space-y-4">
                    {(release.store_deliveries ?? []).map(
                        (delivery) => (
                            <div
                                key={delivery.id}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div className="flex items-center gap-4">
                                        <input
                                            type="checkbox"
                                            checked={selectedDeliveryIds.includes(
                                                delivery.id
                                            )}
                                            onChange={() =>
                                                toggleDelivery(delivery.id)
                                            }
                                            className="rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                                        />

                                        <div className="flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                            {delivery.store?.logo_path ? (
                                                <img
                                                    src={`/storage/${delivery.store.logo_path}`}
                                                    alt=""
                                                    className="h-full w-full object-contain p-2"
                                                />
                                            ) : (
                                                <span className="font-bold text-slate-500">
                                                    {delivery.store?.name
                                                        ?.slice(0, 1)
                                                        .toUpperCase()}
                                                </span>
                                            )}
                                        </div>

                                        <div>
                                            <div className="font-semibold text-slate-900">
                                                {delivery.store?.name}
                                            </div>

                                            <div className="mt-1 text-xs text-slate-500">
                                                {delivery.store_release_id ||
                                                    'Store ID pending'}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3">
                                        <span
                                            className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${
                                                statusStyles[
                                                    delivery.status
                                                ]
                                            }`}
                                        >
                                            {delivery.status}
                                        </span>

                                        {delivery.store_url && (
                                            <a
                                                href={delivery.store_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700"
                                            >
                                                Open Store
                                            </a>
                                        )}

                                        <button
                                            type="button"
                                            onClick={() =>
                                                setEditing(delivery)
                                            }
                                            className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white"
                                        >
                                            Update
                                        </button>
                                    </div>
                                </div>

                                {(delivery.delivery_notes ||
                                    delivery.error_message) && (
                                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                                        {delivery.delivery_notes && (
                                            <div className="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                                                {delivery.delivery_notes}
                                            </div>
                                        )}

                                        {delivery.error_message && (
                                            <div className="rounded-xl bg-red-50 p-4 text-sm text-red-700">
                                                {delivery.error_message}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        )
                    )}
                </div>
            </div>

            {editing && (
                <DeliveryModal
                    delivery={editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </AdminLayout>
    );
}

function DeliveryModal({ delivery, onClose }) {
    const {
        data,
        setData,
        patch,
        processing,
        errors,
    } = useForm({
        status: delivery.status ?? 'pending',
        store_release_id:
            delivery.store_release_id ?? '',
        store_url: delivery.store_url ?? '',
        delivery_notes:
            delivery.delivery_notes ?? '',
        error_message:
            delivery.error_message ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        patch(`/delivery-status/${delivery.id}`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
            <form
                onSubmit={submit}
                className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl"
            >
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-slate-900">
                            Update {delivery.store?.name}
                        </h2>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg px-3 py-2 text-slate-500"
                    >
                        ✕
                    </button>
                </div>

                <div className="mt-6 grid gap-5 md:grid-cols-2">
                    <Field label="Status" error={errors.status}>
                        <select
                            value={data.status}
                            onChange={(event) =>
                                setData(
                                    'status',
                                    event.target.value
                                )
                            }
                            className="w-full rounded-xl border-slate-300"
                        >
                            <option value="pending">Pending</option>
                            <option value="processing">
                                Processing
                            </option>
                            <option value="delivered">
                                Delivered
                            </option>
                            <option value="live">Live</option>
                            <option value="failed">Failed</option>
                            <option value="takedown">
                                Takedown
                            </option>
                        </select>
                    </Field>

                    <Field label="Store Release ID">
                        <input
                            value={data.store_release_id}
                            onChange={(event) =>
                                setData(
                                    'store_release_id',
                                    event.target.value
                                )
                            }
                            className="w-full rounded-xl border-slate-300"
                        />
                    </Field>

                    <Field
                        label="Store URL"
                        error={errors.store_url}
                    >
                        <input
                            type="url"
                            value={data.store_url}
                            onChange={(event) =>
                                setData(
                                    'store_url',
                                    event.target.value
                                )
                            }
                            className="w-full rounded-xl border-slate-300"
                        />
                    </Field>
                </div>

                <div className="mt-5 grid gap-5 md:grid-cols-2">
                    <Field label="Delivery Notes">
                        <textarea
                            value={data.delivery_notes}
                            onChange={(event) =>
                                setData(
                                    'delivery_notes',
                                    event.target.value
                                )
                            }
                            className="min-h-32 w-full rounded-xl border-slate-300"
                        />
                    </Field>

                    <Field label="Error Message">
                        <textarea
                            value={data.error_message}
                            onChange={(event) =>
                                setData(
                                    'error_message',
                                    event.target.value
                                )
                            }
                            className="min-h-32 w-full rounded-xl border-slate-300"
                        />
                    </Field>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {processing
                            ? 'Saving...'
                            : 'Update Status'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="text-sm text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-xl font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function Field({ label, error, children }) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-slate-700">
                {label}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function StatusStat({ label, value, className = '' }) {
    return (
        <div
            className={`rounded-2xl border border-slate-200 p-4 ${className}`}
        >
            <div className="text-xs font-semibold uppercase tracking-wide opacity-70">
                {label}
            </div>

            <div className="mt-2 text-2xl font-bold">
                {value}
            </div>
        </div>
    );
}
