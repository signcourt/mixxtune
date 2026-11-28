import { Head, Link, router, useForm, usePage } from "@inertiajs/react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* W239 MIXXTUNE_LIVE_LINKS_AGENT - staff screen */

const STATUS = {
    found: ["Links found", "bg-emerald-100 text-emerald-800"],
    partial: ["Some found, still looking", "bg-amber-100 text-amber-800"],
    searching: ["Looking", "bg-sky-100 text-sky-800"],
    gave_up: ["Not found in 3 weeks", "bg-slate-200 text-slate-700"],
};
const SOURCE_NAMES = { apple_music: "Apple Music", deezer: "Deezer", spotify: "Spotify", youtube: "YouTube + YouTube Music" };

export default function PromoAgentIndex({ role = "super_admin", ready = true, enabled = false, since = "", sources = {}, can_manage = false, rows = null, waiting = 0 }) {
    const { flash = {}, errors = {} } = usePage().props;
    const keys = useForm({ spotify_id: "", spotify_secret: "", youtube_key: "" });
    const list = rows?.data ?? [];

    const saveKeys = (e) => {
        e.preventDefault();
        keys.post("/admin/promo-agent/keys", { preserveScroll: true, onSuccess: () => keys.reset() });
    };
    const firstError = errors.keys || errors.check;

    return (
        <PanelLayout role={role} title="Live Links Agent" subtitle="Finds the store links of a song on its release date and tells the label it is live">
            <Head title="Live Links Agent" />

            <div className="mx-auto w-full max-w-6xl space-y-4">
                {flash.success && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.success}</div>}
                {firstError && <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{firstError}</div>}
                {!ready && <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">The agent's table is not set up yet.</div>}

                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div className="text-base font-bold text-slate-900">
                                The agent is <span className={enabled ? "text-emerald-700" : "text-rose-700"}>{enabled ? "ON" : "OFF"}</span>
                            </div>
                            <div className="mt-1 max-w-3xl text-xs text-slate-600">
                                Every hour it takes the live releases whose release date has come{enabled && since ? ` (from ${since} on)` : ""}, finds their store links by ISRC / UPC, switches the promo page on and tells the label by bell and e-mail. A song that is not in the stores yet is tried again every 6 hours for 3 weeks.
                                {enabled ? ` Waiting now: ${waiting}.` : ""}
                            </div>
                        </div>
                        {can_manage && (
                            <button type="button" onClick={() => router.post("/admin/promo-agent/toggle", { enabled: !enabled }, { preserveScroll: true })} className={`h-11 rounded-xl px-5 text-sm font-bold text-white ${enabled ? "bg-slate-700 hover:bg-slate-800" : "bg-violet-600 hover:bg-violet-700"}`}>
                                {enabled ? "Switch off" : "Switch on"}
                            </button>
                        )}
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        {Object.keys(SOURCE_NAMES).map((k) => (
                            <span key={k} className={`rounded-full px-3 py-1 text-xs font-semibold ${sources[k] ? "bg-emerald-100 text-emerald-800" : "bg-slate-100 text-slate-500"}`}>
                                {SOURCE_NAMES[k]}: {sources[k] ? "working" : "needs a key"}
                            </span>
                        ))}
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">JioSaavn, Gaana, Wynk, Amazon Music, Instagram: typed by hand in the Promo kit</span>
                    </div>
                </section>

                {can_manage && (
                    <form onSubmit={saveKeys} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div className="text-base font-bold text-slate-900">Keys for Spotify and YouTube</div>
                        <div className="mt-1 text-xs text-slate-600">Without them the agent still finds Apple Music and Deezer. Keys are stored encrypted and never shown again.</div>
                        <div className="mt-3 grid gap-3 sm:grid-cols-3">
                            <div>
                                <label htmlFor="w239-sid" className="block text-xs font-semibold text-slate-700">Spotify Client ID {sources.spotify ? "(saved)" : ""}</label>
                                <input id="w239-sid" type="text" autoComplete="off" value={keys.data.spotify_id} onChange={(e) => keys.setData("spotify_id", e.target.value)} className="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm" />
                            </div>
                            <div>
                                <label htmlFor="w239-ssec" className="block text-xs font-semibold text-slate-700">Spotify Client Secret {sources.spotify ? "(saved)" : ""}</label>
                                <input id="w239-ssec" type="password" autoComplete="new-password" value={keys.data.spotify_secret} onChange={(e) => keys.setData("spotify_secret", e.target.value)} className="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm" />
                            </div>
                            <div>
                                <label htmlFor="w239-yt" className="block text-xs font-semibold text-slate-700">YouTube (Google) API key {sources.youtube ? "(saved)" : ""}</label>
                                <input id="w239-yt" type="password" autoComplete="new-password" value={keys.data.youtube_key} onChange={(e) => keys.setData("youtube_key", e.target.value)} className="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm" />
                            </div>
                        </div>
                        <div className="mt-3 flex flex-wrap justify-end gap-2">
                            {sources.spotify && <button type="button" onClick={() => router.post("/admin/promo-agent/keys", { clear: ["spotify"] }, { preserveScroll: true })} className="h-10 rounded-xl border border-slate-300 px-4 text-xs font-semibold text-slate-700">Remove Spotify keys</button>}
                            {sources.youtube && <button type="button" onClick={() => router.post("/admin/promo-agent/keys", { clear: ["youtube"] }, { preserveScroll: true })} className="h-10 rounded-xl border border-slate-300 px-4 text-xs font-semibold text-slate-700">Remove YouTube key</button>}
                            <button type="submit" disabled={keys.processing} className="h-10 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white disabled:opacity-50">Save keys</button>
                        </div>
                    </form>
                )}

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 px-4 py-3 text-base font-bold text-slate-900 sm:px-5">Songs the agent has looked at</div>
                    {list.length === 0 ? (
                        <div className="px-5 py-10 text-center text-sm text-slate-500">Nothing yet. To try it on one song, open that release → Promo kit → "Find store links now".</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {list.map((r) => {
                                const [label, cls] = STATUS[r.status] ?? [r.status, "bg-slate-100 text-slate-700"];
                                return (
                                    <div key={r.release_id} className="flex flex-wrap items-start justify-between gap-3 px-4 py-3 sm:px-5">
                                        <div className="min-w-0 flex-1">
                                            <Link href={`/releases/${r.release_id}/promo`} className="break-words text-sm font-bold text-slate-900 hover:text-violet-700">{r.title}</Link>
                                            <div className="text-xs text-slate-500">{r.artist}{r.release_date ? ` · released ${r.release_date}` : ""}{r.owner ? ` · ${r.owner}` : ""}</div>
                                            <div className="mt-1 text-xs text-slate-600">{r.note}{r.checked ? ` Last look ${r.checked} (${r.attempts}).` : ""}</div>
                                            {r.url && <div className="mt-1 break-all text-[11px] text-slate-500" data-keep-wording>{r.url}{r.page_on ? "" : " (page is off)"}</div>}
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-2">
                                            <span className={`rounded-full px-3 py-1 text-xs font-bold ${cls}`}>{label}</span>
                                            <div className="flex flex-wrap justify-end gap-2">
                                                <button type="button" onClick={() => router.post(`/admin/promo-agent/${r.release_id}/check`, {}, { preserveScroll: true })} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Look again</button>
                                                {r.whatsapp ? (
                                                    <a href={r.whatsapp} target="_blank" rel="noopener noreferrer" onClick={() => router.post(`/admin/promo-agent/${r.release_id}/sent`, {}, { preserveScroll: true })} className="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50">
                                                        {r.whatsapp_sent ? "Send again on WhatsApp" : "Send on WhatsApp"}
                                                    </a>
                                                ) : (
                                                    <span className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs text-slate-500">{!r.has_phone ? "No phone in the profile" : "No public page yet"}</span>
                                                )}
                                            </div>
                                            {r.whatsapp_sent && <div className="text-[11px] text-slate-500">WhatsApp opened {r.whatsapp_sent}</div>}
                                        </div>
                                    </div>
                                );
                            })}
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
