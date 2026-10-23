import React, { useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/*
 * MIXXTUNE_OWNERSHIP_SAFE_V1 (W56)
 * Search the whole catalogue on the server, preview what will move,
 * reason + e-mailed code, readable history with names, filters and CSV.
 */

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

const TABS = [
    ['release', 'Release → new owner'],
    ['track', 'Track → other release'],
    ['label', 'Label → new login'],
    ['artist', 'Artist → new login'],
    ['history', 'Transfer history'],
];

function Picker({ label, type, value, onChange, placeholder, error }) {
    const [q, setQ] = useState('');
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState([]);
    const [busy, setBusy] = useState(false);
    const timer = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(async () => {
            setBusy(true);
            try {
                const res = await fetch(`/admin/ownership/search?type=${type}&q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const data = res.ok ? await res.json() : { results: [] };
                setItems(data.results || []);
            } catch {
                setItems([]);
            } finally {
                setBusy(false);
            }
        }, 250);
        return () => window.clearTimeout(timer.current);
    }, [q, open, type]);

    return (
        <div className="relative">
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">{label}</span>
            {value ? (
                <div className="flex items-start justify-between gap-3 rounded-xl border border-violet-200 bg-violet-50 px-3 py-2.5">
                    <div className="min-w-0">
                        <div className="truncate text-sm font-semibold text-slate-900">{value.title}</div>
                        <div className="truncate text-xs text-slate-500">{value.sub}</div>
                    </div>
                    <button type="button" onClick={() => onChange(null)} className="shrink-0 text-xs font-semibold text-violet-700 hover:underline">
                        Change
                    </button>
                </div>
            ) : (
                <input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    onFocus={() => setOpen(true)}
                    onBlur={() => window.setTimeout(() => setOpen(false), 200)}
                    placeholder={placeholder}
                    className={inputClass}
                />
            )}
            {open && !value && (
                <div className="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                    {busy && <div className="px-3 py-2 text-xs text-slate-400">Searching…</div>}
                    {!busy && items.length === 0 && <div className="px-3 py-3 text-xs text-slate-500">Nothing found. Type a name, UPC, ISRC or e-mail.</div>}
                    {items.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => {
                                onChange(item);
                                setQ('');
                                setOpen(false);
                            }}
                            className="block w-full border-b border-slate-50 px-3 py-2 text-left hover:bg-violet-50"
                        >
                            <div className="truncate text-sm font-semibold text-slate-900">{item.title}</div>
                            <div className="truncate text-xs text-slate-500">{item.sub}</div>
                        </button>
                    ))}
                </div>
            )}
            {error && <div className="mt-1 text-xs font-semibold text-rose-600">{error}</div>}
        </div>
    );
}

function Preview({ params }) {
    const [state, setState] = useState(null);
    const key = params ? new URLSearchParams(params).toString() : '';

    useEffect(() => {
        if (!key) {
            setState(null);
            return;
        }
        let stop = false;
        setState({ loading: true });
        fetch(`/admin/ownership/preview?${key}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => r.json())
            .then((d) => !stop && setState(d))
            .catch(() => !stop && setState({ ok: false, message: 'Preview could not load.' }));
        return () => {
            stop = true;
        };
    }, [key]);

    if (!state) return null;
    if (state.loading) return <div className="rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500">Checking what will change…</div>;
    if (state.ok === false) return <div className="rounded-xl bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700">{state.message}</div>;

    return (
        <div className="space-y-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500">What will change</div>
            <ul className="space-y-1 text-sm text-slate-700">
                {(state.lines || []).map((l) => (
                    <li key={l}>• {l}</li>
                ))}
            </ul>
            {(state.warnings || []).map((w) => (
                <div key={w} className="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                    ⚠ {w}
                </div>
            ))}
        </div>
    );
}

function Reason({ value, onChange, error }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">Reason (required, saved in history)</span>
            <textarea value={value} onChange={(e) => onChange(e.target.value)} rows={2} className={inputClass} placeholder="e.g. Label sold the catalogue to …, agreement dated …" />
            {error && <div className="mt-1 text-xs font-semibold text-rose-600">{error}</div>}
        </label>
    );
}

function Scope({ value, onChange }) {
    return (
        <div>
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">Which revenue goes to the new owner?</span>
            <div className="grid gap-2 sm:grid-cols-2">
                {[
                    ['future_only', 'Only future reports', 'Reports uploaded after today.'],
                    ['pending_and_future', 'Reports in review + future', 'Also rows of reports not approved yet. Approved / paid money never moves.'],
                ].map(([v, t, d]) => (
                    <button
                        key={v}
                        type="button"
                        onClick={() => onChange(v)}
                        className={`rounded-xl border px-3 py-2.5 text-left ${value === v ? 'border-violet-500 bg-violet-50 ring-2 ring-violet-100' : 'border-slate-200 bg-white hover:bg-slate-50'}`}
                    >
                        <div className="text-sm font-semibold text-slate-900">{t}</div>
                        <div className="text-xs text-slate-500">{d}</div>
                    </button>
                ))}
            </div>
        </div>
    );
}

function Submit({ processing, disabled, children }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
            <p className="text-xs text-slate-500">A 6-digit code is e-mailed to you before anything changes.</p>
            <button
                type="submit"
                disabled={processing || disabled}
                className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {processing ? 'Working…' : children}
            </button>
        </div>
    );
}

