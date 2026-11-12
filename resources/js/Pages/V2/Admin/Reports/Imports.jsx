import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { CheckCircle2, Download, Eye, Loader2, Pencil, RefreshCw, Trash2, Upload, X } from "lucide-react";
import { useEffect, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* MIXXTUNE_REPORT_IMPORTS_V2 (W47) */

const monthName = (v) => {
    const m = String(v || "").match(/^(\d{4})-(\d{2})/);
    return m ? new Intl.DateTimeFormat("en-IN", { month: "long", year: "numeric" }).format(new Date(Number(m[1]), Number(m[2]) - 1, 1)) : "—";
};
const num = (v) => new Intl.NumberFormat("en-IN").format(Number(v ?? 0));
const inr = (v) => new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 2 }).format(Number(v || 0));
const when = (v) => {
    if (!v) return "—";
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? String(v) : new Intl.DateTimeFormat("en-IN", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hour12: true, timeZone: "Asia/Kolkata" }).format(d) + " IST";
};
const mb = (bytes) => (bytes / 1048576).toLocaleString("en-IN", { maximumFractionDigits: 1 });

/* import status + money status -> one plain step */
const stepOf = (i) => {
    if (["queued", "pending", "processing"].includes(i.status)) return ["Reading the file…", "bg-slate-100 text-slate-700", false];
    if (i.status === "failed") return ["Import failed", "bg-red-50 text-red-700", false];
    if (i.financial_status === "published" || i.published_at) return ["Published · in wallets", "bg-emerald-50 text-emerald-700", true];
    if (i.financial_status === "approved_scheduled") {
        const late = i.publish_at && new Date(i.publish_at).getTime() < Date.now() - 15 * 60 * 1000;
        return [late ? "Publish overdue" : `Approved · publishes ${when(i.publish_at)}`, late ? "bg-red-50 text-red-700" : "bg-blue-50 text-blue-700", true];
    }
    return ["Waiting for review", "bg-amber-50 text-amber-800", false];
};

