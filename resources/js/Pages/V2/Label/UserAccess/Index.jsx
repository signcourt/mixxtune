import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    Pencil,
    Plus,
    Search,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const EMPTY_FORM = {
    email: '',
    permission_level: 'standard',
    scope_level: 'entire_label',
    permissions: [],
    artist_ids: [],
    label_ids: [],
    track_ids: [],
};

function statusClasses(status) {
    if (status === 'active') {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }

    if (status === 'suspended') {
        return 'bg-amber-50 text-amber-700 border-amber-200';
    }

    return 'bg-slate-100 text-slate-600 border-slate-200';
}

function PermissionRow({
    permissionKey,
    definition,
    checked,
    disabled,
    onChange,
}) {
    return (
        <label
            className={[
                'flex items-start gap-3 border-b border-slate-100 py-3 last:border-b-0',
                disabled
                    ? 'cursor-not-allowed opacity-45'
                    : 'cursor-pointer',
            ].join(' ')}
        >
            <input
                type="checkbox"
                checked={checked}
                disabled={disabled}
                onChange={() => onChange(permissionKey)}
                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
            />

            <div>
                <div className="text-sm font-medium text-slate-800">
                    {definition.label}
                </div>

                {definition.description && (
                    <div className="mt-0.5 text-xs leading-5 text-slate-500">
                        {definition.description}
                    </div>
                )}
            </div>
        </label>
    );
}

function RadioChoice({
    checked,
    title,
    subtitle,
    onClick,
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex w-full items-start gap-3 text-left"
        >
            <span
                className={[
                    'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border',
                    checked
                        ? 'border-blue-600'
                        : 'border-slate-400',
                ].join(' ')}
            >
                {checked && (
                    <span className="h-2 w-2 rounded-full bg-blue-600" />
                )}
            </span>

            <span>
                <span className="block text-sm font-semibold text-slate-800">
                    {title}
                </span>

                {subtitle && (
                    <span className="mt-1 block text-xs leading-5 text-slate-500">
                        {subtitle}
                    </span>
                )}
            </span>
        </button>
    );
}

