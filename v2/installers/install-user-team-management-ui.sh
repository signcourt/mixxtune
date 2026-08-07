#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/user-team-management-ui/$STAMP"

mkdir -p \
    "$BACKUP" \
    resources/js/Pages/V2/Admin/Users \
    v2/runtime/state

echo "=============================================="
echo "INSTALLING USER & TEAM MANAGEMENT UI"
echo "=============================================="

for FILE in \
    resources/js/Pages/V2/Admin/Users/Index.jsx \
    resources/js/Pages/V2/Admin/Users/Create.jsx
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done

echo "[1/3] Creating Users Index page..."

cat > resources/js/Pages/V2/Admin/Users/Index.jsx <<'JSX'
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import {
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'super_admin',
    users = {},
    summary = {},
    filters = {},
}) {
    const [search, setSearch] =
        useState(filters.search ?? '');

    const applyFilters = (changes = {}) => {
        router.get(
            '/v2/admin/users',
            {
                search,
                role: filters.role ?? '',
                status: filters.status ?? '',
                ...changes,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const toggleStatus = (user) => {
        const action =
            user.account_status === 'active'
                ? 'suspend'
                : 'activate';

        if (
            !window.confirm(
                `Are you sure you want to ${action} ${user.name}?`
            )
        ) {
            return;
        }

        router.post(
            `/v2/admin/users/${user.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    const resendInvitation = (user) => {
        if (
            !window.confirm(
                `Generate a new invitation for ${user.email}?`
            )
        ) {
            return;
        }

        router.post(
            `/v2/admin/users/${user.id}/resend-invitation`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="User Management"
            subtitle="Manage admins, labels, artists and team accounts"
        >
            <Head title="User Management" />

            <div className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
                    <SummaryCard
                        label="Total Users"
                        value={summary.total}
                    />

                    <SummaryCard
                        label="Super Admins"
                        value={summary.super_admins}
                    />

                    <SummaryCard
                        label="Admins"
                        value={summary.admins}
                    />

                    <SummaryCard
                        label="Labels"
                        value={summary.labels}
                    />

                    <SummaryCard
                        label="Artists"
                        value={summary.artists}
                    />

                    <SummaryCard
                        label="Invitations"
                        value={
                            summary.pending_invitations
                        }
                    />

                    <SummaryCard
                        label="Suspended"
                        value={summary.suspended}
                    />
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div className="grid flex-1 gap-3 md:grid-cols-[1fr_180px_180px_auto]">
                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(
                                        event.target.value
                                    )
                                }
                                onKeyDown={(event) => {
                                    if (
                                        event.key ===
                                        'Enter'
                                    ) {
                                        applyFilters({
                                            search,
                                        });
                                    }
                                }}
                                placeholder="Search name, email, phone or label..."
                                className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />

                            <select
                                value={
                                    filters.role ?? ''
                                }
                                onChange={(event) =>
                                    applyFilters({
                                        role:
                                            event.target
                                                .value,
                                    })
                                }
                                className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="">
                                    All Roles
                                </option>

                                <option value="super_admin">
                                    Super Admin
                                </option>

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

                            <select
                                value={
                                    filters.status ??
                                    ''
                                }
                                onChange={(event) =>
                                    applyFilters({
                                        status:
                                            event.target
                                                .value,
                                    })
                                }
                                className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="">
                                    All Statuses
                                </option>

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

                            <button
                                type="button"
                                onClick={() =>
                                    applyFilters({
                                        search,
                                    })
                                }
                                className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Search
                            </button>
                        </div>

                        <Link
                            href="/v2/admin/users/create"
                            className="rounded-xl bg-violet-600 px-5 py-3 text-center text-sm font-semibold text-white hover:bg-violet-700"
                        >
                            + Create User
                        </Link>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'User',
                                        'Role',
                                        'Status',
                                        'KYC',
                                        'Assignments',
                                        'Invitation',
                                        'Last Login',
                                        'Actions',
                                    ].map(
                                        (heading) => (
                                            <th
                                                key={
                                                    heading
                                                }
                                                className="whitespace-nowrap px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                                            >
                                                {
                                                    heading
                                                }
                                            </th>
                                        )
                                    )}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {(users.data ?? []).map(
                                    (user) => (
                                        <tr
                                            key={user.id}
                                            className="hover:bg-slate-50"
                                        >
                                            <Cell>
                                                <div className="font-semibold text-slate-900">
                                                    {
                                                        user.name
                                                    }
                                                </div>

                                                <div className="text-xs text-slate-500">
                                                    {
                                                        user.email
                                                    }
                                                </div>

                                                {user.phone && (
                                                    <div className="mt-1 text-xs text-slate-400">
                                                        {
                                                            user.phone
                                                        }
                                                    </div>
                                                )}
                                            </Cell>

                                            <Cell>
                                                <RoleBadge
                                                    role={
                                                        user.role
                                                    }
                                                />
                                            </Cell>

                                            <Cell>
                                                <StatusBadge
                                                    status={
                                                        user.account_status
                                                    }
                                                />
                                            </Cell>

                                            <Cell>
                                                <span className="capitalize">
                                                    {user.kyc_status ??
                                                        'pending'}
                                                </span>
                                            </Cell>

                                            <Cell>
                                                <div className="space-y-1 text-xs">
                                                    <div>
                                                        Artists:{' '}
                                                        {
                                                            (
                                                                user.assigned_artists ??
                                                                []
                                                            )
                                                                .length
                                                        }
                                                    </div>

                                                    <div>
                                                        Labels:{' '}
                                                        {
                                                            (
                                                                user.assigned_labels ??
                                                                []
                                                            )
                                                                .length
                                                        }
                                                    </div>
                                                </div>
                                            </Cell>

                                            <Cell>
                                                <div className="space-y-1">
                                                    <span className="capitalize">
                                                        {user.invitation_status ??
                                                            '—'}
                                                    </span>

                                                    {user.invitation_count >
                                                        0 && (
                                                        <div className="text-xs text-slate-400">
                                                            Sent{' '}
                                                            {
                                                                user.invitation_count
                                                            }{' '}
                                                            time(s)
                                                        </div>
                                                    )}
                                                </div>
                                            </Cell>

                                            <Cell>
                                                {user.last_login_at ??
                                                    'Never'}
                                            </Cell>

                                            <Cell>
                                                <div className="flex min-w-[220px] flex-wrap gap-2">
                                                    {user.role ===
                                                        'admin' && (
                                                        <Link
                                                            href={`/v2/admin/admins/${user.id}`}
                                                            className="rounded-lg border border-violet-300 bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-700"
                                                        >
                                                            Assign
                                                        </Link>
                                                    )}

                                                    {user.role !==
                                                        'super_admin' && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                toggleStatus(
                                                                    user
                                                                )
                                                            }
                                                            className={[
                                                                'rounded-lg px-3 py-2 text-xs font-semibold text-white',
                                                                user.account_status ===
                                                                'active'
                                                                    ? 'bg-red-600 hover:bg-red-700'
                                                                    : 'bg-emerald-600 hover:bg-emerald-700',
                                                            ].join(
                                                                ' '
                                                            )}
                                                        >
                                                            {user.account_status ===
                                                            'active'
                                                                ? 'Suspend'
                                                                : 'Activate'}
                                                        </button>
                                                    )}

                                                    {user.invitation_status ===
                                                        'pending' && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                resendInvitation(
                                                                    user
                                                                )
                                                            }
                                                            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                        >
                                                            Resend Invite
                                                        </button>
                                                    )}
                                                </div>
                                            </Cell>
                                        </tr>
                                    )
                                )}

                                {(users.data ?? [])
                                    .length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="8"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            No users found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <Pagination
                    links={users.links}
                />
            </div>
        </PanelLayout>
    );
}

function SummaryCard({
    label,
    value = 0,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-3xl font-bold text-slate-900">
                {value ?? 0}
            </div>
        </div>
    );
}

function RoleBadge({ role }) {
    const classes = {
        super_admin:
            'bg-purple-100 text-purple-700',
        admin:
            'bg-blue-100 text-blue-700',
        label:
            'bg-amber-100 text-amber-700',
        artist:
            'bg-emerald-100 text-emerald-700',
    };

    return (
        <span
            className={[
                'rounded-full px-3 py-1 text-xs font-semibold capitalize',
                classes[role] ??
                    'bg-slate-100 text-slate-700',
            ].join(' ')}
        >
            {(role ?? 'unknown').replaceAll(
                '_',
                ' '
            )}
        </span>
    );
}

function StatusBadge({ status }) {
    const classes = {
        active:
            'bg-emerald-100 text-emerald-700',
        pending:
            'bg-amber-100 text-amber-700',
        suspended:
            'bg-red-100 text-red-700',
    };

    return (
        <span
            className={[
                'rounded-full px-3 py-1 text-xs font-semibold capitalize',
                classes[status] ??
                    'bg-slate-100 text-slate-700',
            ].join(' ')}
        >
            {status ?? 'unknown'}
        </span>
    );
}

function Cell({ children }) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}

function Pagination({ links = [] }) {
    if (!links?.length) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-center gap-2">
            {links.map((link, index) => (
                <Link
                    key={index}
                    href={link.url ?? '#'}
                    preserveScroll
                    className={[
                        'rounded-lg border px-3 py-2 text-sm',
                        link.active
                            ? 'border-violet-600 bg-violet-600 text-white'
                            : 'border-slate-300 bg-white text-slate-700',
                        !link.url
                            ? 'pointer-events-none opacity-40'
                            : '',
                    ].join(' ')}
                    dangerouslySetInnerHTML={{
                        __html: link.label,
                    }}
                />
            ))}
        </div>
    );
}
JSX

echo "[2/3] Creating Create User page..."

cat > resources/js/Pages/V2/Admin/Users/Create.jsx <<'JSX'
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
JSX

echo "[3/3] Running checks and build..."

test -f \
resources/js/Pages/V2/Admin/Users/Index.jsx

test -f \
resources/js/Pages/V2/Admin/Users/Create.jsx

npm run build

php artisan optimize:clear

printf '{\n  "module": "UserTeamManagementUI",\n  "installed": true,\n  "version": "4.7.1",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/user-team-management-ui-installed.json

echo ""
echo "=============================================="
echo "USER & TEAM MANAGEMENT UI INSTALLED"
echo "=============================================="

cat \
v2/runtime/state/user-team-management-ui-installed.json

echo ""
echo "Pages:"
echo "https://admin.mixxtune.com/v2/admin/users"
echo "https://admin.mixxtune.com/v2/admin/users/create"

echo ""
echo "Backup:"
echo "$BACKUP"
