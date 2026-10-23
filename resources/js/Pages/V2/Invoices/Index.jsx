import { Head, Link } from "@inertiajs/react";
import { Download, FileText } from "lucide-react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* MIXXTUNE_INVOICES_V2 */

const money = (v, c = "INR") => new Intl.NumberFormat("en-IN", { style: "currency", currency: c || "INR", maximumFractionDigits: 2 }).format(Number(v || 0));

const STATUS = {
    paid: "bg-emerald-50 text-emerald-700",
    issued: "bg-violet-50 text-violet-700",
    generated: "bg-violet-50 text-violet-700",
};

export default function Index({ role = "artist", invoices = {} }) {
    const rows = invoices.data ?? [];
    const admin = role === "admin" || role === "super_admin";

    return (
        <PanelLayout role={role} title="Invoices" subtitle={admin ? "Every invoice made for owners" : "Your royalty and payout invoices"}>
            <Head title="Invoices" />

            <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                {rows.length === 0 ? (
                    <div className="px-5 py-14 text-center">
                        <FileText className="mx-auto h-10 w-10 text-slate-300" />
                        <div className="mt-3 text-sm font-semibold text-slate-700">No invoices yet</div>
                        <div className="mx-auto mt-1 max-w-md text-xs text-slate-500">
                            An invoice appears here when your monthly royalty statement is approved and invoiced, and when a payment (withdrawal) is paid.
                        </div>
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {rows.map((v) => (
                            <li key={v.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-bold text-slate-900">{v.number}</span>
                                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{v.kind}</span>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${STATUS[v.status] ?? "bg-slate-100 text-slate-600"}`}>{v.status}</span>
                                    </div>
                                    <div className="mt-0.5 text-xs text-slate-500">
                                        {v.date}{v.month_label ? ` · for ${v.month_label}` : ""}{admin && v.owner ? ` · ${v.owner}` : ""}
                                    </div>
                                </div>
                                <div className="flex items-center justify-between gap-4 sm:justify-end">
                                    <div className="text-right">
                                        <div className="text-base font-black text-slate-900">{money(v.net, v.currency)}</div>
                                        {v.tds > 0 && <div className="text-xs text-slate-500">after TDS {money(v.tds, v.currency)}</div>}
                                    </div>
                                    <a href={`/invoices/${v.id}/download`} className="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <Download className="h-3.5 w-3.5" /> PDF
                                    </a>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {invoices.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                        {invoices.prev_page_url ? <Link href={invoices.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Previous</Link> : <span />}
                        <span className="text-xs text-slate-500">Page {invoices.current_page} of {invoices.last_page}</span>
                        {invoices.next_page_url ? <Link href={invoices.next_page_url} preserveScroll className="font-semibold text-violet-700">Next →</Link> : <span />}
                    </div>
                )}
            </section>
        </PanelLayout>
    );
}
