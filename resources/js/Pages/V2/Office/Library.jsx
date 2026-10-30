import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';

/* W127 MIXXTUNE_OFFICE: job roles + ready-made task templates */
export default function Library({ roles = [], templates = [] }) {
    const [roleOpen, setRoleOpen] = useState(false);
    const [tplOpen, setTplOpen] = useState(false);
    const byRole = (id) => templates.filter((t) => t.role_id === id);

    return (
        <PanelLayout role="super_admin" title="Roles & Templates" subtitle="Job roles and ready-made tasks you can give in one click">
            <Head title="Roles & Templates" />
            <PageMessages />
            <div className="space-y-5">
                <div className="flex justify-end gap-2">
                    <button onClick={() => setRoleOpen(true)} className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">+ Role</button>
                    <button onClick={() => setTplOpen(true)} className="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">+ Template</button>
                </div>

                {roles.length === 0 && <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">No roles yet. Add a role like &quot;Graphic Designer&quot; or &quot;Video Editor&quot;.</div>}

                {roles.map((r) => (
                    <div key={r.id} className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-100 p-4">
                            <div className="font-bold text-slate-900">{r.name} <span className="ml-1 text-xs font-normal text-slate-400">{r.templates} template{r.templates === 1 ? '' : 's'}</span></div>
                            {r.description && <div className="text-xs text-slate-500">{r.description}</div>}
                        </div>
                        <div className="divide-y divide-slate-100">
                            {byRole(r.id).length === 0 && <div className="p-4 text-xs text-slate-400">No templates for this role yet.</div>}
                            {byRole(r.id).map((t) => (
                                <div key={t.id} className="p-4">
                                    <div className="font-semibold text-slate-800">{t.title}</div>
                                    {t.checklist.length > 0 && <ul className="mt-1 list-disc pl-5 text-xs text-slate-500">{t.checklist.map((c, i) => <li key={i}>{c}</li>)}</ul>}
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            {roleOpen && <AddRole onClose={() => setRoleOpen(false)} />}
            {tplOpen && <AddTemplate roles={roles} onClose={() => setTplOpen(false)} />}
        </PanelLayout>
    );
}

const inp = 'w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-violet-400';
function AddRole({ onClose }) {
    const { data, setData, post, processing, errors } = useForm({ name: '', description: '' });
    return (
        <Shell title="Add a job role" onClose={onClose} onOk={() => post('/office/admin/roles', { preserveScroll: true, onSuccess: onClose })} okText={processing ? '…' : 'Add role'}>
            <F label="Name" error={errors.name}><input value={data.name} onChange={(e) => setData('name', e.target.value)} className={inp} placeholder="e.g. Video Editor" /></F>
            <F label="Description (optional)"><textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} className={inp} /></F>
        </Shell>
    );
}
function AddTemplate({ roles, onClose }) {
    const { data, setData, post, processing, errors } = useForm({ functional_role_id: roles[0]?.id || '', title: '', description: '', priority: 'normal', proof_type: 'link', checklist: [''] });
    const setItem = (i, v) => setData('checklist', data.checklist.map((x, j) => (j === i ? v : x)));
    return (
        <Shell title="Add a task template" onClose={onClose} onOk={() => post('/office/admin/templates', { preserveScroll: true, onSuccess: onClose })} okText={processing ? '…' : 'Add template'}>
            <F label="Role"><select value={data.functional_role_id} onChange={(e) => setData('functional_role_id', e.target.value)} className={inp}><option value="">— none —</option>{roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}</select></F>
            <F label="Title" error={errors.title}><input value={data.title} onChange={(e) => setData('title', e.target.value)} className={inp} /></F>
            <F label="Details (optional)"><textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} className={inp} /></F>
            <div className="mt-2"><div className="text-sm font-semibold text-slate-700">Checklist</div>
                {data.checklist.map((c, i) => <input key={i} value={c} onChange={(e) => setItem(i, e.target.value)} placeholder={`Step ${i + 1}`} className={`${inp} mt-1`} />)}
                <button type="button" onClick={() => setData('checklist', [...data.checklist, ''])} className="mt-1 text-xs font-semibold text-violet-700">+ add step</button>
            </div>
        </Shell>
    );
}
function F({ label, error, children }) {
    return <label className="mt-2 block"><span className="text-sm font-semibold text-slate-700">{label}</span><div className="mt-1">{children}</div>{error && <span className="text-xs text-rose-600">{error}</span>}</label>;
}
function Shell({ title, children, onClose, onOk, okText }) {
    return (
        <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true">
            <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                <div className="text-base font-bold text-slate-900">{title}</div>{children}
                <div className="mt-5 flex justify-end gap-2">
                    <button onClick={onClose} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button onClick={onOk} className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">{okText}</button>
                </div>
            </div>
        </div>
    );
}
