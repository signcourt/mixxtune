#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/user-management-edit-ui/$STAMP"

mkdir -p \
    "$BACKUP" \
    resources/js/Pages/V2/Admin/Users \
    v2/runtime/state

INDEX_FILE="resources/js/Pages/V2/Admin/Users/Index.jsx"
EDIT_FILE="resources/js/Pages/V2/Admin/Users/Edit.jsx"

echo "=================================================="
echo "INSTALLING USER MANAGEMENT EDIT UI"
echo "=================================================="

for FILE in \
    "$INDEX_FILE" \
    "$EDIT_FILE"
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done

echo "[1/4] Creating Edit User page..."

cat > "$EDIT_FILE" <<'JSX'
import {
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/react';

import {
    useEffect,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const permissionOptions = [
    ['can_view_catalogue', 'View Catalogue'],
    ['can_create_releases', 'Create Releases'],
    ['can_manage_releases', 'Manage Releases'],
    ['can_view_reports', 'View Reports'],
    ['can_view_royalties', 'View Royalties'],
    ['can_manage_wallet', 'Manage Wallet'],
    ['can_manage_withdrawals', 'Manage Withdrawals'],
    ['can_manage_users', 'Manage Users'],
    ['can_manage_support', 'Manage Support'],
    ['can_manage_settings', 'Manage Settings'],
    ['can_manage_delivery', 'Manage Delivery'],
    ['can_manage_identifiers', 'Manage ISRC / UPC'],
];

export default function Edit({
    role = 'super_admin',
    managedUser,
    permissions = {},
    artists = [],
    labels = [],
    assignedArtistIds = [],
    assignedLabelIds = [],
}) {
    const {
        flash = {},
    } = usePage().props;

    const [temporaryPassword, setTemporaryPassword] =
        useState(
            flash.temporary_password ?? ''
        );

    const [invitationLink, setInvitationLink] =
        useState(
            flash.invitation_link ?? ''
        );

    useEffect(() => {
        if (flash.temporary_password) {
            setTemporaryPassword(
                flash.temporary_password
            );
        }

        if (flash.invitation_link) {
            setInvitationLink(
                flash.invitation_link
            );
        }
    }, [
        flash.temporary_password,
        flash.invitation_link,
    ]);

    const {
        data,
        setData,
        patch,
        processing,
        errors,
    } = useForm({
        name:
            managedUser?.name ?? '',

        email:
            managedUser?.email ?? '',

        phone:
            managedUser?.phone ?? '',

        country:
            managedUser?.country ?? '',

        role:
            managedUser?.role ?? 'artist',

        account_status:
            managedUser?.account_status ??
            'pending',

        kyc_status:
            managedUser?.kyc_status ??
            'pending',

        artist_ids:
            assignedArtistIds.map(Number),

        label_ids:
            assignedLabelIds.map(Number),

        permissions:
            Object.fromEntries(
                permissionOptions.map(
                    ([field]) => [
                        field,
                        Boolean(
                            permissions?.[field]
                        ),
                    ]
                )
            ),
    });

    const updatePermission = (
        field,
        checked
    ) => {
        setData('permissions', {
            ...data.permissions,
            [field]: checked,
        });
    };

    const toggleSelection = (
        field,
        id
    ) => {
        const numberId =
            Number(id);

        const selected =
            data[field];

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

    const submit = (event) => {
        event.preventDefault();

        patch(
            `/v2/admin/users/${managedUser.id}`,
            {
                preserveScroll: true,
            }
        );
    };

    const resetPassword = () => {
        if (
            !window.confirm(
                `Generate a temporary password for ${managedUser.email}?`
            )
        ) {
            return;
        }

        router.post(
            `/v2/admin/users/${managedUser.id}/reset-password`,
            {},
            {
                preserveScroll: true,

                onSuccess: (page) => {
                    const password =
                        page.props.flash
                            ?.temporary_password;

                    if (password) {
                        setTemporaryPassword(
                            password
                        );
                    }
                },
            }
        );
    };

    const getInvitationLink = () => {
        router.post(
            `/v2/admin/users/${managedUser.id}/invitation-link`,
            {},
            {
                preserveScroll: true,

                onSuccess: (page) => {
                    const link =
                        page.props.flash
                            ?.invitation_link;

                    if (link) {
                        setInvitationLink(
                            link
                        );
                    }
                },
            }
        );
    };

    const resendInvitation = () => {
        if (
            !window.confirm(
                `Generate a new invitation for ${managedUser.email}?`
            )
        ) {
            return;
        }

        router.post(
            `/v2/admin/users/${managedUser.id}/resend-invitation`,
            {},
            {
                preserveScroll: true,

                onSuccess: () => {
                    setInvitationLink('');
                },
            }
        );
    };

    const copyText = async (
        text,
        label
    ) => {
        try {
            await navigator.clipboard.writeText(
                text
            );

            window.alert(
                `${label} copied.`
            );
        } catch {
            window.prompt(
                `Copy ${label}:`,
                text
            );
        }
    };

    return (
        <PanelLayout
            role={role}
            title="Edit User"
            subtitle={`${managedUser.name} • ${managedUser.email}`}
        >
            <Head title="Edit User" />

            <form
                onSubmit={submit}
                className="space-y-6"
            >
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Account Details
                            </h2>

                            <div className="mt-1 text-sm text-slate-500">
                                Last login:{' '}
                                {managedUser.last_login_at ??
                                    'Never'}
                            </div>
                        </div>

                        <Link
                            href="/v2/admin/users"
                            className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            Back to Users
                        </Link>
                    </div>

                    <div className="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
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

                        <SelectField
                            label="Role"
                            value={data.role}
                            error={errors.role}
                            onChange={(value) =>
                                setData(
                                    'role',
                                    value
                                )
                            }
                            options={[
                                [
                                    'super_admin',
                                    'Super Admin',
                                ],
                                ['admin', 'Admin'],
                                ['label', 'Label'],
                                ['artist', 'Artist'],
                            ]}
                        />

                        <SelectField
                            label="Account Status"
                            value={
                                data.account_status
                            }
                            error={
                                errors.account_status
                            }
                            onChange={(value) =>
                                setData(
                                    'account_status',
                                    value
                                )
                            }
                            options={[
                                ['active', 'Active'],
                                ['pending', 'Pending'],
                                [
                                    'suspended',
                                    'Suspended',
                                ],
                            ]}
                        />

                        <SelectField
                            label="KYC Status"
                            value={data.kyc_status}
                            error={
                                errors.kyc_status
                            }
                            onChange={(value) =>
                                setData(
                                    'kyc_status',
                                    value
                                )
                            }
                            options={[
                                ['pending', 'Pending'],
                                [
                                    'submitted',
                                    'Submitted',
                                ],
                                [
                                    'verified',
                                    'Verified',
                                ],
                                [
                                    'rejected',
                                    'Rejected',
                                ],
                            ]}
                        />
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Security & Invitation
                    </h2>

                    <div className="mt-5 grid gap-4 lg:grid-cols-3">
                        <InfoCard
                            label="Invitation Status"
                            value={
                                managedUser.invitation_status ??
                                'Not available'
                            }
                        />

                        <InfoCard
                            label="Invitation Expiry"
                            value={
                                managedUser.invitation_expires_at ??
                                'Not available'
                            }
                        />

                        <InfoCard
                            label="Last Login"
                            value={
                                managedUser.last_login_at ??
                                'Never'
                            }
                        />
                    </div>

                    <div className="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={resetPassword}
                            className="rounded-xl bg-amber-600 px-5 py-3 text-sm font-semibold text-white hover:bg-amber-700"
                        >
                            Reset Password
                        </button>

                        <button
                            type="button"
                            onClick={resendInvitation}
                            className="rounded-xl border border-violet-300 bg-violet-50 px-5 py-3 text-sm font-semibold text-violet-700"
                        >
                            Resend Invitation
                        </button>

                        <button
                            type="button"
                            onClick={getInvitationLink}
                            className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700"
                        >
                            Get Invitation Link
                        </button>
                    </div>

                    {temporaryPassword && (
                        <SecretBox
                            title="Temporary Password"
                            value={temporaryPassword}
                            buttonLabel="Copy Password"
                            onCopy={() =>
                                copyText(
                                    temporaryPassword,
                                    'temporary password'
                                )
                            }
                            onClose={() =>
                                setTemporaryPassword(
                                    ''
                                )
                            }
                        />
                    )}

                    {invitationLink && (
                        <SecretBox
                            title="Invitation Link"
                            value={invitationLink}
                            buttonLabel="Copy Link"
                            onCopy={() =>
                                copyText(
                                    invitationLink,
                                    'invitation link'
                                )
                            }
                            onClose={() =>
                                setInvitationLink(
                                    ''
                                )
                            }
                        />
                    )}
                </section>

                {data.role === 'admin' && (
                    <div className="grid gap-6 xl:grid-cols-2">
                        <SelectionBox
                            title="Assigned Artists"
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
                                setData(
                                    'artist_ids',
                                    artists.map(
                                        (item) =>
                                            Number(
                                                item.id
                                            )
                                    )
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
                            title="Assigned Labels"
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
                                setData(
                                    'label_ids',
                                    labels.map(
                                        (item) =>
                                            Number(
                                                item.id
                                            )
                                    )
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
                                                ([field]) => [
                                                    field,
                                                    true,
                                                ]
                                            )
                                        )
                                    )
                                }
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold"
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
                                                ([field]) => [
                                                    field,
                                                    false,
                                                ]
                                            )
                                        )
                                    )
                                }
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold"
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
                                        'flex cursor-pointer items-center gap-3 rounded-xl border p-4',
                                        data.permissions[
                                            field
                                        ]
                                            ? 'border-violet-400 bg-violet-50'
                                            : 'border-slate-200 bg-white',
                                    ].join(' ')}
                                >
                                    <input
                                        type="checkbox"
                                        checked={
                                            data.permissions[
                                                field
                                            ]
                                        }
                                        onChange={(
                                            event
                                        ) =>
                                            updatePermission(
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
                        कुछ fields save नहीं हुए। Entered details check करें।
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
                            ? 'Saving...'
                            : 'Save User'}
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
                value={value ?? ''}
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

function SelectField({
    label,
    value,
    onChange,
    options,
    error,
}) {
    return (
        <label>
            <span className="text-sm font-semibold text-slate-700">
                {label}
            </span>

            <select
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
            >
                {options.map(
                    ([optionValue, text]) => (
                        <option
                            key={optionValue}
                            value={optionValue}
                        >
                            {text}
                        </option>
                    )
                )}
            </select>

            {error && (
                <div className="mt-1 text-xs text-red-600">
                    {error}
                </div>
            )}
        </label>
    );
}

function InfoCard({
    label,
    value,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="text-xs font-semibold uppercase text-slate-500">
                {label}
            </div>

            <div className="mt-2 break-all text-sm font-semibold capitalize text-slate-900">
                {value}
            </div>
        </div>
    );
}

function SecretBox({
    title,
    value,
    buttonLabel,
    onCopy,
    onClose,
}) {
    return (
        <div className="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <div className="text-sm font-semibold text-amber-800">
                        {title}
                    </div>

                    <div className="mt-2 break-all rounded-lg bg-white p-3 font-mono text-sm text-slate-800">
                        {value}
                    </div>
                </div>

                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={onCopy}
                        className="rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white"
                    >
                        {buttonLabel}
                    </button>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800"
                    >
                        Hide
                    </button>
                </div>
            </div>
        </div>
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
                                    onToggle(
                                        item.id
                                    )
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

echo "[2/4] Adding Edit button to Users Index..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "resources/js/Pages/V2/Admin/Users/Index.jsx"
)

text = path.read_text()

needle = """                                                <div className="flex min-w-[220px] flex-wrap gap-2">
"""

replacement = """                                                <div className="flex min-w-[280px] flex-wrap gap-2">
                                                    <Link
                                                        href={`/v2/admin/users/${user.id}/edit`}
                                                        className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700"
                                                    >
                                                        Edit
                                                    </Link>

"""

if "/edit`}" not in text:
    if needle not in text:
        raise SystemExit(
            "Users Index action block नहीं मिला."
        )

    text = text.replace(
        needle,
        replacement,
        1
    )

path.write_text(text)

print("Edit button added to Users Index.")
PY

echo "[3/4] Running build..."

test -f "$EDIT_FILE"

npm run build

php artisan optimize:clear

echo "[4/4] Verifying routes and page..."

php artisan route:list | grep -E \
"v2/admin/users.*(edit|reset-password|invitation-link)|v2.admin.users.update"

ls -lah \
resources/js/Pages/V2/Admin/Users/Index.jsx \
resources/js/Pages/V2/Admin/Users/Edit.jsx

printf '{\n  "module": "UserManagementEditUI",\n  "installed": true,\n  "version": "4.7.3",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/user-management-edit-ui-installed.json

echo ""
echo "=================================================="
echo "USER MANAGEMENT EDIT UI INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/user-management-edit-ui-installed.json

echo ""
echo "Users:"
echo "https://admin.mixxtune.com/v2/admin/users"

echo ""
echo "Edit URL format:"
echo "https://admin.mixxtune.com/v2/admin/users/USER_ID/edit"

echo ""
echo "Backup:"
echo "$BACKUP"