export default function Index({
    members = [],
    permissionCatalogue = {},
    artists = [],
    childLabels = [],
    tracks = [],
}) {
    const [open, setOpen] = useState(false);
    const [editingMember, setEditingMember] = useState(null);

    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [accessFilter, setAccessFilter] = useState('');
    const [permissionFilter, setPermissionFilter] = useState('');

    const [scopeType, setScopeType] =
        useState('artist');

    const [scopeSearch, setScopeSearch] =
        useState('');

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm(EMPTY_FORM);

    const groupedPermissions = useMemo(() => {
        const groups = {};

        Object.entries(permissionCatalogue).forEach(
            ([key, definition]) => {
                if (definition.owner_only) {
                    return;
                }

                const group = definition.group || 'Other';

                if (!groups[group]) {
                    groups[group] = [];
                }

                groups[group].push({
                    key,
                    ...definition,
                });
            }
        );

        return groups;
    }, [permissionCatalogue]);

    const allowedPermissionKeys = useMemo(
        () =>
            Object.entries(permissionCatalogue)
                .filter(([, definition]) => {
                    if (definition.owner_only) {
                        return false;
                    }

                    return Boolean(
                        definition[data.permission_level]
                    );
                })
                .map(([key]) => key),
        [permissionCatalogue, data.permission_level]
    );

    const filteredMembers = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return members.filter((member) => {
            const matchesSearch =
                !needle ||
                (member.name || '')
                    .toLowerCase()
                    .includes(needle) ||
                (member.email || '')
                    .toLowerCase()
                    .includes(needle);

            const matchesStatus =
                !statusFilter ||
                member.status === statusFilter;

            const matchesAccess =
                !accessFilter ||
                member.scope_level === accessFilter;

            const matchesPermission =
                !permissionFilter ||
                member.permission_level === permissionFilter;

            return (
                matchesSearch &&
                matchesStatus &&
                matchesAccess &&
                matchesPermission
            );
        });
    }, [
        members,
        search,
        statusFilter,
        accessFilter,
        permissionFilter,
    ]);

    const closeForm = () => {
        setOpen(false);
        setEditingMember(null);
        reset();
        clearErrors();
    };

    const openCreate = () => {
        reset();
        clearErrors();
        setEditingMember(null);
        setScopeType('artist');
        setScopeSearch('');
        setOpen(true);
    };

    const openEdit = (member) => {
        clearErrors();

        setEditingMember(member);

        setData({
            email: member.email || '',
            permission_level:
                member.permission_level || 'standard',
            scope_level:
                member.scope_level || 'entire_label',
            permissions:
                member.permissions || [],
            artist_ids:
                (member.scopes || [])
                    .filter(
                        (scope) =>
                            scope.type === 'artist'
                    )
                    .map((scope) => Number(scope.id)),
            label_ids:
                (member.scopes || [])
                    .filter(
                        (scope) =>
                            scope.type === 'label'
                    )
                    .map((scope) => Number(scope.id)),
            track_ids:
                (member.scopes || [])
                    .filter(
                        (scope) =>
                            scope.type === 'track'
                    )
                    .map((scope) => Number(scope.id)),
        });

        const scopeTypes = new Set(
            (member.scopes || []).map(
                (scope) => scope.type
            )
        );

        if (scopeTypes.has('track')) {
            setScopeType('track');
        } else if (scopeTypes.has('label')) {
            setScopeType('label');
        } else {
            setScopeType('artist');
        }

        setScopeSearch('');
        setOpen(true);
    };

    const changeLevel = (level) => {
        const allowed = Object.entries(
            permissionCatalogue
        )
            .filter(
                ([, definition]) =>
                    !definition.owner_only &&
                    Boolean(definition[level])
            )
            .map(([key]) => key);

        setData((current) => ({
            ...current,
            permission_level: level,
            permissions:
                current.permissions.filter(
                    (permission) =>
                        allowed.includes(permission)
                ),
        }));
    };

    const togglePermission = (key) => {
        if (!allowedPermissionKeys.includes(key)) {
            return;
        }

        setData(
            'permissions',
            data.permissions.includes(key)
                ? data.permissions.filter(
                      (item) => item !== key
                  )
                : [...data.permissions, key]
        );
    };

    const toggleId = (field, id) => {
        const value = Number(id);
        const current = data[field] || [];

        setData(
            field,
            current.includes(value)
                ? current.filter(
                      (item) => item !== value
                  )
                : [...current, value]
        );
    };

    const submit = (event) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: closeForm,
        };

        if (editingMember) {
            put(
                `/v2/label/user-access/${editingMember.id}`,
                options
            );

            return;
        }

        post(
            '/v2/label/user-access',
            options
        );
    };

    const changeStatus = (member, status) => {
        router.patch(
            `/v2/label/user-access/${member.id}/status`,
            { status },
            {
                preserveScroll: true,
            }
        );
    };

    const removeMember = (member) => {
        if (
            !window.confirm(
                `Remove access for ${member.name || member.email}?`
            )
        ) {
            return;
        }

        router.delete(
            `/v2/label/user-access/${member.id}`,
            {
                preserveScroll: true,
            }
        );
    };

    if (open) {
        return (
            <PanelLayout
                role="label"
                title="User Access"
                subtitle="Manage users and their access"
            >
                <Head
                    title={
                        editingMember
                            ? 'Edit User Access'
                            : 'Add User Access'
                    }
                />

                <div className="mx-auto max-w-[1500px]">
                    <div className="mb-6 text-sm text-slate-500">
                        <button
                            type="button"
                            onClick={closeForm}
                            className="hover:text-blue-600"
                        >
                            Settings
                        </button>

                        <span className="mx-2">›</span>

                        <button
                            type="button"
                            onClick={closeForm}
                            className="hover:text-blue-600"
                        >
                            User Access
                        </button>

                        <span className="mx-2">›</span>

                        <span className="text-slate-800">
                            {editingMember
                                ? 'Edit User'
                                : 'Add a new user'}
                        </span>
                    </div>

                    <div className="mb-7 flex items-start justify-between border-b border-slate-200 pb-5">
                        <div>
                            <h1 className="text-2xl font-semibold text-slate-900">
                                {editingMember
                                    ? 'Edit user'
                                    : 'Add a new user'}
                            </h1>

                            <p className="mt-1 text-sm text-slate-500">
                                Configure account permissions and catalogue access.
                            </p>
                        </div>

                        <button
                            type="button"
                            onClick={closeForm}
                            className="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            title="Close"
                        >
                            <X size={20} />
                        </button>
                    </div>

                    <form onSubmit={submit}>
                        <div className="grid gap-0 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm xl:grid-cols-[0.9fr_1.4fr_1fr]">
                            <section className="border-b border-slate-200 p-6 xl:border-b-0 xl:border-r xl:p-7">
                                <h2 className="text-base font-semibold text-slate-900">
                                    Contact Information
                                </h2>

                                <p className="mt-1 text-xs leading-5 text-slate-500">
                                    Enter the Mixx Tune account email for this user.
                                </p>

                                <div className="mt-6">
                                    <label className="text-xs font-semibold text-slate-700">
                                        Email address
                                    </label>

                                    <input
                                        type="email"
                                        value={data.email}
                                        disabled={Boolean(editingMember)}
                                        onChange={(event) =>
                                            setData(
                                                'email',
                                                event.target.value
                                            )
                                        }
                                        placeholder="user@example.com"
                                        className="mt-2 block w-full rounded border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500"
                                    />

                                    {errors.email && (
                                        <p className="mt-2 text-xs text-red-600">
                                            {errors.email}
                                        </p>
                                    )}

                                    {editingMember && (
                                        <p className="mt-2 text-xs text-slate-500">
                                            Email cannot be changed while editing access.
                                        </p>
                                    )}
                                </div>

                                <div className="mt-8 rounded-md border border-blue-200 bg-blue-50 p-4 text-xs leading-5 text-blue-800">
                                    User Access and Revenue Sharing remain controlled by the Label Owner and cannot be delegated.
                                </div>
                            </section>

                            <section className="border-b border-slate-200 p-6 xl:border-b-0 xl:border-r xl:p-7">
                                <h2 className="text-base font-semibold text-slate-900">
                                    Permission Level
                                </h2>

                                <p className="mt-1 text-xs leading-5 text-slate-500">
                                    Choose the level first, then select the exact permissions.
                                </p>

                                <div className="relative mt-6">
                                    <select
                                        value={data.permission_level}
                                        onChange={(event) =>
                                            changeLevel(
                                                event.target.value
                                            )
                                        }
                                        className="block w-full appearance-none rounded-md border-slate-300 bg-white py-3 pl-3 pr-10 text-sm font-medium text-slate-800 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    >
                                        <option value="standard">
                                            Standard
                                        </option>

                                        <option value="advanced">
                                            Advanced
                                        </option>
                                    </select>

                                    <ChevronDown
                                        size={16}
                                        className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
                                    />
                                </div>

                                {errors.permission_level && (
                                    <p className="mt-3 text-xs text-red-600">
                                        {errors.permission_level}
                                    </p>
                                )}

                                <div className="mt-7 border-t border-slate-200 pt-5">
                                    <div className="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Permissions
                                    </div>

                                    <div className="space-y-5">
                                        {Object.entries(
                                            groupedPermissions
                                        ).map(
                                            ([group, permissions]) => (
                                                <div key={group}>
                                                    <div className="mb-1 text-sm font-semibold text-slate-800">
                                                        {group}
                                                    </div>

                                                    <div>
                                                        {permissions.map(
                                                            (permission) => {
                                                                const disabled =
                                                                    !Boolean(
                                                                        permission[
                                                                            data
                                                                                .permission_level
                                                                        ]
                                                                    );

                                                                return (
                                                                    <PermissionRow
                                                                        key={
                                                                            permission.key
                                                                        }
                                                                        permissionKey={
                                                                            permission.key
                                                                        }
                                                                        definition={
                                                                            permission
                                                                        }
                                                                        checked={data.permissions.includes(
                                                                            permission.key
                                                                        )}
                                                                        disabled={
                                                                            disabled
                                                                        }
                                                                        onChange={
                                                                            togglePermission
                                                                        }
                                                                    />
                                                                );
                                                            }
                                                        )}
                                                    </div>
                                                </div>
                                            )
                                        )}
                                    </div>
                                </div>
                            </section>

                            <section className="p-6 xl:p-7">
                                <h2 className="text-base font-semibold text-slate-900">
                                    This user has access to
                                </h2>

                                <p className="mt-1 text-xs leading-5 text-slate-500">
                                    Choose the catalogue scope available to this user.
                                </p>

                                <div className="relative mt-6">
                                    <select
                                        value={data.scope_level}
                                        onChange={(event) => {
                                            const value =
                                                event.target.value;

                                            setData(
                                                'scope_level',
                                                value
                                            );

                                            if (
                                                value ===
                                                'entire_label'
                                            ) {
                                                setScopeSearch('');
                                            }
                                        }}
                                        className="block w-full appearance-none rounded-md border-slate-300 bg-white py-3 pl-3 pr-10 text-sm font-medium text-slate-800 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    >
                                        <option value="entire_label">
                                            Entire Label
                                        </option>

                                        <option value="selected">
                                            Selected Catalogue
                                        </option>
                                    </select>

                                    <ChevronDown
                                        size={16}
                                        className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
                                    />
                                </div>

                                {errors.scope_level && (
                                    <p className="mt-3 text-xs text-red-600">
                                        {errors.scope_level}
                                    </p>
                                )}

                                {data.scope_level === 'selected' && (
                                    <div className="mt-7 space-y-5 border-t border-slate-200 pt-5">
                                        <div>
                                            <label className="text-xs font-semibold text-slate-700">
                                                Catalogue Type
                                            </label>

                                            <div className="relative mt-2">
                                                <select
                                                    value={scopeType}
                                                    onChange={(event) => {
                                                        setScopeType(
                                                            event.target.value
                                                        );
                                                        setScopeSearch('');
                                                    }}
                                                    className="block w-full appearance-none rounded-md border-slate-300 bg-white py-3 pl-3 pr-10 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                >
                                                    <option value="artist">
                                                        Artist
                                                    </option>

                                                    <option value="label">
                                                        Sub-Label
                                                    </option>

                                                    <option value="track">
                                                        Track
                                                    </option>
                                                </select>

                                                <ChevronDown
                                                    size={16}
                                                    className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <label className="text-xs font-semibold text-slate-700">
                                                Search Catalogue
                                            </label>

                                            <div className="relative mt-2">
                                                <Search
                                                    size={16}
                                                    className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                                                />

                                                <input
                                                    type="search"
                                                    value={scopeSearch}
                                                    onChange={(event) =>
                                                        setScopeSearch(
                                                            event.target.value
                                                        )
                                                    }
                                                    placeholder={
                                                        scopeType === 'artist'
                                                            ? 'Search artists...'
                                                            : scopeType === 'label'
                                                              ? 'Search sub-labels...'
                                                              : 'Search tracks, ISRC or release...'
                                                    }
                                                    className="block w-full rounded-md border-slate-300 py-3 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                />
                                            </div>
                                        </div>

                                        <div className="max-h-64 overflow-y-auto rounded-md border border-slate-200 bg-white">
                                            {(() => {
                                                const needle =
                                                    scopeSearch
                                                        .trim()
                                                        .toLowerCase();

                                                const items =
                                                    scopeType === 'artist'
                                                        ? artists
                                                        : scopeType === 'label'
                                                          ? childLabels
                                                          : tracks;

                                                const field =
                                                    scopeType === 'artist'
                                                        ? 'artist_ids'
                                                        : scopeType === 'label'
                                                          ? 'label_ids'
                                                          : 'track_ids';

                                                const filtered =
                                                    items.filter(
                                                        (item) => {
                                                            const haystack = [
                                                                item.name,
                                                                item.isrc,
                                                                item.release,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' ')
                                                                .toLowerCase();

                                                            return (
                                                                !needle ||
                                                                haystack.includes(
                                                                    needle
                                                                )
                                                            );
                                                        }
                                                    );

                                                if (!filtered.length) {
                                                    return (
                                                        <div className="p-4 text-xs text-slate-500">
                                                            No matching items found.
                                                        </div>
                                                    );
                                                }

                                                return filtered.map(
                                                    (item) => {
                                                        const selected =
                                                            (
                                                                data[field] ||
                                                                []
                                                            ).includes(
                                                                Number(
                                                                    item.id
                                                                )
                                                            );

                                                        return (
                                                            <label
                                                                key={`${scopeType}-${item.id}`}
                                                                className="flex cursor-pointer items-start gap-3 border-b border-slate-100 px-3 py-3 last:border-b-0 hover:bg-slate-50"
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    checked={
                                                                        selected
                                                                    }
                                                                    onChange={() =>
                                                                        toggleId(
                                                                            field,
                                                                            item.id
                                                                        )
                                                                    }
                                                                    className="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                                />

                                                                <span className="min-w-0">
                                                                    <span className="block truncate text-sm font-medium text-slate-700">
                                                                        {
                                                                            item.name
                                                                        }
                                                                    </span>

                                                                    {scopeType ===
                                                                        'track' && (
                                                                        <span className="mt-0.5 block truncate text-xs text-slate-500">
                                                                            {[
                                                                                item.release,
                                                                                item.isrc,
                                                                            ]
                                                                                .filter(
                                                                                    Boolean
                                                                                )
                                                                                .join(
                                                                                    ' • '
                                                                                )}
                                                                        </span>
                                                                    )}
                                                                </span>
                                                            </label>
                                                        );
                                                    }
                                                );
                                            })()}
                                        </div>

                                        <div className="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                            Selected: {data.artist_ids.length} Artist
                                            {data.artist_ids.length === 1
                                                ? ''
                                                : 's'}
                                            {' • '}
                                            {data.label_ids.length} Sub-Label
                                            {data.label_ids.length === 1
                                                ? ''
                                                : 's'}
                                            {' • '}
                                            {data.track_ids.length} Track
                                            {data.track_ids.length === 1
                                                ? ''
                                                : 's'}
                                        </div>

                                        {(errors.artist_ids ||
                                            errors.label_ids ||
                                            errors.track_ids) && (
                                            <p className="text-xs text-red-600">
                                                {errors.artist_ids ||
                                                    errors.label_ids ||
                                                    errors.track_ids}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </section>
                        </div>

                        <div className="sticky bottom-0 z-20 mt-6 flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50/95 px-4 py-4 backdrop-blur">
                            <button
                                type="button"
                                onClick={closeForm}
                                className="rounded border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {processing
                                    ? 'Saving...'
                                    : editingMember
                                      ? 'Save Changes'
                                      : 'Save and add user'}
                            </button>
                        </div>
                    </form>
                </div>
            </PanelLayout>
        );
    }

    return (
        <PanelLayout
            role="label"
            title="User Access"
            subtitle="Manage users who can access your label account"
        >
            <Head title="User Access" />

            <div className="mx-auto max-w-[1500px]">
                <div className="mb-6 text-sm text-slate-500">
                    <span>Settings</span>
                    <span className="mx-2">›</span>
                    <span className="font-medium text-slate-800">
                        User Access
                    </span>
                </div>

                <div className="mb-5 flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">
                            User Access
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Manage who can access your label, what they can do and which catalogue they can work with.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={openCreate}
                        className="inline-flex shrink-0 items-center justify-center gap-2 rounded bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                    >
                        <Plus size={16} />
                        Add a new user
                    </button>
                </div>

                <div className="mb-4 grid gap-3 rounded border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-[1.5fr_1fr_1fr_1fr]">
                    <div className="relative">
                        <Search
                            size={16}
                            className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                        />

                        <input
                            type="text"
                            value={search}
                            onChange={(event) =>
                                setSearch(event.target.value)
                            }
                            placeholder="Login / Email"
                            className="w-full rounded border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>

                    <div className="relative">
                        <select
                            value={statusFilter}
                            onChange={(event) =>
                                setStatusFilter(
                                    event.target.value
                                )
                            }
                            className="w-full appearance-none rounded border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="">
                                Account Status
                            </option>
                            <option value="active">
                                Active
                            </option>
                            <option value="suspended">
                                Suspended
                            </option>
                            <option value="disabled">
                                Disabled
                            </option>
                        </select>

                        <ChevronDown
                            size={15}
                            className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
                        />
                    </div>

                    <div className="relative">
                        <select
                            value={accessFilter}
                            onChange={(event) =>
                                setAccessFilter(
                                    event.target.value
                                )
                            }
                            className="w-full appearance-none rounded border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="">
                                This user has access to
                            </option>
                            <option value="entire_label">
                                Entire Label
                            </option>
                            <option value="selected">
                                Selected Catalogue
                            </option>
                        </select>

                        <ChevronDown
                            size={15}
                            className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
                        />
                    </div>

                    <div className="relative">
                        <select
                            value={permissionFilter}
                            onChange={(event) =>
                                setPermissionFilter(
                                    event.target.value
                                )
                            }
                            className="w-full appearance-none rounded border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="">
                                Permission Level
                            </option>
                            <option value="standard">
                                Standard
                            </option>
                            <option value="advanced">
                                Advanced
                            </option>
                        </select>

                        <ChevronDown
                            size={15}
                            className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
                        />
                    </div>
                </div>

                <div className="overflow-hidden rounded border border-slate-200 bg-white">
                    {filteredMembers.length ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full border-collapse">
                                <thead className="bg-slate-50">
                                    <tr className="border-b border-slate-200">
                                        {[
                                            'Login',
                                            'Email',
                                            'Account Status',
                                            'This user has access to',
                                            'Permission Level',
                                            'Scope Level',
                                            'Actions',
                                        ].map((heading) => (
                                            <th
                                                key={heading}
                                                className="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-600"
                                            >
                                                {heading}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>

                                <tbody>
                                    {filteredMembers.map(
                                        (member) => (
                                            <tr
                                                key={member.id}
                                                className="border-b border-slate-100 last:border-b-0 hover:bg-slate-50/70"
                                            >
                                                <td className="whitespace-nowrap px-4 py-4 text-sm font-medium text-slate-800">
                                                    {member.name ||
                                                        'User'}
                                                </td>

                                                <td className="whitespace-nowrap px-4 py-4 text-sm text-slate-600">
                                                    {member.email}
                                                </td>

                                                <td className="whitespace-nowrap px-4 py-4">
                                                    <span
                                                        className={[
                                                            'inline-flex rounded border px-2.5 py-1 text-xs font-semibold capitalize',
                                                            statusClasses(
                                                                member.status
                                                            ),
                                                        ].join(
                                                            ' '
                                                        )}
                                                    >
                                                        {
                                                            member.status
                                                        }
                                                    </span>
                                                </td>

                                                <td className="whitespace-nowrap px-4 py-4 text-sm text-slate-600">
                                                    {member.scope_level ===
                                                    'entire_label'
                                                        ? 'Entire Label'
                                                        : 'Selected Catalogue'}
                                                </td>

                                                <td className="whitespace-nowrap px-4 py-4 text-sm capitalize text-slate-700">
                                                    {
                                                        member.permission_level
                                                    }
                                                </td>

                                                <td className="whitespace-nowrap px-4 py-4 text-sm text-slate-600">
                                                    {member.scope_level ===
                                                    'entire_label'
                                                        ? 'All permitted catalogue'
                                                        : `${(member.scopes || []).length} selected`}
                                                </td>

                                                <td className="whitespace-nowrap px-4 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                openEdit(
                                                                    member
                                                                )
                                                            }
                                                            className="rounded border border-slate-200 p-2 text-slate-600 hover:bg-white hover:text-blue-600"
                                                            title="Edit user"
                                                        >
                                                            <Pencil
                                                                size={
                                                                    15
                                                                }
                                                            />
                                                        </button>

                                                        <div className="relative">
                                                            <select
                                                                value={
                                                                    member.status
                                                                }
                                                                onChange={(
                                                                    event
                                                                ) =>
                                                                    changeStatus(
                                                                        member,
                                                                        event
                                                                            .target
                                                                            .value
                                                                    )
                                                                }
                                                                className="appearance-none rounded border-slate-200 bg-white py-2 pl-2.5 pr-7 text-xs font-medium text-slate-600 focus:border-blue-500 focus:ring-blue-500"
                                                                title="Change account status"
                                                            >
                                                                <option value="active">
                                                                    Active
                                                                </option>
                                                                <option value="suspended">
                                                                    Suspended
                                                                </option>
                                                                <option value="disabled">
                                                                    Disabled
                                                                </option>
                                                            </select>

                                                            <ChevronDown
                                                                size={
                                                                    13
                                                                }
                                                                className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-slate-400"
                                                            />
                                                        </div>

                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                removeMember(
                                                                    member
                                                                )
                                                            }
                                                            className="rounded border border-slate-200 p-2 text-slate-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                                                            title="Remove user"
                                                        >
                                                            <Trash2
                                                                size={
                                                                    15
                                                                }
                                                            />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        )
                                    )}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="px-6 py-16 text-center">
                            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                <Users size={22} />
                            </div>

                            <h3 className="mt-4 text-sm font-semibold text-slate-900">
                                {members.length
                                    ? 'No users match these filters'
                                    : 'No users added yet'}
                            </h3>

                            <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">
                                {members.length
                                    ? 'Change or clear the filters to view other users.'
                                    : 'Add a user and choose exactly what they can access.'}
                            </p>

                            {!members.length && (
                                <button
                                    type="button"
                                    onClick={openCreate}
                                    className="mt-5 inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                                >
                                    <Plus size={16} />
                                    Add a new user
                                </button>
                            )}
                        </div>
                    )}
                </div>

                <div className="mt-3 text-xs text-slate-500">
                    Showing {filteredMembers.length} of{' '}
                    {members.length} users
                </div>
            </div>
        </PanelLayout>
    );
}
