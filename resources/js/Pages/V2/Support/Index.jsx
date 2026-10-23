import { Head, Link, router } from "@inertiajs/react";
import { LifeBuoy, Plus } from "lucide-react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import { StatusPill } from "@/V2/Shared/Support/supportUi";

/* MIXXTUNE_SUPPORT_DESK_V1 */

const TABS = [
    ["active", "Open"],
    ["reply", "Your turn"],
    ["closed", "Closed"],
    ["all", "All"],
];

const QUICK = [
    ["release", "Release / delivery problem"],
    ["royalty", "Royalty or statement question"],
    ["withdrawal", "Payout not received"],
    ["kyc", "KYC / bank details"],
    ["copyright", "Copyright / claim"],
    ["technical", "Panel or login problem"],
];

export default function Index({ role = "artist", tickets = {}, tab = "active", counts = {}, categories = {}, legal = false, legalSections = {} }) {
    const rows = tickets.data ?? [];
    /* W73: "Case History" = the same list, only Legal Operations cases */
    const extra = legal ? { legal_section: "history" } : {};

    return (
        <PanelLayout role={role} title={legal ? "Case History" : "Support"} subtitle={legal ? "Your Legal Operations cases and their status" : "Ask us anything — we reply here"}>
            <Head title={legal ? "Case History" : "Support"} />

            <div className="space-y-5">
                {counts.reply > 0 && (
                    <button
                        type="button"
                        onClick={() => router.get("/support", { ...extra, tab: "reply" }, { preserveScroll: true })}
                        className="w-full rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-left text-sm font-semibold text-violet-800"
                    >
                        We replied on {counts.reply} ticket{counts.reply === 1 ? "" : "s"} — please have a look.
                    </button>
                )}

                {legal ? (
                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <LifeBuoy className="h-5 w-5 text-violet-600" />
                            <h2 className="text-base font-semibold text-slate-900">Open a new legal case</h2>
                        </div>
                        <Link href="/legal-operations" className="text-xs font-semibold text-violet-700">All legal topics →</Link>
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                        {Object.entries(legalSections).map(([key, label]) => (
                            <Link key={key} href={`/support/create?legal_section=${key}`} className="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-700 hover:border-violet-300 hover:bg-violet-50/50">
                                {label}
                            </Link>
                        ))}
                    </div>
                </section>
                ) : (
                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <LifeBuoy className="h-5 w-5 text-violet-600" />
                            <h2 className="text-base font-semibold text-slate-900">Need help with…</h2>
                        </div>
                        <Link href="/support/create" className="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700">
                            <Plus className="h-4 w-4" /> New ticket
                        </Link>
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                        {QUICK.map(([cat, label]) => (
                            <Link key={cat} href={`/support/create?category=${cat}`} className="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-700 hover:border-violet-300 hover:bg-violet-50/50">
                                {label}
                            </Link>
                        ))}
                    </div>
                </section>
                )}

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex gap-1 overflow-x-auto border-b border-slate-200 px-3 pt-3">
                        {TABS.map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => router.get("/support", { ...extra, tab: key }, { preserveScroll: true, preserveState: true })}
                                className={`shrink-0 border-b-2 px-3 pb-2.5 pt-1 text-sm font-semibold ${tab === key ? "border-violet-600 text-violet-700" : "border-transparent text-slate-500"}`}
                            >
                                {label} <span className="ml-1 rounded-full bg-slate-100 px-1.5 text-xs text-slate-600">{counts[key] ?? 0}</span>
                            </button>
                        ))}
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-5 py-14 text-center">
                            <div className="text-sm font-semibold text-slate-700">{legal ? "No legal cases here" : "No tickets here"}</div>
                            <div className="mt-1 text-xs text-slate-500">{legal ? "Pick a legal topic above to open a case." : 'Press "New ticket" or one of the topics above to ask us something.'}</div>
                        </div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {rows.map((t) => (
                                <li key={t.id}>
                                    <Link href={`/support/${t.id}`} className="flex flex-col gap-2 px-4 py-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0">
                                            <div className="text-xs font-semibold text-violet-600">
                                                {t.ticket_number} <span className="font-normal text-slate-400">· {t.legal_section ? legalSections[t.legal_section] ?? "Legal case" : categories[t.category] ?? t.category}</span>
                                            </div>
                                            <div className="mt-0.5 truncate text-sm font-semibold text-slate-900">{t.subject}</div>
                                            <div className="mt-0.5 text-xs text-slate-500">{t.messages_count} message{t.messages_count === 1 ? "" : "s"}</div>
                                        </div>
                                        <StatusPill status={t.status} side="owner" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}

                    {tickets.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
                            {tickets.prev_page_url ? <Link href={tickets.prev_page_url} className="font-semibold text-violet-700">← Previous</Link> : <span />}
                            <span className="text-xs text-slate-500">Page {tickets.current_page} of {tickets.last_page}</span>
                            {tickets.next_page_url ? <Link href={tickets.next_page_url} className="font-semibold text-violet-700">Next →</Link> : <span />}
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
