import { Head, Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, Bell, CheckCircle2, Eye, History, Loader2, Search, ShieldCheck, XCircle } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* MIXXTUNE_KYC_REVIEW_V1 */

const TABS = [
    ["submitted", "Needs review"],
    ["pending", "Not submitted"],
    ["missing", "No payout profile"],
    ["verified", "Verified"],
    ["rejected", "Rejected"],
    ["all", "All profiles"],
];

const STATUS = {
    submitted: ["Needs review", "bg-amber-50 text-amber-800"],
    verified: ["Verified", "bg-emerald-50 text-emerald-700"],
    rejected: ["Rejected", "bg-red-50 text-red-700"],
    pending: ["Not submitted", "bg-slate-100 text-slate-600"],
};

const money = (v) => "₹" + new Intl.NumberFormat("en-IN", { maximumFractionDigits: 2 }).format(Number(v || 0));

export default function Index({ role = "admin", filters = {}, counts = {}, profiles = null, missing = null, rejectReasons = [] }) {
    const flash = usePage().props?.flash ?? {};
    const [search, setSearch] = useState(filters.search || "");
    const first = useRef(true);

    const go = (patch) => router.get("/v2/admin/kyc", { ...filters, ...patch, page: undefined }, { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => go({ search }), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const page = filters.status === "missing" ? missing : profiles;
    const rows = page?.data ?? [];

    const remind = (ids) => {
        if (!ids.length) return;
        if (!window.confirm(`Send a "complete your KYC" notice to ${ids.length} owner(s)?`)) return;
        router.post("/v2/admin/kyc/remind", { user_ids: ids }, { preserveScroll: true });
    };

    return (
        <PanelLayout role={role} title="KYC Reviews" subtitle="Check payout and tax details before payouts are allowed">
            <Head title="KYC Reviews" />

            {flash.success && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4" /> {flash.success}
                </div>
            )}

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <Stat label="Needs review" value={counts.submitted} tone="amber" active={filters.status === "submitted"} onClick={() => go({ status: "submitted" })} />
                <Stat label="Not submitted yet" value={counts.pending} active={filters.status === "pending"} onClick={() => go({ status: "pending" })} />
                <Stat label="No payout profile" value={counts.missing} tone={counts.missing ? "red" : ""} active={filters.status === "missing"} onClick={() => go({ status: "missing" })} />
                <Stat label="Verified" value={counts.verified} tone="green" active={filters.status === "verified"} onClick={() => go({ status: "verified" })} />
                <Stat label="Rejected" value={counts.rejected} active={filters.status === "rejected"} onClick={() => go({ status: "rejected" })} />
            </div>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex gap-1 overflow-x-auto border-b border-slate-200 px-3 pt-3">
                    {TABS.map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => go({ status: key })}
                            className={`shrink-0 border-b-2 px-3 pb-2.5 pt-1 text-sm font-semibold ${filters.status === key ? "border-violet-600 text-violet-700" : "border-transparent text-slate-500 hover:text-slate-700"}`}
                        >
                            {label} <span className="ml-1 rounded-full bg-slate-100 px-1.5 text-xs text-slate-600">{counts[key] ?? 0}</span>
                        </button>
                    ))}
                </div>

                <div className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center">
                    <div className="relative min-w-0 flex-1">
                        <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={filters.status === "missing" ? "Name, e-mail or client ID" : "Name, e-mail, client ID, holder name or IFSC"}
                            className="h-10 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm"
                        />
                    </div>
                    <select value={filters.role || ""} onChange={(e) => go({ role: e.target.value })} className="h-10 rounded-xl border border-slate-300 bg-white pl-3 pr-8 text-sm">
                        <option value="">Labels + artists</option>
                        <option value="label">Labels</option>
                        <option value="artist">Artists</option>
                    </select>
                    {["pending", "missing"].includes(filters.status) && rows.length > 0 && (
                        <button
                            type="button"
                            onClick={() => remind(rows.map((r) => r.user_id))}
                            className="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700"
                        >
                            <Bell className="h-4 w-4" /> Remind all on this page ({rows.length})
                        </button>
                    )}
                </div>

                {filters.status === "missing" && (
                    <p className="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500">
                        Active labels / artists with no payout profile. They cannot be paid until a Super Admin adds bank + PAN on the user page.
                    </p>
                )}

                {rows.length === 0 ? (
                    <div className="border-t border-slate-100 px-5 py-14 text-center">
                        <div className="text-sm font-semibold text-slate-700">Nothing here</div>
                        <div className="mt-1 text-xs text-slate-500">
                            {filters.status === "submitted" ? "No KYC is waiting for review. 🎉" : "Try another tab or clear the search."}
                        </div>
                    </div>
                ) : filters.status === "missing" ? (
                    <ul className="divide-y divide-slate-100 border-t border-slate-100">
                        {rows.map((u) => (
                            <li key={u.user_id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0">
                                    <div className="truncate text-sm font-semibold text-slate-900">{u.name}</div>
                                    <div className="truncate text-xs text-slate-500">
                                        {u.email} · {u.role}{u.client_id ? ` · ${u.client_id}` : ""} · wallet {money(u.available)}
                                    </div>
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    <button type="button" onClick={() => remind([u.user_id])} className="h-9 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700">Remind</button>
                                    {role === "super_admin" && (
                                        <a href={`/v2/admin/users/${u.user_id}/edit`} className="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white">Add bank + PAN</a>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div className="space-y-3 border-t border-slate-100 p-3">
                        {rows.map((p) => <ProfileCard key={p.id} p={p} role={role} reasons={rejectReasons} onRemind={() => remind([p.user_id])} />)}
                    </div>
                )}

                {page?.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                        {page.prev_page_url ? <Link href={page.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Previous</Link> : <span />}
                        <span className="text-xs text-slate-500">Page {page.current_page} of {page.last_page}</span>
                        {page.next_page_url ? <Link href={page.next_page_url} preserveScroll className="font-semibold text-violet-700">Next →</Link> : <span />}
                    </div>
                )}
            </section>
        </PanelLayout>
    );
}

function Stat({ label, value = 0, tone = "", active, onClick }) {
    const color = { amber: "text-amber-700", red: "text-red-600", green: "text-emerald-700" }[tone] ?? "text-slate-900";
    return (
        <button type="button" onClick={onClick} className={`rounded-2xl border bg-white p-4 text-left shadow-sm ${active ? "border-violet-500 ring-2 ring-violet-100" : "border-slate-200"}`}>
            <div className="text-xs font-semibold text-slate-500">{label}</div>
            <div className={`mt-1 text-2xl font-black ${color}`}>{value}</div>
        </button>
    );
}

function Row({ label, value, mono = false }) {
    return (
        <div className="min-w-0">
            <div className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</div>
            <div className={`mt-0.5 break-words text-sm text-slate-800 ${mono ? "font-mono" : ""}`}>{value || "—"}</div>
        </div>
    );
}

function ProfileCard({ p, role, reasons, onRemind }) {
    const errors = usePage().props?.errors ?? {};
    const [open, setOpen] = useState(p.kyc_status === "submitted");
    const [note, setNote] = useState("");
    const [override, setOverride] = useState(false);
    const [busy, setBusy] = useState(false);
    const [full, setFull] = useState(null);
    const [hist, setHist] = useState(null);
    const [tried, setTried] = useState(false);

    const [label, cls] = STATUS[p.kyc_status] ?? STATUS.pending;
    const fails = p.summary?.fail ?? 0;
    const warns = p.summary?.warn ?? 0;

    const decide = (status) => {
        if (status !== "verified" && !note.trim()) {
            window.alert("Write the reason first — the owner will see it.");
            return;
        }
        if (status === "verified" && fails > 0 && (!override || !note.trim())) {
            window.alert('Some checks failed. Tick "Verify anyway" and write why, or fix the details first.');
            return;
        }
        setBusy(true);
        setTried(true);
        router.post(`/v2/admin/kyc/${p.id}/verify`, { status, kyc_notes: note, override }, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setNote("") });
    };

    const reveal = () => {
        if (full) {
            setFull(null);
            return;
        }
        axios.post(`/v2/admin/kyc/${p.id}/reveal`).then(({ data }) => setFull(data));
    };

    const loadHistory = () => {
        if (hist) {
            setHist(null);
            return;
        }
        axios.get(`/v2/admin/kyc/${p.id}/history`).then(({ data }) => setHist(data.items || []));
    };

    return (
        <article className={`rounded-2xl border ${fails ? "border-red-200" : "border-slate-200"} bg-white`}>
            <button type="button" onClick={() => setOpen((o) => !o)} className="flex w-full flex-col gap-2 px-4 py-3 text-left sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="truncate text-sm font-semibold text-slate-900">{p.user?.name ?? "—"}</span>
                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${cls}`}>{label}</span>
                        {fails > 0 && <span className="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700"><XCircle className="h-3.5 w-3.5" />{fails} problem{fails > 1 ? "s" : ""}</span>}
                        {warns > 0 && <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800"><AlertTriangle className="h-3.5 w-3.5" />{warns} to check</span>}
                        {p.waiting_days > 2 && <span className="text-xs font-semibold text-red-600">waiting {p.waiting_days} days</span>}
                    </div>
                    <div className="mt-0.5 truncate text-xs text-slate-500">
                        {p.user?.email} · {p.user?.role} · holder {p.account_holder_name || "—"} · {p.ifsc_code || "no IFSC"}
                    </div>
                </div>
                <div className="shrink-0 text-xs text-slate-500 sm:text-right">
                    wallet <b className="text-slate-800">{money(p.available)}</b>
                    {p.pending_withdrawals > 0 && <div className="font-semibold text-amber-700">{p.pending_withdrawals} withdrawal(s) waiting · {money(p.pending_withdrawal_total)}</div>}
                </div>
            </button>

            {open && (
                <div className="border-t border-slate-100 px-4 py-4">
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <Row label="Account holder" value={p.account_holder_name} />
                            <Row label="Account no." value={full ? full.account : p.masked_bank_account} mono />
                            <Row label="IFSC" value={p.ifsc_code} mono />
                            <Row label="Bank / branch" value={[p.bank_name, p.branch_name].filter(Boolean).join(", ")} />
                            <Row label="PAN" value={full ? full.pan : p.masked_pan} mono />
                            <Row label="GST" value={p.gst_number} mono />
                            <div className="col-span-2 sm:col-span-3"><Row label="Address" value={p.address} /></div>
                            <Row label="Phone" value={p.user?.phone} />
                            <Row label="Client ID" value={p.user?.client_id} />
                            <Row label="Updated" value={p.updated_at} />
                            {p.verified_at && <Row label="Verified" value={`${p.verified_at}${p.verified_by ? ` by ${p.verified_by}` : ""}`} />}
                        </div>

                        <div>
                            <div className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Automatic checks</div>
                            <ul className="mt-1.5 space-y-1.5">
                                {p.checks.map((c) => (
                                    <li key={c.key} className="flex items-start gap-2 text-xs">
                                        {c.state === "ok" ? (
                                            <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600" />
                                        ) : c.state === "warn" ? (
                                            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-600" />
                                        ) : (
                                            <XCircle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-red-600" />
                                        )}
                                        <span>
                                            <b className="text-slate-800">{c.label}</b>
                                            {c.detail && <span className="text-slate-500"> — {c.detail}</span>}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>

                    {p.kyc_notes && (
                        <div className="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600"><b>Last note:</b> {p.kyc_notes}</div>
                    )}

                    <div className="mt-4 flex flex-wrap gap-2">
                        {role === "super_admin" && (
                            <>
                                <button type="button" onClick={reveal} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700">
                                    <Eye className="h-3.5 w-3.5" /> {full ? "Hide full numbers" : "Show full PAN / account"}
                                </button>
                                <a href={`/v2/admin/users/${p.user_id}/edit`} className="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700">Edit details</a>
                            </>
                        )}
                        <button type="button" onClick={loadHistory} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700">
                            <History className="h-3.5 w-3.5" /> History
                        </button>
                        {p.kyc_status !== "verified" && (
                            <button type="button" onClick={onRemind} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700">
                                <Bell className="h-3.5 w-3.5" /> Remind owner
                            </button>
                        )}
                    </div>

                    {hist && (
                        <ul className="mt-3 space-y-1 rounded-xl bg-slate-50 p-3 text-xs">
                            {hist.length === 0 && <li className="text-slate-500">No history yet.</li>}
                            {hist.map((h, i) => (
                                <li key={i} className="text-slate-600"><b>{h.at}</b> · {h.by || "system"} · {h.text}</li>
                            ))}
                        </ul>
                    )}

                    <div className="mt-4 rounded-xl border border-slate-200 p-3">
                        <div className="flex items-center gap-2 text-sm font-semibold text-slate-800"><ShieldCheck className="h-4 w-4 text-violet-600" /> Decision</div>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {reasons.map((r) => (
                                <button key={r} type="button" onClick={() => setNote((n) => (n ? `${n} ${r}.` : `${r}.`))} className="rounded-full border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:border-red-300 hover:text-red-700">
                                    {r}
                                </button>
                            ))}
                        </div>
                        <textarea
                            rows={2}
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            placeholder="Note for the owner (needed to reject or send back; optional to verify)"
                            className="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                        />
                        {tried && errors.kyc_notes && <div className="mt-1 text-xs font-medium text-red-600">{errors.kyc_notes}</div>}
                        {fails > 0 && (
                            <label className="mt-2 flex items-center gap-2 text-xs font-semibold text-red-700">
                                <input type="checkbox" checked={override} onChange={(e) => setOverride(e.target.checked)} /> Verify anyway (I checked the documents myself — write why above)
                            </label>
                        )}
                        <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                            <button type="button" disabled={busy} onClick={() => decide("verified")} className="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white disabled:opacity-50">
                                {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />} {p.kyc_status === "verified" ? "Verify again" : "Verify"}
                            </button>
                            <button type="button" disabled={busy} onClick={() => decide("rejected")} className="h-10 rounded-xl border border-red-300 px-4 text-sm font-semibold text-red-700 disabled:opacity-50">
                                {p.kyc_status === "verified" ? "Remove verification (reject)" : "Reject"}
                            </button>
                            <button type="button" disabled={busy} onClick={() => decide("pending")} className="h-10 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 disabled:opacity-50">
                                Send back to owner
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </article>
    );
}
