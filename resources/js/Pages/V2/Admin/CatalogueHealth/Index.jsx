import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Download, ExternalLink, ShieldCheck } from 'lucide-react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/* MIXXTUNE_CATALOGUE_HEALTH_V1 */

const number = (value) => new Intl.NumberFormat('en-IN').format(Number(value || 0));

const HINT = {
    no_poster: 'Fastest: Catalogue → Bulk Posters.',
    label_no_percent: 'Set the % on Accounts & Access → Labels (needs the e-mailed code when changing an existing %).',
    label_no_owner: 'Invite or link the owner on Accounts & Access → Labels.',
};

function Row({ kind, row }) {
    if (kind === 'label') {
        return (
            <>
                <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-bold text-slate-900">{row.name}</div>
                    <div className="mt-0.5 text-xs text-slate-500">
                        % {row.percent ?? '—'} · owner {row.owner_name || '—'}
                        {row.owner_email ? ` (${row.owner_email})` : ''} · {number(row.release_count)} release(s)
                    </div>
                </div>
            </>
        );
    }

    return (
        <div className="min-w-0 flex-1">
            <div className="truncate text-sm font-bold text-slate-900">
                {kind === 'track' ? row.track_title : row.title}
            </div>
            <div className="mt-0.5 text-xs text-slate-500">
                {kind === 'track'
                    ? `Release #${row.id} ${row.title} · UPC ${row.upc || '—'} · ${row.status}`
                    : `#${row.id} · ${row.artist || '—'} · ${row.label_name || 'no label'} · UPC ${row.upc || '—'} · ${row.status}`}
            </div>
        </div>
    );
}

export default function Index({ checks = [], list = null, totals = {} }) {
    const groups = checks.reduce((acc, c) => {
        (acc[c.group] = acc[c.group] || []).push(c);
        return acc;
    }, {});

    const rows = list?.rows;

    return (
        <PanelLayout role="super_admin" title="Missing Details" subtitle="What is still missing in the catalogue and on labels">
            <Head title="Missing Details" />

            <div className="space-y-6">
                <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="flex items-center gap-2">
                        <ShieldCheck className="h-5 w-5 text-violet-600" />
                        <span className="text-xs font-black uppercase tracking-widest text-violet-600">Super Admin · read only</span>
                    </div>
                    <p className="mt-3 text-sm text-slate-600">
                        {number(totals.releases)} releases (drafts not counted) and {number(totals.labels)} labels checked.
                        Press a box to see the list. Nothing is changed on this page.
                    </p>

                    {Object.entries(groups).map(([group, items]) => (
                        <div key={group} className="mt-5">
                            <div className="text-xs font-black uppercase tracking-widest text-slate-400">{group}</div>
                            <div className="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                {items.map((c) => {
                                    const active = list?.check === c.key;
                                    return (
                                        <Link
                                            key={c.key}
                                            href={`/super-admin/catalogue-health?check=${c.key}`}
                                            preserveScroll
                                            className={`rounded-2xl border p-3 transition sm:p-4 ${
                                                active
                                                    ? 'border-violet-500 bg-violet-50'
                                                    : 'border-slate-100 bg-slate-50 hover:border-slate-300'
                                            }`}
                                        >
                                            <div className="text-xs font-semibold text-slate-500">{c.label}</div>
                                            <div className={`mt-1 flex items-center gap-1 text-lg font-black sm:text-2xl ${c.count ? 'text-slate-900' : 'text-emerald-600'}`}>
                                                {c.count ? number(c.count) : <><CheckCircle2 className="h-5 w-5" /> 0</>}
                                            </div>
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </section>

                {list && (
                    <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-base font-black text-slate-900">{list.label}</h2>
                                <div className="text-xs text-slate-500">
                                    {number(rows.total)} found{rows.total ? ` · showing ${rows.from}–${rows.to}` : ''}
                                </div>
                                {HINT[list.check] && <div className="mt-1 text-xs text-violet-700">{HINT[list.check]}</div>}
                            </div>
                            {rows.total > 0 && (
                                <a
                                    href={`/super-admin/catalogue-health/export?check=${list.check}`}
                                    className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
                                >
                                    <Download className="h-4 w-4" />
                                    Download all (CSV)
                                </a>
                            )}
                        </div>

                        {rows.total === 0 ? (
                            <div className="mt-4 flex items-center gap-2 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">
                                <CheckCircle2 className="h-4 w-4" /> Nothing missing here.
                            </div>
                        ) : (
                            <ul className="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-100">
                                {rows.data.map((row) => (
                                    <li key={`${row.track_id || ''}-${row.id}`} className="flex items-center gap-3 p-3">
                                        <Row kind={list.kind} row={row} />
                                        <a
                                            href={row.edit_url}
                                            className="inline-flex h-9 shrink-0 items-center gap-1 rounded-lg bg-amber-50 px-3 text-xs font-bold text-amber-700 hover:bg-amber-100"
                                        >
                                            {list.kind === 'label' ? 'Open' : 'Edit'}
                                            <ExternalLink className="h-3.5 w-3.5" />
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {rows.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between text-sm">
                                {rows.prev_page_url ? (
                                    <Link href={rows.prev_page_url} preserveScroll className="font-bold text-violet-700">
                                        ← Previous
                                    </Link>
                                ) : <span />}
                                <span className="text-xs text-slate-500">
                                    Page {rows.current_page} of {rows.last_page}
                                </span>
                                {rows.next_page_url ? (
                                    <Link href={rows.next_page_url} preserveScroll className="font-bold text-violet-700">
                                        Next →
                                    </Link>
                                ) : <span />}
                            </div>
                        )}
                    </section>
                )}
            </div>
        </PanelLayout>
    );
}
