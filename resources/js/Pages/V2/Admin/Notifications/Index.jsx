import { Head, useForm, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    Bell,
    Building2,
    CheckCircle2,
    Globe2,
    Loader2,
    Megaphone,
    Search,
    Send,
    Sparkles,
    Star,
    Tag,
    Users,
    X,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* MIXXTUNE_NOTIFY_TARGETED_V1 */

const CATEGORIES = [
    { value: "primary", label: "Primary", icon: Bell, hint: "Account work: KYC, bank, payouts, releases" },
    { value: "announcement", label: "Announcement", icon: Megaphone, hint: "News, new features, holidays, rules" },
    { value: "promotion", label: "Promotion", icon: Tag, hint: "Offers, campaigns, growth services" },
];

const SEVERITY = [
    { value: "info", label: "Info", cls: "bg-sky-50 text-sky-700 border-sky-200" },
    { value: "success", label: "Good news", cls: "bg-emerald-50 text-emerald-700 border-emerald-200" },
    { value: "warning", label: "Action needed", cls: "bg-amber-50 text-amber-800 border-amber-200" },
    { value: "danger", label: "Urgent", cls: "bg-red-50 text-red-700 border-red-200" },
];

/* Ready-made notices: press one, it fills the form; check the audience and send. */
const TEMPLATES = [
    {
        category: "primary", severity: "warning", highlight: true, target: { type: "role", value: "label" },
        title: "Complete your KYC to receive payouts",
        message: "Your payout KYC is not complete yet. Please add your PAN and bank details so we can send your earnings without delay.",
        action_url: "/v2/kyc-profile",
    },
    {
        category: "primary", severity: "success", highlight: true, target: { type: "all" },
        title: "Your royalty statement is ready",
        message: "The latest royalty statement is now available. Open Statements to view and download your PDF.",
        action_url: "/v2/statements",
    },
    {
        category: "primary", severity: "info", highlight: false, target: { type: "all" },
        title: "Payout window is open",
        message: "You can now request a withdrawal from your wallet. Payouts are sent to your verified bank account.",
        action_url: "/v2/withdrawals",
    },
    {
        category: "primary", severity: "warning", highlight: true, target: { type: "role", value: "label" },
        title: "Please check your bank details",
        message: "Make sure your bank account number, IFSC and account holder name are correct. Wrong details can delay your payout.",
        action_url: "/v2/kyc-profile",
    },
    {
        category: "primary", severity: "danger", highlight: true, target: { type: "all" },
        title: "Scheduled maintenance",
        message: "The panel will be under maintenance for a short time. Please save your work before the window starts.",
        action_url: "",
    },
    {
        category: "announcement", severity: "info", highlight: false, target: { type: "all" },
        title: "New feature in your panel",
        message: "We have added a new feature to make your work easier. Open the panel to try it and tell us what you think.",
        action_url: "",
    },
    {
        category: "announcement", severity: "warning", highlight: true, target: { type: "all" },
        title: "Festival release deadline",
        message: "Planning a festival release? Submit it at least 10 days before the release date so it goes live on time in every store.",
        action_url: "/v2/releases/create",
    },
    {
        category: "announcement", severity: "info", highlight: false, target: { type: "all" },
        title: "Office holiday notice",
        message: "Our office will be closed for the holiday. Support replies and payouts may take one extra working day.",
        action_url: "/v2/support",
    },
    {
        category: "announcement", severity: "info", highlight: false, target: { type: "all" },
        title: "Updated terms and policies",
        message: "We have updated our distribution terms. Please read the new terms; continuing to use the panel means you accept them.",
        action_url: "",
    },
    {
        category: "announcement", severity: "success", highlight: false, target: { type: "all" },
        title: "New stores added",
        message: "Your music can now reach more listeners: new stores have been added to our distribution network.",
        action_url: "",
    },
    {
        category: "promotion", severity: "success", highlight: true, target: { type: "all" },
        title: "Playlist pitching is open",
        message: "Have a new song coming? Send it for editorial playlist pitching at least 7 days before release to get a better chance.",
        action_url: "/v2/support",
    },
    {
        category: "promotion", severity: "info", highlight: false, target: { type: "role", value: "artist" },
        title: "Get your verified artist profile",
        message: "Ask us to set up verified artist profiles on Spotify, YouTube Music and Instagram so fans can find you easily.",
        action_url: "/v2/support",
    },
    {
        category: "promotion", severity: "info", highlight: false, target: { type: "role", value: "label" },
        title: "Earn more with YouTube Content ID",
        message: "Turn on Content ID for your catalogue and earn from every video that uses your songs.",
        action_url: "/v2/support",
    },
    {
        category: "promotion", severity: "success", highlight: true, target: { type: "all" },
        title: "Festive release offer",
        message: "Release this festive season with Mixx Tune and get free promotion support for your new songs. Limited time.",
        action_url: "/v2/releases/create",
    },
    {
        category: "promotion", severity: "info", highlight: false, target: { type: "all" },
        title: "Pre-save campaign for your next song",
        message: "Build hype before release day: we can make a pre-save link for your next song. Contact support to start.",
        action_url: "/v2/support",
    },
];

const TARGETS = [
    { value: "all", label: "Everyone", icon: Globe2 },
    { value: "role", label: "A group", icon: Users },
    { value: "users", label: "Chosen people", icon: Search },
    { value: "label", label: "One label", icon: Building2 },
    { value: "more", label: "More…", icon: Sparkles },
];

const ROLES = [
    { value: "label", label: "All labels" },
    { value: "artist", label: "All artists" },
    { value: "admin", label: "All managers" },
    { value: "super_admin", label: "All super admins" },
];

const useDebounced = (value, ms = 300) => {
    const [v, setV] = useState(value);
    useEffect(() => {
        const t = setTimeout(() => setV(value), ms);
        return () => clearTimeout(t);
    }, [value, ms]);
    return v;
};

export default function Index({ countries = [], states = [], roleCounts = {}, recentBroadcasts = [] }) {
    const flash = usePage().props?.flash ?? {};

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        title: "",
        message: "",
        severity: "info",
        category: "primary",
        highlight: false,
        action_url: "",
        target_type: "all",
        target_value: "",
        target_users: [],
        include_label_artists: false,
    });

    const [targetTab, setTargetTab] = useState("all");
    const [templateTab, setTemplateTab] = useState("primary");
    const [chosen, setChosen] = useState([]); // [{id,name,email,role}]
    const [chosenLabel, setChosenLabel] = useState(null);
    const [preview, setPreview] = useState(null);
    const [previewBusy, setPreviewBusy] = useState(false);
    const formTop = useRef(null);

    const set = (patch) => setData((c) => ({ ...c, ...patch }));

    const pickTarget = (tab) => {
        setTargetTab(tab);
        clearErrors("target_value");
        if (tab === "more") {
            set({ target_type: "country", target_value: "" });
        } else {
            set({ target_type: tab, target_value: tab === "role" ? "label" : "" });
        }
        setChosenLabel(null);
    };

    const applyTemplate = (t) => {
        set({
            title: t.title,
            message: t.message,
            category: t.category,
            severity: t.severity,
            highlight: !!t.highlight,
            action_url: t.action_url || "",
        });
        if (t.target) {
            setTargetTab(t.target.type);
            set({ target_type: t.target.type, target_value: t.target.value || "" });
        }
        formTop.current?.scrollIntoView({ behavior: "smooth", block: "start" });
    };

    useEffect(() => {
        set({ target_users: chosen.map((u) => u.id) });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [chosen]);

    /* live "who will get it" count */
    const audienceKey = JSON.stringify([data.target_type, data.target_value, data.target_users, data.include_label_artists]);
    const debouncedAudience = useDebounced(audienceKey, 350);

    useEffect(() => {
        let cancel = false;
        setPreviewBusy(true);
        axios
            .post("/v2/admin/notification-management/preview", {
                target_type: data.target_type,
                target_value: data.target_value,
                target_users: data.target_users,
                include_label_artists: data.include_label_artists,
            })
            .then(({ data: r }) => !cancel && setPreview(r))
            .catch(() => !cancel && setPreview(null))
            .finally(() => !cancel && setPreviewBusy(false));
        return () => {
            cancel = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedAudience]);

    const submit = (e) => {
        e.preventDefault();
        const count = preview?.count ?? 0;
        if (!window.confirm(`Send "${data.title || "this notification"}" to ${count} ${count === 1 ? "person" : "people"} (${preview?.label || data.target_type})?`)) {
            return;
        }
        post("/v2/admin/notification-management", {
            preserveScroll: true,
            onSuccess: () => {
                reset("title", "message", "action_url");
                set({ highlight: false });
            },
        });
    };

    const sev = SEVERITY.find((s) => s.value === data.severity) ?? SEVERITY[0];

    return (
        <PanelLayout role="super_admin" title="Send Notification" subtitle="Send a notice to everyone, a group, one label or chosen people">
            <Head title="Send Notification" />

            {flash.success && (
                <div className="mb-5 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4" /> {flash.success}
                </div>
            )}

            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <form ref={formTop} onSubmit={submit} className="space-y-5 scroll-mt-24">
                    {/* 1. Audience */}
                    <Card step="1" title="Who should get it?">
                        <div className="flex flex-wrap gap-2">
                            {TARGETS.map((t) => {
                                const Icon = t.icon;
                                const active = targetTab === t.value;
                                return (
                                    <button
                                        type="button"
                                        key={t.value}
                                        onClick={() => pickTarget(t.value)}
                                        className={`inline-flex h-10 items-center gap-2 rounded-xl border px-3.5 text-sm font-semibold ${active ? "border-violet-500 bg-violet-50 text-violet-700" : "border-slate-200 text-slate-600 hover:border-slate-300"}`}
                                    >
                                        <Icon className="h-4 w-4" /> {t.label}
                                    </button>
                                );
                            })}
                        </div>

                        <div className="mt-4">
                            {targetTab === "all" && (
                                <p className="text-sm text-slate-600">Every active account ({roleCounts.all ?? 0}).</p>
                            )}

                            {targetTab === "role" && (
                                <div className="grid gap-2 sm:grid-cols-4">
                                    {ROLES.map((r) => (
                                        <button
                                            type="button"
                                            key={r.value}
                                            onClick={() => set({ target_value: r.value })}
                                            className={`rounded-xl border px-3 py-2.5 text-left text-sm ${data.target_value === r.value ? "border-violet-500 bg-violet-50" : "border-slate-200 hover:border-slate-300"}`}
                                        >
                                            <div className="font-semibold text-slate-800">{r.label}</div>
                                            <div className="text-xs text-slate-500">{roleCounts[r.value] ?? 0} active</div>
                                        </button>
                                    ))}
                                </div>
                            )}

                            {targetTab === "users" && <PeoplePicker chosen={chosen} setChosen={setChosen} />}

                            {targetTab === "label" && (
                                <LabelPicker
                                    chosen={chosenLabel}
                                    onPick={(l) => {
                                        setChosenLabel(l);
                                        set({ target_value: l ? String(l.id) : "" });
                                    }}
                                    includeArtists={data.include_label_artists}
                                    setIncludeArtists={(v) => set({ include_label_artists: v })}
                                />
                            )}

                            {targetTab === "more" && (
                                <div className="grid gap-3 sm:grid-cols-[12rem_1fr]">
                                    <select
                                        value={data.target_type}
                                        onChange={(e) => set({ target_type: e.target.value, target_value: "" })}
                                        className="h-11 rounded-xl border border-slate-300 px-3 text-sm"
                                    >
                                        <option value="country">Country</option>
                                        <option value="state">State</option>
                                        <option value="client_id">Client ID</option>
                                    </select>
                                    {data.target_type === "client_id" ? (
                                        <input
                                            value={data.target_value}
                                            onChange={(e) => set({ target_value: e.target.value })}
                                            placeholder="Client ID"
                                            className="h-11 rounded-xl border border-slate-300 px-3 text-sm"
                                        />
                                    ) : (
                                        <select
                                            value={data.target_value}
                                            onChange={(e) => set({ target_value: e.target.value })}
                                            className="h-11 rounded-xl border border-slate-300 px-3 text-sm"
                                        >
                                            <option value="">Choose…</option>
                                            {(data.target_type === "country" ? countries : states).map((c) => (
                                                <option key={c} value={c}>{c}</option>
                                            ))}
                                        </select>
                                    )}
                                </div>
                            )}

                            {(errors.target_value || errors.target_users) && (
                                <p className="mt-2 text-sm font-medium text-red-600">{errors.target_value || errors.target_users}</p>
                            )}
                        </div>

                        <div className="mt-4 flex items-center gap-2 rounded-xl bg-slate-50 px-3.5 py-2.5 text-sm">
                            {previewBusy ? <Loader2 className="h-4 w-4 animate-spin text-slate-400" /> : <Users className="h-4 w-4 text-violet-600" />}
                            {preview?.problem ? (
                                <span className="text-amber-700">{preview.problem}</span>
                            ) : (
                                <span className="text-slate-700">
                                    <b>{preview?.count ?? 0}</b> {preview?.count === 1 ? "person" : "people"} will get it
                                    {preview?.sample?.length ? <span className="text-slate-500"> — {preview.sample.join(", ")}{preview.count > preview.sample.length ? " …" : ""}</span> : null}
                                </span>
                            )}
                        </div>
                    </Card>

                    {/* 2. Message */}
                    <Card step="2" title="What to send">
                        <div className="grid gap-2 sm:grid-cols-3">
                            {CATEGORIES.map((c) => {
                                const Icon = c.icon;
                                return (
                                    <button
                                        type="button"
                                        key={c.value}
                                        onClick={() => set({ category: c.value })}
                                        className={`rounded-xl border px-3 py-2.5 text-left ${data.category === c.value ? "border-violet-500 bg-violet-50" : "border-slate-200 hover:border-slate-300"}`}
                                    >
                                        <div className="flex items-center gap-2 text-sm font-semibold text-slate-800"><Icon className="h-4 w-4" />{c.label}</div>
                                        <div className="mt-0.5 text-xs text-slate-500">{c.hint}</div>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="mt-4 grid gap-4">
                            <Input label="Title" error={errors.title} value={data.title} onChange={(v) => set({ title: v })} maxLength={255} />
                            <label className="block">
                                <span className="text-sm font-semibold text-slate-700">Message</span>
                                <textarea
                                    rows={4}
                                    maxLength={5000}
                                    value={data.message}
                                    onChange={(e) => set({ message: e.target.value })}
                                    className={`mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm ${errors.message ? "border-red-400 bg-red-50" : "border-slate-300"}`}
                                />
                                <span className="mt-1 flex justify-between text-xs text-slate-400">
                                    <span className="text-red-600">{errors.message}</span>
                                    <span>{data.message.length} / 5000</span>
                                </span>
                            </label>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Input
                                    label="Button link (optional)"
                                    placeholder="/v2/statements or https://…"
                                    error={errors.action_url}
                                    value={data.action_url}
                                    onChange={(v) => set({ action_url: v.trim() })}
                                    hint="Where the person goes when they open the notice."
                                />
                                <div>
                                    <span className="text-sm font-semibold text-slate-700">Type</span>
                                    <div className="mt-1.5 grid grid-cols-2 gap-2">
                                        {SEVERITY.map((s) => (
                                            <button
                                                type="button"
                                                key={s.value}
                                                onClick={() => set({ severity: s.value })}
                                                className={`h-10 rounded-xl border text-xs font-semibold ${data.severity === s.value ? s.cls + " ring-2 ring-violet-400" : "border-slate-200 text-slate-600"}`}
                                            >
                                                {s.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/60 p-3.5">
                                <input type="checkbox" className="mt-0.5" checked={data.highlight} onChange={(e) => set({ highlight: e.target.checked })} />
                                <span>
                                    <span className="flex items-center gap-1.5 text-sm font-semibold text-slate-800"><Star className="h-4 w-4 text-amber-500" /> Highlight</span>
                                    <span className="mt-0.5 block text-xs text-slate-600">Shows as a banner at the top of their panel until they open or hide it.</span>
                                </span>
                            </label>
                        </div>
                    </Card>

                    {/* 3. Preview + send */}
                    <Card step="3" title="Check and send">
                        <div className={`rounded-xl border px-4 py-3 ${data.highlight ? sev.cls : "border-slate-200 bg-white"}`}>
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="text-xs font-semibold uppercase tracking-wider opacity-70">
                                        {CATEGORIES.find((c) => c.value === data.category)?.label} · {sev.label}{data.highlight ? " · Highlighted" : ""}
                                    </div>
                                    <div className="mt-1 break-words text-sm font-bold text-slate-900">{data.title || "Title"}</div>
                                    <div className="mt-1 whitespace-pre-line break-words text-sm text-slate-600">{data.message || "Message"}</div>
                                </div>
                                {data.action_url && <span className="shrink-0 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">Open</span>}
                            </div>
                        </div>

                        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span className="text-sm text-slate-500">{preview?.count ?? 0} {preview?.count === 1 ? "person" : "people"} · {preview?.label || "—"}</span>
                            <button
                                type="submit"
                                disabled={processing || !data.title || !data.message || !(preview?.count > 0)}
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-violet-600 px-6 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-40"
                            >
                                {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                Send notification
                            </button>
                        </div>
                    </Card>
                </form>

                {/* Ready-made notices */}
                <aside className="space-y-5">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="flex items-center gap-2 text-base font-semibold text-slate-900"><Sparkles className="h-4 w-4 text-violet-600" /> Ready-made notices</h2>
                        <p className="mt-0.5 text-xs text-slate-500">Press one — it fills the form and picks a suggested audience. Change anything, then send.</p>
                        <div className="mt-3 flex gap-1 rounded-xl bg-slate-100 p-1">
                            {CATEGORIES.map((c) => (
                                <button
                                    type="button"
                                    key={c.value}
                                    onClick={() => setTemplateTab(c.value)}
                                    className={`flex-1 rounded-lg py-1.5 text-xs font-semibold ${templateTab === c.value ? "bg-white text-violet-700 shadow-sm" : "text-slate-500"}`}
                                >
                                    {c.label}
                                </button>
                            ))}
                        </div>
                        <ul className="mt-3 space-y-2">
                            {TEMPLATES.filter((t) => t.category === templateTab).map((t) => (
                                <li key={t.title}>
                                    <button
                                        type="button"
                                        onClick={() => applyTemplate(t)}
                                        className="w-full rounded-xl border border-slate-200 p-3 text-left hover:border-violet-300 hover:bg-violet-50/40"
                                    >
                                        <div className="flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                                            {t.highlight && <Star className="h-3.5 w-3.5 shrink-0 text-amber-500" />}
                                            <span className="truncate">{t.title}</span>
                                        </div>
                                        <div className="mt-0.5 line-clamp-2 text-xs text-slate-500">{t.message}</div>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                </aside>
            </div>

            <History items={recentBroadcasts} />
        </PanelLayout>
    );
}

function Card({ step, title, children }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 className="mb-4 flex items-center gap-2 text-base font-semibold text-slate-900">
                <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-violet-600 text-xs font-bold text-white">{step}</span>
                {title}
            </h2>
            {children}
        </section>
    );
}

function Input({ label, value, onChange, error, hint, ...rest }) {
    return (
        <label className="block">
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            <input
                {...rest}
                value={value ?? ""}
                onChange={(e) => onChange(e.target.value)}
                className={`mt-1.5 h-11 w-full rounded-xl border px-3.5 text-sm ${error ? "border-red-400 bg-red-50" : "border-slate-300"}`}
            />
            {hint && !error && <span className="mt-1 block text-xs text-slate-500">{hint}</span>}
            {error && <span className="mt-1 block text-xs font-medium text-red-600">{error}</span>}
        </label>
    );
}

function useSearch(url, key) {
    const [q, setQ] = useState("");
    const [items, setItems] = useState([]);
    const [busy, setBusy] = useState(false);
    const dq = useDebounced(q, 300);

    useEffect(() => {
        if (dq.trim().length < 2) {
            setItems([]);
            return;
        }
        let cancel = false;
        setBusy(true);
        axios
            .get(url, { params: { q: dq } })
            .then(({ data }) => !cancel && setItems(data[key] || []))
            .catch(() => !cancel && setItems([]))
            .finally(() => !cancel && setBusy(false));
        return () => {
            cancel = true;
        };
    }, [dq, url, key]);

    return { q, setQ, items, busy };
}

function PeoplePicker({ chosen, setChosen }) {
    const { q, setQ, items, busy } = useSearch("/v2/admin/notification-management/search-users", "users");
    const chosenIds = useMemo(() => new Set(chosen.map((u) => u.id)), [chosen]);

    return (
        <div>
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-slate-400" />
                <input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search name, e-mail or client ID (2+ letters)"
                    className="h-11 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm"
                />
                {busy && <Loader2 className="absolute right-3 top-3.5 h-4 w-4 animate-spin text-slate-400" />}
            </div>

            {items.length > 0 && (
                <ul className="mt-2 max-h-56 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200">
                    {items.map((u) => (
                        <li key={u.id}>
                            <button
                                type="button"
                                disabled={chosenIds.has(u.id)}
                                onClick={() => setChosen((c) => [...c, u])}
                                className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50 disabled:opacity-40"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate font-semibold text-slate-800">{u.name}</span>
                                    <span className="block truncate text-xs text-slate-500">{u.email}</span>
                                </span>
                                <span className="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{u.role === "admin" ? "manager" : u.role}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {chosen.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {chosen.map((u) => (
                        <span key={u.id} className="inline-flex items-center gap-1 rounded-full bg-violet-50 py-1 pl-3 pr-1 text-xs font-semibold text-violet-700">
                            {u.name}
                            <button type="button" onClick={() => setChosen((c) => c.filter((x) => x.id !== u.id))} className="rounded-full p-0.5 hover:bg-violet-100" aria-label={`Remove ${u.name}`}>
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </span>
                    ))}
                    <button type="button" onClick={() => setChosen([])} className="text-xs font-semibold text-slate-500 underline">Clear all</button>
                </div>
            )}
        </div>
    );
}

function LabelPicker({ chosen, onPick, includeArtists, setIncludeArtists }) {
    const { q, setQ, items, busy } = useSearch("/v2/admin/notification-management/search-labels", "labels");

    return (
        <div>
            {chosen ? (
                <div className="flex items-center justify-between gap-3 rounded-xl border border-violet-300 bg-violet-50 px-3.5 py-2.5">
                    <span className="min-w-0 text-sm">
                        <span className="block truncate font-semibold text-slate-800">{chosen.name}</span>
                        <span className="block truncate text-xs text-slate-500">Owner: {chosen.owner_name || "no login yet"} · plus the label's team</span>
                    </span>
                    <button type="button" onClick={() => onPick(null)} className="text-xs font-semibold text-violet-700 underline">Change</button>
                </div>
            ) : (
                <>
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-slate-400" />
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search label name (2+ letters)"
                            className="h-11 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm"
                        />
                        {busy && <Loader2 className="absolute right-3 top-3.5 h-4 w-4 animate-spin text-slate-400" />}
                    </div>
                    {items.length > 0 && (
                        <ul className="mt-2 max-h-56 divide-y divide-slate-100 overflow-auto rounded-xl border border-slate-200">
                            {items.map((l) => (
                                <li key={l.id}>
                                    <button type="button" onClick={() => onPick(l)} className="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">
                                        <span className="font-semibold text-slate-800">{l.name}</span>
                                        <span className="ml-2 text-xs text-slate-500">{l.owner_name || "no owner login"}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}

            <label className="mt-3 flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" checked={includeArtists} onChange={(e) => setIncludeArtists(e.target.checked)} />
                Also send to this label's artists (who have a login)
            </label>
        </div>
    );
}

function History({ items = [] }) {
    const [open, setOpen] = useState(null);
    const [list, setList] = useState([]);
    const [busy, setBusy] = useState(false);

    const show = (id) => {
        if (open === id) {
            setOpen(null);
            return;
        }
        setOpen(id);
        setBusy(true);
        axios
            .get(`/v2/admin/notification-management/${id}/recipients`)
            .then(({ data }) => setList(data.recipients || []))
            .catch(() => setList([]))
            .finally(() => setBusy(false));
    };

    return (
        <section className="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 className="text-base font-semibold text-slate-900">Sent notifications</h2>
            <p className="mt-0.5 text-xs text-slate-500">Last 25. Press one to see who got it and who opened it.</p>

            {items.length === 0 ? (
                <p className="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">Nothing sent yet.</p>
            ) : (
                <ul className="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
                    {items.map((b) => (
                        <li key={b.broadcast_id}>
                            <button type="button" onClick={() => show(b.broadcast_id)} className="flex w-full flex-col gap-1 px-4 py-3 text-left hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                                <span className="min-w-0">
                                    <span className="flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                                        {b.highlight && <Star className="h-3.5 w-3.5 text-amber-500" />}
                                        <span className="truncate">{b.title}</span>
                                    </span>
                                    <span className="block truncate text-xs text-slate-500">
                                        {b.created_at} · {b.category} · {b.target_label || b.target_type}
                                    </span>
                                </span>
                                <span className="shrink-0 text-xs font-semibold text-slate-600">
                                    {b.opened} / {b.recipients} opened
                                </span>
                            </button>

                            {open === b.broadcast_id && (
                                <div className="border-t border-slate-100 bg-slate-50 px-4 py-3">
                                    {busy ? (
                                        <Loader2 className="h-4 w-4 animate-spin text-slate-400" />
                                    ) : (
                                        <ul className="max-h-64 space-y-1 overflow-auto text-xs">
                                            {list.map((r, i) => (
                                                <li key={i} className="flex justify-between gap-3">
                                                    <span className="truncate text-slate-700">{r.name} <span className="text-slate-400">({r.email})</span></span>
                                                    <span className={`shrink-0 ${r.opened_at ? "text-emerald-700" : "text-slate-400"}`}>
                                                        {r.opened_at ? `opened ${r.opened_at}` : r.removed ? "removed" : "not opened"}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

