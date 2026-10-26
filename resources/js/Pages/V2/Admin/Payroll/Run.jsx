import { Head, Link, router, usePage } from "@inertiajs/react";
import { AlertTriangle, ArrowLeft, CheckCircle2, Loader2, Trash2, Zap } from "lucide-react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/*
 * W115 MIXXTUNE_STAFF_SALARY
 * One month's salary sheet: change extra / cut / note while it is a draft; then Approve & pay
 * through Razorpay (NEFT) - or approve and mark each one paid by hand with the UTR.
 */
const inr = (v) => "₹" + new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));
const ITEM = {
    unpaid: ["To pay", "bg-slate-100 text-slate-600"],
    paying: ["Bank is paying", "bg-violet-50 text-violet-700"],
    paid: ["Paid", "bg-emerald-50 text-emerald-700"],
    failed: ["Not paid", "bg-red-50 text-red-700"],
    skipped: ["Left out", "bg-slate-100 text-slate-400"],
};
const cell = "h-9 w-28 rounded-lg border border-slate-300 bg-white px-2 text-right text-sm";

function Row({ it, draft, canHand }) {
    const [v, setV] = useState({ salary: it.salary, additions: it.additions || "", deductions: it.deductions || "", note: it.note || "" });
    const [utr, setUtr] = useState("");
    const [dirty, setDirty] = useState(false);
    const net = Math.round((Number(v.salary || 0) + Number(v.additions || 0) - Number(v.deductions || 0)) * 100) / 100;
    const change = (k, x) => {
        setV({ ...v, [k]: x });
        setDirty(true);
    };
    const save = (skip = it.status === "skipped") =>
        router.post(`/admin/salaries/items/${it.id}`, { ...v, additions: v.additions || 0, deductions: v.deductions || 0, skip: skip ? 1 : 0 }, { preserveScroll: true, onSuccess: () => setDirty(false) });
    const [label, cls] = ITEM[it.status] ?? [it.status, "bg-slate-100 text-slate-600"];

    return (
        <tr className={`align-top ${it.status === "skipped" ? "opacity-60" : ""}`}>
            <td className="px-4 py-3">
                <div className="font-semibold text-slate-900">{it.name}</div>
                <div className="text-[11px] text-slate-400">{it.designation || ""}</div>
                <div className={`font-mono text-[11px] ${it.bank_ready || it.status === "paid" ? "text-slate-400" : "font-semibold text-red-600"}`}>{it.bank_ready || it.status === "paid" ? it.account : "no bank details"}</div>
            </td>
            {draft ? (
                <>
                    <td className="px-2 py-3"><input type="number" min="0" step="0.01" value={v.salary} onChange={(e) => change("salary", e.target.value)} className={cell} aria-label="Salary" /></td>
                    <td className="px-2 py-3"><input type="number" min="0" step="0.01" value={v.additions} onChange={(e) => change("additions", e.target.value)} placeholder="0" className={cell} aria-label="Extra" /></td>
                    <td className="px-2 py-3"><input type="number" min="0" step="0.01" value={v.deductions} onChange={(e) => change("deductions", e.target.value)} placeholder="0" className={cell} aria-label="Cut" /></td>
                </>
            ) : (
                <>
                    <td className="px-4 py-3 text-right">{inr(it.salary)}</td>
                    <td className="px-4 py-3 text-right text-emerald-700">{it.additions ? `+${inr(it.additions)}` : "—"}</td>
                    <td className="px-4 py-3 text-right text-red-600">{it.deductions ? `−${inr(it.deductions)}` : "—"}</td>
                </>
            )}
            <td className="px-4 py-3 text-right font-black text-slate-900">{inr(draft ? net : it.net)}</td>
            <td className="px-4 py-3">
                {draft ? (
                    <input value={v.note} onChange={(e) => change("note", e.target.value)} placeholder="note (e.g. bonus, leave cut)" maxLength={255} className="h-9 w-full min-w-[160px] rounded-lg border border-slate-300 bg-white px-2 text-sm" />
                ) : (
                    <span className="text-xs text-slate-600">{it.note || ""}</span>
                )}
            </td>
            <td className="px-4 py-3">
                <span className={`inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ${cls}`}>
                    {it.status === "paying" && <Loader2 className="h-3 w-3 animate-spin" />}
                    {label}
                </span>
                {it.utr && <div className="mt-1 font-mono text-[11px] text-slate-500">UTR {it.utr}{it.paid_how === "manual" ? " (by hand)" : ""}</div>}
                {it.problem && it.status !== "paid" && <div className="mt-1 max-w-[220px] text-[11px] text-red-600">{it.problem}</div>}
            </td>
            <td className="px-4 py-3 text-right">
                {draft && (
                    <div className="flex flex-col items-end gap-1">
                        {dirty && (
                            <button type="button" onClick={() => save()} className="rounded-lg bg-violet-600 px-3 py-1 text-xs font-semibold text-white">
                                Save
                            </button>
                        )}
                        <button type="button" onClick={() => save(it.status !== "skipped")} className="text-[11px] font-semibold text-slate-500 underline">
                            {it.status === "skipped" ? "Put back in" : "Leave out this month"}
                        </button>
                    </div>
                )}
                {canHand && ["unpaid", "failed"].includes(it.status) && (
                    <div className="flex items-center justify-end gap-1">
                        <input value={utr} onChange={(e) => setUtr(e.target.value.trim())} placeholder="UTR" className="h-8 w-28 rounded-lg border border-slate-300 px-2 font-mono text-xs" />
                        <button type="button" disabled={utr.length < 3} onClick={() => router.post(`/admin/salaries/items/${it.id}/paid`, { utr }, { preserveScroll: true })} className="h-8 whitespace-nowrap rounded-lg border border-slate-300 px-2 text-xs font-semibold text-slate-700 disabled:opacity-40">
                            Paid by hand
                        </button>
                    </div>
                )}
            </td>
        </tr>
    );
}

