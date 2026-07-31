import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function AdminManagement({
    admins,
    filters = {},
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [showCreate, setShowCreate] = useState(false);

    const submitSearch = (event) => {
        event.preventDefault();

        router.get(
            '/admin-management',
            {
                search: search || undefined,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    const toggleStatus = (admin) => {
        router.patch(
            `/admin-management/${admin.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    const deleteAdmin = (admin) => {
        if (!window.confirm(`Delete ${admin.name}?`)) {
            return;
        }

        router.delete(`/admin-management/${admin.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="Admin Management">
            <Head title="Admin Management" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            Admin Management
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Create admins and assign Labels, Artists and permissions.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => setShowCreate(true)}
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white"
                    >
                        + Create Admin
                    </button>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <form
                        onSubmit={submitSearch}
                        className="flex flex-col gap-3 sm:flex-row"
                    >
                        <input
                            type="search"
                            value={search}
                            onChange={(event) =>
                                setSearch(event.target.value)
                            }
                            placeholder="Search admin by name or email..."
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <button
                            type="submit"
                            className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white"
                        >
                            Search
                        </button>
                    </form>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="hidden border-b border-slate-200 bg-slate-50 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid lg:grid-cols-[minmax(240px,1.4fr)_minmax(220px,1.2fr)_140px_140px_140px_260px]">
                        <div>Admin</div>
                        <div>Email</div>
                        <div>Labels</div>
                        <div>Artists</div>
                        <div>Status</div>
                        <div className="text-right">
                            Actions
                        </div>
                    </div>

                    {(admins?.data ?? []).length === 0 ? (
                        <div className="px-6 py-20 text-center text-slate-500">
                            No admins found.
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {admins.data.map((admin) => (
                                <div
                                    key={admin.id}
                                    className="grid gap-4 px-6 py-5 lg:grid-cols-[minmax(240px,1.4fr)_minmax(220px,1.2fr)_140px_140px_140px_260px] lg:items-center"
                                >
                                    <div>
                                        <div className="font-semibold text-slate-900">
                                            {admin.name}
                                        </div>

                                        <div className="mt-1 text-xs text-slate-500">
                                            ID: {admin.id}
                                        </div>
                                    </div>

                                    <div className="text-sm text-slate-600">
                                        {admin.email}
                                    </div>

                                    <div>
                                        <span className="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">
                                            {admin.assigned_labels_count ?? 0}
                                        </span>
                                    </div>

                                    <div>
                                        <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                            {admin.assigned_artists_count ?? 0}
                                        </span>
                                    </div>

                                    <div>
                                        <span
                                            className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                                admin.account_status === 'active'
                                                    ? 'bg-emerald-50 text-emerald-700'
                                                    : 'bg-red-50 text-red-700'
                                            }`}
                                        >
                                            {admin.account_status ?? 'active'}
                                        </span>
                                    </div>

                                    <div className="flex flex-wrap gap-2 lg:justify-end">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.get(
                                                    `/admin-management/${admin.id}/assignments`
                                                )
                                            }
                                            className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white"
                                        >
                                            Assignments
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                toggleStatus(admin)
                                            }
                                            className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700"
                                        >
                                            {admin.account_status === 'active'
                                                ? 'Suspend'
                                                : 'Activate'}
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                deleteAdmin(admin)
                                            }
                                            className="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600"
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

            {showCreate && (
                <CreateAdminModal
                    onClose={() => setShowCreate(false)}
                />
            )}
        </AdminLayout>
    );
}

function CreateAdminModal({ onClose }) {
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [processing, setProcessing] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        setProcessing(true);

        router.post('/admin-management', form, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
            <form
                onSubmit={submit}
                className="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl"
            >
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-slate-900">
                        Create Admin
                    </h2>

                    <button
                        type="button"
                        onClick={onClose}
                        className="text-slate-500"
                    >
                        Close
                    </button>
                </div>

                <div className="mt-6 space-y-4">
                    <input
                        value={form.name}
                        onChange={(event) =>
                            setForm({
                                ...form,
                                name: event.target.value,
                            })
                        }
                        placeholder="Admin name"
                        className="w-full rounded-xl border border-slate-300 px-4 py-3"
                        required
                    />

                    <input
                        type="email"
                        value={form.email}
                        onChange={(event) =>
                            setForm({
                                ...form,
                                email: event.target.value,
                            })
                        }
                        placeholder="Email"
                        className="w-full rounded-xl border border-slate-300 px-4 py-3"
                        required
                    />

                    <input
                        type="password"
                        value={form.password}
                        onChange={(event) =>
                            setForm({
                                ...form,
                                password: event.target.value,
                            })
                        }
                        placeholder="Password"
                        className="w-full rounded-xl border border-slate-300 px-4 py-3"
                        required
                    />

                    <input
                        type="password"
                        value={form.password_confirmation}
                        onChange={(event) =>
                            setForm({
                                ...form,
                                password_confirmation:
                                    event.target.value,
                            })
                        }
                        placeholder="Confirm password"
                        className="w-full rounded-xl border border-slate-300 px-4 py-3"
                        required
                    />
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {processing
                            ? 'Creating...'
                            : 'Create Admin'}
                    </button>
                </div>
            </form>
        </div>
    );
}
