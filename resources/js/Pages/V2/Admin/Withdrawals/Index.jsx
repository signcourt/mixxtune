import { Head, Link, router, usePage } from "@inertiajs/react";
import { AlertTriangle, CheckCircle2, Download, FileText, Landmark, Loader2, Search, Zap } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* MIXXTUNE_WITHDRAWALS_V2 */

const TABS = [
    ["pending", "Pending"],
    ["approved", "Approved — to pay"],
    ["paid", "Paid"],
    ["rejected", "Rejected"],
    ["all", "All"],
];

const STATUS_CLS = {
    pending: "bg-amber-50 text-amber-800",
    approved: "bg-sky-50 text-sky-700",
    processing: "bg-sky-50 text-sky-700",
    paid: "bg-emerald-50 text-emerald-700",
    rejected: "bg-red-50 text-red-700",
};

const REJECT_REASONS = [
    "KYC / bank details are not verified yet",
    "Bank details do not match the account holder",
    "Amount does not match the statement — please contact support",
    "Duplicate request",
];

const inr = (v) => "₹" + new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));

export default function Index({ role = "admin", withdrawals = {}, counts = {}, totals = {}, filters = {}, payoutTools = {}, razorpay = {} }) {
    const flash = usePage().props?.flash ?? {};
    const pageErrors = usePage().props?.errors ?? {};
    const rows = withdrawals.data ?? [];
    const status = filters.status ?? "pending";
    const [search, setSearch] = useState(filters.search || "");
    const first = useRef(true);

    const go = (patch) =>
        router.get("/admin/withdrawals", { status, month: filters.month || "", search: filters.search || "", ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => go({ search }), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const exportUrl = `/admin/withdrawals/export?status=${encodeURIComponent(status === "all" ? "pending" : status)}&month=${encodeURIComponent(filters.month || "")}`;
    const readyToPay = status === "approved" ? rows.filter((r) => r.ready).length : 0;

    return (
        <PanelLayout role={role} title="Withdrawal Requests" subtitle="Approve, pay and track owner payouts">
            <Head title="Withdrawal Requests" />

            {flash.success && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4" /> {flash.success}
                </div>
            )}
            {pageErrors.razorpay && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <AlertTriangle className="h-4 w-4 shrink-0" /> {pageErrors.razorpay}
                </div>
            )}
            {/* W115 MIXXTUNE_RAZORPAY_PAYOUTS */}
            {razorpay.can && (
                <div className={`mb-4 flex flex-wrap items-center gap-2 rounded-2xl border px-4 py-3 text-sm ${razorpay.ready ? "border-violet-200 bg-violet-50 text-violet-900" : "border-slate-200 bg-white text-slate-600"}`}>
                    <Zap className="h-4 w-4 shrink-0" />
                    {razorpay.ready ? (
                        <span>
                            <b>Razorpay is connected{razorpay.live ? "" : " (TEST mode - no real money)"}.</b> "Approve &amp; Pay" sends the net amount to the owner's bank by NEFT; it turns "Paid" with the UTR by itself. Use one way per request - do not also put it in an Axis file.
                        </span>
                    ) : (
                        <span>Razorpay is not connected yet - pay by Axis file + Mark Paid as before.</span>
                    )}
                    <Link href="/admin/razorpay" className="ml-auto font-semibold text-violet-700 underline">Razorpay Payouts</Link>
                </div>
            )}

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {["pending", "approved", "paid", "rejected"].map((s) => (
                    <button
                        key={s}
                        type="button"
                        onClick={() => go({ status: s })}
                        className={`rounded-2xl border bg-white p-4 text-left shadow-sm ${status === s ? "border-violet-500 ring-2 ring-violet-100" : "border-slate-200"}`}
                    >
                        <div className="text-xs font-semibold text-slate-500">{TABS.find((t) => t[0] === s)[1]}</div>
                        <div className="mt-1 text-2xl font-black text-slate-900">{counts[s] ?? 0}</div>
                        <div className="text-xs text-slate-500">{inr(totals[s])}</div>
                    </button>
                ))}
            </div>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div className="relative sm:w-72">
                            <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="WD no., UTR, owner name or e-mail"
                                className="h-10 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <input type="month" value={filters.month || ""} onChange={(e) => go({ month: e.target.value })} className="h-10 rounded-xl border border-slate-300 px-3 text-sm" aria-label="Month" />
                            {filters.month && <button type="button" onClick={() => go({ month: "" })} className="h-10 rounded-xl border border-slate-300 px-3 text-xs font-semibold">All months</button>}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <a href={exportUrl} className="inline-flex h-10 items-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white"><Download className="h-4 w-4" /> Finance Excel</a>
                        {payoutTools.can_download && (
                            <>
                                <a href="/admin/payouts/axis-file" className="inline-flex h-10 items-center rounded-xl bg-rose-700 px-4 text-sm font-semibold text-white" title="All APPROVED requests that are ready, net after TDS">Axis Bank Payout File</a>
                                <a href="/admin/payouts/axis-file?format=csv" className="inline-flex h-10 items-center rounded-xl border border-rose-300 px-3 text-sm font-semibold text-rose-700">Axis CSV</a>
                                <a href={`/admin/payouts/tds-report?fy=${encodeURIComponent(payoutTools.financial_year ?? "")}`} className="inline-flex h-10 items-center rounded-xl bg-slate-800 px-4 text-sm font-semibold text-white">TDS Report FY {payoutTools.financial_year}</a>
                            </>
                        )}
                    </div>
                </div>

                {payoutTools.can_download && (
                    <p className="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        TDS now: {payoutTools.tds?.rate ?? 0}%
                        {Number(payoutTools.tds?.no_pan_rate ?? 0) > 0 && ` (no PAN: ${payoutTools.tds.no_pan_rate}%)`}
                        {Number(payoutTools.tds?.threshold ?? 0) > 0 ? ` · only after ₹${payoutTools.tds.threshold} per payee in a year` : " · on every payout"} — change in Master Settings.
                        Steps: <b>Approve</b> → <b>Axis Bank Payout File</b> (only ready requests) → transfer in Axis → enter UTR → <b>Mark Paid</b>.
                    </p>
                )}
            </section>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex gap-1 overflow-x-auto border-b border-slate-200 px-3 pt-3">
                    {TABS.map(([key, label]) => (
                        <button key={key} type="button" onClick={() => go({ status: key })} className={`shrink-0 border-b-2 px-3 pb-2.5 pt-1 text-sm font-semibold ${status === key ? "border-violet-600 text-violet-700" : "border-transparent text-slate-500"}`}>
                            {label} <span className="ml-1 rounded-full bg-slate-100 px-1.5 text-xs text-slate-600">{counts[key] ?? 0}</span>
                        </button>
                    ))}
                </div>

                {status === "approved" && rows.length > 0 && (
                    <p className="border-b border-slate-100 px-4 py-2.5 text-xs text-slate-600">
                        {readyToPay} of {rows.length} on this page are ready for the Axis file. Others have a problem shown in red.
                    </p>
                )}

                {rows.length === 0 ? (
                    <div className="px-5 py-14 text-center">
                        <div className="text-sm font-semibold text-slate-700">No withdrawal requests here</div>
                        <div className="mt-1 text-xs text-slate-500">Try another tab, month or search.</div>
                    </div>
                ) : (
                    <div className="space-y-3 p-3">
                        {rows.map((w) => <WithdrawalCard key={w.id} w={w} razorpay={razorpay} />)}
                    </div>
                )}

                {withdrawals.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                        {withdrawals.prev_page_url ? <Link href={withdrawals.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Previous</Link> : <span />}
                        <span className="text-xs text-slate-500">Page {withdrawals.current_page} of {withdrawals.last_page} · {withdrawals.total} requests</span>
                        {withdrawals.next_page_url ? <Link href={withdrawals.next_page_url} preserveScroll className="font-semibold text-violet-700">Next →</Link> : <span />}
                    </div>
                )}
            </section>
        </PanelLayout>
    );
}

