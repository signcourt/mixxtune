import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/*
 * W223 - the distributor's Sub-labels: create a sub-label with its own login, set its share, re-send the invitation.
 * Money: Mixx Tune pays the distributor for the whole tree; the share here is what the distributor passes on.
 */
const STATES = [['AN', 'Andaman & Nicobar'], ['AP', 'Andhra Pradesh'], ['AR', 'Arunachal Pradesh'], ['AS', 'Assam'], ['BR', 'Bihar'], ['CH', 'Chandigarh'], ['CG', 'Chhattisgarh'], ['DN', 'Dadra, Nagar Haveli, Daman & Diu'], ['DL', 'Delhi'], ['GA', 'Goa'], ['GJ', 'Gujarat'], ['HR', 'Haryana'], ['HP', 'Himachal Pradesh'], ['JK', 'Jammu & Kashmir'], ['JH', 'Jharkhand'], ['KA', 'Karnataka'], ['KL', 'Kerala'], ['LA', 'Ladakh'], ['LD', 'Lakshadweep'], ['MP', 'Madhya Pradesh'], ['MH', 'Maharashtra'], ['MN', 'Manipur'], ['ML', 'Meghalaya'], ['MZ', 'Mizoram'], ['NL', 'Nagaland'], ['OD', 'Odisha'], ['PY', 'Puducherry'], ['PB', 'Punjab'], ['RJ', 'Rajasthan'], ['SK', 'Sikkim'], ['TN', 'Tamil Nadu'], ['TS', 'Telangana'], ['TR', 'Tripura'], ['UP', 'Uttar Pradesh'], ['UK', 'Uttarakhand'], ['WB', 'West Bengal']];

const EMPTY = { label_name: '', full_name: '', email: '', phone: '', state_code: '', state: '', city: '', postal_code: '', address_line_1: '', percent: '' };

const fmtWhen = (v) => {
    if (!v) return 'Never';
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
};

