import { Head, Link, router, useForm, usePage } from "@inertiajs/react";

import { useEffect, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

const permissionOptions = [
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

export default function Edit({
    role = "super_admin",
    managedUser,
    payoutProfile = null,
    permissions = {},
    artists = [],
    labels = [],
    assignedArtistIds = [],
    assignedLabelIds = [],
}) {
    const { flash = {} } = usePage().props;

    const [temporaryPassword, setTemporaryPassword] = useState(
        flash.temporary_password ?? "",
    );

    const [invitationLink, setInvitationLink] = useState(
        flash.invitation_link ?? "",
    );

    useEffect(() => {
        if (flash.temporary_password) {
            setTemporaryPassword(flash.temporary_password);
        }

        if (flash.invitation_link) {
            setInvitationLink(flash.invitation_link);
        }
    }, [flash.temporary_password, flash.invitation_link]);

    const { data, setData, patch, processing, errors } = useForm({
        name: managedUser?.name ?? "",

        email: managedUser?.email ?? "",

        phone: managedUser?.phone ?? "",

        country: managedUser?.country ?? "",

        role: managedUser?.role ?? "artist",

        account_status: managedUser?.account_status ?? "pending",

        kyc_status: managedUser?.kyc_status ?? "pending",

        account_holder_name:
            payoutProfile?.account_holder_name ?? "",

        bank_account_number: "",

        bank_name:
            payoutProfile?.bank_name ?? "",

        ifsc_code:
            payoutProfile?.ifsc_code ?? "",

        branch_name:
            payoutProfile?.branch_name ?? "",

        upi_id:
            payoutProfile?.upi_id ?? "",

        pan_number: "",

        gst_number:
            payoutProfile?.gst_number ?? "",

        address_line_1:
            payoutProfile?.address_line_1 ?? "",

        address_line_2:
            payoutProfile?.address_line_2 ?? "",

        city:
            payoutProfile?.city ?? "",

        state:
            payoutProfile?.state ?? "",

        postal_code:
            payoutProfile?.postal_code ?? "",

        country_code:
            payoutProfile?.country_code ?? "",


        artist_ids: assignedArtistIds.map(Number),

        label_ids: assignedLabelIds.map(Number),

        new_labels: [""],

        permissions: Object.fromEntries(
            permissionOptions.map(([field]) => [
                field,
                Boolean(permissions?.[field]),
            ]),
        ),
    });

    const updatePermission = (field, checked) => {
        setData("permissions", {
            ...data.permissions,
            [field]: checked,
        });
    };

    const toggleSelection = (field, id) => {
        const numberId = Number(id);

        const selected = data[field];

        setData(
            field,
            selected.includes(numberId)
                ? selected.filter((item) => item !== numberId)
                : [...selected, numberId],
        );
    };

    const addNewLabel = () => {
        setData("new_labels", [...(data.new_labels ?? []), ""]);
    };

    const updateNewLabel = (index, value) => {
        const next = [...(data.new_labels ?? [])];

        next[index] = value;

        setData("new_labels", next);
    };

    const removeNewLabel = (index) => {
        const next = (data.new_labels ?? []).filter(
            (_, itemIndex) => itemIndex !== index,
        );

        setData("new_labels", next.length ? next : [""]);
    };

    const submit = (event) => {
        event.preventDefault();

        patch(`/v2/admin/users/${managedUser.id}`, {
            preserveScroll: true,
        });
    };

    const resetPassword = () => {
        if (
            !window.confirm(
                `Generate a temporary password for ${managedUser.email}?`,
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
                    const password = page.props.flash?.temporary_password;

                    if (password) {
                        setTemporaryPassword(password);
                    }
                },
            },
        );
    };

    const getInvitationLink = () => {
        router.post(
            `/v2/admin/users/${managedUser.id}/invitation-link`,
            {},
            {
                preserveScroll: true,

                onSuccess: (page) => {
                    const link = page.props.flash?.invitation_link;

                    if (link) {
                        setInvitationLink(link);
                    }
                },
            },
        );
    };

    const resendInvitation = () => {
        if (
            !window.confirm(
                `Generate a new invitation for ${managedUser.email}?`,
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
                    setInvitationLink("");
                },
            },
        );
    };

    const copyText = async (text, label) => {
        try {
            await navigator.clipboard.writeText(text);

            window.alert(`${label} copied.`);
        } catch {
            window.prompt(`Copy ${label}:`, text);
        }
    };

    return (
        <PanelLayout
            role={role}
            title="Edit User"
            subtitle={`${managedUser.name} • ${managedUser.email}`}
        >
            <Head title="Edit User" />

            <form onSubmit={submit} className="space-y-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Account Details
                            </h2>

                            <div className="mt-1 text-sm text-slate-500">
                                Last login:{" "}
                                {managedUser.last_login_at ?? "Never"}
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
                            onChange={(value) => setData("name", value)}
                        />

                        <div>
                            <span className="text-sm font-semibold text-slate-700">
                                Username
                            </span>

                            <div className="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                                {managedUser?.username || "—"}
                            </div>

                            <div className="mt-1 text-xs text-slate-500">
                                Permanent username — cannot be changed.
                            </div>
                        </div>

                        <div>
                            <span className="text-sm font-semibold text-slate-700">
                                Client ID
                            </span>

                            <div className="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 font-mono text-sm font-medium text-slate-700">
                                {managedUser?.client_id || "—"}
                            </div>

                            <div className="mt-1 text-xs text-slate-500">
                                Permanent Mixx Tune account identifier.
                            </div>
                        </div>

                        <Field
                            label="Email"
                            type="email"
                            value={data.email}
                            error={errors.email}
                            required
                            onChange={(value) => setData("email", value)}
                        />

                        <Field
                            label="Phone"
                            value={data.phone}
                            error={errors.phone}
                            onChange={(value) => setData("phone", value)}
                        />

                        <Field
                            label="Country"
                            value={data.country}
                            error={errors.country}
                            onChange={(value) => setData("country", value)}
                        />

                        <SelectField
                            label="Role"
                            value={data.role}
                            error={errors.role}
                            onChange={(value) => setData("role", value)}
                            options={[
                                ["super_admin", "Super Admin"],
                                ["admin", "Admin"],
                                ["label", "Label"],
                                ["artist", "Artist"],
                            ]}
                        />

                        <SelectField
                            label="Account Status"
                            value={data.account_status}
                            error={errors.account_status}
                            onChange={(value) =>
                                setData("account_status", value)
                            }
                            options={[
                                ["active", "Active"],
                                ["pending", "Pending"],
                                ["suspended", "Suspended"],
                            ]}
                        />

                        <SelectField
                            label="KYC Status"
                            value={data.kyc_status}
                            error={errors.kyc_status}
                            onChange={(value) => setData("kyc_status", value)}
                            options={[
                                ["pending", "Pending"],
                                ["submitted", "Submitted"],
                                ["verified", "Verified"],
                                ["rejected", "Rejected"],
                            ]}
                        />
                    </div>
                </section>

                                {/* ================================================
                    ADDRESS DETAILS
                ================================================= */}
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            Address Details
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Legal address attached to this account.
                        </p>
                    </div>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <Field
                            label="Address Line 1"
                            value={data.address_line_1}
                            error={errors.address_line_1}
                            onChange={(value) =>
                                setData("address_line_1", value)
                            }
                        />

                        <Field
                            label="Address Line 2"
                            value={data.address_line_2}
                            error={errors.address_line_2}
                            onChange={(value) =>
                                setData("address_line_2", value)
                            }
                        />

                        <Field
                            label="City"
                            value={data.city}
                            error={errors.city}
                            onChange={(value) =>
                                setData("city", value)
                            }
                        />

                        <Field
                            label="State"
                            value={data.state}
                            error={errors.state}
                            onChange={(value) =>
                                setData("state", value)
                            }
                        />

                        <Field
                            label="Postal / PIN Code"
                            value={data.postal_code}
                            error={errors.postal_code}
                            onChange={(value) =>
                                setData("postal_code", value)
                            }
                        />

                        <Field
                            label="Country Code"
                            value={data.country_code}
                            error={errors.country_code}
                            onChange={(value) =>
                                setData(
                                    "country_code",
                                    value.toUpperCase(),
                                )
                            }
                        />
                    </div>
                </section>

                {/* ================================================
                    TAX & KYC
                ================================================= */}
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            Tax & KYC Details
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Tax identity and verification information.
                        </p>
                    </div>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <div>
                            <Field
                                label="PAN Number"
                                value={data.pan_number}
                                error={errors.pan_number}
                                onChange={(value) =>
                                    setData(
                                        "pan_number",
                                        value.toUpperCase(),
                                    )
                                }
                            />

                            {payoutProfile?.masked_pan && (
                                <p className="mt-2 text-xs text-slate-500">
                                    Current PAN:{" "}
                                    <span className="font-mono font-semibold text-slate-700">
                                        {payoutProfile.masked_pan}
                                    </span>
                                    {" "}— leave blank to keep unchanged.
                                </p>
                            )}
                        </div>

                        <Field
                            label="GST Number"
                            value={data.gst_number}
                            error={errors.gst_number}
                            onChange={(value) =>
                                setData(
                                    "gst_number",
                                    value.toUpperCase(),
                                )
                            }
                        />
                    </div>

                    {payoutProfile?.verified_at && (
                        <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                            KYC verified at:{" "}
                            <span className="font-semibold">
                                {payoutProfile.verified_at}
                            </span>
                        </div>
                    )}
                </section>

                {/* ================================================
                    BANK & PAYOUT
                ================================================= */}
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            Bank & Payout Details
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Royalty payment and settlement details.
                        </p>
                    </div>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <Field
                            label="Account Holder Name"
                            value={data.account_holder_name}
                            error={errors.account_holder_name}
                            onChange={(value) =>
                                setData(
                                    "account_holder_name",
                                    value,
                                )
                            }
                        />

                        <div>
                            <Field
                                label="Bank Account Number"
                                value={data.bank_account_number}
                                error={errors.bank_account_number}
                                onChange={(value) =>
                                    setData(
                                        "bank_account_number",
                                        value,
                                    )
                                }
                            />

                            {payoutProfile?.masked_bank_account && (
                                <p className="mt-2 text-xs text-slate-500">
                                    Current account:{" "}
                                    <span className="font-mono font-semibold text-slate-700">
                                        {payoutProfile.masked_bank_account}
                                    </span>
                                    {" "}— leave blank to keep unchanged.
                                </p>
                            )}
                        </div>

                        <Field
                            label="Bank Name"
                            value={data.bank_name}
                            error={errors.bank_name}
                            onChange={(value) =>
                                setData("bank_name", value)
                            }
                        />

                        <Field
                            label="IFSC Code"
                            value={data.ifsc_code}
                            error={errors.ifsc_code}
                            onChange={(value) =>
                                setData(
                                    "ifsc_code",
                                    value.toUpperCase(),
                                )
                            }
                        />

                        <Field
                            label="Branch Name"
                            value={data.branch_name}
                            error={errors.branch_name}
                            onChange={(value) =>
                                setData("branch_name", value)
                            }
                        />

                        <Field
                            label="UPI ID"
                            value={data.upi_id}
                            error={errors.upi_id}
                            onChange={(value) =>
                                setData("upi_id", value)
                            }
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
                                managedUser.invitation_status ?? "Not available"
                            }
                        />

                        <InfoCard
                            label="Invitation Expiry"
                            value={
                                managedUser.invitation_expires_at ??
                                "Not available"
                            }
                        />

                        <InfoCard
                            label="Last Login"
                            value={managedUser.last_login_at ?? "Never"}
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
                                    "temporary password",
                                )
                            }
                            onClose={() => setTemporaryPassword("")}
                        />
                    )}

                    {invitationLink && (
                        <SecretBox
                            title="Invitation Link"
                            value={invitationLink}
                            buttonLabel="Copy Link"
                            onCopy={() =>
                                copyText(invitationLink, "invitation link")
                            }
                            onClose={() => setInvitationLink("")}
                        />
                    )}
                </section>

                {data.role === "admin" && (
                    <div className="grid gap-6 xl:grid-cols-2">
                        <SelectionBox
                            title="Assign Artists"
                            items={artists}
                            selected={data.artist_ids}
                            nameKey="stage_name"
                            onToggle={(id) => toggleSelection("artist_ids", id)}
                            onSelectAll={() =>
                                setData(
                                    "artist_ids",
                                    artists.map((item) => Number(item.id)),
                                )
                            }
                            onClear={() => setData("artist_ids", [])}
                        />

                        <SelectionBox
                            title="Assign Labels"
                            items={labels}
                            selected={data.label_ids}
                            nameKey="name"
                            onToggle={(id) => toggleSelection("label_ids", id)}
                            onSelectAll={() =>
                                setData(
                                    "label_ids",
                                    labels.map((item) => Number(item.id)),
                                )
                            }
                            onClear={() => setData("label_ids", [])}
                        />
                    </div>
                )}

                {data.role === "label" && (
                    <section className="rounded-2xl border border-violet-200 bg-violet-50/40 p-6 shadow-sm">
                        <div className="mb-5">
                            <h2 className="text-lg font-semibold text-slate-900">
                                Labels for this Account
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Super Admin can manage all labels assigned to
                                this login.
                            </p>
                        </div>

                        <SelectionBox
                            title="Assigned Labels"
                            items={labels}
                            selected={data.label_ids}
                            nameKey="name"
                            onToggle={(id) => toggleSelection("label_ids", id)}
                            onSelectAll={() =>
                                setData(
                                    "label_ids",
                                    labels.map((item) => Number(item.id)),
                                )
                            }
                            onClear={() => setData("label_ids", [])}
                        />

                        {errors.label_ids && (
                            <p className="mt-3 text-sm font-medium text-red-600">
                                {errors.label_ids}
                            </p>
                        )}

                        <div className="mt-5 rounded-xl border border-violet-200 bg-white p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div className="text-sm font-semibold text-slate-900">
                                        Create New Labels
                                    </div>

                                    <div className="mt-1 text-xs text-slate-500">
                                        Add more labels to this existing Label
                                        account.
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={addNewLabel}
                                    className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"
                                >
                                    + Add Label
                                </button>
                            </div>

                            <div className="mt-4 space-y-3">
                                {(data.new_labels ?? []).map(
                                    (labelName, index) => (
                                        <div
                                            key={index}
                                            className="flex items-start gap-3"
                                        >
                                            <div className="flex-1">
                                                <input
                                                    type="text"
                                                    value={labelName}
                                                    placeholder={`New label ${index + 1}`}
                                                    onChange={(event) =>
                                                        updateNewLabel(
                                                            index,
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                                />

                                                {errors[
                                                    `new_labels.${index}`
                                                ] && (
                                                    <p className="mt-1 text-xs font-medium text-red-600">
                                                        {
                                                            errors[
                                                                `new_labels.${index}`
                                                            ]
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeNewLabel(index)
                                                }
                                                className="rounded-xl border border-red-200 px-3 py-3 text-sm font-semibold text-red-600 hover:bg-red-50"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    ),
                                )}
                            </div>

                            {errors.new_labels && (
                                <p className="mt-3 text-sm font-medium text-red-600">
                                    {errors.new_labels}
                                </p>
                            )}
                        </div>
                    </section>
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
                                        "permissions",
                                        Object.fromEntries(
                                            permissionOptions.map(([field]) => [
                                                field,
                                                true,
                                            ]),
                                        ),
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
                                        "permissions",
                                        Object.fromEntries(
                                            permissionOptions.map(([field]) => [
                                                field,
                                                false,
                                            ]),
                                        ),
                                    )
                                }
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {permissionOptions.map(([field, label]) => (
                            <label
                                key={field}
                                className={[
                                    "flex cursor-pointer items-center gap-3 rounded-xl border p-4",
                                    data.permissions[field]
                                        ? "border-violet-400 bg-violet-50"
                                        : "border-slate-200 bg-white",
                                ].join(" ")}
                            >
                                <input
                                    type="checkbox"
                                    checked={data.permissions[field]}
                                    onChange={(event) =>
                                        updatePermission(
                                            field,
                                            event.target.checked,
                                        )
                                    }
                                />

                                <span className="text-sm font-semibold text-slate-700">
                                    {label}
                                </span>
                            </label>
                        ))}
                    </div>
                </section>

                {Object.keys(errors).length > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        Some information could not be saved. Please review the
                        highlighted fields and try again.
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
                        {processing ? "Saving..." : "Save User"}
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
    type = "text",
    error,
    required = false,
}) {
    return (
        <label>
            <span className="text-sm font-semibold text-slate-700">
                {label}

                {required && <span className="ml-1 text-red-500">*</span>}
            </span>

            <input
                type={type}
                value={value ?? ""}
                onChange={(event) => onChange(event.target.value)}
                className={[
                    "mt-2 w-full rounded-xl border px-4 py-3 text-sm",
                    error ? "border-red-400" : "border-slate-300",
                ].join(" ")}
            />

            {error && <div className="mt-1 text-xs text-red-600">{error}</div>}
        </label>
    );
}

function SelectField({ label, value, onChange, options, error }) {
    return (
        <label>
            <span className="text-sm font-semibold text-slate-700">
                {label}
            </span>

            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={[
                    "mt-2 w-full rounded-xl border px-4 py-3 text-sm",
                    error ? "border-red-400" : "border-slate-300",
                ].join(" ")}
            >
                {options.map(([optionValue, text]) => (
                    <option key={optionValue} value={optionValue}>
                        {text}
                    </option>
                ))}
            </select>

            {error && <div className="mt-1 text-xs text-red-600">{error}</div>}
        </label>
    );
}

function InfoCard({ label, value }) {
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

function SecretBox({ title, value, buttonLabel, onCopy, onClose }) {
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
    const [query, setQuery] = useState("");
    const [open, setOpen] = useState(false);

    const selectedIds = new Set(
        (selected ?? []).map((id) => Number(id)),
    );

    const selectedItems = (items ?? []).filter((item) =>
        selectedIds.has(Number(item.id)),
    );

    const search = query.trim().toLowerCase();

    const suggestions =
        search.length >= 3
            ? (items ?? [])
                  .filter((item) => {
                      if (selectedIds.has(Number(item.id))) {
                          return false;
                      }

                      const name = String(
                          item[nameKey] ?? "",
                      ).toLowerCase();

                      const email = String(
                          item.email ?? "",
                      ).toLowerCase();

                      return (
                          name.includes(search) ||
                          email.includes(search)
                      );
                  })
                  .slice(0, 12)
            : [];

    const selectItem = (id) => {
        if (!selectedIds.has(Number(id))) {
            onToggle(id);
        }

        setQuery("");
        setOpen(true);
    };

    return (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="mb-3 flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold text-slate-900">
                        {title}
                    </h2>

                    <p className="mt-0.5 text-xs text-slate-500">
                        Search by name or email.
                    </p>
                </div>

                {selectedItems.length > 0 && (
                    <span className="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">
                        {selectedItems.length} selected
                    </span>
                )}
            </div>

            <div className="relative">
                <div className="flex min-h-[44px] flex-wrap items-center gap-2 rounded-lg border border-slate-300 bg-white px-2.5 py-2 focus-within:border-violet-500 focus-within:ring-2 focus-within:ring-violet-100">
                    {selectedItems.map((item) => (
                        <span
                            key={item.id}
                            className="inline-flex max-w-full items-center gap-1.5 rounded-md border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-800"
                        >
                            <span className="max-w-[200px] truncate">
                                {item[nameKey]}
                            </span>

                            <button
                                type="button"
                                onClick={() => onToggle(item.id)}
                                className="flex h-4 w-4 items-center justify-center rounded-full text-sm leading-none text-violet-500 hover:bg-violet-200 hover:text-violet-900"
                                title="Remove"
                            >
                                ×
                            </button>
                        </span>
                    ))}

                    <input
                        type="text"
                        value={query}
                        autoComplete="off"
                        onFocus={() => setOpen(true)}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setOpen(true);
                        }}
                        onBlur={() => {
                            window.setTimeout(
                                () => setOpen(false),
                                180,
                            );
                        }}
                        onKeyDown={(event) => {
                            if (
                                event.key === "Enter" &&
                                suggestions.length === 1
                            ) {
                                event.preventDefault();
                                selectItem(suggestions[0].id);
                            }

                            if (
                                event.key === "Backspace" &&
                                query === "" &&
                                selectedItems.length > 0
                            ) {
                                onToggle(
                                    selectedItems[
                                        selectedItems.length - 1
                                    ].id,
                                );
                            }
                        }}
                        placeholder={
                            selectedItems.length
                                ? "Search more..."
                                : "Type minimum 3 characters..."
                        }
                        className="min-w-[180px] flex-1 border-0 bg-transparent px-1 py-1.5 text-sm outline-none placeholder:text-slate-400 focus:ring-0"
                    />
                </div>

                {open &&
                    search.length > 0 &&
                    search.length < 3 && (
                        <div className="absolute left-0 right-0 z-50 mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500 shadow-lg">
                            Type {3 - search.length} more character
                            {3 - search.length === 1 ? "" : "s"}.
                        </div>
                    )}

                {open && search.length >= 3 && (
                    <div className="absolute left-0 right-0 z-50 mt-1 max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-xl">
                        {suggestions.length > 0 ? (
                            suggestions.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onMouseDown={(event) => {
                                        event.preventDefault();
                                        selectItem(item.id);
                                    }}
                                    className="flex w-full items-center justify-between gap-4 rounded-md px-3 py-2.5 text-left hover:bg-violet-50"
                                >
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-semibold text-slate-900">
                                            {item[nameKey]}
                                        </div>

                                        <div className="mt-0.5 truncate text-xs text-slate-500">
                                            {item.email ?? "No email"}
                                        </div>
                                    </div>

                                    <span className="shrink-0 text-xs font-semibold text-violet-600">
                                        Select
                                    </span>
                                </button>
                            ))
                        ) : (
                            <div className="px-3 py-4 text-center text-sm text-slate-500">
                                No matching records found.
                            </div>
                        )}
                    </div>
                )}
            </div>

            {selectedItems.length > 0 && (
                <div className="mt-2 flex justify-end">
                    <button
                        type="button"
                        onClick={onClear}
                        className="text-xs font-semibold text-slate-500 hover:text-red-600"
                    >
                        Clear all
                    </button>
                </div>
            )}
        </section>
    );
}
