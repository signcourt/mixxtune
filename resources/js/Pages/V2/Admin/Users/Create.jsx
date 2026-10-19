import UserRequiredFieldsPanel from "@/V2/Admin/UserRequiredFieldsPanel";
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

const indiaStateNameByCode = Object.fromEntries(indiaStates);

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

const permissionDefaultsForRole = (role) => {
    const enabled = new Set();

    if (role === "admin") {
        [
            "can_view_catalogue",
            "can_manage_releases",
            "can_view_reports",
            "can_manage_users",
            "can_manage_support",
            "can_manage_delivery",
            "can_manage_identifiers",
        ].forEach((item) => enabled.add(item));
    }

    if (role === "label") {
        [
            "can_view_catalogue",
            "can_create_releases",
            "can_view_reports",
            "can_view_royalties",
            "can_manage_wallet",
            "can_manage_withdrawals",
            "can_manage_support",
        ].forEach((item) => enabled.add(item));
    }

    if (role === "artist") {
        [
            "can_view_catalogue",
            "can_create_releases",
            "can_view_reports",
            "can_view_royalties",
            "can_manage_wallet",
            "can_manage_withdrawals",
            "can_manage_support",
        ].forEach((item) => enabled.add(item));
    }

    return Object.fromEntries(
        permissionOptions.map(([field]) => [
            field,
            enabled.has(field),
        ]),
    );
};

