import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { AlertTriangle, CheckCircle2, Copy, KeyRound, Landmark, PlugZap, RefreshCw, ShieldCheck, Zap } from "lucide-react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/*
 * W115 MIXXTUNE_RAZORPAY_PAYOUTS
 * Super Admin: connect RazorpayX, switch payouts on/off, and follow every bank transfer.
 */
const inr = (v) => "₹" + new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));

const STATUS = {
    creating: ["Sending", "bg-violet-50 text-violet-700"],
    error: ["No answer yet", "bg-amber-50 text-amber-800"],
    pending: ["Waiting approval (RazorpayX)", "bg-amber-50 text-amber-800"],
    queued: ["Queued - low balance", "bg-amber-50 text-amber-800"],
    scheduled: ["Scheduled", "bg-sky-50 text-sky-700"],
    processing: ["Bank is paying", "bg-sky-50 text-sky-700"],
    processed: ["Paid", "bg-emerald-50 text-emerald-700"],
    reversed: ["Came back", "bg-red-50 text-red-700"],
    failed: ["Failed", "bg-red-50 text-red-700"],
    rejected: ["Rejected", "bg-red-50 text-red-700"],
    cancelled: ["Cancelled", "bg-slate-100 text-slate-600"],
};

function Field({ label, hint, error, children }) {
    return (
        <label className="block">
            <span className="text-xs font-semibold text-slate-600">{label}</span>
            <div className="mt-1">{children}</div>
            {hint && !error && <span className="mt-1 block text-[11px] text-slate-400">{hint}</span>}
            {error && <span className="mt-1 block text-[11px] font-semibold text-red-600">{error}</span>}
        </label>
    );
}

const input = "h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm";

