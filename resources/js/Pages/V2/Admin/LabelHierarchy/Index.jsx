import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';

import {
    AlertTriangle,
    ChevronDown,
    ChevronRight,
    CirclePlus,
    Edit3,
    FolderTree,
    Layers3,
    Search,
    Trash2,
    UserX,
    X,
} from 'lucide-react';

import {
    useMemo,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';

const normalizeId = (value) =>
    value === null ||
    value === undefined ||
    value === ''
        ? null
        : Number(value);

function buildTree(labels = []) {
    const map = new Map();

    labels.forEach((label) => {
        map.set(Number(label.id), {
            ...label,
            id: Number(label.id),
            parent_label_id:
                normalizeId(label.parent_label_id),
            children: [],
        });
    });

    const roots = [];

    map.forEach((node) => {
        if (
            node.parent_label_id !== null &&
            map.has(node.parent_label_id)
        ) {
            map
                .get(node.parent_label_id)
                .children.push(node);
        } else {
            roots.push(node);
        }
    });

    const sortNodes = (nodes) => {
        nodes.sort((a, b) =>
            String(a.name).localeCompare(
                String(b.name),
            ),
        );

        nodes.forEach((node) =>
            sortNodes(node.children),
        );
    };

    sortNodes(roots);

    return roots;
}

/*
 * W61 MIXXTUNE_LEVELS_V2: search (name, owner e-mail, ID), filters, owner
 * login and release / artist counts per row, sub-label % shown with what
 * the master keeps, "% not set" warning, page of 50 + show more, useful
 * cards (the old "Maximum depth" was always 0 or 1 - only two levels exist).
 */
const PAGE = 50;

export default function Index({
    role = 'super_admin',
    labels = [],
    totals = {},
}) {
    const [q, setQ] = useState('');
    const [show, setShow] = useState(() => {
        /* W69: the dashboard links here with ?show=share_missing / no_login */
        try {
            const wanted = new URLSearchParams(window.location.search).get('show');
            return ['all', 'with_sub', 'no_login', 'share_missing', 'not_active'].includes(wanted) ? wanted : 'all';
        } catch {
            return 'all';
        }
    });
    const [limit, setLimit] = useState(PAGE);
    const [expanded, setExpanded] = useState(() => new Set());
    const [createParent, setCreateParent] = useState(null);
    const [editing, setEditing] = useState(null);

    const tree = useMemo(() => buildTree(labels), [labels]);
    const term = q.trim().toLowerCase();

    const matches = (n) =>
        !term ||
        String(n.name || '').toLowerCase().includes(term) ||
        String(n.owner_email || '').toLowerCase().includes(term) ||
        String(n.owner_name || '').toLowerCase().includes(term) ||
        `#${n.id}` === term ||
        String(n.id) === term;

    const filtered = useMemo(() => {
        return tree.filter((root) => {
            if (show === 'with_sub' && root.children.length === 0) return false;
            if (show === 'no_login' && root.owner_email) return false;
            if (show === 'share_missing' && !root.children.some((c) => c.share_missing)) return false;
            if (show === 'not_active' && root.status === 'active' && !root.children.some((c) => c.status !== 'active')) return false;
            return matches(root) || root.children.some(matches);
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tree, show, term]);

    /* searching or filtering for sub-labels opens the masters that hold them */
    const autoOpen = useMemo(() => {
        const ids = new Set();
        if (term || show === 'share_missing' || show === 'with_sub') {
            filtered.forEach((root) => {
                if (root.children.some((c) => matches(c) || (show === 'share_missing' && c.share_missing))) ids.add(root.id);
            });
        }
        return ids;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filtered, term, show]);

    const visible = filtered.slice(0, limit);
    const anyChildren = labels.some((l) => l.parent_label_id !== null && l.parent_label_id !== undefined);

    const toggle = (id) =>
        setExpanded((current) => {
            const next = new Set(current);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });

    const isOpen = (id) => expanded.has(id) || autoOpen.has(id);

    return (
        <PanelLayout role={role} title="Label Management" subtitle="Master labels and their sub-labels">
            <Head title="Label Management" />
            <PageMessages />

            <div className="space-y-5">
                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Master labels" value={totals.masters ?? 0} icon={FolderTree} hint={`${totals.masters_with_sub ?? 0} have sub-labels`} onClick={() => { setShow('all'); setLimit(PAGE); }} />
                    <StatCard title="Sub-labels" value={totals.sub_labels ?? 0} icon={Layers3} onClick={() => { setShow('with_sub'); setLimit(PAGE); }} />
                    <StatCard title="Sub-labels without %" value={totals.sub_without_share ?? 0} icon={AlertTriangle} warn={(totals.sub_without_share ?? 0) > 0} hint="Their revenue split is unknown" onClick={() => { setShow('share_missing'); setLimit(PAGE); }} />
                    <StatCard title="Masters without login" value={totals.masters_without_login ?? 0} icon={UserX} warn={(totals.masters_without_login ?? 0) > 0} hint="No owner account linked" onClick={() => { setShow('no_login'); setLimit(PAGE); }} />
                </section>

                <section className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:flex-row lg:items-center">
                    <div className="relative w-full lg:w-96">
                        <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            value={q}
                            onChange={(e) => { setQ(e.target.value); setLimit(PAGE); }}
                            placeholder="Search label, owner e-mail or ID"
                            className="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm"
                        />
                    </div>
                    <select value={show} onChange={(e) => { setShow(e.target.value); setLimit(PAGE); }} className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="all">All master labels</option>
                        <option value="with_sub">With sub-labels</option>
                        <option value="share_missing">Sub-label % not set</option>
                        <option value="no_login">No owner login</option>
                        <option value="not_active">Not active</option>
                    </select>
                    <div className="flex flex-1 flex-wrap items-center justify-end gap-2 text-sm">
                        <span className="text-xs text-slate-500">{filtered.length} master label{filtered.length === 1 ? '' : 's'} shown</span>
                        {anyChildren && (
                            <>
                                <button type="button" onClick={() => setExpanded(new Set(filtered.map((r) => r.id)))} className="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open all</button>
                                <button type="button" onClick={() => setExpanded(new Set())} className="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Close all</button>
                            </>
                        )}
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-3 text-sm text-slate-500">
                        A sub-label gets its % of the money earned by its releases; the master keeps the rest. Press <b>+ Sub-label</b> on a master to add one.
                    </div>

                    <div className="divide-y divide-slate-100">
                        {visible.length ? (
                            visible.map((node) => (
                                <TreeNode
                                    key={node.id}
                                    node={node}
                                    open={isOpen(node.id)}
                                    toggle={toggle}
                                    highlight={term ? matches : null}
                                    onAdd={setCreateParent}
                                    onEdit={setEditing}
                                />
                            ))
                        ) : (
                            <div className="px-6 py-14 text-center text-sm text-slate-500">
                                {labels.length ? 'Nothing matches this search / filter.' : 'No labels yet.'}
                            </div>
                        )}
                    </div>

                    {filtered.length > limit && (
                        <div className="border-t border-slate-100 p-3 text-center">
                            <button type="button" onClick={() => setLimit((n) => n + PAGE)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Show {Math.min(PAGE, filtered.length - limit)} more ({filtered.length - limit} left)
                            </button>
                        </div>
                    )}
                </section>
            </div>

            {createParent && <CreateLevelModal parent={createParent} onClose={() => setCreateParent(null)} />}

            {editing && <EditLevelModal label={editing} labels={labels} onClose={() => setEditing(null)} />}
        </PanelLayout>
    );
}

function TreeNode({ node, open, toggle, highlight, onAdd, onEdit, master = null }) {
    const hasChildren = (node.children || []).length > 0;
    const isRoot = node.parent_label_id === null;
    const dim = highlight && !highlight(node);

    const remove = () => {
        if (!window.confirm(`Delete sub-label "${node.name}"?\n\nOnly an empty sub-label (no releases, artists or revenue) can be deleted.`)) return;
        router.delete(`/super-admin/label-hierarchy/${node.id}`, { preserveScroll: true });
    };

    return (
        <div>
            <div className={`flex flex-col gap-2 px-4 py-3 hover:bg-slate-50 sm:flex-row sm:items-center ${isRoot ? '' : 'bg-slate-50/60 sm:pl-14'} ${dim ? 'opacity-60' : ''}`}>
                <div className="flex min-w-0 flex-1 items-center gap-3">
                    {isRoot ? (
                        <button
                            type="button"
                            onClick={() => hasChildren && toggle(node.id)}
                            disabled={!hasChildren}
                            aria-label={hasChildren ? (open ? 'Close sub-labels' : 'Open sub-labels') : 'No sub-labels'}
                            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${hasChildren ? 'text-slate-600 hover:bg-slate-200' : 'cursor-default text-transparent'}`}
                        >
                            {open ? <ChevronDown size={17} /> : <ChevronRight size={17} />}
                        </button>
                    ) : (
                        <span className="w-8 shrink-0 text-center text-slate-300">└</span>
                    )}

                    <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${isRoot ? 'bg-violet-100 text-violet-700' : 'bg-blue-50 text-blue-600'}`}>
                        {isRoot ? <FolderTree size={18} /> : <Layers3 size={17} />}
                    </div>

                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <span className="truncate font-semibold text-slate-900">{node.name}</span>
                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${isRoot ? 'bg-violet-100 text-violet-700' : 'bg-blue-50 text-blue-700'}`}>{isRoot ? 'Master' : 'Sub-label'}</span>
                            <StatusBadge status={node.status} />
                            {!isRoot && (node.share_missing ? (
                                <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700">% not set</span>
                            ) : (
                                <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                    gets {fmt(node.revenue_share_percentage)}% · master keeps {fmt(node.parent_commission_percentage)}%
                                </span>
                            ))}
                        </div>
                        <div className="mt-0.5 flex flex-wrap gap-x-2 text-xs text-slate-500">
                            <span>ID #{node.id}</span>
                            {isRoot && (node.owner_email ? (
                                <span>· owner {node.owner_email}{node.owner_status && node.owner_status !== 'active' ? ` (${node.owner_status})` : ''}</span>
                            ) : (
                                <span className="font-semibold text-amber-700">· no owner login</span>
                            ))}
                            {isRoot && node.label_percent !== null && node.label_percent !== undefined && <span>· label % {fmt(node.label_percent)}</span>}
                            <span>· {node.releases_count ?? 0} release{Number(node.releases_count) === 1 ? '' : 's'}</span>
                            <span>· {node.artists_count ?? 0} artist{Number(node.artists_count) === 1 ? '' : 's'}</span>
                            {isRoot && <span>· {node.children_count ?? 0} sub-label{Number(node.children_count) === 1 ? '' : 's'}</span>}
                        </div>
                    </div>
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-2 pl-11 sm:pl-0">
                    {isRoot && (
                        <button type="button" onClick={() => onAdd(node)} className="inline-flex items-center gap-1 rounded-lg border border-emerald-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                            <CirclePlus size={14} /> Sub-label
                        </button>
                    )}
                    <button type="button" onClick={() => onEdit({ ...node, master })} className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                        <Edit3 size={14} /> Edit
                    </button>
                    {!isRoot && (
                        <button type="button" onClick={remove} className="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                            <Trash2 size={14} /> Delete
                        </button>
                    )}
                </div>
            </div>

            {hasChildren && open && node.children.map((child) => (
                <TreeNode key={child.id} node={child} open={false} toggle={toggle} highlight={highlight} onAdd={onAdd} onEdit={onEdit} master={node} />
            ))}
        </div>
    );
}

const fmt = (v) => {
    const n = Number(v);
    return Number.isFinite(n) ? (Math.round(n * 100) / 100).toString() : '—';
};

function CreateLevelModal({ parent, onClose }) {
    const form = useForm({ name: '', parent_label_id: parent.id, revenue_share_percent: '', status: 'active' });
    const pct = form.data.revenue_share_percent === '' ? null : Number(form.data.revenue_share_percent);

    const submit = (event) => {
        event.preventDefault();
        form.post('/super-admin/label-hierarchy', { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal title="Add Sub-label" subtitle={`Under master: ${parent.name}${parent.owner_email ? ` (${parent.owner_email})` : ''}`} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Sub-label name" error={form.errors.name || form.errors.parent_label_id}>
                    <input autoFocus required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Name exactly as on the releases" className="w-full rounded-xl border border-slate-300 px-4 py-3" />
                </Field>

                <Field label="Sub-label share (%)" error={form.errors.revenue_share_percent}>
                    <input type="number" min="0" max="100" step="0.01" required value={form.data.revenue_share_percent} onChange={(e) => form.setData('revenue_share_percent', e.target.value)} placeholder="Type the agreed %" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-800" />
                    <p className="mt-1.5 text-xs text-slate-500">
                        {pct !== null && pct >= 0 && pct <= 100
                            ? `The sub-label gets ${fmt(pct)}%, the master "${parent.name}" keeps ${fmt(100 - pct)}%.`
                            : 'Type the % agreed with the owner. The master keeps the rest.'}
                    </p>
                </Field>

                <Field label="Status">
                    <StatusSelect value={form.data.status} onChange={(v) => form.setData('status', v)} />
                </Field>

                <ModalActions processing={form.processing} onClose={onClose} submitLabel="Create sub-label" />
            </form>
        </Modal>
    );
}

function EditLevelModal({ label, labels, onClose }) {
    const isRoot = label.parent_label_id === null;
    const master = label.master || labels.find((l) => Number(l.id) === Number(label.parent_label_id));

    const form = useForm({
        name: label.name ?? '',
        parent_label_id: label.parent_label_id ?? '',
        revenue_share_percent: label.revenue_share_percentage ?? '',
        status: label.status ?? 'active',
    });
    const pct = form.data.revenue_share_percent === '' ? null : Number(form.data.revenue_share_percent);
    const pctChanged = !isRoot && label.revenue_share_percentage !== null && pct !== null && Math.round(pct * 100) !== Math.round(Number(label.revenue_share_percentage) * 100);
    const masterChanged = isRoot && (form.data.name.trim() !== String(label.name) || form.data.status !== label.status);

    const submit = (event) => {
        event.preventDefault();
        form.put(`/super-admin/label-hierarchy/${label.id}`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal title={isRoot ? 'Edit master label' : 'Edit sub-label'} subtitle={isRoot ? label.name : `${label.name} · under ${master?.name ?? `#${label.parent_label_id}`}`} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Name" error={form.errors.name || form.errors.parent_label_id}>
                    <input required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="w-full rounded-xl border border-slate-300 px-4 py-3" />
                </Field>

                {!isRoot && (
                    <Field label="Sub-label share (%)" error={form.errors.revenue_share_percent}>
                        <input type="number" min="0" max="100" step="0.01" required value={form.data.revenue_share_percent} onChange={(e) => form.setData('revenue_share_percent', e.target.value)} placeholder={label.share_missing ? 'Not set yet - type the agreed %' : ''} className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-800" />
                        <p className="mt-1.5 text-xs text-slate-500">
                            {pct !== null && pct >= 0 && pct <= 100 ? `The sub-label gets ${fmt(pct)}%, the master keeps ${fmt(100 - pct)}%.` : 'The master keeps the rest.'}
                            {pctChanged ? ` Was ${fmt(label.revenue_share_percentage)}% - changing it needs the e-mailed code.` : ''}
                        </p>
                    </Field>
                )}

                <Field label="Status">
                    <StatusSelect value={form.data.status} onChange={(v) => form.setData('status', v)} />
                </Field>

                {isRoot && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                        This is the owner&apos;s master label{label.owner_email ? ` (${label.owner_email})` : ''} with {label.releases_count ?? 0} release(s). Changing its name or status needs the e-mailed code{masterChanged ? ' - you will be asked for it when you save' : ''}.
                    </div>
                )}

                <ModalActions processing={form.processing} onClose={onClose} submitLabel="Save changes" />
            </form>
        </Modal>
    );
}

function StatusSelect({ value, onChange }) {
    return (
        <select value={value} onChange={(e) => onChange(e.target.value)} className="w-full rounded-xl border border-slate-300 px-4 py-3">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
        </select>
    );
}

function Modal({
    title,
    subtitle,
    onClose,
    children,
}) {
    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-4">
            <div className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div className="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <h3 className="text-lg font-bold text-slate-900">
                            {title}
                        </h3>

                        {subtitle && (
                            <p className="mt-1 text-sm text-slate-500">
                                {subtitle}
                            </p>
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                    >
                        <X size={18} />
                    </button>
                </div>

                <div className="p-6">
                    {children}
                </div>
            </div>
        </div>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <div>
            <label className="mb-2 block text-sm font-semibold text-slate-700">
                {label}
            </label>

            {children}

            {error && (
                <p className="mt-1.5 text-xs font-medium text-rose-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function ModalActions({
    processing,
    onClose,
    submitLabel,
}) {
    return (
        <div className="flex justify-end gap-3 border-t border-slate-100 pt-5">
            <button
                type="button"
                onClick={onClose}
                className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700"
            >
                Cancel
            </button>

            <button
                type="submit"
                disabled={processing}
                className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-50"
            >
                {processing
                    ? 'Saving...'
                    : submitLabel}
            </button>
        </div>
    );
}

function StatusBadge({ status }) {
    const cls = status === 'active' ? 'bg-emerald-50 text-emerald-700' : status === 'suspended' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700';
    return <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${cls}`}>{status ?? 'unknown'}</span>;
}

function StatCard({ title, value, icon: Icon, hint, warn = false, onClick }) {
    return (
        <button type="button" onClick={onClick} className={`rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:shadow ${warn ? 'border-amber-200' : 'border-slate-200'}`}>
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                    <div className="text-xs font-bold uppercase tracking-wide text-slate-500">{title}</div>
                    <div className={`mt-1 text-2xl font-black ${warn ? 'text-amber-700' : 'text-slate-900'}`}>{Number(value).toLocaleString('en-IN')}</div>
                    {hint && <div className="mt-0.5 truncate text-xs text-slate-400">{hint}</div>}
                </div>
                <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${warn ? 'bg-amber-50 text-amber-700' : 'bg-violet-50 text-violet-700'}`}>
                    <Icon size={20} />
                </div>
            </div>
        </button>
    );
}