export default function Create({
    role = "super_admin",
    accountType = "user",
    requiredFields = {},
    requiredFieldOptions = {},
    canEditRequiredFields = false,
    artists = [],
    labels = [],
}) {
    /* MIXXTUNE_CREATE_USER_LAYOUT_V2: Users page creates Label/Artist only;
       Managers are created from Accounts & Access → Managers. */
    const isManagerForm = accountType === "manager";
    const initialRole = isManagerForm ? "admin" : "label";
    const backHref = isManagerForm ? "/v2/admin/admins" : "/v2/admin/users";

    const { data, setData, post, processing, errors } = useForm({
        name: "",
        username: "",
        email: "",
        phone: "",
        country: "India",
        state_code: "",
        role: initialRole,
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
        new_labels: initialRole === "label" ? [""] : [],
        permissions: permissionDefaultsForRole(initialRole),
    });

    const isPayee = ["label", "artist"].includes(data.role);
    const req = (field) => fieldRequired(requiredFields, data.role, field);

    const toggleSelection = (field, id) => {
        const numberId = Number(id);
        const selected = data[field];

        if (
            field === "label_ids" &&
            data.role === "label"
        ) {
            setData(
                field,
                selected.includes(numberId)
                    ? []
                    : [numberId],
            );
            return;
        }

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
            permissions: permissionDefaultsForRole(selectedRole),
        }));
    };

    const selectAll = (field, items) => {
        setData(
            field,
            items.map((item) => Number(item.id)),
        );
    };

    const updateNewLabel = (index, value) => {
        const next = [...(data.new_labels ?? [])];

        next[index] = value;

        setData("new_labels", next);
    };

    const clearNewLabel = () => {
        setData("new_labels", [""]);
    };

    const generateUsername = () => {
        const source = data.name || data.email?.split("@")[0] || "user";

        let generated = source
            .toLowerCase()
            .normalize("NFKD")
            .replace(/[^a-z0-9]/g, "")
            .slice(0, 40);

        if (generated.length < 4) {
            generated = `${generated || "user"}account`.slice(0, 40);
        }

        setData("username", generated);
    };

    const setAllPermissions = (value) => {
        setData(
            "permissions",
            Object.fromEntries(
                permissionOptions.map(([field]) => [field, value]),
            ),
        );
    };

    const submit = (event) => {
        event.preventDefault();

        post("/v2/admin/users", {
            preserveScroll: true,
        });
    };

    const nameLabel =
        data.role === "label"
            ? "Owner name (account name)"
            : data.role === "artist"
              ? "Artist name (account name)"
              : "Manager name";

    const pageTitle = isManagerForm ? "Create Manager" : "Create User";

    return (
        <PanelLayout
            role={role}
            title={pageTitle}
            subtitle={
                isManagerForm
                    ? "Create a Manager account and choose the Artists and Labels they handle"
                    : "Create a Label or Artist account"
            }
        >
            <Head title={pageTitle} />
            {/* MIXXTUNE_USER_REQUIRED_FIELDS_V1 */}
            {canEditRequiredFields && (
                <UserRequiredFieldsPanel
                    requiredFields={requiredFields}
                    options={requiredFieldOptions}
                    currentRole={data.role}
                />
            )}

            <form onSubmit={submit} className="space-y-5">
                {/* =====================================================
                    1. ACCOUNT
                ====================================================== */}
                <Card>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                {isManagerForm ? "Manager account" : "Account type"}
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                {isManagerForm
                                    ? "Login details for the new Manager."
                                    : "Choose what kind of account you are creating."}
                            </p>
                        </div>

                        <Link
                            href={backHref}
                            className="text-sm font-semibold text-slate-500 hover:text-slate-900"
                        >
                            {isManagerForm ? "Back to Managers" : "Back to Users"}
                        </Link>
                    </div>

                    {!isManagerForm && (
                        <div className="mt-4 flex flex-wrap gap-3">
                            {[
                                ["label", "Label", "Owns one label and receives its earnings"],
                                ["artist", "Artist", "Individual artist account with own earnings"],
                            ].map(([value, title, help]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => applyRoleDefaults(value)}
                                    className={[
                                        "min-w-[220px] flex-1 rounded-xl border px-4 py-3 text-left transition sm:flex-none",
                                        data.role === value
                                            ? "border-violet-500 bg-violet-50 ring-2 ring-violet-100"
                                            : "border-slate-300 bg-white hover:bg-slate-50",
                                    ].join(" ")}
                                >
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={[
                                                "flex h-4 w-4 items-center justify-center rounded-full border",
                                                data.role === value
                                                    ? "border-violet-600"
                                                    : "border-slate-400",
                                            ].join(" ")}
                                        >
                                            {data.role === value && (
                                                <span className="h-2 w-2 rounded-full bg-violet-600" />
                                            )}
                                        </span>
                                        <span className="text-sm font-semibold text-slate-900">
                                            {title}
                                        </span>
                                    </div>
                                    <div className="mt-1 pl-6 text-xs text-slate-500">
                                        {help}
                                    </div>
                                </button>
                            ))}
                        </div>
                    )}

                    {errors.role && (
                        <div className="mt-2 text-xs text-red-600">{errors.role}</div>
                    )}

                    <SubHeading>Account</SubHeading>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            label={nameLabel}
                            value={data.name}
                            error={errors.name}
                            required
                            onChange={(value) => setData("name", value)}
                        />

                        <div>
                            <Field
                                label="Username"
                                required={req("username")}
                                value={data.username}
                                error={errors.username}
                                onChange={(value) =>
                                    setData(
                                        "username",
                                        value
                                            .toLowerCase()
                                            .replace(/[^a-z0-9]/g, ""),
                                    )
                                }
                            />

                            <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <button
                                    type="button"
                                    onClick={generateUsername}
                                    className="text-xs font-semibold text-violet-600 hover:text-violet-800"
                                >
                                    Generate username
                                </button>

                                {!req("username") && (
                                    <span className="text-xs text-slate-400">
                                        Generated automatically if blank.
                                    </span>
                                )}
                            </div>
                        </div>

                        <Field
                            label={isManagerForm ? "Email" : "Owner e-mail"}
                            type="email"
                            value={data.email}
                            error={errors.email}
                            required
                            onChange={(value) => setData("email", value)}
                        />

                        <Field
                            label="Phone"
                            required={req("phone")}
                            value={data.phone}
                            error={errors.phone}
                            onChange={(value) => setData("phone", value)}
                        />
                    </div>
                </Card>

                {/* =====================================================
                    2. LABEL (label accounts only)
                ====================================================== */}
                {data.role === "label" && (
                    <Card>
                        <h2 className="text-lg font-semibold text-slate-900">
                            Label
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Link one existing Label <span className="font-semibold">or</span> type the name of a new Label.
                        </p>

                        <div className="mt-4 grid gap-4 xl:grid-cols-2">
                            <SelectionBox
                                title="Existing Label"
                                items={labels}
                                selected={data.label_ids}
                                nameKey="name"
                                onToggle={(id) =>
                                    toggleSelection("label_ids", id)
                                }
                                onClear={() => setData("label_ids", [])}
                            />

                            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <h2 className="text-sm font-semibold text-slate-900">
                                    New Label
                                </h2>

                                <p className="mt-0.5 text-xs text-slate-500">
                                    Only when the Label does not exist yet.
                                </p>

                                <div className="mt-3 flex items-start gap-3">
                                    <div className="flex-1">
                                        <input
                                            type="text"
                                            value={(data.new_labels ?? [""])[0] ?? ""}
                                            placeholder="New label name"
                                            onChange={(event) =>
                                                updateNewLabel(0, event.target.value)
                                            }
                                            className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                        />

                                        {errors["new_labels.0"] && (
                                            <p className="mt-1 text-xs font-medium text-red-600">
                                                {errors["new_labels.0"]}
                                            </p>
                                        )}
                                    </div>

                                    {((data.new_labels ?? [""])[0] ?? "") !== "" && (
                                        <button
                                            type="button"
                                            onClick={clearNewLabel}
                                            className="rounded-lg border border-red-200 px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50"
                                            title="Clear new label"
                                        >
                                            ×
                                        </button>
                                    )}
                                </div>
                            </section>
                        </div>

                        {errors.label_ids && (
                            <p className="mt-3 text-sm font-medium text-red-600">
                                {errors.label_ids}
                            </p>
                        )}

                        {errors.new_labels && (
                            <p className="mt-3 text-sm font-medium text-red-600">
                                {errors.new_labels}
                            </p>
                        )}
                    </Card>
                )}

                {/* =====================================================
                    3. DETAILS (label / artist)
                ====================================================== */}
                {isPayee && (
                    <Card>
                        <h2 className="text-lg font-semibold text-slate-900">
                            Details
                        </h2>

                        <SubHeading>Address</SubHeading>

                        <div className="grid gap-4 md:grid-cols-3">
                            <label>
                                <FieldLabel
                                    label="State"
                                    required={req("state")}
                                />

                                {data.country_code === "IN" ? (
                                    <select
                                        value={data.state_code}
                                        onChange={(event) => {
                                            const code = event.target.value;

                                            setData((current) => ({
                                                ...current,
                                                state_code: code,
                                                state:
                                                    indiaStateNameByCode[code] ??
                                                    "",
                                            }));
                                        }}
                                        className={inputClass(errors.state || errors.state_code)}
                                        required={req("state")}
                                    >
                                        <option value="">Select state...</option>

                                        {indiaStates.map(([code, name]) => (
                                            <option key={code} value={code}>
                                                {name}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <input
                                        type="text"
                                        value={data.state}
                                        onChange={(event) =>
                                            setData(
                                                "state",
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass(errors.state || errors.state_code)}
                                        required={req("state")}
                                    />
                                )}

                                {(errors.state || errors.state_code) && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.state || errors.state_code}
                                    </p>
                                )}
                            </label>

                            <Field
                                label="City"
                                value={data.city}
                                error={errors.city}
                                required={req("city")}
                                onChange={(value) => setData("city", value)}
                            />

                            <Field
                                label="Pincode"
                                value={data.postal_code}
                                error={errors.postal_code}
                                required={req("postal_code")}
                                onChange={(value) =>
                                    setData("postal_code", value)
                                }
                            />

                            <Field
                                label="Address line 1"
                                value={data.address_line_1}
                                error={errors.address_line_1}
                                required={req("address_line_1")}
                                onChange={(value) =>
                                    setData("address_line_1", value)
                                }
                            />

                            <Field
                                label="Address line 2"
                                required={req("address_line_2")}
                                value={data.address_line_2}
                                error={errors.address_line_2}
                                onChange={(value) =>
                                    setData("address_line_2", value)
                                }
                            />

                            <label>
                                <FieldLabel label="Country" required />

                                <select
                                    value={data.country_code}
                                    onChange={(event) => {
                                        const code = event.target.value;

                                        setData((current) => ({
                                            ...current,
                                            country:
                                                code === "IN"
                                                    ? "India"
                                                    : "",
                                            country_code: code,
                                            state: "",
                                            state_code: "",
                                        }));
                                    }}
                                    className={inputClass(errors.country || errors.country_code)}
                                    required
                                >
                                    <option value="">
                                        Select country
                                    </option>

                                    <option value="IN">
                                        India
                                    </option>
                                </select>

                                {(errors.country || errors.country_code) && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.country || errors.country_code}
                                    </p>
                                )}
                            </label>
                        </div>

                        <SubHeading>Revenue</SubHeading>

                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <Field
                                    label="Revenue %"
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
                            </div>

                            <p className="self-end pb-1 text-xs leading-5 text-slate-500 md:col-span-2">
                                Positive collected revenue is paid at this %
                                (at 80%, ₹100 collected = ₹80 earning). Negative
                                adjustments pass through at 100%.
                            </p>
                        </div>

                        <SubHeading>Bank details</SubHeading>

                        <div className="grid gap-4 md:grid-cols-3">
                            <Field
                                label="Account holder name"
                                value={data.account_holder_name}
                                error={errors.account_holder_name}
                                required={req("account_holder_name")}
                                onChange={(value) =>
                                    setData("account_holder_name", value)
                                }
                            />

                            <Field
                                label="Bank account number"
                                value={data.bank_account_number}
                                error={errors.bank_account_number}
                                required={req("bank_account_number")}
                                onChange={(value) =>
                                    setData("bank_account_number", value)
                                }
                            />

                            <Field
                                label="Bank name"
                                value={data.bank_name}
                                error={errors.bank_name}
                                required={req("bank_name")}
                                onChange={(value) =>
                                    setData("bank_name", value)
                                }
                            />

                            <Field
                                label="IFSC code"
                                value={data.ifsc_code}
                                error={errors.ifsc_code}
                                required={req("ifsc_code")}
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
                                label="Branch name"
                                required={req("branch_name")}
                                value={data.branch_name}
                                error={errors.branch_name}
                                onChange={(value) =>
                                    setData("branch_name", value)
                                }
                            />

                            <Field
                                label="UPI ID"
                                required={req("upi_id")}
                                value={data.upi_id}
                                error={errors.upi_id}
                                onChange={(value) =>
                                    setData("upi_id", value)
                                }
                            />
                        </div>

                        <SubHeading>Tax</SubHeading>

                        <div className="grid gap-4 md:grid-cols-3">
                            <Field
                                label="PAN"
                                value={data.pan_number}
                                error={errors.pan_number}
                                required={req("pan_number")}
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
                                label="GST"
                                required={req("gst_number")}
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
                    </Card>
                )}

                {/* =====================================================
                    MANAGER ASSIGNMENTS (manager form only)
                ====================================================== */}
                {isManagerForm && (
                    <Card>
                        <h2 className="text-lg font-semibold text-slate-900">
                            Manager assignments
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Select the Artists and Labels this Manager is
                            responsible for.
                        </p>

                        <div className="mt-4 grid gap-4 xl:grid-cols-2">
                            <SelectionBox
                                title="Assigned Artists"
                                items={artists}
                                selected={data.artist_ids}
                                nameKey="stage_name"
                                onToggle={(id) =>
                                    toggleSelection("artist_ids", id)
                                }
                                onSelectAll={() =>
                                    selectAll("artist_ids", artists)
                                }
                                onClear={() => setData("artist_ids", [])}
                            />

                            <SelectionBox
                                title="Assigned Labels"
                                items={labels}
                                selected={data.label_ids}
                                nameKey="name"
                                onToggle={(id) =>
                                    toggleSelection("label_ids", id)
                                }
                                onSelectAll={() =>
                                    selectAll("label_ids", labels)
                                }
                                onClear={() => setData("label_ids", [])}
                            />
                        </div>
                    </Card>
                )}

                {/* =====================================================
                    PANEL PERMISSIONS
                ====================================================== */}
                <Card>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Panel permissions
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Recommended defaults are already ticked for this
                                account type.
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => setAllPermissions(true)}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Select all
                            </button>

                            <button
                                type="button"
                                onClick={() => setAllPermissions(false)}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        {permissionOptions.map(([field, label]) => (
                            <label
                                key={field}
                                className={[
                                    "flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 transition",
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
                </Card>

                {/* =====================================================
                    LOGIN & INVITATION
                ====================================================== */}
                <section className="rounded-2xl border border-violet-200 bg-violet-50/40 p-5 shadow-sm">
                    <label className="flex cursor-pointer items-start gap-4">
                        <input
                            type="checkbox"
                            checked={data.send_invitation}
                            onChange={(event) =>
                                setData(
                                    "send_invitation",
                                    event.target.checked,
                                )
                            }
                            className="mt-1"
                        />

                        <div>
                            <div className="text-sm font-semibold text-slate-900">
                                Send account invitation by e-mail
                            </div>

                            <p className="mt-1 text-xs leading-5 text-slate-500">
                                The account is created as{" "}
                                <span className="font-semibold text-amber-700">Pending</span>{" "}
                                and becomes Active after the invitation is
                                accepted and a password is set (link valid for
                                seven days).
                            </p>
                        </div>
                    </label>

                    {!data.send_invitation && (
                        <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                            Invitation is disabled. The account stays Pending
                            until a valid invitation is later issued and
                            accepted.
                        </div>
                    )}
                </section>

                {Object.keys(errors).length > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
                        Some information could not be saved. Review the
                        highlighted fields before submitting again.
                    </div>
                )}

                <div className="sticky bottom-4 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                    <div className="text-xs text-slate-500">
                        <span className="text-red-500">*</span> = required.
                        New accounts are created as{" "}
                        <span className="font-semibold text-amber-700">
                            Pending
                        </span>
                        .
                    </div>

                    <div className="flex gap-3">
                        <Link
                            href={backHref}
                            className="rounded-xl border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Cancel
                        </Link>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-violet-600 px-7 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing
                                ? "Creating..."
                                : isManagerForm
                                  ? "Create Manager"
                                  : data.role === "artist"
                                    ? "Create Artist"
                                    : "Create Label User"}
                        </button>
                    </div>
                </div>
            </form>
        </PanelLayout>
    );
}

function Card({ children }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            {children}
        </section>
    );
}

function SubHeading({ children }) {
    return (
        <div className="mb-2 mt-5 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
            {children}
        </div>
    );
}

function inputClass(error) {
    return [
        "mt-1.5 w-full rounded-xl border bg-white px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100",
        error ? "border-red-400" : "border-slate-300",
    ].join(" ");
}

function FieldLabel({ label, required = false }) {
    return (
        <span className="text-sm font-semibold text-slate-700">
            {label}
            {required ? (
                <span className="ml-1 text-red-500">*</span>
            ) : (
                <span className="ml-1 text-xs font-normal text-slate-400">
                    (optional)
                </span>
            )}
        </span>
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
            <FieldLabel label={label} required={required} />

            <input
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={inputClass(error)}
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

function fieldRequired(requiredFields, role, field) {
    return Boolean(requiredFields?.[role]?.[field]);
}