export default function Index({ settings = {}, webhookUrl = "", stagingSite = false, counts = {}, filter = "", payouts = {} }) {
    const { flash = {}, errors: pageErrors = {} } = usePage().props;
    const [copied, setCopied] = useState(false);
    const [testing, setTesting] = useState(false);
    const form = useForm({
        enabled: settings.enabled ? 1 : 0,
        key_id: settings.key_id ?? "",
        key_secret: "",
        webhook_secret: "",
        account_number: "",
        narration: settings.narration ?? "Mixx Tune",
    });
    const rows = payouts.data ?? [];
    const save = (e) => {
        e.preventDefault();
        form.post("/admin/razorpay/settings", { preserveScroll: true, onSuccess: () => form.setData((d) => ({ ...d, key_secret: "", webhook_secret: "", account_number: "" })) });
    };
    const test = () => {
        setTesting(true);
        router.post("/admin/razorpay/test", {}, { preserveScroll: true, onFinish: () => setTesting(false) });
    };
    const copy = () => {
        navigator.clipboard?.writeText(webhookUrl).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };
    const bad = pageErrors.razorpay || pageErrors.enabled;

    return (
        <PanelLayout role="super_admin" title="Razorpay Payouts" subtitle="Withdrawals and salaries straight to the bank (RazorpayX, NEFT)">
            <Head title="Razorpay Payouts" />

            {flash.success && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4 shrink-0" /> {flash.success}
                </div>
            )}
            {bad && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <AlertTriangle className="h-4 w-4 shrink-0" /> {bad}
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-3">
                <div className={`rounded-2xl border p-4 shadow-sm ${settings.enabled && settings.configured ? "border-emerald-200 bg-emerald-50" : "border-slate-200 bg-white"}`}>
                    <div className="flex items-center gap-2 text-xs font-semibold text-slate-500">
                        <PlugZap className="h-4 w-4" /> Status
                    </div>
                    <div className="mt-1 text-lg font-black text-slate-900">{settings.enabled && settings.configured ? "ON" : settings.configured ? "Connected, OFF" : "Not connected"}</div>
                    <div className="text-xs text-slate-500">{settings.configured ? (settings.live ? "LIVE keys - real money" : "TEST keys - no real money") : "Add the keys below"}</div>
                </div>
                <Link href="/admin/razorpay?status=open" className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-xs font-semibold text-slate-500">Running now</div>
                    <div className="mt-1 text-2xl font-black text-slate-900">{counts.open ?? 0}</div>
                    <div className="text-xs text-slate-500">sent, bank not finished</div>
                </Link>
                <Link href="/admin/razorpay?status=failed" className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-xs font-semibold text-slate-500">Failed (30 days)</div>
                    <div className={`mt-1 text-2xl font-black ${counts.failed ? "text-red-600" : "text-slate-900"}`}>{counts.failed ?? 0}</div>
                    <div className="text-xs text-slate-500">{counts.processed ?? 0} paid in total</div>
                </Link>
            </div>

            <div className="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                <form onSubmit={save} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h2 className="flex items-center gap-2 text-base font-bold text-slate-900">
                        <KeyRound className="h-4 w-4" /> Connection
                    </h2>
                    <p className="mt-1 text-xs text-slate-500">Secrets are kept locked (encrypted) and are never shown again. Leave a secret box empty to keep the saved one. Saving needs the e-mailed code.</p>
                    {stagingSite && <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">Staging site: only TEST keys (rzp_test_...) are accepted here.</p>}

                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        <Field label="Key Id" hint="RazorpayX → Developer Controls → API Keys" error={form.errors.key_id}>
                            <input value={form.data.key_id} onChange={(e) => form.setData("key_id", e.target.value.trim())} placeholder="rzp_test_..." className={`${input} font-mono`} autoComplete="off" />
                        </Field>
                        <Field label={`Key Secret ${settings.has_secret ? "(saved ✓)" : ""}`} error={form.errors.key_secret}>
                            <input type="password" value={form.data.key_secret} onChange={(e) => form.setData("key_secret", e.target.value)} placeholder={settings.has_secret ? "•••••••• keep" : "paste the secret"} className={`${input} font-mono`} autoComplete="new-password" />
                        </Field>
                        <Field label={`RazorpayX account number ${settings.account_hint ? `(saved ${settings.account_hint})` : ""}`} hint="The account money is paid FROM (Current Account / customer identifier in RazorpayX)" error={form.errors.account_number}>
                            <input value={form.data.account_number} onChange={(e) => form.setData("account_number", e.target.value.replace(/\s+/g, ""))} placeholder={settings.account_hint ? "keep" : "e.g. 2323230000000000"} className={`${input} font-mono`} autoComplete="off" />
                        </Field>
                        <Field label={`Webhook secret ${settings.has_webhook_secret ? "(saved ✓)" : ""}`} hint="The same secret you type in the RazorpayX webhook" error={form.errors.webhook_secret}>
                            <input type="password" value={form.data.webhook_secret} onChange={(e) => form.setData("webhook_secret", e.target.value)} placeholder={settings.has_webhook_secret ? "•••••••• keep" : "make one up, also put it in RazorpayX"} className={`${input} font-mono`} autoComplete="new-password" />
                        </Field>
                        <Field label="Text on the bank statement" hint="Letters, digits and spaces; '... royalty' / '... salary' is added" error={form.errors.narration}>
                            <input value={form.data.narration} maxLength={20} onChange={(e) => form.setData("narration", e.target.value.replace(/[^A-Za-z0-9 ]/g, ""))} className={input} />
                        </Field>
                        <Field label="Payouts" error={form.errors.enabled}>
                            <div className="flex h-10 items-center gap-2">
                                {[
                                    [1, "ON"],
                                    [0, "OFF"],
                                ].map(([v, l]) => (
                                    <button key={l} type="button" onClick={() => form.setData("enabled", v)} className={`h-10 flex-1 rounded-xl border text-sm font-bold ${form.data.enabled === v ? (v ? "border-emerald-500 bg-emerald-600 text-white" : "border-slate-500 bg-slate-700 text-white") : "border-slate-300 bg-white text-slate-600"}`}>
                                        {l}
                                    </button>
                                ))}
                            </div>
                        </Field>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        <button type="submit" disabled={form.processing} className="inline-flex h-10 items-center gap-1.5 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white disabled:opacity-50">
                            <ShieldCheck className="h-4 w-4" /> Save
                        </button>
                        <button type="button" onClick={test} disabled={testing || !settings.configured} className="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 disabled:opacity-40">
                            <RefreshCw className={`h-4 w-4 ${testing ? "animate-spin" : ""}`} /> Test connection
                        </button>
                    </div>
                </form>

                <aside className="rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-sm sm:p-5">
                    <h2 className="flex items-center gap-2 text-base font-bold text-slate-900">
                        <Zap className="h-4 w-4" /> How to connect
                    </h2>
                    <ol className="mt-3 list-decimal space-y-2 pl-5 text-xs leading-relaxed text-slate-600">
                        <li>Open a <b>RazorpayX</b> account (business KYC) and add money to it.</li>
                        <li>RazorpayX → Developer Controls → <b>API Keys</b>: make a key. Start with <b>Test mode</b>.</li>
                        <li>Developer Controls → <b>IP allow-list</b>: add this server's IP (Hostinger VPS IP). Without it Razorpay refuses payouts.</li>
                        <li>
                            Webhooks → add this URL, give a secret, tick all <b>payout.*</b> events:
                            <span className="mt-1 flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1.5 font-mono text-[11px] text-slate-800">
                                <span className="min-w-0 flex-1 break-all">{webhookUrl}</span>
                                <button type="button" onClick={copy} aria-label="Copy" className="shrink-0 rounded p-1 hover:bg-white">
                                    {copied ? <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
                                </button>
                            </span>
                        </li>
                        <li>Fill in the boxes, <b>Save</b>, <b>Test connection</b>, then switch <b>ON</b>.</li>
                        <li>Withdrawals: "Approve &amp; Pay via Razorpay". Salaries: Financial → Staff &amp; Salary.</li>
                    </ol>
                    <p className="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
                        Every payment is NEFT, needs the e-mailed code, is sent only once (each has its own safety key), and the panel checks unfinished ones every 5 minutes even if a webhook is missed.
                    </p>
                </aside>
            </div>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-center gap-1 border-b border-slate-200 px-3 pt-3">
                    <h2 className="mr-3 flex items-center gap-2 pb-2.5 text-sm font-bold text-slate-900">
                        <Landmark className="h-4 w-4" /> Bank transfers
                    </h2>
                    {[
                        ["", "All"],
                        ["open", "Running"],
                        ["processed", "Paid"],
                        ["failed", "Failed"],
                    ].map(([k, l]) => (
                        <Link key={k} href={`/admin/razorpay${k ? `?status=${k}` : ""}`} preserveScroll className={`border-b-2 px-3 pb-2.5 pt-1 text-sm font-semibold ${filter === k ? "border-violet-600 text-violet-700" : "border-transparent text-slate-500"}`}>
                            {l}
                        </Link>
                    ))}
                </div>
                {rows.length === 0 ? (
                    <p className="px-5 py-12 text-center text-sm text-slate-500">No bank transfers yet.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-left text-sm">
                            <thead className="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th className="px-4 py-2">When</th>
                                    <th className="px-4 py-2">For</th>
                                    <th className="px-4 py-2">To</th>
                                    <th className="px-4 py-2 text-right">Amount</th>
                                    <th className="px-4 py-2">Status</th>
                                    <th className="px-4 py-2">UTR / reason</th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.map((p) => {
                                    const [label, cls] = STATUS[p.status] ?? [p.status, "bg-slate-100 text-slate-600"];
                                    return (
                                        <tr key={p.id} className="align-top">
                                            <td className="px-4 py-3 text-xs text-slate-500">
                                                {p.at}
                                                {p.by && <div>by {p.by}</div>}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-slate-900">{p.what}</div>
                                                <div className="text-[11px] text-slate-400">{p.live ? "LIVE" : "TEST"} · {p.mode}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="text-slate-800">{p.payee}</div>
                                                <div className="font-mono text-[11px] text-slate-400">{p.account}</div>
                                            </td>
                                            <td className="px-4 py-3 text-right font-bold text-slate-900">{inr(p.amount)}</td>
                                            <td className="px-4 py-3">
                                                <span className={`whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ${cls}`}>{label}</span>
                                                {p.reversed_after_paid && <div className="mt-1 text-[11px] font-semibold text-red-600">came back after paid!</div>}
                                            </td>
                                            <td className="max-w-[280px] px-4 py-3 text-xs">
                                                {p.utr && <div className="font-mono text-slate-800">{p.utr}</div>}
                                                {p.reason && <div className="text-red-600">{p.reason}</div>}
                                                {p.razorpay_id && <div className="font-mono text-[11px] text-slate-400">{p.razorpay_id}</div>}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {(!["processed", ...["failed", "reversed", "rejected", "cancelled"]].includes(p.status) || (p.status === "processed" && !p.settled)) && (
                                                    <button type="button" onClick={() => router.post(`/admin/razorpay/payouts/${p.id}/check`, {}, { preserveScroll: true })} className="whitespace-nowrap rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                        {["creating", "error"].includes(p.status) ? "Ask again now" : "Check now"}
                                                    </button>
                                                )}
                                                {["creating", "error"].includes(p.status) && !p.razorpay_id && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            window.confirm(`Close ${p.what} (${inr(p.amount)}) as NOT paid?\n\nThe panel first asks Razorpay; it closes only if Razorpay has no such payout.`) &&
                                                            router.post(`/admin/razorpay/payouts/${p.id}/close`, {}, { preserveScroll: true })
                                                        }
                                                        className="mt-1 block w-full whitespace-nowrap text-right text-[11px] font-semibold text-red-600 underline"
                                                    >
                                                        Close as not paid
                                                    </button>
                                                )}
                                                {p.checked && <div className="mt-1 text-[10px] text-slate-400">checked {p.checked}</div>}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
                {payouts.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                        {payouts.prev_page_url ? <Link href={payouts.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Newer</Link> : <span />}
                        <span className="text-xs text-slate-500">Page {payouts.current_page} of {payouts.last_page}</span>
                        {payouts.next_page_url ? <Link href={payouts.next_page_url} preserveScroll className="font-semibold text-violet-700">Older →</Link> : <span />}
                    </div>
                )}
            </section>
        </PanelLayout>
    );
}
