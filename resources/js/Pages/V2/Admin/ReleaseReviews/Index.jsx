import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';

/*
 * W63 MIXXTUNE_RELEASE_QUEUE_V2: page links (only the first 25 were
 * reachable), messages after approve / reject, sort, label column, days
 * waiting, title per tab, date column that fits the tab, delivery link
 * for approved / processing releases.
 */
const statusClasses = {
    submitted: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
    changes_requested: 'bg-orange-100 text-orange-700',
    processing: 'bg-blue-100 text-blue-700',
};

const TABS = [
    ['submitted', 'In Review', 'Waiting for your review - oldest first.'],
    ['changes_requested', 'Changes Requested', 'Sent back to the owner; they come back to "In Review" when the owner sends them again.'],
    ['approved', 'Approved', 'Approved - press Start Processing to begin store delivery.'],
    ['processing', 'Processing', 'Being delivered to stores - open Delivery to update each store.'],
    ['rejected', 'Rejected', 'Not accepted. The owner sees the reason.'],
];

const DATE_COLUMN = {
    submitted: ['Submitted', 'submitted_at'],
    changes_requested: ['Sent back', 'updated_at'],
    approved: ['Approved', 'approved_at'],
    processing: ['Updated', 'updated_at'],
    rejected: ['Rejected', 'updated_at'],
};

const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
};

const formatDate = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
};

const daysSince = (value) => {
    if (!value) return null;
    const t = new Date(value).getTime();
    if (Number.isNaN(t)) return null;
    return Math.max(0, Math.floor((Date.now() - t) / 86400000));
};

