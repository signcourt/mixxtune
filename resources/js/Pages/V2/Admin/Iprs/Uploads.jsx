import { Head, Link, router, usePage } from "@inertiajs/react";
import { AlertTriangle, CheckCircle2, FileSpreadsheet, Loader2, Percent, Trash2, Upload, X } from "lucide-react";
import { useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* IPRS Publishing Royalty - Phase 1: upload & parse IPRS statement files. */

const num = (v) => new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));
const when = (v) => {
    if (!v) return "—";
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? String(v) : new Intl.DateTimeFormat("en-IN", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hour12: true, timeZone: "Asia/Kolkata" }).format(d) + " IST";
};
const mb = (bytes) => (bytes / 1048576).toLocaleString("en-IN", { maximumFractionDigits: 1 });

const STATUS = {
    imported: ["Imported", "bg-emerald-50 text-emerald-700"],
    needs_review: ["Needs review", "bg-amber-50 text-amber-800"],
    mapped: ["Mapped", "bg-blue-50 text-blue-700"],
    distributed: ["Distributed", "bg-violet-50 text-violet-700"],
    reversed: ["Reversed", "bg-slate-100 text-slate-600"],
};

const RESULT_TONE = {
    imported: "text-emerald-700",
    needs_review: "text-amber-700",
    duplicate: "text-slate-500",
    failed: "text-red-600",
};

