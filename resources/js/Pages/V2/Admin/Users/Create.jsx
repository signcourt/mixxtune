import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const permissionOptions = [
    [
        'can_view_catalogue',
        'View Catalogue',
    ],
    [
        'can_create_releases',
        'Create Releases',
    ],
    [
        'can_manage_releases',
        'Manage Releases',
    ],
    [
        'can_view_reports',
        'View Reports',
    ],
    [
        'can_view_royalties',
        'View Royalties',
    ],
    [
        'can_manage_wallet',
        'Manage Wallet',
    ],
    [
        'can_manage_withdrawals',
        'Manage Withdrawals',
    ],
    [
        'can_manage_users',
        'Manage Users',
    ],
    [
        'can_manage_support',
        'Manage Support',
    ],
    [
        'can_manage_settings',
        'Manage Settings',
    ],
    [
        'can_manage_delivery',
        'Manage Delivery',
    ],
    [
        'can_manage_identifiers',
        'Manage ISRC / UPC',
    ],
];

export default function Create({
    role = 'super_admin',
    artists = [],
    labels = [],
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        name: '',
        username: '',
        email: '',
        phone: '',
        country: 'India',
        role: 'admin',
        account_status: 'active',
        send_invitation: true,
        artist_ids: [],
        label_ids: [],
        permissions: Object.fromEntries(
            permissionOptions.map(
                ([field]) => [
                    field,
                    false,
                ]
            )
        ),
    });

    const toggleSelection = (
        field,
        id
    ) => {
        const numberId = Number(id);
        const selected = data[field];

        setData(
            field,
            selected.includes(numberId)
                ? selected.filter(
                      (item) =>
                          item !== numberId
                  )
                : [
                      ...selected,
                      numberId,
                  ]
        );
    };

    const togglePermission = (
        field,
        checked
    ) => {
        setData('permissions', {
            ...data.permissions,
            [field]: checked,
        });
    };

    const applyRoleDefaults = (
        selectedRole
    ) => {
        const enabled = new Set();

        if (selectedRole === 'admin') {
            [
                'can_view_catalogue',
                'can_manage_releases',
                'can_view_reports',
                'can_view_royalties',
                'can_manage_support',
                'can_manage_delivery',
                'can_manage_identifiers',
            ].forEach((item) =>
                enabled.add(item)
            );
        }

        if (selectedRole === 'label') {
            [
                'can_view_catalogue',
                'can_create_releases',
                'can_view_reports',
                'can_view_royalties',
                'can_manage_wallet',
                'can_manage_withdrawals',
            ].forEach((item) =>
                enabled.add(item)
            );
        }

        if (selectedRole === 'artist') {
            [
                'can_view_catalogue',
                'can_create_releases',
                'can_view_reports',
                'can_view_royalties',
            ].forEach((item) =>
                enabled.add(item)
            );
        }

        setData((current) => ({
            ...current,
            role: selectedRole,
            artist_ids:
                selectedRole === 'admin'
                    ? current.artist_ids
                    : [],
            label_ids:
                selectedRole === 'admin'
                    ? current.label_ids
                    : [],
            permissions:
                Object.fromEntries(
                    permissionOptions.map(
                        ([field]) => [
                            field,
                            enabled.has(
                                field
                            ),
                        ]
                    )
                ),
        }));
    };

    const selectAll = (
        field,
        items
    ) => {
        setData(
            field,
            items.map((item) =>
                Number(item.id)
            )
        );
    };

    const generateUsername = () => {
        const source =
            data.name || data.email || 'user';

        const generated = source
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 40);

        setData(
            'username',
            generated.length >= 4
                ? generated
                : `${generated || 'user'}-account`
        );
    };

    const submit = (event) => {
        event.preventDefault();

        post('/v2/admin/users', {
            preserveScroll: true,
        });
    };

    return (
        <PanelLayout
            role={role}
            title="Create User"
            subtitle="Create an admin, label or artist account"
        >
            <Head title="Create User" />

            <form
                onSubmit={submit}
                className="space-y-6"
            >
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between gap-4">
                        <h2 className="text-lg font-semibold text-slate-900">
                            Account Details
                        </h2>

                        <Link
                            href="/v2/admin/users"
                            className="text-sm font-semibold text-slate-500 hover:text-slate-800"
                        >
                            Back to Users
                        </Link>
                    </div>

                    <div className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <Field
                            label="Full Name"
                            value={data.name}
                            error={errors.name}
                            required
                            onChange={(value) =>
                                setData(
                                    'name',
                                    value
                                )
                            }
                        />

                        <div>
                            <Field
                                label="Username"
                                value={data.username}
                                error={errors.username}
                                onChange={(value) =>
                                    setData(
                                        'username',
                                        value
                                            .toLowerCase()
                                            .replace(
                                                /[^a-z0-9-]/g,
                                                ''
                                            )
                                    )
                                }
                            />

                            <button
                                type="button"
                                onClick={generateUsername}
                                className="mt-2 text-xs font-semibold text-violet-600 hover:text-violet-700"
                            >
                                Generate unique username
                            </button>

                            <p className="mt-1 text-xs text-slate-400">
                                Leave blank to generate automatically.
                            </p>
                        </div>

                        <Field
                            label="Email"
                            type="email"
                            value={data.email}
                            error={errors.email}
                            required
                            onChange={(value) =>
                                setData(
                                    'email',
                                    value
                                )
                            }
                        />

                        <Field
                            label="Phone"
                            value={data.phone}
                            error={errors.phone}
                            onChange={(value) =>
                                setData(
                                    'phone',
                                    value
                                )
                            }
                        />

                        <Field
                            label="Country"
                            value={data.country}
                            error={errors.country}
                            onChange={(value) =>
                                setData(
                                    'country',
                                    value
                                )
                            }
                        />

                        <label>
                            <span className="text-sm font-semibold text-slate-700">
                                Role
                            </span>

                            <select
                                value={data.role}
                                onChange={(event) =>
                                    applyRoleDefaults(
                                        event.target
                                            .value
                                    )
                                }
                                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="admin">
                                    Admin
                                </option>

                                <option value="label">
                                    Label
                                </option>

                                <option value="artist">
                                    Artist
                                </option>
                            </select>
                        </label>

                        <label>
                            <span className="text-sm font-semibold text-slate-700">
                                Account Status
                            </span>

                            <select
                                value={
                                    data.account_status
                                }
                                onChange={(event) =>
                                    setData(
                                        'account_status',
                                        event.target
                                            .value
                                    )
                                }
                                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="active">
                                    Active
                                </option>

                                <option value="pending">
                                    Pending
                                </option>

                                <option value="suspended">
                                    Suspended
                                </option>
                            </select>
                        </label>
                    </div>

                    <label className="mt-5 flex items-center gap-3 rounded-xl border border-violet-200 bg-violet-50 p-4">
                        <input
                            type="checkbox"
                            checked={
                                data.send_invitation
                            }
                            onChange={(event) =>
                                setData(
                                    'send_invitation',
                                    event.target
                                        .checked
                                )
                            }
                        />

                        <div>
                            <div className="text-sm font-semibold text-violet-800">
                                Send Invitation
                            </div>

                            <div className="text-xs text-violet-600">
                                Generate a secure password setup invitation valid for seven days.
                            </div>
                        </div>
                    </label>
                </section>

                {data.role === 'admin' && (
                    <div className="grid gap-6 xl:grid-cols-2">
                        <SelectionBox
                            title="Assign Artists"
                            items={artists}
                            selected={
                                data.artist_ids
                            }
                            nameKey="stage_name"
                            onToggle={(id) =>
                                toggleSelection(
                                    'artist_ids',
                                    id
                                )
                            }
                            onSelectAll={() =>
                                selectAll(
                                    'artist_ids',
                                    artists
                                )
                            }
                            onClear={() =>
                                setData(
                                    'artist_ids',
                                    []
                                )
                            }
                        />

                        <SelectionBox
                            title="Assign Labels"
                            items={labels}
                            selected={
                                data.label_ids
                            }
                            nameKey="name"
                            onToggle={(id) =>
                                toggleSelection(
                                    'label_ids',
                                    id
                                )
                            }
                            onSelectAll={() =>
                                selectAll(
                                    'label_ids',
                                    labels
                                )
                            }
                            onClear={() =>
                                setData(
                                    'label_ids',
                                    []
                                )
                            }
                        />
                    </div>
                )}

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold text-slate-900">
                            Panel Permissions
                        </h2>

                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() =>
                                    setData(
                                        'permissions',
                                        Object.fromEntries(
                                            permissionOptions.map(
                                                ([
                                                    field,
                                                ]) => [
                                                    field,
                                                    true,
                                                ]
                                            )
                                        )
                                    )
                                }
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700"
                            >
                                Select All
                            </button>

                            <button
                                type="button"
                                onClick={() =>
                                    setData(
                                        'permissions',
                                        Object.fromEntries(
                                            permissionOptions.map(
                                                ([
                                                    field,
                                                ]) => [
                                                    field,
                                                    false,
                                                ]
                                            )
                                        )
                                    )
                                }
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {permissionOptions.map(
                            ([field, label]) => (
                                <label
                                    key={field}
                                    className={[
                                        'flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition',
                                        data.permissions[
                                            field
                                        ]
                                            ? 'border-violet-400 bg-violet-50'
                                            : 'border-slate-200 bg-white',
                                    ].join(
                                        ' '
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        checked={
                                            data
                                                .permissions[
                                                field
                                            ]
                                        }
                                        onChange={(
                                            event
                                        ) =>
                                            togglePermission(
                                                field,
                                                event
                                                    .target
                                                    .checked
                                            )
                                        }
                                    />

                                    <span className="text-sm font-semibold text-slate-700">
                                        {label}
                                    </span>
                                </label>
                            )
                        )}
                    </div>
                </section>

                {Object.keys(errors).length >
                    0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        Some information could not be saved. Please review the fields.
                    </div>
                )}

                <div className="sticky bottom-4 z-20 flex justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                    <Link
                        href="/v2/admin/users"
                        className="rounded-xl border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-7 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                    >
                        {processing
                            ? 'Creating...'
                            : 'Create User'}
                    </button>
                </div>
            </form>
        </PanelLayout>
    );
}

