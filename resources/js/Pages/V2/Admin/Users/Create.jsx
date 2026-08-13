import { useState } from "react";
import { Head, Link, useForm } from "@inertiajs/react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

const indiaStates = [
    ["AN", "Andaman and Nicobar Islands"],
    ["AP", "Andhra Pradesh"],
    ["AR", "Arunachal Pradesh"],
    ["AS", "Assam"],
    ["BR", "Bihar"],
    ["CH", "Chandigarh"],
    ["CG", "Chhattisgarh"],
    ["DN", "Dadra and Nagar Haveli and Daman and Diu"],
    ["DL", "Delhi"],
    ["GA", "Goa"],
    ["GJ", "Gujarat"],
    ["HR", "Haryana"],
    ["HP", "Himachal Pradesh"],
    ["JK", "Jammu and Kashmir"],
    ["JH", "Jharkhand"],
    ["KA", "Karnataka"],
    ["KL", "Kerala"],
    ["LA", "Ladakh"],
    ["LD", "Lakshadweep"],
    ["MP", "Madhya Pradesh"],
    ["MH", "Maharashtra"],
    ["MN", "Manipur"],
    ["ML", "Meghalaya"],
    ["MZ", "Mizoram"],
    ["NL", "Nagaland"],
    ["OD", "Odisha"],
    ["PY", "Puducherry"],
    ["PB", "Punjab"],
    ["RJ", "Rajasthan"],
    ["SK", "Sikkim"],
    ["TN", "Tamil Nadu"],
    ["TS", "Telangana"],
    ["TR", "Tripura"],
    ["UP", "Uttar Pradesh"],
    ["UK", "Uttarakhand"],
    ["WB", "West Bengal"],
];

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

