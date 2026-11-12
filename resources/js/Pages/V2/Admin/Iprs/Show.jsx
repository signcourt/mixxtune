import { Head, Link } from "@inertiajs/react";
import { AlertTriangle, ArrowLeft, CheckCircle2, Music2 } from "lucide-react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* IPRS Phase 2 (view): read-only detail of one imported statement - its works,
   writers and royalty lines. Mapping to the catalogue is added on top of this. */

const MONTHS = ["", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const ROLES = { C: "Composer", A: "Author / Lyricist", CA: "Composer + Author", E: "Publisher", SE: "Sub-publisher", AR: "Arranger" };

const money = (v) => "₹" + new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));
const amt4 = (v) => new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(Number(v || 0));
const pct = (v) => (v === null || v === undefined ? "—" : `${Number(v)}%`);

const MAP_BADGE = {
    unmatched: ["Unmatched", "bg-amber-50 text-amber-800"],
    matched: ["Matched", "bg-emerald-50 text-emerald-700"],
    ignored: ["Ignored", "bg-slate-100 text-slate-500"],
};

export default function Show({ role = "admin", upload = {}, works = [] }) {
    const ok = upload.within_tolerance;

    return (
        <PanelLayout role={role} title="IPRS statement" subtitle={upload.file_name}>
            <Head title={`IPRS · ${upload.file_name || "statement"}`} />

            <div className="space-y-5">
                <Link href="/admin/iprs" className="inline-flex items-center gap-1.5 text-sm font-semibold text-violet-700">
                    <ArrowLeft className="h-4 w-4" /> Back to IPRS statements
                </Link>

                {/* summary */}
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-bold text-slate-900">{upload.file_name}</h2>
                            <div className="mt-1 text-sm text-slate-600">
                                {upload.member_name || "—"}
                                {upload.member_internal_no ? ` · Internal ${upload.member_internal_no}` : ""}
                                {upload.ipi_name_no ? ` · IPI Name ${upload.ipi_name_no}` : ""}
                                {upload.ipi_base_no ? ` · IPI Base ${upload.ipi_base_no}` : ""}
                            </div>
                            {upload.period_label && <div className="mt-1 text-xs text-slate-500">Period: {upload.period_label}</div>}
                        </div>
                        <div className="text-right">
                            <div className={`text-xl font-bold ${ok ? "text-emerald-700" : "text-red-600"}`}>
                                {ok ? (
                                    <span className="inline-flex items-center gap-1"><CheckCircle2 className="h-5 w-5" /> {money(upload.parsed_total)}</span>
                                ) : (
                                    <span className="inline-flex items-center gap-1"><AlertTriangle className="h-5 w-5" /> Δ {amt4(upload.difference)}</span>
                                )}
                            </div>
                            <div className="text-xs text-slate-500">file {money(upload.file_total)} · {upload.total_lines} lines · {upload.total_works} works</div>
                        </div>
                    </div>
                </div>

                {/* works */}
                {works.map((w) => {
                    const [label, tone] = MAP_BADGE[w.mapping_status] || [w.mapping_status, "bg-slate-100 text-slate-500"];
                    return (
                        <section key={w.work_int_no} className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 px-4 py-3 sm:px-5">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Music2 className="h-4 w-4 text-violet-500" />
                                        <span className="text-base font-bold text-slate-900">{w.title || "(untitled)"}</span>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>{label}</span>
                                    </div>
                                    <div className="mt-0.5 text-xs text-slate-500">Work No {w.work_int_no}{w.language ? ` · ${w.language}` : ""}</div>
                                </div>
                                <div className="text-right text-sm font-bold text-slate-900">{money(w.total_amount)}</div>
                            </div>

                            {/* writers */}
                            {w.credits?.length > 0 && (
                                <div className="flex flex-wrap gap-2 border-b border-slate-100 px-4 py-3 sm:px-5">
                                    {w.credits.map((c, i) => (
                                        <span key={i} className="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-xs text-slate-700">
                                            <span className="font-semibold">{c.name}</span>
                                            <span className="text-slate-400">{ROLES[c.role] || c.role || "—"}</span>
                                        </span>
                                    ))}
                                </div>
                            )}

                            {/* royalty lines */}
                            <div className="overflow-x-auto px-4 py-2 sm:px-5">
                                <table className="w-full min-w-[520px] text-sm">
                                    <thead>
                                        <tr className="text-left text-xs uppercase tracking-wide text-slate-400">
                                            <th className="py-2 pr-3 font-semibold">Source</th>
                                            <th className="py-2 pr-3 font-semibold">Month</th>
                                            <th className="py-2 pr-3 font-semibold">Own %</th>
                                            <th className="py-2 pr-3 font-semibold">Coll %</th>
                                            <th className="py-2 pr-3 text-right font-semibold">Royalty</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-50">
                                        {w.lines.map((l) => (
                                            <tr key={l.id}>
                                                <td className="py-2 pr-3 text-slate-700">{l.source || "—"}</td>
                                                <td className="py-2 pr-3 text-slate-500">{MONTHS[l.source_month] || "—"}</td>
                                                <td className="py-2 pr-3 text-slate-500">{pct(l.own_pct)}</td>
                                                <td className="py-2 pr-3 text-slate-500">{pct(l.coll_pct)}</td>
                                                <td className="py-2 pr-3 text-right font-semibold text-slate-900">{amt4(l.amount)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    );
                })}

                <p className="px-1 text-xs text-slate-400">
                    IPRS files carry no ISRC — only the Work No and title. Mapping each work to a track (ISRC) and its owner is the next step; once mapped, the royalty can be split and shown to the right client.
                </p>
            </div>
        </PanelLayout>
    );
}
