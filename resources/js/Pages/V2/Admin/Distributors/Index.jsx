import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/*
 * W221 - Super Admin: Distributors.
 * A distributor = a master label account that runs its own branded panel with sub-labels below it.
 * Invite the person as a Label under Users first (mail, access, %), then make him a distributor here.
 */
const slugify = (v) => String(v || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);

export default function Index({ role = 'super_admin', distributors = [], candidates = [] }) {
    const { errors = {} } = usePage().props;
    const [labelId, setLabelId] = useState('');
    const [slug, setSlug] = useState('');
    const [brand, setBrand] = useState('');
    const [busy, setBusy] = useState(false);

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
                        <li>A Manager is put over him the usual way (Accounts → Managers → assign the label).</li>
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
                                    <tr key={d.id}>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                {d.logo_url
                                                    ? <img src={d.logo_url} alt="" className="h-10 w-10 rounded-lg border border-slate-200 bg-white object-contain" />
                                                    : <span className="grid h-10 w-10 place-items-center rounded-lg bg-violet-100 text-sm font-bold text-violet-700">{String(d.brand_name || d.label_name || '?').slice(0, 1).toUpperCase()}</span>}
                                                <div>
                                                    <div className="font-semibold text-slate-900">{d.brand_name || d.label_name}</div>
                                                    <div className="text-xs text-slate-500">Label: {d.label_name} · <span className="font-mono">{d.slug}</span></div>
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
                                            <span className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${d.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>{d.status}</span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {d.status === 'active'
                                                ? <button type="button" onClick={() => setStatus(d, 'suspended')} className="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Switch brand off</button>
                                                : <button type="button" onClick={() => setStatus(d, 'active')} className="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Switch brand on</button>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
