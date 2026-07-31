import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RevenueLabelMappings({
    mappings = {},
    labels = [],
    filters = {},
    summary = {},
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [createModal, setCreateModal] = useState({
        open: false,
        mappingId: null,
        name: '',
        royalty_percentage: 100,
    });

    const [creating, setCreating] = useState(false);


    const applyFilters = (event) => {
        event.preventDefault();

        router.get(
            '/revenue-label-mappings',
            { search, status },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const openCreateLabel = (mapping) => {
        setCreateModal({
            open: true,
            mappingId: mapping.id,
            name: mapping.revenue_label_name ?? '',
            royalty_percentage: mapping.royalty_percentage ?? 100,
        });
    };

    const closeCreateLabel = () => {
        if (creating) return;

        setCreateModal({
            open: false,
            mappingId: null,
            name: '',
            royalty_percentage: 100,
        });
    };
    const createLabel = (event) => {
        event.preventDefault();

        if (!createModal.name.trim()) {
            alert('Label name required hai.');
            return;
        }

        setCreating(true);

        router.post(
            '/revenue-label-mappings/create-label',
            {
                name: createModal.name.trim(),
                royalty_percentage: createModal.royalty_percentage,
                mapping_id: createModal.mappingId,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setCreateModal({
                        open: false,
                        mappingId: null,
                        name: '',
                        royalty_percentage: 100,
                    });

                    router.reload({
                        only: ['mappings', 'labels', 'summary'],
                        preserveScroll: true,
                    });
                },

                onError: (errors) => {
                    const message = Object.values(errors || {})
                        .flat()
                        .join('\n');

                    alert(message || 'Label create nahi hua.');
                },

                onFinish: () => {
                    setCreating(false);
                },
            }
        );
    };


    const resetFilters = () => {
        setSearch('');
        setStatus('');
        router.get('/revenue-label-mappings');
    };

    const rows = mappings?.data ?? [];

    return (
        <AdminLayout title="Revenue Label Mapping">
            <Head title="Revenue Label Mapping" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        Revenue Label Mapping
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Map imported revenue label names to system labels before
                        posting royalties.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat label="Total Labels" value={summary.total ?? 0} />
                    <Stat label="Mapped" value={summary.mapped ?? 0} />
                    <Stat label="Unmapped" value={summary.unmapped ?? 0} />
                    <Stat label="Ignored" value={summary.ignored ?? 0} />
                </div>

                <form
                    onSubmit={applyFilters}
                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="grid gap-3 md:grid-cols-3">
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search revenue or system label"
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All statuses</option>
                            <option value="mapped">Mapped</option>
                            <option value="unmapped">Unmapped</option>
                            <option value="ignored">Ignored</option>
                        </select>

                        <div className="flex gap-3">
                            <button
                                type="submit"
                                className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white"
                            >
                                Apply
                            </button>

                            <button
                                type="button"
                                onClick={resetFilters}
                                className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="font-semibold text-slate-900">
                            Label Mappings
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Revenue Label</th>
                                    <th className="px-4 py-3">System Label</th>
                                    <th className="px-4 py-3">Royalty %</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Notes</th>
                                    <th className="px-4 py-3">Action</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.map((mapping) => (
                                    <MappingRow
                                        key={mapping.id}
                                        mapping={mapping}
                                        labels={labels}
                                        onCreateLabel={openCreateLabel}
                                    />
                                ))}

                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No mappings found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
                <CreateLabelModal
                    modal={createModal}
                    creating={creating}
                    onChange={setCreateModal}
                    onClose={closeCreateLabel}
                    onSubmit={createLabel}
                />
            </div>
        </AdminLayout>
    );
}

function MappingRow({ mapping, labels, onCreateLabel }) {
    const [form, setForm] = useState({
        label_id: mapping.label_id ?? '',
        royalty_percentage: mapping.royalty_percentage ?? 100,
        status: mapping.status ?? 'unmapped',
        notes: mapping.notes ?? '',
    });

    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);

        router.put(
            `/revenue-label-mappings/${mapping.id}`,
            form,
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            }
        );
    };

    return (
        <tr>
            <td className="px-4 py-4 font-semibold text-slate-900">
                {mapping.revenue_label_name}
            </td>

            <td className="px-4 py-4">
                <select
                    value={form.label_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            label_id: event.target.value,
                        })
                    }
                    className="min-w-[220px] rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">Select system label</option>

                    {labels.map((label) => (
                        <option key={label.id} value={label.id}>
                            {label.name}
                        </option>
                    ))}
                </select>

                <button
                    type="button"
                    onClick={() => onCreateLabel(mapping)}
                    className="mt-2 block text-xs font-semibold text-violet-600 hover:underline"
                >
                    + Create New Label
                </button>
            </td>

            <td className="px-4 py-4">
                <input
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    value={form.royalty_percentage}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            royalty_percentage: event.target.value,
                        })
                    }
                    className="w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
            </td>

            <td className="px-4 py-4">
                <select
                    value={form.status}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            status: event.target.value,
                        })
                    }
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="unmapped">Unmapped</option>
                    <option value="mapped">Mapped</option>
                    <option value="ignored">Ignored</option>
                </select>
            </td>

            <td className="px-4 py-4">
                <input
                    type="text"
                    value={form.notes}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            notes: event.target.value,
                        })
                    }
                    placeholder="Optional note"
                    className="min-w-[220px] rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
            </td>

            <td className="px-4 py-4">
                <button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    className="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                >
                    {saving ? 'Saving...' : 'Save'}
                </button>
            </td>
        </tr>
    );
}


function CreateLabelModal({
    modal,
    creating,
    onChange,
    onClose,
    onSubmit,
}) {
    if (!modal.open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
            <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <h2 className="text-lg font-bold text-slate-900">
                            Create System Label
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Revenue label ko naye system label ke roop me create karein.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100"
                    >
                        ✕
                    </button>
                </div>

                <form onSubmit={onSubmit} className="space-y-5 p-6">
                    <div>
                        <label className="mb-2 block text-sm font-semibold text-slate-700">
                            Label Name
                        </label>

                        <input
                            type="text"
                            value={modal.name}
                            onChange={(event) =>
                                onChange({
                                    ...modal,
                                    name: event.target.value,
                                })
                            }
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            required
                        />
                    </div>

                    <div>
                        <label className="mb-2 block text-sm font-semibold text-slate-700">
                            Royalty Percentage
                        </label>

                        <input
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={modal.royalty_percentage}
                            onChange={(event) =>
                                onChange({
                                    ...modal,
                                    royalty_percentage:
                                        event.target.value,
                                })
                            }
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            required
                        />
                    </div>

                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            disabled={creating}
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            disabled={creating}
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {creating ? 'Creating...' : 'Create Label'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-2xl font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}