export default function Uploads({ role = "admin", uploads = {} }) {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const results = flash.iprs_results ?? [];

    const [files, setFiles] = useState([]);
    const [periodLabel, setPeriodLabel] = useState("");
    const [processing, setProcessing] = useState(false);
    const [progress, setProgress] = useState(null);
    const [error, setError] = useState("");
    const [busyId, setBusyId] = useState(null);
    const inputRef = useRef(null);

    const rows = uploads.data ?? [];

    const pickFiles = (list) => {
        setError("");
        setFiles(Array.from(list || []));
    };

    const submit = (e) => {
        e.preventDefault();
        if (processing || files.length === 0) return;

        setError("");
        setProcessing(true);
        setProgress({ percentage: 0 });

        const body = new FormData();
        files.forEach((f) => body.append("report_files[]", f));
        if (periodLabel.trim()) body.append("period_label", periodLabel.trim());

        router.post("/admin/iprs/uploads", body, {
            forceFormData: true,
            preserveScroll: true,
            onProgress: (ev) => ev && setProgress({ percentage: Math.round((ev.percentage ?? 0)) }),
            onError: (errs) => {
                if (errs?.action_otp) return; // the e-mailed code box handles it
                setError(errs?.report_files || errs?.["report_files.0"] || Object.values(errs || {})[0] || "Upload could not be completed.");
            },
            onSuccess: () => {
                setFiles([]);
                setPeriodLabel("");
                if (inputRef.current) inputRef.current.value = "";
            },
            onFinish: () => {
                setProcessing(false);
                setProgress(null);
            },
        });
    };

    const remove = (u) => {
        if (busyId || !window.confirm(`Delete "${u.file_name}" and its ${u.total_lines} line(s)?`)) return;
        setBusyId(u.id);
        router.delete(`/admin/iprs/uploads/${u.public_id}`, { preserveScroll: true, onFinish: () => setBusyId(null) });
    };

    return (
        <PanelLayout role={role} title="IPRS Publishing Royalty" subtitle="Upload IPRS statements (MIXX TUNE as publisher), parse and validate them - mapping & client split come next">
            <Head title="IPRS Publishing Royalty" />

            <div className="space-y-5">
                <div className="flex justify-end">
                    <Link href="/admin/iprs/rates" className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700">
                        <Percent className="h-4 w-4" /> Royalty rates
                    </Link>
                </div>

                {flash.success && (
                    <div className="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        <CheckCircle2 className="h-4 w-4" /> {flash.success}
                    </div>
                )}

                {results.length > 0 && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 className="mb-2 text-sm font-semibold text-slate-700">Last upload</h3>
                        <ul className="space-y-1 text-sm">
                            {results.map((r, i) => (
                                <li key={i} className={RESULT_TONE[r.status] || "text-slate-600"}>• {r.message}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-base font-semibold text-slate-900">Upload IPRS statements (CSV or Excel)</h2>
                        <span className="text-xs text-slate-500">Pick many files at once (up to 30). Each is parsed and checked against its own TOTAL ROYALTIES.</span>
                    </div>

                    <div className="mt-4 grid gap-4 sm:grid-cols-3">
                        <label className="block sm:col-span-2">
                            <span className="mb-1.5 block text-sm font-semibold text-slate-700">Statement files</span>
                            <input
                                ref={inputRef}
                                type="file"
                                multiple
                                accept=".csv,.txt,.xlsx,.xls"
                                onChange={(e) => pickFiles(e.target.files)}
                                className="block w-full rounded-xl border border-slate-300 p-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold"
                            />
                        </label>
                        <label className="block">
                            <span className="mb-1.5 block text-sm font-semibold text-slate-700">Period label (optional)</span>
                            <input
                                type="text"
                                value={periodLabel}
                                onChange={(e) => setPeriodLabel(e.target.value)}
                                placeholder="e.g. FY 2025-26 Q1"
                                className="block h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"
                            />
                        </label>
                    </div>

                    {files.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {files.map((f, i) => (
                                <span key={i} className="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                                    <FileSpreadsheet className="h-3.5 w-3.5" /> {f.name} · {mb(f.size)} MB
                                </span>
                            ))}
                        </div>
                    )}

                    {error && <p className="mt-3 flex items-center gap-1.5 text-xs text-red-600"><AlertTriangle className="h-3.5 w-3.5" /> {error}</p>}

                    <p className="mt-3 text-xs text-slate-500">Uploading is confirmed with a one-time code sent to your e-mail. Duplicate statements are skipped automatically.</p>

                    <div className="mt-4 flex items-center gap-3">
                        <button type="submit" disabled={processing || files.length === 0} className="inline-flex h-11 items-center gap-2 rounded-xl bg-violet-600 px-6 text-sm font-semibold text-white disabled:opacity-40">
                            {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                            {processing ? "Uploading…" : `Upload & import${files.length ? ` (${files.length})` : ""}`}
                        </button>
                        {progress && (
                            <div className="h-2 w-48 overflow-hidden rounded-full bg-slate-100">
                                <div className="h-full bg-violet-600" style={{ width: `${progress.percentage}%` }} />
                            </div>
                        )}
                    </div>
                </form>

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 px-4 py-3 sm:px-5">
                        <h2 className="text-base font-semibold text-slate-900">Imported statements</h2>
                        <p className="text-xs text-slate-500">File total vs parsed total must match (green). A red difference means the file needs review before mapping.</p>
                    </div>

                    {rows.length === 0 ? (
                        <p className="px-5 py-12 text-center text-sm text-slate-500">No IPRS statement uploaded yet.</p>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {rows.map((u) => {
                                const [label, tone] = STATUS[u.status] || [u.status, "bg-slate-100 text-slate-600"];
                                const ok = u.within_tolerance;
                                return (
                                    <div key={u.id} className="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
                                        <Link href={`/admin/iprs/uploads/${u.public_id}`} className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="truncate text-sm font-bold text-slate-900 hover:text-violet-700 hover:underline">{u.file_name}</span>
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>{label}</span>
                                                {u.period_label && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{u.period_label}</span>}
                                            </div>
                                            <div className="mt-0.5 text-xs text-slate-500">
                                                {u.member_name || "—"}{u.member_internal_no ? ` · Internal ${u.member_internal_no}` : ""}{u.ipi_name_no ? ` · IPI ${u.ipi_name_no}` : ""}
                                            </div>
                                            <div className="text-[11px] text-slate-400">uploaded {when(u.created_at)} · click to view</div>
                                        </Link>

                                        <div className="text-right text-xs">
                                            <div className={`font-semibold ${ok ? "text-emerald-700" : "text-red-600"}`}>
                                                {ok ? <span className="inline-flex items-center gap-1"><CheckCircle2 className="h-3.5 w-3.5" /> ₹{num(u.parsed_total)}</span> : <span className="inline-flex items-center gap-1"><AlertTriangle className="h-3.5 w-3.5" /> Δ {num(u.difference)}</span>}
                                            </div>
                                            <div className="text-slate-500">file ₹{num(u.file_total)} · {u.total_lines} lines · {u.total_works} works</div>
                                        </div>

                                        <button
                                            type="button"
                                            title="Delete statement"
                                            onClick={() => remove(u)}
                                            disabled={busyId === u.id}
                                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-600 disabled:opacity-50"
                                        >
                                            {busyId === u.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {(uploads.last_page ?? 1) > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                            {uploads.prev_page_url ? <Link href={uploads.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Previous</Link> : <span />}
                            <span className="text-xs text-slate-500">Page {uploads.current_page} of {uploads.last_page}</span>
                            {uploads.next_page_url ? <Link href={uploads.next_page_url} preserveScroll className="font-semibold text-violet-700">Next →</Link> : <span />}
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
