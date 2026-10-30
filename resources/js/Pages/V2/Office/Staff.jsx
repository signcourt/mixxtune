import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';
import DatePicker from '@/V2/Shared/Components/DatePicker';

/* W127 MIXXTUNE_OFFICE: staff list + add staff + give a task */
export default function Staff({ staff = [], roles = [] }) {
    const [addOpen, setAddOpen] = useState(false);
    const [assignFor, setAssignFor] = useState(null);

    return (
        <PanelLayout role="super_admin" title="Office Staff" subtitle="Add staff, give them job roles and tasks">
            <Head title="Office Staff" />
            <PageMessages />
            <div className="space-y-5">
                <div className="flex justify-end">
                    <button onClick={() => setAddOpen(true)} className="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">+ Add staff</button>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {staff.length === 0 ? (
                        <div className="p-8 text-center text-sm text-slate-500">No staff yet. Add your first staff member.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {staff.map((s) => (
                                <div key={s.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
                                    <div className="min-w-0">
                                        <div className="font-semibold text-slate-900">{s.name} {!s.active && <span className="ml-1 rounded bg-slate-100 px-1.5 text-[11px] text-slate-500">off</span>}</div>
                                        <div className="text-xs text-slate-500">{s.email}{s.phone ? ` · ${s.phone}` : ''}</div>
                                        <div className="mt-1 flex flex-wrap gap-1">
                                            {s.roles.length === 0 && <span className="text-[11px] text-slate-400">no role set</span>}
                                            {s.roles.map((r) => <span key={r} className="rounded-md bg-violet-50 px-1.5 py-0.5 text-[11px] font-medium text-violet-700">{r}</span>)}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-slate-500">{s.open_tasks} open</span>
                                        <button onClick={() => setAssignFor(s)} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700">Give task</button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {addOpen && <AddStaff roles={roles} onClose={() => setAddOpen(false)} />}
            {assignFor && <GiveTask staff={assignFor} onClose={() => setAssignFor(null)} />}
        </PanelLayout>
    );
}

function AddStaff({ roles, onClose }) {
    const { data, setData, post, processing, errors } = useForm({ name: '', email: '', phone: '', roles: [] });
    const toggle = (id) => setData('roles', data.roles.includes(id) ? data.roles.filter((r) => r !== id) : [...data.roles, id]);
    const submit = () => post('/office/admin/staff', { preserveScroll: true, onSuccess: onClose });
    return (
        <Shell title="Add staff" onClose={onClose} onOk={submit} okText={processing ? 'Adding…' : 'Add staff'}>
            <Field label="Full name" error={errors.name}><input value={data.name} onChange={(e) => setData('name', e.target.value)} className={inp} /></Field>
            <Field label="Email (their login)" error={errors.email}><input value={data.email} onChange={(e) => setData('email', e.target.value)} className={inp} /></Field>
            <Field label="Phone (optional)" error={errors.phone}><input value={data.phone} onChange={(e) => setData('phone', e.target.value)} className={inp} /></Field>
            <div className="mt-2">
                <div className="text-sm font-semibold text-slate-700">Job roles</div>
                <div className="mt-1 flex flex-wrap gap-1.5">
                    {roles.length === 0 && <span className="text-xs text-slate-400">No roles yet — add them under Roles &amp; Templates.</span>}
                    {roles.map((r) => (
                        <button key={r.id} type="button" onClick={() => toggle(r.id)} className={`rounded-full border px-3 py-1 text-xs font-semibold ${data.roles.includes(r.id) ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-200 text-slate-600'}`}>{r.name}</button>
                    ))}
                </div>
            </div>
            <p className="mt-2 text-xs text-slate-500">A one-time password is shown after adding. Give it to the staff member; they also get an e-mailed code at login.</p>
        </Shell>
    );
}

function GiveTask({ staff, onClose }) {
    const { data, setData, post, processing, errors } = useForm({ assigned_to: staff.id, title: '', description: '', priority: 'normal', due_at: '', checklist: [''] });
    const setItem = (i, v) => setData('checklist', data.checklist.map((x, j) => (j === i ? v : x)));
    const submit = () => post('/office/admin/assign', { preserveScroll: true, onSuccess: onClose });
    return (
        <Shell title={`Give a task to ${staff.name}`} onClose={onClose} onOk={submit} okText={processing ? 'Giving…' : 'Give task'}>
            <Field label="Task title" error={errors.title}><input value={data.title} onChange={(e) => setData('title', e.target.value)} className={inp} placeholder="e.g. 3 thumbnails for the new release" /></Field>
            <Field label="Details (optional)"><textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} className={inp} /></Field>
            <div className="grid grid-cols-2 gap-3">
                <Field label="Priority"><select value={data.priority} onChange={(e) => setData('priority', e.target.value)} className={inp}><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></Field>
                <Field label="Due (optional)"><DatePicker value={data.due_at ? data.due_at.slice(0, 10) : ''} onChange={(iso) => setData('due_at', `${iso} 18:00:00`)} placeholder="Pick a date" /></Field>
            </div>
            <div className="mt-2">
                <div className="text-sm font-semibold text-slate-700">Checklist</div>
                {data.checklist.map((c, i) => (
                    <input key={i} value={c} onChange={(e) => setItem(i, e.target.value)} placeholder={`Step ${i + 1}`} className={`${inp} mt-1`} />
                ))}
                <button type="button" onClick={() => setData('checklist', [...data.checklist, ''])} className="mt-1 text-xs font-semibold text-violet-700">+ add step</button>
            </div>
        </Shell>
    );
}

const inp = 'w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-violet-400';
function Field({ label, error, children }) {
    return <label className="mt-2 block"><span className="text-sm font-semibold text-slate-700">{label}</span><div className="mt-1">{children}</div>{error && <span className="text-xs text-rose-600">{error}</span>}</label>;
}
function Shell({ title, children, onClose, onOk, okText }) {
    return (
        <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true">
            <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                <div className="text-base font-bold text-slate-900">{title}</div>
                {children}
                <div className="mt-5 flex justify-end gap-2">
                    <button onClick={onClose} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button onClick={onOk} className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">{okText}</button>
                </div>
            </div>
        </div>
    );
}