export default function Create({
    role = "super_admin",
    artists = [],
    labels = [],
}) {
    const { data, setData, post, processing, errors } = useForm({
        name: "",
        username: "",
        email: "",
        phone: "",
        country: "India",
        state_code: "DL",
        role: "admin",
        account_status: "active",
        revenue_share_percentage: "",
        send_invitation: true,

        account_holder_name: "",
        bank_account_number: "",
        bank_name: "",
        ifsc_code: "",
        branch_name: "",
        upi_id: "",

        pan_number: "",
        gst_number: "",

        address_line_1: "",
        address_line_2: "",
        city: "",
        state: "",
        postal_code: "",
        country_code: "IN",

        artist_ids: [],
        label_ids: [],
        new_labels: [""],
        permissions: Object.fromEntries(
            permissionOptions.map(([field]) => [field, false]),
        ),
    });

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

    const togglePermission = (field, checked) => {
        setData("permissions", {
            ...data.permissions,
            [field]: checked,
        });
    };

    const applyRoleDefaults = (selectedRole) => {
        const enabled = new Set();

        if (selectedRole === "admin") {
            [
                "can_view_catalogue",
                "can_manage_releases",
                "can_view_reports",
                "can_view_royalties",
                "can_manage_support",
                "can_manage_delivery",
                "can_manage_identifiers",
            ].forEach((item) => enabled.add(item));
        }

        if (selectedRole === "label") {
            [
                "can_view_catalogue",
                "can_create_releases",
                "can_view_reports",
                "can_view_royalties",
                "can_manage_wallet",
                "can_manage_withdrawals",
            ].forEach((item) => enabled.add(item));
        }

        if (selectedRole === "artist") {
            [
                "can_view_catalogue",
                "can_create_releases",
                "can_view_reports",
                "can_view_royalties",
            ].forEach((item) => enabled.add(item));
        }

        setData((current) => ({
            ...current,
            role: selectedRole,
            artist_ids: selectedRole === "admin" ? current.artist_ids : [],
            label_ids: ["admin", "label"].includes(selectedRole)
                ? current.label_ids
                : [],
            new_labels:
                selectedRole === "label"
                    ? current.new_labels?.length
                        ? current.new_labels
                        : [""]
                    : [],
            permissions: Object.fromEntries(
                permissionOptions.map(([field]) => [field, enabled.has(field)]),
            ),
        }));
    };

    const selectAll = (field, items) => {
        setData(
            field,
            items.map((item) => Number(item.id)),
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

    const generateUsername = () => {
        const source = data.name || data.email || "user";

        const generated = source
            .toLowerCase()
            .normalize("NFKD")
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .slice(0, 40);

        setData(
            "username",
            generated.length >= 4
                ? generated
                : `${generated || "user"}-account`,
        );
    };

    const submit = (event) => {
        event.preventDefault();

        post("/v2/admin/users", {
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

            <form onSubmit={submit} className="space-y-4">
                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between gap-4">
                        <h2 className="text-base font-semibold text-slate-900">
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
                            onChange={(value) => setData("name", value)}
                        />

                        <div>
                            <Field
                                label="Username"
                                value={data.username}
                                error={errors.username}
                                onChange={(value) =>
                                    setData(
                                        "username",
                                        value
                                            .toLowerCase()
                                            .replace(/[^a-z0-9-]/g, ""),
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

                        {["label", "artist"].includes(data.role) && (
                            <label>
                                <span className="text-sm font-semibold text-slate-700">
                                    State / Region
                                </span>

                                <select
                                    value={data.state_code}
                                    onChange={(event) =>
                                        setData(
                                            "state_code",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    required
                                >
                                    {indiaStates.map(([code, name]) => (
                                        <option key={code} value={code}>
                                            {name} ({code})
                                        </option>
                                    ))}
                                </select>

                                {errors.state_code && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.state_code}
                                    </p>
                                )}
                            </label>
                        )}

                        <label>
                            <span className="text-sm font-semibold text-slate-700">
                                Role
                            </span>

                            <select
                                value={data.role}
                                onChange={(event) =>
                                    applyRoleDefaults(event.target.value)
                                }
                                className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="admin">Admin</option>

                                <option value="label">Label</option>

                                <option value="artist">Artist</option>
                            </select>
                        </label>

                        {["label", "artist"].includes(data.role) && (
                            <Field
                                label="Assigned Revenue Rate (%)"
                                type="number"
                                value={data.revenue_share_percentage}
                                error={errors.revenue_share_percentage}
                                required
                                onChange={(value) =>
                                    setData(
                                        "revenue_share_percentage",
                                        value,
                                    )
                                }
                            />
                        )}

                        <label>
                            <span className="text-sm font-semibold text-slate-700">
                                Account Status
                            </span>

                            <select
                                value={data.account_status}
                                onChange={(event) =>
                                    setData(
                                        "account_status",
                                        event.target.value,
                                    )
                                }
                                className="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="active">Active</option>

                                <option value="pending">Pending</option>

                                <option value="suspended">Suspended</option>
                            </select>
                        </label>
                    </div>

                    <label className="mt-5 flex items-center gap-3 rounded-xl border border-violet-200 bg-violet-50 p-4">
                        <input
                            type="checkbox"
                            checked={data.send_invitation}
                            onChange={(event) =>
                                setData("send_invitation", event.target.checked)
                            }
                        />

                        <div>
                            <div className="text-sm font-semibold text-violet-800">
                                Send Invitation
                            </div>

                            <div className="text-xs text-violet-600">
                                Generate a secure password setup invitation
                                valid for seven days.
                            </div>
                        </div>
                    </label>
                </section>

                {["label", "artist"].includes(data.role) && (
                    <>
                        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="mb-3">
                                <h2 className="text-base font-semibold text-slate-900">
                                    Registered Address
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    Permanent address for the account profile.
                                </p>
                            </div>

                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                <Field
                                    label="Address Line 1"
                                    value={data.address_line_1}
                                    error={errors.address_line_1}
                                    required
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
                                    required
                                    onChange={(value) => setData("city", value)}
                                />

                                <Field
                                    label="State"
                                    value={data.state}
                                    error={errors.state}
                                    required
                                    onChange={(value) =>
                                        setData("state", value)
                                    }
                                />

                                <Field
                                    label="Postal Code"
                                    value={data.postal_code}
                                    error={errors.postal_code}
                                    required
                                    onChange={(value) =>
                                        setData("postal_code", value)
                                    }
                                />

                                <Field
                                    label="Country Code"
                                    value={data.country_code}
                                    error={errors.country_code}
                                    required
                                    onChange={(value) =>
                                        setData(
                                            "country_code",
                                            value
                                                .toUpperCase()
                                                .replace(/[^A-Z]/g, "")
                                                .slice(0, 2),
                                        )
                                    }
                                />
                            </div>
                        </section>

                        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="mb-3">
                                <h2 className="text-base font-semibold text-slate-900">
                                    Tax Information
                                </h2>

                                <p className="mt-0.5 text-xs text-slate-500">
                                    PAN and GST details for this account.
                                </p>
                            </div>

                            <div className="grid gap-3 md:grid-cols-2">
                                <Field
                                    label="PAN Number"
                                    value={data.pan_number}
                                    error={errors.pan_number}
                                    required
                                    onChange={(value) =>
                                        setData(
                                            "pan_number",
                                            value
                                                .toUpperCase()
                                                .replace(/\s+/g, ""),
                                        )
                                    }
                                />

                                <Field
                                    label="GST Number"
                                    value={data.gst_number}
                                    error={errors.gst_number}
                                    onChange={(value) =>
                                        setData(
                                            "gst_number",
                                            value
                                                .toUpperCase()
                                                .replace(/\s+/g, ""),
                                        )
                                    }
                                />
                            </div>
                        </section>

                        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="mb-3">
                                <h2 className="text-base font-semibold text-slate-900">
                                    Bank Account Information
                                </h2>

                                <p className="mt-0.5 text-xs text-slate-500">
                                    Bank details used for royalty payouts.
                                </p>
                            </div>

                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                <Field
                                    label="Account Holder Name"
                                    value={data.account_holder_name}
                                    error={errors.account_holder_name}
                                    required
                                    onChange={(value) =>
                                        setData("account_holder_name", value)
                                    }
                                />

                                <Field
                                    label="Bank Account Number"
                                    value={data.bank_account_number}
                                    error={errors.bank_account_number}
                                    required
                                    onChange={(value) =>
                                        setData("bank_account_number", value)
                                    }
                                />

                                <Field
                                    label="Bank Name"
                                    value={data.bank_name}
                                    error={errors.bank_name}
                                    required
                                    onChange={(value) =>
                                        setData("bank_name", value)
                                    }
                                />

                                <Field
                                    label="IFSC Code"
                                    value={data.ifsc_code}
                                    error={errors.ifsc_code}
                                    required
                                    onChange={(value) =>
                                        setData(
                                            "ifsc_code",
                                            value
                                                .toUpperCase()
                                                .replace(/\s+/g, ""),
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

                            </div>
                        </section>
                    </>
                )}

                {data.role === "admin" && (
                    <div className="grid gap-4 xl:grid-cols-2">
                        <SelectionBox
                            title="Assign Artists"
                            items={artists}
                            selected={data.artist_ids}
                            nameKey="stage_name"
                            onToggle={(id) => toggleSelection("artist_ids", id)}
                            onSelectAll={() => selectAll("artist_ids", artists)}
                            onClear={() => setData("artist_ids", [])}
                        />

                        <SelectionBox
                            title="Assign Labels"
                            items={labels}
                            selected={data.label_ids}
                            nameKey="name"
                            onToggle={(id) => toggleSelection("label_ids", id)}
                            onSelectAll={() => selectAll("label_ids", labels)}
                            onClear={() => setData("label_ids", [])}
                        />
                    </div>
                )}

                {data.role === "label" && (
                    <section className="rounded-xl border border-violet-200 bg-violet-50/30 p-4 shadow-sm">
                        <div className="mb-3">
                            <h2 className="text-base font-semibold text-slate-900">
                                Labels for this Account
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Select all labels this user can manage. More
                                labels can be added later by Super Admin.
                            </p>
                        </div>

                        <SelectionBox
                            title="Assigned Labels"
                            items={labels}
                            selected={data.label_ids}
                            nameKey="name"
                            onToggle={(id) => toggleSelection("label_ids", id)}
                            onSelectAll={() => selectAll("label_ids", labels)}
                            onClear={() => setData("label_ids", [])}
                        />

                        {errors.label_ids && (
                            <p className="mt-3 text-sm font-medium text-red-600">
                                {errors.label_ids}
                            </p>
                        )}

                        <div className="mt-3 rounded-lg border border-violet-200 bg-white p-3">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div className="text-sm font-semibold text-slate-900">
                                        Create New Labels
                                    </div>

                                    <div className="mt-1 text-xs text-slate-500">
                                        Add one or more label names. Only Super
                                        Admin can create labels.
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

                            <div className="mt-3 space-y-2">
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
                                                    placeholder={`Label ${index + 1} name`}
                                                    onChange={(event) =>
                                                        updateNewLabel(
                                                            index,
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"
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

                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-base font-semibold text-slate-900">
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
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700"
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
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700"
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
                                    "flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition",
                                    data.permissions[field]
                                        ? "border-violet-400 bg-violet-50"
                                        : "border-slate-200 bg-white",
                                ].join(" ")}
                            >
                                <input
                                    type="checkbox"
                                    checked={data.permissions[field]}
                                    onChange={(event) =>
                                        togglePermission(
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
                        fields.
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
                        {processing ? "Creating..." : "Create User"}
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
                value={value}
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
