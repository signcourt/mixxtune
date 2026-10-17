import axios from "axios";
import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";

/* MIXXTUNE_USER_REQUIRED_FIELDS_V1 */
const ROLE_TABS = [
    ["label", "Label"],
    ["artist", "Artist"],
    ["admin", "Manager"],
];

const isRoleTab = (role) => ROLE_TABS.some(([key]) => key === role);

export default function UserRequiredFieldsPanel({
    requiredFields = {},
    options = {},
    currentRole = "label",
}) {
    const [open, setOpen] = useState(false);
    const [tab, setTab] = useState(isRoleTab(currentRole) ? currentRole : "label");
    const [draft, setDraft] = useState(requiredFields);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState("");
    const [error, setError] = useState("");

    useEffect(() => {
        setDraft(requiredFields);
    }, [requiredFields]);

    useEffect(() => {
        if (isRoleTab(currentRole)) {
            setTab(currentRole);
        }
    }, [currentRole]);

    const fields = options?.fields?.[tab] ?? [];
    const locked = options?.locked?.[tab] ?? [];
    const dirty = JSON.stringify(draft) !== JSON.stringify(requiredFields);

    const toggle = (key) => {
        setMessage("");
        setDraft((previous) => ({
            ...previous,
            [tab]: {
                ...(previous?.[tab] ?? {}),
                [key]: !previous?.[tab]?.[key],
            },
        }));
    };

    const save = async () => {
        setSaving(true);
        setError("");
        setMessage("");

        try {
            await axios.post("/v2/admin/user-form/required-fields", { fields: draft });
            setMessage("Saved. The form below now follows these settings.");
            router.reload({ only: ["requiredFields"], preserveScroll: true });
        } catch (exception) {
            setError(exception?.response?.data?.message ?? "Could not save. Please try again.");
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="mb-6 rounded-2xl border border-violet-200 bg-violet-50/60 p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="text-sm font-semibold text-slate-900">Required fields</div>
                    <div className="mt-1 text-xs text-slate-500">
                        Choose which details must be filled before a user can be created.
                        Fields marked <span className="font-semibold text-red-500">*</span> are required.
                    </div>
                </div>

                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    className="rounded-xl border border-violet-300 bg-white px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"
                >
                    {open ? "Close" : "Choose required fields"}
                </button>
            </div>

            {open && (
                <div className="mt-4 rounded-xl border border-violet-100 bg-white p-4">
                    <div className="flex flex-wrap gap-2">
                        {ROLE_TABS.map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setTab(key)}
                                className={[
                                    "rounded-xl px-4 py-2 text-sm font-semibold",
                                    tab === key
                                        ? "bg-violet-600 text-white"
                                        : "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50",
                                ].join(" ")}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    <div className="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        {locked.map((field) => (
                            <label
                                key={`locked-${field.key}`}
                                className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500"
                            >
                                <input type="checkbox" checked disabled className="h-4 w-4" />
                                <span className="flex-1">{field.label}</span>
                                <span className="text-xs font-semibold uppercase tracking-wide text-slate-400">Always</span>
                            </label>
                        ))}

                        {fields.map((field) => {
                            const checked = Boolean(draft?.[tab]?.[field.key]);

                            return (
                                <label
                                    key={field.key}
                                    className={[
                                        "flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 text-sm",
                                        checked
                                            ? "border-violet-300 bg-violet-50 text-slate-900"
                                            : "border-slate-200 bg-white text-slate-700",
                                    ].join(" ")}
                                >
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={() => toggle(field.key)}
                                        className="h-4 w-4 accent-violet-600"
                                    />
                                    <span className="flex-1">{field.label}</span>
                                    <span
                                        className={[
                                            "text-xs font-semibold uppercase tracking-wide",
                                            checked ? "text-violet-700" : "text-slate-400",
                                        ].join(" ")}
                                    >
                                        {checked ? "Required" : "Optional"}
                                    </span>
                                </label>
                            );
                        })}
                    </div>

                    <div className="mt-4 flex flex-wrap items-center justify-end gap-2">
                        {message && <span className="mr-auto text-sm font-medium text-emerald-700">{message}</span>}
                        {error && <span className="mr-auto text-sm font-medium text-red-600">{error}</span>}

                        <button
                            type="button"
                            disabled={!dirty || saving}
                            onClick={() => {
                                setDraft(requiredFields);
                                setMessage("");
                            }}
                            className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 disabled:opacity-50"
                        >
                            Undo changes
                        </button>

                        <button
                            type="button"
                            disabled={!dirty || saving}
                            onClick={save}
                            className="rounded-xl bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            {saving ? "Saving..." : "Save"}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
