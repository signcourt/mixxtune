/* MIXXTUNE_FF429_FINAL_UI_V1 */
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";

import { useEffect, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import AgreementPanel from "@/V2/Shared/Components/AgreementPanel";


export default function Edit({
    ownedLabels = [],
    role = "super_admin",
    managedUser,
    payoutProfile = null,
    permissions = {},
    artists = [],
    labels = [],
    assignedArtistIds = [],
    assignedLabelIds = [],
    kycLock = null,
    panelTabs = null,
    agreement = null,
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

    const { data, setData, patch, processing, errors, transform } = useForm({
        name: managedUser?.name ?? "",

        /* W79: the person the account is created for */
        full_name: managedUser?.full_name ?? "",

        email: managedUser?.email ?? "",

        phone: managedUser?.phone ?? "",

        country: managedUser?.country ?? "",

        role: managedUser?.role ?? "artist",

        account_status: managedUser?.account_status ?? "pending",

        revenue_share_percentage:
            managedUser?.revenue_share_percentage ?? "",

        label_revenue_rates: Object.fromEntries(
            (ownedLabels ?? []).map((label) => [
                String(label.id),
                label.revenue_share_percentage ?? "",
            ]),
        ),

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

        label_ids:
            managedUser?.role === "label"
                ? Array.from(
                      new Set([
                          ...assignedLabelIds.map(Number),
                          ...(ownedLabels ?? []).map((label) =>
                              Number(label.id),
                          ),
                      ]),
                  )
                : assignedLabelIds.map(Number),

        /* W79: new label rows appear only after "+ Create new label" */
        new_labels: [],
        new_label_rates: [],

        /* W81: allow / block single sidebar tabs (sent only after a change) */
        tab_overrides: Object.fromEntries((panelTabs ?? []).map((tab) => [tab.key, tab.override])),
        tab_overrides_touched: false,
    });

    const setTabChoice = (key, choice) => {
        setData((d) => ({ ...d, tab_overrides: { ...d.tab_overrides, [key]: choice }, tab_overrides_touched: true }));
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

    const [showExistingPicker, setShowExistingPicker] = useState(false);

    const addNewLabel = () => {
        setData((current) => ({
            ...current,
            new_labels: [...(current.new_labels ?? []), ""],
            new_label_rates: [...(current.new_label_rates ?? []), ""],
        }));
    };

    const updateNewLabel = (index, value) => {
        const next = [...(data.new_labels ?? [])];
        next[index] = value;
        setData("new_labels", next);
    };

    const updateNewLabelRate = (index, value) => {
        const next = [...(data.new_label_rates ?? [])];
        next[index] = value;
        setData("new_label_rates", next);
    };

    const removeNewLabel = (index) => {
        setData((current) => ({
            ...current,
            new_labels: (current.new_labels ?? []).filter((_, i) => i !== index),
            new_label_rates: (current.new_label_rates ?? []).filter((_, i) => i !== index),
        }));
    };

    const [labelError, setLabelError] = useState("");

    /*
     * MIXXTUNE_QUICK_EDIT_FIELD_TOGGLE_V1
     *
     * Each account controls which Full Edit fields are exposed
     * in the compact Quick Edit modal.
     */
    // MIXXTUNE_QUICK_EDIT_NULL_EMPTY_SEMANTICS_V1
    // null = legacy/default Quick Edit configuration
    // []   = explicitly no Quick Edit fields
    const legacyQuickEditFields = [
        "name",
        "email",
        "account_status",
        "label_revenue_rates",
    ];

    const [quickEditFields, setQuickEditFields] = useState(
        Array.isArray(managedUser?.quick_edit_fields)
            ? managedUser.quick_edit_fields
            : legacyQuickEditFields,
    );

    const quickEditFieldEnabled = (field) =>
        quickEditFields.includes(field);

    const toggleQuickEditField = (field) => {
        const next = quickEditFieldEnabled(field)
            ? quickEditFields.filter((item) => item !== field)
            : [...quickEditFields, field];

        setQuickEditFields(next);

        router.patch(
            `/admin/users/${managedUser.id}/quick-edit-fields`,
            {
                fields: next,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const quickEditFieldMap = {
        "Account Name": "name",
        "Full Name (account holder)": "full_name",
        "Email": "email",
        "Phone": "phone",
        "Country": "country",
        "Role": "role",
        "Assigned Revenue Rate (%)": "revenue_share_percentage",
        "Account Status": "account_status",
        "KYC Status": "kyc_status",

        "Address Line 1": "address_line_1",
        "Address Line 2": "address_line_2",
        "City": "city",
        "State": "state",
        "Postal / PIN Code": "postal_code",
        "Country Code": "country_code",

        "PAN Number": "pan_number",
        "GST Number": "gst_number",

        "Account Holder Name": "account_holder_name",
        "Bank Account Number": "bank_account_number",
        "Bank Name": "bank_name",
        "IFSC Code": "ifsc_code",
        "Branch Name": "branch_name",
    };

    // MIXXTUNE_FF429_D3R_SWITCH_V1 (W79: small switch; the legend at the top explains it)
    const QuickEditToggle = ({ field }) => {
        const enabled = quickEditFieldEnabled(field);

        return (
            <button
                type="button"
                onClick={() => toggleQuickEditField(field)}
                aria-pressed={enabled}
                title={enabled ? "Shown in Quick Edit - click to hide" : "Hidden in Quick Edit - click to show"}
                className={`inline-flex shrink-0 items-center gap-1 rounded-full px-1 py-0.5 text-[10px] font-semibold ${enabled ? "text-violet-700" : "text-slate-400 hover:text-violet-600"}`}
            >
                <span className={`relative inline-flex h-3.5 w-6 rounded-full transition-colors ${enabled ? "bg-violet-600" : "bg-slate-200"}`}>
                    <span className={`absolute top-[2px] h-2.5 w-2.5 rounded-full bg-white shadow-sm transition-all ${enabled ? "left-[12px]" : "left-[2px]"}`} />
                </span>
                <span className="sr-only">Quick Edit {enabled ? "on" : "off"}</span>
                QE
            </button>
        );
    };

    const submit = (event) => {
        event.preventDefault();
        setLabelError("");

        const named = (data.new_labels ?? []).map((name, i) => [String(name).trim(), (data.new_label_rates ?? [])[i]]).filter(([name]) => name !== "");
        if (data.role === "label" && named.some(([, rate]) => rate === "" || rate === undefined || Number(rate) < 0 || Number(rate) > 100)) {
            setLabelError("Every new label needs a revenue % between 0 and 100.");
            return;
        }

        transform((d) => ({
            ...d,
            new_labels: named.map(([name]) => name),
            new_label_rates: named.map(([, rate]) => rate),
        }));

        patch(`/admin/users/${managedUser.id}`, {
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
            `/admin/users/${managedUser.id}/reset-password`,
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
            `/admin/users/${managedUser.id}/invitation-link`,
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
            `/admin/users/${managedUser.id}/resend-invitation`,
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

            <form onSubmit={submit} className="mx-auto max-w-[1200px] space-y-4">

                {/* MIXXTUNE_AUTO_INLINE_QUICK_EDIT_V1 */}
                <section className="overflow-hidden rounded-2xl border border-violet-100 bg-white shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-violet-100 bg-violet-50/70 px-4 py-3 sm:px-5">
                        <div>
                            <h2 className="text-[15px] font-bold text-slate-900">
                                Account Details
                            </h2>

                            <div className="mt-0.5 text-xs text-slate-500">
                                Last login: {managedUser.last_login_at ?? "Never"} · <span className="text-violet-700">QE</span> switch = field shown in the Quick Edit window
                            </div>
                        </div>

                        <Link
                            href="/admin/users"
                            className="rounded-xl border border-slate-200 bg-white shadow-sm transition focus:border-violet-400 focus:ring-violet-200 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            Back to Users
                        </Link>
                    </div>

                    <div className="grid gap-3 p-4 sm:p-5 md:grid-cols-2 xl:grid-cols-3">
                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="Account Name"
                            value={data.name}
                            error={errors.name}
                            required
                            onChange={(value) => setData("name", value)}
                        />

                        {["label", "artist"].includes(data.role) && (
                            <Field
                                quickEditFieldMap={quickEditFieldMap}
                                QuickEditToggle={QuickEditToggle}
                                label="Full Name (account holder)"
                                value={data.full_name}
                                error={errors.full_name}
                                onChange={(value) => setData("full_name", value)}
                            />
                        )}

                        <div>
                            <div className="flex min-h-[24px] items-center justify-between gap-3">
                                <span className="text-xs font-semibold text-slate-600">
                                    Username
                                </span>

                                <QuickEditToggle field="username" />
                            </div>

                            <div className="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                {managedUser?.username || "—"}
                            </div>

                            <div className="mt-1 text-xs text-slate-500">
                                Permanent username — cannot be changed.
                            </div>
                        </div>

                        <div>
                            <div className="flex min-h-[24px] items-center justify-between gap-3">
                                <span className="text-xs font-semibold text-slate-600">
                                    Client ID
                                </span>

                                <QuickEditToggle field="client_id" />
                            </div>

                            <div className="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm font-medium text-slate-700">
                                {managedUser?.client_id || "—"}
                            </div>

                            <div className="mt-1 text-xs text-slate-500">
                                Permanent Mixx Tune account identifier.
                            </div>
                        </div>

                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="Email"
                            type="email"
                            value={data.email}
                            error={errors.email}
                            required
                            onChange={(value) => setData("email", value)}
                        />

                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="Phone"
                            value={data.phone}
                            error={errors.phone}
                            onChange={(value) => setData("phone", value)}
                        />

                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="Country"
                            value={data.country}
                            error={errors.country}
                            onChange={(value) => setData("country", value)}
                        />

                        <SelectField
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="Role"
                            value={data.role}
                            error={errors.role}
                            onChange={(value) => setData("role", value)}
                            options={[
                                ["super_admin", "Super Admin"],
                                ["admin", "Manager"],
                                ["label", "Label"],
                                ["artist", "Artist"],
                            ]}
                        />

                        {data.role === "artist" && (
                            <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
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

                        <SelectField
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
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
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
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

                {data.role === "label" && (
                    <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-[15px] font-bold text-slate-900">Labels for this account</h2>
                                <p className="mt-0.5 text-xs text-slate-500">Each label has its own revenue %. Blank means Rate Pending.</p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <button type="button" onClick={() => setShowExistingPicker((open) => !open)} className="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100">
                                    {showExistingPicker ? "Close label picker" : "+ Add existing label"}
                                </button>
                                <button type="button" onClick={addNewLabel} className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    + Create new label
                                </button>
                            </div>
                        </div>

                            <div className="md:col-span-2">
                                <div className="mb-1 mt-3 flex items-center justify-between gap-3">
                                    <div className="text-xs font-semibold text-slate-600">Current labels and their %</div>
                                    <QuickEditToggle field="label_revenue_rates" />
                                </div>
                                {(ownedLabels ?? []).length === 0 && <div className="rounded-lg border border-dashed border-slate-300 px-3 py-3 text-xs text-slate-500">No label owned yet.</div>}
                                <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                    {(ownedLabels ?? []).map((label) => (
                                        <div
                                            key={label.id}
                                            className="rounded-lg border border-slate-200 bg-slate-50/70 p-2.5"
                                        >
                                            <div className="text-[13px] font-bold text-slate-900">
                                                {label.name}
                                            </div>

                                            <div className="text-[11px] text-slate-500">
                                                Label ID: {label.id}
                                            </div>

                                            <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                                                label="Revenue Rate (%)"
                                                type="number"
                                                value={
                                                    data.label_revenue_rates?.[
                                                        String(label.id)
                                                    ] ?? ""
                                                }
                                                error={
                                                    errors[
                                                        `label_revenue_rates.${label.id}`
                                                    ]
                                                }
                                                onChange={(value) =>
                                                    setData(
                                                        "label_revenue_rates",
                                                        {
                                                            ...data.label_revenue_rates,
                                                            [String(label.id)]:
                                                                value,
                                                        },
                                                    )
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>
                        

                        {showExistingPicker && (
                            <div className="mt-3">
                                <SelectionBox
                                    title="Labels linked to this login"
                                    quickEditField="label_ids"
                                    QuickEditToggle={QuickEditToggle}
                                    items={labels}
                                    selected={data.label_ids}
                                    nameKey="name"
                                    onToggle={(id) => toggleSelection("label_ids", id)}
                                    onSelectAll={() => setData("label_ids", labels.map((item) => Number(item.id)))}
                                    onClear={() => setData("label_ids", [])}
                                />
                                <p className="mt-1 text-[11px] text-slate-400">A label you add here gets its % after saving (then set it above).</p>
                            </div>
                        )}

                        {errors.label_ids && <p className="mt-2 text-xs font-medium text-red-600">{errors.label_ids}</p>}

                        {(data.new_labels ?? []).length > 0 && (
                            <div className="mt-3 space-y-2">
                                {(data.new_labels ?? []).map((labelName, index) => (
                                    <div key={index} className="grid items-end gap-2 rounded-lg border border-slate-200 bg-slate-50/60 p-2.5 sm:grid-cols-[1fr_140px_auto]">
                                        <label>
                                            <span className="text-xs font-semibold text-slate-600">New label name {index + 1}</span>
                                            <input
                                                type="text"
                                                value={labelName}
                                                placeholder="e.g. Sanatan Records"
                                                onChange={(event) => updateNewLabel(index, event.target.value)}
                                                className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                            />
                                            {errors[`new_labels.${index}`] && <p className="mt-1 text-xs font-medium text-red-600">{errors[`new_labels.${index}`]}</p>}
                                        </label>
                                        <label>
                                            <span className="text-xs font-semibold text-slate-600">Revenue %</span>
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                value={(data.new_label_rates ?? [])[index] ?? ""}
                                                onChange={(event) => updateNewLabelRate(index, event.target.value)}
                                                className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"
                                            />
                                        </label>
                                        <button type="button" onClick={() => removeNewLabel(index)} className="h-[38px] rounded-lg border border-red-200 bg-white px-3 text-xs font-semibold text-red-600 hover:bg-red-50">
                                            Remove
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        {(labelError || errors.new_labels) && <p className="mt-2 text-xs font-medium text-red-600">{labelError || errors.new_labels}</p>}
                    </section>
                )}

                                {/* ================================================
                    ADDRESS DETAILS
                ================================================= */}
                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div>
                        <h2 className="text-[15px] font-bold text-slate-900">
                            Address Details
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Legal address attached to this account.
                        </p>
                    </div>

                    <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="Address Line 1"
                            value={data.address_line_1}
                            error={errors.address_line_1}
                            onChange={(value) =>
                                setData("address_line_1", value)
                            }
                        />

                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="Address Line 2"
                            value={data.address_line_2}
                            error={errors.address_line_2}
                            onChange={(value) =>
                                setData("address_line_2", value)
                            }
                        />

                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="City"
                            value={data.city}
                            error={errors.city}
                            onChange={(value) =>
                                setData("city", value)
                            }
                        />

                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="State"
                            value={data.state}
                            error={errors.state}
                            onChange={(value) =>
                                setData("state", value)
                            }
                        />

                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="Postal / PIN Code"
                            value={data.postal_code}
                            error={errors.postal_code}
                            onChange={(value) =>
                                setData("postal_code", value)
                            }
                        />

                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
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
                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div>
                        <h2 className="text-[15px] font-bold text-slate-900">
                            Tax & KYC Details
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Tax identity and verification information.
                        </p>
                    </div>

                    <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
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
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
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

                    {kycLock && <KycLockPanel userId={managedUser.id} lock={kycLock} />}

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
                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div>
                        <h2 className="text-[15px] font-bold text-slate-900">
                            Bank & Payout Details
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Royalty payment and settlement details.
                        </p>
                    </div>

                    <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
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
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
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
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="Bank Name"
                            value={data.bank_name}
                            error={errors.bank_name}
                            onChange={(value) =>
                                setData("bank_name", value)
                            }
                        />

                        <Field
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
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
                            quickEditFieldMap={quickEditFieldMap}
                            QuickEditToggle={QuickEditToggle}
                            label="Branch Name"
                            value={data.branch_name}
                            error={errors.branch_name}
                            onChange={(value) =>
                                setData("branch_name", value)
                            }
                        />
                    </div>
                </section>

<section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h2 className="text-[15px] font-bold text-slate-900">
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

                        {managedUser.invitation_status !== "accepted" && (
                            <>
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
                                    className="rounded-xl border border-slate-200 bg-white shadow-sm transition focus:border-violet-400 focus:ring-violet-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700"
                                >
                                    Get Invitation Link
                                </button>
                            </>
                        )}
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
                            quickEditField="artist_ids"
                            QuickEditToggle={QuickEditToggle}
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
                            quickEditField="label_ids"
                            QuickEditToggle={QuickEditToggle}
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

                <PanelAccessCard
                    tabs={panelTabs}
                    choices={data.tab_overrides}
                    onChoose={setTabChoice}
                    accountRole={managedUser.role}
                    formRole={data.role}
                />

                {Object.keys(errors).filter((key) => !["action_otp", "bank_change_otp"].includes(key)).length > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        Some information could not be saved. Please review the
                        highlighted fields and try again.
                    </div>
                )}

                <div className="sticky bottom-4 z-20 flex justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                    <Link
                        href="/admin/users"
                        className="rounded-xl border border-slate-200 bg-white shadow-sm transition focus:border-violet-400 focus:ring-violet-200 px-6 py-3 text-sm font-semibold text-slate-700"
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

            {/* W94 MIXXTUNE_ACCOUNT_AGREEMENTS */}
            <AgreementPanel userId={managedUser.id} agreement={agreement} />
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
    quickEditFieldMap = {},
    QuickEditToggle = null,
}) {
    return (
        <label>
            <span className="flex min-h-[24px] items-center justify-between gap-3">
                <span className="text-xs font-semibold text-slate-600">
                    {label}
                    {required && <span className="ml-1 text-red-500">*</span>}
                </span>

                {QuickEditToggle && quickEditFieldMap[label] && (
                    <span className="shrink-0">
                        <QuickEditToggle field={quickEditFieldMap[label]} />
                    </span>
                )}
            </span>

            <input
                type={type}
                value={value ?? ""}
                onChange={(event) => onChange(event.target.value)}
                className={[
                    "mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100",
                    error ? "border-red-400" : "border-slate-300",
                ].join(" ")}
            />

            {error && <div className="mt-1 text-xs text-red-600">{error}</div>}
        </label>
    );
}

function SelectField({
    label,
    value,
    onChange,
    options,
    error,
    quickEditFieldMap = {},
    QuickEditToggle = null,
}) {
    return (
        <label>
            <span className="flex min-h-[24px] items-center justify-between gap-3">
                <span className="text-xs font-semibold text-slate-600">
                    {label}
                </span>

                {QuickEditToggle && quickEditFieldMap[label] && (
                    <span className="shrink-0">
                        <QuickEditToggle field={quickEditFieldMap[label]} />
                    </span>
                )}
            </span>

            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={[
                    "mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100",
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
        <div className="rounded-lg bg-slate-50 px-3 py-2">
            <div className="text-[11px] font-semibold uppercase text-slate-500">
                {label}
            </div>

            <div className="mt-1 break-all text-sm font-semibold capitalize text-slate-900">
                {String(value ?? "").replaceAll("_", " ")}
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
    quickEditField = null,
    QuickEditToggle = null,
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
                    <div className="flex items-center gap-3">
                        <h2 className="text-sm font-bold text-slate-900">
                            {title}
                        </h2>

                        {quickEditField && QuickEditToggle && (
                            <QuickEditToggle field={quickEditField} />
                        )}
                    </div>

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
                <div className="flex min-h-[44px] flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white shadow-sm transition focus:border-violet-400 focus:ring-violet-200 bg-white px-2.5 py-2 focus-within:border-violet-500 focus-within:ring-2 focus-within:ring-violet-100">
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
                                        <div className="truncate text-sm font-bold text-slate-900">
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


/* W80 MIXXTUNE_KYC_EDIT_LOCK: can the owner change KYC / bank now; Super Admin opens it with the signed form */
const KYC_STATE_TEXT = {
    first_time: ["Owner can fill KYC & bank (first time)", "bg-sky-50 text-sky-800 ring-sky-200"],
    sent_back: ["Sent back - owner can correct it", "bg-amber-50 text-amber-800 ring-amber-200"],
    waiting: ["Locked - waiting for verification", "bg-slate-100 text-slate-700 ring-slate-200"],
    verified: ["Locked - verified", "bg-emerald-50 text-emerald-800 ring-emerald-200"],
    unlocked: ["Open for one change by the owner", "bg-violet-50 text-violet-800 ring-violet-200"],
};

function KycLockPanel({ userId, lock }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: "", document: null });
    const [label, tone] = KYC_STATE_TEXT[lock.state] ?? [lock.state, "bg-slate-100 text-slate-700 ring-slate-200"];
    const active = (lock.history ?? []).find((item) => item.active);

    const send = (event) => {
        event.preventDefault();
        form.post(`/admin/users/${userId}/kyc-unlock`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    const lockAgain = () => {
        if (!window.confirm("Lock KYC again? The owner will not be able to change it.")) return;
        router.post(`/admin/users/${userId}/kyc-unlock/revoke`, {}, { preserveScroll: true });
    };

    return (
        <div className="mt-3 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2 text-xs">
                    <span className="font-semibold text-slate-600">Owner access:</span>
                    <span className={`rounded-full px-2.5 py-0.5 font-bold ring-1 ring-inset ${tone}`}>{label}</span>
                    {active && <span className="text-slate-500">until {active.expires}</span>}
                </div>
                <div className="flex gap-2">
                    {active ? (
                        <button type="button" onClick={lockAgain} className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Lock again
                        </button>
                    ) : (
                        ["waiting", "verified"].includes(lock.state) && (
                            <button type="button" onClick={() => setOpen((v) => !v)} className="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100">
                                {open ? "Cancel" : "Allow owner to change KYC / bank"}
                            </button>
                        )
                    )}
                </div>
            </div>

            {open && (
                <form onSubmit={send} className="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    <label>
                        <span className="text-xs font-semibold text-slate-600">Reason (support ticket no. etc.) *</span>
                        <input type="text" value={form.data.reason} onChange={(e) => form.setData("reason", e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="e.g. TKT-123: new bank account" />
                        {form.errors.reason && <span className="mt-1 block text-xs text-red-600">{form.errors.reason}</span>}
                    </label>
                    <label>
                        <span className="text-xs font-semibold text-slate-600">Signed change form (PDF / photo) *</span>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => form.setData("document", e.target.files?.[0] ?? null)} className="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs" />
                        {form.errors.document && <span className="mt-1 block text-xs text-red-600">{form.errors.document}</span>}
                    </label>
                    <button type="submit" disabled={form.processing} className="h-[38px] rounded-lg bg-violet-600 px-4 text-xs font-semibold text-white hover:bg-violet-700 disabled:opacity-50">
                        {form.processing ? "Saving..." : `Open for ${lock.unlock_days} days`}
                    </button>
                    {form.errors.kyc_unlock && <span className="text-xs text-red-600 sm:col-span-3">{form.errors.kyc_unlock}</span>}
                    <p className="text-[11px] text-slate-500 sm:col-span-3">
                        The owner can then change KYC / bank once and send it again for verification. A bank change still needs the code e-mailed to the owner and puts payouts on hold for 48 hours.
                    </p>
                </form>
            )}

            {(lock.history ?? []).length > 0 && (
                <details className="mt-2 text-xs">
                    <summary className="cursor-pointer font-semibold text-slate-600">History of openings ({lock.history.length})</summary>
                    <ul className="mt-2 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
                        {lock.history.map((item) => (
                            <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                                <span className="min-w-0">
                                    <span className="font-semibold text-slate-800">{item.at}</span>
                                    <span className="text-slate-500"> · by {item.by ?? "—"} · {item.reason}</span>
                                    <span className="block text-[11px] text-slate-400">
                                        {item.active ? `open until ${item.expires}` : item.used ? `used ${item.used}` : item.revoked ? `locked again ${item.revoked}` : `ended ${item.expires}`}
                                    </span>
                                </span>
                                <a href={`/admin/kyc-unlocks/${item.id}/document`} className="shrink-0 font-semibold text-violet-700 hover:text-violet-900">
                                    Signed form ↓
                                </a>
                            </li>
                        ))}
                    </ul>
                </details>
            )}
        </div>
    );
}

/* W81 MIXXTUNE_PANEL_ACCESS: per-account allow / block of sidebar tabs above the role default */
const ROLE_TITLES = { label: "Label", artist: "Artist", admin: "Manager" };

function PanelAccessCard({ tabs, choices = {}, onChoose, accountRole, formRole }) {
    const header = (
        <div className="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 className="text-[15px] font-bold text-slate-900">Panel access</h2>
                <p className="text-xs text-slate-500">
                    Sidebar tabs this account can open. <b>Default</b> follows the {ROLE_TITLES[accountRole] ?? "role"} setting;
                    Allow / Block is only for this account.
                </p>
            </div>
            <Link href="/admin/panel-access" className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:border-violet-300">
                Role defaults →
            </Link>
        </div>
    );

    if (!tabs) {
        return (
            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                {header}
                <p className="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    {accountRole === "super_admin" ? "A Super Admin always sees every tab." : "This account type has no panel tab settings."}
                </p>
            </section>
        );
    }

    return (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            {header}
            {formRole && formRole !== accountRole && (
                <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    The role is being changed. Save first - then the tabs of the new role show here.
                </p>
            )}
            <div className="mt-3 grid grid-cols-1 gap-1.5 lg:grid-cols-2">
                {tabs.map((tab) => {
                    const choice = choices[tab.key] ?? "default";
                    const on = choice === "default" ? tab.role_default || tab.old_extra : choice === "allow";
                    const options = [
                        ["default", `Default · ${tab.role_default || tab.old_extra ? "On" : "Off"}`],
                        ["allow", "Allow"],
                        ["block", "Block"],
                    ];
                    return (
                        <div key={tab.key} className="flex min-w-0 flex-wrap items-center justify-between gap-x-2 gap-y-1 rounded-lg border border-slate-200 px-2.5 py-1.5">
                            <div className="min-w-0 flex-1 basis-40">
                                <div className="flex items-center gap-1.5 text-[13px] font-semibold text-slate-800">
                                    <span className={`h-2 w-2 shrink-0 rounded-full ${on ? "bg-emerald-500" : "bg-slate-300"}`} />
                                    <span className="truncate">{tab.name}</span>
                                    {tab.old_extra && choice === "default" && (
                                        <span className="rounded bg-sky-50 px-1 text-[10px] font-bold text-sky-700" title="Given with the old permission boxes">old extra</span>
                                    )}
                                </div>
                                <div className="truncate text-[11px] text-slate-400">{tab.help}</div>
                            </div>
                            <div className="flex shrink-0 overflow-hidden rounded-md border border-slate-200 text-[11px] font-semibold">
                                {options.map(([value, text]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        aria-pressed={choice === value}
                                        onClick={() => onChoose(tab.key, value)}
                                        className={`px-2 py-1 ${
                                            choice === value
                                                ? value === "block"
                                                    ? "bg-rose-600 text-white"
                                                    : value === "allow"
                                                      ? "bg-emerald-600 text-white"
                                                      : "bg-violet-600 text-white"
                                                : "bg-white text-slate-600 hover:bg-slate-50"
                                        }`}
                                    >
                                        {text}
                                    </button>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