function Info({ label, value, mono = false }) {
    return (
        <div className="min-w-0 rounded-xl bg-slate-50 px-3 py-2">
            <div className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</div>
            <div className={`mt-0.5 break-words text-sm font-semibold text-slate-900 ${mono ? "font-mono" : ""}`}>{value || "—"}</div>
        </div>
    );
}

function WithdrawalCard({ w, razorpay = {} }) {
    const [open, setOpen] = useState(false);
    const [note, setNote] = useState("");
    const [utr, setUtr] = useState("");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [notInFile, setNotInFile] = useState(false);
    const rz = w.razorpay;
    const rzRunning = Boolean(rz?.open);
    const actionable = ["pending", "approved", "processing"].includes(w.status) && !rzRunning;
    const canRazorpay = razorpay.can && razorpay.ready && ["pending", "approved"].includes(w.status) && w.ready;

    const send = (method, url, payload, confirmText) => {
        if (confirmText && !window.confirm(confirmText)) return;
        setBusy(true);
        setError("");
        const opts = {
            preserveScroll: true,
            onError: (errs) => {
                if (errs?.action_otp) return; // the e-mailed code box handles it
                setError(String(Object.values(errs ?? {})[0] ?? "Request failed."));
            },
            onSuccess: () => {
                setOpen(false);
                setNote("");
                setUtr("");
            },
            onFinish: () => setBusy(false),
        };
        if (method === "delete") router.delete(url, opts);
        else router.post(url, payload, opts);
    };

    const d = w.deduction;

    return (
        <article className={`rounded-2xl border bg-white ${actionable && !w.ready ? "border-red-200" : "border-slate-200"}`}>
            <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-bold text-slate-900">{w.request_number}</span>
                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${STATUS_CLS[w.status] ?? "bg-slate-100 text-slate-600"}`}>{w.status}</span>
                        {w.waiting_days > 2 && <span className="text-xs font-semibold text-red-600">waiting {w.waiting_days} days</span>}
                    </div>
                    <div className="mt-0.5 truncate text-sm text-slate-600">
                        {w.user?.name ?? "—"} <span className="text-slate-400">· {w.user?.email}{w.user?.client_id ? ` · ${w.user.client_id}` : ""}</span>
                    </div>
                    <div className="mt-0.5 text-xs text-slate-500">
                        Requested {w.dates?.requested}
                        {w.dates?.approved ? ` · approved ${w.dates.approved}${w.approved_by_name ? ` by ${w.approved_by_name}` : ""}` : ""}
                        {w.dates?.paid ? ` · paid ${w.dates.paid}` : ""}
                        {w.dates?.rejected ? ` · rejected ${w.dates.rejected}` : ""}
                    </div>
                </div>
                <div className="shrink-0 sm:text-right">
                    <div className="text-xl font-black text-slate-900">{inr(w.amount)}</div>
                    {d && <div className="text-xs text-slate-500">{w.status === "paid" ? "net paid" : "net to transfer"} <b className="text-slate-800">{inr(d.net)}</b></div>}
                </div>
            </div>

            {actionable && w.problems?.length > 0 && (
                <div className="mx-4 mb-3 flex items-start gap-2 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-700">
                    <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    <span><b>Cannot be paid yet:</b> {w.problems.join(" · ")}</span>
                </div>
            )}

            {rz && (rzRunning || w.status !== "paid") && (
                <div className={`mx-4 mb-3 flex items-start gap-2 rounded-xl px-3 py-2 text-xs ${rzRunning ? "bg-violet-50 text-violet-800" : rz.status === "processed" ? "bg-emerald-50 text-emerald-800" : "bg-red-50 text-red-700"}`}>
                    {rzRunning ? <Loader2 className="mt-0.5 h-3.5 w-3.5 shrink-0 animate-spin" /> : <Landmark className="mt-0.5 h-3.5 w-3.5 shrink-0" />}
                    <span>
                        <b>Razorpay {inr(rz.amount)} · {rz.status}</b> ({rz.at})
                        {rzRunning && " - the bank is paying; it turns Paid with the UTR by itself."}
                        {rz.reason && ` · ${rz.reason}`}
                    </span>
                </div>
            )}

            <div className="grid grid-cols-2 gap-2 px-4 pb-4 sm:grid-cols-4">
                <Info label="KYC" value={w.kyc_status} />
                <Info label="Account" value={`${w.payout_profile?.masked_bank_account ?? "—"}${w.ifsc ? ` · ${w.ifsc}` : ""}`} mono />
                <Info label="Holder" value={w.holder} />
                <Info label="Wallet now" value={inr(w.owner_available)} />
                {d && (
                    <>
                        <Info label="GST added" value={inr(d.gst_amount)} />
                        <Info label={`TDS ${d.tds_percent}%`} value={inr(d.tds_amount)} />
                    </>
                )}
                {w.payment_reference && <Info label="UTR / reference" value={w.payment_reference} mono />}
                {w.status === "rejected" && w.rejection_reason && <div className="col-span-2 sm:col-span-4"><Info label="Rejection reason" value={w.rejection_reason} /></div>}
                {w.admin_note && <div className="col-span-2 sm:col-span-4"><Info label="Manager note" value={w.admin_note} /></div>}
                {w.request_note && <div className="col-span-2 sm:col-span-4"><Info label="Owner's note" value={w.request_note} /></div>}
            </div>

            <div className="flex flex-wrap gap-2 border-t border-slate-100 px-4 py-3">
                {w.invoice?.id && (
                    <a href={`/invoices/${w.invoice.id}/download`} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700">
                        <FileText className="h-3.5 w-3.5" /> Payout document {w.invoice.invoice_number}
                    </a>
                )}
                {actionable && !open && (
                    <button type="button" onClick={() => setOpen(true)} className="h-9 rounded-lg bg-violet-600 px-4 text-xs font-semibold text-white">
                        {canRazorpay ? (w.status === "pending" ? "Approve & Pay / Reject" : "Pay / Reject") : w.status === "pending" ? "Approve / Reject" : "Mark Paid / Reject"}
                    </button>
                )}
            </div>

            {open && (
                <div className="space-y-3 border-t border-slate-100 bg-slate-50/60 px-4 py-4">
                    {w.status === "approved" || w.status === "processing" ? (
                        <label className="block">
                            <span className="text-xs font-semibold text-slate-600">Bank reference (UTR) after the transfer</span>
                            <input value={utr} onChange={(e) => setUtr(e.target.value.trim())} placeholder="e.g. AXISN26294012345" className="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 font-mono text-sm" />
                        </label>
                    ) : null}

                    <div className="flex flex-wrap gap-1.5">
                        {REJECT_REASONS.map((r) => (
                            <button key={r} type="button" onClick={() => setNote(r)} className="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-600 hover:border-red-300">{r}</button>
                        ))}
                    </div>
                    <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={2} placeholder="Note (needed to reject — the owner sees it)" className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />

                    {error && <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">{error}</div>}
                    {canRazorpay && w.metadata?.bank_file_at && (
                        <label className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            <input type="checkbox" checked={notInFile} onChange={(e) => setNotInFile(e.target.checked)} className="mt-0.5" />
                            <span>This request was put in an Axis bank file. Tick only if it was <b>not</b> paid through that file - otherwise use Mark Paid with the bank's UTR.</span>
                        </label>
                    )}

                    <div className="flex flex-wrap gap-2">
                        {canRazorpay && (
                            <button
                                type="button"
                                disabled={busy}
                                onClick={() =>
                                    send(
                                        "post",
                                        `/admin/withdrawals/${w.id}/razorpay-pay`,
                                        { admin_note: note, not_in_bank_file: notInFile ? 1 : 0 },
                                        `${w.status === "pending" ? "Approve and send" : "Send"} ${inr(d?.net ?? w.amount)} to ${w.holder || "the owner"} (${w.payout_profile?.masked_bank_account ?? ""} · ${w.ifsc ?? ""}) by NEFT through Razorpay${razorpay.live ? "" : " (TEST mode)"}?\n\nThe money leaves the RazorpayX account right away.`,
                                    )
                                }
                                className="inline-flex h-10 items-center gap-1.5 rounded-xl bg-violet-700 px-4 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                <Zap className="h-4 w-4" /> {w.status === "pending" ? "Approve & Pay" : "Pay"} via Razorpay
                            </button>
                        )}
                        {w.status === "pending" && (
                            <button type="button" disabled={busy} onClick={() => send("post", `/admin/withdrawals/${w.id}/approve`, { admin_note: note })} className="h-10 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white disabled:opacity-50">
                                {canRazorpay ? "Approve only" : "Approve"}
                            </button>
                        )}
                        {["approved", "processing"].includes(w.status) && (
                            <button
                                type="button"
                                disabled={busy || utr.length < 3}
                                onClick={() => send("post", `/admin/withdrawals/${w.id}/paid`, { payment_reference: utr, admin_note: note }, `Mark ${w.request_number} (${inr(d?.net ?? w.amount)}) as PAID with UTR ${utr}?\n\nOnly do this after the money has left the bank.`)}
                                className="h-10 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white disabled:opacity-40"
                            >
                                Mark Paid
                            </button>
                        )}
                        <button
                            type="button"
                            disabled={busy || note.trim().length < 3}
                            onClick={() => send("post", `/admin/withdrawals/${w.id}/reject`, { rejection_reason: note }, `Reject ${w.request_number}? ${inr(w.amount)} goes back to the owner's wallet.`)}
                            className="h-10 rounded-xl border border-red-300 bg-white px-4 text-sm font-semibold text-red-700 disabled:opacity-40"
                        >
                            Reject
                        </button>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => send("delete", `/admin/withdrawals/${w.id}`, {}, `Delete ${w.request_number}? Use only for a request made by mistake. ${inr(w.amount)} goes back to the wallet.`)}
                            className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 disabled:opacity-50"
                        >
                            Delete
                        </button>
                        <button type="button" onClick={() => setOpen(false)} className="h-10 rounded-xl px-3 text-sm font-semibold text-slate-500">Cancel</button>
                    </div>
                </div>
            )}
        </article>
    );
}
