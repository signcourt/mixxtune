import { Head, Link, router, usePage } from "@inertiajs/react";
import { CheckCircle2, Clock3, Download, FileText, Loader2, Search, WalletCards } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import StatementEmailPanel from "@/V2/Admin/StatementEmailPanel";

/* MIXXTUNE_STATEMENTS_V2 (W43) */

const money = (v, c = "INR") => {
    try {
        return new Intl.NumberFormat("en-IN", { style: "currency", currency: c || "INR", maximumFractionDigits: 2 }).format(Number(v ?? 0));
    } catch {
        return `${c} ${Number(v ?? 0).toFixed(2)}`;
    }
};

/* statement_month comes as "2026-07" or "2026-07-01" (the old code broke on the second) */
const monthName = (v) => {
    if (!v) return "Unknown month";
    const m = String(v).match(/^(\d{4})-(\d{2})/);
    if (!m) return String(v);
    return new Intl.DateTimeFormat("en-IN", { month: "long", year: "numeric" }).format(new Date(Number(m[1]), Number(m[2]) - 1, 1));
};

const day = (v) => {
    if (!v) return null;
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? String(v) : new Intl.DateTimeFormat("en-IN", { day: "2-digit", month: "short", year: "numeric" }).format(d);
};

const STATUS = {
    pending: ["Waiting for approval", "bg-amber-50 text-amber-800"],
    generated: ["Generated", "bg-amber-50 text-amber-800"],
    approved: ["Approved · pending in wallet", "bg-blue-50 text-blue-700"],
    available: ["Available to withdraw", "bg-emerald-50 text-emerald-700"],
    paid: ["Paid", "bg-green-50 text-green-700"],
    cancelled: ["Cancelled", "bg-rose-50 text-rose-700"],
};

