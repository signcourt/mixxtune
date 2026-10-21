import { useMemo, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';

/*
 * W60 MIXXTUNE_MANAGER_ASSIGN_V2
 * A searchable list (not a wall of 282 chips): filters, select all shown,
 * owner / other managers per row, a summary of what changes before saving.
 * The manager's existing permission flags are kept by the server.
 */
export default function Show({ role = 'super_admin', admin, artists = [], labels = [], assignedArtistIds = [], assignedLabelIds = [] }) {
    const form = useForm({
        artist_ids: assignedArtistIds.map(Number),
        label_ids: assignedLabelIds.map(Number),
    });
    const [tab, setTab] = useState('labels');

    const before = useMemo(() => ({ labels: new Set(assignedLabelIds.map(Number)), artists: new Set(assignedArtistIds.map(Number)) }), [assignedLabelIds, assignedArtistIds]);
    const diff = (key, set) => {
        const now = new Set(form.data[key]);
        let added = 0;
        let removed = 0;
        now.forEach((id) => { if (!set.has(id)) added++; });
        set.forEach((id) => { if (!now.has(id)) removed++; });
        return { added, removed };
    };
    const dl = diff('label_ids', before.labels);
    const da = diff('artist_ids', before.artists);
    const changed = dl.added + dl.removed + da.added + da.removed > 0;

    const submit = () => {
        if (!changed) return;
        const msg = `Save for ${admin.name}?\n\nLabels: +${dl.added} / -${dl.removed}\nArtists: +${da.added} / -${da.removed}`;
        if (!window.confirm(msg)) return;
        form.patch(`/v2/admin/admins/${admin.id}/assignments`, { preserveScroll: true });
    };

    return (
        <PanelLayout role={role} title="Manager Assignments" subtitle={`${admin.name} • ${admin.email}`}>
            <Head title="Manager Assignments" />
            <PageMessages />

            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href="/v2/admin/admins" className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">← Managers</Link>
                    <div className="text-xs text-slate-500">
                        Status: <b className="capitalize">{admin.account_status}</b> · Last login: {admin.last_login || 'never'}
                    </div>
                </div>

                <div className="rounded-2xl border border-violet-100 bg-violet-50/60 px-5 py-3 text-sm text-violet-900">
                    A manager can see and work on the labels and artists ticked here (releases, catalogue, reports of those owners). Nothing else.
                </div>

                <div className="flex gap-2 rounded-2xl border border-slate-200 bg-white p-2">
                    {[
                        ['labels', `Labels (${form.data.label_ids.length})`],
                        ['artists', `Artists (${form.data.artist_ids.length})`],
                    ].map(([k, t]) => (
                        <button key={k} type="button" onClick={() => setTab(k)} className={`rounded-xl px-4 py-2 text-sm font-semibold ${tab === k ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-slate-50'}`}>{t}</button>
                    ))}
                </div>

                {tab === 'labels' ? (
                    <Picker items={labels} selected={form.data.label_ids} onChange={(ids) => form.setData('label_ids', ids)} noun="label" />
                ) : (
                    <Picker items={artists} selected={form.data.artist_ids} onChange={(ids) => form.setData('artist_ids', ids)} noun="artist" />
                )}

                <div className="sticky bottom-4 flex flex-wrap items-center justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur">
                    <span className="text-sm text-slate-600">
                        {changed ? `Labels +${dl.added} / -${dl.removed} · Artists +${da.added} / -${da.removed}` : 'No changes'}
                    </span>
                    <button type="button" disabled={form.processing || !changed} onClick={() => { form.setData({ artist_ids: assignedArtistIds.map(Number), label_ids: assignedLabelIds.map(Number) }); }} className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 disabled:opacity-40">Undo changes</button>
                    <button type="button" disabled={form.processing || !changed} onClick={submit} className="rounded-xl bg-violet-600 px-6 py-2.5 text-sm font-bold text-white disabled:opacity-50">{form.processing ? 'Saving…' : 'Save assignments'}</button>
                </div>
            </div>
        </PanelLayout>
    );
}

function Picker({ items, selected, onChange, noun }) {
    const [q, setQ] = useState('');
    const [show, setShow] = useState('all');
    const sel = useMemo(() => new Set(selected.map(Number)), [selected]);
    const term = q.trim().toLowerCase();

    const shown = useMemo(
        () =>
            items.filter((i) => {
                if (show === 'mine' && !sel.has(i.id)) return false;
                if (show === 'free' && ((i.others || []).length > 0 || sel.has(i.id))) return false;
                if (show === 'others' && (i.others || []).length === 0) return false;
                if (!term) return true;
                return `${i.name} ${i.sub}`.toLowerCase().includes(term);
            }),
        [items, show, sel, term],
    );
    const limit = 300;
    const visible = shown.slice(0, limit);

    const toggle = (id) => onChange(sel.has(id) ? selected.filter((x) => x !== id) : [...selected, id]);
    const selectShown = () => onChange(Array.from(new Set([...selected, ...shown.map((i) => i.id)])));
    const clearShown = () => {
        const drop = new Set(shown.map((i) => i.id));
        onChange(selected.filter((x) => !drop.has(x)));
    };

    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-col gap-2 border-b border-slate-100 p-3 lg:flex-row lg:items-center">
                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={`Search ${noun} name, owner or label`} className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm lg:w-80" />
                <select value={show} onChange={(e) => setShow(e.target.value)} className="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="all">All {noun}s</option>
                    <option value="mine">Ticked for this manager</option>
                    <option value="free">No manager at all</option>
                    <option value="others">With another manager</option>
                </select>
                <div className="flex flex-1 flex-wrap justify-end gap-2 text-xs">
                    <span className="self-center text-slate-500">{shown.length} shown · {selected.length} ticked</span>
                    <button type="button" onClick={selectShown} className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold text-slate-700">Tick all shown</button>
                    <button type="button" onClick={clearShown} className="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold text-slate-700">Untick all shown</button>
                </div>
            </div>
            <div className="max-h-[60vh] divide-y divide-slate-50 overflow-y-auto">
                {visible.length === 0 && <div className="px-5 py-10 text-center text-sm text-slate-500">Nothing matches.</div>}
                {visible.map((i) => (
                    <label key={i.id} className={`flex cursor-pointer items-start gap-3 px-4 py-2.5 hover:bg-slate-50 ${sel.has(i.id) ? 'bg-violet-50/50' : ''}`}>
                        <input type="checkbox" className="mt-1" checked={sel.has(i.id)} onChange={() => toggle(i.id)} />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-semibold text-slate-900">{i.name}</span>
                            <span className="block truncate text-xs text-slate-500">
                                {i.sub}
                                {i.status && i.status !== 'active' ? ` · ${i.status}` : ''}
                            </span>
                        </span>
                        {(i.others || []).length > 0 && (
                            <span className="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700" title={i.others.join(', ')}>
                                also: {i.others.slice(0, 2).join(', ')}{i.others.length > 2 ? ` +${i.others.length - 2}` : ''}
                            </span>
                        )}
                    </label>
                ))}
                {shown.length > limit && <div className="px-5 py-3 text-center text-xs text-slate-500">Showing the first {limit} - search to narrow down.</div>}
            </div>
        </section>
    );
}
