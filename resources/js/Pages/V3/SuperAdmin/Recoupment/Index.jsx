import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import axios from "axios";
import { BadgeIndianRupee, Ban, CheckCircle2, Download, FileText, History, Loader2, Pencil, Plus, ReceiptIndianRupee, Search, TrendingDown, Upload, WalletCards, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* MIXXTUNE_RECOUPMENT_V2 */

const money = (v) => new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 2 }).format(Number(v || 0));
const pct = (v) => `${Number(v || 0).toLocaleString("en-IN", { maximumFractionDigits: 2 })}%`;
const day = (v) => (v ? new Date(v).toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" }) : "—");
const today = () => new Date().toISOString().slice(0, 10);

const CATEGORIES = {
    advance: "Advance",
    youtube_promotion: "YouTube promotion",
    meta_ads: "Meta / Instagram ads",
    google_ads: "Google ads",
    music_video_promotion: "Music video promotion",
    influencer_promotion: "Influencer promotion",
    pr_media: "PR / media",
    artwork_production: "Artwork / production",
    marketing: "Marketing",
    distribution_expense: "Distribution cost",
    legal_expense: "Legal cost",
    other: "Other",
};

const DOC_TYPES = {
    signed_agreement: "Signed agreement",
    advance_agreement: "Advance agreement",
    invoice: "Invoice",
    payment_receipt: "Payment receipt",
    bank_proof: "Bank proof",
    utr_proof: "UTR proof",
    addendum: "Addendum",
    other: "Other",
};

const STATUS = {
    active: ["Recovering", "bg-amber-50 text-amber-800"],
    completed: ["Fully recovered", "bg-emerald-50 text-emerald-700"],
    cancelled: ["Cancelled", "bg-slate-100 text-slate-600"],
};

const input = "mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm";
const owner = (p) => p.label?.name ?? p.artist?.stage_name ?? p.artist?.legal_name ?? p.user?.name ?? "—";
const scopeText = (p) => (p.label ? `Label: ${p.label.name}` : p.artist ? `Artist: ${p.artist.stage_name || p.artist.legal_name}` : "Whole account");

export default function Index({ plans = {}, summary = {}, filters = {} }) {
    const flash = usePage().props?.flash ?? {};
    const [search, setSearch] = useState(filters.search || "");
    const [openNew, setOpenNew] = useState(false);
    const [detail, setDetail] = useState(null);
    const [loadingId, setLoadingId] = useState(null);
    const first = useRef(true);
    const rows = plans.data ?? [];

    const go = (patch) => router.get("/super-admin/finance/recoupment", { ...filters, ...patch, page: undefined }, { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => go({ search }), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const openDetail = async (id, tab = "costs") => {
        setLoadingId(id);
        try {
            const { data } = await axios.get(`/super-admin/finance/recoupment/${id}`);
            setDetail({ plan: data.data, tab });
        } catch (e) {
            window.alert("Could not load the plan. Please refresh the page.");
        } finally {
            setLoadingId(null);
        }
    };

    const refresh = () => {
        if (!detail) return;
        axios.get(`/super-admin/finance/recoupment/${detail.plan.id}`).then(({ data }) => setDetail((d) => d && { ...d, plan: data.data }));
    };

    const query = new URLSearchParams(Object.entries(filters).filter(([, v]) => v)).toString();

    return (
        <PanelLayout title="Advances & Recoupment" subtitle="Money given to an owner in advance (or costs paid for them) and taken back from their royalties.">
            <Head title="Advances & Recoupment" />

            {flash.success && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4" /> {flash.success}
                </div>
            )}

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat icon={ReceiptIndianRupee} label="Given / to recover" value={money(summary.total_recoverable)} hint="active + fully recovered plans" />
                <Stat icon={BadgeIndianRupee} label="Recovered" value={money(summary.total_recovered)} tone="text-emerald-700" />
                <Stat icon={TrendingDown} label="Still outstanding" value={money(summary.outstanding)} tone="text-amber-700" hint={summary.written_off > 0 ? `${money(summary.written_off)} written off (cancelled)` : null} />
                <Stat icon={WalletCards} label="Plans recovering" value={`${summary.active_plans ?? 0}`} hint={`${summary.completed_plans ?? 0} fully recovered`} />
            </div>

            {summary.empty_plans > 0 && (
                <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
                    {summary.empty_plans} active plan{summary.empty_plans > 1 ? "s have" : " has"} nothing to recover yet (₹0). Open it and press "Add cost" to enter the advance.
                </div>
            )}

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex flex-col gap-2 border-b border-slate-100 p-3 lg:flex-row lg:items-center">
                    <div className="relative min-w-0 flex-1">
                        <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Plan no., title, owner, label, artist, e-mail" className="h-10 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm" />
                    </div>
                    <div className="flex gap-2">
                        <select value={filters.status || ""} onChange={(e) => go({ status: e.target.value })} className="h-10 min-w-0 flex-1 rounded-xl border border-slate-300 bg-white pl-3 pr-8 text-sm sm:flex-none">
                            <option value="">All</option>
                            <option value="active">Recovering</option>
                            <option value="completed">Fully recovered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <a href={`/super-admin/finance/recoupment/export${query ? `?${query}` : ""}`} className="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 px-3 text-sm font-semibold text-slate-700">
                            <Download className="h-4 w-4" /> CSV
                        </a>
                        <button type="button" onClick={() => setOpenNew(true)} className="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl bg-violet-600 px-3 text-sm font-semibold text-white hover:bg-violet-700 sm:px-4">
                            <Plus className="h-4 w-4" /> Add Advance
                        </button>
                    </div>
                </div>

                {rows.length === 0 ? (
                    <div className="px-5 py-14 text-center">
                        <WalletCards className="mx-auto h-10 w-10 text-slate-300" />
                        <div className="mt-3 text-sm font-semibold text-slate-700">No plans here</div>
                        <div className="mx-auto mt-1 max-w-md text-xs text-slate-500">
                            When you pay an owner in advance, or pay a promotion / video cost for them, press "Add Advance". The amount is then taken back from their royalties at the % you choose.
                        </div>
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {rows.map((p) => <Row key={p.id} p={p} busy={loadingId === p.id} onOpen={(tab) => openDetail(p.id, tab)} />)}
                    </ul>
                )}

                {plans.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                        {plans.prev_page_url ? <Link href={plans.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Previous</Link> : <span />}
                        <span className="text-xs text-slate-500">Page {plans.current_page} of {plans.last_page} · {plans.total} plans</span>
                        {plans.next_page_url ? <Link href={plans.next_page_url} preserveScroll className="font-semibold text-violet-700">Next →</Link> : <span />}
                    </div>
                )}
            </section>

            {openNew && <NewPlanModal onClose={() => setOpenNew(false)} />}
            {detail && <DetailModal plan={detail.plan} tab={detail.tab} setTab={(t) => setDetail((d) => ({ ...d, tab: t }))} onClose={() => setDetail(null)} onChanged={refresh} />}
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

function Bar({ total, done }) {
    const w = total > 0 ? Math.min(100, (done / total) * 100) : 0;
    return (
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
            <div className="h-full rounded-full bg-emerald-500" style={{ width: `${w}%` }} />
        </div>
    );
}

function Row({ p, busy, onOpen }) {
    const [label, cls] = STATUS[p.status] ?? STATUS.cancelled;
    const r = p.rates ?? {};
    const total = Number(p.total_recoverable_amount || 0);

    return (
        <li className="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-bold ${cls}`}>{label}</span>
                    <span className="text-xs font-semibold text-slate-500">{p.plan_number}</span>
                    {p.status === "active" && total <= 0 && <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">₹0 to recover — add the advance</span>}
                </div>
                <div className="mt-1 truncate text-sm font-semibold text-slate-900">
                    {owner(p)} <span className="font-normal text-slate-500">· {p.user?.email} · {scopeText(p)}</span>
                </div>
                <div className="mt-0.5 truncate text-sm text-slate-600">{p.title}</div>
                <div className="mt-0.5 text-xs text-slate-500">
                    Recovery <b className="text-amber-700">{pct(r.recovery)}</b> of revenue · owner share {pct(r.base)}{r.running ? <> → <b className="text-violet-700">{pct(r.owner_now)}</b> while repaying</> : null} · since {day(p.starts_on)}
                </div>
            </div>
            <div className="w-full shrink-0 lg:w-64">
                <div className="flex items-baseline justify-between text-xs text-slate-500">
                    <span>Recovered {money(p.total_recovered_amount)}</span>
                    <span>of {money(total)}</span>
                </div>
                <div className="mt-1"><Bar total={total} done={Number(p.total_recovered_amount || 0)} /></div>
                <div className="mt-1 text-right text-sm font-bold text-amber-700">{money(p.outstanding_amount)} left</div>
            </div>
            <div className="flex shrink-0 flex-wrap gap-1.5">
                <button type="button" disabled={busy} onClick={() => onOpen("costs")} className="inline-flex h-9 items-center gap-1 rounded-lg bg-violet-600 px-3 text-xs font-semibold text-white disabled:opacity-50">
                    {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <ReceiptIndianRupee className="h-3.5 w-3.5" />} Open
                </button>
                <button type="button" disabled={busy} onClick={() => onOpen("documents")} className="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-600" title="Documents">
                    <FileText className="h-3.5 w-3.5" /> {p.documents_count ?? 0}
                </button>
                <button type="button" disabled={busy} onClick={() => onOpen("recoveries")} className="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-600" title="Recoveries">
                    <History className="h-3.5 w-3.5" /> {p.recoveries_count ?? 0}
                </button>
            </div>
        </li>
    );
}

function Modal({ title, onClose, children, wide = false }) {
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center sm:p-4" onClick={onClose}>
            <div onClick={(e) => e.stopPropagation()} className={`max-h-[92vh] w-full ${wide ? "max-w-3xl" : "max-w-xl"} overflow-auto rounded-t-3xl bg-white p-5 shadow-xl sm:rounded-3xl sm:p-6`}>
                <div className="mb-4 flex items-start justify-between gap-3">
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
        <div className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {list.map(([k, v]) => <div key={k}>{v}</div>)}
        </div>
    );
}

function Field({ label, hint, children }) {
    return (
        <label className="block">
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            {children}
            {hint && <span className="mt-1 block text-xs text-slate-500">{hint}</span>}
        </label>
    );
}

/* Shows what the rates mean on ₹1,000 of store revenue. */
function RateExample({ base, recovery, max }) {
    const b = Number(base);
    let r = Number(recovery);
    if (max !== "" && max !== null && max !== undefined) r = Math.min(r, Number(max));
    r = Math.min(r, b);
    if (!(b > 0) || !(r >= 0) || Number.isNaN(r)) return null;
    return (
        <div className="rounded-xl bg-violet-50 px-3 py-2 text-xs text-violet-900">
            On <b>₹1,000</b> store revenue: owner normally gets <b>{money(b * 10)}</b>. While repaying, <b>{money(r * 10)}</b> goes to the advance and <b>{money((b - r) * 10)}</b> to the owner's wallet.
        </div>
    );
}

function RateFields({ data, setData }) {
    return (
        <>
            <div className="grid grid-cols-3 gap-2">
                <Field label="Owner share %">
                    <input required type="number" min="0" max="100" step="0.01" value={data.base_percentage} onChange={(e) => setData("base_percentage", e.target.value)} className={input} placeholder="e.g. 80" />
                </Field>
                <Field label="Recovery %">
                    <input required type="number" min="0" max="100" step="0.01" value={data.recovery_uplift_percentage} onChange={(e) => setData("recovery_uplift_percentage", e.target.value)} className={input} placeholder="e.g. 10" />
                </Field>
                <Field label="Max %">
                    <input type="number" min="0" max="100" step="0.01" value={data.maximum_recovery_percentage} onChange={(e) => setData("maximum_recovery_percentage", e.target.value)} className={input} placeholder="optional" />
                </Field>
            </div>
            <p className="-mt-2 text-xs text-slate-500">Owner share = the owner's % of store revenue in their agreement. Recovery = how many of those % points go to repay the advance.</p>
            <RateExample base={data.base_percentage} recovery={data.recovery_uplift_percentage} max={data.maximum_recovery_percentage} />
        </>
    );
}

function NewPlanModal({ onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        user_id: "",
        label_id: "",
        artist_id: "",
        title: "",
        initial_amount: "",
        initial_category: "advance",
        initial_title: "",
        reference_number: "",
        expense_date: today(),
        base_percentage: "",
        recovery_uplift_percentage: "",
        maximum_recovery_percentage: "",
        starts_on: today(),
        notes: "",
        notify_owner: true,
    });
    const [q, setQ] = useState("");
    const [found, setFound] = useState([]);
    const [busy, setBusy] = useState(false);
    const [picked, setPicked] = useState(null);

    useEffect(() => {
        if (q.trim().length < 2) {
            setFound([]);
            return;
        }
        const t = setTimeout(() => {
            setBusy(true);
            axios.get("/super-admin/finance/recoupment/owners", { params: { q } }).then(({ data: r }) => setFound(r.owners || [])).finally(() => setBusy(false));
        }, 300);
        return () => clearTimeout(t);
    }, [q]);

    const pick = (o) => {
        setPicked(o);
        setData((d) => ({ ...d, user_id: o.id, label_id: "", artist_id: "", title: d.title || `Advance - ${o.name}` }));
    };

    const scope = data.label_id ? `l:${data.label_id}` : data.artist_id ? `a:${data.artist_id}` : "";
    const setScope = (v) => setData((d) => ({ ...d, label_id: v.startsWith("l:") ? v.slice(2) : "", artist_id: v.startsWith("a:") ? v.slice(2) : "" }));
    const amount = Number(data.initial_amount || 0);
    const blocked = !!picked?.open_plan;

    const submit = (e) => {
        e.preventDefault();
        if (amount > 0 && !window.confirm(`Recover ${money(amount)} from ${picked?.name}'s future royalties?\nAn e-mailed code is needed next.`)) return;
        post("/super-admin/finance/recoupment", { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal title="Add advance / recoverable cost" onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Errors errors={errors} />

                <div>
                    <span className="text-sm font-semibold text-slate-700">Owner (label or artist account)</span>
                    {picked ? (
                        <div className="mt-1.5 flex items-center justify-between gap-2 rounded-xl border border-violet-300 bg-violet-50 px-3 py-2.5 text-sm">
                            <span className="min-w-0">
                                <b className="block truncate">{picked.name}</b>
                                <span className="block truncate text-xs text-slate-500">{picked.email} · {picked.role} · wallet {money(picked.wallet)}</span>
                            </span>
                            <button type="button" onClick={() => { setPicked(null); setData("user_id", ""); }} className="text-xs font-semibold text-violet-700 underline">Change</button>
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
                                            <button type="button" onClick={() => pick(o)} className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50">
                                                <span className="min-w-0"><b className="block truncate">{o.name}</b><span className="block truncate text-xs text-slate-500">{o.email} · {o.role}</span></span>
                                                {o.open_plan ? <span className="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">has a plan</span> : null}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {q.trim().length >= 2 && !busy && found.length === 0 && <p className="mt-1 text-xs text-slate-500">No label / artist account found.</p>}
                        </>
                    )}
                </div>

                {blocked ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
                        {picked.name} already has an active plan <b>{picked.open_plan.plan_number}</b> ({money(picked.open_plan.outstanding)} left). Close this and use <b>Open → Add cost</b> on that plan.
                    </div>
                ) : picked && (
                    <>
                        {(picked.labels.length > 0 || picked.artists.length > 0) && (
                            <Field label="Recover from" hint="Whole account = royalties of every label / artist of this owner.">
                                <select value={scope} onChange={(e) => setScope(e.target.value)} className={input}>
                                    <option value="">Whole account</option>
                                    {picked.labels.map((l) => <option key={`l${l.id}`} value={`l:${l.id}`}>Only label: {l.name}</option>)}
                                    {picked.artists.map((a) => <option key={`a${a.id}`} value={`a:${a.id}`}>Only artist: {a.name}</option>)}
                                </select>
                            </Field>
                        )}

                        <Field label="Plan title">
                            <input required value={data.title} onChange={(e) => setData("title", e.target.value)} className={input} />
                        </Field>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Amount given (₹)" hint="Leave empty to add costs later.">
                                <input type="number" min="0" step="0.01" inputMode="decimal" value={data.initial_amount} onChange={(e) => setData("initial_amount", e.target.value)} className={input} placeholder="e.g. 100000" />
                            </Field>
                            <Field label="What is it?">
                                <select value={data.initial_category} onChange={(e) => setData("initial_category", e.target.value)} className={input}>
                                    {Object.entries(CATEGORIES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                            </Field>
                        </div>

                        {amount > 0 && (
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Payment reference (optional)">
                                    <input value={data.reference_number} onChange={(e) => setData("reference_number", e.target.value)} className={input} placeholder="UTR / invoice no." />
                                </Field>
                                <Field label="Paid on">
                                    <input type="date" value={data.expense_date} onChange={(e) => setData("expense_date", e.target.value)} className={input} />
                                </Field>
                            </div>
                        )}

                        <RateFields data={data} setData={setData} />

                        <Field label="Start recovering from">
                            <input type="date" required value={data.starts_on} onChange={(e) => setData("starts_on", e.target.value)} className={input} />
                        </Field>

                        <Field label="Internal note (team only)">
                            <textarea rows={2} value={data.notes} onChange={(e) => setData("notes", e.target.value)} className="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        </Field>

                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" checked={data.notify_owner} onChange={(e) => setData("notify_owner", e.target.checked)} /> Tell the owner (panel notice + shown on their Wallet page)
                        </label>
                    </>
                )}

                <div className="flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Cancel</button>
                    <button type="submit" disabled={processing || !data.user_id || blocked || data.base_percentage === "" || data.recovery_uplift_percentage === ""} className="h-11 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white disabled:opacity-40">
                        {processing ? "Saving…" : amount > 0 ? `Create · recover ${money(amount)}` : "Create plan"}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

function DetailModal({ plan, tab, setTab, onClose, onChanged }) {
    const [label, cls] = STATUS[plan.status] ?? STATUS.cancelled;
    const r = plan.rates ?? {};
    const tabs = [
        ["costs", `Costs (${plan.expenses?.length ?? 0})`],
        ["recoveries", `Recovered (${plan.recoveries?.length ?? 0})`],
        ["documents", `Documents (${plan.documents?.length ?? 0})`],
        ["settings", "Rates & plan"],
    ];

    return (
        <Modal title={`${owner(plan)} · ${plan.plan_number}`} onClose={onClose} wide>
            <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className={`rounded-full px-2 py-0.5 font-bold ${cls}`}>{label}</span>
                <span className="text-slate-500">{plan.user?.email} · {scopeText(plan)} · since {day(plan.starts_on)}{plan.creator?.name ? ` · by ${plan.creator.name}` : ""}</span>
            </div>
            <div className="mt-3 grid grid-cols-3 gap-2">
                <Mini label="To recover" value={money(plan.total_recoverable_amount)} />
                <Mini label="Recovered" value={money(plan.total_recovered_amount)} tone="text-emerald-700" />
                <Mini label="Left" value={money(plan.outstanding_amount)} tone="text-amber-700" />
            </div>
            <div className="mt-2"><Bar total={Number(plan.total_recoverable_amount || 0)} done={Number(plan.total_recovered_amount || 0)} /></div>
            <p className="mt-2 text-xs text-slate-500">
                Recovery {pct(r.recovery)} of revenue · owner share {pct(r.base)}{r.running ? ` → ${pct(r.owner_now)} while repaying` : ""}. Recovery happens automatically when royalty statements / report imports are posted.
            </p>

            <div className="mt-4 flex gap-1 overflow-x-auto border-b border-slate-200">
                {tabs.map(([k, v]) => (
                    <button key={k} type="button" onClick={() => setTab(k)} className={`whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold ${tab === k ? "border-violet-600 text-violet-700" : "border-transparent text-slate-500"}`}>{v}</button>
                ))}
            </div>

            <div className="mt-4">
                {tab === "costs" && <Costs plan={plan} onChanged={onChanged} />}
                {tab === "recoveries" && <Recoveries plan={plan} />}
                {tab === "documents" && <Documents plan={plan} onChanged={onChanged} />}
                {tab === "settings" && <Settings key={`${plan.id}-${plan.updated_at}`} plan={plan} onChanged={onChanged} />}
            </div>
        </Modal>
    );
}

function Mini({ label, value, tone = "text-slate-900" }) {
    return (
        <div className="rounded-xl bg-slate-50 px-3 py-2">
            <div className="text-[11px] font-semibold text-slate-500">{label}</div>
            <div className={`text-sm font-black sm:text-base ${tone}`}>{value}</div>
        </div>
    );
}

function Costs({ plan, onChanged }) {
    const [adding, setAdding] = useState(false);
    const [removing, setRemoving] = useState(null);
    const list = plan.expenses ?? [];

    return (
        <div className="space-y-3">
            {plan.status !== "cancelled" && !adding && (
                <button type="button" onClick={() => setAdding(true)} className="inline-flex h-10 items-center gap-1.5 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white">
                    <Plus className="h-4 w-4" /> Add cost
                </button>
            )}
            {adding && <AddCost plan={plan} onDone={() => { setAdding(false); onChanged(); }} onCancel={() => setAdding(false)} />}
            {removing && <RemoveCost plan={plan} cost={removing} onDone={() => { setRemoving(null); onChanged(); }} onCancel={() => setRemoving(null)} />}

            {list.length === 0 ? (
                <p className="rounded-xl bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">No costs yet. Add the advance amount with "Add cost".</p>
            ) : (
                <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200">
                    {list.map((x) => (
                        <li key={x.id} className="flex flex-col gap-1 px-3 py-2.5 sm:flex-row sm:items-start sm:justify-between">
                            <div className="min-w-0">
                                <div className="text-sm font-semibold text-slate-900">{x.title}</div>
                                <div className="text-xs text-slate-500">
                                    {CATEGORIES[x.category] ?? x.category} · {day(x.expense_date)}{x.reference_number ? ` · ref ${x.reference_number}` : ""}
                                </div>
                                {x.description && <div className="mt-0.5 whitespace-pre-line break-words text-xs text-slate-500">{x.description}</div>}
                            </div>
                            <div className="flex shrink-0 items-center gap-2 sm:flex-col sm:items-end">
                                <span className={`whitespace-nowrap text-sm font-bold ${x.is_recoverable ? "text-slate-900" : "text-slate-400 line-through"}`}>{money(x.amount)}</span>
                                <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${x.is_recoverable ? "bg-amber-50 text-amber-800" : "bg-slate-100 text-slate-500"}`}>{x.is_recoverable ? "Recovered from owner" : "Not recovered"}</span>
                                {x.is_recoverable && plan.status !== "cancelled" && (
                                    <button type="button" onClick={() => setRemoving(x)} className="text-xs font-semibold text-red-600 underline">Take out</button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function AddCost({ plan, onDone, onCancel }) {
    const { data, setData, post, processing, errors } = useForm({
        category: "advance",
        title: "",
        amount: "",
        is_recoverable: true,
        expense_date: today(),
        reference_number: "",
        description: "",
        notify_owner: true,
    });
    const amount = Number(data.amount || 0);

    const submit = (e) => {
        e.preventDefault();
        if (data.is_recoverable && !window.confirm(`Add ${money(amount)} to recover from ${owner(plan)}?\nAn e-mailed code is needed next.`)) return;
        post(`/super-admin/finance/recoupment/${plan.id}/expenses`, { preserveScroll: true, preserveState: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="space-y-3 rounded-2xl border border-violet-200 bg-violet-50/40 p-4">
            <Errors errors={errors} />
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="What is it?">
                    <select value={data.category} onChange={(e) => setData((d) => ({ ...d, category: e.target.value, title: d.title || CATEGORIES[e.target.value] }))} className={input}>
                        {Object.entries(CATEGORIES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                </Field>
                <Field label="Amount (₹)">
                    <input required type="number" min="0.01" step="0.01" inputMode="decimal" value={data.amount} onChange={(e) => setData("amount", e.target.value)} className={input} />
                </Field>
            </div>
            <Field label="Title (the owner sees it)">
                <input required value={data.title} onChange={(e) => setData("title", e.target.value)} className={input} placeholder="e.g. Instagram ads for 'Song name'" />
            </Field>
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Date paid">
                    <input type="date" required value={data.expense_date} onChange={(e) => setData("expense_date", e.target.value)} className={input} />
                </Field>
                <Field label="Reference / invoice (optional)">
                    <input value={data.reference_number} onChange={(e) => setData("reference_number", e.target.value)} className={input} />
                </Field>
            </div>
            <Field label="Details (optional)">
                <textarea rows={2} value={data.description} onChange={(e) => setData("description", e.target.value)} className="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
            </Field>
            <label className="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" className="mt-1" checked={data.is_recoverable} onChange={(e) => setData("is_recoverable", e.target.checked)} />
                <span><b>Recover from the owner</b> — adds to the amount taken from royalties. Untick for a cost you only want to note.</span>
            </label>
            {data.is_recoverable && (
                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={data.notify_owner} onChange={(e) => setData("notify_owner", e.target.checked)} /> Tell the owner
                </label>
            )}
            <div className="flex justify-end gap-2">
                <button type="button" onClick={onCancel} className="h-10 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Cancel</button>
                <button type="submit" disabled={processing || !(amount > 0) || !data.title.trim()} className="h-10 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white disabled:opacity-40">
                    {processing ? "Saving…" : `Add ${money(amount)}`}
                </button>
            </div>
        </form>
    );
}

function RemoveCost({ plan, cost, onDone, onCancel }) {
    const { data, setData, post, processing, errors } = useForm({ reason: "", notify_owner: true });
    const submit = (e) => {
        e.preventDefault();
        post(`/super-admin/finance/recoupment/${plan.id}/expenses/${cost.id}/remove`, { preserveScroll: true, preserveState: true, onSuccess: onDone });
    };
    return (
        <form onSubmit={submit} className="space-y-3 rounded-2xl border border-red-200 bg-red-50/50 p-4">
            <Errors errors={errors} />
            <p className="text-sm text-slate-700">
                Take <b>{cost.title}</b> ({money(cost.amount)}) out of recovery? It stays in the history, marked "Not recovered".
            </p>
            <Field label="Why?">
                <input required value={data.reason} onChange={(e) => setData("reason", e.target.value)} className={input} placeholder="e.g. Wrong amount typed" />
            </Field>
            <label className="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" checked={data.notify_owner} onChange={(e) => setData("notify_owner", e.target.checked)} /> Tell the owner
            </label>
            <div className="flex justify-end gap-2">
                <button type="button" onClick={onCancel} className="h-10 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Keep it</button>
                <button type="submit" disabled={processing || data.reason.trim().length < 3} className="h-10 rounded-xl bg-red-600 px-4 text-sm font-semibold text-white disabled:opacity-40">{processing ? "Saving…" : "Take out"}</button>
            </div>
        </form>
    );
}

function Recoveries({ plan }) {
    const list = plan.recoveries ?? [];
    if (!list.length) {
        return <p className="rounded-xl bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">Nothing recovered yet. Recovery starts when the next royalty statement or report import for this owner is posted.</p>;
    }
    return (
        <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200">
            {list.map((x) => (
                <li key={x.id} className="flex flex-col gap-1 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <div className="text-sm font-semibold text-slate-900">{x.reporting_month ? day(x.reporting_month).replace(/^\d+ /, "") : day(x.created_at)}</div>
                        <div className="truncate text-xs text-slate-500">{x.reference ?? "—"} · {pct(x.recovery_percentage)} of {money(x.source_amount)} revenue</div>
                    </div>
                    <div className="shrink-0 text-right">
                        <div className="text-sm font-bold text-emerald-700">−{money(x.applied_recovery_amount)}</div>
                        <div className="text-xs text-slate-500">left {money(x.outstanding_before)} → {money(x.outstanding_after)}</div>
                    </div>
                </li>
            ))}
        </ul>
    );
}

function Documents({ plan, onChanged }) {
    const { data, setData, post, processing, errors, reset, progress } = useForm({ document_type: "advance_agreement", recoupment_expense_id: "", document: null });
    const fileRef = useRef(null);
    const list = plan.documents ?? [];

    const submit = (e) => {
        e.preventDefault();
        post(`/super-admin/finance/recoupment/${plan.id}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reset("document");
                if (fileRef.current) fileRef.current.value = "";
                onChanged();
            },
        });
    };

    return (
        <div className="space-y-3">
            <form onSubmit={submit} className="space-y-3 rounded-2xl border border-slate-200 p-4">
                <Errors errors={errors} />
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Type">
                        <select value={data.document_type} onChange={(e) => setData("document_type", e.target.value)} className={input}>
                            {Object.entries(DOC_TYPES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                        </select>
                    </Field>
                    <Field label="For cost (optional)">
                        <select value={data.recoupment_expense_id} onChange={(e) => setData("recoupment_expense_id", e.target.value)} className={input}>
                            <option value="">Whole plan</option>
                            {(plan.expenses ?? []).map((x) => <option key={x.id} value={x.id}>{x.title} · {money(x.amount)}</option>)}
                        </select>
                    </Field>
                </div>
                <Field label="File (PDF / JPG / PNG / WEBP, up to 10 MB)">
                    <input ref={fileRef} required type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => setData("document", e.target.files?.[0] ?? null)} className="mt-1.5 block w-full text-sm" />
                </Field>
                {progress && <div className="text-xs text-slate-500">Uploading {progress.percentage}%</div>}
                <div className="flex justify-end">
                    <button type="submit" disabled={processing || !data.document} className="inline-flex h-10 items-center gap-1.5 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white disabled:opacity-40">
                        <Upload className="h-4 w-4" /> {processing ? "Uploading…" : "Upload"}
                    </button>
                </div>
            </form>

            {list.length === 0 ? (
                <p className="rounded-xl bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">No documents yet. Keep the signed agreement and payment proof here.</p>
            ) : (
                <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200">
                    {list.map((d) => (
                        <li key={d.id} className="flex items-center justify-between gap-2 px-3 py-2.5">
                            <div className="min-w-0">
                                <div className="truncate text-sm font-semibold text-slate-900">{d.original_name}</div>
                                <div className="text-xs text-slate-500">{DOC_TYPES[d.document_type] ?? d.document_type} · {day(d.created_at)}</div>
                            </div>
                            <a href={`/super-admin/finance/recoupment/${plan.id}/documents/${d.id}/download`} className="inline-flex h-9 shrink-0 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700">
                                <Download className="h-3.5 w-3.5" /> Download
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Settings({ plan, onChanged }) {
    const edit = useForm({
        title: plan.title ?? "",
        notes: plan.notes ?? "",
        base_percentage: Number(plan.base_percentage ?? 0),
        recovery_uplift_percentage: Number(plan.recovery_uplift_percentage ?? 0),
        maximum_recovery_percentage: plan.maximum_recovery_percentage === null ? "" : Number(plan.maximum_recovery_percentage),
        notify_owner: true,
    });
    const cancel = useForm({ reason: "", notify_owner: true });
    const [confirmCancel, setConfirmCancel] = useState(false);
    const locked = plan.status === "cancelled";

    const save = (e) => {
        e.preventDefault();
        edit.post(`/super-admin/finance/recoupment/${plan.id}/update`, { preserveScroll: true, preserveState: true, onSuccess: onChanged });
    };
    const doCancel = (e) => {
        e.preventDefault();
        cancel.post(`/super-admin/finance/recoupment/${plan.id}/cancel`, { preserveScroll: true, preserveState: true, onSuccess: () => { setConfirmCancel(false); onChanged(); } });
    };

    return (
        <div className="space-y-5">
            <form onSubmit={save} className="space-y-3">
                <Errors errors={edit.errors} />
                <Field label="Plan title">
                    <input required disabled={locked} value={edit.data.title} onChange={(e) => edit.setData("title", e.target.value)} className={input} />
                </Field>
                <fieldset disabled={locked} className="space-y-3">
                    <RateFields data={edit.data} setData={edit.setData} />
                </fieldset>
                <Field label="Internal note (team only)">
                    <textarea disabled={locked} rows={2} value={edit.data.notes} onChange={(e) => edit.setData("notes", e.target.value)} className="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                </Field>
                {!locked && (
                    <div className="flex items-center justify-between gap-2">
                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" checked={edit.data.notify_owner} onChange={(e) => edit.setData("notify_owner", e.target.checked)} /> Tell the owner if the rate changes
                        </label>
                        <button type="submit" disabled={edit.processing || !edit.isDirty} className="inline-flex h-10 items-center gap-1.5 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white disabled:opacity-40">
                            <Pencil className="h-4 w-4" /> {edit.processing ? "Saving…" : "Save"}
                        </button>
                    </div>
                )}
                {!locked && <p className="text-xs text-slate-500">A rate change needs an e-mailed code and applies to the next royalties only.</p>}
            </form>

            {plan.status === "active" && (
                <div className="rounded-2xl border border-red-200 p-4">
                    {!confirmCancel ? (
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div className="text-sm text-slate-700">
                                <b>Stop recovering</b> — the {money(plan.outstanding_amount)} left is written off. Already recovered money stays.
                            </div>
                            <button type="button" onClick={() => setConfirmCancel(true)} className="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-xl border border-red-300 px-4 text-sm font-semibold text-red-700">
                                <Ban className="h-4 w-4" /> Cancel plan
                            </button>
                        </div>
                    ) : (
                        <form onSubmit={doCancel} className="space-y-3">
                            <Errors errors={cancel.errors} />
                            <Field label="Why stop?">
                                <input required value={cancel.data.reason} onChange={(e) => cancel.setData("reason", e.target.value)} className={input} placeholder="e.g. Settled outside / agreement ended" />
                            </Field>
                            <label className="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" checked={cancel.data.notify_owner} onChange={(e) => cancel.setData("notify_owner", e.target.checked)} /> Tell the owner
                            </label>
                            <div className="flex justify-end gap-2">
                                <button type="button" onClick={() => setConfirmCancel(false)} className="h-10 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Keep recovering</button>
                                <button type="submit" disabled={cancel.processing || cancel.data.reason.trim().length < 3} className="h-10 rounded-xl bg-red-600 px-4 text-sm font-semibold text-white disabled:opacity-40">
                                    {cancel.processing ? "Cancelling…" : "Cancel plan"}
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            )}
            {plan.status !== "active" && plan.completed_on && (
                <p className="text-xs text-slate-500">{plan.status === "completed" ? "Fully recovered" : "Cancelled"} on {day(plan.completed_on)}{plan.completer?.name ? ` by ${plan.completer.name}` : ""}.</p>
            )}
        </div>
    );
}
