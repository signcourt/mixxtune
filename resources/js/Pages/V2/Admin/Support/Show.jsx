import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, Lock, Send } from "lucide-react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import { FilePicker, Message, PriorityPill, StatusPill } from "@/V2/Shared/Support/supportUi";

/* MIXXTUNE_SUPPORT_DESK_V1 */

const QUICK_REPLIES = [
    ["Checking now", "Thank you for writing to us. We are checking this now and will reply here soon."],
    ["Need more details", "To help you faster, please share: the release title, UPC / ISRC, and a screenshot of the problem."],
    ["Release delivered", "Your release has been delivered to the stores. It can take 2–7 days to appear everywhere. Please check again and tell us if any store is still missing."],
    ["Statement ready", "Your statement for this month is ready. You can download it from Statements in your panel."],
    ["Payout sent", "Your payout has been sent to your registered bank account. It usually reaches you within 1–2 working days. The UTR number is shown in Withdrawals."],
    ["KYC needed", "To send your payout we need your KYC: PAN and bank details (account number, IFSC, account holder name). Please add them in KYC / Bank details."],
    ["Copyright claim", "We have received your claim details. We are contacting the platform and will update you here once they reply."],
    ["Solved — closing", "We are happy this is solved. We are closing this ticket now. If you need anything else, just reply here or open a new ticket."],
];

