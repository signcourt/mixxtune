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

/* W118 MIXXTUNE_REVIEW_LIST_DETAILS: poster and the main release details right in the list */
const artworkUrl = (path) => {
    if (!path) return null;
    const s = String(path);
    if (s.startsWith('http://') || s.startsWith('https://') || s.startsWith('/')) return s;
    return `/storage/${s}`;
};

const TYPE_NAMES = { single: 'Single', ep: 'EP', album: 'Album' };
const ROLE_NAMES = { super_admin: 'Super Admin', admin: 'Manager', label: 'Label', artist: 'Artist' };

const featuring = (r) => {
    const list = Array.isArray(r.featuring_artists) ? r.featuring_artists.map((a) => (typeof a === 'string' ? a : a?.name)).filter(Boolean) : [];
    if (list.length) return list.join(', ');
    return r.featuring_artist_name || '';
};

const territoryText = (r) => {
    if (r.worldwide || !Array.isArray(r.territories) || r.territories.length === 0) return 'Worldwide';
    return `${r.territories.length} countr${r.territories.length === 1 ? 'y' : 'ies'}`;
};

function Poster({ release, size = 'h-20 w-20' }) {
    const [broken, setBroken] = useState(false);
    const src = artworkUrl(release.artwork_url || release.artwork_path);
    if (!src || broken) {
        return (
            <div className={`${size} grid shrink-0 place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-[10px] font-semibold text-slate-400`}>
                No poster
            </div>
        );
    }

    return (
        <a href={src} target="_blank" rel="noreferrer" title="Open the poster" className="shrink-0">
            <img src={src} alt={`Poster of ${release.title}`} loading="lazy" onError={() => setBroken(true)} className={`${size} rounded-xl border border-slate-200 object-cover shadow-sm`} />
        </a>
    );
}

function DetailChips({ release }) {
    const items = [
        TYPE_NAMES[release.release_type] || release.release_type,
        [release.primary_genre, release.sub_genre].filter(Boolean).join(' / '),
        release.language,
        territoryText(release),
    ].filter(Boolean);

    return (
        <div className="mt-1 flex flex-wrap gap-1">
            {items.map((x, i) => (
                <span key={`${x}-${i}`} className="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600">{x}</span>
            ))}
        </div>
    );
}

const daysSince = (value) => {
    if (!value) return null;
    const t = new Date(value).getTime();
    if (Number.isNaN(t)) return null;
    return Math.max(0, Math.floor((Date.now() - t) / 86400000));
};

function PosterRequestsPanel({ requests = [] }) {
    /* W195e: list + View modal (full details, big posters) with Approve / Reject (reason). */
    const [open, setOpen] = useState(null);

    if (!requests || requests.length === 0) return null;

    return (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
            <div className="mb-3 text-sm font-bold uppercase tracking-wide text-amber-700">
                Poster change requests ({requests.length})
            </div>
            <div className="space-y-3">
                {requests.map((r) => (
                    <div key={r.id} className="flex flex-col gap-3 rounded-xl border border-amber-200 bg-white p-3 sm:flex-row sm:items-center">
                        <div className="flex items-center gap-3">
                            <figure className="text-center">
                                <div className="mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-400">Current</div>
                                {r.current_path ? (
                                    <img src={`/storage/${r.current_path}`} alt="Current" className="h-20 w-20 rounded-lg border border-slate-200 object-cover" />
                                ) : (
                                    <div className="flex h-20 w-20 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-[9px] font-semibold uppercase text-slate-400">None</div>
                                )}
                            </figure>
                            <div className="text-xl text-slate-300">→</div>
                            <figure className="text-center">
                                <button type="button" onClick={() => setOpen(r)} title="View details" className="block">
                                    <div className="mb-1 text-[10px] font-bold uppercase tracking-wide text-violet-500">Proposed</div>
                                    <img src={`/storage/${r.proposed_path}`} alt="Proposed" className="h-20 w-20 cursor-zoom-in rounded-lg border-2 border-violet-300 object-cover transition hover:ring-2 hover:ring-violet-300" />
                                </button>
                            </figure>
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-sm font-bold text-slate-900">{r.release_title || `Release #${r.release_id}`}</div>
                            <div className="text-xs text-slate-500">Requested by {r.requested_by || 'a user'}</div>
                            {r.created_at && <div className="text-[11px] text-slate-400">{r.created_at}</div>}
                        </div>
                        <div className="flex items-center gap-2">
                            <button type="button" onClick={() => setOpen(r)} className="inline-flex items-center rounded-lg border border-violet-300 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100">
                                View
                            </button>
                        </div>
                    </div>
                ))}
            </div>

            {open && (
                <PosterRequestModal request={open} onClose={() => setOpen(null)} />
            )}
        </div>
    );
}

