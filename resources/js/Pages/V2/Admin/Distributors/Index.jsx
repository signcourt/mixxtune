import { Head, Link, router, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/*
 * W221 - Super Admin: Distributors.
 * A distributor = a master label account that runs its own branded panel with sub-labels below it.
 * Invite the person as a Label under Users first (mail, access, %), then make him a distributor here.
 * W228: "Manage" opens the controls of one distributor - hold the whole tree, limits, Manager, agreement, figures.
 */
const inr = (v) => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const field = 'mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900';

function Manage({ d, managerOptions, errors }) {
    const c = d.control || {};
    const [maxSubs, setMaxSubs] = useState(c.max_sub_labels ?? '');
    const [maxRel, setMaxRel] = useState(c.max_monthly_releases ?? '');
    const [notes, setNotes] = useState(c.notes ?? '');
    const [reason, setReason] = useState('');
    const [manager, setManager] = useState('');
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState('');
    const post = (key, url, data, extra = {}) => {
        if (busy) return;
        setBusy(key);
        router.post(url, data, { preserveScroll: true, preserveState: true, onFinish: () => setBusy(''), ...extra });
    };
    const base = `/super-admin/distributors/${d.id}`;
    const f = d.figures;
    const box = 'rounded-xl border border-slate-200 bg-white p-4';

    return (
        <div className="grid gap-4 xl:grid-cols-2">
            <div className={box}>
                <div className="text-sm font-semibold text-slate-900">Figures of the whole tree</div>
                {f ? (
                    <div className="mt-3 grid grid-cols-3 gap-3 text-sm">
                        <div><div className="text-xs uppercase text-slate-500">Collected</div><div className="font-bold text-slate-900">{inr(f.collected)}</div></div>
                        <div><div className="text-xs uppercase text-slate-500">Paid to distributor</div><div className="font-bold text-slate-900">{inr(f.earning)}</div></div>
                        <div><div className="text-xs uppercase text-slate-500">Mixx Tune keeps</div><div className="font-bold text-emerald-700">{inr(f.margin)}</div></div>
                    </div>
                ) : <p className="mt-2 text-sm text-slate-500">No figures yet.</p>}
                <p className="mt-3 text-xs text-slate-500">From published reports, the distributor's own label and all his sub-labels together. {d.sub_labels} sub-label{d.sub_labels === 1 ? '' : 's'}, {d.month_releases} release{d.month_releases === 1 ? '' : 's'} sent this month.</p>
            </div>

            <div className={box}>
                <div className="text-sm font-semibold text-slate-900">Limits</div>
                <div className="mt-2 grid gap-3 md:grid-cols-2">
                    <label className="text-xs font-semibold text-slate-700">Sub-labels allowed (empty = no limit)
                        <input type="number" min="0" value={maxSubs} onChange={(e) => setMaxSubs(e.target.value)} className={field} />
                        {errors.max_sub_labels && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.max_sub_labels}</span>}
                    </label>
                    <label className="text-xs font-semibold text-slate-700">Releases a month, whole tree (empty = no limit)
                        <input type="number" min="0" value={maxRel} onChange={(e) => setMaxRel(e.target.value)} className={field} />
                        {errors.max_monthly_releases && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.max_monthly_releases}</span>}
                    </label>
                    <label className="text-xs font-semibold text-slate-700 md:col-span-2">Note for Super Admins (not shown to the distributor)
                        <input value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={1000} className={field} />
                    </label>
                </div>
                <div className="mt-3 flex justify-end">
                    <button type="button" disabled={!!busy} onClick={() => post('limits', `${base}/limits`, { max_sub_labels: maxSubs === '' ? null : maxSubs, max_monthly_releases: maxRel === '' ? null : maxRel, notes })} className="rounded-lg bg-violet-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50">{busy === 'limits' ? 'Saving…' : 'Save limits'}</button>
                </div>
            </div>

            <div className={box}>
                <div className="text-sm font-semibold text-slate-900">Manager</div>
                <p className="mt-1 text-xs text-slate-500">Now: {d.managers.length ? d.managers.join(', ') : 'not assigned'}. The Manager chosen here is put over the distributor and every sub-label.</p>
                <div className="mt-2 flex gap-2">
                    <select value={manager} onChange={(e) => setManager(e.target.value)} className={field + ' mt-0'}>
                        <option value="">{managerOptions.length ? 'Choose a Manager…' : 'No Manager account'}</option>
                        {managerOptions.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                    </select>
                    <button type="button" disabled={!manager || !!busy} onClick={() => post('manager', `${base}/manager`, { manager_id: manager }, { onSuccess: () => setManager('') })} className="shrink-0 rounded-lg bg-violet-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50">{busy === 'manager' ? 'Saving…' : 'Put over tree'}</button>
                </div>
                {errors.manager_id && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.manager_id}</span>}
            </div>

            <div className={box}>
                <div className="text-sm font-semibold text-slate-900">Agreement (PDF)</div>
                <p className="mt-1 text-xs text-slate-500">{c.agreement_name ? <>Current: <a href={`${base}/agreement`} className="font-semibold text-violet-700 hover:underline">{c.agreement_name}</a> ({String(c.agreement_at || '').slice(0, 10)})</> : 'No agreement uploaded.'} Only Super Admins can open it.</p>
                <div className="mt-2 flex items-center gap-2">
                    <input type="file" accept="application/pdf,.pdf" onChange={(e) => setFile(e.target.files?.[0] || null)} className="block w-full text-xs text-slate-600" />
                    <button type="button" disabled={!file || !!busy} onClick={() => post('file', `${base}/agreement`, { file }, { forceFormData: true, onSuccess: () => setFile(null) })} className="shrink-0 rounded-lg bg-violet-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50">{busy === 'file' ? 'Uploading…' : 'Upload'}</button>
                </div>
                {errors.file && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.file}</span>}
            </div>

            <div className={`${box} xl:col-span-2 ${c.tree_hold ? 'border-rose-300 bg-rose-50' : ''}`}>
                <div className="text-sm font-semibold text-slate-900">Hold the whole tree</div>
                {c.tree_hold ? (
                    <>
                        <p className="mt-1 text-sm text-rose-700"><b>On hold</b> since {String(c.hold_at || '').slice(0, 16).replace('T', ' ')}: {c.hold_reason}. The distributor, his sub-labels and their staff cannot log in.</p>
                        <div className="mt-3 flex justify-end">
                            <button type="button" disabled={!!busy} onClick={() => post('hold', `${base}/hold`, { hold: false })} className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50">{busy === 'hold' ? 'Working…' : 'Lift the hold'}</button>
                        </div>
                    </>
                ) : (
                    <>
                        <p className="mt-1 text-xs text-slate-500">The distributor, all his sub-labels and their staff are signed out and cannot log in until you lift it. Releases, reports and money stay exactly as they are. A code is e-mailed to you.</p>
                        <div className="mt-2 flex gap-2">
                            <input value={reason} onChange={(e) => setReason(e.target.value)} maxLength={300} placeholder="Reason (kept in the audit log)" className={field + ' mt-0'} />
                            <button type="button" disabled={reason.trim().length < 3 || !!busy} onClick={() => post('hold', `${base}/hold`, { hold: true, reason })} className="shrink-0 rounded-lg bg-rose-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50">{busy === 'hold' ? 'Working…' : 'Put on hold'}</button>
                        </div>
                        {errors.reason && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.reason}</span>}
                    </>
                )}
            </div>
        </div>
    );
}
const slugify = (v) => String(v || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);

