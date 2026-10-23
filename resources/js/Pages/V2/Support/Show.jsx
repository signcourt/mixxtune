import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, Send } from "lucide-react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import { FilePicker, Message, StatusPill } from "@/V2/Shared/Support/supportUi";

/* MIXXTUNE_SUPPORT_DESK_V1 */

export default function Show({ role = "artist", ticket, messages = [] }) {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const pageErrors = page.props?.errors ?? {};
    const closed = ticket.status === "closed";

    const { data, setData, post, processing, errors, reset } = useForm({ message: "", attachments: [] });

    const submit = (e) => {
        e.preventDefault();
        post(`/support/${ticket.id}/reply`, { forceFormData: true, preserveScroll: true, onSuccess: () => reset() });
    };

    const act = (what) => {
        if (what === "close" && !window.confirm("Close this ticket? Use this when your problem is solved.")) return;
        router.post(`/support/${ticket.id}/${what}`, {}, { preserveScroll: true });
    };

    return (
        <PanelLayout role={role} title={ticket.ticket_number} subtitle={ticket.subject}>
            <Head title={ticket.subject} />

            <Link href="/support" className="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-violet-700">
                <ArrowLeft className="h-4 w-4" /> My tickets
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
                            <div className="mt-1 text-xs text-slate-500">Opened {ticket.created_at}{closed && ticket.closed_at ? ` · closed ${ticket.closed_at}` : ""}</div>
                        </div>
                        <StatusPill status={ticket.status} side="owner" />
                    </div>
                </section>

                <div className="space-y-3">
                    {messages.map((m) => <Message key={m.id} m={m} ownerSide />)}
                </div>

                {pageErrors.message && !data.message && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{pageErrors.message}</div>
                )}

                {closed ? (
                    <section className="rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-sm sm:p-5">
                        <div className="font-semibold text-slate-800">This ticket is closed.</div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {ticket.can_reopen && (
                                <button type="button" onClick={() => act("reopen")} className="h-10 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white">
                                    Reopen — still not solved
                                </button>
                            )}
                            <Link href="/support/create" className="inline-flex h-10 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700">
                                Open a new ticket
                            </Link>
                        </div>
                    </section>
                ) : (
                    <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <textarea
                            rows={4}
                            value={data.message}
                            onChange={(e) => setData("message", e.target.value)}
                            placeholder="Write your reply…"
                            className={`w-full rounded-xl border px-3.5 py-2.5 text-sm ${errors.message ? "border-red-400" : "border-slate-300"}`}
                        />
                        {errors.message && <div className="mt-1 text-xs text-red-600">{errors.message}</div>}
                        <div className="mt-3">
                            <FilePicker files={data.attachments} onChange={(f) => setData("attachments", f)} error={errors.attachments || errors["attachments.0"]} />
                        </div>
                        <div className="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <button type="button" onClick={() => act("close")} className="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700">
                                My problem is solved — close
                            </button>
                            <button type="submit" disabled={processing || data.message.trim().length < 2} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white disabled:opacity-40">
                                <Send className="h-4 w-4" /> Send reply
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </PanelLayout>
    );
}
