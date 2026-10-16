/* MIXXTUNE_FF429_FINAL_UI_V1 */
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import {
    useEffect,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

// MIXXTUNE_QUICK_EDIT_PERMISSION_OPTIONS_V1
const quickEditPermissionOptions = [
    ["can_view_catalogue", "View Catalogue"],
    ["can_create_releases", "Create Releases"],
    ["can_manage_releases", "Manage Releases"],
    ["can_view_reports", "View Reports"],
    ["can_view_royalties", "View Royalties"],
    ["can_manage_wallet", "Manage Wallet"],
    ["can_manage_withdrawals", "Manage Withdrawals"],
    ["can_manage_users", "Manage Users"],
    ["can_manage_support", "Manage Support"],
    ["can_manage_settings", "Manage Settings"],
    ["can_manage_delivery", "Manage Delivery"],
    ["can_manage_identifiers", "Manage ISRC / UPC"],
];

export default function Index({
    role = 'super_admin',
    users = {},
    summary = {},
    filters = {},
    quickEditManagers = [],
    quickEditMasterLabels = [],
    quickEditAvailableLabels = [],
    quickEditLabelManagers = [],

    // MIXXTUNE_QUICK_EDIT_AVAILABLE_ARTISTS_V1
    quickEditAvailableArtists = [],
}) {
    const [search, setSearch] =
        useState(filters.search ?? '');

    /*
     * MIXXTUNE_USER_QUICK_EDIT_MODAL_V2
     */
    const [quickEditUser, setQuickEditUser] =
        useState(null);

    const [quickEditForm, setQuickEditForm] =
        useState(null);

    const [quickEditSaving, setQuickEditSaving] =
        useState(false);

    const [quickEditErrors, setQuickEditErrors] =
        useState({});


    /*
     * MIXXTUNE_QUICK_LABEL_UI_V2
     */
    const [quickLabelMode, setQuickLabelMode] =
        useState(null);

    const [quickLabelSaving, setQuickLabelSaving] =
        useState(false);

    const [quickLabelForm, setQuickLabelForm] =
        useState({
            label_id: '',
            name: '',
            revenue_share_percentage: '80',
        });

    const resetQuickLabel = () => {
        setQuickLabelMode(null);

        setQuickLabelForm({
            label_id: '',
            name: '',
            revenue_share_percentage: '80',
        });
    };

    const submitQuickLabel = () => {
        if (
            !quickEditUser
            || !quickLabelMode
        ) {
            return;
        }

        setQuickLabelSaving(true);
        setQuickEditErrors({});

        router.post(
            `/v2/admin/users/${quickEditUser.id}/quick-label`,
            {
                mode:
                    quickLabelMode,

                label_id:
                    quickLabelMode ===
                    'existing'
                        ? quickLabelForm.label_id
                        : null,

                name:
                    quickLabelMode ===
                    'create'
                        ? quickLabelForm.name
                        : null,

                revenue_share_percentage:
                    quickLabelForm
                        .revenue_share_percentage,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    resetQuickLabel();

                    /*
                     * Close modal after mutation.
                     * Reload Index payload so the new
                     * assignment is immediately fresh.
                     */
                    setQuickEditUser(null);
                    setQuickEditForm(null);

                    router.reload({
                        only: [
                            'users',
                            'quickEditAvailableLabels',
                            'quickEditMasterLabels',
                            'quickEditLabelManagers',
                        ],
                    });
                },

                onError: (errors) => {
                    setQuickEditErrors(
                        errors || {}
                    );
                },

                onFinish: () => {
                    setQuickLabelSaving(false);
                },
            }
        );
    };


    /*
     * MIXXTUNE_DYNAMIC_QUICK_EDIT_FIELDS_V1
     */
    const quickFieldEnabled = (field) => {
        const configured =
            quickEditUser?.quick_edit_fields;

        // MIXXTUNE_INDEX_QUICK_EDIT_NULL_EMPTY_V1
        // null / undefined = legacy Quick Edit defaults.
        // [] = explicitly no configurable Quick Edit fields.
        if (!Array.isArray(configured)) {
            return [
                "name",
                "email",
                "account_status",
                "label_revenue_rates",
            ].includes(field);
        }

        return configured.includes(field);
    };

    const openQuickEdit = (user) => {
        const assignedLabels =
            user.assigned_labels ??
            user.assignedLabels ??
            [];

        const labels = assignedLabels.map(
            (label) => {
                const manager =
                    quickEditLabelManagers.find(
                        (item) =>
                            Number(item.label_id) ===
                            Number(label.id)
                    );

                return {
                    id: label.id,

                    name:
                        label.name ?? '',

                    parent_label_id:
                        label.parent_label_id ??
                        '',

                    revenue_share_percentage:
                        label.revenue_share_percentage ??
                        '',

                    manager_id:
                        manager?.manager_id ??
                        '',

                    status:
                        label.status ??
                        'active',

                    can_withdraw:
                        Boolean(
                            label.can_withdraw
                        ),
                };
            }
        );

        setQuickEditErrors({});

        setQuickEditUser(user);

        setQuickEditForm({
            name:
                user.name ?? '',

            email:
                user.email ?? '',

            account_status:
                user.account_status ??
                'active',

            // MIXXTUNE_QUICK_EDIT_ACCOUNT_STATE_V1
            phone:
                user.phone ?? '',

            country:
                user.country ?? '',

            role:
                user.role ?? '',

            kyc_status:
                user.kyc_status ??
                'pending',

            // MIXXTUNE_QUICK_EDIT_PAYOUT_STATE_V1
            address_line_1:
                user.address_line_1 ?? '',

            address_line_2:
                user.address_line_2 ?? '',

            city:
                user.city ?? '',

            state:
                user.state ?? '',

            postal_code:
                user.postal_code ?? '',

            country_code:
                user.country_code ?? 'IN',

            /*
             * Sensitive encrypted values stay write-only.
             * Existing plaintext is never prefetched.
             */
            pan_number: '',

            gst_number:
                user.gst_number ?? '',

            account_holder_name:
                user.account_holder_name ?? '',

            bank_account_number: '',

            bank_name:
                user.bank_name ?? '',

            ifsc_code:
                user.ifsc_code ?? '',

            branch_name:
                user.branch_name ?? '',

            upi_id:
                user.upi_id ?? '',

            // MIXXTUNE_QUICK_EDIT_ASSIGNMENT_STATE_V1
            artist_ids:
                (
                    user.assigned_artists ??
                    user.assignedArtists ??
                    []
                ).map((artist) =>
                    Number(artist.id)
                ),

            label_ids:
                assignedLabels.map((label) =>
                    Number(label.id)
                ),

            // MIXXTUNE_QUICK_EDIT_PERMISSION_STATE_V1
            // MIXXTUNE_QUICK_EDIT_PERMISSION_HYDRATION_V1
            permissions:
                (() => {
                    let hydratedPermissions =
                        user.quick_edit_permissions ??
                        user.permissions ??
                        user.panel_permissions ??
                        user.panelPermissions ??
                        {};

                    /*
                     * Controller supplies quick_edit_permissions
                     * as JSON from the scalar subquery.
                     */
                    if (
                        typeof hydratedPermissions ===
                        "string"
                    ) {
                        try {
                            hydratedPermissions =
                                JSON.parse(
                                    hydratedPermissions
                                );
                        } catch {
                            hydratedPermissions = {};
                        }
                    }

                    return Object.fromEntries(
                        quickEditPermissionOptions.map(
                            ([key]) => [
                                key,
                                Boolean(
                                    hydratedPermissions?.[
                                        key
                                    ] ?? false
                                ),
                            ]
                        )
                    );
                })(),

            labels,
        });
    };

    const closeQuickEdit = () => {
        if (quickEditSaving) {
            return;
        }

        setQuickEditUser(null);
        setQuickEditForm(null);
        setQuickEditErrors({});
    };

    const updateQuickLabel = (
        index,
        field,
        value
    ) => {
        setQuickEditForm(
            (current) => {
                if (!current) {
                    return current;
                }

                const labels = [
                    ...(current.labels ?? []),
                ];

                labels[index] = {
                    ...labels[index],
                    [field]: value,
                };

                return {
                    ...current,
                    labels,
                };
            }
        );
    };

    const saveQuickEdit = () => {
        if (
            !quickEditUser ||
            !quickEditForm ||
            quickEditSaving
        ) {
            return;
        }

        setQuickEditSaving(true);
        setQuickEditErrors({});

        router.patch(
            `/v2/admin/users/${quickEditUser.id}/quick-update`,
            quickEditForm,
            {
                preserveScroll: true,

                onSuccess: () => {
                    setQuickEditUser(null);
                    setQuickEditForm(null);
                    setQuickEditErrors({});
                },

                onError: (errors) => {
                    setQuickEditErrors(
                        errors ?? {}
                    );
                },

                onFinish: () => {
                    setQuickEditSaving(
                        false
                    );
                },
            }
        );
    };

    const applyFilters = (changes = {}) => {
        const nextFilters = {
            search,
            role: filters.role ?? '',
            status: filters.status ?? '',
            invitation:
                filters.invitation ?? '',
            ...changes,
        };

        router.get(
            '/v2/admin/users',
            nextFilters,
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    useEffect(() => {
        const currentSearch =
            filters.search ?? '';

        if (search === currentSearch) {
            return;
        }

        const timer =
            window.setTimeout(() => {
                applyFilters({
                    search,
                });
            }, 300);

        return () => {
            window.clearTimeout(timer);
        };
    }, [search]);

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
            subtitle="Manage managers, labels, artists and team accounts"
        >
            <Head title="User Management" />

            {quickEditUser &&
                quickEditForm && (
                    <div
                        className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4"
                        onMouseDown={(
                            event
                        ) => {
                            if (
                                event.target ===
                                event.currentTarget
                            ) {
                                closeQuickEdit();
                            }
                        }}
                    >
                        <div className="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                            <div className="sticky top-0 z-20 flex items-start justify-between border-b border-slate-200 bg-white px-6 py-5">
                                <div>
                                    <h2 className="text-xl font-bold text-slate-900">
                                        Quick Edit
                                    </h2>

                                    <div className="mt-1 text-sm text-slate-500">
                                        {
                                            quickEditUser.name
                                        }
                                        {' · '}
                                        {
                                            quickEditUser.role
                                        }
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={
                                        closeQuickEdit
                                    }
                                    disabled={
                                        quickEditSaving
                                    }
                                    className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
                                >
                                    Close
                                </button>
                            </div>

                            <div className="space-y-6 p-6">
                                {Object.keys(
                                    quickEditErrors
                                ).length >
                                    0 && (
                                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                                        {Object.entries(
                                            quickEditErrors
                                        ).map(
                                            ([
                                                key,
                                                error,
                                            ]) => (
                                                <div
                                                    key={
                                                        key
                                                    }
                                                >
                                                    {Array.isArray(
                                                        error
                                                    )
                                                        ? error.join(
                                                              ', '
                                                          )
                                                        : error}
                                                </div>
                                            )
                                        )}
                                    </div>
                                )}

                                <div>
                                    <div className="mb-3">
                                        <h3 className="font-semibold text-slate-900">
                                            Account
                                        </h3>

                                        <p className="text-sm text-slate-500">
                                            Basic login account settings.
                                        </p>
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-3">
                                        <label>
                                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Name
                                            </span>

                                            <input
                                                type="text"
                                                value={
                                                    quickEditForm.name
                                                }
                                                onChange={(
                                                    event
                                                ) =>
                                                    setQuickEditForm(
                                                        (
                                                            current
                                                        ) => ({
                                                            ...current,
                                                            name:
                                                                event
                                                                    .target
                                                                    .value,
                                                        })
                                                    )
                                                }
                                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                            />
                                        </label>

                                        <label>
                                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Email
                                            </span>

                                            <input
                                                type="email"
                                                value={
                                                    quickEditForm.email
                                                }
                                                onChange={(
                                                    event
                                                ) =>
                                                    setQuickEditForm(
                                                        (
                                                            current
                                                        ) => ({
                                                            ...current,
                                                            email:
                                                                event
                                                                    .target
                                                                    .value,
                                                        })
                                                    )
                                                }
                                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                            />
                                        </label>

                                        <label>
                                            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Account Status
                                            </span>

                                            <select
                                                value={
                                                    quickEditForm.account_status
                                                }
                                                onChange={(
                                                    event
                                                ) =>
                                                    setQuickEditForm(
                                                        (
                                                            current
                                                        ) => ({
                                                            ...current,
                                                            account_status:
                                                                event
                                                                    .target
                                                                    .value,
                                                        })
                                                    )
                                                }
                                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
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
                                </div>

                                {quickEditUser.role ===
                                    'label' && (
                                    <div className="space-y-4">
                                        <div>
                                            <h3 className="font-semibold text-slate-900">
                                                Label Configuration
                                            </h3>

                                            <p className="text-sm text-slate-500">
                                                Each label is managed independently.
                                            </p>
                                        </div>

                                        {quickEditForm
                                            .labels
                                            .length ===
                                            0 && (
                                            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                                No label is currently assigned to this account.
                                            </div>
                                        )}

                                        
                                        <div className="mb-4 rounded-xl border border-violet-200 bg-violet-50 p-4">
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <div className="text-sm font-semibold text-slate-900">
                                                        Label Management
                                                    </div>

                                                    <div className="mt-1 text-xs text-slate-600">
                                                        Add an existing unowned label or create a new label for this account.
                                                    </div>
                                                </div>

                                                <div className="flex flex-wrap gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setQuickLabelMode(
                                                                'existing'
                                                            );

                                                            setQuickLabelForm({
                                                                label_id: '',
                                                                name: '',
                                                                revenue_share_percentage:
                                                                    '80',
                                                            });
                                                        }}
                                                        className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                    >
                                                        + Add Existing Label
                                                    </button>

                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setQuickLabelMode(
                                                                'create'
                                                            );

                                                            setQuickLabelForm({
                                                                label_id: '',
                                                                name: '',
                                                                revenue_share_percentage:
                                                                    '80',
                                                            });
                                                        }}
                                                        className="rounded-lg bg-violet-700 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-800"
                                                    >
                                                        + Create New Label
                                                    </button>
                                                </div>
                                            </div>

                                            {quickLabelMode && (
                                                <div className="mt-4 rounded-lg border border-violet-200 bg-white p-4">
                                                    <div className="mb-3 flex items-center justify-between gap-3">
                                                        <div className="text-sm font-semibold text-slate-900">
                                                            {quickLabelMode ===
                                                            'existing'
                                                                ? 'Add Existing Label'
                                                                : 'Create New Label'}
                                                        </div>

                                                        <button
                                                            type="button"
                                                            onClick={
                                                                resetQuickLabel
                                                            }
                                                            className="text-xs font-semibold text-slate-500 hover:text-slate-900"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </div>

                                                    <div className="grid gap-3 md:grid-cols-2">
                                                        {quickLabelMode ===
                                                        'existing' ? (
                                                            <label className="block">
                                                                <span className="text-xs font-medium text-slate-700">
                                                                    Existing Label
                                                                </span>

                                                                <select
                                                                    value={
                                                                        quickLabelForm.label_id
                                                                    }
                                                                    onChange={(
                                                                        event
                                                                    ) => {
                                                                        const id =
                                                                            event.target.value;

                                                                        const selected =
                                                                            quickEditAvailableLabels.find(
                                                                                (
                                                                                    label
                                                                                ) =>
                                                                                    Number(
                                                                                        label.id
                                                                                    ) ===
                                                                                    Number(
                                                                                        id
                                                                                    )
                                                                            );

                                                                        setQuickLabelForm(
                                                                            (
                                                                                current
                                                                            ) => ({
                                                                                ...current,
                                                                                label_id:
                                                                                    id,
                                                                                revenue_share_percentage:
                                                                                    selected
                                                                                        ?.revenue_share_percentage ??
                                                                                    current
                                                                                        .revenue_share_percentage,
                                                                            })
                                                                        );
                                                                    }}
                                                                    className="mt-1 w-full rounded-lg border-slate-300 text-sm"
                                                                >
                                                                    <option value="">
                                                                        Select label
                                                                    </option>

                                                                    {quickEditAvailableLabels.map(
                                                                        (
                                                                            label
                                                                        ) => (
                                                                            <option
                                                                                key={
                                                                                    label.id
                                                                                }
                                                                                value={
                                                                                    label.id
                                                                                }
                                                                            >
                                                                                {
                                                                                    label.name
                                                                                }
                                                                                {' (#'}
                                                                                {
                                                                                    label.id
                                                                                }
                                                                                {')'}
                                                                            </option>
                                                                        )
                                                                    )}
                                                                </select>

                                                                {quickEditAvailableLabels.length ===
                                                                    0 && (
                                                                    <span className="mt-1 block text-xs text-amber-700">
                                                                        No unowned labels are currently available.
                                                                    </span>
                                                                )}
                                                            </label>
                                                        ) : (
                                                            <label className="block">
                                                                <span className="text-xs font-medium text-slate-700">
                                                                    New Label Name
                                                                </span>

                                                                <input
                                                                    type="text"
                                                                    value={
                                                                        quickLabelForm.name
                                                                    }
                                                                    onChange={(
                                                                        event
                                                                    ) =>
                                                                        setQuickLabelForm(
                                                                            (
                                                                                current
                                                                            ) => ({
                                                                                ...current,
                                                                                name: event.target.value,
                                                                            })
                                                                        )
                                                                    }
                                                                    placeholder="Enter label name"
                                                                    className="mt-1 w-full rounded-lg border-slate-300 text-sm"
                                                                />
                                                            </label>
                                                        )}

                                                        <label className="block">
                                                            <span className="text-xs font-medium text-slate-700">
                                                                Revenue Rate (%)
                                                            </span>

                                                            <input
                                                                type="number"
                                                                min="0"
                                                                max="100"
                                                                step="0.01"
                                                                value={
                                                                    quickLabelForm.revenue_share_percentage
                                                                }
                                                                onChange={(
                                                                    event
                                                                ) =>
                                                                    setQuickLabelForm(
                                                                        (
                                                                            current
                                                                        ) => ({
                                                                            ...current,
                                                                            revenue_share_percentage:
                                                                                event.target.value,
                                                                        })
                                                                    )
                                                                }
                                                                className="mt-1 w-full rounded-lg border-slate-300 text-sm"
                                                            />
                                                        </label>
                                                    </div>

                                                    {(quickEditErrors.label_id ||
                                                        quickEditErrors.name ||
                                                        quickEditErrors.revenue_share_percentage ||
                                                        quickEditErrors.label) && (
                                                        <div className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                                                            {quickEditErrors.label_id ||
                                                                quickEditErrors.name ||
                                                                quickEditErrors.revenue_share_percentage ||
                                                                quickEditErrors.label}
                                                        </div>
                                                    )}

                                                    <div className="mt-4 flex justify-end">
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                quickLabelSaving
                                                            }
                                                            onClick={
                                                                submitQuickLabel
                                                            }
                                                            className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            {quickLabelSaving
                                                                ? 'Saving...'
                                                                : quickLabelMode ===
                                                                    'existing'
                                                                  ? 'Add Label'
                                                                  : 'Create & Add Label'}
                                                        </button>
                                                    </div>
                                                </div>
                                            )}
                                        </div>


                                            {/* MIXXTUNE_DYNAMIC_QUICK_EDIT_ACCOUNT_FIELDS_V1 */}
                                            <div className="grid gap-4 md:grid-cols-2">
                                                {quickFieldEnabled("phone") && (
                                                    <label className="block">
                                                        <span className="mb-1 block text-xs font-semibold text-slate-600">
                                                            Phone
                                                        </span>
                                                        <input
                                                            type="text"
                                                            value={quickEditForm.phone ?? ""}
                                                            onChange={(e) =>
                                                                setQuickEditForm((current) => ({
                                                                    ...current,
                                                                    phone: e.target.value,
                                                                }))
                                                            }
                                                            className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-400 focus:ring-4 focus:ring-violet-100"
                                                        />
                                                    </label>
                                                )}

                                                {quickFieldEnabled("country") && (
                                                    <label className="block">
                                                        <span className="mb-1 block text-xs font-semibold text-slate-600">
                                                            Country
                                                        </span>
                                                        <input
                                                            type="text"
                                                            value={quickEditForm.country ?? ""}
                                                            onChange={(e) =>
                                                                setQuickEditForm((current) => ({
                                                                    ...current,
                                                                    country: e.target.value,
                                                                }))
                                                            }
                                                            className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-400 focus:ring-4 focus:ring-violet-100"
                                                        />
                                                    </label>
                                                )}

                                                {/* MIXXTUNE_QUICK_EDIT_ROLE_KYC_UI_V1 */}

                                                {quickFieldEnabled("role") && (
                                                    <label className="block">
                                                        <span className="mb-1 block text-xs font-semibold text-slate-600">
                                                            Role
                                                        </span>

                                                        <select
                                                            value={quickEditForm.role ?? ""}
                                                            onChange={(e) =>
                                                                setQuickEditForm((current) => ({
                                                                    ...current,
                                                                    role: e.target.value,
                                                                }))
                                                            }
                                                            className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-400 focus:ring-4 focus:ring-violet-100"
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
                                                )}

                                                {quickFieldEnabled("kyc_status") && (
                                                    <label className="block">
                                                        <span className="mb-1 block text-xs font-semibold text-slate-600">
                                                            KYC Status
                                                        </span>

                                                        <select
                                                            value={
                                                                quickEditForm.kyc_status ??
                                                                "pending"
                                                            }
                                                            onChange={(e) =>
                                                                setQuickEditForm((current) => ({
                                                                    ...current,
                                                                    kyc_status: e.target.value,
                                                                }))
                                                            }
                                                            className="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-400 focus:ring-4 focus:ring-violet-100"
                                                        >
                                                            <option value="pending">
                                                                Pending
                                                            </option>
                                                            <option value="verified">
                                                                Verified
                                                            </option>
                                                            <option value="rejected">
                                                                Rejected
                                                            </option>
                                                        </select>
                                                    </label>
                                                )}

                                                {quickFieldEnabled("username") && (
                                                    <label className="block">
                                                        <span className="mb-1 block text-xs font-semibold text-slate-600">
                                                            Username
                                                        </span>
                                                        <input
                                                            type="text"
                                                            value={quickEditUser?.username ?? ""}
                                                            disabled
                                                            className="w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-500 shadow-inner"
                                                        />
                                                    </label>
                                                )}

                                                {quickFieldEnabled("client_id") && (
                                                    <label className="block">
                                                        <span className="mb-1 block text-xs font-semibold text-slate-600">
                                                            Client ID
                                                        </span>
                                                        <input
                                                            type="text"
                                                            value={quickEditUser?.client_id ?? ""}
                                                            disabled
                                                            className="w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-500 shadow-inner"
                                                        />
                                                    </label>
                                                )}
                                            </div>

{/* MIXXTUNE_QUICK_EDIT_ASSIGNMENT_UI_V1 */}

{quickEditForm.role === "admin" &&
    (
        quickFieldEnabled("artist_ids") ||
        quickFieldEnabled("label_ids")
    ) && (
        <div className="mb-5 overflow-hidden rounded-2xl border border-violet-200/70 bg-gradient-to-br from-violet-50/90 via-white to-white p-5 shadow-sm shadow-violet-100/60">
            <div className="mb-1 flex items-center gap-2 text-sm font-extrabold tracking-tight text-slate-900">
                Assignments & Access
            </div>

            <div className="mb-4 text-xs text-slate-500">
                Select the Artists and Labels this Manager can access.
            </div>

            <div className="grid gap-5 md:grid-cols-2">
                {quickFieldEnabled(
                    "artist_ids"
                ) && (
                    <div>
                        <div className="mb-2 text-xs font-semibold text-slate-700">
                            Assign Artists
                        </div>

                        <div className="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-inner shadow-slate-100">
                            {quickEditAvailableArtists.length ===
                            0 ? (
                                <div className="text-xs text-slate-500">
                                    No artists available.
                                </div>
                            ) : (
                                quickEditAvailableArtists.map(
                                    (artist) => {
                                        const artistId =
                                            Number(
                                                artist.id
                                            );

                                        const checked =
                                            (
                                                quickEditForm.artist_ids ??
                                                []
                                            ).includes(
                                                artistId
                                            );

                                        return (
                                            <label
                                                key={
                                                    artist.id
                                                }
                                                className="flex cursor-pointer items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 transition hover:border-violet-100 hover:bg-violet-50/60"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        checked
                                                    }
                                                    onChange={(
                                                        e
                                                    ) => {
                                                        const current =
                                                            quickEditForm.artist_ids ??
                                                            [];

                                                        const next =
                                                            e
                                                                .target
                                                                .checked
                                                                ? [
                                                                      ...new Set(
                                                                          [
                                                                              ...current,
                                                                              artistId,
                                                                          ]
                                                                      ),
                                                                  ]
                                                                : current.filter(
                                                                      (
                                                                          id
                                                                      ) =>
                                                                          Number(
                                                                              id
                                                                          ) !==
                                                                          artistId
                                                                  );

                                                        setQuickEditForm(
                                                            (
                                                                form
                                                            ) => ({
                                                                ...form,
                                                                artist_ids:
                                                                    next,
                                                            })
                                                        );
                                                    }}
                                                    className="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                                                />

                                                <span className="min-w-0 text-sm text-slate-700">
                                                    {artist.stage_name ||
                                                        `Artist #${artist.id}`}
                                                </span>
                                            </label>
                                        );
                                    }
                                )
                            )}
                        </div>
                    </div>
                )}

                {quickFieldEnabled(
                    "label_ids"
                ) && (
                    <div>
                        <div className="mb-2 text-xs font-semibold text-slate-700">
                            Assign Labels
                        </div>

                        <div className="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-inner shadow-slate-100">
                            {quickEditMasterLabels.length ===
                            0 ? (
                                <div className="text-xs text-slate-500">
                                    No labels available.
                                </div>
                            ) : (
                                quickEditMasterLabels.map(
                                    (label) => {
                                        const labelId =
                                            Number(
                                                label.id
                                            );

                                        const checked =
                                            (
                                                quickEditForm.label_ids ??
                                                []
                                            ).includes(
                                                labelId
                                            );

                                        return (
                                            <label
                                                key={
                                                    label.id
                                                }
                                                className="flex cursor-pointer items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 transition hover:border-violet-100 hover:bg-violet-50/60"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        checked
                                                    }
                                                    onChange={(
                                                        e
                                                    ) => {
                                                        const current =
                                                            quickEditForm.label_ids ??
                                                            [];

                                                        const next =
                                                            e
                                                                .target
                                                                .checked
                                                                ? [
                                                                      ...new Set(
                                                                          [
                                                                              ...current,
                                                                              labelId,
                                                                          ]
                                                                      ),
                                                                  ]
                                                                : current.filter(
                                                                      (
                                                                          id
                                                                      ) =>
                                                                          Number(
                                                                              id
                                                                          ) !==
                                                                          labelId
                                                                  );

                                                        setQuickEditForm(
                                                            (
                                                                form
                                                            ) => ({
                                                                ...form,
                                                                label_ids:
                                                                    next,
                                                            })
                                                        );
                                                    }}
                                                    className="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                                                />

                                                <span className="min-w-0 text-sm text-slate-700">
                                                    {label.name}
                                                </span>
                                            </label>
                                        );
                                    }
                                )
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    )}

{/* MIXXTUNE_QUICK_EDIT_PERMISSION_UI_V1 */}

{quickFieldEnabled("permissions") && (
    <div className="mb-5 overflow-hidden rounded-2xl border border-indigo-200/70 bg-gradient-to-br from-indigo-50/90 via-white to-white p-5 shadow-sm shadow-indigo-100/60">
        <div className="mb-1 flex items-center gap-2 text-sm font-extrabold tracking-tight text-slate-900">
            Panel Permissions
        </div>

        <div className="mb-4 text-xs text-slate-500">
            Control which Back Stage modules this user can access.
        </div>

        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {quickEditPermissionOptions.map(
                ([key, label]) => {
                    const checked =
                        Boolean(
                            quickEditForm
                                .permissions?.[
                                    key
                                ]
                        );

                    return (
                        <label
                            key={key}
                            className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-indigo-50/50 hover:shadow-md"
                        >
                            <input
                                type="checkbox"
                                checked={
                                    checked
                                }
                                onChange={(
                                    e
                                ) =>
                                    setQuickEditForm(
                                        (
                                            current
                                        ) => ({
                                            ...current,

                                            permissions:
                                                {
                                                    ...(
                                                        current.permissions ??
                                                        {}
                                                    ),

                                                    [key]:
                                                        e
                                                            .target
                                                            .checked,
                                                },
                                        })
                                    )
                                }
                                className="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                            />

                            <span className="text-sm font-medium text-slate-700">
                                {label}
                            </span>
                        </label>
                    );
                }
            )}
        </div>
    </div>
)}

{/* MIXXTUNE_QUICK_EDIT_PAYOUT_UI_V1 */}

{[
    "address_line_1",
    "address_line_2",
    "city",
    "state",
    "postal_code",
    "country_code",
].some((field) =>
    quickFieldEnabled(field)
) && (
    <div className="mb-5 overflow-hidden rounded-2xl border border-blue-200/70 bg-gradient-to-br from-blue-50/90 via-white to-white p-5 shadow-sm shadow-blue-100/60">
        <div className="mb-4 flex items-center gap-2 text-sm font-extrabold tracking-tight text-slate-900">
            Address Details
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {[
                [
                    "address_line_1",
                    "Address Line 1",
                ],
                [
                    "address_line_2",
                    "Address Line 2",
                ],
                ["city", "City"],
                ["state", "State"],
                [
                    "postal_code",
                    "Postal / PIN Code",
                ],
                [
                    "country_code",
                    "Country Code",
                ],
            ].map(([field, label]) =>
                quickFieldEnabled(field) ? (
                    <label
                        key={field}
                        className="block"
                    >
                        <span className="mb-1 block text-xs font-semibold text-slate-600">
                            {label}
                        </span>

                        <input
                            type="text"
                            value={
                                quickEditForm[
                                    field
                                ] ?? ""
                            }
                            onChange={(e) =>
                                setQuickEditForm(
                                    (current) => ({
                                        ...current,
                                        [field]:
                                            e.target
                                                .value,
                                    })
                                )
                            }
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        />
                    </label>
                ) : null
            )}
        </div>
    </div>
)}

{[
    "pan_number",
    "gst_number",
].some((field) =>
    quickFieldEnabled(field)
) && (
    <div className="mb-5 rounded-2xl border border-rose-100 bg-rose-50/40 p-4">
        <div className="mb-4 flex items-center gap-2 text-sm font-extrabold tracking-tight text-slate-900">
            Tax & KYC Details
        </div>

        <div className="grid gap-4 md:grid-cols-2">
            {quickFieldEnabled(
                "pan_number"
            ) && (
                <label className="block">
                    <span className="mb-1 block text-xs font-semibold text-slate-600">
                        PAN Number
                    </span>

                    <input
                        type="text"
                        value={
                            quickEditForm
                                .pan_number ??
                            ""
                        }
                        placeholder="Leave blank to keep existing PAN"
                        onChange={(e) =>
                            setQuickEditForm(
                                (current) => ({
                                    ...current,
                                    pan_number:
                                        e.target
                                            .value,
                                })
                            )
                        }
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm uppercase"
                    />

                    <span className="mt-1 block text-[11px] text-slate-500">
                        Existing PAN is protected.
                        Enter a value only to replace it.
                    </span>
                </label>
            )}

            {quickFieldEnabled(
                "gst_number"
            ) && (
                <label className="block">
                    <span className="mb-1 block text-xs font-semibold text-slate-600">
                        GST Number
                    </span>

                    <input
                        type="text"
                        value={
                            quickEditForm
                                .gst_number ??
                            ""
                        }
                        onChange={(e) =>
                            setQuickEditForm(
                                (current) => ({
                                    ...current,
                                    gst_number:
                                        e.target
                                            .value,
                                })
                            )
                        }
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                    />
                </label>
            )}
        </div>
    </div>
)}

{[
    "account_holder_name",
    "bank_account_number",
    "bank_name",
    "ifsc_code",
    "branch_name",
    "upi_id",
].some((field) =>
    quickFieldEnabled(field)
) && (
    <div className="mb-5 rounded-2xl border border-emerald-100 bg-emerald-50/40 p-4">
        <div className="mb-4 flex items-center gap-2 text-sm font-extrabold tracking-tight text-slate-900">
            Bank & Payout Details
        </div>

        <div className="grid gap-4 md:grid-cols-2">
            {[
                [
                    "account_holder_name",
                    "Account Holder Name",
                ],
                [
                    "bank_name",
                    "Bank Name",
                ],
                [
                    "ifsc_code",
                    "IFSC Code",
                ],
                [
                    "branch_name",
                    "Branch Name",
                ],
                [
                    "upi_id",
                    "UPI ID",
                ],
            ].map(([field, label]) =>
                quickFieldEnabled(field) ? (
                    <label
                        key={field}
                        className="block"
                    >
                        <span className="mb-1 block text-xs font-semibold text-slate-600">
                            {label}
                        </span>

                        <input
                            type="text"
                            value={
                                quickEditForm[
                                    field
                                ] ?? ""
                            }
                            onChange={(e) =>
                                setQuickEditForm(
                                    (current) => ({
                                        ...current,
                                        [field]:
                                            e.target
                                                .value,
                                    })
                                )
                            }
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        />
                    </label>
                ) : null
            )}

            {quickFieldEnabled(
                "bank_account_number"
            ) && (
                <label className="block">
                    <span className="mb-1 block text-xs font-semibold text-slate-600">
                        Bank Account Number
                    </span>

                    <input
                        type="text"
                        value={
                            quickEditForm
                                .bank_account_number ??
                            ""
                        }
                        placeholder="Leave blank to keep existing account"
                        onChange={(e) =>
                            setQuickEditForm(
                                (current) => ({
                                    ...current,
                                    bank_account_number:
                                        e.target
                                            .value,
                                })
                            )
                        }
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                    />

                    <span className="mt-1 block text-[11px] text-slate-500">
                        Existing bank account is protected.
                        Enter a value only to replace it.
                    </span>
                </label>
            )}
        </div>
    </div>
)}

{quickEditForm.labels.map(
                                            (
                                                label,
                                                index
                                            ) => (
                                                <div
                                                    key={
                                                        label.id
                                                    }
                                                    className="rounded-2xl border border-slate-200 bg-slate-50/50 p-5"
                                                >
                                                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                                        <div>
                                                            <div className="font-bold text-slate-900">
                                                                {
                                                                    label.name
                                                                }
                                                            </div>

                                                            <div className="text-xs text-slate-500">
                                                                Label ID #
                                                                {
                                                                    label.id
                                                                }
                                                            </div>
                                                        </div>

                                                        <span className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">
                                                            Label
                                                        </span>
                                                    </div>

                                                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                                        <label>
                                                            <span className="mb-1 block text-xs font-semibold text-slate-500">
                                                                Label Name
                                                            </span>

                                                            <input
                                                                type="text"
                                                                value={
                                                                    label.name
                                                                }
                                                                onChange={(
                                                                    event
                                                                ) =>
                                                                    updateQuickLabel(
                                                                        index,
                                                                        'name',
                                                                        event
                                                                            .target
                                                                            .value
                                                                    )
                                                                }
                                                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                                            />
                                                        </label>

                                                        <label>
                                                            <span className="mb-1 block text-xs font-semibold text-slate-500">
                                                                Master / Parent
                                                            </span>

                                                            <select
                                                                value={
                                                                    label.parent_label_id ??
                                                                    ''
                                                                }
                                                                onChange={(
                                                                    event
                                                                ) =>
                                                                    updateQuickLabel(
                                                                        index,
                                                                        'parent_label_id',
                                                                        event
                                                                            .target
                                                                            .value
                                                                    )
                                                                }
                                                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                                            >
                                                                <option value="">
                                                                    Master Label / No Parent
                                                                </option>

                                                                {quickEditMasterLabels
                                                                    .filter(
                                                                        (
                                                                            master
                                                                        ) =>
                                                                            Number(
                                                                                master.id
                                                                            ) !==
                                                                            Number(
                                                                                label.id
                                                                            )
                                                                    )
                                                                    .map(
                                                                        (
                                                                            master
                                                                        ) => (
                                                                            <option
                                                                                key={
                                                                                    master.id
                                                                                }
                                                                                value={
                                                                                    master.id
                                                                                }
                                                                            >
                                                                                {
                                                                                    master.name
                                                                                }
                                                                                {' · #'}
                                                                                {
                                                                                    master.id
                                                                                }
                                                                            </option>
                                                                        )
                                                                    )}
                                                            </select>
                                                        </label>

                                                        <label>
                                                            <span className="mb-1 block text-xs font-semibold text-slate-500">
                                                                Revenue Rate %
                                                            </span>

                                                            <input
                                                                type="number"
                                                                min="0"
                                                                max="100"
                                                                step="0.01"
                                                                value={
                                                                    label.revenue_share_percentage ??
                                                                    ''
                                                                }
                                                                onChange={(
                                                                    event
                                                                ) =>
                                                                    updateQuickLabel(
                                                                        index,
                                                                        'revenue_share_percentage',
                                                                        event
                                                                            .target
                                                                            .value
                                                                    )
                                                                }
                                                                placeholder="Rate not set"
                                                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                                            />
                                                        </label>

                                                        <label>
                                                            <span className="mb-1 block text-xs font-semibold text-slate-500">
                                                                Manager
                                                            </span>

                                                            <select
                                                                value={
                                                                    label.manager_id ??
                                                                    ''
                                                                }
                                                                onChange={(
                                                                    event
                                                                ) =>
                                                                    updateQuickLabel(
                                                                        index,
                                                                        'manager_id',
                                                                        event
                                                                            .target
                                                                            .value
                                                                    )
                                                                }
                                                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                                            >
                                                                <option value="">
                                                                    Unassigned
                                                                </option>

                                                                {quickEditManagers.map(
                                                                    (
                                                                        manager
                                                                    ) => (
                                                                        <option
                                                                            key={
                                                                                manager.id
                                                                            }
                                                                            value={
                                                                                manager.id
                                                                            }
                                                                        >
                                                                            {
                                                                                manager.name
                                                                            }
                                                                            {manager.username
                                                                                ? ` · @${manager.username}`
                                                                                : ''}
                                                                        </option>
                                                                    )
                                                                )}
                                                            </select>
                                                        </label>

                                                        <label>
                                                            <span className="mb-1 block text-xs font-semibold text-slate-500">
                                                                Label Status
                                                            </span>

                                                            <select
                                                                value={
                                                                    label.status
                                                                }
                                                                onChange={(
                                                                    event
                                                                ) =>
                                                                    updateQuickLabel(
                                                                        index,
                                                                        'status',
                                                                        event
                                                                            .target
                                                                            .value
                                                                    )
                                                                }
                                                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                                            >
                                                                <option value="active">
                                                                    Active
                                                                </option>

                                                                <option value="inactive">
                                                                    Inactive
                                                                </option>
                                                            </select>
                                                        </label>

                                                        <label className="flex min-h-[66px] items-center gap-3 rounded-lg border border-slate-300 bg-white px-4 py-3">
                                                            <input
                                                                type="checkbox"
                                                                checked={Boolean(
                                                                    label.can_withdraw
                                                                )}
                                                                onChange={(
                                                                    event
                                                                ) =>
                                                                    updateQuickLabel(
                                                                        index,
                                                                        'can_withdraw',
                                                                        event
                                                                            .target
                                                                            .checked
                                                                    )
                                                                }
                                                                className="h-4 w-4"
                                                            />

                                                            <div>
                                                                <div className="text-sm font-semibold text-slate-700">
                                                                    Withdrawal
                                                                </div>

                                                                <div className="text-xs text-slate-500">
                                                                    {label.can_withdraw
                                                                        ? 'Enabled'
                                                                        : 'Suspended'}
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            )
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="sticky bottom-0 z-20 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                                <button
                                    type="button"
                                    onClick={
                                        closeQuickEdit
                                    }
                                    disabled={
                                        quickEditSaving
                                    }
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    onClick={
                                        saveQuickEdit
                                    }
                                    disabled={
                                        quickEditSaving
                                    }
                                    className="rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {quickEditSaving
                                        ? 'Saving...'
                                        : 'Save Changes'}
                                </button>
                            </div>
                        </div>
                    </div>
                )}


            <div className="space-y-6">
                {/* MIXXTUNE_CLICKABLE_USER_STATS_V1 */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
                    <SummaryCard
                        label="Total Users"
                        value={summary.total}
                        active={
                            !(filters.role ?? '') &&
                            !(filters.status ?? '') &&
                            !(filters.invitation ?? '')
                        }
                        onClick={() =>
                            applyFilters({
                                role: '',
                                status: '',
                                invitation: '',
                            })
                        }
                    />

                    <SummaryCard
                        label="Super Admins"
                        value={summary.super_admins}
                        active={
                            filters.role ===
                            'super_admin'
                        }
                        onClick={() =>
                            applyFilters({
                                role: 'super_admin',
                                status: '',
                                invitation: '',
                            })
                        }
                    />

                    <SummaryCard
                        label="Managers"
                        value={summary.admins}
                        active={
                            filters.role === 'admin'
                        }
                        onClick={() =>
                            applyFilters({
                                role: 'admin',
                                status: '',
                                invitation: '',
                            })
                        }
                    />

                    <SummaryCard
                        label="Labels"
                        value={summary.labels}
                        active={
                            filters.role === 'label'
                        }
                        onClick={() =>
                            applyFilters({
                                role: 'label',
                                status: '',
                                invitation: '',
                            })
                        }
                    />

                    <SummaryCard
                        label="Artists"
                        value={summary.artists}
                        active={
                            filters.role === 'artist'
                        }
                        onClick={() =>
                            applyFilters({
                                role: 'artist',
                                status: '',
                                invitation: '',
                            })
                        }
                    />

                    <SummaryCard
                        label="Invitations"
                        value={
                            summary.pending_invitations
                        }
                        active={
                            filters.invitation ===
                            'pending'
                        }
                        onClick={() =>
                            applyFilters({
                                role: '',
                                status: '',
                                invitation:
                                    'pending',
                            })
                        }
                    />

                    <SummaryCard
                        label="Suspended"
                        value={summary.suspended}
                        active={
                            filters.status ===
                            'suspended'
                        }
                        onClick={() =>
                            applyFilters({
                                role: '',
                                status:
                                    'suspended',
                                invitation: '',
                            })
                        }
                    />
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div className="grid flex-1 gap-3 md:grid-cols-[1fr_180px_180px]">
                            <div className="relative">
                                <input
                                    type="search"
                                    value={search}
                                    autoComplete="off"
                                    onChange={(event) =>
                                        setSearch(
                                            event.target.value
                                        )
                                    }
                                    placeholder="Search name, username, email, phone or label..."
                                    className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                                />
                            </div>

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
                                    Manager
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

                                                <div className="text-xs font-semibold text-violet-600">
                                                    @{user.username}
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

                                                    <div className="text-slate-500">
                                                        Owned Labels:{' '}
                                                        <span className="font-semibold text-slate-700">
                                                            {user.owned_labels_count ?? 0}
                                                        </span>
                                                    </div>
                                                </div>
                                            </Cell>

                                            <Cell>
                                                <div className="space-y-1">
                                                    <InvitationBadge
                                                        status={
                                                            user.invitation_status ??
                                                            'not_invited'
                                                        }
                                                    />

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
                                                <div className="flex min-w-[280px] flex-wrap gap-2">
                                                    {/* MIXXTUNE_SEPARATE_QUICK_EDIT_ACTION_V1 */}
                                                    <Link
                                                        href={`/v2/admin/users/${user.id}/edit`}
                                                        className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700"
                                                    >
                                                        Edit
                                                    </Link>

                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openQuickEdit(
                                                                user
                                                            )
                                                        }
                                                        className="rounded-xl border border-violet-200 bg-violet-50 px-3.5 py-2 text-xs font-bold text-violet-700 shadow-sm transition hover:border-violet-300 hover:bg-violet-100 hover:shadow-md"
                                                    >
                                                        Quick Edit
                                                    </button>

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
                                                        'super_admin' &&
                                                        user.account_status ===
                                                            'active' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                if (
                                                                    window.confirm(
                                                                        `Open the panel as ${user.name}?`
                                                                    )
                                                                ) {
                                                                    router.post(
                                                                        `/v2/admin/users/${user.id}/impersonate`
                                                                    );
                                                                }
                                                            }}
                                                            className="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"
                                                        >
                                                            Login As
                                                        </button>
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

                                                    {user.role !==
                                                        'super_admin' &&
                                                        [
                                                            'not_invited',
                                                            'pending',
                                                            'sent',
                                                            'password_pending',
                                                            'failed',
                                                            'expired',
                                                        ].includes(
                                                            user.invitation_status ??
                                                                'not_invited'
                                                        ) && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                resendInvitation(
                                                                    user
                                                                )
                                                            }
                                                            className={[
                                                                'rounded-lg px-3 py-2 text-xs font-semibold transition',
                                                                (user.invitation_status ??
                                                                    'not_invited') ===
                                                                'not_invited'
                                                                    ? 'bg-violet-600 text-white hover:bg-violet-700'
                                                                    : 'border border-violet-300 bg-violet-50 text-violet-700 hover:bg-violet-100',
                                                            ].join(
                                                                ' '
                                                            )}
                                                        >
                                                            {(user.invitation_status ??
                                                                'not_invited') ===
                                                            'not_invited'
                                                                ? 'Send Invite'
                                                                : 'Resend Invite'}
                                                        </button>
                                                    )}

                                                    {user.invitation_status ===
                                                        'accepted' && (
                                                        <span className="inline-flex cursor-default items-center rounded-lg bg-emerald-100 px-3 py-2 text-xs font-semibold text-emerald-700">
                                                            ✓ Invite Accepted
                                                        </span>
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

function InvitationBadge({
    status = 'not_invited',
}) {
    const labels = {
        not_invited: 'Not Invited',
        pending: 'Invitation Pending',
        sent: 'Invitation Sent',
        password_pending:
            'Password Setup Pending',
        accepted: 'Invite Accepted',
        failed: 'Delivery Failed',
        expired: 'Invitation Expired',
    };

    const classes = {
        not_invited:
            'bg-slate-100 text-slate-700',
        pending:
            'bg-amber-100 text-amber-700',
        sent:
            'bg-blue-100 text-blue-700',
        password_pending:
            'bg-orange-100 text-orange-700',
        accepted:
            'bg-emerald-100 text-emerald-700',
        failed:
            'bg-red-100 text-red-700',
        expired:
            'bg-slate-200 text-slate-700',
    };

    const fallbackLabel = String(status)
        .replaceAll('_', ' ')
        .replace(
            /\b\w/g,
            (letter) => letter.toUpperCase()
        );

    return (
        <span
            className={[
                'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                classes[status] ??
                    'bg-slate-100 text-slate-700',
            ].join(' ')}
        >
            {labels[status] ?? fallbackLabel}
        </span>
    );
}

function SummaryCard({
    label,
    value = 0,
    onClick = null,
    active = false,
}) {
    const classes = [
        'w-full rounded-2xl border p-5 text-left shadow-sm transition-all duration-150',
        onClick
            ? 'cursor-pointer hover:-translate-y-0.5 hover:shadow-md'
            : '',
        active
            ? 'border-violet-500 bg-violet-50 ring-2 ring-violet-100'
            : 'border-slate-200 bg-white hover:border-violet-300',
    ].join(' ');

    const content = (
        <>
            <div
                className={[
                    'text-xs font-semibold uppercase tracking-wide',
                    active
                        ? 'text-violet-700'
                        : 'text-slate-500',
                ].join(' ')}
            >
                {label}
            </div>

            <div
                className={[
                    'mt-2 text-3xl font-bold',
                    active
                        ? 'text-violet-700'
                        : 'text-slate-900',
                ].join(' ')}
            >
                {value ?? 0}
            </div>

            {onClick && (
                <div
                    className={[
                        'mt-2 text-[11px] font-semibold',
                        active
                            ? 'text-violet-600'
                            : 'text-slate-400',
                    ].join(' ')}
                >
                    {active
                        ? 'Filter active'
                        : 'Click to filter'}
                </div>
            )}
        </>
    );

    if (!onClick) {
        return (
            <div className={classes}>
                {content}
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={onClick}
            className={classes}
        >
            {content}
        </button>
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
