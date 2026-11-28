import { Head, Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* W241 MIXXTUNE_PROMOTION - Super Admin: the promotion packages labels can ask for */

const EMPTY = { id: null, name: "", details: "", price: "", sort: 0, active: true };

function PackageForm({ start, onDone }) {
    const [d, setD] = useState(start);
    const [busy, setBusy] = useState(false);
    const set = (k, v) => setD((x) => ({ ...x, [k]: v }));
    const save = (e) => {
        e.preventDefault();
        setBusy(true);
        router.post("/admin/promotion/packages", d, { preserveScroll: true, onSuccess: () => { if (!start.id) setD(EMPTY); onDone?.(); }, onFinish: () => setBusy(false) });
    };
    const input = "mt-1 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm";
    return (
        <form onSubmit={save} className="grid gap-3 sm:grid-cols-4">
            <label className="text-xs font-semibold text-slate-700 sm:col-span-2">Name
                <input required value={d.name} onChange={(e) => set("name", e.target.value)} maxLength={120} placeholder="e.g. Instagram Reels Boost" className={input} />
            </label>
            <label className="text-xs font-semibold text-slate-700">Price (as you want it shown)
                <input value={d.price} onChange={(e) => set("price", e.target.value)} maxLength={80} placeholder="e.g. ₹4,999" className={input} />
            </label>
            <label className="text-xs font-semibold text-slate-700">Order
                <input type="number" min={0} max={9999} value={d.sort} onChange={(e) => set("sort", e.target.value)} className={input} />
            </label>
            <label className="text-xs font-semibold text-slate-700 sm:col-span-4">What the label gets
                <textarea value={d.details} onChange={(e) => set("details", e.target.value)} rows={3} maxLength={3000} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
            </label>
            <label className="flex items-center gap-2 text-sm font-semibold text-slate-700 sm:col-span-2">
                <input type="checkbox" checked={!!d.active} onChange={(e) => set("active", e.target.checked)} className="h-5 w-5 rounded border-slate-300" />
                On offer (labels can see it)
            </label>
            <div className="sm:col-span-2 sm:text-right">
                <button type="submit" disabled={busy} className="h-11 rounded-xl bg-violet-600 px-6 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-50">{start.id ? "Save changes" : "Add package"}</button>
            </div>
        </form>
    );
}

export default function Packages({ role = "super_admin", ready = true, packages = [] }) {
    const { flash = {}, errors = {} } = usePage().props;
    const [open, setOpen] = useState(null);
    const firstError = errors.name || errors.details || errors.price || errors.sort;

    return (
        <PanelLayout role={role} title="Promotion packages" subtitle="What you offer. Labels see the packages that are on offer and send a request">
            <Head title="Promotion packages" />
            <div className="mx-auto w-full max-w-5xl space-y-4">
                {flash.success && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.success}</div>}
                {firstError && <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{firstError}</div>}
                {!ready && <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Promotion is not set up yet.</div>}
                <div className="text-right"><Link href="/admin/promotion/requests" className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Requests</Link></div>

                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div className="mb-3 text-base font-bold text-slate-900">Add a package</div>
                    <PackageForm start={EMPTY} />
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 px-4 py-3 text-base font-bold text-slate-900 sm:px-5">Your packages</div>
                    {packages.length === 0 ? (
                        <div className="px-5 py-8 text-center text-sm text-slate-500">No package yet.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {packages.map((p) => (
                                <div key={p.id} className="px-4 py-3 sm:px-5">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="text-sm font-bold text-slate-900">{p.name}{p.price ? ` · ${p.price}` : ""}</div>
                                        <div className="flex items-center gap-2">
                                            <span className={`rounded-full px-3 py-1 text-xs font-bold ${p.active ? "bg-emerald-100 text-emerald-800" : "bg-slate-200 text-slate-700"}`}>{p.active ? "On offer" : "Hidden"}</span>
                                            <button type="button" onClick={() => setOpen(open === p.id ? null : p.id)} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{open === p.id ? "Close" : "Edit"}</button>
                                        </div>
                                    </div>
                                    {p.details && open !== p.id && <div className="mt-1 whitespace-pre-line text-xs text-slate-600">{p.details}</div>}
                                    {open === p.id && <div className="mt-3"><PackageForm start={p} onDone={() => setOpen(null)} /></div>}
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
