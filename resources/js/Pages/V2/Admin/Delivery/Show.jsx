import { Head, Link, router } from "@inertiajs/react";

import { useEffect, useMemo, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

const transitionMap = {
    pending: ["processing", "failed"],

    processing: ["delivered", "failed"],

    delivered: ["live", "failed", "takedown_requested"],

    live: ["takedown_requested", "failed"],

    failed: ["processing"],

    takedown_requested: ["taken_down", "live"],

    taken_down: [],
};

const statusLabels = {
    processing: "Processing",
    delivered: "Delivered",
    live: "Live",
    failed: "Failed",
    takedown_requested: "Takedown Requested",
    taken_down: "Taken Down",
};

const statusClasses = {
    pending: "bg-slate-100 text-slate-700",

    processing: "bg-blue-100 text-blue-700",

    delivered: "bg-indigo-100 text-indigo-700",

    live: "bg-emerald-100 text-emerald-700",

    failed: "bg-red-100 text-red-700",

    takedown_requested: "bg-amber-100 text-amber-700",

    taken_down: "bg-slate-800 text-white",
};

export default function Show({
    role = "admin",
    release,
    deliveries = [],
    summary = {},
}) {
    const [selected, setSelected] = useState([]);

    const [bulkStatus, setBulkStatus] = useState("processing");

    const [bulkNote, setBulkNote] = useState("");

    const [bulkError, setBulkError] = useState("");

    const [processing, setProcessing] = useState(false);

    const allSelected =
        deliveries.length > 0 && selected.length === deliveries.length;

    const selectedDeliveries = useMemo(
        () => deliveries.filter((delivery) => selected.includes(delivery.id)),
        [deliveries, selected],
    );

    const availableBulkStatuses = useMemo(() => {
        if (selectedDeliveries.length === 0) {
            return [];
        }

        const [first, ...rest] = selectedDeliveries;

        const firstActions = transitionMap[first.status] ?? [];

        return firstActions.filter((status) =>
            rest.every((delivery) =>
                (transitionMap[delivery.status] ?? []).includes(status),
            ),
        );
    }, [selectedDeliveries]);

    const hasValidBulkAction = availableBulkStatuses.length > 0;

    useEffect(() => {
        if (availableBulkStatuses.length === 0) {
            if (bulkStatus !== "") {
                setBulkStatus("");
            }

            return;
        }

        if (!availableBulkStatuses.includes(bulkStatus)) {
            setBulkStatus(availableBulkStatuses[0]);
        }
    }, [availableBulkStatuses, bulkStatus]);

    const toggleAll = () => {
        setSelected(allSelected ? [] : deliveries.map((item) => item.id));
    };

    const toggleOne = (id) => {
        setSelected((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
    };

    const bulkUpdate = () => {
        if (selected.length === 0) {
            return;
        }

        setProcessing(true);

        router.patch(
            `/v2/admin/distribution/${release.id}/bulk`,
            {
                delivery_ids: selected,

                status: bulkStatus,

                delivery_note: bulkNote,

                error_message: bulkStatus === "failed" ? bulkError : null,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setSelected([]);
                    setBulkNote("");
                    setBulkError("");
                },

                onFinish: () => setProcessing(false),
            },
        );
    };

    const individualActions = (delivery) => {
        const transitions = {
            pending: ["processing", "failed"],
            processing: ["delivered", "failed"],
            delivered: ["live", "failed", "takedown_requested"],
            live: ["takedown_requested", "failed"],
            failed: ["processing"],
            takedown_requested: ["taken_down", "live"],
            taken_down: [],
        };

        return transitions[delivery.status] ?? [];
    };

    const actionLabel = (status) => {
        const labels = {
            processing: "Start Processing",
            delivered: "Mark Delivered",
            live: "Mark Live",
            failed: "Mark Failed",
            takedown_requested: "Request Takedown",
            taken_down: "Mark Taken Down",
        };

        return labels[status] ?? status;
    };

    const submitIndividualAction = (delivery, status) => {
        const form = document.createElement("form");

        form.method = "POST";
        form.action = `/v2/admin/deliveries/${delivery.id}`;

        const method = document.createElement("input");
        method.type = "hidden";
        method.name = "_method";
        method.value = "PATCH";

        const csrf = document.createElement("input");
        csrf.type = "hidden";
        csrf.name = "_token";
        csrf.value =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content") ?? "";

        const statusInput = document.createElement("input");
        statusInput.type = "hidden";
        statusInput.name = "status";
        statusInput.value = status;

        form.appendChild(method);
        form.appendChild(csrf);
        form.appendChild(statusInput);

        if (status === "failed") {
            const reason = window.prompt(
                "Enter failure reason:"
            );

            if (!reason || reason.trim().length < 3) {
                return;
            }

            const errorInput = document.createElement("input");
            errorInput.type = "hidden";
            errorInput.name = "error_message";
            errorInput.value = reason.trim();

            form.appendChild(errorInput);
        }

        if (
            status === "takedown_requested"
            || status === "taken_down"
        ) {
            const note = window.prompt(
                "Enter delivery note (optional):"
            );

            if (note) {
                const noteInput = document.createElement("input");
                noteInput.type = "hidden";
                noteInput.name = "delivery_note";
                noteInput.value = note.trim();

                form.appendChild(noteInput);
            }
        }

        document.body.appendChild(form);
        form.submit();
    };

    return (
        <PanelLayout role={role} title="DSP Delivery" subtitle={release.title}>
            <Head title={`DSP Delivery - ${release.title}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/v2/admin/distribution"
                        className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                    >
                        ← Distribution Queue
                    </Link>

                    <span className="rounded-full bg-blue-100 px-4 py-2 text-sm font-semibold capitalize text-blue-700">
                        {release.status.replaceAll("_", " ")}
                    </span>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    {[
                        "total",
                        "pending",
                        "processing",
                        "delivered",
                        "live",
                        "failed",
                    ].map((status) => (
                        <div
                            key={status}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div className="text-xs font-semibold uppercase text-slate-500">
                                {status}
                            </div>

                            <div className="mt-2 text-3xl font-bold text-slate-900">
                                {summary[status] ?? 0}
                            </div>
                        </div>
                    ))}
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Bulk Update
                    </h2>

                    <div className="mt-4 grid gap-3 lg:grid-cols-4">
                        <select
                            value={bulkStatus}
                            onChange={(event) =>
                                setBulkStatus(event.target.value)
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            {availableBulkStatuses.length > 0 ? (
                                availableBulkStatuses.map((status) => (
                                    <option key={status} value={status}>
                                        {statusLabels[status] ??
                                            status.replaceAll("_", " ")}
                                    </option>
                                ))
                            ) : (
                                <option value="">
                                    {selected.length === 0
                                        ? "Select DSPs first"
                                        : "No common valid action"}
                                </option>
                            )}
                        </select>

                        <input
                            value={bulkNote}
                            onChange={(event) =>
                                setBulkNote(event.target.value)
                            }
                            placeholder="Delivery note"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <input
                            value={bulkError}
                            onChange={(event) =>
                                setBulkError(event.target.value)
                            }
                            disabled={bulkStatus !== "failed"}
                            placeholder="Failure reason"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm disabled:bg-slate-100"
                        />

                        <button
                            type="button"
                            disabled={
                                processing ||
                                selected.length === 0 ||
                                !hasValidBulkAction ||
                                !availableBulkStatuses.includes(bulkStatus) ||
                                (bulkStatus === "failed" &&
                                    bulkError.trim().length < 3)
                            }
                            onClick={bulkUpdate}
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing
                                ? "Updating..."
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
                                            checked={allSelected}
                                            onChange={toggleAll}
                                        />
                                    </th>

                                    {[
                                        "Store",
                                        "Status",
                                        "Reference",
                                        "Note",
                                        "Error",
                                        "Updated",
                                        "Actions",
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {deliveries.length > 0 ? (
                                    deliveries.map((delivery) => (
                                        <tr key={delivery.id}>
                                            <td className="px-5 py-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selected.includes(
                                                        delivery.id,
                                                    )}
                                                    onChange={() =>
                                                        toggleOne(delivery.id)
                                                    }
                                                />
                                            </td>

                                            <td className="px-5 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                                                        {delivery.store
                                                            ?.logo_path ? (
                                                            <img
                                                                src={
                                                                    delivery.store.logo_path.startsWith(
                                                                        "http",
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
                                                                {delivery.store?.name?.charAt(
                                                                    0,
                                                                ) ?? "?"}
                                                            </span>
                                                        )}
                                                    </div>

                                                    <span className="font-semibold text-slate-900">
                                                        {delivery.store?.name ||
                                                            "Unknown Store"}
                                                    </span>
                                                </div>
                                            </td>

                                            <td className="px-5 py-4">
                                                <span
                                                    className={[
                                                        "rounded-full px-3 py-1 text-xs font-semibold capitalize",
                                                        statusClasses[
                                                            delivery.status
                                                        ] ??
                                                            statusClasses.pending,
                                                    ].join(" ")}
                                                >
                                                    {delivery.status.replaceAll(
                                                        "_",
                                                        " ",
                                                    )}
                                                </span>
                                            </td>

                                            <td className="px-5 py-4 text-sm text-slate-600">
                                                {delivery.external_reference ||
                                                    "—"}
                                            </td>

                                            <td className="max-w-64 px-5 py-4 text-sm text-slate-600">
                                                {delivery.delivery_note || "—"}
                                            </td>

                                            <td className="max-w-64 px-5 py-4 text-sm text-red-600">
                                                {delivery.error_message || "—"}
                                            </td>

                                            <td className="px-5 py-4 text-sm text-slate-500">
                                                {delivery.updated_at || "—"}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {individualActions(
                                                        delivery,
                                                    ).map((status) => (
                                                        <button
                                                            key={status}
                                                            type="button"
                                                            onClick={() => {
                                                                const label =
                                                                    actionLabel(
                                                                        status,
                                                                    );

                                                                if (
                                                                    window.confirm(
                                                                        `${label} for ${delivery.store?.name ?? "this store"}?`,
                                                                    )
                                                                ) {
                                                                    submitIndividualAction(
                                                                        delivery,
                                                                        status,
                                                                    );
                                                                }
                                                            }}
                                                            className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                        >
                                                            {actionLabel(status)}
                                                        </button>
                                                    ))}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="8"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            DSP deliveries have not been
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
