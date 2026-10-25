import { Head, Link, router, usePage } from "@inertiajs/react";
import { CheckCircle2, Lock, Mail, Plus, RefreshCw, Trash2, UserRound } from "lucide-react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import SignatureDesigner, { defaultDesign, renderSignature } from "@/V2/Shared/Mail/SignatureDesigner";

/*
 * W95 MIXXTUNE_PANEL_MAIL
 * Super Admin > Email accounts: mailboxes made in Hostinger hPanel are added
 * here with their password (checked by logging in) and given to managers.
 */
const when = (v) => (v ? new Intl.DateTimeFormat("en-IN", { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit", hour12: true }).format(new Date(v)) : "never");
const roleName = (r) => (r === "super_admin" ? "Super Admin" : "Manager");

function People({ people, value, onChange }) {
    const toggle = (id) => onChange(value.includes(id) ? value.filter((v) => v !== id) : [...value, id]);

    return (
        <div className="max-h-48 overflow-y-auto rounded-lg border border-slate-200 p-2">
            {people.length === 0 && <p className="px-1 py-2 text-xs text-slate-500">No managers yet.</p>}
            {people.map((p) => (
                <label key={p.id} className="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-sm hover:bg-slate-50">
                    <input type="checkbox" checked={value.includes(p.id)} onChange={() => toggle(p.id)} />
                    <span className="min-w-0 truncate">
                        <b className="font-semibold text-slate-800">{p.full_name || p.name}</b> <span className="text-xs text-slate-500">{p.email} · {roleName(p.role)}</span>
                    </span>
                </label>
            ))}
        </div>
    );
}

/* W102: the signature - simple text, or a designed one pasted as HTML source code */
export const sigDoc = (html) => `<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>body{margin:8px;font-family:Arial,sans-serif;font-size:14px}img{max-width:100%}</style></head><body>${html}</body></html>`;

/* W108: the signature - simple text, or made in the Signature Designer (no code pasted any more) */
function SignatureBox({ data, set, error, hadPasted }) {
    const tab = (mode, label) => (
        <button type="button" onClick={() => set("sig_mode", mode)} className={`rounded-md px-3 py-1 text-xs font-semibold ${data.sig_mode === mode ? "bg-white text-violet-700 shadow-sm" : "text-slate-600 hover:text-slate-900"}`}>
            {label}
        </button>
    );

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-xs font-semibold text-slate-700">Signature (added under every mail)</span>
                <div className="inline-flex rounded-lg bg-slate-100 p-0.5">
                    {tab("text", "Simple text")}
                    {tab("design", "Signature designer")}
                </div>
            </div>
            {data.sig_mode === "text" ? (
                <textarea rows={3} value={data.signature} onChange={(e) => set("signature", e.target.value)} placeholder={"Regards,\nMixx Tune Entertainment"} className="w-full rounded-lg border border-slate-300 p-2.5 text-sm" />
            ) : (
                <>
                    {hadPasted && (
                        <p className="rounded-lg bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-800">
                            This mailbox still has the signature pasted earlier (from zoviz). Fill the designer below - when you Save, the new signature takes its place.
                        </p>
                    )}
                    <SignatureDesigner design={data.signature_design} onChange={(v) => set("signature_design", v)} />
                    {error && <span className="block text-xs text-red-600">{error}</span>}
                </>
            )}
        </div>
    );
}

function AccountForm({ people, account = null, onDone, preset = null }) {
    const { errors = {}, brandLogo = "" } = usePage().props;
    const [needName, alertName] = useState(false);
    const [data, setData] = useState({
        address: "",
        display_name: account?.display_name ?? "",
        password: "",
        signature: account?.signature ?? "",
        sig_mode: account?.signature_design || account?.signature_html ? "design" : "text",
        signature_design: account?.signature_design ? { ...defaultDesign(account, brandLogo), ...account.signature_design } : defaultDesign({ address: account?.address ?? "" }, brandLogo),
        status: account?.status ?? "active",
        user_ids: account?.user_ids ?? preset?.user_ids ?? [],
        is_private: account?.is_private ?? preset?.is_private ?? false,
        aliases: account?.aliases ?? [],
    });
    const [busy, setBusy] = useState(false);
    const set = (k, v) => setData((d) => ({ ...d, [k]: v }));

    const save = async () => {
        if (busy) return;
        setBusy(true);
        /* W108: the designer makes the HTML; the text one clears it */
        const design = data.sig_mode === "design";
        if (design && !String(data.signature_design.name || "").trim()) {
            setBusy(false);
            alertName(true);
            return;
        }
        const sig = design
            ? { signature: data.signature, signature_html: renderSignature(data.signature_design), signature_design: data.signature_design }
            : { signature: data.signature, signature_html: "", signature_design: "" };
        const { sig_mode, signature_design, ...rest } = data;
        const payload = account ? { display_name: data.display_name, password: data.password || null, ...sig, status: data.status, user_ids: data.user_ids, is_private: data.is_private, aliases: data.aliases.filter((a) => a.address?.trim()) } : { ...rest, ...sig, aliases: data.aliases.filter((a) => a.address?.trim()) };
        router.post(account ? `/admin/mail-accounts/${account.id}` : "/admin/mail-accounts", payload, { preserveScroll: true, onSuccess: onDone, onFinish: () => setBusy(false) });
    };

    return (
        <div className="space-y-3">
            {!account && (
                <label className="block">
                    <span className="text-xs font-semibold text-slate-700">Mailbox address *</span>
                    <input value={data.address} onChange={(e) => set("address", e.target.value)} placeholder={preset ? "yourname@mixxtune.com" : "manager1@mixxtune.com"} className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" />
                    {errors.address && <span className="mt-1 block text-xs text-red-600">{errors.address}</span>}
                </label>
            )}
            <div className="grid gap-3 sm:grid-cols-2">
                <label className="block">
                    <span className="text-xs font-semibold text-slate-700">{account ? "New password (leave empty to keep)" : "Mailbox password *"}</span>
                    <input type="password" autoComplete="new-password" value={data.password} onChange={(e) => set("password", e.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" />
                    <span className="mt-1 block text-[11px] text-slate-500">The same password as in Hostinger hPanel. Kept encrypted.</span>
                    {errors.password && <span className="mt-1 block text-xs text-red-600">{errors.password}</span>}
                </label>
                <label className="block">
                    <span className="text-xs font-semibold text-slate-700">Name shown to receivers</span>
                    <input value={data.display_name} onChange={(e) => set("display_name", e.target.value)} placeholder="Mixx Tune Support" className="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" />
                </label>
            </div>
            <div>
                <span className="text-xs font-semibold text-slate-700">Who can use this mailbox</span>
                <p className="mb-1 text-[11px] text-slate-500">Super Admins can open every shared mailbox (not the private ones).</p>
                <People people={people} value={data.user_ids} onChange={(v) => set("user_ids", v)} />
                {errors.user_ids && <span className="mt-1 block text-xs text-red-600">{errors.user_ids}</span>}
            </div>
            {/* W101: other addresses of this mailbox (aliases made in Hostinger) - pick one as "From" when writing */}
            <div>
                <span className="text-xs font-semibold text-slate-700">Aliases (send as)</span>
                <p className="mb-1 text-[11px] text-slate-500">Hostinger hPanel → Emails → Aliases. Mail to an alias already arrives in this mailbox; list it here to also send from it.</p>
                <div className="space-y-1.5">
                    {data.aliases.map((al, i) => (
                        <div key={i} className="flex flex-wrap gap-1.5">
                            <input
                                value={al.address}
                                onChange={(e) => set("aliases", data.aliases.map((x, j) => (j === i ? { ...x, address: e.target.value } : x)))}
                                placeholder="support@mixxtune.com"
                                aria-label="Alias address"
                                className="h-9 min-w-0 flex-[2_1_200px] rounded-lg border border-slate-300 px-3 text-sm"
                            />
                            <input
                                value={al.name ?? ""}
                                onChange={(e) => set("aliases", data.aliases.map((x, j) => (j === i ? { ...x, name: e.target.value } : x)))}
                                placeholder="Name shown (optional)"
                                aria-label="Alias name"
                                className="h-9 min-w-0 flex-[1_1_160px] rounded-lg border border-slate-300 px-3 text-sm"
                            />
                            <button type="button" onClick={() => set("aliases", data.aliases.filter((_, j) => j !== i))} aria-label="Remove alias" className="h-9 rounded-lg border border-slate-300 px-3 text-sm text-slate-600 hover:bg-slate-50">
                                ×
                            </button>
                        </div>
                    ))}
                    {data.aliases.length < 20 && (
                        <button type="button" onClick={() => set("aliases", [...data.aliases, { address: "", name: "" }])} className="text-xs font-semibold text-violet-700">
                            + Add alias
                        </button>
                    )}
                    {Object.entries(errors).filter(([k]) => k.startsWith("aliases")).slice(0, 1).map(([k, v]) => (
                        <span key={k} className="block text-xs text-red-600">{v}</span>
                    ))}
                </div>
            </div>
            {/* W100: private = only the people ticked can open it, other Super Admins cannot */}
            <label className="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <input type="checkbox" className="mt-0.5" checked={data.is_private} onChange={(e) => set("is_private", e.target.checked)} />
                <span>
                    <b className="font-semibold text-slate-800">Private mailbox</b>
                    <span className="block text-xs text-slate-500">Only the people ticked above can open it. Other Super Admins cannot see it. Use this for your own mail.</span>
                </span>
            </label>
            <SignatureBox data={data} set={set} error={(needName && data.sig_mode === "design" && !String(data.signature_design.name || "").trim() ? "Write the full name in the designer (1. Details)." : null) || errors.signature_html || errors.signature_design} hadPasted={Boolean(account?.signature_html && !account?.signature_design)} />
            {account && (
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.status === "disabled"} onChange={(e) => set("status", e.target.checked ? "disabled" : "active")} /> Switch off (not read, not shown)
                </label>
            )}
            <div className="flex justify-end gap-2">
                <button type="button" onClick={onDone} disabled={busy} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                    Cancel
                </button>
                <button type="button" onClick={save} disabled={busy || (!account && (!data.address || !data.password))} className="rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">
                    {busy ? "Checking login..." : account ? "Save" : "Check login and add"}
                </button>
            </div>
        </div>
    );
}

export default function MailAccounts({ role = "super_admin", accounts = [], people = [], me = null }) {
    const { flash = {}, errors = {} } = usePage().props;
    const [adding, setAdding] = useState(false);
    const [preset, setPreset] = useState(null);
    const [editing, setEditing] = useState(null);
    const [busy, setBusy] = useState(null);
    const nameOf = (id) => {
        const p = people.find((x) => x.id === id);
        return p ? p.full_name || p.name || p.email : `#${id}`;
    };

    const test = (a) => {
        setBusy(a.id);
        router.post(`/admin/mail-accounts/${a.id}/test`, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    };
    const remove = (a) => {
        const typed = window.prompt(`Remove ${a.address} from the panel?\nThe mailbox and its mail stay at Hostinger.\n\nType the address to confirm:`);
        if (typed === null) return;
        router.post(`/admin/mail-accounts/${a.id}/delete`, { confirm: typed.trim().toLowerCase() }, { preserveScroll: true });
    };

    return (
        <PanelLayout role={role} title="Email accounts" subtitle="Domain mailboxes used inside the panel">
            <Head title="Email accounts" />

            {flash.success && (
                <div className="mb-3 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 size={16} /> {flash.success}
                </div>
            )}
            {(errors.mail || errors.confirm) && <div className="mb-3 rounded-xl bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700">{errors.mail || errors.confirm}</div>}

            <section className="rounded-2xl border border-violet-100 bg-violet-50/50 p-4 text-sm text-slate-700">
                <b className="text-slate-900">How to add a mailbox</b>
                <ol className="mt-1 list-decimal space-y-0.5 pl-5 text-xs">
                    <li>Hostinger hPanel → Emails → mixxtune.com → Create mailbox (address + password).</li>
                    <li>Here: Add mailbox → same address and password → choose who can use it.</li>
                    <li>They find it under "Mail" in their panel. New mail comes in every 2 minutes.</li>
                </ol>
            </section>

            <div className="mt-4 flex items-center justify-between">
                <h2 className="text-[15px] font-bold text-slate-900">Mailboxes ({accounts.length})</h2>
                {!adding && (
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                setPreset(me ? { user_ids: [me], is_private: true } : null);
                                setAdding(true);
                            }}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-violet-300 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"
                        >
                            <UserRound size={16} /> Add my own mailbox
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setPreset(null);
                                setAdding(true);
                            }}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"
                        >
                            <Plus size={16} /> Add mailbox
                        </button>
                    </div>
                )}
            </div>

            {adding && (
                <section className="mt-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h3 className="mb-3 text-sm font-bold text-slate-900">{preset ? "Add my own mailbox" : "Add mailbox"}</h3>
                    {preset && <p className="mb-3 text-xs text-slate-500">Make it in Hostinger hPanel first (for example yourname@mixxtune.com). You are ticked and it is private - only you open it, and it shows first under Mail.</p>}
                    <AccountForm key={preset ? "mine" : "team"} people={people} preset={preset} onDone={() => setAdding(false)} />
                </section>
            )}

            <div className="mt-3 space-y-3">
                {accounts.length === 0 && !adding && <p className="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-slate-500">No mailbox added yet.</p>}
                {accounts.map((a) => (
                    <section key={a.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Mail size={16} className="text-violet-600" />
                                    <b className="break-all text-sm text-slate-900">{a.address}</b>
                                    {a.display_name && <span className="text-xs text-slate-500">"{a.display_name}"</span>}
                                    {a.is_private && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold uppercase text-violet-800">
                                            <Lock size={10} /> private
                                        </span>
                                    )}
                                    {me && a.user_ids.includes(me) && <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-800">mine</span>}
                                    {a.status === "disabled" && <span className="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-600">off</span>}
                                </div>
                                <div className="mt-1 text-xs text-slate-500">
                                    {a.inbox} in inbox · {a.unread} unread · read {when(a.last_synced_at)}
                                    {a.sent_folder ? ` · sent folder "${a.sent_folder}"` : ""}
                                    {a.aliases?.length ? ` · aliases: ${a.aliases.map((x) => x.address).join(", ")}` : ""}
                                </div>
                                {a.last_error && <div className="mt-1 break-words text-xs font-semibold text-red-600">Problem: {a.last_error}</div>}
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {a.user_ids.length === 0 ? (
                                        <span className="text-xs text-slate-400">Only Super Admins</span>
                                    ) : (
                                        a.user_ids.map((id) => (
                                            <span key={id} className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                                {nameOf(id)}
                                            </span>
                                        ))
                                    )}
                                </div>
                            </div>
                            {a.can_manage === false ? (
                                <p className="max-w-xs text-xs text-slate-500">Private mailbox - only its own people can open or change it.</p>
                            ) : (
                            <div className="flex flex-wrap gap-2">
                                <Link href={`/mail?account=${a.id}`} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Open
                                </Link>
                                <button type="button" onClick={() => test(a)} disabled={busy === a.id} className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                                    <RefreshCw size={13} className={busy === a.id ? "animate-spin" : ""} /> Test & read now
                                </button>
                                <button type="button" onClick={() => setEditing(editing === a.id ? null : a.id)} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    {editing === a.id ? "Close" : "Edit"}
                                </button>
                                <button type="button" onClick={() => remove(a)} className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                    <Trash2 size={13} /> Remove
                                </button>
                            </div>
                            )}
                        </div>
                        {editing === a.id && (
                            <div className="mt-4 border-t border-slate-100 pt-4">
                                <AccountForm people={people} account={a} onDone={() => setEditing(null)} />
                            </div>
                        )}
                    </section>
                ))}
            </div>
        </PanelLayout>
    );
}
