import { Head, Link, router, usePage } from "@inertiajs/react";
import { ArrowLeft, Bold, CornerUpLeft, CornerUpRight, Inbox, Italic, List, Mail, Paperclip, PenSquare, RefreshCw, ReplyAll, Search, Send, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/*
 * W95 MIXXTUNE_PANEL_MAIL
 * Inbox / Sent of the domain mailboxes given to this person (Super Admin: all).
 * New mail is read every 2 minutes; "Refresh" reads it now.
 */
const when = (v, long = false) => {
    if (!v) return "";
    const d = new Date(v);
    const today = new Date();
    if (!long && d.toDateString() === today.toDateString()) return new Intl.DateTimeFormat("en-IN", { hour: "2-digit", minute: "2-digit", hour12: true }).format(d);
    return new Intl.DateTimeFormat("en-IN", long ? { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hour12: true } : { day: "2-digit", month: "short" }).format(d);
};
const size = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

function Compose({ account, draft, onClose }) {
    const errors = usePage().props.errors ?? {};
    const [to, setTo] = useState(draft.to ?? "");
    const [cc, setCc] = useState(draft.cc ?? "");
    const [showCc, setShowCc] = useState(Boolean(draft.cc));
    /* W101: Bcc, and the From address (the mailbox or one of its aliases) */
    const [bcc, setBcc] = useState("");
    const [showBcc, setShowBcc] = useState(false);
    const fromList = account.from?.length ? account.from : [{ address: account.address, name: account.display_name || "" }];
    const [from, setFrom] = useState(draft.from && fromList.some((f) => f.address === draft.from) ? draft.from : fromList[0].address);
    const [subject, setSubject] = useState(draft.subject ?? "");
    const [body, setBody] = useState(draft.body ?? "");
    const [files, setFiles] = useState([]);
    const [kept, setKept] = useState(draft.carried ?? []);
    const [busy, setBusy] = useState(false);
    const box = useRef(null);
    const total = files.reduce((s, f) => s + f.size, 0) + kept.reduce((s, a) => s + (a.size ?? 0), 0);

    /* W96: simple marks, turned into bold / italic / list in the mail that is sent */
    const mark = (kind) => {
        const el = box.current;
        if (!el) return;
        const a = el.selectionStart ?? body.length;
        const b = el.selectionEnd ?? body.length;
        const sel = body.slice(a, b);
        let next;
        let caret;
        if (kind === "list") {
            const start = body.lastIndexOf("\n", a - 1) + 1;
            const block = body.slice(start, b) || "";
            const lines = (block || " ").split("\n").map((l) => (/^\s*[-•]\s/.test(l) ? l : `- ${l}`));
            next = body.slice(0, start) + lines.join("\n") + body.slice(b);
            caret = start + lines.join("\n").length;
        } else {
            const m = kind === "bold" ? "**" : "_";
            const inner = sel || (kind === "bold" ? "bold text" : "italic text");
            next = body.slice(0, a) + m + inner + m + body.slice(b);
            caret = a + m.length + inner.length + m.length;
        }
        setBody(next);
        requestAnimationFrame(() => {
            el.focus();
            el.setSelectionRange(caret, caret);
        });
    };

    const send = () => {
        if (busy) return;
        setBusy(true);
        router.post(
            `/mail/accounts/${account.id}/send`,
            { from, to, cc, bcc, subject, body, files, reply_to_id: draft.reply_to_id ?? "", forward_of: draft.forward_of ?? "", keep_attachments: kept.map((a) => a.id) },
            { forceFormData: true, preserveScroll: true, onSuccess: onClose, onFinish: () => setBusy(false) },
        );
    };

    const err = errors.to || errors.to_list || errors["to_list.0"] || errors.cc_list || errors["cc_list.0"] || errors.bcc_list || errors["bcc_list.0"] || errors.from || errors.subject || errors.body || errors.files || errors["files.0"] || errors.send;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 sm:items-center sm:p-4">
            <div className="flex max-h-[94vh] w-full max-w-2xl flex-col rounded-t-3xl bg-white shadow-2xl sm:rounded-2xl">
                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <h3 className="text-sm font-bold text-slate-900">{draft.title ?? "New mail"} · from {from}</h3>
                    <button type="button" onClick={onClose} disabled={busy} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" aria-label="Close">
                        <X size={18} />
                    </button>
                </div>
                <div className="space-y-2 overflow-y-auto px-5 py-3">
                    {fromList.length > 1 && (
                        <div className="flex items-center gap-2">
                            <span className="w-14 text-xs font-semibold text-slate-500">From</span>
                            <select value={from} onChange={(e) => setFrom(e.target.value)} aria-label="From" className="h-9 min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-2 text-sm">
                                {fromList.map((f) => (
                                    <option key={f.address} value={f.address}>
                                        {f.name ? `${f.name} <${f.address}>` : f.address}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                    <div className="flex items-center gap-2">
                        <span className="w-14 text-xs font-semibold text-slate-500">To</span>
                        <input value={to} onChange={(e) => setTo(e.target.value)} placeholder="name@example.com, other@example.com" className="h-9 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 text-sm" />
                        {!showCc && (
                            <button type="button" onClick={() => setShowCc(true)} className="text-xs font-semibold text-violet-700">
                                Cc
                            </button>
                        )}
                        {!showBcc && (
                            <button type="button" onClick={() => setShowBcc(true)} className="text-xs font-semibold text-violet-700">
                                Bcc
                            </button>
                        )}
                    </div>
                    {showCc && (
                        <div className="flex items-center gap-2">
                            <span className="w-14 text-xs font-semibold text-slate-500">Cc</span>
                            <input value={cc} onChange={(e) => setCc(e.target.value)} className="h-9 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 text-sm" />
                        </div>
                    )}
                    {showBcc && (
                        <div className="flex items-center gap-2">
                            <span className="w-14 text-xs font-semibold text-slate-500">Bcc</span>
                            <input value={bcc} onChange={(e) => setBcc(e.target.value)} placeholder="hidden copy - others do not see these addresses" className="h-9 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 text-sm" />
                        </div>
                    )}
                    <div className="flex items-center gap-2">
                        <span className="w-14 text-xs font-semibold text-slate-500">Subject</span>
                        <input value={subject} onChange={(e) => setSubject(e.target.value)} className="h-9 min-w-0 flex-1 rounded-lg border border-slate-300 px-3 text-sm" />
                    </div>
                    <div className="flex items-center gap-1">
                        <button type="button" onClick={() => mark("bold")} title="Bold" className="rounded-md border border-slate-300 p-1.5 text-slate-700 hover:bg-slate-50">
                            <Bold size={14} />
                        </button>
                        <button type="button" onClick={() => mark("italic")} title="Italic" className="rounded-md border border-slate-300 p-1.5 text-slate-700 hover:bg-slate-50">
                            <Italic size={14} />
                        </button>
                        <button type="button" onClick={() => mark("list")} title="List" className="rounded-md border border-slate-300 p-1.5 text-slate-700 hover:bg-slate-50">
                            <List size={14} />
                        </button>
                        <span className="ml-1 text-[11px] text-slate-500">**bold**, _italic_, "- " list - shown formatted to the receiver; links become clickable</span>
                    </div>
                    <textarea ref={box} value={body} onChange={(e) => setBody(e.target.value)} rows={12} autoFocus={!draft.body} className="w-full rounded-lg border border-slate-300 p-3 text-sm leading-relaxed" placeholder="Write your mail..." />
                    <div className="flex flex-wrap items-center gap-2">
                        <label className="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Paperclip size={14} /> Attach files
                            <input type="file" multiple className="hidden" onChange={(e) => setFiles([...files, ...Array.from(e.target.files ?? [])].slice(0, 10))} />
                        </label>
                        {kept.map((a) => (
                            <span key={`k${a.id}`} className="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-1 text-[11px] text-violet-800">
                                <Paperclip size={11} /> {a.filename} · {size(a.size ?? 0)}
                                <button type="button" onClick={() => setKept(kept.filter((k) => k.id !== a.id))} aria-label="Remove">
                                    <X size={12} />
                                </button>
                            </span>
                        ))}
                        {files.map((f, i) => (
                            <span key={`${f.name}-${i}`} className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-[11px] text-slate-700">
                                {f.name} · {size(f.size)}
                                <button type="button" onClick={() => setFiles(files.filter((_, j) => j !== i))} aria-label="Remove">
                                    <X size={12} />
                                </button>
                            </span>
                        ))}
                        {total > 24 * 1048576 && <span className="text-xs font-semibold text-red-600">Too big: at most 24 MB together</span>}
                    </div>
                    {draft.note && <p className="text-[11px] text-slate-500">{draft.note}</p>}
                    {err && <p className="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{err}</p>}
                </div>
                <div className="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
                    <button type="button" onClick={onClose} disabled={busy} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                        Cancel
                    </button>
                    <button type="button" onClick={send} disabled={busy || !to.trim() || !subject.trim() || !body.trim() || total > 24 * 1048576} className="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">
                        <Send size={15} /> {busy ? "Sending..." : "Send"}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function MailIndex({ role = "admin", accounts = [], current = null, folder = "inbox", search = "", messages = null, open = null, isSuperAdmin = false }) {
    const { flash = {}, errors = {} } = usePage().props;
    const account = accounts.find((a) => a.id === current) ?? null;
    const [q, setQ] = useState(search);
    const [draft, setDraft] = useState(null);
    const [busy, setBusy] = useState(false);
    const [plain, setPlain] = useState(false);
    const rows = messages?.data ?? [];

    useEffect(() => setPlain(false), [open?.id]);

    const go = (params) => router.get("/mail", { account: current, folder, q: q || undefined, ...params }, { preserveScroll: true, preserveState: true });
    const refresh = () => {
        if (!account || busy) return;
        setBusy(true);
        router.post(`/mail/accounts/${account.id}/refresh`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    const quote = (m) => `\n\n\nOn ${when(m.at, true)}, ${m.from_name ? `${m.from_name} <${m.from_email}>` : m.from_email} wrote:\n${String(m.text ?? "").split("\n").map((l) => `> ${l}`).join("\n")}`;
    const re = (s) => (/^re:/i.test(s) ? s : `Re: ${s}`);
    const reply = (all) => {
        const own = account.address.toLowerCase();
        const back = open.folder === "sent" ? open.to_list.join(", ") : open.reply_to || open.from_email;
        const others = all ? [...open.to_list, ...open.cc_list].filter((e) => e && e.toLowerCase() !== own && e.toLowerCase() !== String(back).toLowerCase()) : [];
        const mineAddr = (account.from ?? []).map((f) => f.address);
        const hit = [...(open.to_list ?? []), ...(open.cc_list ?? [])].map((e) => String(e).toLowerCase()).find((e) => mineAddr.includes(e));
        setDraft({ title: all ? "Reply all" : "Reply", to: back, cc: others.filter((e) => !mineAddr.includes(String(e).toLowerCase())).join(", "), subject: re(open.subject), body: quote(open), reply_to_id: open.id, from: open.folder === "sent" ? open.from_email : hit });
    };
    const forward = () =>
        setDraft({
            title: "Forward",
            to: "",
            subject: /^fwd?:/i.test(open.subject) ? open.subject : `Fwd: ${open.subject}`,
            body: `\n\n\n---------- Forwarded message ----------\nFrom: ${open.from_name ? `${open.from_name} <${open.from_email}>` : open.from_email}\nDate: ${when(open.at, true)}\nSubject: ${open.subject}\nTo: ${open.to}\n\n${open.text ?? ""}`,
            forward_of: open.id,
            carried: open.attachments ?? [],
            note: open.attachments?.length ? "The attachments of the original mail go along - remove any you do not want to send." : null,
        });

    const listTitle = useMemo(() => (folder === "sent" ? "Sent" : "Inbox"), [folder]);

    if (accounts.length === 0) {
        return (
            <PanelLayout role={role} title="Mail">
                <Head title="Mail" />
                <div className="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <Mail className="mx-auto text-slate-400" size={36} />
                    <h2 className="mt-3 text-base font-bold text-slate-900">No mailbox yet</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        {isSuperAdmin ? "Create the mailbox in Hostinger hPanel → Emails, then add it under Email accounts." : "Ask the Super Admin to give you a mailbox."}
                    </p>
                    {isSuperAdmin && (
                        <Link href="/admin/mail-accounts" className="mt-4 inline-flex rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white">
                            Email accounts
                        </Link>
                    )}
                </div>
            </PanelLayout>
        );
    }

    return (
        <PanelLayout role={role} title="Mail" subtitle={account ? `${account.display_name ? `${account.display_name} · ` : ""}${account.address}` : ""}>
            <Head title={`${listTitle} - Mail`} />

            {(flash.success || errors.mail || errors.send) && (
                <div className={`mb-3 rounded-xl px-4 py-2.5 text-sm font-semibold ${flash.success && !errors.mail && !errors.send ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"}`}>{errors.mail || errors.send || flash.success}</div>
            )}

            <div className="flex flex-wrap items-center gap-2">
                {/* W100: own mailbox first; a Super Admin also sees the team mailboxes */}
                {accounts.length > 1 && (
                    <select value={current ?? ""} onChange={(e) => router.get("/mail", { account: e.target.value, folder })} aria-label="Mailbox" className="h-10 max-w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold">
                        {[
                            ["My mailbox", accounts.filter((a) => a.mine)],
                            [isSuperAdmin ? "Team mailboxes" : "Shared with me", accounts.filter((a) => !a.mine)],
                        ]
                            .filter(([, list]) => list.length > 0)
                            .map(([label, list]) => (
                                <optgroup key={label} label={label}>
                                    {list.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.address}
                                            {a.is_private ? " (private)" : ""}
                                            {a.unread ? ` · ${a.unread} new` : ""}
                                        </option>
                                    ))}
                                </optgroup>
                            ))}
                    </select>
                )}
                {account?.mine && isSuperAdmin && (
                    <span className="rounded-full bg-violet-100 px-2.5 py-1 text-[11px] font-bold text-violet-800">My mailbox{account.is_private ? " · private" : ""}</span>
                )}
                <div className="inline-flex rounded-xl border border-slate-200 bg-white p-1">
                    {[
                        ["inbox", "Inbox", Inbox],
                        ["sent", "Sent", Send],
                    ].map(([key, label, Icon]) => (
                        <button key={key} type="button" onClick={() => router.get("/mail", { account: current, folder: key })} className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold ${folder === key ? "bg-violet-600 text-white" : "text-slate-600 hover:bg-slate-50"}`}>
                            <Icon size={15} /> {label}
                            {key === "inbox" && account?.unread > 0 && <span className={`rounded-full px-1.5 text-[11px] font-black ${folder === key ? "bg-white text-violet-700" : "bg-violet-600 text-white"}`}>{account.unread}</span>}
                        </button>
                    ))}
                </div>
                <form onSubmit={(e) => { e.preventDefault(); go({ q: q || undefined, m: undefined, page: undefined }); }} className="relative min-w-[180px] flex-1">
                    <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search mail" className="h-10 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-sm" />
                </form>
                <button type="button" onClick={refresh} disabled={busy} title="Read new mail now" className="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 disabled:opacity-50">
                    <RefreshCw size={15} className={busy ? "animate-spin" : ""} /> Refresh
                </button>
                <button type="button" onClick={() => setDraft({})} className="inline-flex h-10 items-center gap-1.5 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700">
                    <PenSquare size={15} /> Write
                </button>
            </div>
            {account && (
                <p className={`mt-1.5 text-[11px] ${account.last_error ? "text-red-600" : "text-slate-400"}`}>
                    {account.last_error ? `Mailbox could not be read: ${account.last_error}` : `Updated ${account.last_synced_at ? when(account.last_synced_at, true) : "never"} · new mail comes in every 2 minutes`}
                </p>
            )}

            <div className="mt-3 grid min-h-[60vh] gap-3 lg:grid-cols-[380px_minmax(0,1fr)]">
                <section className={`overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm ${open ? "hidden lg:block" : ""}`}>
                    {rows.length === 0 ? (
                        <p className="px-5 py-16 text-center text-sm text-slate-500">{search ? "Nothing found." : folder === "sent" ? "No sent mail yet." : "No mail yet."}</p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {rows.map((m) => (
                                <li key={m.id}>
                                    <button type="button" onClick={() => go({ m: m.id })} className={`block w-full px-4 py-3 text-left hover:bg-slate-50 ${open?.id === m.id ? "bg-violet-50" : ""}`}>
                                        <div className="flex items-center justify-between gap-2">
                                            <span className={`truncate text-sm ${m.seen ? "text-slate-700" : "font-black text-slate-950"}`}>{m.who || "—"}</span>
                                            <span className="shrink-0 text-[11px] text-slate-400">{when(m.at)}</span>
                                        </div>
                                        <div className={`flex items-center gap-1 truncate text-sm ${m.seen ? "text-slate-600" : "font-bold text-slate-900"}`}>
                                            {m.has_attachments && <Paperclip size={12} className="shrink-0 text-slate-400" />}
                                            <span className="truncate">{m.subject}</span>
                                        </div>
                                        <div className="truncate text-xs text-slate-400">{m.snippet}</div>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    {messages?.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-2 text-xs">
                            {messages.prev_page_url ? <Link href={messages.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Newer</Link> : <span />}
                            <span className="text-slate-400">
                                Page {messages.current_page} of {messages.last_page}
                            </span>
                            {messages.next_page_url ? <Link href={messages.next_page_url} preserveScroll className="font-semibold text-violet-700">Older →</Link> : <span />}
                        </div>
                    )}
                </section>

                <section className={`min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm ${open ? "" : "hidden lg:block"}`}>
                    {!open ? (
                        <p className="px-5 py-24 text-center text-sm text-slate-400">Choose a mail to read it.</p>
                    ) : (
                        <article className="flex h-full flex-col">
                            <div className="border-b border-slate-100 px-5 py-4">
                                <button type="button" onClick={() => go({ m: undefined })} className="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-slate-500 lg:hidden">
                                    <ArrowLeft size={14} /> Back
                                </button>
                                <h2 className="break-words text-lg font-bold text-slate-950">{open.subject}</h2>
                                <div className="mt-2 flex flex-wrap items-start justify-between gap-2 text-sm">
                                    <div className="min-w-0">
                                        <div className="font-semibold text-slate-900">
                                            {open.from_name || open.from_email} {open.from_name && <span className="font-normal text-slate-500">&lt;{open.from_email}&gt;</span>}
                                        </div>
                                        <div className="break-words text-xs text-slate-500">To: {open.to || "—"}</div>
                                        {open.cc && <div className="break-words text-xs text-slate-500">Cc: {open.cc}</div>}
                                        {open.bcc && <div className="break-words text-xs text-slate-500">Bcc: {open.bcc}</div>}
                                    </div>
                                    <span className="text-xs text-slate-400">{when(open.at, true)}</span>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <button type="button" onClick={() => reply(false)} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <CornerUpLeft size={14} /> Reply
                                    </button>
                                    <button type="button" onClick={() => reply(true)} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <ReplyAll size={14} /> Reply all
                                    </button>
                                    <button type="button" onClick={forward} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <CornerUpRight size={14} /> Forward
                                    </button>
                                    {open.has_html && open.text && (
                                        <button type="button" onClick={() => setPlain(!plain)} className="ml-auto text-xs font-semibold text-violet-700">
                                            {plain ? "Show formatted" : "Show plain text"}
                                        </button>
                                    )}
                                </div>
                            </div>
                            {open.attachments?.length > 0 && (
                                <div className="flex flex-wrap gap-2 border-b border-slate-100 px-5 py-3">
                                    {open.attachments.map((a) => (
                                        <a key={a.id} href={`/mail/attachments/${a.id}`} className="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                            <Paperclip size={13} /> <span className="truncate">{a.filename}</span> <span className="font-normal text-slate-400">{size(a.size)}</span>
                                        </a>
                                    ))}
                                </div>
                            )}
                            <div className="min-h-[50vh] flex-1">
                                {open.has_html && !plain ? (
                                    <iframe key={open.id} title="Mail" src={`/mail/messages/${open.id}/html`} sandbox="allow-popups allow-popups-to-escape-sandbox" className="h-[70vh] w-full rounded-b-2xl bg-white" />
                                ) : (
                                    <pre className="whitespace-pre-wrap break-words px-5 py-4 font-sans text-sm leading-relaxed text-slate-800">{open.text || "(empty)"}</pre>
                                )}
                            </div>
                        </article>
                    )}
                </section>
            </div>

            {draft && account && <Compose account={account} draft={draft} onClose={() => setDraft(null)} />}
        </PanelLayout>
    );
}