export default function SubLabels({ role = 'label', master = {}, subLabels = [] }) {
    const { errors = {} } = usePage().props;
    const [form, setForm] = useState(EMPTY);
    const [busy, setBusy] = useState(false);
    const [editing, setEditing] = useState(null);
    const [newPercent, setNewPercent] = useState('');
    const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

    const known = Object.keys(EMPTY);
    const otherErrors = Object.entries(errors).filter(([k]) => !known.includes(k) && k !== 'action_otp').map(([, v]) => (Array.isArray(v) ? v.join(' ') : String(v)));

    const pct = Number(form.percent);
    const ready = form.label_name.trim().length >= 2 && form.full_name.trim().length >= 2 && /.+@.+\..+/.test(form.email) && form.percent !== '' && pct >= 0 && pct <= 100 && !busy;

    const create = () => {
        if (!ready) return;
        setBusy(true);
        router.post('/label/distributor/sub-labels', form, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => { if (!Object.keys(page?.props?.errors || {}).length && page?.props?.flash?.success) setForm(EMPTY); },
        });
    };

    const saveShare = (id) => {
        if (newPercent === '' || Number(newPercent) < 0 || Number(newPercent) > 100) return;
        router.post(`/label/distributor/sub-labels/${id}/share`, { percent: newPercent }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => { if (!Object.keys(page?.props?.errors || {}).length) { setEditing(null); setNewPercent(''); } },
        });
    };

    const input = 'mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900';
    const errLine = (k) => (errors[k] ? <span className="mt-1 block text-xs font-semibold text-rose-600">{Array.isArray(errors[k]) ? errors[k].join(' ') : errors[k]}</span> : null);

    return (
        <PanelLayout role={role} title="Sub-labels" subtitle={`Labels that work under ${master.brand || master.name || 'you'}`}>
            <Head title="Sub-labels" />

            <div className="space-y-5">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">Add a sub-label</h2>
                    <p className="mt-1 text-sm text-slate-500">The sub-label gets its own login by e-mail and sees your name and logo. You are paid for the whole tree; the share below is the part of your earning you pass on to this sub-label.</p>

                    <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label className="text-sm font-semibold text-slate-700 xl:col-span-2">Sub-label name <span className="text-rose-600">*</span>
                            <input value={form.label_name} onChange={(e) => set('label_name', e.target.value)} maxLength={150} className={input} />{errLine('label_name')}{errLine('name')}{errLine('new_labels')}
                        </label>
                        <label className="text-sm font-semibold text-slate-700">Owner's full name <span className="text-rose-600">*</span>
                            <input value={form.full_name} onChange={(e) => set('full_name', e.target.value)} maxLength={150} className={input} />{errLine('full_name')}
                        </label>
                        <label className="text-sm font-semibold text-slate-700">Login e-mail <span className="text-rose-600">*</span>
                            <input type="email" value={form.email} onChange={(e) => set('email', e.target.value)} maxLength={190} className={input} />{errLine('email')}
                        </label>
                        <label className="text-sm font-semibold text-slate-700">Phone
                            <input value={form.phone} onChange={(e) => set('phone', e.target.value)} maxLength={30} className={input} />{errLine('phone')}
                        </label>
                        <label className="text-sm font-semibold text-slate-700">State
                            <select value={form.state_code} onChange={(e) => { const c = e.target.value; const n = (STATES.find(([k]) => k === c) || [])[1] || ''; setForm((f) => ({ ...f, state_code: c, state: n })); }} className={input}>
                                <option value="">Choose…</option>
                                {STATES.map(([c, n]) => <option key={c} value={c}>{n}</option>)}
                            </select>{errLine('state_code')}{errLine('state')}
                        </label>
                        <label className="text-sm font-semibold text-slate-700">City
                            <input value={form.city} onChange={(e) => set('city', e.target.value)} maxLength={100} className={input} />{errLine('city')}
                        </label>
                        <label className="text-sm font-semibold text-slate-700">PIN code
                            <input value={form.postal_code} onChange={(e) => set('postal_code', e.target.value)} maxLength={20} className={input} />{errLine('postal_code')}
                        </label>
                        <label className="text-sm font-semibold text-slate-700 xl:col-span-2">Address
                            <input value={form.address_line_1} onChange={(e) => set('address_line_1', e.target.value)} maxLength={255} className={input} />{errLine('address_line_1')}
                        </label>
                        <label className="text-sm font-semibold text-slate-700">Sub-label's share % <span className="text-rose-600">*</span>
                            <input type="number" min="0" max="100" step="0.01" value={form.percent} onChange={(e) => set('percent', e.target.value)} placeholder="e.g. 70" className={input} />{errLine('percent')}{errLine('revenue_share_percent')}
                        </label>
                    </div>

                    {form.percent !== '' && pct >= 0 && pct <= 100 && (
                        <p className="mt-3 text-xs text-slate-600">Of every ₹100 you earn on this sub-label's songs: <b>₹{pct}</b> is the sub-label's, <b>₹{Math.round((100 - pct) * 100) / 100}</b> stays with you.</p>
                    )}
                    {otherErrors.length > 0 && <p className="mt-3 text-xs font-semibold text-rose-600">{otherErrors.join(' ')}</p>}

                    <div className="mt-5 flex justify-end">
                        <button type="button" disabled={!ready} onClick={create} className="rounded-xl bg-violet-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50">
                            {busy ? 'Creating…' : 'Create sub-label & send invitation'}
                        </button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 p-5">
                        <h2 className="text-lg font-semibold text-slate-900">Your sub-labels <span className="ml-1 text-sm font-normal text-slate-500">{subLabels.length}</span></h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {['Sub-label', 'Login', 'Share', 'Releases', 'Last login', 'Action'].map((h) => (
                                        <th key={h} className="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {subLabels.length === 0 && <tr><td colSpan="6" className="px-5 py-14 text-center text-sm text-slate-500">No sub-label yet. Add the first one above.</td></tr>}
                                {subLabels.map((s) => (
                                    <tr key={s.id}>
                                        <td className="px-5 py-4">
                                            <div className="font-semibold text-slate-900">{s.name}</div>
                                            <div className="text-xs capitalize text-slate-500">{s.status}</div>
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600">
                                            {s.owner_email ? (
                                                <>
                                                    <div>{s.owner_name}</div>
                                                    <div className="text-xs text-slate-500">{s.owner_email}</div>
                                                    <div className={`mt-0.5 text-xs font-semibold ${s.invitation === 'accepted' ? 'text-emerald-600' : 'text-amber-600'}`}>{s.invitation === 'accepted' ? 'Invitation accepted' : 'Invitation not accepted yet'}</div>
                                                </>
                                            ) : <span className="text-slate-400">no login</span>}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-700">
                                            {editing === s.id ? (
                                                <div className="flex items-center gap-2">
                                                    <input type="number" min="0" max="100" step="0.01" value={newPercent} onChange={(e) => setNewPercent(e.target.value)} className="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-slate-900" />
                                                    <button type="button" onClick={() => saveShare(s.id)} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-bold text-white">Save</button>
                                                    <button type="button" onClick={() => setEditing(null)} className="text-xs font-semibold text-slate-500">Cancel</button>
                                                </div>
                                            ) : (
                                                <span>{s.percent !== null ? `${s.percent}%` : <span className="text-rose-600">not set</span>}</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-slate-600">{s.releases}</td>
                                        <td className="px-5 py-4 text-sm text-slate-600">{fmtWhen(s.last_login)}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                {editing !== s.id && <button type="button" onClick={() => { setEditing(s.id); setNewPercent(s.percent ?? ''); }} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Change share</button>}
                                                {/* W237: look at this sub-label's panel (view only) */}
                                                {s.owner_status === 'active' && (
                                                    <button type="button" onClick={() => router.post(`/label/distributor/sub-labels/${s.id}/view`, {}, { preserveScroll: true })} className="rounded-lg border border-sky-300 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-50">View panel</button>
                                                )}
                                                {s.owner_email && s.invitation !== 'accepted' && s.invitation !== 'not_required' && (
                                                    <button type="button" onClick={() => router.post(`/label/distributor/sub-labels/${s.id}/resend`, {}, { preserveScroll: true })} className="rounded-lg border border-violet-300 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-50">Send invitation again</button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">Changing a share asks for a code sent to your e-mail. Enter it in the box that opens, then press Save again.</p>
                </section>
            </div>
        </PanelLayout>
    );
}
