import { Head, router, usePage } from "@inertiajs/react";
import { AlertTriangle, CheckCircle2, ShieldCheck, UserCheck } from "lucide-react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/*
 * W118 MIXXTUNE_MAKER_CHECKER
 * Second approval: when ON, money actions wait for a "yes" from another Super Admin.
 */
const STATUS = {
    open: ["Waiting", "bg-amber-50 text-amber-800"],
    approved: ["Approved - not used yet", "bg-emerald-50 text-emerald-700"],
    used: ["Done", "bg-slate-100 text-slate-600"],
    rejected: ["Rejected", "bg-red-50 text-red-700"],
};

function Row({ r }) {
    const [note, setNote] = useState("");
    const decide = (decision) =>
        window.confirm(`${decision === "approve" ? "Approve" : "Reject"}: ${r.what}?`) && router.post(`/admin/approvals/${r.id}`, { decision, note }, { preserveScroll: true });
    const [label, cls] = r.expired ? ["Approval ran out", "bg-slate-100 text-slate-500"] : STATUS[r.status] ?? [r.status, "bg-slate-100 text-slate-600"];

    return (
        <li className="px-4 py-3">
            <div className="flex flex-wrap items-start gap-2">
                <div className="min-w-0 flex-1">
                    <div className="font-semibold text-slate-900">{r.what}</div>
                    <div className="mt-0.5 text-xs text-slate-500">
                        asked by {r.requested ?? "—"} · {r.at}
                        {r.decided && ` · ${r.status === "rejected" ? "rejected" : "approved"} by ${r.decided} ${r.decided_at ?? ""}`}
                        {r.used && ` · done by ${r.used}`}
                        {r.note && ` · "${r.note}"`}
                    </div>
                </div>
                <span className={`whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ${cls}`}>{label}</span>
            </div>
            {r.status === "open" &&
                (r.mine ? (
                    <p className="mt-2 text-xs text-slate-500">You asked for this - another Super Admin has to decide it.</p>
                ) : (
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <input value={note} onChange={(e) => setNote(e.target.value)} placeholder="Note (optional)" maxLength={500} className="h-9 min-w-[200px] flex-1 rounded-lg border border-slate-300 px-3 text-sm" />
                        <button type="button" onClick={() => decide("approve")} className="h-9 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white">
                            Approve
                        </button>
                        <button type="button" onClick={() => decide("reject")} className="h-9 rounded-lg border border-red-300 bg-white px-4 text-sm font-semibold text-red-700">
                            Reject
                        </button>
                        {r.url && (
                            <a href={r.url} className="text-xs font-semibold text-violet-700 underline">
                                Open the page
                            </a>
                        )}
                    </div>
                ))}
        </li>
    );
}

export default function Index({ on = false, superAdmins = 1, validHours = 24, rows = [] }) {
    const { flash = {}, errors = {} } = usePage().props;
    const open = rows.filter((r) => r.status === "open");

    return (
        <PanelLayout role="super_admin" title="Second approval" subtitle="Money actions need a yes from another Super Admin">
            <Head title="Second approval" />
            {flash.success && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4 shrink-0" /> {flash.success}
                </div>
            )}
            {errors.approval && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <AlertTriangle className="h-4 w-4 shrink-0" /> {errors.approval}
                </div>
            )}

            <section className={`rounded-2xl border p-4 shadow-sm sm:p-5 ${on ? "border-emerald-200 bg-emerald-50" : "border-slate-200 bg-white"}`}>
                <div className="flex flex-wrap items-center gap-3">
                    <ShieldCheck className={`h-6 w-6 ${on ? "text-emerald-700" : "text-slate-400"}`} />
                    <div className="min-w-0 flex-1">
                        <div className="text-lg font-black text-slate-900">Second approval is {on ? "ON" : "OFF"}</div>
                        <p className="text-sm text-slate-600">
                            When ON: Mark Paid, Pay via Razorpay, salaries, wallet adjustments, recoupment, statement / report approval, label % changes and Razorpay settings wait until <b>another</b> Super Admin presses Approve here. Then the same button works once, within {validHours} hours (after the e-mailed code as now).
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                            Active Super Admins: <b>{superAdmins}</b>
                            {superAdmins < 2 && " - a second Super Admin is needed before it can be switched on."}
                        </p>
                    </div>
                    <button
                        type="button"
                        disabled={!on && superAdmins < 2}
                        onClick={() => window.confirm(on ? "Switch Second approval OFF? (the other Super Admin must approve this too)" : "Switch Second approval ON?") && router.post("/admin/approvals/switch", { on: on ? 0 : 1 }, { preserveScroll: true })}
                        className={`h-11 rounded-xl px-5 text-sm font-bold disabled:opacity-40 ${on ? "border border-slate-300 bg-white text-slate-700" : "bg-emerald-600 text-white"}`}
                    >
                        {on ? "Switch OFF" : "Switch ON"}
                    </button>
                </div>
            </section>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
                    <UserCheck className="h-4 w-4" />
                    <h2 className="text-sm font-bold text-slate-900">Requests</h2>
                    {open.length > 0 && <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">{open.length} waiting</span>}
                </div>
                {rows.length === 0 ? (
                    <p className="px-5 py-10 text-center text-sm text-slate-500">No requests yet.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {rows.map((r) => (
                            <Row key={r.id} r={r} />
                        ))}
                    </ul>
                )}
            </section>
        </PanelLayout>
    );
}