function useTransferForm(initial, url, onDone) {
    const form = useForm(initial);
    const submit = (e) => {
        e.preventDefault();
        form.post(url, {
            preserveScroll: true,
            onSuccess: (page) => {
                if (!page.props?.errors || Object.keys(page.props.errors).length === 0) {
                    form.reset();
                    onDone?.();
                }
            },
        });
    };
    return [form, submit];
}

function ReleaseForm() {
    const [release, setRelease] = useState(null);
    const [owner, setOwner] = useState(null);
    const [form, submit] = useTransferForm({ release_id: '', owner_type: 'label', owner_id: '', scope: 'future_only', reason: '' }, '/admin/ownership/release', () => {
        setRelease(null);
        setOwner(null);
    });
    const ready = release && owner;

    return (
        <form onSubmit={submit} className="space-y-4">
            <Picker label="1. Release" type="release" value={release} onChange={(v) => { setRelease(v); form.setData('release_id', v?.id || ''); }} placeholder="Search title, UPC, catalogue no. or artist" error={form.errors.release_id} />
            <div>
                <span className="mb-1.5 block text-sm font-semibold text-slate-700">2. New owner is a</span>
                <div className="flex gap-2">
                    {['label', 'artist'].map((t) => (
                        <button key={t} type="button" onClick={() => { form.setData((d) => ({ ...d, owner_type: t, owner_id: '' })); setOwner(null); }} className={`rounded-xl border px-4 py-2 text-sm font-semibold ${form.data.owner_type === t ? 'border-violet-500 bg-violet-50 text-violet-800' : 'border-slate-200 text-slate-600'}`}>
                            {t === 'label' ? 'Label' : 'Artist (no label)'}
                        </button>
                    ))}
                </div>
            </div>
            <Picker label={`3. New ${form.data.owner_type}`} type={form.data.owner_type} value={owner} onChange={(v) => { setOwner(v); form.setData('owner_id', v?.id || ''); }} placeholder={`Search ${form.data.owner_type} name`} error={form.errors.owner_id} />
            <Scope value={form.data.scope} onChange={(v) => form.setData('scope', v)} />
            {ready && <Preview params={{ kind: 'release', release_id: release.id, owner_type: form.data.owner_type, owner_id: owner.id }} />}
            <Reason value={form.data.reason} onChange={(v) => form.setData('reason', v)} error={form.errors.reason} />
            <Submit processing={form.processing} disabled={!ready}>Move release</Submit>
        </form>
    );
}

function TrackForm() {
    const [track, setTrack] = useState(null);
    const [release, setRelease] = useState(null);
    const [form, submit] = useTransferForm({ track_id: '', release_id: '', scope: 'future_only', reason: '' }, '/admin/ownership/track', () => {
        setTrack(null);
        setRelease(null);
    });
    const ready = track && release;

    return (
        <form onSubmit={submit} className="space-y-4">
            <Picker label="1. Track" type="track" value={track} onChange={(v) => { setTrack(v); form.setData('track_id', v?.id || ''); }} placeholder="Search track title or ISRC" error={form.errors.track_id} />
            <Picker label="2. Move to release" type="release" value={release} onChange={(v) => { setRelease(v); form.setData('release_id', v?.id || ''); }} placeholder="Search title or UPC" error={form.errors.release_id} />
            <Scope value={form.data.scope} onChange={(v) => form.setData('scope', v)} />
            {ready && <Preview params={{ kind: 'track', track_id: track.id, release_id: release.id }} />}
            <Reason value={form.data.reason} onChange={(v) => form.setData('reason', v)} error={form.errors.reason} />
            <Submit processing={form.processing} disabled={!ready}>Move track</Submit>
        </form>
    );
}

function LoginForm({ kind }) {
    const isLabel = kind === 'label';
    const [entity, setEntity] = useState(null);
    const [user, setUser] = useState(null);
    const field = isLabel ? 'label_id' : 'artist_id';
    const [form, submit] = useTransferForm({ [field]: '', user_id: '', reason: '' }, isLabel ? '/admin/ownership/label-user' : '/admin/ownership/artist-user', () => {
        setEntity(null);
        setUser(null);
    });
    const ready = entity && user;

    return (
        <form onSubmit={submit} className="space-y-4">
            <Picker label={`1. ${isLabel ? 'Label' : 'Artist'}`} type={kind} value={entity} onChange={(v) => { setEntity(v); form.setData(field, v?.id || ''); }} placeholder={`Search ${kind} name`} error={form.errors[field]} />
            <Picker label={`2. New ${isLabel ? 'label' : 'artist'} login (active accounts only)`} type={isLabel ? 'label_login' : 'artist_login'} value={user} onChange={(v) => { setUser(v); form.setData('user_id', v?.id || ''); }} placeholder="Search name or e-mail" error={form.errors.user_id} />
            {ready && <Preview params={{ kind: isLabel ? 'label_login' : 'artist_login', [field]: entity.id, user_id: user.id }} />}
            <Reason value={form.data.reason} onChange={(v) => form.setData('reason', v)} error={form.errors.reason} />
            <Submit processing={form.processing} disabled={!ready}>Change login</Submit>
        </form>
    );
}

