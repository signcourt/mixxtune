import { Head, Link, router, usePage } from "@inertiajs/react";
import { AlertTriangle, ArrowLeft, CheckCircle2, Loader2, Wallet } from "lucide-react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/*
 * W163: one screen for ALL held revenue.
 * - Digital import holds (report_unclaimed_revenues) -> released via /admin/finance/held/release-owner
 * - IPRS publishing holds (iprs_held_revenues)       -> released via /admin/iprs/held/release
 * Both release endpoints are OTP-gated; this page adds no new money path.
 */

const money = (v) => "₹" + new Intl.NumberFormat("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));

export default function Held({ role = "admin", owners = [], summary = {} }) {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const errors = page.props?.errors ?? {};
    const [busy, setBusy] = useState(null);

    const releaseDigital = (o) => {
        if (!window.confirm(`Release ${money(o.digital_total)} of import-held revenue to ${o.name}'s wallet?`)) return;
        setBusy(`d:${o.owner_type}:${o.owner_id}`);
        router.post("/admin/finance/held/release-owner", { owner_type: o.owner_type, owner_id: o.owner_id }, {
            preserveScroll: true,
            onError: (e) => { if (e?.action_otp) return; },
            onFinish: () => setBusy(null),
        });
    };

    const releaseIprs = (o) => {
        if (!window.confirm(`Release ${money(o.iprs_total)} of IPRS-held revenue to ${o.name}'s wallet?`)) return;
        setBusy(`i:${o.owner_type}:${o.owner_id}`);
        router.post("/admin/iprs/held/release", { owner_type: o.owner_type, owner_id: o.owner_id }, {
            preserveScroll: true,
            onError: (e) => { if (e?.action_otp) return; },
            onFinish: () => setBusy(null),
        });
    };

    return (
        <PanelLayout role={role} title="Held revenue" subtitle="Everything waiting for an owner's active user account — from report imports and IPRS">
            <Head title="Held revenue" />

            <div className="space-y-5">
                <Link href="/admin/finance" className="inline-flex items-center gap-1.5 text-sm font-semibold text-violet-700">
                    <ArrowLeft className="h-4 w-4" /> Back to Finance &amp; Royalties
                </Link>

                {flash.success && (
                    <div className="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        <CheckCircle2 className="h-4 w-4" /> {flash.success}
                    </div>
                )}
                {errors.release && (
                    <div className="flex items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                        <AlertTriangle className="h-4 w-4" /> {errors.release}
                    </div>
                )}

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Tile label="Total held" value={money(summary.total)} big />
                    <Tile label="From report imports (digital)" value={money(summary.digital_total)} />
                    <Tile label="From IPRS publishing" value={money(summary.iprs_total)} />
                </div>

                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Money is held when its owner has <span className="font-semibold">no active user account</span> yet. Link the label/artist to an active user, then release — the amount goes to that owner's wallet. A confirmation code is e-mailed first. Only super-admins can release.
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {owners.length === 0 ? (
                        <p className="px-5 py-12 text-center text-sm text-slate-500">Nothing held — every published payment reached an active owner.</p>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {owners.map((o) => {
                                const dKey = `d:${o.owner_type}:${o.owner_id}`;
                                const iKey = `i:${o.owner_type}:${o.owner_id}`;
                                return (
                                    <div key={`${o.owner_type}:${o.owner_id}`} className="px-4 py-3 sm:px-5">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="truncate text-sm font-bold text-slate-900">{o.name}</span>
                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 capitalize">{o.owner_type}</span>
                                            {o.user_active ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"><CheckCircle2 className="h-3 w-3" /> active user</span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700"><AlertTriangle className="h-3 w-3" /> {o.has_user ? "user inactive" : "no user yet"}</span>
                                            )}
                                            <span className="ml-auto text-sm font-bold text-slate-900">{money(o.total_held)}</span>
                                        </div>

                                        <div className="mt-2 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                            {o.digital_total > 0 && (
                                                <SourceRow
                                                    label="Report imports"
                                                    note={`${o.digital_hold_ids.length} hold(s)`}
                                                    amount={money(o.digital_total)}
                                                    canRelease={o.user_active}
                                                    busy={busy === dKey}
                                                    onRelease={() => releaseDigital(o)}
                                                />
                                            )}
                                            {o.iprs_total > 0 && (
                                                <SourceRow
                                                    label="IPRS publishing"
                                                    note={`${o.iprs_count} line(s)`}
                                                    amount={money(o.iprs_total)}
                                                    canRelease={o.user_active}
                                                    busy={busy === iKey}
                                                    onRelease={() => releaseIprs(o)}
                                                />
                                            )}
                                        </div>
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

function Tile({ label, value, big = false }) {
    return (
        <div className={`rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm ${big ? "ring-1 ring-violet-100" : ""}`}>
            <div className="text-xs font-medium text-slate-500">{label}</div>
            <div className={`mt-1 font-bold text-slate-900 ${big ? "text-2xl" : "text-lg"}`}>{value}</div>
        </div>
    );
}

function SourceRow({ label, note, amount, canRelease, busy, onRelease }) {
    return (
        <div className="flex flex-1 items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
            <div className="min-w-0 flex-1">
                <div className="text-xs font-semibold text-slate-700">{label}</div>
                <div className="text-[11px] text-slate-500">{note}</div>
            </div>
            <div className="text-sm font-semibold text-slate-900">{amount}</div>
            <button
                type="button"
                onClick={onRelease}
                disabled={!canRelease || busy}
                title={canRelease ? "Release to wallet" : "Owner needs an active user account first"}
                className="inline-flex items-center gap-1.5 rounded-xl bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40"
            >
                {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Wallet className="h-3.5 w-3.5" />} Release
            </button>
        </div>
    );
}