export default function Index({ role = 'super_admin', distributors = [], candidates = [], managerOptions = [] }) {
    const { errors = {} } = usePage().props;
    const [labelId, setLabelId] = useState('');
    const [slug, setSlug] = useState('');
    const [brand, setBrand] = useState('');
    const [busy, setBusy] = useState(false);
    const [open, setOpen] = useState(null); /* W228: distributor whose controls are open */

    const pick = (id) => {
        setLabelId(id);
        const c = candidates.find((x) => String(x.id) === String(id));
        if (c) {
            setSlug(slugify(c.name));
            setBrand(c.name);
        }
    };

    const make = () => {
        if (!labelId || !slug || busy) return;
        setBusy(true);
        router.post('/super-admin/distributors', { label_id: labelId, slug, brand_name: brand }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => { if (!Object.keys(page?.props?.errors || {}).length) { setLabelId(''); setSlug(''); setBrand(''); } },
        });
    };

    const setStatus = (d, status) => {
        router.post(`/super-admin/distributors/${d.id}`, { status }, { preserveScroll: true, preserveState: true });
    };

    return (
        <PanelLayout role={role} title="Distributors" subtitle="Partners who run their own branded panel with sub-labels below them">
            <Head title="Distributors" />

            <div className="space-y-5">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">How it works</h2>
                    <ol className="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-600">
                        <li>Invite the person as a <b>Label</b> under <Link href="/admin/users" className="font-semibold text-violet-700 hover:underline">Accounts → Users</Link> (mail ID, access and his % are set there, exactly like any label).</li>
                        <li>Choose his master label below and press <b>Make distributor</b> (a code is e-mailed to you).</li>
                        <li>He gets <b>My Brand</b> in his menu: his own panel name and logo. Everybody in his tree sees that brand instead of Mixx Tune.</li>
                        <li>Press <b>Manage</b> on his row to put a Manager over his tree, set limits, upload the agreement, see his figures or hold the whole tree.</li>
                    </ol>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">Make a distributor</h2>
                    <div className="mt-3 grid gap-3 lg:grid-cols-4">
                        <label className="text-sm font-semibold text-slate-700 lg:col-span-2">
                            Master label (with a label login)
                            <select value={labelId} onChange={(e) => pick(e.target.value)} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900">
                                <option value="">{candidates.length ? 'Choose a label…' : 'No label with a login is free'}</option>
                                {candidates.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name} — {c.owner} ({c.email})</option>
                                ))}
                            </select>
                            {errors.label_id && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.label_id}</span>}
                        </label>
                        <label className="text-sm font-semibold text-slate-700">
                            Panel name
                            <input value={brand} onChange={(e) => setBrand(e.target.value)} maxLength={120} placeholder="e.g. ABC Music" className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900" />
                            {errors.brand_name && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.brand_name}</span>}
                        </label>
                        <label className="text-sm font-semibold text-slate-700">
                            Short name (for his URL later)
                            <input value={slug} onChange={(e) => setSlug(slugify(e.target.value))} maxLength={40} placeholder="abc-music" className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm text-slate-900" />
                            {errors.slug && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.slug}</span>}
                        </label>
                    </div>
                    <div className="mt-4 flex items-center justify-between gap-3">
                        <p className="text-xs text-slate-500">Nothing in his label, releases, wallet or % changes. Only the brand of his panel does.</p>
                        <button type="button" disabled={!labelId || slug.length < 3 || busy} onClick={make} className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50">
                            {busy ? 'Working…' : 'Make distributor'}
                        </button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 p-5">
                        <h2 className="text-lg font-semibold text-slate-900">Distributors <span className="ml-1 text-sm font-normal text-slate-500">{distributors.length}</span></h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {['Distributor', 'Owner login', '%', 'Sub-labels', 'Manager', 'Status', 'Action'].map((h) => (
                                        <th key={h} className="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {distributors.length === 0 && (
                                    <tr><td colSpan="7" className="px-5 py-14 text-center text-sm text-slate-500">No distributor yet.</td></tr>
                                )}
                                {distributors.map((d) => (
                                    <Fragment key={d.id}>
                                    <tr>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                {d.logo_url
                                                    ? <img src={d.logo_url} alt="" className="h-10 w-10 rounded-lg border border-slate-200 bg-white object-contain" />
                                                    : <span className="grid h-10 w-10 place-items-center rounded-lg bg-violet-100 text-sm font-bold text-violet-700">{String(d.brand_name || d.label_name || '?').slice(0, 1).toUpperCase()}</span>}
                                                <div>
                                                    <div className="font-semibold text-slate-900">{d.brand_name || d.label_name}</div>
                                                    <div className="text-xs text-slate-500">Label: {d.label_name} · <span className="font-mono">{d.slug}</span></div>
                                                    {d.url && <div className="text-xs">{d.url_ready ? <a href={d.url} target="_blank" rel="noreferrer" className="font-mono text-violet-700 hover:underline">{d.url.replace('https://', '')}</a> : <span className="font-mono text-slate-400">{d.url.replace('https://', '')} · address is being set up</span>}</div>}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600">
                                            <div>{d.owner_name}</div>
                                            <div className="text-xs text-slate-500">{d.owner_email}</div>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600">{d.percent !== null && d.percent !== undefined && d.percent !== '' ? `${Number(d.percent)}%` : '—'}</td>
                                        <td className="px-5 py-4 text-sm text-slate-600">{d.sub_labels}</td>
                                        <td className="px-5 py-4 text-sm text-slate-600">{d.managers.length ? d.managers.join(', ') : <span className="text-slate-400">not assigned</span>}</td>
                                        <td className="px-5 py-4">
                                            <span className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${d.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>{d.status === 'active' ? 'brand on' : 'brand off'}</span>
                                            {d.control?.tree_hold && <span className="ml-2 rounded-full bg-rose-600 px-3 py-1 text-xs font-bold text-white">ON HOLD</span>}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                            {d.control !== null && <button type="button" onClick={() => setOpen(open === d.id ? null : d.id)} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-violet-700">{open === d.id ? 'Close' : 'Manage'}</button>}
                                            {d.status === 'active'
                                                ? <button type="button" onClick={() => setStatus(d, 'suspended')} className="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Switch brand off</button>
                                                : <button type="button" onClick={() => setStatus(d, 'active')} className="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Switch brand on</button>}
                                            </div>
                                        </td>
                                    </tr>
                                    {open === d.id && (
                                        <tr><td colSpan="7" className="bg-slate-50 px-5 py-5"><Manage d={d} managerOptions={managerOptions} errors={errors} /></td></tr>
                                    )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
