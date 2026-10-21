import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import axios from "axios";
import { ArrowDownCircle, ArrowUpCircle, CheckCircle2, Download, History, Loader2, Plus, RotateCcw, Scale, Search, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* MIXXTUNE_ADJUSTMENTS_V2 */

const money = (v) => new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 2 }).format(Number(v || 0));

const REASONS = [
    "Royalty correction for an earlier month",
    "Advance paid to the owner",
    "Bonus / incentive",
    "Duplicate payout recovered",
    "Store refund / chargeback",
    "Opening balance from the old system",
];

const newKey = () =>
    typeof crypto !== "undefined" && typeof crypto.randomUUID === "function"
        ? crypto.randomUUID()
        : ["adj", Date.now(), Math.random().toString(36).slice(2)].join("-");

export default function Index({ adjustments = {}, summary = {}, filters = {} }) {
    const flash = usePage().props?.flash ?? {};
    const [openNew, setOpenNew] = useState(false);
    const [reverse, setReverse] = useState(null);
    const [search, setSearch] = useState(filters.search || "");
    const first = useRef(true);
    const rows = adjustments.data ?? [];

    const go = (patch) =>
        router.get("/super-admin/finance/adjustments", { ...filters, ...patch, page: undefined }, { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => go({ search }), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const query = new URLSearchParams(Object.entries(filters).filter(([, v]) => v)).toString();
    const select = "h-10 rounded-xl border border-slate-300 bg-white pl-3 pr-8 text-sm";

    return (
        <PanelLayout title="Financial Adjustments" subtitle="Manual wallet credits and debits. Mistakes are undone with Reverse, never deleted.">
            <Head title="Financial Adjustments" />

            {flash.success && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4" /> {flash.success}
                </div>
            )}

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat icon={ArrowUpCircle} label="Credits (live)" value={money(summary.credits)} tone="text-emerald-700" />
                <Stat icon={ArrowDownCircle} label="Debits (live)" value={money(summary.debits)} tone="text-red-600" />
                <Stat icon={Scale} label="Net added to wallets" value={money(summary.net)} tone={summary.net < 0 ? "text-red-600" : "text-slate-900"} />
                <Stat icon={History} label="Reversed" value={`${summary.reversed ?? 0}`} hint={`${summary.posted ?? 0} live · ${summary.reversals ?? 0} reversal entries`} />
            </div>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex flex-col gap-2 border-b border-slate-100 p-3 lg:flex-row lg:items-center">
                    <div className="relative min-w-0 flex-1">
                        <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="ADJ no., reference, reason, owner name or e-mail" className="h-10 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm" />
                    </div>
                    <div className="grid grid-cols-3 gap-2 sm:flex">
                        <select value={filters.show || ""} onChange={(e) => go({ show: e.target.value })} className={select}>
                            <option value="">All</option>
                            <option value="active">Live</option>
                            <option value="reversed">Reversed</option>
                            <option value="reversals">Reversals</option>
                        </select>
                        <select value={filters.direction || ""} onChange={(e) => go({ direction: e.target.value })} className={select}>
                            <option value="">Both</option>
                            <option value="credit">Credits</option>
                            <option value="debit">Debits</option>
                        </select>
                        <input type="month" value={filters.month || ""} onChange={(e) => go({ month: e.target.value })} className="h-10 rounded-xl border border-slate-300 px-2 text-sm" aria-label="Month" title="Month" />
                    </div>
                    <div className="flex gap-2">
                        <a href={`/super-admin/finance/adjustments/export${query ? `?${query}` : ""}`} className="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-slate-300 px-3 text-sm font-semibold text-slate-700 sm:flex-none">
                            <Download className="h-4 w-4" /> CSV
                        </a>
                        <button type="button" onClick={() => setOpenNew(true)} className="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700 sm:flex-none">
                            <Plus className="h-4 w-4" /> Adjust Balance
                        </button>
                    </div>
                </div>

                {rows.length === 0 ? (
                    <div className="px-5 py-14 text-center">
                        <div className="text-sm font-semibold text-slate-700">No adjustments here</div>
                        <div className="mt-1 text-xs text-slate-500">Press "Adjust Balance" to add or deduct money from an owner's wallet.</div>
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {rows.map((a) => <Row key={a.id} a={a} onReverse={() => setReverse(a)} />)}
                    </ul>
                )}

                {adjustments.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                        {adjustments.prev_page_url ? <Link href={adjustments.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Previous</Link> : <span />}
                        <span className="text-xs text-slate-500">Page {adjustments.current_page} of {adjustments.last_page} · {adjustments.total} entries</span>
                        {adjustments.next_page_url ? <Link href={adjustments.next_page_url} preserveScroll className="font-semibold text-violet-700">Next →</Link> : <span />}
                    </div>
                )}
            </section>

            {openNew && <AdjustModal onClose={() => setOpenNew(false)} />}
            {reverse && <ReverseModal a={reverse} onClose={() => setReverse(null)} />}
        </PanelLayout>
    );
}

function Stat({ icon: Icon, label, value, tone = "text-slate-900", hint }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-center gap-2 text-xs font-semibold text-slate-500"><Icon className="h-4 w-4" /> {label}</div>
            <div className={`mt-1 text-xl font-black ${tone}`}>{value}</div>
            {hint && <div className="text-xs text-slate-500">{hint}</div>}
        </div>
    );
}

