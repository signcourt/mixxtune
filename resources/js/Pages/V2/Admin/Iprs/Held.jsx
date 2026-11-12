import { Head, Link, router, usePage } from "@inertiajs/react";
import { AlertTriangle, ArrowLeft, CheckCircle2, Loader2, Send, Wallet } from "lucide-react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* IPRS held payments: owners with no active user yet. Release credits their wallet. */

const money = (v) => "₹" + new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));
const REASON = { owner_user_missing: "No user account", owner_user_inactive: "User inactive" };

export default function Held({ role = "admin", owners = [], total_held = 0 }) {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const [busy, setBusy] = useState(null);

    const releasable = owners.filter((o) => o.user_active).length;

    const release = (owner) => {
        const body = owner ? { owner_type: owner.owner_type, owner_id: owner.owner_id } : {};
        const key = owner ? `${owner.owner_type}:${owner.owner_id}` : "all";
        if (!window.confirm(owner ? `Release ${money(owner.total_held)} to ${owner.name}'s wallet?` : "Release all eligible held payments to owners' wallets?")) return;
        setBusy(key);
        router.post("/admin/iprs/held/release", body, {
            preserveScroll: true,
            onError: (e) => { if (e?.action_otp) return; },
            onFinish: () => setBusy(null),
        });
    };

    return (
        <PanelLayout role={role} title="IPRS held payments" subtitle="Payments waiting for the owner's active user account">
            <Head title="IPRS held payments" />

            <div className="space-y-5">
                <Link href="/admin/iprs" className="inline-flex items-center gap-1.5 text-sm font-semibold text-violet-700">
                    <ArrowLeft className="h-4 w-4" /> Back to IPRS statements
                </Link>

                {flash.success && (
                    <div className="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        <CheckCircle2 className="h-4 w-4" /> {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <div className="text-sm text-amber-900">
                        <span className="font-semibold">{money(total_held)}</span> held across {owners.length} owner(s). A payment is released to the owner’s wallet once they have an active user account.
                    </div>
                    {releasable > 0 && (
                        <button type="button" onClick={() => release(null)} disabled={busy === "all"} className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            {busy === "all" ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />} Release all eligible ({releasable})
                        </button>
                    )}
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {owners.length === 0 ? (
                        <p className="px-5 py-12 text-center text-sm text-slate-500">Nothing held — every published payment reached an active owner.</p>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {owners.map((o) => {
                                const key = `${o.owner_type}:${o.owner_id}`;
                                return (
                                    <div key={key} className="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="truncate text-sm font-bold text-slate-900">{o.name}</span>
                                                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 capitalize">{o.owner_type}</span>
                                                {o.user_active ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"><CheckCircle2 className="h-3 w-3" /> active user</span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700"><AlertTriangle className="h-3 w-3" /> {o.has_user ? "user inactive" : "no user yet"}</span>
                                                )}
                                            </div>
                                            <div className="mt-0.5 text-xs text-slate-500">
                                                {o.count} line(s) · {o.reasons.map((r) => REASON[r] || r).join(", ")}
                                            </div>
                                        </div>
                                        <div className="text-right text-sm font-bold text-slate-900">{money(o.total_held)}</div>
                                        <button
                                            type="button"
                                            onClick={() => release(o)}
                                            disabled={!o.user_active || busy === key}
                                            title={o.user_active ? "Release to wallet" : "Owner needs an active user account first"}
                                            className="inline-flex items-center gap-1.5 rounded-xl bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            {busy === key ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Wallet className="h-3.5 w-3.5" />} Release
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
