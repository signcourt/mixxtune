import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';

import {
    ChevronDown,
    ChevronRight,
    CirclePlus,
    Edit3,
    FolderTree,
    Layers3,
    Trash2,
    X,
} from 'lucide-react';

import {
    useMemo,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

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

function collectSubtreeIds(
    labelId,
    labels,
) {
    const ids = new Set([Number(labelId)]);

    let changed = true;

    while (changed) {
        changed = false;

        labels.forEach((label) => {
            if (
                label.parent_label_id !== null &&
                ids.has(
                    Number(label.parent_label_id),
                ) &&
                !ids.has(Number(label.id))
            ) {
                ids.add(Number(label.id));
                changed = true;
            }
        });
    }

    return ids;
}

export default function Index({
    role = 'super_admin',
    labels = [],
}) {
    const [selectedRootId, setSelectedRootId] =
        useState('all');

    const [expanded, setExpanded] =
        useState(() => new Set());

    const [createParent, setCreateParent] =
        useState(null);

    const [editing, setEditing] =
        useState(null);

    const tree = useMemo(
        () => buildTree(labels),
        [labels],
    );

    const roots = useMemo(
        () =>
            labels
                .filter(
                    (label) =>
                        label.parent_label_id ===
                            null ||
                        label.parent_label_id ===
                            undefined,
                )
                .sort((a, b) =>
                    String(a.name).localeCompare(
                        String(b.name),
                    ),
                ),
        [labels],
    );

    const visibleTree = useMemo(() => {
        if (selectedRootId === 'all') {
            return tree;
        }

        return tree.filter(
            (root) =>
                Number(root.id) ===
                Number(selectedRootId),
        );
    }, [tree, selectedRootId]);

    const totalLevels = labels.filter(
        (label) =>
            label.parent_label_id !== null,
    ).length;

    const maxDepth = labels.reduce(
        (max, label) =>
            Math.max(
                max,
                Number(label.depth ?? 0),
            ),
        0,
    );

    const toggle = (id) => {
        setExpanded((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const expandAll = () => {
        setExpanded(
            new Set(
                labels.map((label) =>
                    Number(label.id),
                ),
            ),
        );
    };

    const collapseAll = () => {
        setExpanded(new Set());
    };

    return (
        <PanelLayout
            role={role}
            title="Label Hierarchy"
            subtitle="Manage master labels and their direct sub-labels"
        >
            <Head title="Label Hierarchy" />

            <div className="space-y-6">
                <section className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        title="Master Labels"
                        value={roots.length}
                        icon={FolderTree}
                    />

                    <StatCard
                        title="Sub-Labels"
                        value={totalLevels}
                        icon={Layers3}
                    />

                    <StatCard
                        title="Maximum Depth"
                        value={maxDepth}
                        icon={ChevronRight}
                    />
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="w-full lg:max-w-md">
                            <label className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                Master Label
                            </label>

                            <select
                                value={selectedRootId}
                                onChange={(event) =>
                                    setSelectedRootId(
                                        event.target.value,
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-800"
                            >
                                <option value="all">
                                    All Master Labels
                                </option>

                                {roots.map((root) => (
                                    <option
                                        key={root.id}
                                        value={root.id}
                                    >
                                        {root.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={expandAll}
                                className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Expand All
                            </button>

                            <button
                                type="button"
                                onClick={collapseAll}
                                className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Collapse All
                            </button>
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="text-base font-bold text-slate-900">
                            Catalogue Hierarchy
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Revenue and catalogue visibility
                            rolls upward through this tree.
                        </p>
                    </div>

                    <div className="divide-y divide-slate-100">
                        {visibleTree.length ? (
                            visibleTree.map((node) => (
                                <TreeNode
                                    key={node.id}
                                    node={node}
                                    labels={labels}
                                    expanded={expanded}
                                    toggle={toggle}
                                    onAdd={(label) =>
                                        setCreateParent(
                                            label,
                                        )
                                    }
                                    onEdit={(label) =>
                                        setEditing(label)
                                    }
                                />
                            ))
                        ) : (
                            <div className="px-6 py-14 text-center text-sm text-slate-500">
                                No labels found.
                            </div>
                        )}
                    </div>
                </section>
            </div>

            {createParent && (
                <CreateLevelModal
                    parent={createParent}
                    labels={labels}
                    onClose={() =>
                        setCreateParent(null)
                    }
                />
            )}

            {editing && (
                <EditLevelModal
                    label={editing}
                    labels={labels}
                    onClose={() =>
                        setEditing(null)
                    }
                />
            )}
        </PanelLayout>
    );
}

function TreeNode({
    node,
    labels,
    expanded,
    toggle,
    onAdd,
    onEdit,
}) {
    const hasChildren =
        node.children.length > 0;

    const isOpen =
        expanded.has(Number(node.id));

    const isRoot =
        node.parent_label_id === null;

    const remove = () => {
        if (isRoot) {
            return;
        }

        if (
            !window.confirm(
                `Delete sub-label "${node.name}"?\n\nA sub-label containing releases or artists cannot be deleted.`,
            )
        ) {
            return;
        }

        router.delete(
            `/super-admin/label-hierarchy/${node.id}`,
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <div>
            <div
                className="flex items-center gap-3 px-4 py-3.5 hover:bg-slate-50"
                style={{
                    paddingLeft:
                        16 +
                        Number(node.depth ?? 0) *
                            28,
                }}
            >
                <button
                    type="button"
                    onClick={() =>
                        hasChildren &&
                        toggle(Number(node.id))
                    }
                    className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-200"
                >
                    {hasChildren ? (
                        isOpen ? (
                            <ChevronDown size={17} />
                        ) : (
                            <ChevronRight
                                size={17}
                            />
                        )
                    ) : (
                        <span className="h-1.5 w-1.5 rounded-full bg-slate-300" />
                    )}
                </button>

                <div className="flex min-w-0 flex-1 items-center gap-3">
                    <div
                        className={[
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                            isRoot
                                ? 'bg-violet-100 text-violet-700'
                                : 'bg-slate-100 text-slate-600',
                        ].join(' ')}
                    >
                        {isRoot ? (
                            <FolderTree size={18} />
                        ) : (
                            <Layers3 size={17} />
                        )}
                    </div>

                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="truncate font-semibold text-slate-900">
                                {node.name}
                            </span>

                            <span
                                className={[
                                    'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                    isRoot
                                        ? 'bg-violet-100 text-violet-700'
                                        : 'bg-blue-50 text-blue-700',
                                ].join(' ')}
                            >
                                {isRoot
                                    ? 'Master'
                                    : 'Sub-Label'}
                            </span>

                            <StatusBadge
                                status={
                                    node.status
                                }
                            />
                        </div>

                        <div className="mt-1 text-xs text-slate-500">
                            ID #{node.id}
                            {' · '}
                            {
                                node.descendant_count
                            }{' '}
                            descendant
                            {Number(
                                node.descendant_count,
                            ) === 1
                                ? ''
                                : 's'}
                        </div>
                    </div>
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    {isRoot && (
                        <button
                            type="button"
                            onClick={() => onAdd(node)}
                            title="Add Sub-Label"
                            className="flex h-9 w-9 items-center justify-center rounded-lg text-emerald-600 hover:bg-emerald-50"
                        >
                            <CirclePlus size={18} />
                        </button>
                    )}

                    <button
                        type="button"
                        onClick={() =>
                            onEdit(node)
                        }
                        title="Edit"
                        className="flex h-9 w-9 items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50"
                    >
                        <Edit3 size={17} />
                    </button>

                    {!isRoot && (
                        <button
                            type="button"
                            onClick={remove}
                            title="Delete"
                            className="flex h-9 w-9 items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50"
                        >
                            <Trash2 size={17} />
                        </button>
                    )}
                </div>
            </div>

            {hasChildren &&
                isOpen &&
                node.children.map((child) => (
                    <TreeNode
                        key={child.id}
                        node={child}
                        labels={labels}
                        expanded={expanded}
                        toggle={toggle}
                        onAdd={onAdd}
                        onEdit={onEdit}
                    />
                ))}
        </div>
    );
}

function CreateLevelModal({
    parent,
    onClose,
}) {
    const form = useForm({
        name: '',
        parent_label_id: parent.id,
        revenue_share_percent: '100',
        status: 'active',
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            '/super-admin/label-hierarchy',
            {
                preserveScroll: true,
                onSuccess: onClose,
            },
        );
    };

    return (
        <Modal
            title="Add Sub-Label"
            subtitle={`Master Label: ${parent.name}`}
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <Field
                    label="Sub-Label Name"
                    error={form.errors.name}
                >
                    <input
                        autoFocus
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData(
                                'name',
                                event.target.value,
                            )
                        }
                        placeholder="Example: Mixx Tune"
                        className="w-full rounded-xl border border-slate-300 px-4 py-3"
                    />
                </Field>

                <Field
                    label="Sub-Label Revenue Share (%)"
                    error={
                        form.errors
                            .revenue_share_percent
                    }
                >
                    <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        value={
                            form.data
                                .revenue_share_percent
                        }
                        onChange={(event) =>
                            form.setData(
                                'revenue_share_percent',
                                event.target.value,
                            )
                        }
                        className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-800"
                        required
                    />

                    <p className="mt-1.5 text-xs text-slate-500">
                        The sub-label receives this
                        percentage. The master label
                        retains the remaining share.
                    </p>
                </Field>

                <Field label="Status">
                    <select
                        value={form.data.status}
                        onChange={(event) =>
                            form.setData(
                                'status',
                                event.target.value,
                            )
                        }
                        className="w-full rounded-xl border border-slate-300 px-4 py-3"
                    >
                        <option value="active">
                            Active
                        </option>
                        <option value="inactive">
                            Inactive
                        </option>
                        <option value="suspended">
                            Suspended
                        </option>
                    </select>
                </Field>

                <ModalActions
                    processing={
                        form.processing
                    }
                    onClose={onClose}
                    submitLabel="Create Sub-Label"
                />
            </form>
        </Modal>
    );
}

function EditLevelModal({
    label,
    labels,
    onClose,
}) {
    const isRoot =
        label.parent_label_id === null;

    const excludedIds = useMemo(
        () =>
            collectSubtreeIds(
                label.id,
                labels,
            ),
        [label.id, labels],
    );

    const sameRootParents = labels
        .filter(
            (candidate) =>
                candidate.parent_label_id === null &&
                Number(candidate.id) ===
                    Number(label.root_id) &&
                !excludedIds.has(
                    Number(candidate.id),
                ),
        )
        .sort((a, b) =>
            String(a.name).localeCompare(
                String(b.name),
            ),
        );

    const form = useForm({
        name: label.name ?? '',
        parent_label_id:
            label.parent_label_id ?? '',
        revenue_share_percent:
            label.revenue_share_percentage ??
            '100',
        status: label.status ?? 'active',
    });

    const submit = (event) => {
        event.preventDefault();

        form.put(
            `/super-admin/label-hierarchy/${label.id}`,
            {
                preserveScroll: true,
                onSuccess: onClose,
            },
        );
    };

    return (
        <Modal
            title={
                isRoot
                    ? 'Edit Master Label'
                    : 'Edit Sub-Label'
            }
            subtitle={label.name}
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <Field
                    label="Name"
                    error={form.errors.name}
                >
                    <input
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData(
                                'name',
                                event.target.value,
                            )
                        }
                        className="w-full rounded-xl border border-slate-300 px-4 py-3"
                    />
                </Field>

                {!isRoot && (
                    <Field
                        label="Master Label"
                        error={
                            form.errors
                                .parent_label_id
                        }
                    >
                        <select
                            value={
                                form.data
                                    .parent_label_id
                            }
                            onChange={(event) =>
                                form.setData(
                                    'parent_label_id',
                                    event.target
                                        .value,
                                )
                            }
                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                        >
                            {sameRootParents.map(
                                (candidate) => (
                                    <option
                                        key={
                                            candidate.id
                                        }
                                        value={
                                            candidate.id
                                        }
                                    >
                                        {'— '.repeat(
                                            Number(
                                                candidate.depth ??
                                                    0,
                                            ),
                                        )}
                                        {
                                            candidate.name
                                        }
                                    </option>
                                ),
                            )}
                        </select>
                    </Field>
                )}

                {!isRoot && (
                    <Field
                        label="Sub-Label Revenue Share (%)"
                        error={
                            form.errors
                                .revenue_share_percent
                        }
                    >
                        <input
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={
                                form.data
                                    .revenue_share_percent
                            }
                            onChange={(event) =>
                                form.setData(
                                    'revenue_share_percent',
                                    event.target.value,
                                )
                            }
                            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-800"
                            required
                        />

                        <p className="mt-1.5 text-xs text-slate-500">
                            The sub-label receives this
                            percentage. The master label
                            retains the remaining share.
                        </p>
                    </Field>
                )}

                <Field label="Status">
                    <select
                        value={form.data.status}
                        onChange={(event) =>
                            form.setData(
                                'status',
                                event.target.value,
                            )
                        }
                        className="w-full rounded-xl border border-slate-300 px-4 py-3"
                    >
                        <option value="active">
                            Active
                        </option>
                        <option value="inactive">
                            Inactive
                        </option>
                        <option value="suspended">
                            Suspended
                        </option>
                    </select>
                </Field>

                <ModalActions
                    processing={
                        form.processing
                    }
                    onClose={onClose}
                    submitLabel="Save Changes"
                />
            </form>
        </Modal>
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
    const active =
        status === 'active';

    return (
        <span
            className={[
                'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                active
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-amber-50 text-amber-700',
            ].join(' ')}
        >
            {status ?? 'unknown'}
        </span>
    );
}

function StatCard({
    title,
    value,
    icon: Icon,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <div>
                    <div className="text-xs font-bold uppercase tracking-wide text-slate-500">
                        {title}
                    </div>

                    <div className="mt-2 text-3xl font-black text-slate-900">
                        {value}
                    </div>
                </div>

                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
                    <Icon size={21} />
                </div>
            </div>
        </div>
    );
}