export default function Index({ role = "artist", statements = {}, filters = {}, summary = {}, months = [], pipeline = [] }) {
    const page = usePage().props;
    const flash = page.flash ?? {};
    const errors = page.errors ?? {};
    const staff = role === "admin" || role === "super_admin";
    const rows = statements.data ?? [];
    const [search, setSearch] = useState(filters.search ?? "");
    const [busy, setBusy] = useState(null);
    const first = useRef(true);

    const go = (patch) =>
        router.get(window.location.pathname, { search: filters.search || undefined, status: filters.status || undefined, month: filters.month || undefined, ...patch, page: undefined }, { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => go({ search: search.trim() || undefined }), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const act = (s, kind) => {
        const text = kind === "approve"
            ? `Approve ${monthName(s.statement_month)} for ${s.owner_name}?\n${money(s.net_payable, s.currency)} goes to the owner's PENDING wallet balance.\nAn e-mailed code is needed next.`
            : `Make ${money(s.net_payable, s.currency)} of ${s.owner_name} AVAILABLE to withdraw?\nAn e-mailed code is needed next.`;
        if (!window.confirm(text)) return;
        setBusy(`${kind}${s.id}`);
        router.post(`/v2/admin/finance/statements/${s.id}/${kind}`, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    const filtered = !!(filters.search || filters.status || filters.month);
    const title = role === "artist" ? "My Statements" : role === "label" ? "Label Statements" : "Royalty Statements";
    const actionError = errors.approve || errors.available;

    return (
        <PanelLayout role={role} title={title} subtitle={staff ? "Every owner's monthly statement: check, approve, download, e-mail" : "Your monthly royalty statements and PDFs"}>
            <Head title={title} />

            {role === "super_admin" && <div className="mb-4"><StatementEmailPanel /></div>}

            {flash.success && (
                <div className="mb-3 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4" /> {flash.success}
                </div>
            )}
            {actionError && <div className="mb-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{actionError}</div>}

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat label="Gross earnings" value={money(summary.gross)} hint={`${Number(summary.count ?? 0).toLocaleString("en-IN")} statement(s)${filtered ? " · filtered" : ""}`} />
                <Stat label="Deductions" value={money(summary.deductions)} hint="commission, tax, advance recovery" />
                <Stat label="Net payable" value={money(summary.net)} tone="text-violet-700" />
                {staff ? (
                    <Stat label="Waiting for approval" value={`${summary.pending_count ?? 0}`} tone={summary.pending_count ? "text-amber-700" : "text-slate-900"} hint={summary.pending_count ? `${money(summary.pending_net)} not in wallets yet` : "nothing waiting"} />
                ) : (
                    <Stat label="Wallet available" value={money(summary.wallet)} tone="text-emerald-700" hint={<Link href="/v2/wallet" className="font-semibold text-violet-700">Open wallet →</Link>} />
                )}
            </div>

            {staff && summary.pending_count > 0 && filters.status !== "pending" && (
                <button type="button" onClick={() => go({ status: "pending" })} className="mt-3 w-full rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-left text-sm text-amber-800">
                    {summary.pending_count} statement(s) are waiting for approval — owners don't see this money yet. <b>Show them →</b>
                </button>
            )}

            {staff && pipeline.length > 0 && <Pipeline items={pipeline} />}

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex flex-col gap-2 border-b border-slate-100 p-3 lg:flex-row lg:items-center">
                    <div className="relative min-w-0 flex-1">
                        <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={staff ? "Label, artist, month or statement ID" : "Month or statement ID"} className="h-10 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm" />
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:flex">
                        <select value={filters.month || ""} onChange={(e) => go({ month: e.target.value || undefined })} className="h-10 min-w-0 rounded-xl border border-slate-300 bg-white pl-3 pr-8 text-sm">
                            <option value="">All months</option>
                            {months.map((m) => <option key={m} value={m}>{monthName(m)}</option>)}
                        </select>
                        <select value={filters.status || ""} onChange={(e) => go({ status: e.target.value || undefined })} className="h-10 min-w-0 rounded-xl border border-slate-300 bg-white pl-3 pr-8 text-sm">
                            <option value="">All statuses</option>
                            <option value="pending">Waiting for approval</option>
                            <option value="approved">Approved</option>
                            <option value="available">Available</option>
                            <option value="paid">Paid</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        {filtered && (
                            <button type="button" onClick={() => { setSearch(""); router.get(window.location.pathname, {}, { preserveState: true, replace: true }); }} className="col-span-2 h-10 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700">
                                Clear
                            </button>
                        )}
                    </div>
                </div>

                {rows.length === 0 ? (
                    <div className="px-5 py-14 text-center">
                        <FileText className="mx-auto h-10 w-10 text-slate-300" />
                        <div className="mt-3 text-sm font-semibold text-slate-700">{filtered ? "No statement matches" : "No statements yet"}</div>
                        <div className="mx-auto mt-1 max-w-md text-xs text-slate-500">
                            {filtered
                                ? "Clear the filters to see every statement."
                                : staff
                                  ? "Statements are made when a monthly sales report is uploaded, reviewed, approved and published (Financial → Revenue Imports). See \"Monthly reports → statements\" above for where each report is."
                                  : "Your statement appears here after the monthly store report is processed and approved."}
                        </div>
                        {!filtered && staff && (
                            <Link href="/v2/admin/reports/imports" className="mt-3 inline-flex h-9 items-center rounded-lg bg-violet-600 px-4 text-xs font-semibold text-white">Open Revenue Imports</Link>
                        )}
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {rows.map((s) => {
                            const [label, cls] = STATUS[s.status] ?? [s.status, "bg-slate-100 text-slate-600"];
                            const ded = Number(s.commission_amount ?? 0) + Number(s.tax_amount ?? 0) + Number(s.other_deductions ?? 0);
                            const dates = [
                                s.approved_at && `approved ${day(s.approved_at)}`,
                                s.available_at && `available ${day(s.available_at)}`,
                                s.paid_at && `paid ${day(s.paid_at)}`,
                            ].filter(Boolean).join(" · ");
                            return (
                                <li key={s.id} className="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-sm font-bold text-slate-900">{monthName(s.statement_month)}</span>
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${cls}`}>{label}</span>
                                        </div>
                                        {staff && <div className="mt-0.5 truncate text-sm text-slate-700">{s.owner_name} <span className="text-xs text-slate-500">· {s.owner_type}</span></div>}
                                        <div className="mt-0.5 text-xs text-slate-500">
                                            Gross {money(s.gross_earnings, s.currency)} · deductions {money(ded, s.currency)}{Number(s.other_deductions) > 0 ? ` (incl. advance recovery ${money(s.other_deductions, s.currency)})` : ""}
                                        </div>
                                        <div className="text-[11px] text-slate-400">{s.public_id || `#${s.id}`}{dates ? ` · ${dates}` : ""}</div>
                                    </div>
                                    <div className="flex flex-wrap items-center justify-between gap-2 lg:justify-end">
                                        <div className="mr-2 text-right">
                                            <div className="text-base font-black text-slate-900">{money(s.net_payable, s.currency)}</div>
                                            <div className="text-xs text-slate-500">net payable</div>
                                        </div>
                                        <div className="flex flex-wrap gap-1.5">
                                            <a href={`/v2/statements/${s.id}/download`} className="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                <Download className="h-3.5 w-3.5" /> PDF
                                            </a>
                                            {staff && s.invoice_id && (
                                                <a href={`/v2/invoices/${s.invoice_id}/download`} className="inline-flex h-9 items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700">
                                                    <FileText className="h-3.5 w-3.5" /> Invoice
                                                </a>
                                            )}
                                            {role === "super_admin" && s.status === "pending" && (
                                                <button type="button" disabled={!!busy} onClick={() => act(s, "approve")} className="inline-flex h-9 items-center gap-1 rounded-lg bg-violet-600 px-3 text-xs font-semibold text-white disabled:opacity-50">
                                                    {busy === `approve${s.id}` ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <CheckCircle2 className="h-3.5 w-3.5" />} Approve
                                                </button>
                                            )}
                                            {role === "super_admin" && s.status === "approved" && (
                                                <button type="button" disabled={!!busy} onClick={() => act(s, "available")} className="inline-flex h-9 items-center gap-1 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white disabled:opacity-50">
                                                    {busy === `available${s.id}` ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <WalletCards className="h-3.5 w-3.5" />} Make available
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}

                {statements.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                        {statements.prev_page_url ? <Link href={statements.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Previous</Link> : <span />}
                        <span className="text-xs text-slate-500">Page {statements.current_page} of {statements.last_page} · {statements.total}</span>
                        {statements.next_page_url ? <Link href={statements.next_page_url} preserveScroll className="font-semibold text-violet-700">Next →</Link> : <span />}
                    </div>
                )}
            </section>

            <div className="mt-4 flex items-start gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500">
                <Clock3 className="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    How a statement moves: <b>Waiting for approval</b> → <b>Approved</b> (money shows as pending in the wallet) → <b>Available</b> (can be withdrawn) → <b>Paid</b>.
                </span>
            </div>
        </PanelLayout>
    );
}

function Stat({ label, value, tone = "text-slate-900", hint }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-xs font-semibold text-slate-500">{label}</div>
            <div className={`mt-1 text-xl font-black ${tone}`}>{value}</div>
            {hint && <div className="text-xs text-slate-500">{hint}</div>}
        </div>
    );
}

/* MIXXTUNE_STATEMENTS_V2 (W45): monthly report -> statements, step by step */
const STEPS = [
    ["importing", "Import"],
    ["review", "Review"],
    ["scheduled", "Approved"],
    ["published", "Published"],
];
const STEP_TONE = {
    importing: "bg-slate-100 text-slate-700",
    review: "bg-amber-50 text-amber-800",
    scheduled: "bg-blue-50 text-blue-700",
    late: "bg-red-50 text-red-700",
    published: "bg-emerald-50 text-emerald-700",
    failed: "bg-red-50 text-red-700",
};

function Pipeline({ items }) {
    const waiting = items.filter((i) => ["review", "late", "failed"].includes(i.step)).length;
    return (
        <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-4 py-3 sm:px-5">
                <h2 className="text-base font-semibold text-slate-900">Monthly reports → statements</h2>
                <p className="text-xs text-slate-500">
                    Statements are made only when a report is <b>published</b>. {waiting > 0 ? `${waiting} report(s) need you.` : "Nothing is waiting for you."}
                </p>
            </div>
            <ul className="divide-y divide-slate-100">
                {items.map((r) => {
                    const at = r.step === "late" ? 2 : r.step === "failed" ? 0 : STEPS.findIndex(([k]) => k === r.step);
                    return (
                        <li key={r.id} className="flex flex-col gap-2 px-4 py-3 sm:px-5 lg:flex-row lg:items-center">
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-bold text-slate-900">{r.month ?? "No month"}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${STEP_TONE[r.step] ?? STEP_TONE.importing}`}>
                                        {r.step === "late" ? "Publish overdue" : r.step === "failed" ? "Import failed" : STEPS[at]?.[1]}
                                    </span>
                                </div>
                                <div className="truncate text-xs text-slate-500">
                                    {r.file} · {Number(r.rows).toLocaleString("en-IN")} rows{r.unmapped > 0 ? ` · ${Number(r.unmapped).toLocaleString("en-IN")} unmapped` : ""}
                                </div>
                                <div className={`text-xs ${["late", "failed", "review"].includes(r.step) ? "font-semibold text-amber-800" : "text-slate-600"}`}>{r.todo}</div>
                            </div>
                            <div className="flex items-center gap-3">
                                <ol className="hidden items-center gap-1 sm:flex">
                                    {STEPS.map(([k, label], i) => (
                                        <li key={k} className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${i < at || r.step === "published" ? "bg-emerald-100 text-emerald-700" : i === at ? (r.step === "late" || r.step === "failed" ? "bg-red-100 text-red-700" : "bg-violet-100 text-violet-700") : "bg-slate-100 text-slate-400"}`}>
                                            {label}
                                        </li>
                                    ))}
                                </ol>
                                <a href={r.url} className="inline-flex h-9 shrink-0 items-center rounded-lg bg-violet-600 px-3 text-xs font-semibold text-white">Open report</a>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
