import { Head, Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* W241 MIXXTUNE_PROMOTION - Promotion > Promo kit: every live release of the viewer's circle */

export default function PromoKit({ role = "label", ready = true, rows = null, filters = {}, show_label = true }) {
    const { flash = {} } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? "");
    const [copied, setCopied] = useState(null);
    const list = rows?.data ?? [];
    const go = (extra = {}) => router.get("/promotion/promo-kit", { search, show: filters.show ?? "", ...extra }, { preserveState: true, replace: true });
    const copy = (r) => {
        try {
            navigator.clipboard.writeText(r.url).then(() => { setCopied(r.id); setTimeout(() => setCopied(null), 1500); });
        } catch (e) { /* the address is also shown, it can be copied by hand */ }
    };
    const TABS = [["", "All live releases"], ["on", "Page is on"], ["none", "No page yet"]];

    return (
        <PanelLayout role={role} title="Promo kit" subtitle="A public listen page for each live release: poster, store buttons and a share picture">
            <Head title="Promo kit" />
            <div className="mx-auto w-full max-w-6xl space-y-4">
                {flash.success && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.success}</div>}
                {!ready && <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">The promo kit is not set up yet.</div>}

                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div className="text-sm text-slate-600">Open a release, paste its store links (Spotify, JioSaavn, YouTube ...), save, and share one link with your listeners. Only releases that are live in stores are listed.</div>
                    <form onSubmit={(e) => { e.preventDefault(); go(); }} className="mt-3 flex flex-wrap gap-2">
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search by title, artist or UPC" className="h-11 min-w-0 flex-1 rounded-xl border border-slate-300 px-3 text-sm" />
                        <button type="submit" className="h-11 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white hover:bg-violet-700">Search</button>
                    </form>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {TABS.map(([key, label]) => (
                            <button key={key} type="button" onClick={() => go({ show: key })} className={`rounded-full px-4 py-1.5 text-xs font-bold ${(filters.show ?? "") === key ? "bg-slate-900 text-white" : "bg-slate-100 text-slate-700 hover:bg-slate-200"}`}>{label}</button>
                        ))}
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {list.length === 0 ? (
                        <div className="px-5 py-10 text-center text-sm text-slate-500">No live release found here.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {list.map((r) => (
                                <div key={r.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
                                    <div className="min-w-0 flex-1">
                                        <Link href={`/releases/${r.id}/promo`} className="break-words text-sm font-bold text-slate-900 hover:text-violet-700">{r.title}</Link>
                                        <div className="text-xs text-slate-500">{r.artist}{show_label && r.label ? ` · ${r.label}` : ""}{r.release_date ? ` · released ${r.release_date}` : ""}</div>
                                        {r.url && <div className="mt-1 break-all text-[11px] text-slate-500" data-keep-wording>{r.url}</div>}
                                    </div>
                                    <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                                        <span className={`rounded-full px-3 py-1 text-xs font-bold ${r.page_on ? "bg-emerald-100 text-emerald-800" : "bg-slate-100 text-slate-600"}`}>{r.page_on ? `On · ${r.links} link${r.links === 1 ? "" : "s"} · ${r.views} view${r.views === 1 ? "" : "s"}` : r.links > 0 ? `Off · ${r.links} link${r.links === 1 ? "" : "s"} ready` : "No page yet"}</span>
                                        {r.url && <button type="button" onClick={() => copy(r)} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{copied === r.id ? "Copied" : "Copy link"}</button>}
                                        <Link href={`/releases/${r.id}/promo`} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-violet-700">{r.page_on ? "Open promo kit" : "Make promo kit"}</Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                    {rows && rows.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm">
                            {rows.prev_page_url ? <Link href={rows.prev_page_url} className="font-semibold text-violet-700">← Previous</Link> : <span />}
                            <span className="text-slate-500">Page {rows.current_page} of {rows.last_page}</span>
                            {rows.next_page_url ? <Link href={rows.next_page_url} className="font-semibold text-violet-700">Next →</Link> : <span />}
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
