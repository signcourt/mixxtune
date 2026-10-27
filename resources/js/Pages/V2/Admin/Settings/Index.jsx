import { Head, useForm } from "@inertiajs/react";
import { createContext, useContext, useEffect, useMemo, useRef, useState } from "react";
import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import UpdatePasswordForm from "@/Pages/Profile/Partials/UpdatePasswordForm";

/* MIXXTUNE_SETTINGS_WIRED_V1: each field shows its own save error */
const FieldErrors = createContext({});

const toMb = (value) => {
    const m = String(value || "").trim().match(/^(\d+(?:\.\d+)?)\s*([KMG]?)/i);
    if (!m) return null;
    const n = Number(m[1]);
    const unit = m[2].toUpperCase();
    return unit === "G" ? n * 1024 : unit === "K" ? n / 1024 : unit === "M" ? n : n / 1024 / 1024;
};

const maintenanceWindow = (m) => {
    if (!m.enabled) return { tone: "slate", text: "Off — users see nothing." };
    if (!m.date || !m.start_time || !m.end_time) {
        return { tone: "red", text: "Switched on, but date / start / end time is missing — nothing will happen. Fill all three." };
    }
    const start = new Date(`${m.date}T${m.start_time}:00+05:30`);
    let end = new Date(`${m.date}T${m.end_time}:00+05:30`);
    if (end <= start) end = new Date(end.getTime() + 24 * 3600 * 1000);
    const now = new Date();
    const fmt = (d) => d.toLocaleString("en-IN", { timeZone: "Asia/Kolkata", day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" });
    const range = `${fmt(start)} → ${fmt(end)} (India time)`;
    if (now < start) return { tone: "amber", text: `Scheduled: ${range}. Users see the notice until it starts.` };
    if (now < end) return { tone: "red", text: `Active now: ${range}. Everyone except Super Admin sees the maintenance screen.` };
    return { tone: "slate", text: `Over: ${range}. Nothing is shown any more.` };
};

export default function Index({
    role = "super_admin",
    settings = {},
    serverLimits = {},
}) {
    const getValue = (group, key, fallback = "") =>
        settings?.[group]?.[`${group}.${key}`] ?? fallback;

    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        branding: {
            name: getValue("branding", "name", "MIXX TUNE"),
            subtitle: getValue("branding", "subtitle"),
            logo_url: getValue(
                "branding",
                "logo_url",
                "/images/mixx-tune-login-logo.svg",
            ),
            login_logo_url: getValue(
                "branding",
                "login_logo_url",
                "/images/mixx-tune-login-logo.svg",
            ),
            favicon_url: getValue("branding", "favicon_url"),
            footer_text: getValue("branding", "footer_text"),
        },

        company: {
            name: getValue("company", "name", "MixxTune"),
            legal_name: getValue("company", "legal_name"),
            email: getValue("company", "email"),
            phone: getValue("company", "phone"),
            address: getValue("company", "address"),
            gst_number: getValue("company", "gst_number"),
            pan_number: getValue("company", "pan_number"),
        },

        release: {
            minimum_days_ahead: Number(
                getValue("release", "minimum_days_ahead", 1),
            ),
            allow_explicit: Boolean(
                getValue("release", "allow_explicit", true),
            ),
            require_artwork: Boolean(
                getValue("release", "require_artwork", true),
            ),
            require_wav: Boolean(getValue("release", "require_wav", true)),
        },

        upload: {
            audio_max_mb: Number(getValue("upload", "audio_max_mb", 300)),
            artwork_max_mb: Number(getValue("upload", "artwork_max_mb", 20)),
            report_max_mb: Number(getValue("upload", "report_max_mb", 500)),
        },

        maintenance: {
            enabled: Boolean(
                getValue("maintenance", "enabled", false),
            ),
            date: getValue("maintenance", "date", ""),
            start_time: getValue("maintenance", "start_time", ""),
            end_time: getValue("maintenance", "end_time", ""),
            message: getValue(
                "maintenance",
                "message",
                "Mixx Tune is undergoing scheduled maintenance. Please check back shortly.",
            ),
        },

        finance: {
            default_currency: getValue("finance", "default_currency", "INR"),
            minimum_withdrawal: Number(
                getValue("finance", "minimum_withdrawal", 1000),
            ),
            default_commission_percent: Number(
                getValue("finance", "default_commission_percent", 0),
            ),
        },

        invoice: {
            prefix: getValue("invoice", "prefix", "INV"),
            gst_percent: Number(getValue("invoice", "gst_percent", 0)),
            tds_percent: Number(getValue("invoice", "tds_percent", 0)),
            tds_no_pan_percent: Number(
                getValue("invoice", "tds_no_pan_percent", 0),
            ),
            tds_threshold: Number(getValue("invoice", "tds_threshold", 0)),
        },

        payout: {
            debit_account_number: getValue(
                "payout",
                "debit_account_number",
                "",
            ),
        },

        invitation: {
            reminders_enabled: Boolean(
                getValue("invitation", "reminders_enabled", true),
            ),
            reminder_every_days: Number(
                getValue("invitation", "reminder_every_days", 3),
            ),
            reminder_max: Number(getValue("invitation", "reminder_max", 3)),
        },
    });

    const update = (group, field, value) => {
        /* functional update: a value set after an upload never drops other typing */
        setData((current) => ({
            ...current,
            [group]: {
                ...current[group],
                [field]: value,
            },
        }));
    };

    const [uploadNotice, setUploadNotice] = useState({});

    /* unsaved-changes tracking (compares with what was last saved) */
    const latestData = useRef(data);
    latestData.current = data;
    const [savedSnapshot, setSavedSnapshot] = useState(() => JSON.stringify(data));
    const isDirty = JSON.stringify(data) !== savedSnapshot;

    const [brandingUploads, setBrandingUploads] = useState({});
    const [brandingUploading, setBrandingUploading] = useState({});
    const [brandingUploadErrors, setBrandingUploadErrors] = useState({});

    const uploadBrandingAsset = async (
        type,
        settingField,
    ) => {
        const file = brandingUploads[type];

        if (!file) {
            setBrandingUploadErrors((current) => ({
                ...current,
                [type]: "Please choose an image first.",
            }));
            return;
        }

        const formData = new FormData();

        formData.append("type", type);
        formData.append("file", file);

        setBrandingUploading((current) => ({
            ...current,
            [type]: true,
        }));

        setBrandingUploadErrors((current) => ({
            ...current,
            [type]: "",
        }));

        try {
            const token = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content");

            const response = await fetch(
                "/admin/settings/branding/upload",
                {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        ...(token
                            ? {
                                  "X-CSRF-TOKEN": token,
                              }
                            : {}),
                    },
                    body: formData,
                    credentials: "same-origin",
                },
            );

            const result = await response.json();

            if (!response.ok) {
                const validationMessage =
                    result?.errors?.file?.[0] ||
                    result?.message ||
                    "Upload failed.";

                throw new Error(validationMessage);
            }

            update(
                "branding",
                settingField,
                result.path || result.url,
            );

            setBrandingUploads((current) => ({
                ...current,
                [type]: null,
            }));

            setUploadNotice((current) => ({
                ...current,
                [type]: 'Uploaded. Press "Save Settings" at the bottom to use it.',
            }));
        } catch (error) {
            setBrandingUploadErrors((current) => ({
                ...current,
                [type]:
                    error?.message ||
                    "Unable to upload image.",
            }));
        } finally {
            setBrandingUploading((current) => ({
                ...current,
                [type]: false,
            }));
        }
    };

    const submit = (event) => {
        event.preventDefault();

        patch("/admin/settings", {
            preserveScroll: true,
            /* saved values become the new "clean" state */
            onSuccess: () => setSavedSnapshot(JSON.stringify(latestData.current)),
        });
    };

    const serverMb = Math.min(
        toMb(serverLimits.upload_max_filesize) ?? Infinity,
        toMb(serverLimits.post_max_size) ?? Infinity,
    );
    const overServer = [
        ["Audio", data.upload.audio_max_mb],
        ["Artwork", data.upload.artwork_max_mb],
        ["Report", data.upload.report_max_mb],
    ].filter(([, mb]) => Number.isFinite(serverMb) && Number(mb) > serverMb);
    const win = maintenanceWindow(data.maintenance);
    const winClass = {
        slate: "border-slate-200 bg-slate-50 text-slate-600",
        amber: "border-amber-300 bg-amber-50 text-amber-800",
        red: "border-red-200 bg-red-50 text-red-700",
    }[win.tone];
    const errorCount = Object.keys(errors).length;

    const sections = [
        ["branding", "Branding"],
        ["company", "Company"],
        ["release", "Release rules"],
        ["upload", "Upload limits"],
        ["maintenance", "Maintenance"],
        ["finance", "Finance & tax"],
        ["payout", "Payout & reminders"],
        ["password", "Password"],
    ];

    const uploadProps = (type, field) => ({
        type,
        selectedFile: brandingUploads[type],
        uploading: brandingUploading[type],
        error: brandingUploadErrors[type],
        notice: uploadNotice[type],
        onSelect: (file) => setBrandingUploads((current) => ({ ...current, [type]: file })),
        onUpload: () => uploadBrandingAsset(type, field),
    });

    return (
        <PanelLayout
            role={role}
            title="Master Settings"
            subtitle="Branding, company, release, upload and finance configuration"
        >
            <Head title="Master Settings" />

            {/* MIXXTUNE_SETTINGS_LAYOUT_V1: one column of tidy cards + a jump bar */}
            <nav className="sticky top-0 z-20 -mx-1 mb-5 flex gap-2 overflow-x-auto bg-slate-50/90 px-1 py-2 backdrop-blur">
                {sections.map(([id, label]) => (
                    <a
                        key={id}
                        href={`#settings-${id}`}
                        className="shrink-0 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-violet-300 hover:text-violet-700"
                    >
                        {label}
                    </a>
                ))}
            </nav>

            <FieldErrors.Provider value={errors}>
                <form onSubmit={submit} className="space-y-5 pb-4">
                    <Card id="branding" title="Branding" description="Name, logos and footer shown in the panel and on the login page.">
                        <Grid cols={3}>
                            <Field name="branding.name" label="Brand name" value={data.branding.name} onChange={(v) => update("branding", "name", v)} />
                            <Field name="branding.subtitle" label="Brand subtitle" value={data.branding.subtitle} onChange={(v) => update("branding", "subtitle", v)} />
                            <Field name="branding.footer_text" label="Footer text" value={data.branding.footer_text} onChange={(v) => update("branding", "footer_text", v)} />
                        </Grid>

                        <div className="mt-5 grid gap-4 md:grid-cols-3">
                            <BrandingUpload label="Panel logo" recommendedSize="1200 × 400 px" recommendedRatio="3:1" currentUrl={data.branding.logo_url} {...uploadProps("panel_logo", "logo_url")} />
                            <BrandingUpload label="Login logo" recommendedSize="1200 × 400 px" recommendedRatio="3:1" currentUrl={data.branding.login_logo_url} {...uploadProps("login_logo", "login_logo_url")} />
                            <BrandingUpload label="Favicon" recommendedSize="512 × 512 px" recommendedRatio="1:1" currentUrl={data.branding.favicon_url} {...uploadProps("favicon", "favicon_url")} />
                        </div>
                    </Card>

                    <Card id="company" title="Company profile" description="Printed on payout documents and invoices.">
                        <Grid cols={3}>
                            <Field name="company.name" label="Company name" value={data.company.name} onChange={(v) => update("company", "name", v)} />
                            <Field name="company.legal_name" label="Legal name" value={data.company.legal_name} onChange={(v) => update("company", "legal_name", v)} />
                            <Field name="company.email" label="E-mail" type="email" value={data.company.email} onChange={(v) => update("company", "email", v)} />
                            <Field name="company.phone" label="Phone" value={data.company.phone} onChange={(v) => update("company", "phone", v)} />
                            <Field name="company.gst_number" label="GST number" value={data.company.gst_number} onChange={(v) => update("company", "gst_number", v.toUpperCase())} />
                            <Field name="company.pan_number" label="PAN number" value={data.company.pan_number} onChange={(v) => update("company", "pan_number", v.toUpperCase())} />
                            <Field name="company.address" label="Address" wide value={data.company.address} onChange={(v) => update("company", "address", v)} />
                        </Grid>
                    </Card>

                    <Card id="release" title="Release rules" description="Checked when a label or artist sends a release for review. Super Admin edits of old releases are not checked.">
                        <div className="grid gap-4 md:grid-cols-[minmax(0,16rem)_1fr]">
                            {/* W118 MIXXTUNE_RELEASE_DATE_RULE: fixed rule, no minimum days any more */}
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                                <div className="font-semibold text-slate-900">Release date</div>
                                <p className="mt-1 text-xs leading-5 text-slate-600">Label / artist: today or any later date (earlier days are faded in the calendar). Super Admin / Manager: any date, also a past one for old songs.</p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <Toggle fill name="release.allow_explicit" label="Allow explicit content" checked={data.release.allow_explicit} onChange={(c) => update("release", "allow_explicit", c)} hint={`Off = labels / artists cannot tick "Explicit".`} />
                                <Toggle fill name="release.require_artwork" label="Artwork required" checked={data.release.require_artwork} onChange={(c) => update("release", "require_artwork", c)} hint="Off = can be sent without a poster." />
                                <Toggle fill name="release.require_wav" label="WAV audio required" checked={data.release.require_wav} onChange={(c) => update("release", "require_wav", c)} hint="Not in use — stores need WAV, so audio is always WAV." muted />
                            </div>
                        </div>
                    </Card>

                    <Card id="upload" title="Upload limits" description="Largest file a person can upload. The server has its own limit too.">
                        <Grid cols={3}>
                            <Field name="upload.audio_max_mb" label="Audio (WAV), MB" type="number" min={1} value={data.upload.audio_max_mb} onChange={(v) => update("upload", "audio_max_mb", Number(v))} />
                            <Field name="upload.artwork_max_mb" label="Artwork, MB" type="number" min={1} value={data.upload.artwork_max_mb} onChange={(v) => update("upload", "artwork_max_mb", Number(v))} />
                            <Field name="upload.report_max_mb" label="Revenue report (CSV), MB" type="number" min={1} value={data.upload.report_max_mb} onChange={(v) => update("upload", "report_max_mb", Number(v))} />
                        </Grid>

                        {overServer.length > 0 && (
                            <div className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {overServer.map(([n, mb]) => `${n} ${mb} MB`).join(", ")} is more than the server allows ({Math.round(serverMb)} MB).
                                The server limit is used — set {Math.round(serverMb)} MB or less to match.
                            </div>
                        )}

                        <div className="mt-4 flex flex-wrap gap-2 text-xs">
                            <span className="font-semibold text-slate-500">Server limits:</span>
                            {Object.entries(serverLimits).map(([key, value]) => (
                                <span key={key} className="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">
                                    {key.replaceAll("_", " ")}: <b>{value}</b>
                                </span>
                            ))}
                        </div>
                    </Card>

                    <Card
                        id="maintenance"
                        title="Scheduled maintenance"
                        description="Users first see a notice, then a maintenance screen during the window. Super Admin is never blocked."
                        right={
                            <label className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700">
                                <input type="checkbox" checked={data.maintenance.enabled} onChange={(e) => update("maintenance", "enabled", e.target.checked)} />
                                Enabled
                            </label>
                        }
                    >
                        <div className={`rounded-xl border px-4 py-3 text-sm font-semibold ${winClass}`}>
                            {win.text}
                            <div className="mt-0.5 text-xs font-normal opacity-80">
                                India time. An end time earlier than the start (e.g. 23:00 → 02:00) ends the next day.
                            </div>
                        </div>

                        <div className="mt-4 grid gap-4 sm:grid-cols-3">
                            <Field name="maintenance.date" label="Date" type="date" value={data.maintenance.date} onChange={(v) => update("maintenance", "date", v)} />
                            <Field name="maintenance.start_time" label="Start time" type="time" value={data.maintenance.start_time} onChange={(v) => update("maintenance", "start_time", v)} />
                            <Field name="maintenance.end_time" label="End time" type="time" value={data.maintenance.end_time} onChange={(v) => update("maintenance", "end_time", v)} />
                        </div>

                        <div className="mt-4 grid gap-4 lg:grid-cols-2">
                            <Field name="maintenance.message" label="Message users see" textarea value={data.maintenance.message} onChange={(v) => update("maintenance", "message", v)} />
                            <div>
                                <span className="text-sm font-semibold text-slate-700">Preview</span>
                                <div className="mt-2 flex items-start justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                                    <div>
                                        <div className="text-sm font-semibold text-slate-900">Scheduled maintenance</div>
                                        <div className="mt-1 text-xs leading-5 text-slate-600">
                                            {data.maintenance.message || "The Mixx Tune platform will undergo scheduled maintenance."}
                                        </div>
                                    </div>
                                    <span className="shrink-0 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">I Understand</span>
                                </div>
                            </div>
                        </div>
                    </Card>

                    <Card id="finance" title="Finance & tax" description="Withdrawals, invoices and TDS on payouts.">
                        <SubTitle>Withdrawals</SubTitle>
                        <Grid cols={3}>
                            <Field name="finance.minimum_withdrawal" label="Minimum withdrawal (₹)" type="number" min={0} value={data.finance.minimum_withdrawal} onChange={(v) => update("finance", "minimum_withdrawal", Number(v))} hint="Owners cannot request less than this." />
                            <Field name="finance.default_currency" label="Default currency" value={data.finance.default_currency} onChange={(v) => update("finance", "default_currency", v.toUpperCase())} hint="Not in use — each report keeps its own currency." muted />
                            <Field name="finance.default_commission_percent" label="Default commission %" type="number" min={0} value={data.finance.default_commission_percent} onChange={(v) => update("finance", "default_commission_percent", Number(v))} hint="Not in use — each label's own revenue % is used." muted />
                        </Grid>

                        <SubTitle className="mt-6">Invoice & TDS</SubTitle>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            <Field name="invoice.prefix" label="Invoice prefix" value={data.invoice.prefix} onChange={(v) => update("invoice", "prefix", v.toUpperCase().replace(/[^A-Z0-9]/g, ""))} hint="e.g. INV → INV-2026-000123" />
                            <Field name="invoice.gst_percent" label="GST %" type="number" min={0} value={data.invoice.gst_percent} onChange={(v) => update("invoice", "gst_percent", Number(v))} />
                            <Field name="invoice.tds_percent" label="TDS %" type="number" min={0} value={data.invoice.tds_percent} onChange={(v) => update("invoice", "tds_percent", Number(v))} />
                            <Field name="invoice.tds_no_pan_percent" label="TDS % without PAN" type="number" min={0} value={data.invoice.tds_no_pan_percent} onChange={(v) => update("invoice", "tds_no_pan_percent", Number(v))} hint="0 = same as TDS %" />
                            <Field name="invoice.tds_threshold" label="TDS yearly limit (₹)" type="number" min={0} value={data.invoice.tds_threshold} onChange={(v) => update("invoice", "tds_threshold", Number(v))} hint="0 = cut on every payout" />
                        </div>

                        <p className="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-800">
                            TDS is cut from each payout at Mark Paid and shown on the payout document. While TDS % is 0 nothing is cut. Confirm the rate and the yearly limit (April–March) with your CA.
                        </p>
                    </Card>

                    <div id="settings-payout" className="grid scroll-mt-20 gap-5 lg:grid-cols-2">
                        <Card title="Payout bank file (Axis)" description="Account the money is sent from in the Axis payout file.">
                            <Field
                                name="payout.debit_account_number"
                                label="Company Axis account number"
                                inputMode="numeric"
                                value={data.payout.debit_account_number}
                                onChange={(v) => update("payout", "debit_account_number", v.replace(/[^0-9 ]/g, ""))}
                            />
                        </Card>

                        <Card title="Invitation reminders" description="Re-sends unaccepted invitations, daily at 10 AM India time.">
                            <Toggle name="invitation.reminders_enabled" label="Send reminder e-mails automatically" checked={data.invitation.reminders_enabled} onChange={(c) => update("invitation", "reminders_enabled", c)} />
                            <div className="mt-4 grid grid-cols-2 gap-4">
                                <Field name="invitation.reminder_every_days" label="Remind every (days)" type="number" min={1} value={data.invitation.reminder_every_days} onChange={(v) => update("invitation", "reminder_every_days", Number(v))} />
                                <Field name="invitation.reminder_max" label="Reminders per person" type="number" min={0} value={data.invitation.reminder_max} onChange={(v) => update("invitation", "reminder_max", Number(v))} />
                            </div>
                        </Card>
                    </div>

                    {errorCount > 0 && (
                        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            <div className="font-semibold">Settings were NOT saved. Fix these and press Save again:</div>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                {Object.entries(errors).map(([key, message]) => (
                                    <li key={key}>{message}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="sticky bottom-0 z-20 -mx-1 flex flex-col gap-2 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                        <span className={`text-sm ${errorCount ? "font-semibold text-red-600" : isDirty ? "font-semibold text-amber-700" : "text-slate-500"}`}>
                            {errorCount
                                ? `${errorCount} problem(s) — not saved`
                                : isDirty
                                  ? "You have unsaved changes"
                                  : recentlySuccessful
                                    ? "Saved ✓"
                                    : "All changes saved"}
                        </span>
                        <button
                            type="submit"
                            disabled={processing}
                            className="h-11 rounded-xl bg-violet-600 px-7 text-sm font-semibold text-white shadow hover:bg-violet-700 disabled:opacity-50"
                        >
                            {processing ? "Saving..." : "Save Settings"}
                        </button>
                    </div>
                </form>
            </FieldErrors.Provider>

            <section id="settings-password" className="mt-5 scroll-mt-20 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div className="max-w-xl">
                    <UpdatePasswordForm />
                </div>
                <p className="mt-3 text-xs text-slate-500">Your own login password — separate from the settings above, with its own Save button.</p>
            </section>
        </PanelLayout>
    );
}

function BrandingUpload({
    label,
    currentUrl,
    selectedFile,
    recommendedSize,
    recommendedRatio,
    uploading = false,
    error = "",
    notice = "",
    onSelect,
    onUpload,
}) {
    const localPreview = useMemo(
        () => (selectedFile ? URL.createObjectURL(selectedFile) : null),
        [selectedFile],
    );

    useEffect(
        () => () => {
            if (localPreview) URL.revokeObjectURL(localPreview);
        },
        [localPreview],
    );

    const preview = localPreview || currentUrl;

    return (
        <div className="flex flex-col rounded-xl border border-slate-200 bg-white p-4">
            <div className="text-sm font-semibold text-slate-800">{label}</div>
            <div className="mt-0.5 text-xs text-slate-500">
                {recommendedSize} ({recommendedRatio}) · PNG / WebP / JPG / SVG · max 5 MB
            </div>

            <div className="mt-3 flex h-24 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-3">
                {preview ? (
                    <img src={preview} alt={`${label} preview`} className="max-h-16 max-w-full object-contain" />
                ) : (
                    <span className="text-xs text-slate-500">No image</span>
                )}
            </div>

            <div className="mt-3 flex items-center gap-2">
                <label className="inline-flex h-9 cursor-pointer items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Choose file
                    <input
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,.svg,.ico,image/*"
                        className="hidden"
                        onChange={(event) => onSelect(event.target.files?.[0] || null)}
                    />
                </label>
                <button
                    type="button"
                    disabled={!selectedFile || uploading}
                    onClick={onUpload}
                    className="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {uploading ? "Uploading..." : "Upload"}
                </button>
            </div>

            <div className="mt-2 min-h-4 truncate text-xs">
                {error ? (
                    <span className="font-medium text-red-600">{error}</span>
                ) : selectedFile ? (
                    <span className="text-slate-500">Selected: {selectedFile.name}</span>
                ) : notice ? (
                    <span className="font-medium text-amber-700">{notice}</span>
                ) : null}
            </div>
        </div>
    );
}

function Card({ id, title, description, right = null, children }) {
    return (
        <section id={id ? `settings-${id}` : undefined} className="scroll-mt-20 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="text-base font-semibold text-slate-900">{title}</h2>
                    {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
                </div>
                {right}
            </div>
            {children}
        </section>
    );
}

function SubTitle({ children, className = "" }) {
    return <div className={`mb-3 text-xs font-bold uppercase tracking-wider text-slate-400 ${className}`}>{children}</div>;
}

function Grid({ cols = 3, children }) {
    const cls = cols === 2 ? "grid gap-4 sm:grid-cols-2" : "grid gap-4 sm:grid-cols-2 lg:grid-cols-3";
    return <div className={cls}>{children}</div>;
}

function Field({ label, value, onChange, type = "text", wide = false, name = "", hint = "", muted = false, textarea = false, min, inputMode }) {
    const error = useContext(FieldErrors)?.[name];
    const cls = `mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm ${error ? "border-red-400 bg-red-50" : muted ? "border-slate-200 bg-slate-50 text-slate-500" : "border-slate-300"}`;

    return (
        <label className={`block ${wide ? "sm:col-span-2 lg:col-span-3" : ""}`}>
            <span className="text-sm font-semibold text-slate-700">{label}</span>
            {textarea ? (
                <textarea rows={3} value={value ?? ""} onChange={(e) => onChange(e.target.value)} className={cls} />
            ) : (
                <input type={type} min={min} inputMode={inputMode} value={value ?? ""} onChange={(e) => onChange(e.target.value)} className={cls} />
            )}
            {hint && <span className={`mt-1 block text-xs ${muted ? "text-slate-400" : "text-slate-500"}`}>{hint}</span>}
            {error && <span className="mt-1 block text-xs font-medium text-red-600">{error}</span>}
        </label>
    );
}

function Toggle({ label, checked, onChange, name = "", hint = "", muted = false, fill = false }) {
    const error = useContext(FieldErrors)?.[name];

    return (
        <label className={`flex ${fill ? "h-full" : ""} cursor-pointer items-start gap-3 rounded-xl border p-3.5 ${muted ? "border-slate-200 bg-slate-50" : "border-slate-200"}`}>
            <input type="checkbox" className="mt-0.5" checked={checked} onChange={(e) => onChange(e.target.checked)} />
            <span>
                <span className={`block text-sm font-semibold ${muted ? "text-slate-500" : "text-slate-700"}`}>{label}</span>
                {hint && <span className="mt-0.5 block text-xs text-slate-500">{hint}</span>}
                {error && <span className="mt-1 block text-xs font-medium text-red-600">{error}</span>}
            </span>
        </label>
    );
}
