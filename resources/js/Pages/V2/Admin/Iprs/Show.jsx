import { Head, Link, router, usePage } from "@inertiajs/react";
import { AlertTriangle, ArrowLeft, CheckCircle2, Link2, Loader2, Music2, Search, Unlink, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* IPRS Phase 2: statement detail + map each work to a catalogue track.
   Mapping stores the work number on the track, so future statements auto-map. */

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

function MapPanel({ upload, work }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState("");
    const [results, setResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const [busy, setBusy] = useState(false);
    const timer = useRef(null);

    useEffect(() => {
        if (!open) return;
        if (timer.current) clearTimeout(timer.current);
        if (q.trim().length < 2) {
            setResults([]);
            return;
        }
        timer.current = setTimeout(async () => {
            setSearching(true);
            try {
                const res = await fetch(`/admin/iprs/tracks/search?q=${encodeURIComponent(q.trim())}`, {
                    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                });
                const data = await res.json();
                setResults(data.tracks || []);
            } catch {
                setResults([]);
            } finally {
                setSearching(false);
            }
        }, 300);
        return () => timer.current && clearTimeout(timer.current);
    }, [q, open]);

    const doMap = (trackId) => {
        setBusy(true);
        router.post(`/admin/iprs/uploads/${upload.public_id}/map`, { work_int_no: work.work_int_no, track_id: trackId }, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); setOpen(false); setQ(""); setResults([]); },
        });
    };

    const unmap = () => {
        if (!window.confirm("Remove this mapping? The work will become unmatched again.")) return;
        setBusy(true);
        router.post(`/admin/iprs/uploads/${upload.public_id}/unmap`, { work_int_no: work.work_int_no }, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };

    if (work.mapping_status === "matched" && work.mapped_track) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-emerald-50/50 px-4 py-2.5 sm:px-5">
                <div className="text-sm text-emerald-800">
                    <span className="font-semibold">Mapped →</span> {work.mapped_track.title}
                    {work.mapped_track.isrc ? <span className="text-emerald-600"> · {work.mapped_track.isrc}</span> : null}
                    {work.owner_name ? <span className="text-slate-500"> · owner {work.owner_name}</span> : null}
                </div>
                <button type="button" onClick={unmap} disabled={busy} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-600 disabled:opacity-50">
                    {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Unlink className="h-3.5 w-3.5" />} Unmap
                </button>
            </div>
        );
    }

    return (
        <div className="border-t border-slate-100 px-4 py-2.5 sm:px-5">
            {!open ? (
                <button type="button" onClick={() => setOpen(true)} className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white">
                    <Link2 className="h-3.5 w-3.5" /> Map to a song
                </button>
            ) : (
                <div className="space-y-2">
                    <div className="flex items-center gap-2">
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                autoFocus
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Search your catalogue by song name or ISRC…"
                                className="h-10 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm"
                            />
                        </div>
                        <button type="button" onClick={() => { setOpen(false); setQ(""); setResults([]); }} className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500">
                            <X className="h-4 w-4" />
                        </button>
                    </div>

                    {searching && <p className="flex items-center gap-1.5 text-xs text-slate-400"><Loader2 className="h-3.5 w-3.5 animate-spin" /> searching…</p>}
                    {!searching && q.trim().length >= 2 && results.length === 0 && <p className="text-xs text-slate-400">No matching song found.</p>}

                    {results.length > 0 && (
                        <div className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                            {results.map((t) => (
                                <div key={t.track_id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-semibold text-slate-800">{t.title}</div>
                                        <div className="text-xs text-slate-500">
                                            {t.isrc || "no ISRC"}{t.upc ? ` · UPC ${t.upc}` : ""}{t.owner_name ? ` · ${t.owner_name}` : ""}
                                        </div>
                                        {t.current_work_no && t.current_work_no !== work.work_int_no && (
                                            <div className="text-[11px] text-amber-600">already holds work {t.current_work_no} — mapping will move it here</div>
                                        )}
                                    </div>
                                    <button type="button" onClick={() => doMap(t.track_id)} disabled={busy} className="inline-flex items-center gap-1 rounded-lg bg-violet-600 px-3 py-1 text-xs font-semibold text-white disabled:opacity-50">
                                        {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Link2 className="h-3.5 w-3.5" />} Map
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function Show({ role = "admin", upload = {}, works = [] }) {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const ok = upload.within_tolerance;
    const matchedCount = works.filter((w) => w.mapping_status === "matched").length;

    return (
        <PanelLayout role={role} title="IPRS statement" subtitle={upload.file_name}>
            <Head title={`IPRS · ${upload.file_name || "statement"}`} />

            <div className="space-y-5">
                <Link href="/admin/iprs" className="inline-flex items-center gap-1.5 text-sm font-semibold text-violet-700">
                    <ArrowLeft className="h-4 w-4" /> Back to IPRS statements
                </Link>

                {flash.success && (
                    <div className="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        <CheckCircle2 className="h-4 w-4" /> {flash.success}
                    </div>
                )}

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
                            <div className="mt-1 text-xs text-slate-500">
                                {matchedCount} of {works.length} works mapped
                                {upload.period_label ? ` · Period: ${upload.period_label}` : ""}
                            </div>
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

                            {/* mapping control */}
                            <MapPanel upload={upload} work={w} />

                            {/* writers */}
                            {w.credits?.length > 0 && (
                                <div className="flex flex-wrap gap-2 border-t border-slate-100 px-4 py-3 sm:px-5">
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
                    Mapping a work stores its work number on the chosen song, so the next IPRS statement with the same work number maps to that song automatically. Mapped royalty can then be split and shown to the owner.
                </p>
            </div>
        </PanelLayout>
    );
}