function Row({ a, onReverse }) {
    const tx = a.wallet_transaction;
    const isReversal = !!a.reversal_of_id || a.type === "reversal";
    const canReverse = a.status === "posted" && !isReversal;
    const credit = a.direction === "credit";

    return (
        <li className={`flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-start ${a.status === "reversed" ? "bg-slate-50/70" : ""}`}>
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-bold ${credit ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"}`}>{credit ? "CREDIT" : "DEBIT"}</span>
                    {isReversal && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Reversal of {a.reversal_of?.adjustment_number ?? "an entry"}</span>}
                    {a.status === "reversed" && <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">Reversed{a.reversed_by?.name ? ` by ${a.reversed_by.name}` : ""}</span>}
                    <span className="text-xs font-semibold text-slate-500">{a.adjustment_number}</span>
                </div>
                <div className="mt-1 truncate text-sm font-semibold text-slate-900">
                    {a.user?.name ?? "—"} <span className="font-normal text-slate-500">· {a.user?.email} · {a.user?.role}</span>
                </div>
                <div className="mt-0.5 break-words text-sm text-slate-600">{a.reason}</div>
                <div className="mt-0.5 text-xs text-slate-400">
                    {a.created_at ? new Date(a.created_at).toLocaleString("en-IN", { timeZone: "Asia/Kolkata", day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" }) : ""}
                    {a.creator?.name ? ` · by ${a.creator.name}` : ""}
                    {a.reference_number ? ` · ref ${a.reference_number}` : ""}
                    {a.internal_note ? ` · note: ${a.internal_note}` : ""}
                </div>
            </div>
            <div className="flex shrink-0 items-center justify-between gap-4 sm:flex-col sm:items-end sm:gap-1">
                <div className={`whitespace-nowrap text-lg font-black ${credit ? "text-emerald-700" : "text-red-600"}`}>{credit ? "+" : "−"}{money(a.amount)}</div>
                {tx && <div className="text-right text-xs text-slate-500">wallet {money(tx.balance_before)} → <b className="text-slate-700">{money(tx.balance_after)}</b></div>}
                {canReverse && (
                    <button type="button" onClick={onReverse} className="inline-flex h-8 items-center gap-1 rounded-lg border border-red-200 px-2.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                        <RotateCcw className="h-3.5 w-3.5" /> Reverse
                    </button>
                )}
            </div>
        </li>
    );
}

function Modal({ title, onClose, children }) {
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center sm:p-4" onClick={onClose}>
            <div onClick={(e) => e.stopPropagation()} className="max-h-[92vh] w-full max-w-xl overflow-auto rounded-t-3xl bg-white p-5 shadow-xl sm:rounded-3xl sm:p-6">
                <div className="mb-4 flex items-start justify-between">
                    <h2 className="text-base font-semibold text-slate-900">{title}</h2>
                    <button type="button" onClick={onClose} className="rounded-lg p-1 hover:bg-slate-100" aria-label="Close"><X className="h-5 w-5" /></button>
                </div>
                {children}
            </div>
        </div>
    );
}

function Errors({ errors }) {
    const list = Object.entries(errors || {}).filter(([k]) => k !== "action_otp");
    if (!list.length) return null;
    return (
        <div className="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {list.map(([k, v]) => <div key={k}>{v}</div>)}
        </div>
    );
}

const input = "mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm";

function AdjustModal({ onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        idempotency_key: newKey(),
        user_id: "",
        direction: "credit",
        amount: "",
        reason: "",
        reference_number: "",
        internal_note: "",
        effective_date: "",
        type: "manual",
        currency: "INR",
        notify_owner: true,
    });
    const [q, setQ] = useState("");
    const [found, setFound] = useState([]);
    const [busy, setBusy] = useState(false);
    const [owner, setOwner] = useState(null);

    useEffect(() => {
        if (q.trim().length < 2) {
            setFound([]);
            return;
        }
        const t = setTimeout(() => {
            setBusy(true);
            axios.get("/super-admin/finance/adjustments/owners", { params: { q } }).then(({ data: r }) => setFound(r.owners || [])).finally(() => setBusy(false));
        }, 300);
        return () => clearTimeout(t);
    }, [q]);

    const amount = Number(data.amount || 0);
    const after = owner ? (data.direction === "credit" ? owner.available + amount : owner.available - amount) : null;
    const tooMuch = owner && data.direction === "debit" && amount > owner.available;

    const submit = (e) => {
        e.preventDefault();
        if (!window.confirm(`${data.direction === "credit" ? "Add" : "Deduct"} ${money(amount)} ${data.direction === "credit" ? "to" : "from"} ${owner?.name}'s wallet?\nAn e-mailed code is needed next.`)) return;
        post("/super-admin/finance/adjustments", { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal title="Adjust wallet balance" onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Errors errors={errors} />

                <div>
                    <span className="text-sm font-semibold text-slate-700">Owner (label or artist)</span>
                    {owner ? (
                        <div className="mt-1.5 flex items-center justify-between gap-2 rounded-xl border border-violet-300 bg-violet-50 px-3 py-2.5 text-sm">
                            <span className="min-w-0">
                                <b className="block truncate">{owner.name}</b>
                                <span className="block truncate text-xs text-slate-500">{owner.email} · {owner.role} · wallet {money(owner.available)}{owner.pending ? ` (+${money(owner.pending)} pending)` : ""}</span>
                            </span>
                            <button type="button" onClick={() => { setOwner(null); setData("user_id", ""); }} className="text-xs font-semibold text-violet-700 underline">Change</button>
                        </div>
                    ) : (
                        <>
                            <div className="relative">
                                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name, e-mail or client ID" className={input} autoFocus />
                                {busy && <Loader2 className="absolute right-3 top-5 h-4 w-4 animate-spin text-slate-400" />}
                            </div>
                            {found.length > 0 && (
                                <ul className="mt-1 max-h-52 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200">
                                    {found.map((o) => (
                                        <li key={o.id}>
                                            <button type="button" onClick={() => { setOwner(o); setData("user_id", o.id); }} className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50">
                                                <span className="min-w-0"><b className="block truncate">{o.name}</b><span className="block truncate text-xs text-slate-500">{o.email} · {o.role}</span></span>
                                                <span className="shrink-0 text-xs font-semibold text-slate-600">{money(o.available)}</span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-2">
                    {["credit", "debit"].map((d) => (
                        <button key={d} type="button" onClick={() => setData("direction", d)} className={`h-11 rounded-xl border text-sm font-semibold ${data.direction === d ? (d === "credit" ? "border-emerald-500 bg-emerald-50 text-emerald-700" : "border-red-400 bg-red-50 text-red-700") : "border-slate-200 text-slate-600"}`}>
                            {d === "credit" ? "+ Add money (credit)" : "− Deduct money (debit)"}
                        </button>
                    ))}
                </div>

                <label className="block">
                    <span className="text-sm font-semibold text-slate-700">Amount (₹)</span>
                    <input required min="0.01" step="0.01" type="number" inputMode="decimal" value={data.amount} onChange={(e) => setData("amount", e.target.value)} className={input} placeholder="0.00" />
                    {owner && amount > 0 && (
                        <span className={`mt-1 block text-xs ${tooMuch ? "font-semibold text-red-600" : "text-slate-500"}`}>
                            Wallet {money(owner.available)} → {money(after)}{tooMuch ? " — more than the wallet has; the debit will be refused." : ""}
                        </span>
                    )}
                </label>

                <label className="block">
                    <span className="text-sm font-semibold text-slate-700">Reason (the owner sees it)</span>
                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                        {REASONS.map((r) => (
                            <button key={r} type="button" onClick={() => setData("reason", r)} className="rounded-full border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:border-violet-300">{r}</button>
                        ))}
                    </div>
                    <textarea required rows={2} value={data.reason} onChange={(e) => setData("reason", e.target.value)} className="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                </label>

                <div className="grid gap-3 sm:grid-cols-2">
                    <label className="block">
                        <span className="text-sm font-semibold text-slate-700">Reference (optional)</span>
                        <input value={data.reference_number} onChange={(e) => setData("reference_number", e.target.value)} className={input} placeholder="Statement / invoice / UTR" />
                    </label>
                    <label className="block">
                        <span className="text-sm font-semibold text-slate-700">Effective date (optional)</span>
                        <input type="date" value={data.effective_date} onChange={(e) => setData("effective_date", e.target.value)} className={input} />
                    </label>
                </div>

                <label className="block">
                    <span className="text-sm font-semibold text-slate-700">Internal note (team only)</span>
                    <input value={data.internal_note} onChange={(e) => setData("internal_note", e.target.value)} className={input} placeholder="Optional" />
                </label>

                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={data.notify_owner} onChange={(e) => setData("notify_owner", e.target.checked)} /> Tell the owner (panel notice with the reason)
                </label>

                <p className="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">This entry can never be edited or deleted — a mistake is undone with Reverse. An e-mailed code is needed to post.</p>

                <div className="flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Cancel</button>
                    <button type="submit" disabled={processing || !data.user_id || !(amount > 0) || data.reason.trim().length < 3 || tooMuch} className={`h-11 rounded-xl px-5 text-sm font-semibold text-white disabled:opacity-40 ${data.direction === "credit" ? "bg-emerald-600" : "bg-red-600"}`}>
                        {processing ? "Posting…" : data.direction === "credit" ? `Add ${money(amount)}` : `Deduct ${money(amount)}`}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

function ReverseModal({ a, onClose }) {
    const { data, setData, post, processing, errors } = useForm({ reason: "", notify_owner: true });

    const submit = (e) => {
        e.preventDefault();
        post(`/super-admin/finance/adjustments/${a.id}/reverse`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal title={`Reverse ${a.adjustment_number}`} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Errors errors={errors} />
                <div className="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    {a.direction === "credit" ? "Takes back" : "Gives back"} <b>{money(a.amount)}</b> {a.direction === "credit" ? "from" : "to"} <b>{a.user?.name}</b>'s wallet. The original entry stays in the history, marked "Reversed".
                </div>
                <label className="block">
                    <span className="text-sm font-semibold text-slate-700">Why?</span>
                    <textarea required rows={2} value={data.reason} onChange={(e) => setData("reason", e.target.value)} className="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. Posted to the wrong owner" />
                </label>
                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={data.notify_owner} onChange={(e) => setData("notify_owner", e.target.checked)} /> Tell the owner
                </label>
                <div className="flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Cancel</button>
                    <button type="submit" disabled={processing || data.reason.trim().length < 3} className="h-11 rounded-xl bg-red-600 px-5 text-sm font-semibold text-white disabled:opacity-40">
                        {processing ? "Reversing…" : "Reverse"}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
