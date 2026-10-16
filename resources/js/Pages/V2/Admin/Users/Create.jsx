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
    artists = [],
    labels = [],
}) {
    const { data, setData, post, processing, errors } = useForm({
        name: "",
        username: "",
        email: "",
        phone: "",
        country: "India",
        state_code: "",
        role: "admin",
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
        permissions: permissionDefaultsForRole("admin"),
    });

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

    const addNewLabel = () => {
        if ((data.new_labels ?? []).length >= 1) {
            return;
        }

        setData("new_labels", [""]);
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
            subtitle="Create and configure a Manager, Label or Artist account"
        >
            <Head title="Create User" />

            <form onSubmit={submit} className="space-y-5">

                {/* =====================================================
                    1. ACCOUNT & ACCESS
                ====================================================== */}
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">
                                Step 1
                            </div>

                            <h2 className="mt-1 text-lg font-semibold text-slate-900">
                                Account & Access
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Login identity and account role.
                            </p>
                        </div>

                        <Link
                            href="/v2/admin/users"
                            className="text-sm font-semibold text-slate-500 hover:text-slate-900"
                        >
                            Back to Users
                        </Link>
                    </div>

                    <div className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <Field
                            label={
                                data.role === "label"
                                    ? "Label Name"
                                    : data.role === "artist"
                                      ? "Artist Name"
                                      : "Manager Name"
                            }
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
                                            .replace(/[^a-z0-9]/g, ""),
                                    )
                                }
                            />

                            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <button
                                    type="button"
                                    onClick={generateUsername}
                                    className="text-xs font-semibold text-violet-600 hover:text-violet-800"
                                >
                                    Generate username
                                </button>

                                <span className="text-xs text-slate-400">
                                    Optional — generated automatically if blank.
                                </span>
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

                        <label>
                            <span className="text-sm font-semibold text-slate-700">
                                Role
                                <span className="ml-1 text-red-500">*</span>
                            </span>

                            <select
                                value={data.role}
                                onChange={(event) =>
                                    applyRoleDefaults(event.target.value)
                                }
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"
                            >
                                <option value="admin">Manager</option>
                                <option value="label">Label</option>
                                <option value="artist">Artist</option>
                            </select>

                            {errors.role && (
                                <div className="mt-1 text-xs text-red-600">
                                    {errors.role}
                                </div>
                            )}
                        </label>

                        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                            <div className="text-xs font-semibold uppercase tracking-wide text-amber-700">
                                Activation
                            </div>

                            <div className="mt-1 text-sm font-semibold text-amber-900">
                                Pending
                            </div>

                            <p className="mt-1 text-xs leading-5 text-amber-700">
                                Account becomes Active automatically after the
                                invitation is accepted and password is set.
                            </p>
                        </div>
                    </div>
                </section>

                {/* =====================================================
                    2. BUSINESS & REVENUE
                ====================================================== */}
                {["label", "artist"].includes(data.role) && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div>
                            <div className="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">
                                Step 2
                            </div>

                            <h2 className="mt-1 text-lg font-semibold text-slate-900">
                                Business & Revenue
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Commercial settings used for reporting and royalty
                                calculation.
                            </p>
                        </div>

                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                            <div>
                                <Field
                                    label="Revenue Share (%)"
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

                                <p className="mt-2 text-xs leading-5 text-slate-500">
                                    Positive collected revenue is paid using this
                                    percentage. Negative adjustments are passed
                                    through at 100%.
                                </p>
                            </div>

                            <div className="rounded-xl border border-violet-100 bg-violet-50/50 p-4">
                                <div className="text-sm font-semibold text-slate-900">
                                    Revenue example
                                </div>

                                <p className="mt-2 text-xs leading-5 text-slate-600">
                                    At an 80% share, ₹100 positive collected
                                    revenue produces ₹80 earning. A ₹100 negative
                                    adjustment remains −₹100.
                                </p>
                            </div>
                        </div>
                    </section>
                )}

                {/* =====================================================
                    3. ACCOUNT ASSIGNMENT
                ====================================================== */}
                {data.role === "admin" && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="mb-4">
                            <div className="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">
                                Step 2
                            </div>

                            <h2 className="mt-1 text-lg font-semibold text-slate-900">
                                Manager Assignments
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Select the Artists and Labels this Manager is
                                responsible for.
                            </p>
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
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
                    </section>
                )}

                {data.role === "label" && (
                    <section className="rounded-2xl border border-violet-200 bg-violet-50/30 p-5 shadow-sm">
                        <div className="mb-4">
                            <div className="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">
                                Step 3
                            </div>

                            <h2 className="mt-1 text-lg font-semibold text-slate-900">
                                Label Assignment
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Link one existing Label or create one new Label
                                for this account.
                            </p>
                        </div>

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

                        {errors.label_ids && (
                            <p className="mt-3 text-sm font-medium text-red-600">
                                {errors.label_ids}
                            </p>
                        )}

                        <div className="mt-4 rounded-xl border border-violet-200 bg-white p-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div className="text-sm font-semibold text-slate-900">
                                        Create New Label
                                    </div>

                                    <div className="mt-1 text-xs text-slate-500">
                                        Use this only when the Label does not
                                        already exist in the catalogue.
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

                            <div className="mt-4 space-y-2">
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
                                                    placeholder="New label name"
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
                                                className="rounded-xl border border-red-200 px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50"
                                                title="Clear new label"
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

                {/* =====================================================
                    4. REGISTERED ADDRESS
                ====================================================== */}
                {["label", "artist"].includes(data.role) && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div>
                            <div className="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">
                                {data.role === "label" ? "Step 4" : "Step 3"}
                            </div>

                            <h2 className="mt-1 text-lg font-semibold text-slate-900">
                                Registered Address
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Legal and correspondence address for this account.
                            </p>
                        </div>

                        <div className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
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

                            <label>
                                <span className="text-sm font-semibold text-slate-700">
                                    State / Region
                                    <span className="ml-1 text-red-500">*</span>
                                </span>

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
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                        required
                                    >
                                        <option value="">Select State</option>

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
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                        required
                                    />
                                )}

                                {(errors.state || errors.state_code) && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.state || errors.state_code}
                                    </p>
                                )}
                            </label>

                            <Field
                                label="Postal Code"
                                value={data.postal_code}
                                error={errors.postal_code}
                                required
                                onChange={(value) =>
                                    setData("postal_code", value)
                                }
                            />

                            <label>
                                <span className="text-sm font-semibold text-slate-700">
                                    Country
                                    <span className="ml-1 text-red-500">*</span>
                                </span>

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
                                    className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                    required
                                >
                                    <option value="">
                                        Select Country
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
                    </section>
                )}

                {/* =====================================================
                    5. TAX
                ====================================================== */}
                {["label", "artist"].includes(data.role) && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div>
                            <div className="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">
                                {data.role === "label" ? "Step 5" : "Step 4"}
                            </div>

                            <h2 className="mt-1 text-lg font-semibold text-slate-900">
                                Tax Information
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Tax identifiers used for finance and payout
                                documentation.
                            </p>
                        </div>

                        <div className="mt-5 grid gap-5 md:grid-cols-2">
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
                )}

                {/* =====================================================
                    6. PAYOUT
                ====================================================== */}
                {["label", "artist"].includes(data.role) && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div>
                            <div className="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">
                                {data.role === "label" ? "Step 6" : "Step 5"}
                            </div>

                            <h2 className="mt-1 text-lg font-semibold text-slate-900">
                                Payout Information
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Verified banking details used for royalty payouts.
                            </p>
                        </div>

                        <div className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
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
                )}

                {/* =====================================================
                    PANEL PERMISSIONS
                ====================================================== */}
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Panel Permissions
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Control which platform modules this account can
                                access. Recommended defaults are applied when the
                                role changes.
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() =>
                                    setData(
                                        "permissions",
                                        Object.fromEntries(
                                            permissionOptions.map(
                                                ([field]) => [
                                                    field,
                                                    true,
                                                ],
                                            ),
                                        ),
                                    )
                                }
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Select All
                            </button>

                            <button
                                type="button"
                                onClick={() =>
                                    setData(
                                        "permissions",
                                        Object.fromEntries(
                                            permissionOptions.map(
                                                ([field]) => [
                                                    field,
                                                    false,
                                                ],
                                            ),
                                        ),
                                    )
                                }
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
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

                {/* =====================================================
                    LOGIN & INVITATION
                ====================================================== */}
                <section className="rounded-2xl border border-violet-200 bg-violet-50/40 p-5 shadow-sm">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            Login & Invitation
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Account activation is controlled automatically by
                            invitation acceptance.
                        </p>
                    </div>

                    <label className="mt-5 flex cursor-pointer items-start gap-4 rounded-xl border border-violet-200 bg-white p-4">
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
                                Send account invitation by email
                            </div>

                            <p className="mt-1 text-xs leading-5 text-slate-500">
                                A secure password setup link will be sent to the
                                user and will remain valid for seven days.
                            </p>

                            <div className="mt-3 inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                Pending until invitation is accepted
                            </div>
                        </div>
                    </label>

                    {!data.send_invitation && (
                        <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                            Invitation is disabled. The account will be created
                            as Pending and will not become Active until a valid
                            invitation is later issued and accepted.
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
                        New accounts are created as{" "}
                        <span className="font-semibold text-amber-700">
                            Pending
                        </span>
                        .
                    </div>

                    <div className="flex gap-3">
                        <Link
                            href="/v2/admin/users"
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
                                ? "Creating User..."
                                : "Create User"}
                        </button>
                    </div>
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