function PosterRequestModal({ request, onClose }) {
    /* W195f: big poster LEFT (Current/Proposed toggle), details RIGHT, actions bottom. */
    const r = request;
    const [side, setSide] = useState('proposed'); // proposed | current
    const [mode, setMode] = useState('view');      // view | reject
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);

    const bigSrc = side === 'current'
        ? (r.current_path ? `/storage/${r.current_path}` : null)
        : `/storage/${r.proposed_path}`;

    const approve = () => {
        if (busy) return;
        setBusy(true);
        router.post(`/admin/poster-requests/${r.id}/approve`, {}, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); onClose(); },
        });
    };

    const reject = () => {
        if (busy || reason.trim() === '') return;
        setBusy(true);
        router.post(`/admin/poster-requests/${r.id}/reject`, { reason: reason.trim() }, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); onClose(); },
        });
    };

    /* W195g: ready-made reasons; clicking one FILLS the textarea, owner still presses Confirm. */
    const QUICK_REASONS = [
        'Poster par logo/watermark hai - hata kar clean poster bhejo.',
        'Poster par extra naam/text hai - clean poster bhejo.',
        'Poster ki details release metadata se match nahi karti.',
        'Poster low quality ya galat size ka hai - 3000x3000 square bhejo.',
    ];

    const Row = ({ label, value }) => (
        <div className="flex justify-between gap-4 border-b border-slate-100 py-1.5 text-sm">
            <span className="font-semibold text-slate-500">{label}</span>
            <span className="min-w-0 break-words text-right text-slate-900">{value || '-'}</span>
        </div>
    );

    const Thumb = ({ k, src, label, color }) => (
        <button
            type="button"
            onClick={() => setSide(k)}
            className={`rounded-lg border-2 p-0.5 transition ${side === k ? color : 'border-transparent opacity-70 hover:opacity-100'}`}
            title={label}
        >
            {src ? (
                <img src={src} alt={label} className="h-14 w-14 rounded-md object-cover" />
            ) : (
                <div className="flex h-14 w-14 items-center justify-center rounded-md border border-dashed border-slate-300 bg-slate-50 text-[9px] font-semibold uppercase text-slate-400">None</div>
            )}
            <div className="mt-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">{label}</div>
        </button>
    );

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" onClick={onClose}>
            <div className="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between gap-4 border-b border-slate-100 p-4 sm:p-5">
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wide text-violet-600">{r.release_type || 'Release'}</div>
                        <h3 className="text-xl font-bold text-slate-950">{r.release_title || `Release #${r.release_id}`}</h3>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">✕</button>
                </div>

                <div className="flex flex-1 flex-col gap-5 overflow-auto p-4 sm:p-5 lg:flex-row">
                    {/* LEFT: big poster + thumbnails */}
                    <div className="lg:w-1/2">
                        <a href={bigSrc || undefined} target="_blank" rel="noreferrer" title="Open full size" className="block">
                            {bigSrc ? (
                                <img src={bigSrc} alt="Poster" className="w-full rounded-2xl border border-slate-200 object-contain" />
                            ) : (
                                <div className="flex aspect-square w-full items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-sm font-semibold uppercase text-slate-400">No current poster</div>
                            )}
                        </a>
                        <div className="mt-3 flex items-start gap-3">
                            <Thumb k="current" src={r.current_path ? `/storage/${r.current_path}` : null} label="Current" color="border-slate-400" />
                            <Thumb k="proposed" src={`/storage/${r.proposed_path}`} label="Proposed" color="border-violet-400" />
                        </div>
                    </div>

                    {/* RIGHT: details */}
                    <div className="lg:w-1/2">
                        <div className="rounded-xl border border-slate-200 p-3">
                            <Row label="Singer" value={r.featuring ? `${r.singer || '-'}, ${r.featuring}` : r.singer} />
                            <Row label="Composer" value={r.composer} />
                            <Row label="Writer" value={r.writer} />
                            <Row label="Music director" value={r.music_director} />
                            <Row label="Artist" value={r.artist} />
                            <Row label="Label" value={r.label} />
                            <Row label="UPC" value={r.upc} />
                            <Row label="Language" value={r.language} />
                            <Row label="Genre" value={r.genre} />
                            <Row label="Requested by" value={r.requested_by} />
                            <Row label="Requested at" value={r.created_at} />
                        </div>
                    </div>
                </div>

                {/* BOTTOM: actions */}
                <div className="border-t border-slate-100 p-4 sm:p-5">
                    {mode === 'reject' ? (
                        <div className="space-y-3">
                            <div>
                                <div className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Quick reasons (tap to fill)</div>
                                <div className="flex flex-wrap gap-2">
                                    {QUICK_REASONS.map((q) => (
                                        <button
                                            key={q}
                                            type="button"
                                            onClick={() => setReason(q)}
                                            disabled={busy}
                                            title={q}
                                            className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition disabled:opacity-50 ${reason === q ? 'border-red-400 bg-red-100 text-red-800' : 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100'}`}
                                        >
                                            {q.split(' - ')[0]}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <label className="block text-sm font-semibold text-slate-700">Reason for rejection (required)</label>
                            <textarea
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                rows={3}
                                placeholder="Tell the owner why this poster was not approved..."
                                className="w-full rounded-xl border border-slate-300 p-3 text-sm"
                            />
                            <div className="flex items-center justify-end gap-2">
                                <button type="button" onClick={() => setMode('view')} className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</button>
                                <button type="button" onClick={reject} disabled={busy || reason.trim() === ''} className="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">
                                    {busy ? '...' : 'Confirm reject'}
                                </button>
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-center justify-end gap-2">
                            <button type="button" onClick={() => setMode('reject')} disabled={busy} className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">Reject</button>
                            <button type="button" onClick={approve} disabled={busy} className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
                                {busy ? '...' : 'Approve'}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Index({ role = 'admin', releases = {}, counts = {}, filters = {}, posterRequests = [] }) {
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
                {/* W195d: pending poster change requests (label/artist) */}
                {role === 'super_admin' && <PosterRequestsPanel requests={posterRequests} />}{/* W215: only the Super Admin decides (W211) */}
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
                                <div className="flex items-start gap-3">
                                    <Poster release={r} size="h-24 w-24" />
                                    <Link href={`/admin/release-reviews/${r.id}`} className="min-w-0 flex-1">
                                        <div className="font-semibold text-slate-900">{r.title}{r.version ? <span className="font-normal text-slate-500"> ({r.version})</span> : null}</div>
                                        <div className="text-xs text-slate-600">{r.primary_artist_name || '—'}{featuring(r) ? ` feat. ${featuring(r)}` : ''}</div>
                                        <div className="text-xs text-slate-500">{r.label_name || 'No label'}</div>
                                        <DetailChips release={r} />
                                    </Link>
                                    <Waiting release={r} status={status} />
                                </div>
                                <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                                    <span>{r.catalog_number || 'No catalogue no.'}</span>
                                    <span>{r.tracks_count ?? 0} track(s)</span>
                                    <span>Release {formatDate(r.digital_release_date)}</span>
                                    <span>UPC {r.upc || 'pending'}</span>
                                    <span>{dateLabel} {formatDateTime(r[dateKey] || r.updated_at)}</span>
                                    {r.sent_by_name && <span>by {r.sent_by_name} ({ROLE_NAMES[r.sent_by_role] || r.sent_by_role})</span>}
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
                                    {['Release', 'Artist / Label', 'Tracks', 'Release date', dateLabel, 'UPC / Codes', ''].map((h, i) => (
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
                                            <div className="flex items-start gap-3">
                                                <Poster release={r} />
                                                <Link href={`/admin/release-reviews/${r.id}`} className="group min-w-0">
                                                    <div className="font-semibold text-slate-900 group-hover:text-violet-700">{r.title}{r.version ? <span className="font-normal text-slate-500"> ({r.version})</span> : null}</div>
                                                    <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                                        <span>{r.catalog_number || 'No catalogue number'}</span>
                                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${statusClasses[r.status] ?? 'bg-slate-100 text-slate-700'}`}>{String(r.status).replaceAll('_', ' ')}</span>
                                                    </div>
                                                    <DetailChips release={r} />
                                                </Link>
                                            </div>
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="text-sm font-medium text-slate-700">{r.primary_artist_name || '—'}</div>
                                            {featuring(r) && <div className="text-xs text-slate-500">feat. {featuring(r)}</div>}
                                            <div className="text-xs text-slate-500">{r.label_name || 'No label'}</div>
                                            {r.sent_by_name && <div className="mt-1 text-[11px] text-slate-400">Sent by {r.sent_by_name} ({ROLE_NAMES[r.sent_by_role] || r.sent_by_role})</div>}
                                        </td>
                                        <td className="px-5 py-3 text-sm font-semibold text-slate-700">{r.tracks_count ?? 0}</td>
                                        <td className="whitespace-nowrap px-5 py-3 text-sm text-slate-600">{formatDate(r.digital_release_date)}</td>
                                        <td className="whitespace-nowrap px-5 py-3 text-sm text-slate-600">
                                            {formatDateTime(r[dateKey] || r.updated_at)}
                                            <div><Waiting release={r} status={status} /></div>
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-3 text-sm text-slate-700">
                                            <div>{r.upc || 'UPC pending'}</div>
                                            {(r.tracks_count ?? 0) > 0 && (
                                                <div className={`text-xs ${Number(r.tracks_without_isrc ?? 0) > 0 ? 'text-amber-700' : 'text-emerald-700'}`}>
                                                    {Number(r.tracks_without_isrc ?? 0) > 0 ? `ISRC missing on ${r.tracks_without_isrc}` : 'ISRC on all tracks'}
                                                </div>
                                            )}
                                        </td>
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
