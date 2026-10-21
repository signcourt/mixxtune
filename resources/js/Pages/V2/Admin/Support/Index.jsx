import { Head, Link, router, useForm } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, Loader2, Plus, Search, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import { FilePicker, PriorityPill, StatusPill } from "@/V2/Shared/Support/supportUi";

/* MIXXTUNE_SUPPORT_DESK_V1 */

const TABS = [
    ["needs_reply", "Needs reply"],
    ["open", "New"],
    ["customer_reply", "Customer replied"],
    ["admin_reply", "We replied"],
    ["waiting", "Waiting for customer"],
    ["closed", "Closed"],
    ["all", "All"],
];

export default function Index({ role = "admin", tickets = {}, counts = {}, filters = {}, categories = {}, lateHours = 24 }) {
    const [search, setSearch] = useState(filters.search || "");
    const [openNew, setOpenNew] = useState(false);
    const first = useRef(true);

    const go = (patch) =>
        router.get("/v2/admin/support", { ...filters, ...patch, page: undefined }, { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => go({ search }), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const select = "h-10 rounded-xl border border-slate-300 bg-white pl-3 pr-8 text-sm";
    const rows = tickets.data ?? [];

    return (
        <PanelLayout role={role} title="Support Management" subtitle="Tickets from labels and artists">
            <Head title="Support Management" />

            <div className="space-y-5">
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Stat label="Need a reply" value={counts.needs_reply} active={filters.status === "needs_reply"} onClick={() => go({ status: "needs_reply", assigned: "" })} />
                    <Stat label={`Late (over ${lateHours} h)`} value={counts.late} tone={counts.late ? "red" : ""} onClick={() => go({ status: "needs_reply" })} />
                    <Stat label="Not given to anyone" value={counts.unassigned} active={filters.assigned === "none"} onClick={() => go({ status: "all", assigned: "none" })} />
                    <Stat label="Given to me (open)" value={counts.mine} active={filters.assigned === "me"} onClick={() => go({ status: "all", assigned: "me" })} />
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex gap-1 overflow-x-auto border-b border-slate-200 px-3 pt-3">
                        {TABS.map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => go({ status: key })}
                                className={`shrink-0 rounded-t-lg border-b-2 px-3 pb-2.5 pt-1 text-sm font-semibold ${filters.status === key ? "border-violet-600 text-violet-700" : "border-transparent text-slate-500 hover:text-slate-700"}`}
                            >
                                {label} <span className="ml-1 rounded-full bg-slate-100 px-1.5 text-xs text-slate-600">{counts[key] ?? 0}</span>
                            </button>
                        ))}
                    </div>

                    <div className="flex flex-col gap-2 p-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <div className="relative min-w-0 flex-1 sm:min-w-[16rem]">
                            <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Ticket no., subject, name or e-mail"
                                className="h-10 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm"
                            />
                        </div>
                        <div className="grid grid-cols-3 gap-2 sm:flex">
                            <select value={filters.category} onChange={(e) => go({ category: e.target.value })} className={select}>
                                <option value="">All topics</option>
                                <option value="legal">Legal Operations cases</option>
                                {Object.entries(categories).map(([k, v]) => (
                                    <option key={k} value={k}>{v}</option>
                                ))}
                            </select>
                            <select value={filters.priority} onChange={(e) => go({ priority: e.target.value })} className={select}>
                                <option value="">Any priority</option>
                                <option value="urgent">Urgent</option>
                                <option value="high">High</option>
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                            </select>
                            <select value={filters.assigned} onChange={(e) => go({ assigned: e.target.value })} className={select}>
                                <option value="">Anyone</option>
                                <option value="me">Given to me</option>
                                <option value="none">Not given</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            onClick={() => setOpenNew(true)}
                            className="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700"
                        >
                            <Plus className="h-4 w-4" /> Open ticket for a user
                        </button>
                    </div>

                    {rows.length === 0 ? (
                        <div className="border-t border-slate-100 px-5 py-14 text-center">
                            <div className="text-sm font-semibold text-slate-700">No tickets here</div>
                            <div className="mt-1 text-xs text-slate-500">
                                {filters.status === "needs_reply" ? "Nothing is waiting for a reply. 🎉" : "Try another tab or clear the filters."}
                            </div>
                        </div>
                    ) : (
                        <ul className="divide-y divide-slate-100 border-t border-slate-100">
                            {rows.map((t) => (
                                <li key={t.id}>
                                    <Link href={`/v2/admin/support/${t.id}`} className={`flex flex-col gap-2 px-4 py-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-4 ${t.late ? "bg-red-50/40" : ""}`}>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2 text-xs">
                                                <span className="font-semibold text-violet-600">{t.ticket_number}</span>
                                                <span className="text-slate-400">· {t.category_label}</span>
                                                {t.late && (
                                                    <span className="inline-flex items-center gap-1 font-semibold text-red-600">
                                                        <AlertTriangle className="h-3.5 w-3.5" /> late
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-0.5 truncate text-sm font-semibold text-slate-900">{t.subject}</div>
                                            <div className="mt-0.5 truncate text-xs text-slate-500">
                                                {t.user?.name || "—"} ({t.user?.role === "admin" ? "manager" : t.user?.role}) · {t.messages_count} messages · last {t.last_reply_human}
                                                {t.assigned ? ` · with ${t.assigned}` : " · not given"}
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <PriorityPill priority={t.priority} />
                                            <StatusPill status={t.status} />
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}

                    {tickets.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                            {tickets.prev_page_url ? <Link href={tickets.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Previous</Link> : <span />}
                            <span className="text-xs text-slate-500">Page {tickets.current_page} of {tickets.last_page}</span>
                            {tickets.next_page_url ? <Link href={tickets.next_page_url} preserveScroll className="font-semibold text-violet-700">Next →</Link> : <span />}
                        </div>
                    )}
                </section>
            </div>

            {openNew && <NewTicketDialog categories={categories} onClose={() => setOpenNew(false)} />}
        </PanelLayout>
    );
}

function Stat({ label, value = 0, active = false, tone = "", onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-2xl border bg-white p-4 text-left shadow-sm ${active ? "border-violet-500 ring-2 ring-violet-100" : "border-slate-200"}`}
        >
            <div className="text-xs font-semibold text-slate-500">{label}</div>
            <div className={`mt-1 text-2xl font-black ${tone === "red" ? "text-red-600" : "text-slate-900"}`}>{value}</div>
        </button>
    );
}

function NewTicketDialog({ categories, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        user_id: "",
        subject: "",
        category: "general",
        priority: "normal",
        message: "",
        attachments: [],
    });
    const [q, setQ] = useState("");
    const [found, setFound] = useState([]);
    const [busy, setBusy] = useState(false);
    const [person, setPerson] = useState(null);

    useEffect(() => {
        if (q.trim().length < 2) {
            setFound([]);
            return;
        }
        const t = setTimeout(() => {
            setBusy(true);
            axios
                .get("/v2/admin/support/search-users", { params: { q } })
                .then(({ data: r }) => setFound(r.users || []))
                .finally(() => setBusy(false));
        }, 300);
        return () => clearTimeout(t);
    }, [q]);

    const submit = (e) => {
        e.preventDefault();
        post("/v2/admin/support", { forceFormData: true });
    };

    const input = "mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm";

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-0 sm:items-center sm:p-4" onClick={onClose}>
            <form
                onSubmit={submit}
                onClick={(e) => e.stopPropagation()}
                className="max-h-[92vh] w-full max-w-xl overflow-auto rounded-t-3xl bg-white p-5 shadow-xl sm:rounded-3xl sm:p-6"
            >
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="text-base font-semibold text-slate-900">Open a ticket for a user</h2>
                        <p className="mt-0.5 text-xs text-slate-500">They get a notice and can reply in their Support page.</p>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1 hover:bg-slate-100" aria-label="Close"><X className="h-5 w-5" /></button>
                </div>

                <div className="mt-4 space-y-4">
                    <div>
                        <span className="text-sm font-semibold text-slate-700">User</span>
                        {person ? (
                            <div className="mt-1.5 flex items-center justify-between rounded-xl border border-violet-300 bg-violet-50 px-3 py-2.5 text-sm">
                                <span className="truncate"><b>{person.name}</b> · {person.email}</span>
                                <button type="button" onClick={() => { setPerson(null); setData("user_id", ""); }} className="text-xs font-semibold text-violet-700 underline">Change</button>
                            </div>
                        ) : (
                            <>
                                <div className="relative">
                                    <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name, e-mail or client ID" className={input} />
                                    {busy && <Loader2 className="absolute right-3 top-5 h-4 w-4 animate-spin text-slate-400" />}
                                </div>
                                {found.length > 0 && (
                                    <ul className="mt-1 max-h-48 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200">
                                        {found.map((u) => (
                                            <li key={u.id}>
                                                <button type="button" onClick={() => { setPerson(u); setData("user_id", u.id); }} className="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">
                                                    <b>{u.name}</b> <span className="text-xs text-slate-500">{u.email} · {u.role}</span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </>
                        )}
                        {errors.user_id && <div className="mt-1 text-xs text-red-600">Choose a user.</div>}
                    </div>

                    <label className="block">
                        <span className="text-sm font-semibold text-slate-700">Subject</span>
                        <input value={data.subject} onChange={(e) => setData("subject", e.target.value)} className={input} />
                        {errors.subject && <span className="mt-1 block text-xs text-red-600">{errors.subject}</span>}
                    </label>

                    <div className="grid grid-cols-2 gap-3">
                        <label className="block">
                            <span className="text-sm font-semibold text-slate-700">Topic</span>
                            <select value={data.category} onChange={(e) => setData("category", e.target.value)} className={input}>
                                {Object.entries(categories).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </label>
                        <label className="block">
                            <span className="text-sm font-semibold text-slate-700">Priority</span>
                            <select value={data.priority} onChange={(e) => setData("priority", e.target.value)} className={input}>
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </label>
                    </div>

                    <label className="block">
                        <span className="text-sm font-semibold text-slate-700">Message</span>
                        <textarea rows={5} value={data.message} onChange={(e) => setData("message", e.target.value)} className="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                        {errors.message && <span className="mt-1 block text-xs text-red-600">{errors.message}</span>}
                    </label>

                    <FilePicker files={data.attachments} onChange={(f) => setData("attachments", f)} error={errors.attachments || errors["attachments.0"]} />
                </div>

                <div className="mt-5 flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" disabled={processing || !data.user_id} className="h-11 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white disabled:opacity-40">
                        {processing ? "Opening…" : "Open ticket"}
                    </button>
                </div>
            </form>
        </div>
    );
}