export default function Run({ run = {}, items = [], razorpayReady = false, live = false }) {
    const { flash = {}, errors = {} } = usePage().props;
    const [busy, setBusy] = useState(false);
    const draft = run.status === "draft";
    const toPay = items.filter((i) => ["unpaid", "failed"].includes(i.status));
    const toPayTotal = toPay.reduce((a, i) => a + Number(i.net || 0), 0);
    const bad = errors.razorpay || errors.item || errors.run || errors.utr || errors.deductions;

    const pay = () => {
        if (!window.confirm(`Send ${toPay.length} salary payment(s), ${inr(toPayTotal)} in total, by NEFT through Razorpay${live ? "" : " (TEST mode)"}?\n\nThe money leaves the RazorpayX account right away.`)) return;
        setBusy(true);
        router.post(`/admin/salaries/${run.id}/pay`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    return (
        <PanelLayout role="super_admin" title={`Salary ${run.label}`} subtitle="Check, approve and pay">
            <Head title={`Salary ${run.label}`} />
            <Link href="/admin/salaries" className="mb-3 inline-flex items-center gap-1 text-xs font-semibold text-slate-500">
                <ArrowLeft className="h-3.5 w-3.5" /> Staff &amp; Salary
            </Link>

            {flash.success && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4 shrink-0" /> {flash.success}
                </div>
            )}
            {bad && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <AlertTriangle className="h-4 w-4 shrink-0" /> {bad}
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-3">
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-xs font-semibold text-slate-500">Total this month</div>
                    <div className="mt-1 text-2xl font-black text-slate-900">{inr(run.total)}</div>
                    <div className="text-xs text-slate-500">{items.filter((i) => i.status !== "skipped").length} people</div>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-xs font-semibold text-slate-500">Paid</div>
                    <div className="mt-1 text-2xl font-black text-emerald-700">{inr(run.paid)}</div>
                    <div className="text-xs text-slate-500">{run.approved_at ? `approved ${run.approved_at}` : "not approved yet"}</div>
                </div>
                <div className="flex flex-col justify-center gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    {run.status === "done" ? (
                        <div className="flex items-center gap-2 text-sm font-bold text-emerald-700">
                            <CheckCircle2 className="h-5 w-5" /> Everyone is paid
                        </div>
                    ) : (
                        <>
                            {toPay.length > 0 && razorpayReady && (
                                <button type="button" disabled={busy} onClick={pay} className="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-violet-700 px-4 text-sm font-semibold text-white disabled:opacity-50">
                                    {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Zap className="h-4 w-4" />} {draft ? "Approve & pay" : "Pay"} {inr(toPayTotal)} via Razorpay
                                </button>
                            )}
                            {draft && (
                                <button type="button" onClick={() => router.post(`/admin/salaries/${run.id}/approve`, {}, { preserveScroll: true })} className="h-9 rounded-xl border border-slate-300 px-4 text-xs font-semibold text-slate-700">
                                    Approve only (pay by hand)
                                </button>
                            )}
                            {!razorpayReady && (
                                <p className="text-[11px] text-slate-500">
                                    Razorpay not connected - <Link href="/admin/razorpay" className="font-semibold text-violet-700 underline">connect</Link>, or approve and mark paid by hand.
                                </p>
                            )}
                        </>
                    )}
                </div>
            </div>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                {draft && <p className="border-b border-slate-100 bg-amber-50 px-4 py-2.5 text-xs text-amber-900">Draft: change salary, extra (bonus) or cut (advance, leave) and press Save on that line. After approving nothing can be changed.</p>}
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[960px] text-left text-sm">
                        <thead className="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                            <tr>
                                <th className="px-4 py-2">Person</th>
                                <th className="px-4 py-2 text-right">Salary</th>
                                <th className="px-4 py-2 text-right">Extra</th>
                                <th className="px-4 py-2 text-right">Cut</th>
                                <th className="px-4 py-2 text-right">To pay</th>
                                <th className="px-4 py-2">Note</th>
                                <th className="px-4 py-2">Status</th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.map((it) => (
                                <Row key={`${it.id}-${it.status}-${it.net}`} it={it} draft={draft} canHand={!draft && run.status !== "done"} />
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            {draft && (
                <button type="button" onClick={() => window.confirm(`Delete the draft sheet of ${run.label}?`) && router.delete(`/admin/salaries/${run.id}`)} className="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-red-600">
                    <Trash2 className="h-3.5 w-3.5" /> Delete this draft sheet
                </button>
            )}
        </PanelLayout>
    );
}
