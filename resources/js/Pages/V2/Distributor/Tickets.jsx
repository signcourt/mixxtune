import { Head, Link } from "@inertiajs/react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import { PriorityPill, StatusPill } from "@/V2/Shared/Support/supportUi";

/* W234 - the distributor's support desk: tickets opened by his sub-labels */

const TABS = [
    ["active", "Open"],
    ["waiting", "Waiting for you"],
    ["closed", "Closed"],
    ["all", "All"],
];

export default function Tickets({ role = "label", tab = "active", counts = {}, tickets = {} }) {
    const rows = tickets.data ?? [];

    return (
        <PanelLayout role={role} title="Sub-label Tickets" subtitle="Questions and problems sent by your sub-labels">
            <Head title="Sub-label Tickets" />

            <div className="mx-auto w-full max-w-5xl space-y-4">
                <div className="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm">
                    When one of your sub-labels opens a support ticket it comes here and your bell rings. Your answer goes back to them in their panel.
                </div>

                <div className="flex flex-wrap gap-2">
                    {TABS.map(([key, label]) => (
                        <Link
                            key={key}
                            href={`/label/distributor/tickets?tab=${key}`}
                            preserveScroll
                            className={`rounded-full px-4 py-1.5 text-sm font-semibold ${tab === key ? "bg-violet-600 text-white" : "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"}`}
                        >
                            {label} <span className="opacity-70">({counts[key] ?? 0})</span>
                        </Link>
                    ))}
                </div>

                {rows.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-slate-500">No tickets here.</div>
                ) : (
                    <div className="space-y-3">
                        {rows.map((t) => (
                            <Link key={t.id} href={`/label/distributor/tickets/${t.id}`} className="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-violet-300">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="text-xs font-semibold text-violet-600">{t.ticket_number} · {t.category}</div>
                                        <div className="mt-0.5 break-words text-base font-bold text-slate-900">{t.subject}</div>
                                        <div className="mt-1 text-xs text-slate-500">
                                            From {t.from}{t.label && t.label !== t.from ? ` (${t.label})` : ""} · {t.messages} message{t.messages === 1 ? "" : "s"}{t.last_reply_at ? ` · last ${t.last_reply_at}` : ""}
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <PriorityPill priority={t.priority} />
                                        <StatusPill status={t.status} side="staff" />
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                {tickets.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        {tickets.prev_page_url ? <Link href={tickets.prev_page_url} className="font-semibold text-violet-700">← Previous</Link> : <span />}
                        <span className="text-slate-500">Page {tickets.current_page} of {tickets.last_page}</span>
                        {tickets.next_page_url ? <Link href={tickets.next_page_url} className="font-semibold text-violet-700">Next →</Link> : <span />}
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}
