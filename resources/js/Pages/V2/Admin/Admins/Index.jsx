import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';

/*
 * W60 MIXXTUNE_MANAGERS_V2: search, status filter, last login, clear
 * counts, actions (Manage, Edit, Login as, Suspend / Activate), totals,
 * labels without any manager, page links, phone cards.
 */
const STATUS = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    suspended: 'bg-rose-50 text-rose-700 ring-rose-600/20',
};

export default function Index({ role = 'super_admin', admins = {}, filters = {}, totals = {} }) {
    const [q, setQ] = useState(filters.q || '');
    const [busy, setBusy] = useState(null);
    const rows = admins.data ?? [];

    const go = (extra = {}) =>
        router.get('/v2/admin/admins', { q, status: filters.status || '', ...extra }, { preserveState: true, replace: true, preserveScroll: true });

    const toggle = (a) => {
        const next = a.account_status === 'active' ? 'suspend' : 'activate';
        if (!window.confirm(`${next === 'suspend' ? 'Suspend' : 'Activate'} ${a.name}? ${next === 'suspend' ? 'They are logged out at once and lose access to their labels.' : ''}`)) return;
        setBusy(a.id);
        router.post(`/v2/admin/users/${a.id}/toggle-status`, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    const loginAs = (a) => {
        if (!window.confirm(`Open the panel as ${a.name}? Everything you do is logged under your name.`)) return;
        router.post(`/v2/admin/users/${a.id}/impersonate`);
    };

    return (
        <PanelLayout role={role} title="Managers" subtitle="People who work on the labels and artists assigned to them">
            <Head title="Managers" />
            <PageMessages />

            <div className="space-y-5">
                <div className="grid gap-3 sm:grid-cols-3">
                    <Stat label="Managers" value={totals.managers ?? 0} />
                    <Stat label="Active" value={totals.active ?? 0} />
                    <Stat label="Labels with no manager" value={totals.labels_without_manager ?? 0} warn={(totals.labels_without_manager ?? 0) > 0} />
                </div>

                <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                    <form onSubmit={(e) => { e.preventDefault(); go({ page: 1 }); }} className="flex flex-1 flex-wrap gap-2">
                        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name or e-mail" className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:w-72" />
                        <select value={filters.status || ''} onChange={(e) => go({ status: e.target.value, page: 1 })} className="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="pending">Invited (pending)</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        <button className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
                    </form>
                    <Link href="/v2/admin/users/create?type=manager" className="rounded-xl bg-violet-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-violet-700">
                        + Create Manager
                    </Link>
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {rows.length === 0 ? (
                        <div className="px-6 py-14 text-center text-sm text-slate-500">No managers{filters.q || filters.status ? ' for this search' : ' yet'}.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {rows.map((a) => (
                                <div key={a.id} className="grid gap-3 px-5 py-4 lg:grid-cols-[1.5fr_120px_1fr_1fr_auto] lg:items-center">
                                    <div className="min-w-0">
                                        <div className="truncate font-semibold text-slate-900">{a.name}</div>
                                        <div className="truncate text-xs text-slate-500">{a.email}</div>
                                        <div className="mt-0.5 text-xs text-slate-400">
                                            Manager · since {a.created || '—'}
                                            {['pending', 'sent', 'expired', 'failed', 'password_reset'].includes(a.invitation_status) ? ` · invite ${String(a.invitation_status).replaceAll('_', ' ')}` : ''}
                                        </div>
                                    </div>
                                    <div>
                                        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1 ring-inset ${STATUS[a.account_status] ?? 'bg-slate-100 text-slate-700'}`}>{a.account_status}</span>
                                    </div>
                                    <div className="text-sm text-slate-700">
                                        <b>{a.assigned_labels_count}</b> labels · <b>{a.assigned_artists_count}</b> artists
                                    </div>
                                    <div className="text-xs text-slate-500">Last login: {a.last_login || 'never'}</div>
                                    <div className="flex flex-wrap gap-2">
                                        <Link href={`/v2/admin/admins/${a.id}`} className="rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white">Manage</Link>
                                        <Link href={`/v2/admin/users/${a.id}/edit`} className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Edit</Link>
                                        {a.account_status === 'active' && (
                                            <button type="button" onClick={() => loginAs(a)} className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Login as</button>
                                        )}
                                        <button type="button" disabled={busy === a.id} onClick={() => toggle(a)} className={`rounded-lg px-3 py-2 text-xs font-semibold text-white disabled:opacity-50 ${a.account_status === 'active' ? 'bg-rose-600' : 'bg-emerald-600'}`}>
                                            {a.account_status === 'active' ? 'Suspend' : 'Activate'}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {(admins.links || []).length > 3 && (
                        <div className="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                            {admins.links.map((link, i) => (
                                <Link key={`${link.label}-${i}`} href={link.url || '#'} preserveScroll className={`rounded-lg border px-3 py-1.5 text-xs font-semibold ${link.active ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 bg-white text-slate-600'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}

function Stat({ label, value, warn = false }) {
    return (
        <div className={`rounded-2xl border bg-white p-4 shadow-sm ${warn ? 'border-amber-200' : 'border-slate-200'}`}>
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</div>
            <div className={`mt-1 text-2xl font-bold ${warn ? 'text-amber-700' : 'text-slate-900'}`}>{Number(value).toLocaleString('en-IN')}</div>
        </div>
    );
}
