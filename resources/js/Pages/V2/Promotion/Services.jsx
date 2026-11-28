import { Head, useForm, usePage } from "@inertiajs/react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* W241 MIXXTUNE_PROMOTION - Promotion > Promotion services: packages on offer, the request form, my requests */

const CHIP = { new: "bg-sky-100 text-sky-800", in_talk: "bg-amber-100 text-amber-800", accepted: "bg-emerald-100 text-emerald-800", done: "bg-slate-200 text-slate-700", declined: "bg-rose-100 text-rose-800" };

export default function Services({ role = "label", ready = true, packages = [], releases = [], mine = [], statuses = {} }) {
    const { flash = {}, errors = {} } = usePage().props;
    const form = useForm({ package_id: "", release_id: "", message: "", budget: "", contact: "" });
    const submit = (e) => {
        e.preventDefault();
        form.post("/promotion/services/request", { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const pick = (id) => {
        form.setData("package_id", String(id));
        document.getElementById("w241-request")?.scrollIntoView({ behavior: "smooth" });
    };
    const firstError = errors.promotion || errors.message || errors.budget || errors.contact;
    const input = "h-11 w-full rounded-xl border border-slate-300 px-3 text-sm";

    return (
        <PanelLayout role={role} title="Promotion services" subtitle="Ask for a promotion of your song and follow the answer here">
            <Head title="Promotion services" />
            <div className="mx-auto w-full max-w-5xl space-y-4">
                {flash.success && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.success}</div>}
                {firstError && <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{firstError}</div>}
                {!ready && <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Promotion is not set up yet.</div>}

                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div className="text-base font-bold text-slate-900">Packages</div>
                    {packages.length === 0 ? (
                        <div className="mt-2 text-sm text-slate-500">No package is listed right now. You can still describe what you need in the form below.</div>
                    ) : (
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            {packages.map((p) => (
                                <div key={p.id} className={`flex flex-col rounded-2xl border p-4 ${String(form.data.package_id) === String(p.id) ? "border-violet-500 bg-violet-50" : "border-slate-200"}`}>
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="text-sm font-bold text-slate-900">{p.name}</div>
                                        {p.price && <div className="shrink-0 rounded-full bg-slate-900 px-3 py-1 text-xs font-bold text-white">{p.price}</div>}
                                    </div>
                                    {p.details && <div className="mt-2 flex-1 whitespace-pre-line text-xs text-slate-600">{p.details}</div>}
                                    <button type="button" onClick={() => pick(p.id)} className="mt-3 h-10 rounded-xl bg-violet-600 px-4 text-sm font-bold text-white hover:bg-violet-700">Ask for this</button>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <section id="w241-request" className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div className="text-base font-bold text-slate-900">Send a request</div>
                    <form onSubmit={submit} className="mt-3 grid gap-3 sm:grid-cols-2">
                        <label className="text-xs font-semibold text-slate-700">Package
                            <select value={form.data.package_id} onChange={(e) => form.setData("package_id", e.target.value)} className={`mt-1 ${input}`}>
                                <option value="">Something else (describe below)</option>
                                {packages.map((p) => <option key={p.id} value={p.id}>{p.name}{p.price ? ` - ${p.price}` : ""}</option>)}
                            </select>
                        </label>
                        <label className="text-xs font-semibold text-slate-700">For which release
                            <select value={form.data.release_id} onChange={(e) => form.setData("release_id", e.target.value)} className={`mt-1 ${input}`}>
                                <option value="">Not for one release</option>
                                {releases.map((r) => <option key={r.id} value={r.id}>{r.title}{r.artist ? ` - ${r.artist}` : ""}</option>)}
                            </select>
                        </label>
                        <label className="text-xs font-semibold text-slate-700 sm:col-span-2">What do you need
                            <textarea required value={form.data.message} onChange={(e) => form.setData("message", e.target.value)} rows={4} maxLength={2000} placeholder="e.g. Instagram reels promotion for 2 weeks, playlist pitching ..." className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        </label>
                        <label className="text-xs font-semibold text-slate-700">Your budget (optional)
                            <input value={form.data.budget} onChange={(e) => form.setData("budget", e.target.value)} maxLength={100} className={`mt-1 ${input}`} />
                        </label>
                        <label className="text-xs font-semibold text-slate-700">Phone / WhatsApp to reach you (optional)
                            <input value={form.data.contact} onChange={(e) => form.setData("contact", e.target.value)} maxLength={100} className={`mt-1 ${input}`} />
                        </label>
                        <div className="sm:col-span-2">
                            <button type="submit" disabled={form.processing || !ready} className="h-11 rounded-xl bg-violet-600 px-6 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-50">Send request</button>
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 px-4 py-3 text-base font-bold text-slate-900 sm:px-5">My requests</div>
                    {mine.length === 0 ? (
                        <div className="px-5 py-8 text-center text-sm text-slate-500">You have not sent a request yet.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {mine.map((m) => (
                                <div key={m.id} className="px-4 py-3 sm:px-5">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="text-sm font-bold text-slate-900">{m.package}{m.release ? ` · ${m.release}` : ""}</div>
                                        <span className={`rounded-full px-3 py-1 text-xs font-bold ${CHIP[m.status] ?? "bg-slate-100 text-slate-700"}`}>{statuses[m.status] ?? m.status}</span>
                                    </div>
                                    <div className="mt-1 whitespace-pre-line text-xs text-slate-600">{m.message}</div>
                                    <div className="mt-1 text-[11px] text-slate-500">Sent {m.sent_at}{m.budget ? ` · budget ${m.budget}` : ""}</div>
                                    {m.reply && <div className="mt-2 whitespace-pre-line rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-900"><span className="font-bold">Answer{m.replied_at ? ` (${m.replied_at})` : ""}: </span>{m.reply}</div>}
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