export default function Imports({ role = "admin", imports = {}, uploadLimitMb = 0, settingLimitMb = 0, monthMeta = {} }) {
    const page = usePage().props;
    const flash = page.flash ?? {};
    const pageErrors = page.errors ?? {};
    const limitMb = Number(uploadLimitMb) || Number(page.platformRules?.report_max_mb) || 100;
    const rows = imports.data ?? [];

    /* W138: months already uploaded (blocked) + the next month to suggest */
    const usedMonths = monthMeta.used ?? [];
    const suggestedMonth = monthMeta.suggested ?? "";

    const [editing, setEditing] = useState(null);
    const [editMonth, setEditMonth] = useState("");
    const [busy, setBusy] = useState(false);
    const [fileError, setFileError] = useState("");

    /* reports are read by a background worker: refresh while one is running */
    useEffect(() => {
        if (!rows.some((i) => ["queued", "pending", "processing"].includes(i.status))) return undefined;
        const t = window.setInterval(() => router.reload({ only: ["imports"], preserveScroll: true, preserveState: true }), 5000);
        return () => window.clearInterval(t);
    }, [imports.data]);

    const { data, setData, post, processing, errors, reset, progress } = useForm({ reporting_month: suggestedMonth, report_file: null });

    /* keep the suggested month selected; after an upload the props reload and this advances it */
    useEffect(() => {
        if (suggestedMonth) setData("reporting_month", suggestedMonth);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [suggestedMonth]);

    const sameMonth = data.reporting_month ? rows.filter((i) => String(i.reporting_month || "").startsWith(data.reporting_month)) : [];

    const pickFile = (file) => {
        setFileError("");
        if (file && file.size > limitMb * 1048576) {
            setFileError(`This file is ${mb(file.size)} MB - the server accepts up to ${limitMb} MB. Split it into smaller CSV or Excel files (same reporting month) and upload each.`);
            setData("report_file", null);
            return;
        }
        if (file && !/\.(csv|txt|xlsx|xls)$/i.test(file.name)) {
            setFileError("Choose the report as a .csv or Excel (.xlsx / .xls) file.");
            setData("report_file", null);
            return;
        }
        setData("report_file", file ?? null);
    };

    const submit = (e) => {
        e.preventDefault();
        if (sameMonth.length && !window.confirm(`${monthName(data.reporting_month)} already has ${sameMonth.length} report(s). Upload this one too?\n(Exact duplicate rows are skipped automatically.)`)) return;
        post("/admin/reports/imports", { forceFormData: true, preserveScroll: true, onSuccess: () => { setData("report_file", null); const f = document.getElementById("report-file"); if (f) f.value = ""; } });
    };

    const saveEdit = (e) => {
        e.preventDefault();
        if (!editing || !editMonth || busy) return;
        setBusy(true);
        router.patch(`/admin/reports/imports/${editing.id}/change-month`, { reporting_month: editMonth }, {
            preserveScroll: true,
            // Keep the modal open when an OTP is required (the global OTP prompt
            // opens on errors.action_otp; pressing Save again confirms the move).
            onSuccess: () => { setEditing(null); setEditMonth(""); },
            onFinish: () => setBusy(false),
        });
    };

    const remove = (i) => {
        if (busy || !window.confirm(`Delete "${i.original_filename}" and its ${num(i.imported_rows)} rows?\n\nOnly possible while nothing from it is in wallets.`)) return;
        setBusy(true);
        router.delete(`/admin/reports/imports/${i.id}`, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    const redetect = (i) => {
        if (busy || !window.confirm(`Check the ${num(i.unmapped_rows_count)} unmapped row(s) of ${i.original_filename} against the latest catalogue again?\n\nMapped rows are not changed. Nothing is credited or published.`)) return;
        setBusy(true);
        router.post(`/admin/reports/imports/${i.id}/re-detect-unmapped`, {}, { preserveScroll: true, preserveState: true, onFinish: () => setBusy(false) });
    };

    const errorList = Object.entries(pageErrors).filter(([k]) => !["reporting_month", "report_file"].includes(k));

    return (
        <PanelLayout role={role} title="Revenue Imports" subtitle="Upload the monthly store report, review it, approve it - then statements and wallets are filled">
            <Head title="Revenue Imports" />

            <div className="space-y-5">
                {flash.success && (
                    <div className="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        <CheckCircle2 className="h-4 w-4" /> {flash.success}
                    </div>
                )}
                {errorList.length > 0 && (
                    <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {errorList.map(([k, v]) => <div key={k}>{v}</div>)}
                    </div>
                )}

                <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-base font-semibold text-slate-900">Upload a report (CSV or Excel)</h2>
                        <span className="text-xs text-slate-500">
                            Up to <b>{limitMb} MB</b> per file{Number(settingLimitMb) > limitMb ? ` (Master Settings says ${settingLimitMb} MB, the server allows ${limitMb} MB)` : ""}
                        </span>
                    </div>

                    <div className="mt-4 grid gap-4 md:grid-cols-[240px_minmax(0,1fr)]">
                        <label className="block">
                            <span className="mb-1.5 block text-sm font-semibold text-slate-700">Reporting month</span>
                            <MonthPicker value={data.reporting_month} used={usedMonths} suggested={suggestedMonth} onChange={(m) => setData("reporting_month", m)} />
                            {errors.reporting_month && <span className="mt-1 block text-xs text-red-600">{errors.reporting_month}</span>}
                        </label>
                        <label className="block min-w-0">
                            <span className="mb-1.5 block text-sm font-semibold text-slate-700">Report file</span>
                            <input id="report-file" type="file" accept=".csv,.txt,.xlsx,.xls" onChange={(e) => pickFile(e.target.files?.[0])} className="block w-full rounded-xl border border-slate-300 p-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold" />
                            {data.report_file && <span className="mt-1 block text-xs text-slate-500">{data.report_file.name} · {mb(data.report_file.size)} MB</span>}
                            {(fileError || errors.report_file) && <span className="mt-1 block text-xs text-red-600">{fileError || errors.report_file}</span>}
                        </label>
                    </div>

                    <p className="mt-3 text-xs text-slate-500">
                        Money from this file is counted under the reporting month you choose (the sale month inside the CSV stays as it is).
                    </p>
                    {sameMonth.length > 0 && (
                        <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            {monthName(data.reporting_month)} already has {sameMonth.length} report(s): {sameMonth.map((i) => i.original_filename).join(", ")}. Exact duplicate rows are skipped, but a different file for the same sales would be counted again.
                        </p>
                    )}

                    <div className="mt-4 flex flex-wrap items-center gap-3">
                        <button type="submit" disabled={processing || !data.reporting_month || !data.report_file} className="inline-flex h-11 items-center gap-2 rounded-xl bg-violet-600 px-6 text-sm font-semibold text-white disabled:opacity-40">
                            {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                            {processing ? `Uploading${progress ? ` ${progress.percentage}%` : "…"}` : "Upload & import"}
                        </button>
                        {processing && progress && (
                            <div className="h-2 w-48 overflow-hidden rounded-full bg-slate-100">
                                <div className="h-full bg-violet-600" style={{ width: `${progress.percentage}%` }} />
                            </div>
                        )}
                    </div>
                </form>

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 px-4 py-3 sm:px-5">
                        <h2 className="text-base font-semibold text-slate-900">Uploaded reports</h2>
                        <p className="text-xs text-slate-500">Steps: read the file → review (map unmapped rows) → open the report and "Approve & schedule" → published to statements and wallets.</p>
                    </div>

                    {rows.length === 0 ? (
                        <p className="px-5 py-12 text-center text-sm text-slate-500">No report uploaded yet.</p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {rows.map((i) => {
                                const [label, tone, locked] = stepOf(i);
                                const unmapped = Number(i.unmapped_rows_count || 0);
                                const running = ["queued", "pending", "processing"].includes(i.status);
                                const done = Number(i.imported_rows || 0) + Number(i.duplicate_rows || 0) + Number(i.failed_rows || 0);
                                return (
                                    <li key={i.id} className="flex flex-col gap-3 px-4 py-4 sm:px-5 lg:flex-row lg:items-center">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="text-sm font-bold text-slate-900">{monthName(i.reporting_month)}</span>
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${tone}`}>{label}</span>
                                                {unmapped > 0 && !running && <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">{num(unmapped)} unmapped</span>}
                                            </div>
                                            <div className="mt-0.5 truncate text-sm text-slate-700">{i.original_filename}</div>
                                            <div className="text-xs text-slate-500">
                                                {running && Number(i.expected_rows) > 0 ? (
                                                    <span className="font-semibold text-violet-700">
                                                        Reading… {num(i.total_rows)} of {num(i.expected_rows)} rows ({Math.min(100, Math.round((Number(i.total_rows) / Number(i.expected_rows)) * 100))}%)
                                                    </span>
                                                ) : (
                                                    <>{num(i.total_rows)} rows · {num(i.imported_rows)} imported</>
                                                )}
                                                {Number(i.duplicate_rows) > 0 && <span title="Rows identical to rows already imported - skipped so nothing is counted twice"> · {num(i.duplicate_rows)} duplicate (skipped)</span>}
                                                {Number(i.failed_rows) > 0 && <span className="text-red-600"> · {num(i.failed_rows)} failed</span>}
                                                {i.review_earnings !== undefined && i.review_earnings !== null && Number(i.review_earnings) > 0 ? ` · earnings ${inr(i.review_earnings)}` : ""}
                                            </div>
                                            <div className="text-[11px] text-slate-400">uploaded {when(i.created_at)}</div>
                                            {running && Number(i.expected_rows) > 0 && (
                                                <div className="mt-1 h-1.5 w-56 overflow-hidden rounded-full bg-slate-100">
                                                    <div className="h-full bg-violet-500 transition-all duration-500" style={{ width: `${Math.min(100, (Number(i.total_rows) / Number(i.expected_rows)) * 100)}%` }} />
                                                </div>
                                            )}
                                            {running && !(Number(i.expected_rows) > 0) && (
                                                <div className="mt-1 text-xs text-violet-700">Reading the file…</div>
                                            )}
                                            {i.status === "failed" && i.error_short && <div className="mt-1 break-words text-xs text-red-600">{i.error_short}</div>}
                                        </div>

                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <a href={`/admin/reports/imports/${i.id}/view`} className="inline-flex h-9 items-center gap-1 rounded-lg bg-violet-600 px-3 text-xs font-semibold text-white">
                                                <Eye className="h-3.5 w-3.5" /> {locked || i.status === "failed" || running ? "Open" : "Open & review"}
                                            </a>
                                            {unmapped > 0 && !running && (
                                                <a href={`/super-admin/unmapped-revenue?report_import_id=${i.id}`} className="inline-flex h-9 items-center rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-800">
                                                    Map {num(unmapped)}
                                                </a>
                                            )}
                                            {unmapped > 0 && i.financial_status === "review_pending" && !running && (
                                                <button type="button" onClick={() => redetect(i)} disabled={busy} title="Check unmapped rows again" className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-cyan-200 bg-cyan-50 text-cyan-700 disabled:opacity-50">
                                                    <RefreshCw className="h-4 w-4" />
                                                </button>
                                            )}
                                            <a href={`/admin/reports/imports/${i.id}/download`} title="Download the original file" className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600">
                                                <Download className="h-4 w-4" />
                                            </a>
                                            {!running && (
                                                <button type="button" title="Change reporting month" onClick={() => { setEditing(i); setEditMonth(String(i.reporting_month || "").slice(0, 7)); }} disabled={busy} className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 disabled:opacity-50">
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                            )}
                                            {!locked && !running && (
                                                <button type="button" title="Delete report" onClick={() => remove(i)} disabled={busy} className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-600 disabled:opacity-50">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {imports.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                            {imports.prev_page_url ? <Link href={imports.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Previous</Link> : <span />}
                            <span className="text-xs text-slate-500">Page {imports.current_page} of {imports.last_page}</span>
                            {imports.next_page_url ? <Link href={imports.next_page_url} preserveScroll className="font-semibold text-violet-700">Next →</Link> : <span />}
                        </div>
                    )}
                </section>
            </div>

            {editing && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 sm:items-center sm:p-4" onClick={() => !busy && setEditing(null)}>
                    <form onClick={(e) => e.stopPropagation()} onSubmit={saveEdit} className="w-full max-w-md rounded-t-3xl bg-white p-5 shadow-2xl sm:rounded-2xl">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h3 className="font-semibold text-slate-900">Change reporting month</h3>
                                <p className="mt-0.5 text-xs text-slate-500">{editing.original_filename}</p>
                            </div>
                            <button type="button" onClick={() => setEditing(null)} disabled={busy} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" aria-label="Close"><X className="h-4 w-4" /></button>
                        </div>
                        <input type="month" required value={editMonth} onChange={(e) => setEditMonth(e.target.value)} className="mt-4 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" />
                        <p className="mt-2 text-xs text-slate-500">Moves this report and everything it shows — card, statements, wallets and analytics — to the new month. Amounts and wallet balances do not change. A confirmation code is e-mailed before it is saved.</p>
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setEditing(null)} disabled={busy} className="h-10 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Cancel</button>
                            <button type="submit" disabled={busy || !editMonth} className="h-10 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white disabled:opacity-40">{busy ? "Saving…" : "Save"}</button>
                        </div>
                    </form>
                </div>
            )}
        </PanelLayout>
    );
}

/* W138 MIXXTUNE_REPORT_MONTH_PICKER: already-uploaded months are blurred + not clickable;
   the next month to do is shown "Suggested" and pre-selected. */
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

function MonthPicker({ value, used = [], suggested = "", onChange }) {
    const [open, setOpen] = useState(false);
    const usedSet = new Set(used);
    const base = value || suggested || new Date().toISOString().slice(0, 7);
    const [year, setYear] = useState(Number(String(base).slice(0, 4)) || new Date().getFullYear());
    const nowKey = new Date().toISOString().slice(0, 7);
    const key = (m) => `${year}-${String(m + 1).padStart(2, "0")}`;
    const choose = (k) => { onChange(k); setOpen(false); };

    return (
        <div className="relative">
            <button type="button" onClick={() => setOpen((o) => !o)} className="flex h-11 w-full items-center justify-between rounded-xl border border-slate-300 px-3 text-sm">
                <span className={value ? "font-medium text-slate-800" : "text-slate-400"}>{value ? monthName(value) : "Select month"}</span>
                <svg className="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M6 8l4 4 4-4" /></svg>
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
                    <div className="absolute z-50 mt-2 w-72 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                        <div className="flex items-center justify-between pb-2">
                            <button type="button" onClick={() => setYear((y) => y - 1)} className="rounded-lg px-2.5 py-1 text-lg leading-none text-slate-500 hover:bg-slate-100" aria-label="Previous year">‹</button>
                            <span className="text-sm font-bold text-slate-800">{year}</span>
                            <button type="button" onClick={() => setYear((y) => y + 1)} className="rounded-lg px-2.5 py-1 text-lg leading-none text-slate-500 hover:bg-slate-100" aria-label="Next year">›</button>
                        </div>
                        <div className="grid grid-cols-3 gap-1.5">
                            {MONTHS.map((label, m) => {
                                const k = key(m);
                                const isUsed = usedSet.has(k);
                                const isSuggested = k === suggested;
                                const isSelected = k === value;
                                const cls = isUsed
                                    ? "cursor-not-allowed text-slate-300 line-through opacity-50"
                                    : isSelected
                                        ? "bg-violet-600 text-white shadow-sm"
                                        : isSuggested
                                            ? "bg-violet-50 text-violet-700 ring-1 ring-violet-300 hover:bg-violet-100"
                                            : "text-slate-700 hover:bg-slate-100";
                                return (
                                    <button key={k} type="button" disabled={isUsed} onClick={() => !isUsed && choose(k)} title={isUsed ? "Already uploaded — report done for this month" : isSuggested ? "Suggested — the next month to upload" : ""} className={`relative h-10 rounded-xl text-sm font-medium transition ${cls}`}>
                                        {label}
                                        {isSuggested && !isSelected && !isUsed && (
                                            <span className="absolute -top-1.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-violet-600 px-1.5 text-[9px] font-bold uppercase leading-4 text-white">Suggested</span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                        <div className="mt-3 flex items-center justify-between">
                            <button type="button" onClick={() => onChange("")} className="rounded-lg px-1.5 py-1 text-xs font-semibold text-slate-400 hover:text-slate-600">Clear</button>
                            <button type="button" disabled={usedSet.has(nowKey)} onClick={() => { setYear(Number(nowKey.slice(0, 4))); choose(nowKey); }} className="rounded-lg px-1.5 py-1 text-xs font-semibold text-violet-600 hover:text-violet-700 disabled:opacity-40">This month</button>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
