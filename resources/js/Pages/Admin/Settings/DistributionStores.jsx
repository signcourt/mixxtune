import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

const emptyStore = {
    name: '',
    slug: '',
    logo: null,
    is_active: true,
    default_selected: true,
    sort_order: 0,
};

export default function DistributionStores({ stores = [] }) {
    const [search, setSearch] = useState('');
    const [editingStore, setEditingStore] = useState(null);
    const [showForm, setShowForm] = useState(false);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm(emptyStore);

    const filteredStores = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return stores;
        }

        return stores.filter((store) =>
            `${store.name} ${store.slug}`
                .toLowerCase()
                .includes(query)
        );
    }, [stores, search]);

    const openAdd = () => {
        clearErrors();
        setEditingStore(null);
        reset();

        setData({
            ...emptyStore,
            sort_order: stores.length + 1,
        });

        setShowForm(true);
    };

    const openEdit = (store) => {
        clearErrors();
        setEditingStore(store);

        setData({
            name: store.name ?? '',
            slug: store.slug ?? '',
            logo: null,
            is_active: Boolean(store.is_active),
            default_selected: Boolean(store.default_selected),
            sort_order: Number(store.sort_order ?? 0),
        });

        setShowForm(true);

        window.setTimeout(() => {
            document
                .getElementById('distribution-store-form')
                ?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
        }, 100);
    };

    const closeForm = () => {
        clearErrors();
        reset();
        setEditingStore(null);
        setShowForm(false);
    };

    const submit = (event) => {
        event.preventDefault();

        const url = editingStore
            ? `/settings/distribution-stores/${editingStore.id}`
            : '/settings/distribution-stores';

        post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: closeForm,
        });
    };

    const toggleStore = (store) => {
        router.post(
            `/settings/distribution-stores/${store.id}/toggle`,
            {},
            { preserveScroll: true }
        );
    };

    const deleteStore = (store) => {
        if (!window.confirm(`Delete "${store.name}"?`)) {
            return;
        }

        router.delete(
            `/settings/distribution-stores/${store.id}`,
            { preserveScroll: true }
        );
    };

    return (
        <AdminLayout title="DSP Management">
            <Head title="DSP Management" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            DSP Management
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Add, edit and manage digital distribution stores.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={openAdd}
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                    >
                        + Add DSP
                    </button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat label="Total DSPs" value={stores.length} />

                    <Stat
                        label="Active"
                        value={stores.filter((store) => store.is_active).length}
                    />

                    <Stat
                        label="Inactive"
                        value={stores.filter((store) => !store.is_active).length}
                    />

                    <Stat
                        label="Auto Selected"
                        value={
                            stores.filter(
                                (store) => store.default_selected
                            ).length
                        }
                    />
                </div>

                {showForm && (
                    <form
                        id="distribution-store-form"
                        onSubmit={submit}
                        className="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h2 className="text-xl font-semibold text-slate-900">
                                    {editingStore
                                        ? 'Edit DSP'
                                        : 'Add New DSP'}
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    Active stores appear automatically in release distribution.
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={closeForm}
                                className="rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-100"
                            >
                                Close
                            </button>
                        </div>

                        <div className="mt-6 grid gap-5 md:grid-cols-2">
                            <Field
                                label="DSP Name"
                                required
                                error={errors.name}
                            >
                                <input
                                    className={inputClass}
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    placeholder="Spotify"
                                />
                            </Field>

                            <Field label="Slug" error={errors.slug}>
                                <input
                                    className={inputClass}
                                    value={data.slug}
                                    onChange={(event) =>
                                        setData('slug', event.target.value)
                                    }
                                    placeholder="spotify"
                                />
                            </Field>

                            <Field label="Store Logo" error={errors.logo}>
                                <input
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp,.svg,image/*"
                                    className={inputClass}
                                    onChange={(event) =>
                                        setData(
                                            'logo',
                                            event.target.files?.[0] ?? null
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Display Order"
                                error={errors.sort_order}
                            >
                                <input
                                    type="number"
                                    min="0"
                                    className={inputClass}
                                    value={data.sort_order}
                                    onChange={(event) =>
                                        setData(
                                            'sort_order',
                                            Number(event.target.value)
                                        )
                                    }
                                />
                            </Field>
                        </div>

                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            <Check
                                label="Active DSP"
                                description="DSP release wizard में दिखाई देगा।"
                                checked={data.is_active}
                                onChange={(value) =>
                                    setData('is_active', value)
                                }
                            />

                            <Check
                                label="Default Selected"
                                description="Release बनाते समय पहले से selected रहेगा।"
                                checked={data.default_selected}
                                onChange={(value) =>
                                    setData('default_selected', value)
                                }
                            />
                        </div>

                        <div className="mt-6 flex justify-end">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                {processing
                                    ? 'Saving...'
                                    : editingStore
                                      ? 'Update DSP'
                                      : 'Add DSP'}
                            </button>
                        </div>
                    </form>
                )}

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="font-semibold text-slate-900">
                                Distribution Stores
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                {filteredStores.length} stores shown
                            </p>
                        </div>

                        <input
                            type="search"
                            value={search}
                            onChange={(event) =>
                                setSearch(event.target.value)
                            }
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm lg:max-w-sm"
                            placeholder="Search DSP..."
                        />
                    </div>

                    {filteredStores.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <div className="text-4xl">♫</div>

                            <h3 className="mt-4 text-lg font-semibold text-slate-900">
                                No DSPs found
                            </h3>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {filteredStores.map((store) => (
                                <div
                                    key={store.id}
                                    className="grid gap-4 px-6 py-5 lg:grid-cols-[70px_minmax(220px,1fr)_120px_150px_130px_minmax(250px,auto)] lg:items-center"
                                >
                                    <div className="flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                        {store.logo_path ? (
                                            <img
                                                src={`/storage/${store.logo_path}`}
                                                alt={store.name}
                                                className="h-full w-full object-contain p-2"
                                            />
                                        ) : (
                                            <span className="text-lg font-bold text-slate-500">
                                                {store.name
                                                    .slice(0, 1)
                                                    .toUpperCase()}
                                            </span>
                                        )}
                                    </div>

                                    <div className="min-w-0">
                                        <div className="truncate font-semibold text-slate-900">
                                            {store.name}
                                        </div>

                                        <div className="mt-1 truncate text-xs text-slate-500">
                                            {store.slug}
                                        </div>
                                    </div>

                                    <div className="text-sm text-slate-600">
                                        Order: {store.sort_order}
                                    </div>

                                    <div>
                                        <span
                                            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                                                store.is_active
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-600'
                                            }`}
                                        >
                                            {store.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </div>

                                    <div className="text-sm text-slate-600">
                                        {store.default_selected
                                            ? 'Auto Select'
                                            : 'Manual'}
                                    </div>

                                    <div className="flex flex-wrap gap-2 lg:justify-end">
                                        <button
                                            type="button"
                                            onClick={() => openEdit(store)}
                                            className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                toggleStore(store)
                                            }
                                            className={`rounded-lg px-3 py-2 text-xs font-semibold text-white ${
                                                store.is_active
                                                    ? 'bg-amber-500 hover:bg-amber-600'
                                                    : 'bg-emerald-600 hover:bg-emerald-700'
                                            }`}
                                        >
                                            {store.is_active
                                                ? 'Deactivate'
                                                : 'Activate'}
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                deleteStore(store)
                                            }
                                            className="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm text-slate-500">{label}</div>
            <div className="mt-2 text-3xl font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function Field({ label, required = false, error, children }) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-slate-700">
                {label}
                {required && (
                    <span className="ml-1 text-red-500">*</span>
                )}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function Check({
    label,
    description,
    checked,
    onChange,
}) {
    return (
        <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) =>
                    onChange(event.target.checked)
                }
                className="mt-1 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
            />

            <div>
                <div className="text-sm font-semibold text-slate-900">
                    {label}
                </div>

                <div className="mt-1 text-xs text-slate-500">
                    {description}
                </div>
            </div>
        </label>
    );
}