export default function Show({ role = "admin", ticket, messages = [], otherTickets = [], categories = {}, admins = [] }) {
    const flash = usePage().props?.flash ?? {};
    const [after, setAfter] = useState("admin_reply");

    const reply = useForm({ message: "", is_internal: false, status: "admin_reply", attachments: [] });

    const settings = useForm({
        assigned_admin_id: ticket.assigned_admin_id ?? "",
        priority: ticket.priority,
        status: ticket.status,
        category: ticket.category,
    });

    const sendReply = (e) => {
        e.preventDefault();
        reply.transform((d) => ({ ...d, status: d.is_internal ? undefined : after }));
        reply.post(`/admin/support/${ticket.id}/reply`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reply.reset(),
        });
    };

    const saveSettings = (patch = {}) => {
        settings.transform((d) => ({ ...d, ...patch, assigned_admin_id: (patch.assigned_admin_id ?? d.assigned_admin_id) || null }));
        settings.patch(`/admin/support/${ticket.id}`, { preserveScroll: true });
    };

    const select = "mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm";
    const closed = ticket.status === "closed";

    return (
        <PanelLayout role={role} title={ticket.ticket_number} subtitle={ticket.subject}>
            <Head title={`${ticket.ticket_number} · ${ticket.subject}`} />

            <Link href="/admin/support" className="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-violet-700">
                <ArrowLeft className="h-4 w-4" /> All tickets
            </Link>

            {flash.success && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4" /> {flash.success}
                </div>
            )}

            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-4">
                    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div className="min-w-0">
                                <div className="text-xs font-semibold text-violet-600">{ticket.ticket_number} · {categories[ticket.category] ?? ticket.category}</div>
                                <h1 className="mt-1 break-words text-lg font-bold text-slate-900">{ticket.subject}</h1>
                                <div className="mt-1 text-xs text-slate-500">Opened {ticket.created_at}</div>
                            </div>
                            <div className="flex gap-2">
                                <PriorityPill priority={ticket.priority} />
                                <StatusPill status={ticket.status} />
                            </div>
                        </div>
                    </section>

                    <div className="space-y-3">
                        {messages.map((m) => <Message key={m.id} m={m} />)}
                    </div>

                    <form onSubmit={sendReply} className={`rounded-2xl border p-4 shadow-sm sm:p-5 ${reply.data.is_internal ? "border-amber-300 bg-amber-50/60" : "border-slate-200 bg-white"}`}>
                        {closed && !reply.data.is_internal && (
                            <div className="mb-3 rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600">This ticket is closed. Sending a reply opens it again.</div>
                        )}

                        <div className="mb-2 flex flex-wrap gap-1.5">
                            {QUICK_REPLIES.map(([label, text]) => (
                                <button
                                    type="button"
                                    key={label}
                                    onClick={() => {
                                        reply.setData("message", reply.data.message ? `${reply.data.message}\n\n${text}` : text);
                                        if (label.startsWith("Solved")) setAfter("closed");
                                    }}
                                    className="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 hover:border-violet-300 hover:text-violet-700"
                                >
                                    {label}
                                </button>
                            ))}
                        </div>

                        <textarea
                            rows={5}
                            value={reply.data.message}
                            onChange={(e) => reply.setData("message", e.target.value)}
                            placeholder={reply.data.is_internal ? "Note for the team (the customer will not see this)…" : "Write your reply to the customer…"}
                            className={`w-full rounded-xl border px-3.5 py-2.5 text-sm ${reply.errors.message ? "border-red-400" : "border-slate-300"}`}
                        />
                        {reply.errors.message && <div className="mt-1 text-xs text-red-600">{reply.errors.message}</div>}

                        <div className="mt-3">
                            <FilePicker files={reply.data.attachments} onChange={(f) => reply.setData("attachments", f)} error={reply.errors.attachments || reply.errors["attachments.0"]} />
                        </div>

                        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <label className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <input type="checkbox" checked={reply.data.is_internal} onChange={(e) => reply.setData("is_internal", e.target.checked)} />
                                <Lock className="h-4 w-4 text-amber-600" /> Internal note only
                            </label>

                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                {!reply.data.is_internal && (
                                    <select value={after} onChange={(e) => setAfter(e.target.value)} className="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm">
                                        <option value="admin_reply">Send — keep open</option>
                                        <option value="waiting">Send — wait for customer</option>
                                        <option value="closed">Send — and close</option>
                                    </select>
                                )}
                                <button type="submit" disabled={reply.processing || reply.data.message.trim().length < 2} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white disabled:opacity-40">
                                    <Send className="h-4 w-4" /> {reply.data.is_internal ? "Save note" : "Send reply"}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <aside className="space-y-4">
                    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h2 className="text-sm font-semibold text-slate-900">Customer</h2>
                        {ticket.user ? (
                            <div className="mt-2 text-sm">
                                <div className="font-semibold text-slate-800">{ticket.user.name}</div>
                                <div className="break-all text-xs text-slate-500">{ticket.user.email}</div>
                                <div className="mt-1 text-xs text-slate-500">
                                    {ticket.user.role === "admin" ? "manager" : ticket.user.role}
                                    {ticket.user.client_id ? ` · client ${ticket.user.client_id}` : ""}
                                </div>
                                {role === "super_admin" && (
                                    <a href={`/admin/users/${ticket.user.id}/edit`} className="mt-2 inline-block text-xs font-semibold text-violet-700 underline">Open user</a>
                                )}
                            </div>
                        ) : (
                            <div className="mt-2 text-sm text-slate-500">User removed</div>
                        )}
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h2 className="text-sm font-semibold text-slate-900">Ticket</h2>
                        <div className="mt-2 space-y-3">
                            <label className="block text-xs font-semibold text-slate-600">
                                Given to
                                <select value={settings.data.assigned_admin_id} onChange={(e) => settings.setData("assigned_admin_id", e.target.value)} className={select}>
                                    <option value="">Nobody</option>
                                    {admins.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                                </select>
                            </label>
                            <label className="block text-xs font-semibold text-slate-600">
                                Priority
                                <select value={settings.data.priority} onChange={(e) => settings.setData("priority", e.target.value)} className={select}>
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </label>
                            <label className="block text-xs font-semibold text-slate-600">
                                Topic
                                <select value={settings.data.category} onChange={(e) => settings.setData("category", e.target.value)} className={select}>
                                    {Object.entries(categories).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                            </label>
                            <label className="block text-xs font-semibold text-slate-600">
                                Status
                                <select value={settings.data.status} onChange={(e) => settings.setData("status", e.target.value)} className={select}>
                                    <option value="open">New</option>
                                    <option value="customer_reply">Customer replied</option>
                                    <option value="admin_reply">We replied</option>
                                    <option value="waiting">Waiting for customer</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </label>
                            <button type="button" disabled={settings.processing} onClick={() => saveSettings()} className="h-10 w-full rounded-xl bg-slate-900 text-sm font-semibold text-white disabled:opacity-40">
                                Save
                            </button>
                            {closed ? (
                                <button type="button" onClick={() => saveSettings({ status: "admin_reply" })} className="h-10 w-full rounded-xl border border-slate-300 text-sm font-semibold text-slate-700">
                                    Reopen ticket
                                </button>
                            ) : (
                                <button type="button" onClick={() => saveSettings({ status: "closed" })} className="h-10 w-full rounded-xl border border-slate-300 text-sm font-semibold text-slate-700">
                                    Close without reply
                                </button>
                            )}
                        </div>
                    </section>

                    {otherTickets.length > 0 && (
                        <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h2 className="text-sm font-semibold text-slate-900">Other tickets from this user</h2>
                            <ul className="mt-2 space-y-1.5">
                                {otherTickets.map((o) => (
                                    <li key={o.id}>
                                        <Link href={`/admin/support/${o.id}`} className="flex items-center justify-between gap-2 text-xs hover:underline">
                                            <span className="truncate text-slate-700">{o.subject}</span>
                                            <StatusPill status={o.status} />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </aside>
            </div>
        </PanelLayout>
    );
}
