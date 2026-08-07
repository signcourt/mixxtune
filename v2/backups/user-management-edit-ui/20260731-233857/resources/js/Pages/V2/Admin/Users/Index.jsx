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
