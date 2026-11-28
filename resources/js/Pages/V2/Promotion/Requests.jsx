import { Head, Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* W241 MIXXTUNE_PROMOTION - staff: promotion requests from labels */

const CHIP = { new: "bg-sky-100 text-sky-800", in_talk: "bg-amber-100 text-amber-800", accepted: "bg-emerald-100 text-emerald-800", done: "bg-slate-200 text-slate-700", declined: "bg-rose-100 text-rose-800" };

function Row({ r, statuses }) {
    const [status, setStatus] = useState(r.status);
    const [reply, setReply] = useState("");
    const [busy, setBusy] = useState(false);
    const save = () => {
        setBusy(true);
        router.post(`/admin/promotion/requests/${r.id}`, { status, staff_reply: reply }, { preserveScroll: true, onSuccess: () => setReply(""), onFinish: () => setBusy(false) });
    };
    return (
        <div className="px-4 py-4 sm:px-5">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="text-sm font-bold text-slate-900">{r.package}{r.release ? <> · <Link href={`/releases/${r.release_id}`} className="text-violet-700 hover:underline">{r.release}</Link></> : ""}</div>
                <span className={`rounded-full px-3 py-1 text-xs font-bold ${CHIP[r.status] ?? "bg-slate-100 text-slate-700"}`}>{statuses[r.status] ?? r.status}</span>
            </div>
            <div className="mt-1 text-xs text-slate-500">{r.user}{r.email ? ` · ${r.email}` : ""}{r.contact ? ` · ${r.contact}` : r.phone ? ` · ${r.phone}` : ""} · sent {r.sent_at}{r.budget ? ` · budget ${r.budget}` : ""}</div>
            <div className="mt-2 whitespace-pre-line rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-800">{r.message}</div>
            {r.reply && <div className="mt-2 whitespace-pre-line rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-900"><span className="font-bold">Our answer{r.replied_at ? ` (${r.replied_at})` : ""}: </span>{r.reply}</div>}
            <div className="mt-3 flex flex-wrap items-start gap-2">
                <select value={status} onChange={(e) => setStatus(e.target.value)} className="h-11 rounded-xl border border-slate-300 px-3 text-sm">
                    {Object.entries(statuses).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
                <textarea value={reply} onChange={(e) => setReply(e.target.value)} rows={2} maxLength={2000} placeholder="Write the details / price / next step for the label (optional)" className="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                <button type="button" disabled={busy} onClick={save} className="h-11 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-50">Save and tell the label</button>
            </div>
        </div>
    );
}

export default function Requests({ role = "super_admin", ready = true, rows = null, status = "", statuses = {}, counts = {} }) {
    const { flash = {}, errors = {} } = usePage().props;
    const list = rows?.data ?? [];
    const firstError = errors.status || errors.staff_reply;
    const tabs = [["", "All"], ...Object.entries(statuses)];

    return (
        <PanelLayout role={role} title="Promotion requests" subtitle="What labels have asked for, and your answer to each">
            <Head title="Promotion requests" />
            <div className="mx-auto w-full max-w-5xl space-y-4">
                {flash.success && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.success}</div>}
                {firstError && <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{firstError}</div>}
                {!ready && <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Promotion is not set up yet.</div>}

                <div className="flex flex-wrap items-center gap-2">
                    {tabs.map(([key, label]) => (
                        <Link key={key} href={key ? `/admin/promotion/requests?status=${key}` : "/admin/promotion/requests"} className={`rounded-full px-4 py-1.5 text-xs font-bold ${status === key ? "bg-slate-900 text-white" : "bg-slate-100 text-slate-700 hover:bg-slate-200"}`}>{label}{key && counts[key] ? ` (${counts[key]})` : ""}</Link>
                    ))}
                    {role === "super_admin" && <Link href="/admin/promotion/packages" className="ml-auto rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Packages</Link>}
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {list.length === 0 ? (
                        <div className="px-5 py-10 text-center text-sm text-slate-500">No request here.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">{list.map((r) => <Row key={`${r.id}-${r.status}-${r.replied_at}`} r={r} statuses={statuses} />)}</div>
                    )}
                    {rows && rows.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm">
                            {rows.prev_page_url ? <Link href={rows.prev_page_url} className="font-semibold text-violet-700">← Previous</Link> : <span />}
                            <span className="text-slate-500">Page {rows.current_page} of {rows.last_page}</span>
                            {rows.next_page_url ? <Link href={rows.next_page_url} className="font-semibold text-violet-700">Next →</Link> : <span />}
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