export default function Index({ role = 'admin', releases = {}, counts = {}, filters = {} }) {
    const rows = releases.data ?? [];
    const status = filters.status || 'submitted';
    const tab = TABS.find((t) => t[0] === status) ?? TABS[0];
    const [dateLabel, dateKey] = DATE_COLUMN[status] ?? DATE_COLUMN.submitted;
    const [search, setSearch] = useState(filters.search ?? '');

    const go = (changes) =>
        router.get('/admin/release-reviews', { ...filters, ...changes, page: undefined }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <PanelLayout role={role} title={tab[1]} subtitle={tab[2]}>
            <Head title={`${tab[1]} - Releases`} />
            <PageMessages />

            <div className="space-y-5">
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                    {TABS.map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => go({ status: key })}
                            className={[
                                'rounded-2xl border bg-white p-4 text-left shadow-sm transition',
                                status === key ? 'border-violet-500 ring-2 ring-violet-100' : 'border-slate-200 hover:border-violet-300',
                            ].join(' ')}
                        >
                            <div className="text-sm text-slate-500">{label}</div>
                            <div className="mt-1 text-2xl font-bold text-slate-900">{Number(counts[key] ?? 0).toLocaleString('en-IN')}</div>
                        </button>
                    ))}
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                go({ search: search.trim() });
                            }}
                            className="flex flex-1 flex-wrap gap-2"
                        >
                            <input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search title, artist, label, UPC, ISRC, catalogue no."
                                className="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-violet-500 sm:w-96"
                            />
                            <button className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Search</button>
                            {filters.search && (
                                <button type="button" onClick={() => { setSearch(''); go({ search: '' }); }} className="rounded-xl px-3 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50">
                                    Clear
                                </button>
                            )}
                        </form>
                        <select value={filters.sort || 'oldest'} onChange={(e) => go({ sort: e.target.value })} className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option value="oldest">Oldest submitted first</option>
                            <option value="latest">Newest submitted first</option>
                            <option value="release_date">Release date (soonest)</option>
                            <option value="title_asc">Title A–Z</option>
                            <option value="title_desc">Title Z–A</option>
                        </select>
                        <span className="text-xs text-slate-500">{Number(releases.total ?? rows.length).toLocaleString('en-IN')} release(s)</span>
                    </div>

                    {/* phones: cards */}
                    <div className="divide-y divide-slate-100 md:hidden">
                        {rows.length === 0 && <Empty filters={filters} />}
                        {rows.map((r) => (
                            <div key={r.id} className="space-y-2 p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <Link href={`/admin/release-reviews/${r.id}`} className="min-w-0">
                                        <div className="truncate font-semibold text-slate-900">{r.title}</div>
                                        <div className="truncate text-xs text-slate-500">{r.primary_artist_name || '—'} · {r.label_name || 'No label'}</div>
                                    </Link>
                                    <Waiting release={r} status={status} />
                                </div>
                                <div className="flex flex-wrap gap-x-3 text-xs text-slate-500">
                                    <span>{r.tracks_count ?? 0} track(s)</span>
                                    <span>Release {formatDate(r.digital_release_date)}</span>
                                    <span>{dateLabel} {formatDateTime(r[dateKey] || r.updated_at)}</span>
                                </div>
                                <Actions release={r} status={status} />
                            </div>
                        ))}
                    </div>

                    {/* desktop: table */}
                    <div className="hidden overflow-x-auto md:block">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {['Release', 'Artist / Label', 'Tracks', 'Release date', dateLabel, 'UPC', ''].map((h, i) => (
                                        <th key={`${h}-${i}`} className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan="7"><Empty filters={filters} /></td>
                                    </tr>
                                )}
                                {rows.map((r) => (
                                    <tr key={r.id} className="hover:bg-slate-50">
                                        <td className="px-5 py-3">
                                            <Link href={`/admin/release-reviews/${r.id}`} className="group inline-block">
                                                <div className="font-semibold text-slate-900 group-hover:text-violet-700">{r.title}</div>
                                                <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                                    <span>{r.catalog_number || 'No catalogue number'}</span>
                                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${statusClasses[r.status] ?? 'bg-slate-100 text-slate-700'}`}>{String(r.status).replaceAll('_', ' ')}</span>
                                                </div>
                                            </Link>
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="text-sm font-medium text-slate-700">{r.primary_artist_name || '—'}</div>
                                            <div className="text-xs text-slate-500">{r.label_name || 'No label'}</div>
                                        </td>
                                        <td className="px-5 py-3 text-sm font-semibold text-slate-700">{r.tracks_count ?? 0}</td>
                                        <td className="whitespace-nowrap px-5 py-3 text-sm text-slate-600">{formatDate(r.digital_release_date)}</td>
                                        <td className="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                                            {formatDateTime(r[dateKey] || r.updated_at)}
                                            <div><Waiting release={r} status={status} /></div>
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-3 text-sm text-slate-700">{r.upc || 'Pending'}</td>
                                        <td className="px-5 py-3"><Actions release={r} status={status} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {(releases.links || []).length > 3 && (
                        <div className="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                            {releases.links.map((link, i) => (
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
            </div>
        </PanelLayout>
    );
}

function Waiting({ release, status }) {
    if (status !== 'submitted') return null;
    const d = daysSince(release.submitted_at);
    if (d === null) return null;
    const cls = d >= 7 ? 'bg-rose-50 text-rose-700' : d >= 3 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600';
    return <span className={`mt-1 inline-flex shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${cls}`}>{d === 0 ? 'today' : `waiting ${d} day${d === 1 ? '' : 's'}`}</span>;
}

function Actions({ release, status }) {
    return (
        <div className="flex flex-wrap gap-2">
            <Link href={`/admin/release-reviews/${release.id}`} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700">
                {status === 'submitted' ? 'Review' : 'Open'}
            </Link>
            {(status === 'approved' || status === 'processing') && (
                <Link href={`/admin/distribution/${release.id}`} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Delivery
                </Link>
            )}
        </div>
    );
}

function Empty({ filters }) {
    return (
        <div className="px-5 py-14 text-center text-sm text-slate-500">
            {filters.search ? 'No release matches this search.' : 'Nothing here right now.'}
        </div>
    );
}
