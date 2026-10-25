import { Head, Link, router, usePage } from "@inertiajs/react";

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
    readiness = null,
    history = [],
    canStart = false,
}) {
    /* W58: server messages, action window (note / reason / reference), start delivery */
    const { flash = {}, errors = {} } = usePage().props;
    const errorList = Object.entries(errors).filter(([k]) => k !== "action_otp");
    const [action, setAction] = useState(null);
    const [actionNote, setActionNote] = useState("");
    const [actionError, setActionError] = useState("");
    const [actionRef, setActionRef] = useState("");
    const [starting, setStarting] = useState(false);
    const fmt = (v) => (v ? new Date(v).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : "—");
    const startDelivery = () => {
        if (!window.confirm("Start delivery for this release? Store records are created" + (readiness?.notes?.length ? " and: " + readiness.notes.join(" ") : "."))) return;
        setStarting(true);
        router.post(`/admin/distribution/${release.id}/initialise`, {}, { preserveScroll: true, onFinish: () => setStarting(false) });
    };
    const sendAction = () => {
        if (!action) return;
        setProcessing(true);
        router.patch(
            `/admin/distribution/deliveries/${action.delivery.id}`,
            {
                status: action.status,
                delivery_note: actionNote.trim() || null,
                error_message: action.status === "failed" ? actionError.trim() : null,
                external_reference: actionRef.trim() || null,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    if (!page.props?.errors || Object.keys(page.props.errors).length === 0) setAction(null);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };
    const [selected, setSelected] = useState([]);

    /*
     * W99 MIXXTUNE_DELIVERY_BULK: tick stores (or use the quick picks), then one button per action.
     * Each action is sent only for the ticked stores that can take it; the rest are left as they are
     * (for example "Mark Delivered" on 20 ticked stores of which 2 are already delivered changes 18).
     */
    const [bulk, setBulk] = useState(null);
    const [bulkNote, setBulkNote] = useState("");
    const [bulkError, setBulkError] = useState("");
    const [bulkRef, setBulkRef] = useState("");

    const [processing, setProcessing] = useState(false);

    const allSelected =
        deliveries.length > 0 && selected.length === deliveries.length;

    const selectedDeliveries = useMemo(
        () => deliveries.filter((delivery) => selected.includes(delivery.id)),
        [deliveries, selected],
    );

    /* every action at least one ticked store can take, with the stores that can take it */
    const bulkOptions = useMemo(() => {
        const order = ["processing", "delivered", "live", "takedown_requested", "taken_down", "failed"];

        return order
            .map((status) => ({
                status,
                ids: selectedDeliveries.filter((d) => (transitionMap[d.status] ?? []).includes(status)).map((d) => d.id),
            }))
            .filter((o) => o.ids.length > 0);
    }, [selectedDeliveries]);

    const countBy = useMemo(() => {
        const c = {};
        deliveries.forEach((d) => {
            c[d.status] = (c[d.status] ?? 0) + 1;
        });
        return c;
    }, [deliveries]);

    const pickStatus = (status) => setSelected(deliveries.filter((d) => d.status === status).map((d) => d.id));

    const openBulk = (option) => {
        setBulkNote("");
        setBulkError("");
        setBulkRef("");
        setBulk(option);
    };

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
        if (!bulk || bulk.ids.length === 0) {
            return;
        }

        setProcessing(true);

        router.patch(
            `/admin/distribution/${release.id}/bulk`,
            {
                delivery_ids: bulk.ids,
                status: bulk.status,
                delivery_note: bulkNote.trim() || null,
                error_message: bulk.status === "failed" ? bulkError.trim() : null,
                external_reference: bulkRef.trim() || null,
            },
            {
                preserveScroll: true,

                onSuccess: (page) => {
                    if (!page.props?.errors || Object.keys(page.props.errors).length === 0) {
                        setBulk(null);
                        setSelected([]);
                    }
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
        setActionNote("");
        setActionError("");
        setActionRef(delivery.external_reference || "");
        setAction({ delivery, status });
    };

    return (
        <PanelLayout role={role} title="DSP Delivery" subtitle={release.title}>
            <Head title={`DSP Delivery - ${release.title}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/admin/distribution"
                        className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                    >
                        ← Distribution Queue
                    </Link>

                    <span className={`rounded-full px-4 py-2 text-sm font-semibold capitalize ${statusClasses[release.status] ?? "bg-blue-100 text-blue-700"}`}>
                        {release.status.replaceAll("_", " ")}
                    </span>
                </div>

                {flash.success && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-800">{flash.success}</div>}
                {errorList.length > 0 && (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800">
                        {errorList.map(([k, v]) => <div key={k}>{v}</div>)}
                    </div>
                )}

                {deliveries.length === 0 && readiness && (
                    <section className="rounded-2xl border border-violet-200 bg-violet-50/50 p-5">
                        <h2 className="text-base font-bold text-slate-900">Ready for stores?</h2>
                        <ul className="mt-2 space-y-1 text-sm">
                            {readiness.blocking.map((b) => <li key={b} className="font-semibold text-rose-700">✗ {b}</li>)}
                            {readiness.notes.map((n) => <li key={n} className="text-slate-600">• {n}</li>)}
                            {readiness.blocking.length === 0 && <li className="font-semibold text-emerald-700">✓ Poster, tracks and audio are in place.</li>}
                        </ul>
                        <button
                            type="button"
                            disabled={starting || !canStart || readiness.blocking.length > 0}
                            onClick={startDelivery}
                            className="mt-4 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {starting ? "Starting…" : "Start delivery"}
                        </button>
                        {!canStart && <p className="mt-2 text-xs text-slate-500">The release must be approved first.</p>}
                    </section>
                )}

                {action && (
                    <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4">
                        <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                            <h3 className="text-lg font-bold text-slate-900">{actionLabel(action.status)}</h3>
                            <p className="mt-1 text-sm text-slate-500">{action.delivery.store?.name} · {release.title}</p>
                            {["live", "takedown_requested", "taken_down"].includes(action.status) && (
                                <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">A 6-digit code is e-mailed to you before this is saved.</p>
                            )}
                            {action.status === "failed" && (
                                <label className="mt-3 block text-sm font-semibold text-slate-700">Failure reason (required)
                                    <textarea value={actionError} onChange={(e) => setActionError(e.target.value)} rows={2} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal" />
                                </label>
                            )}
                            <label className="mt-3 block text-sm font-semibold text-slate-700">Store reference / link (optional)
                                <input value={actionRef} onChange={(e) => setActionRef(e.target.value)} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal" />
                            </label>
                            <label className="mt-3 block text-sm font-semibold text-slate-700">Note (optional)
                                <textarea value={actionNote} onChange={(e) => setActionNote(e.target.value)} rows={2} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal" />
                            </label>
                            <div className="mt-4 flex justify-end gap-2">
                                <button type="button" disabled={processing} onClick={() => setAction(null)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                                <button
                                    type="button"
                                    disabled={processing || (action.status === "failed" && actionError.trim().length < 3)}
                                    onClick={sendAction}
                                    className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                                >
                                    {processing ? "Saving…" : "Save"}
                                </button>
                            </div>
                        </div>
                    </div>
                )}

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

                {deliveries.length > 0 && (
                    <section className="sticky top-2 z-30 rounded-2xl border border-violet-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">Bulk action</h2>
                                <p className="mt-0.5 text-sm text-slate-500">
                                    {selected.length === 0
                                        ? "Tick stores below (or pick a group here), then choose what to do with all of them at once."
                                        : `${selected.length} of ${deliveries.length} stores ticked.`}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-xs">
                                <span className="font-semibold text-slate-500">Tick:</span>
                                <QuickPick onClick={() => setSelected(deliveries.map((d) => d.id))} active={allSelected}>
                                    All ({deliveries.length})
                                </QuickPick>
                                {["pending", "processing", "delivered", "live", "failed", "takedown_requested"].map((s) =>
                                    countBy[s] ? (
                                        <QuickPick key={s} onClick={() => pickStatus(s)}>
                                            {(statusLabels[s] ?? "Pending")} ({countBy[s]})
                                        </QuickPick>
                                    ) : null,
                                )}
                                {selected.length > 0 && (
                                    <QuickPick onClick={() => setSelected([])}>Clear</QuickPick>
                                )}
                            </div>
                        </div>

                        <div className="mt-4 flex flex-wrap gap-2">
                            {selected.length === 0 && <span className="text-sm text-slate-400">No stores ticked yet.</span>}
                            {bulkOptions.map((option) => {
                                const skipped = selected.length - option.ids.length;

                                return (
                                    <button
                                        key={option.status}
                                        type="button"
                                        disabled={processing}
                                        onClick={() => openBulk(option)}
                                        title={skipped > 0 ? `${skipped} ticked store(s) cannot take this step and are left as they are` : undefined}
                                        className={`rounded-xl px-4 py-2.5 text-sm font-semibold disabled:opacity-50 ${
                                            option.status === "failed"
                                                ? "border border-red-200 text-red-700 hover:bg-red-50"
                                                : option.status === "delivered" || option.status === "live"
                                                  ? "bg-violet-600 text-white hover:bg-violet-700"
                                                  : "border border-slate-300 text-slate-700 hover:bg-slate-50"
                                        }`}
                                    >
                                        {actionLabel(option.status)} · {option.ids.length}
                                        {skipped > 0 && <span className="ml-1 font-normal opacity-75">(skip {skipped})</span>}
                                    </button>
                                );
                            })}
                        </div>
                    </section>
                )}

                {bulk && (
                    <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4">
                        <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                            <h3 className="text-lg font-bold text-slate-900">
                                {actionLabel(bulk.status)} · {bulk.ids.length} {bulk.ids.length === 1 ? "store" : "stores"}
                            </h3>
                            <p className="mt-1 text-sm text-slate-500">{release.title}</p>
                            <div className="mt-3 max-h-32 overflow-y-auto rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                {deliveries.filter((d) => bulk.ids.includes(d.id)).map((d) => d.store?.name || "Store").join(", ")}
                            </div>
                            {selected.length > bulk.ids.length && (
                                <p className="mt-2 text-xs text-slate-500">
                                    {selected.length - bulk.ids.length} other ticked store(s) cannot take this step and stay as they are.
                                </p>
                            )}
                            {["live", "takedown_requested", "taken_down"].includes(bulk.status) && (
                                <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">A 6-digit code is e-mailed to you before this is saved.</p>
                            )}
                            {bulk.status === "failed" && (
                                <label className="mt-3 block text-sm font-semibold text-slate-700">Failure reason (required)
                                    <textarea value={bulkError} onChange={(e) => setBulkError(e.target.value)} rows={2} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal" />
                                </label>
                            )}
                            <label className="mt-3 block text-sm font-semibold text-slate-700">Store reference / link (optional, same for all)
                                <input value={bulkRef} onChange={(e) => setBulkRef(e.target.value)} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal" />
                            </label>
                            <label className="mt-3 block text-sm font-semibold text-slate-700">Note (optional)
                                <textarea value={bulkNote} onChange={(e) => setBulkNote(e.target.value)} rows={2} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal" />
                            </label>
                            <div className="mt-4 flex justify-end gap-2">
                                <button type="button" disabled={processing} onClick={() => setBulk(null)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                                <button
                                    type="button"
                                    disabled={processing || (bulk.status === "failed" && bulkError.trim().length < 3)}
                                    onClick={bulkUpdate}
                                    className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                                >
                                    {processing ? "Saving…" : `Yes, ${actionLabel(bulk.status).toLowerCase()} for ${bulk.ids.length}`}
                                </button>
                            </div>
                        </div>
                    </div>
                )}

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
                                                {fmt(delivery.updated_at)}
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

                                                                void label;
                                                                submitIndividualAction(
                                                                    delivery,
                                                                    status,
                                                                );
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
                                            Delivery has not started yet - see "Ready for stores?" above.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {/* W58: who changed which store, when */}
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">History</h2>
                    {history.length === 0 ? (
                        <p className="mt-2 text-sm text-slate-500">No store status changes yet.</p>
                    ) : (
                        <div className="mt-3 divide-y divide-slate-100">
                            {history.map((h) => (
                                <div key={h.id} className="grid gap-1 py-2 text-sm sm:grid-cols-[170px_1fr_1fr]">
                                    <div className="text-slate-500">{h.at}<div className="text-xs">by {h.by}</div></div>
                                    <div className="font-semibold text-slate-900">{h.store}: {(h.from || "").replaceAll("_", " ")} → {(h.to || "").replaceAll("_", " ")}</div>
                                    <div className="text-xs text-slate-500">{h.note || ""}</div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}

function QuickPick({ children, onClick, active = false }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-full border px-3 py-1.5 font-semibold ${active ? "border-violet-600 bg-violet-600 text-white" : "border-slate-300 text-slate-700 hover:bg-slate-50"}`}
        >
            {children}
        </button>
    );
}
