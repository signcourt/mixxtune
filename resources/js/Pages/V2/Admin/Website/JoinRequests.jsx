import { Head, Link, router, usePage } from "@inertiajs/react";
import { AlertTriangle, CalendarClock, CheckCircle2, Mail, Search, Send, UserCheck, X } from "lucide-react";
import { useEffect, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/*
 * W121 MIXXTUNE_WEBSITE_JOIN
 * Website -> Join Requests (Super Admin): people who pressed "Join" on the
 * homepage. Call / WhatsApp / e-mail them, keep call notes, set the next
 * follow-up and turn them into an account.
 */
const BADGE = {
    new: "bg-amber-100 text-amber-900",
    called: "bg-sky-100 text-sky-900",
    discussion: "bg-violet-100 text-violet-900",
    joined: "bg-emerald-100 text-emerald-900",
    not_interested: "bg-slate-200 text-slate-700",
    spam: "bg-red-100 text-red-800",
};

const PhoneIcon = () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" />
    </svg>
);
const ChatIcon = () => (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        <path d="M4 20l1.5-4A8 8 0 1 1 8 18.5z" />
    </svg>
);

function Detail({ open, statuses, staff, close }) {
    const { errors = {} } = usePage().props;
    const [status, setStatus] = useState(open.status);
    const [follow, setFollow] = useState(open.next_follow_up_at || "");
    const [handledBy, setHandledBy] = useState(open.handled_by ? String(open.handled_by) : "");
    const [note, setNote] = useState("");
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        setStatus(open.status);
        setFollow(open.next_follow_up_at || "");
        setHandledBy(open.handled_by ? String(open.handled_by) : "");
    }, [open.id, open.status, open.next_follow_up_at, open.handled_by]);

    const save = () => {
        setBusy(true);
        router.post(`/admin/join-requests/${open.id}`, { status, next_follow_up_at: follow || null, handled_by: handledBy || null }, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    const addNote = (e) => {
        e.preventDefault();
        if (!note.trim()) return;
        setBusy(true);
        router.post(`/admin/join-requests/${open.id}/notes`, { body: note }, { preserveScroll: true, onSuccess: () => setNote(""), onFinish: () => setBusy(false) });
    };
    const label = statuses.find((s) => s.key === open.status)?.label ?? open.status;

    return (
        <section aria-label="Selected request" className="min-w-0 flex-[1_1_380px] rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-4">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="truncate text-lg font-bold text-slate-900">{open.name}</h2>
                    <p className="mt-0.5 text-sm text-slate-500">{[open.role, open.brand, open.city].filter(Boolean).join(" · ") || "—"}</p>
                </div>
                <div className="flex items-center gap-2">
                    <span className={`whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ${BADGE[open.status] ?? "bg-slate-100 text-slate-700"}`}>{label}</span>
                    <button type="button" onClick={close} aria-label="Close" className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500">
                        <X className="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
                {open.tel && (
                    <a href={`tel:${open.tel}`} className="inline-flex min-h-[44px] flex-[1_1_100px] items-center justify-center gap-2 rounded-xl bg-violet-600 px-3 text-sm font-semibold text-white">
                        <PhoneIcon /> Call
                    </a>
                )}
                {open.whatsapp && (
                    <a href={open.whatsapp} target="_blank" rel="noreferrer" className="inline-flex min-h-[44px] flex-[1_1_100px] items-center justify-center gap-2 rounded-xl bg-emerald-50 px-3 text-sm font-semibold text-emerald-800">
                        <ChatIcon /> WhatsApp
                    </a>
                )}
                {open.email && (
                    <a href={`mailto:${open.email}`} className="inline-flex min-h-[44px] flex-[1_1_100px] items-center justify-center gap-2 rounded-xl bg-violet-50 px-3 text-sm font-semibold text-violet-900">
                        <Mail className="h-4 w-4" /> Email
                    </a>
                )}
            </div>

            <dl className="mt-4 grid grid-cols-[120px_1fr] gap-x-3 gap-y-2 text-sm">
                <dt className="text-slate-500">Received</dt>
                <dd className="text-slate-900">{open.received}</dd>
                {open.answers.map((a, i) => (
                    <div key={i} className="contents">
                        <dt className="text-slate-500">{a.label}</dt>
                        <dd className="whitespace-pre-line break-words text-slate-900">{a.value || "—"}</dd>
                    </div>
                ))}
            </dl>

            <div className="mt-5 grid gap-3 sm:grid-cols-2">
                <label className="flex flex-col gap-1.5 text-xs font-semibold text-slate-600">
                    Status
                    <select value={status} onChange={(e) => setStatus(e.target.value)} className="h-11 rounded-xl border border-slate-300 bg-white px-2 text-sm text-slate-900">
                        {statuses.map((s) => (
                            <option key={s.key} value={s.key}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="flex flex-col gap-1.5 text-xs font-semibold text-slate-600">
                    Next follow-up (India time)
                    <input type="datetime-local" value={follow} onChange={(e) => setFollow(e.target.value)} className="h-11 rounded-xl border border-slate-300 bg-white px-2 text-sm text-slate-900" />
                    {errors.next_follow_up_at && <span className="text-red-600">{errors.next_follow_up_at}</span>}
                </label>
                <label className="flex flex-col gap-1.5 text-xs font-semibold text-slate-600 sm:col-span-2">
                    Handled by
                    <select value={handledBy} onChange={(e) => setHandledBy(e.target.value)} className="h-11 rounded-xl border border-slate-300 bg-white px-2 text-sm text-slate-900">
                        <option value="">Nobody yet</option>
                        {staff.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </select>
                    {errors.handled_by && <span className="text-red-600">{errors.handled_by}</span>}
                </label>
            </div>
            <div className="mt-3 flex flex-wrap gap-2">
                <button type="button" onClick={save} disabled={busy} className="h-11 flex-[1_1_120px] rounded-xl bg-violet-600 px-4 text-sm font-bold text-white disabled:opacity-50">
                    Save
                </button>
                <Link href="/admin/users/create" className="inline-flex h-11 flex-[1_1_200px] items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-bold text-emerald-800">
                    <UserCheck className="h-4 w-4" /> Joined → Create account
                </Link>
            </div>

            <h3 className="mt-6 text-sm font-bold text-slate-900">Call notes</h3>
            <form onSubmit={addNote} className="mt-2 flex flex-col gap-2">
                <label htmlFor="jr-note" className="text-xs font-semibold text-slate-600">
                    Add a note
                </label>
                <textarea id="jr-note" rows={2} maxLength={2000} value={note} onChange={(e) => setNote(e.target.value)} placeholder="What was discussed on the call?" className="rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                {errors.body && <span className="text-xs text-red-600">{errors.body}</span>}
                <button type="submit" disabled={busy || !note.trim()} className="inline-flex h-10 items-center justify-center gap-2 self-start rounded-xl border border-violet-300 bg-white px-4 text-sm font-semibold text-violet-800 disabled:opacity-40">
                    <Send className="h-4 w-4" /> Add note
                </button>
            </form>
            <ol className="mt-3 space-y-2 text-sm">
                {open.notes.map((n) => (
                    <li key={n.id} className={`rounded-xl border px-3 py-2 ${n.kind === "note" ? "border-violet-100 bg-violet-50/50" : "border-slate-100 bg-slate-50"}`}>
                        <div className="whitespace-pre-line break-words text-slate-900">{n.body}</div>
                        <div className="mt-1 text-xs text-slate-500">
                            {n.by ?? "Website"} · {n.at}
                        </div>
                    </li>
                ))}
            </ol>
        </section>
    );
}

export default function JoinRequests({ tab = "new", q = "", tabs = [], statuses = [], rows = [], pagination = {}, open = null, staff = [] }) {
    const { flash = {}, errors = {} } = usePage().props;
    const [search, setSearch] = useState(q);

    const go = (params) => router.get("/admin/join-requests", { tab, q: search || undefined, ...params }, { preserveState: true, preserveScroll: true, replace: true });
    const openRow = (id) => go({ open: id, page: pagination.current > 1 ? pagination.current : undefined });

    return (
        <PanelLayout role="super_admin" title="Join Requests" subtitle='People who pressed "Join" on mixxtune.com'>
            <Head title="Join Requests" />

            {flash.success && (
                <div role="status" className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4 shrink-0" /> {flash.success}
                </div>
            )}
            {errors.status && (
                <div role="alert" className="mb-4 flex items-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <AlertTriangle className="h-4 w-4 shrink-0" /> {errors.status}
                </div>
            )}

            <div className="flex flex-wrap items-end justify-between gap-3">
                <div role="tablist" aria-label="Status" className="flex flex-wrap gap-2">
                    {tabs.map((t) => (
                        <button
                            key={t.key}
                            type="button"
                            role="tab"
                            aria-selected={t.key === tab}
                            onClick={() => router.get("/admin/join-requests", { tab: t.key, q: search || undefined }, { preserveState: true, preserveScroll: true })}
                            className={`inline-flex h-10 items-center gap-2 rounded-full border px-4 text-sm font-semibold ${t.key === tab ? "border-violet-600 bg-violet-600 text-white" : "border-slate-300 bg-white text-slate-700"}`}
                        >
                            {t.key === "today" && <CalendarClock className="h-4 w-4" />}
                            {t.label}
                            <span className={`rounded-full px-2 text-xs ${t.key === tab ? "bg-white/20" : t.key === "today" && t.count > 0 ? "bg-red-100 text-red-800" : "bg-violet-50 text-violet-800"}`}>{t.count}</span>
                        </button>
                    ))}
                </div>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get("/admin/join-requests", { tab, q: search || undefined }, { preserveState: true });
                    }}
                    className="flex items-end gap-2"
                >
                    <label className="flex flex-col gap-1 text-xs font-semibold text-slate-600">
                        Search
                        <span className="relative">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Name, phone, label or city" className="h-11 w-64 max-w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-sm" />
                        </span>
                    </label>
                    <button type="submit" className="h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">
                        Find
                    </button>
                </form>
            </div>

            <div className="mt-5 flex flex-wrap items-start gap-5">
                <section aria-label="Requests" className="min-w-0 flex-[1_1_560px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {rows.length === 0 ? (
                        <p className="px-5 py-12 text-center text-sm text-slate-500">{q ? "Nothing found." : "No join requests here yet."}</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">Person</th>
                                        <th className="px-3 py-3 font-semibold">Joining as</th>
                                        <th className="px-3 py-3 font-semibold">Received</th>
                                        <th className="px-3 py-3 font-semibold">Status</th>
                                        <th className="px-4 py-3 font-semibold">Next follow-up</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {rows.map((r) => {
                                        const st = statuses.find((s) => s.key === r.status)?.label ?? r.status;
                                        const active = open?.id === r.id;
                                        return (
                                            <tr key={r.id} className={`cursor-pointer hover:bg-violet-50/50 ${active ? "bg-violet-50 shadow-[inset_3px_0_0_#7c3aed]" : ""}`} onClick={() => openRow(r.id)}>
                                                <td className="px-4 py-3">
                                                    <button type="button" onClick={(e) => { e.stopPropagation(); openRow(r.id); }} className="text-left">
                                                        <span className="block font-bold text-slate-900">{r.name}</span>
                                                        <span className="text-xs text-slate-500">{[r.brand, r.city, r.phone].filter(Boolean).join(" · ")}</span>
                                                    </button>
                                                </td>
                                                <td className="px-3 py-3 text-slate-700">{r.role || "—"}</td>
                                                <td className="px-3 py-3 text-slate-500">{r.received}</td>
                                                <td className="px-3 py-3">
                                                    <span className={`whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ${BADGE[r.status] ?? "bg-slate-100 text-slate-700"}`}>{st}</span>
                                                </td>
                                                <td className={`px-4 py-3 text-xs ${r.follow_due ? "font-bold text-red-700" : "text-slate-500"}`}>{r.follow || "Not set"}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {pagination.last > 1 && (
                        <div className="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-3 text-sm">
                            <span className="text-slate-500">
                                Page {pagination.current} of {pagination.last} · {pagination.total} requests
                            </span>
                            <span className="flex gap-2">
                                {pagination.prev && (
                                    <Link href={pagination.prev} preserveScroll className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold text-slate-700">
                                        Previous
                                    </Link>
                                )}
                                {pagination.next && (
                                    <Link href={pagination.next} preserveScroll className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold text-slate-700">
                                        Next
                                    </Link>
                                )}
                            </span>
                        </div>
                    )}
                </section>

                {open ? (
                    <Detail open={open} statuses={statuses} staff={staff} close={() => go({ open: undefined })} />
                ) : (
                    <section className="min-w-0 flex-[1_1_380px] rounded-2xl border border-dashed border-slate-300 bg-white/60 p-8 text-center text-sm text-slate-500">Pick a request on the left to call, note and follow up.</section>
                )}
            </div>
        </PanelLayout>
    );
}
