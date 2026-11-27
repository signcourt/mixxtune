import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, Paperclip, Send } from "lucide-react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import { FilePicker, PriorityPill, StatusPill } from "@/V2/Shared/Support/supportUi";

/* W234 - one sub-label ticket, answered by the distributor */

export default function TicketShow({ role = "label", ticket, messages = [] }) {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const closed = ticket.status === "closed";
    const { data, setData, post, processing, errors, reset, transform } = useForm({ message: "", close: false, attachments: [] });

    const send = (close) => {
        if (processing || data.message.trim().length < 2) return;
        transform((d) => ({ ...d, close: close ? 1 : 0 }));
        post(`/label/distributor/tickets/${ticket.id}/reply`, { forceFormData: true, preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <PanelLayout role={role} title={ticket.ticket_number} subtitle={ticket.subject}>
            <Head title={ticket.subject} />

            <Link href="/label/distributor/tickets" className="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-violet-700">
                <ArrowLeft className="h-4 w-4" /> Sub-label tickets
            </Link>

            <div className="mx-auto max-w-4xl space-y-4">
                {flash.success && (
                    <div className="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        <CheckCircle2 className="h-4 w-4" /> {flash.success}
                    </div>
                )}

                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                            <div className="text-xs font-semibold text-violet-600">{ticket.ticket_number} · {ticket.category_label}</div>
                            <h1 className="mt-1 break-words text-lg font-bold text-slate-900">{ticket.subject}</h1>
                            <div className="mt-1 text-xs text-slate-500">From {ticket.from} · opened {ticket.created_at}{closed && ticket.closed_at ? ` · closed ${ticket.closed_at}` : ""}</div>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            <PriorityPill priority={ticket.priority} />
                            <StatusPill status={ticket.status} side="staff" />
                        </div>
                    </div>
                </section>

                <div className="space-y-3">
                    {messages.map((m) => (
                        <div key={m.id} className={`rounded-2xl border p-4 shadow-sm ${m.mine ? "border-violet-200 bg-violet-50 sm:ml-10" : "border-slate-200 bg-white sm:mr-10"}`}>
                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <span className="font-semibold text-slate-800">{m.author}{m.mine ? " · you" : m.from_customer ? " · sub-label" : ""}</span>
                                <span className="text-slate-500">{m.created_at}</span>
                            </div>
                            <div className="mt-2 whitespace-pre-wrap break-words text-sm text-slate-800">{m.message}</div>
                            {(m.attachments ?? []).length > 0 && (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {m.attachments.map((f, i) => (
                                        <a key={i} href={f.url} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            <Paperclip className="h-3.5 w-3.5" /> {f.name}
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    {closed && <div className="mb-3 text-sm font-semibold text-slate-700">This ticket is closed. Sending a reply opens it again.</div>}
                    <textarea
                        rows={4}
                        value={data.message}
                        onChange={(e) => setData("message", e.target.value)}
                        placeholder="Write your answer to the sub-label…"
                        className={`w-full rounded-xl border px-3.5 py-2.5 text-sm ${errors.message ? "border-red-400" : "border-slate-300"}`}
                    />
                    {errors.message && <div className="mt-1 text-xs text-red-600">{errors.message}</div>}
                    <div className="mt-3">
                        <FilePicker files={data.attachments} onChange={(f) => setData("attachments", f)} error={errors.attachments || errors["attachments.0"]} />
                    </div>
                    <div className="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                        <button type="button" onClick={() => send(true)} disabled={processing || data.message.trim().length < 2} className="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 disabled:opacity-40">
                            Send and close
                        </button>
                        <button type="button" onClick={() => send(false)} disabled={processing || data.message.trim().length < 2} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white disabled:opacity-40">
                            <Send className="h-4 w-4" /> Send reply
                        </button>
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