function History({ history, filters, types }) {
    const [q, setQ] = useState(filters.q || '');
    const go = (extra) =>
        router.get('/admin/ownership', { section: 'history', type: filters.type || '', q, ...extra }, { preserveScroll: true, preserveState: true, replace: true });
    const rows = history.data || [];

    return (
        <div id="transfer-history">
            <div className="flex flex-col gap-3 border-b border-slate-100 pb-4 lg:flex-row lg:items-center lg:justify-between">
                <form onSubmit={(e) => { e.preventDefault(); go({ page: 1 }); }} className="flex flex-1 flex-wrap items-center gap-2">
                    <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search reason, transfer id or entity id" className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm sm:w-80" />
                    <select value={filters.type || ''} onChange={(e) => go({ type: e.target.value, page: 1 })} className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                        <option value="">All kinds</option>
                        {Object.entries(types).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    <button className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
                </form>
                <a href="/admin/ownership/export" className="rounded-xl border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Export CSV
                </a>
            </div>

            {rows.length === 0 ? (
                <div className="py-12 text-center text-sm text-slate-500">No transfers yet{filters.q || filters.type ? ' for this search' : ''}.</div>
            ) : (
                <div className="divide-y divide-slate-100">
                    {rows.map((r) => (
                        <div key={r.id} className="grid gap-2 py-3 text-sm lg:grid-cols-[160px_1.2fr_1fr_1fr_1fr]">
                            <div>
                                <div className="font-semibold text-slate-900">{r.date}</div>
                                <div className="text-xs text-slate-400">by {r.by}</div>
                            </div>
                            <div>
                                <div className="text-xs font-bold uppercase tracking-wide text-violet-600">{r.type}</div>
                                <div className="font-semibold text-slate-900">{r.what}</div>
                            </div>
                            <div className="text-slate-600"><span className="text-xs text-slate-400">From </span>{r.from}</div>
                            <div className="text-slate-900"><span className="text-xs text-slate-400">To </span>{r.to}<div className="text-xs text-slate-400">{r.scope}</div></div>
                            <div className="text-xs text-slate-500">{r.reason || '—'}</div>
                        </div>
                    ))}
                </div>
            )}

            {(history.links || []).length > 3 && (
                <div className="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                    {history.links.map((link, i) => (
                        <Link
                            key={`${link.label}-${i}`}
                            href={link.url || '#'}
                            preserveScroll
                            className={`rounded-lg border px-3 py-1.5 text-xs font-semibold ${link.active ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 bg-white text-slate-600'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

export default function OwnershipIndex({ history = { data: [] }, filters = {}, types = {}, section = '' }) {
    const { flash = {}, errors = {} } = usePage().props;
    const [tab, setTab] = useState(section === 'history' ? 'history' : 'release');

    useEffect(() => {
        setTab(section === 'history' ? 'history' : 'release');
    }, [section]);

    const otherErrors = Object.entries(errors).filter(([k]) => !['release_id', 'owner_id', 'track_id', 'label_id', 'artist_id', 'user_id', 'reason', 'action_otp'].includes(k));

    return (
        <PanelLayout role="super_admin" title={tab === 'history' ? 'Transfer History' : 'Ownership Transfer'} subtitle="Move releases and tracks to another owner, or give a label / artist to another login">
            <Head title="Ownership Transfer" />

            <div className="space-y-5">
                {flash.success && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-800">{flash.success}</div>}
                {otherErrors.length > 0 && (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800">
                        {otherErrors.map(([k, v]) => (
                            <div key={k}>{v}</div>
                        ))}
                    </div>
                )}

                <div className="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2">
                    {TABS.map(([k, t]) => (
                        <button
                            key={k}
                            type="button"
                            onClick={() => setTab(k)}
                            className={`whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold ${tab === k ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-slate-50'}`}
                        >
                            {t}
                            {k === 'history' && history.total ? ` (${history.total})` : ''}
                        </button>
                    ))}
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    {tab === 'release' && <ReleaseForm />}
                    {tab === 'track' && <TrackForm />}
                    {tab === 'label' && <LoginForm kind="label" />}
                    {tab === 'artist' && <LoginForm kind="artist" />}
                    {tab === 'history' && <History history={history} filters={filters} types={types} />}
                </div>
            </div>
        </PanelLayout>
    );
}
