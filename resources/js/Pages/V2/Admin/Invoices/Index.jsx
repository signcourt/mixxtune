import { Head, Link, router, usePage } from "@inertiajs/react";
import { CheckCircle2, Download, FileText, Loader2, Search, Wand2 } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* MIXXTUNE_INVOICES_V2 */

const money = (v, c = "INR") => new Intl.NumberFormat("en-IN", { style: "currency", currency: c || "INR", maximumFractionDigits: 2 }).format(Number(v || 0));
const URL = "/v2/admin/invoices";

export default function Index({ role = "admin", tab = "ready", counts = {}, statements = {}, invoices = null, months = [], filters = {} }) {
    const page = usePage().props;
    const flash = page.flash ?? {};
    const errors = page.errors ?? {};
    const [search, setSearch] = useState(filters.search || "");
    const [busy, setBusy] = useState(null);
    const [notify, setNotify] = useState(true);
    const first = useRef(true);

    const go = (patch) => router.get(URL, { ...filters, ...patch, page: undefined }, { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => go({ search }), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const make = (id) => {
        setBusy(id);
        router.post(`${URL}/statements/${id}/generate`, { notify_owner: notify }, { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    const makeAll = () => {
        const n = counts.missing ?? 0;
        if (!window.confirm(`Make invoices for ${n > 200 ? "the first 200 of " : ""}${n} statement(s)${filters.month ? " of the chosen month" : ""}?`)) return;
        setBusy("all");
        router.post(`${URL}/generate-missing`, { search: filters.search, month: filters.month, notify_owner: notify }, { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    const list = tab === "invoices" ? invoices ?? {} : statements;
    const rows = list.data ?? [];

    return (
        <PanelLayout role={role} title="Invoices" subtitle="Make royalty invoices from approved statements and see every invoice">
            <Head title="Invoices" />

            {flash.success && (
                <div className="mb-3 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4" /> {flash.success}
                </div>
            )}
            {errors.invoice && <div className="mb-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{errors.invoice}</div>}

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat label="Waiting for invoice" value={counts.missing ?? 0} tone={counts.missing ? "text-amber-700" : "text-slate-900"} />
                <Stat label="Statements invoiced" value={counts.invoiced ?? 0} tone="text-emerald-700" />
                <Stat label="Statements not approved yet" value={counts.waiting_approval ?? 0} hint="approve in Statements first" />
                <Stat label="All invoices" value={counts.invoices ?? 0} hint="monthly + payout" />
            </div>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex gap-1 overflow-x-auto border-b border-slate-200 px-3">
                    {[["ready", `Ready for invoice (${counts.missing ?? 0})`], ["invoices", `All invoices (${counts.invoices ?? 0})`]].map(([k, v]) => (
                        <button key={k} type="button" onClick={() => go({ tab: k, show: undefined })} className={`whitespace-nowrap border-b-2 px-3 py-3 text-sm font-semibold ${tab === k ? "border-violet-600 text-violet-700" : "border-transparent text-slate-500"}`}>{v}</button>
                    ))}
                </div>

                <div className="flex flex-col gap-2 border-b border-slate-100 p-3 lg:flex-row lg:items-center">
                    <div className="relative min-w-0 flex-1">
                        <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={tab === "invoices" ? "Invoice no., owner name or e-mail" : "Label or artist name"} className="h-10 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm" />
                    </div>
                    {tab === "ready" && (
                        <div className="grid grid-cols-2 gap-2 sm:flex">
                            <select value={filters.month || ""} onChange={(e) => go({ month: e.target.value })} className="h-10 min-w-0 rounded-xl border border-slate-300 bg-white pl-3 pr-8 text-sm">
                                <option value="">All months</option>
                                {months.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                            </select>
                            <select value={filters.show || "missing"} onChange={(e) => go({ show: e.target.value })} className="h-10 min-w-0 rounded-xl border border-slate-300 bg-white pl-3 pr-8 text-sm">
                                <option value="missing">Without invoice</option>
                                <option value="all">All approved</option>
                            </select>
                            <button type="button" disabled={!counts.missing || busy === "all"} onClick={makeAll} className="col-span-2 inline-flex h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white disabled:opacity-40">
                                {busy === "all" ? <Loader2 className="h-4 w-4 animate-spin" /> : <Wand2 className="h-4 w-4" />} Make all missing
                            </button>
                        </div>
                    )}
                </div>
                {tab === "ready" && (
                    <label className="flex items-center gap-2 border-b border-slate-100 px-4 py-2 text-xs text-slate-600">
                        <input type="checkbox" checked={notify} onChange={(e) => setNotify(e.target.checked)} /> Tell the owner when their invoice is made (panel notice)
                    </label>
                )}

                {rows.length === 0 ? (
                    <Empty tab={tab} counts={counts} filtered={!!(filters.search || filters.month)} />
                ) : tab === "ready" ? (
                    <ul className="divide-y divide-slate-100">
                        {rows.map((s) => (
                            <li key={s.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-bold text-slate-900">{s.month_label}</span>
                                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold capitalize text-slate-600">{s.status}</span>
                                    </div>
                                    <div className="mt-0.5 truncate text-sm text-slate-700">{s.owner} <span className="text-xs text-slate-500">· {s.owner_type}</span></div>
                                    <div className="text-xs text-slate-500">Gross {money(s.gross, s.currency)} · deductions {money(s.deductions, s.currency)}</div>
                                </div>
                                <div className="flex items-center justify-between gap-4 sm:justify-end">
                                    <div className="text-right">
                                        <div className="text-base font-black text-slate-900">{money(s.net, s.currency)}</div>
                                        <div className="text-xs text-slate-500">net payable</div>
                                    </div>
                                    {s.invoice_id ? (
                                        <a href={`/v2/invoices/${s.invoice_id}/download`} className="inline-flex h-9 items-center gap-1 whitespace-nowrap rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700">
                                            <Download className="h-3.5 w-3.5" /> {s.invoice_number}
                                        </a>
                                    ) : (
                                        <button type="button" disabled={busy === s.id} onClick={() => make(s.id)} className="inline-flex h-9 items-center gap-1 whitespace-nowrap rounded-lg bg-violet-600 px-3 text-xs font-semibold text-white disabled:opacity-50">
                                            {busy === s.id ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <FileText className="h-3.5 w-3.5" />} Make invoice
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {rows.map((v) => (
                            <li key={v.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-bold text-slate-900">{v.number}</span>
                                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{v.kind}</span>
                                        <span className="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-semibold capitalize text-violet-700">{v.status}</span>
                                    </div>
                                    <div className="mt-0.5 truncate text-sm text-slate-700">{v.owner} <span className="text-xs text-slate-500">· {v.owner_email}</span></div>
                                    <div className="text-xs text-slate-500">{v.date}{v.month_label ? ` · for ${v.month_label}` : ""}</div>
                                </div>
                                <div className="flex items-center justify-between gap-4 sm:justify-end">
                                    <div className="text-right">
                                        <div className="text-base font-black text-slate-900">{money(v.net, v.currency)}</div>
                                        {v.tds > 0 && <div className="text-xs text-slate-500">after TDS {money(v.tds, v.currency)}</div>}
                                    </div>
                                    <a href={`/v2/invoices/${v.id}/download`} className="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700">
                                        <Download className="h-3.5 w-3.5" /> PDF
                                    </a>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {list.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                        {list.prev_page_url ? <Link href={list.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Previous</Link> : <span />}
                        <span className="text-xs text-slate-500">Page {list.current_page} of {list.last_page} · {list.total}</span>
                        {list.next_page_url ? <Link href={list.next_page_url} preserveScroll className="font-semibold text-violet-700">Next →</Link> : <span />}
                    </div>
                )}
            </section>
        </PanelLayout>
    );
}

function Stat({ label, value, tone = "text-slate-900", hint }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-xs font-semibold text-slate-500">{label}</div>
            <div className={`mt-1 text-xl font-black ${tone}`}>{Number(value).toLocaleString("en-IN")}</div>
            {hint && <div className="text-xs text-slate-500">{hint}</div>}
        </div>
    );
}

function Empty({ tab, counts, filtered }) {
    let title = "Nothing here";
    let text = "";
    if (filtered) {
        title = "No match";
        text = "Clear the search or month to see more.";
    } else if (tab === "invoices") {
        title = "No invoices yet";
        text = "Invoices appear when you make them from approved statements, and automatically when a withdrawal is paid.";
    } else if ((counts.invoiced ?? 0) > 0) {
        title = "All done";
        text = "Every approved statement already has an invoice. Choose \"All approved\" to see them.";
    } else {
        title = "No approved statements yet";
        text = (counts.waiting_approval ?? 0) > 0
            ? `${counts.waiting_approval} statement(s) are waiting for approval. Approve them in Finance → Statements; they will then show here for invoicing.`
            : "Statements are made when a monthly sales report is imported and approved. Import the report first.";
    }
    return (
        <div className="px-5 py-14 text-center">
            <FileText className="mx-auto h-10 w-10 text-slate-300" />
            <div className="mt-3 text-sm font-semibold text-slate-700">{title}</div>
            <div className="mx-auto mt-1 max-w-md text-xs text-slate-500">{text}</div>
            {!filtered && tab === "ready" && (counts.waiting_approval ?? 0) > 0 && (
                <Link href="/v2/admin/statements" className="mt-3 inline-flex h-9 items-center rounded-lg bg-violet-600 px-4 text-xs font-semibold text-white">Open Statements</Link>
            )}
        </div>
    );
}
