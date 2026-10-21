import { Head, Link } from "@inertiajs/react";
import { ArrowRight, FileWarning, FolderClock, Gavel, MonitorPlay, Music2, Plus, Radio, ShieldCheck } from "lucide-react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import { StatusPill } from "@/V2/Shared/Support/supportUi";

/*
 * W73 MIXXTUNE_LEGAL_OPS_V2
 * Every card opens the matching case form (before: most cards opened the
 * plain support form, and "Rights & Ownership" opened an admin-only page).
 */
const SECTIONS = [
    {
        key: "copyright",
        title: "Copyright & Claims",
        icon: FileWarning,
        text: "Wrong copyright claim, dispute, Content ID claim, strike or takedown on your music.",
        examples: "Someone claimed my song · claim on my own video",
    },
    {
        key: "youtube",
        title: "YouTube Operations",
        icon: MonitorPlay,
        text: "Content ID claims and releases, copyright strikes, blocked videos, channel issues.",
        examples: "Release a Content ID claim · video blocked",
    },
    {
        key: "spotify",
        title: "Spotify Operations",
        icon: Music2,
        text: "Artist profile, songs on the wrong profile, metadata, rights or removal on Spotify.",
        examples: "Song on wrong artist page · profile claim",
    },
    {
        key: "dsp",
        title: "Other DSP Operations",
        icon: Radio,
        text: "Same problems on JioSaavn, Apple Music, Amazon, Gaana, Wynk and other stores.",
        examples: "Wrong artwork on a store · release missing",
    },
    {
        key: "rights",
        title: "Rights & Ownership",
        icon: ShieldCheck,
        text: "Prove you own a song, transfer rights, ownership disputes and authorisation letters.",
        examples: "Rights transfer · ownership proof",
    },
    {
        key: "request",
        title: "Legal Requests",
        icon: Gavel,
        text: "Legal notice, contract or agreement questions, and any other legal matter.",
        examples: "Legal notice received · agreement copy",
    },
];

export default function Index({ role = "artist", counts = {}, recent = [] }) {
    const sections = counts.sections ?? {};
    const titleOf = Object.fromEntries(SECTIONS.map((s) => [s.key, s.title]));

    return (
        <PanelLayout role={role} title="Legal Operations" subtitle="Copyright, platform, rights and legal cases">
            <Head title="Legal Operations" />

            <div className="mx-auto w-full max-w-[1500px] space-y-5">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div className="max-w-2xl">
                            <h1 className="text-xl font-bold text-slate-900">Legal Operations</h1>
                            <p className="mt-1 text-sm leading-6 text-slate-500">
                                Pick the type of problem below and fill in one short form. Each case gets a number, our team replies inside the case, and you can follow
                                every case under <b>Case History</b>.
                            </p>
                        </div>
                        <div className="grid grid-cols-3 gap-2 sm:w-[420px]">
                            <Link href="/v2/support?legal_section=history" className="rounded-xl border border-slate-200 px-3 py-2.5 hover:border-violet-300">
                                <span className="block text-[11px] font-semibold text-slate-500">Open cases</span>
                                <span className="text-xl font-black text-slate-950">{counts.open ?? 0}</span>
                            </Link>
                            <Link
                                href="/v2/support?legal_section=history&tab=reply"
                                className={`rounded-xl border px-3 py-2.5 ${Number(counts.your_turn || 0) > 0 ? "border-amber-300 bg-amber-50" : "border-slate-200 hover:border-violet-300"}`}
                            >
                                <span className="block text-[11px] font-semibold text-slate-500">We replied</span>
                                <span className="text-xl font-black text-slate-950">{counts.your_turn ?? 0}</span>
                            </Link>
                            <Link href="/v2/support?legal_section=history&tab=closed" className="rounded-xl border border-slate-200 px-3 py-2.5 hover:border-violet-300">
                                <span className="block text-[11px] font-semibold text-slate-500">Closed</span>
                                <span className="text-xl font-black text-slate-950">{counts.closed ?? 0}</span>
                            </Link>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {SECTIONS.map(({ key, title, icon: Icon, text, examples }) => (
                        <article key={key} className="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-violet-300 hover:shadow-md">
                            <div className="flex items-start gap-3">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
                                    <Icon size={20} />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center justify-between gap-2">
                                        <h2 className="text-base font-bold text-slate-900">{title}</h2>
                                        {Number(sections[key] || 0) > 0 && (
                                            <span className="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">{sections[key]} open</span>
                                        )}
                                    </div>
                                    <p className="mt-1 text-sm leading-6 text-slate-500">{text}</p>
                                    <p className="mt-1 text-xs text-slate-400">e.g. {examples}</p>
                                </div>
                            </div>
                            <Link
                                href={`/v2/support/create?legal_section=${key}`}
                                className="mt-4 inline-flex items-center justify-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-700"
                            >
                                <Plus size={16} /> New case
                            </Link>
                        </article>
                    ))}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div className="flex items-center gap-2">
                            <FolderClock className="h-4 w-4 text-slate-500" />
                            <h2 className="text-base font-bold text-slate-900">Latest cases</h2>
                        </div>
                        <Link href="/v2/support?legal_section=history&tab=all" className="inline-flex items-center gap-1 text-xs font-bold text-violet-700">
                            Case History <ArrowRight size={14} />
                        </Link>
                    </div>
                    {recent.length === 0 ? (
                        <div className="px-5 py-10 text-center text-sm text-slate-500">No legal cases yet. Choose a type above to open one.</div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {recent.map((c) => (
                                <li key={c.id}>
                                    <Link href={`/v2/support/${c.id}`} className="flex flex-col gap-1 px-5 py-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0">
                                            <div className="text-xs font-semibold text-violet-600">
                                                {c.ticket_number}
                                                {c.section && <span className="font-normal text-slate-400"> · {titleOf[c.section]}</span>}
                                                <span className="font-normal text-slate-400"> · {c.updated}</span>
                                            </div>
                                            <div className="truncate text-sm font-semibold text-slate-900">{c.subject}</div>
                                        </div>
                                        <StatusPill status={c.status} side="owner" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