function Field({
    label,
    value,
    onChange,
    type = 'text',
    error,
    required = false,
}) {
    return (
        <label>
            <span className="text-sm font-semibold text-slate-700">
                {label}
                {required && (
                    <span className="ml-1 text-red-500">
                        *
                    </span>
                )}
            </span>

            <input
                type={type}
                value={value}
                onChange={(event) =>
                    onChange(
                        event.target.value
                    )
                }
                className={[
                    'mt-2 w-full rounded-xl border px-4 py-3 text-sm',
                    error
                        ? 'border-red-400'
                        : 'border-slate-300',
                ].join(' ')}
            />

            {error && (
                <div className="mt-1 text-xs text-red-600">
                    {error}
                </div>
            )}
        </label>
    );
}

function SelectionBox({
    title,
    items,
    selected,
    nameKey,
    onToggle,
    onSelectAll,
    onClear,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">
                        {title}
                    </h2>

                    <div className="mt-1 text-xs text-slate-500">
                        {selected.length} selected
                    </div>
                </div>

                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={onSelectAll}
                        className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold"
                    >
                        Select All
                    </button>

                    <button
                        type="button"
                        onClick={onClear}
                        className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <div className="mt-4 max-h-[440px] space-y-2 overflow-y-auto">
                {items.map((item) => {
                    const checked =
                        selected.includes(
                            Number(item.id)
                        );

                    return (
                        <label
                            key={item.id}
                            className={[
                                'flex cursor-pointer items-center gap-3 rounded-xl border p-4',
                                checked
                                    ? 'border-violet-400 bg-violet-50'
                                    : 'border-slate-200',
                            ].join(' ')}
                        >
                            <input
                                type="checkbox"
                                checked={checked}
                                onChange={() =>
                                    onToggle(item.id)
                                }
                            />

                            <div className="min-w-0">
                                <div className="truncate text-sm font-semibold text-slate-900">
                                    {item[nameKey]}
                                </div>

                                <div className="truncate text-xs text-slate-500">
                                    {item.email ??
                                        'No email'}
                                </div>
                            </div>
                        </label>
                    );
                })}

                {items.length === 0 && (
                    <div className="rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">
                        No records available.
                    </div>
                )}
            </div>
        </section>
    );
}
