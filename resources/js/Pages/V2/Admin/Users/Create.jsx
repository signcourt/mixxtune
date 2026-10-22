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
       Managers are created from Accounts & Access → Managers.
       W79: compact form - account name + full name, username / client ID
       made automatically, labels added row by row with their own %. */
    const isManagerForm = accountType === "manager";
    const initialRole = isManagerForm ? "admin" : "label";
    const backHref = isManagerForm ? "/v2/admin/admins" : "/v2/admin/users";

    const { data, setData, post, processing, errors, transform } = useForm({
        name: "",
        full_name: "",
        username: "",
        email: "",
        phone: "",
        country: "India",
        state_code: "",
        role: initialRole,
        revenue_share_percentage: "",
        send_invitation: true,
        /* W80: the owner fills KYC & bank himself after login, we verify */
        kyc_by_owner: false,

        account_holder_name: "",
        bank_account_number: "",
        bank_name: "",
        ifsc_code: "",
        branch_name: "",

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
        new_labels: [],
        permissions: permissionDefaultsForRole(initialRole),
    });

    /* label rows: { kind: "existing" | "new", labelId, name, rate } */
    const [labelRows, setLabelRows] = useState([]);
    const [localError, setLocalError] = useState("");

    const isPayee = ["label", "artist"].includes(data.role);
    const req = (field) => fieldRequired(requiredFields, data.role, field);

    /* only master labels nobody owns yet can be linked to a new account */
    const freeLabels = (labels ?? []).filter((label) => !label.user_id && !label.parent_label_id);
    const usedIds = new Set(labelRows.filter((row) => row.kind === "existing" && row.labelId).map((row) => Number(row.labelId)));

    const addRow = (kind) => setLabelRows((rows) => [...rows, { key: `${kind}-${Date.now()}-${rows.length}`, kind, labelId: "", name: "", rate: "" }]);
    const updateRow = (key, patch) => setLabelRows((rows) => rows.map((row) => (row.key === key ? { ...row, ...patch } : row)));
    const removeRow = (key) => setLabelRows((rows) => rows.filter((row) => row.key !== key));

    const toggleSelection = (field, id) => {
        const numberId = Number(id);
        const selected = data[field];

        setData(field, selected.includes(numberId) ? selected.filter((item) => item !== numberId) : [...selected, numberId]);
    };

    const togglePermission = (field, checked) => setData("permissions", { ...data.permissions, [field]: checked });

    const applyRoleDefaults = (selectedRole) => {
        setLabelRows([]);
        setData((current) => ({
            ...current,
            role: selectedRole,
            artist_ids: selectedRole === "admin" ? current.artist_ids : [],
            label_ids: selectedRole === "admin" ? current.label_ids : [],
            new_labels: [],
            permissions: permissionDefaultsForRole(selectedRole),
        }));
    };

    const selectAll = (field, items) => setData(field, items.map((item) => Number(item.id)));

    const setAllPermissions = (value) =>
        setData("permissions", Object.fromEntries(permissionOptions.map(([field]) => [field, value])));

    const submit = (event) => {
        event.preventDefault();
        setLocalError("");

        if (data.role === "label") {
            const rows = labelRows.filter((row) => (row.kind === "existing" ? row.labelId : row.name.trim()));
            if (rows.length === 0) {
                setLocalError("Add at least one label: pick an existing label or create a new one.");
                return;
            }
            if (rows.some((row) => row.rate === "" || Number(row.rate) < 0 || Number(row.rate) > 100)) {
                setLocalError("Every label needs a revenue % between 0 and 100.");
                return;
            }
        }

        transform((d) => {
            if (d.role !== "label") {
                return { ...d, label_ids: d.role === "admin" ? d.label_ids : [], new_labels: [] };
            }
            const existing = labelRows.filter((row) => row.kind === "existing" && row.labelId);
            const created = labelRows.filter((row) => row.kind === "new" && row.name.trim());
            const first = existing[0] || created[0];

            return {
                ...d,
                revenue_share_percentage: first ? first.rate : d.revenue_share_percentage,
                label_ids: existing.map((row) => Number(row.labelId)),
                label_rates: Object.fromEntries(existing.map((row) => [String(row.labelId), row.rate])),
                new_labels: created.map((row) => row.name.trim()),
                new_label_rates: created.map((row) => row.rate),
            };
        });

        post("/v2/admin/users", { preserveScroll: true });
    };

    const pageTitle = isManagerForm ? "Create Manager" : "Create User";
    const err = (key) => errors[key];

    return (
        <PanelLayout
            role={role}
            title={pageTitle}
            subtitle={isManagerForm ? "Create a Manager account and choose the Artists and Labels they handle" : "Create a Label or Artist account"}
        >
            <Head title={pageTitle} />
            {/* MIXXTUNE_USER_REQUIRED_FIELDS_V1 */}
            {canEditRequiredFields && (
                <UserRequiredFieldsPanel requiredFields={requiredFields} options={requiredFieldOptions} currentRole={data.role} />
            )}

            <form onSubmit={submit} className="mx-auto max-w-[1200px] space-y-4">
                {/* 1. ACCOUNT */}
                <Card>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-[15px] font-bold text-slate-900">{isManagerForm ? "Manager account" : "Account"}</h2>
                        <Link href={backHref} className="text-xs font-semibold text-slate-500 hover:text-slate-900">
                            ← {isManagerForm ? "Back to Managers" : "Back to Users"}
                        </Link>
                    </div>

                    {!isManagerForm && (
                        <div className="mt-3 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1" role="radiogroup" aria-label="Account type">
                            {[
                                ["label", "Label"],
                                ["artist", "Artist"],
                            ].map(([value, title]) => (
                                <button
                                    key={value}
                                    type="button"
                                    role="radio"
                                    aria-checked={data.role === value}
                                    onClick={() => applyRoleDefaults(value)}
                                    className={`rounded-md px-4 py-1.5 text-sm font-semibold transition ${data.role === value ? "bg-white text-violet-700 shadow-sm" : "text-slate-600 hover:text-slate-900"}`}
                                >
                                    {title}
                                </button>
                            ))}
                        </div>
                    )}
                    {errors.role && <div className="mt-1 text-xs text-red-600">{errors.role}</div>}

                    <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <Field
                            label={data.role === "label" ? "Account name (label / company)" : data.role === "artist" ? "Account name (artist name)" : "Manager name"}
                            value={data.name}
                            error={err("name")}
                            required
                            hint="Shown everywhere in the panel, e.g. on the dashboard."
                            onChange={(value) => setData("name", value)}
                        />
                        {isPayee && (
                            <Field
                                label="Full name (account holder)"
                                value={data.full_name}
                                error={err("full_name")}
                                required
                                hint="The person this account is created for."
                                onChange={(value) => setData("full_name", value)}
                            />
                        )}
                        <Field label="E-mail" type="email" value={data.email} error={err("email")} required onChange={(value) => setData("email", value)} />
                        <Field label="Phone" required={req("phone")} value={data.phone} error={err("phone")} onChange={(value) => setData("phone", value)} />
                        <label>
                            <FieldLabel label="Country" required />
                            <select
                                value={data.country_code}
                                onChange={(event) => {
                                    const code = event.target.value;
                                    setData((current) => ({ ...current, country: code === "IN" ? "India" : "", country_code: code, state: "", state_code: "" }));
                                }}
                                className={inputClass(errors.country || errors.country_code)}
                            >
                                <option value="IN">India</option>
                            </select>
                        </label>
                        <div className="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-500">
                            <div><b className="text-slate-700">Username</b> and <b className="text-slate-700">Client ID</b> are created automatically when you save.</div>
                        </div>
                    </div>

                    {data.role === "artist" && (
                        <div className="mt-3 grid gap-3 md:grid-cols-3">
                            <Field
                                label="Revenue %"
                                type="number"
                                value={data.revenue_share_percentage}
                                error={err("revenue_share_percentage")}
                                required
                                hint="At 80%, ₹100 collected = ₹80 earning."
                                onChange={(value) => setData("revenue_share_percentage", value)}
                            />
                        </div>
                    )}
                </Card>

                {/* 2. LABELS (label accounts) */}
                {data.role === "label" && (
                    <Card>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-[15px] font-bold text-slate-900">Labels & revenue %</h2>
                                <p className="mt-0.5 text-xs text-slate-500">Each label keeps its own %. Add as many labels as this account owns.</p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <button type="button" onClick={() => addRow("existing")} className="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100">
                                    + Add existing label
                                </button>
                                <button type="button" onClick={() => addRow("new")} className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    + Create new label
                                </button>
                            </div>
                        </div>

                        {labelRows.length === 0 ? (
                            <div className="mt-3 rounded-lg border border-dashed border-slate-300 px-4 py-5 text-center text-xs text-slate-500">
                                No label yet. Use <b>Add existing label</b> or <b>Create new label</b>.
                            </div>
                        ) : (
                            <div className="mt-3 space-y-2">
                                {labelRows.map((row, index) => (
                                    <div key={row.key} className="grid items-end gap-2 rounded-lg border border-slate-200 bg-slate-50/60 p-2.5 sm:grid-cols-[1fr_140px_auto]">
                                        {row.kind === "existing" ? (
                                            <label>
                                                <span className="text-xs font-semibold text-slate-600">Existing label {index + 1}</span>
                                                <select
                                                    value={row.labelId}
                                                    onChange={(event) => {
                                                        const picked = freeLabels.find((label) => String(label.id) === event.target.value);
                                                        const own = picked && picked.revenue_share_percentage !== null && picked.revenue_share_percentage !== undefined && picked.revenue_share_percentage !== "";
                                                        updateRow(row.key, { labelId: event.target.value, rate: own ? String(Number(picked.revenue_share_percentage)) : "", locked: Boolean(own) });
                                                    }}
                                                    className={inputClass(false)}
                                                >
                                                    <option value="">Choose a free label…</option>
                                                    {freeLabels
                                                        .filter((label) => !usedIds.has(Number(label.id)) || Number(label.id) === Number(row.labelId))
                                                        .map((label) => (
                                                            <option key={label.id} value={label.id}>
                                                                {label.name}
                                                            </option>
                                                        ))}
                                                </select>
                                            </label>
                                        ) : (
                                            <label>
                                                <span className="text-xs font-semibold text-slate-600">New label name {index + 1}</span>
                                                <input type="text" value={row.name} onChange={(event) => updateRow(row.key, { name: event.target.value })} placeholder="e.g. Sanatan Records" className={inputClass(false)} />
                                            </label>
                                        )}
                                        <label>
                                            <span className="text-xs font-semibold text-slate-600">Revenue %</span>
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                value={row.rate}
                                                readOnly={Boolean(row.locked)}
                                                title={row.locked ? "This label already has a %. Change it on the Labels page." : undefined}
                                                onChange={(event) => updateRow(row.key, { rate: event.target.value })}
                                                className={`${inputClass(false)} ${row.locked ? "bg-slate-100 text-slate-500" : ""}`}
                                            />
                                        </label>
                                        <button type="button" onClick={() => removeRow(row.key)} className="h-[38px] rounded-lg border border-red-200 bg-white px-3 text-xs font-semibold text-red-600 hover:bg-red-50" aria-label={`Remove label ${index + 1}`}>
                                            Remove
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                        {(localError || errors.label_ids || errors.new_labels || errors["new_labels.0"] || errors.revenue_share_percentage) && (
                            <p className="mt-2 text-xs font-medium text-red-600">{localError || errors.label_ids || errors.new_labels || errors["new_labels.0"] || errors.revenue_share_percentage}</p>
                        )}
                        <p className="mt-2 text-[11px] leading-5 text-slate-400">
                            Positive collected revenue is paid at the label %; negative adjustments pass through at 100%. An existing label keeps its own % (grey box).
                            Giving an existing label to this account needs the 6-digit code e-mailed to you when you press Create.
                        </p>
                    </Card>
                )}

                {/* 3. ADDRESS + KYC + BANK (label / artist) */}
                {isPayee && (
                    <Card>
                        <h2 className="text-[15px] font-bold text-slate-900">Address</h2>
                        <div className="mt-3 grid gap-3 md:grid-cols-3">
                            <Field label="Address line 1" value={data.address_line_1} error={err("address_line_1")} required={req("address_line_1")} onChange={(value) => setData("address_line_1", value)} />
                            <Field label="Address line 2" value={data.address_line_2} error={err("address_line_2")} required={req("address_line_2")} onChange={(value) => setData("address_line_2", value)} />
                            <Field label="City" value={data.city} error={err("city")} required={req("city")} onChange={(value) => setData("city", value)} />
                            <label>
                                <FieldLabel label="State" required={req("state")} />
                                <select
                                    value={data.state_code}
                                    onChange={(event) => {
                                        const code = event.target.value;
                                        setData((current) => ({ ...current, state_code: code, state: indiaStateNameByCode[code] ?? "" }));
                                    }}
                                    className={inputClass(errors.state || errors.state_code)}
                                >
                                    <option value="">Select state…</option>
                                    {indiaStates.map(([code, name]) => (
                                        <option key={code} value={code}>
                                            {name}
                                        </option>
                                    ))}
                                </select>
                                {(errors.state || errors.state_code) && <p className="mt-1 text-xs text-red-600">{errors.state || errors.state_code}</p>}
                            </label>
                            <Field label="Pincode" value={data.postal_code} error={err("postal_code")} required={req("postal_code")} onChange={(value) => setData("postal_code", value)} />
                        </div>

                        <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <h2 className="text-[15px] font-bold text-slate-900">KYC & bank</h2>
                            <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1" role="radiogroup" aria-label="Who fills KYC and bank details">
                                {[
                                    [false, "I fill it now"],
                                    [true, "Owner fills after login"],
                                ].map(([value, title]) => (
                                    <button
                                        key={String(value)}
                                        type="button"
                                        role="radio"
                                        aria-checked={data.kyc_by_owner === value}
                                        onClick={() => setData("kyc_by_owner", value)}
                                        className={`rounded-md px-3 py-1 text-xs font-semibold transition ${data.kyc_by_owner === value ? "bg-white text-violet-700 shadow-sm" : "text-slate-600 hover:text-slate-900"}`}
                                    >
                                        {title}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {data.kyc_by_owner ? (
                            <p className="mt-3 rounded-lg border border-violet-100 bg-violet-50/60 px-3 py-2.5 text-xs leading-5 text-violet-900">
                                The owner fills PAN, GST and bank details once under <b>KYC & Payout</b> after the first login and sends them to you. You verify them in KYC Reviews; after that they are locked.
                                A later change needs a Support request and your <b>Allow change</b> with the signed form.
                            </p>
                        ) : (
                        <div className="mt-3 grid gap-5 lg:grid-cols-2">
                            <div>
                                <h2 className="text-[13px] font-bold text-slate-700">Tax & KYC</h2>
                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                    <Field label="PAN" value={data.pan_number} error={err("pan_number")} required={req("pan_number")} onChange={(value) => setData("pan_number", value.toUpperCase().replace(/\s+/g, ""))} />
                                    <Field label="GST" value={data.gst_number} error={err("gst_number")} required={req("gst_number")} onChange={(value) => setData("gst_number", value.toUpperCase().replace(/\s+/g, ""))} />
                                </div>
                            </div>
                            <div>
                                <h2 className="text-[13px] font-bold text-slate-700">Bank details</h2>
                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                    <Field label="Account holder name" value={data.account_holder_name} error={err("account_holder_name")} required={req("account_holder_name")} onChange={(value) => setData("account_holder_name", value)} />
                                    <Field label="Account number" value={data.bank_account_number} error={err("bank_account_number")} required={req("bank_account_number")} onChange={(value) => setData("bank_account_number", value)} />
                                    <Field label="Bank name" value={data.bank_name} error={err("bank_name")} required={req("bank_name")} onChange={(value) => setData("bank_name", value)} />
                                    <Field label="IFSC" value={data.ifsc_code} error={err("ifsc_code")} required={req("ifsc_code")} onChange={(value) => setData("ifsc_code", value.toUpperCase().replace(/\s+/g, ""))} />
                                    <Field label="Branch" value={data.branch_name} error={err("branch_name")} required={req("branch_name")} onChange={(value) => setData("branch_name", value)} />
                                </div>
                            </div>
                        </div>
                        )}
                    </Card>
                )}

                {/* MANAGER ASSIGNMENTS (manager form only) */}
                {isManagerForm && (
                    <Card>
                        <h2 className="text-[15px] font-bold text-slate-900">Manager assignments</h2>
                        <p className="mt-0.5 text-xs text-slate-500">Select the Artists and Labels this Manager is responsible for.</p>
                        <div className="mt-3 grid gap-3 xl:grid-cols-2">
                            <SelectionBox title="Assigned Artists" items={artists} selected={data.artist_ids} nameKey="stage_name" onToggle={(id) => toggleSelection("artist_ids", id)} onSelectAll={() => selectAll("artist_ids", artists)} onClear={() => setData("artist_ids", [])} />
                            <SelectionBox title="Assigned Labels" items={labels} selected={data.label_ids} nameKey="name" onToggle={(id) => toggleSelection("label_ids", id)} onSelectAll={() => selectAll("label_ids", labels)} onClear={() => setData("label_ids", [])} />
                        </div>
                    </Card>
                )}

                {/* PANEL PERMISSIONS */}
                <Card>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-[15px] font-bold text-slate-900">Panel permissions</h2>
                            <p className="mt-0.5 text-xs text-slate-500">Recommended defaults are ticked for this account type.</p>
                        </div>
                        <div className="flex gap-2">
                            <button type="button" onClick={() => setAllPermissions(true)} className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Select all</button>
                            <button type="button" onClick={() => setAllPermissions(false)} className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Clear</button>
                        </div>
                    </div>
                    <div className="mt-3 grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {permissionOptions.map(([field, label]) => (
                            <label key={field} className={`flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-1.5 text-[13px] ${data.permissions[field] ? "border-violet-300 bg-violet-50 text-violet-900" : "border-slate-200 text-slate-700"}`}>
                                <input type="checkbox" checked={data.permissions[field]} onChange={(event) => togglePermission(field, event.target.checked)} />
                                <span className="font-medium">{label}</span>
                            </label>
                        ))}
                    </div>
                </Card>

                {/* LOGIN & INVITATION */}
                <Card>
                    <label className="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" checked={data.send_invitation} onChange={(event) => setData("send_invitation", event.target.checked)} className="mt-1" />
                        <div>
                            <div className="text-sm font-semibold text-slate-900">Send account invitation by e-mail</div>
                            <p className="mt-0.5 text-xs leading-5 text-slate-500">
                                The account starts as <b className="text-amber-700">Pending</b> and becomes Active once the owner opens the link (valid 7 days) and sets a password.
                            </p>
                            {!data.send_invitation && (
                                <p className="mt-1 text-xs text-amber-700">No e-mail now. You can send the invitation or copy the link later from Edit User → Security.</p>
                            )}
                        </div>
                    </label>
                </Card>

                {Object.keys(errors).filter((key) => !["action_otp", "bank_change_otp"].includes(key)).length > 0 && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700">Some information could not be saved. Check the fields marked in red.</div>
                )}

                <div className="sticky bottom-3 z-20 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur">
                    <div className="text-xs text-slate-500">
                        <span className="text-red-500">*</span> required · new accounts start as <b className="text-amber-700">Pending</b>
                    </div>
                    <div className="flex gap-2">
                        <Link href={backHref} className="rounded-lg border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</Link>
                        <button type="submit" disabled={processing} className="rounded-lg bg-violet-600 px-6 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50">
                            {processing ? "Creating..." : isManagerForm ? "Create Manager" : data.role === "artist" ? "Create Artist" : "Create Label Account"}
                        </button>
                    </div>
                </div>
            </form>
        </PanelLayout>
    );
}

function Card({ children }) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
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
        "mt-1 w-full rounded-lg border bg-white px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100",
        error ? "border-red-400" : "border-slate-300",
    ].join(" ");
}

function FieldLabel({ label, required = false }) {
    return (
        <span className="text-xs font-semibold text-slate-600">
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
    hint = null,
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
            {error ? <div className="mt-1 text-xs text-red-600">{error}</div> : hint ? <div className="mt-1 text-[11px] text-slate-400">{hint}</div> : null}
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
