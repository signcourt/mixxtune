import { Head, useForm } from "@inertiajs/react";
import { useState } from "react";
import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import UpdatePasswordForm from "@/Pages/Profile/Partials/UpdatePasswordForm";

export default function Index({
    role = "super_admin",
    settings = {},
    serverLimits = {},
}) {
    const getValue = (group, key, fallback = "") =>
        settings?.[group]?.[`${group}.${key}`] ?? fallback;

    const { data, setData, patch, processing, errors } = useForm({
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
                getValue("release", "minimum_days_ahead", 7),
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
        },
    });

    const update = (group, field, value) => {
        setData(group, {
            ...data[group],
            [field]: value,
        });
    };

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
                "/v2/admin/settings/branding/upload",
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

        patch("/v2/admin/settings", {
            preserveScroll: true,
        });
    };

    return (
        <PanelLayout
            role={role}
            title="Master Settings"
            subtitle="Branding, company, release, upload and finance configuration"
        >
            <Head title="Master Settings" />

            <form onSubmit={submit} className="space-y-6">
                <Section title="Branding">
                    <Grid>
                        <Field
                            label="Brand Name"
                            value={data.branding.name}
                            onChange={(value) =>
                                update("branding", "name", value)
                            }
                        />

                        <Field
                            label="Brand Subtitle"
                            value={data.branding.subtitle}
                            onChange={(value) =>
                                update("branding", "subtitle", value)
                            }
                        />

                        <BrandingUpload
                            label="Panel Logo"
                            type="panel_logo"
                            recommendedSize="1200 × 400 px"
                            recommendedRatio="3:1"
                            currentUrl={data.branding.logo_url}
                            selectedFile={
                                brandingUploads.panel_logo
                            }
                            uploading={
                                brandingUploading.panel_logo
                            }
                            error={
                                brandingUploadErrors.panel_logo
                            }
                            onSelect={(file) =>
                                setBrandingUploads(
                                    (current) => ({
                                        ...current,
                                        panel_logo: file,
                                    }),
                                )
                            }
                            onUpload={() =>
                                uploadBrandingAsset(
                                    "panel_logo",
                                    "logo_url",
                                )
                            }
                        />

                        <BrandingUpload
                            label="Login Logo"
                            type="login_logo"
                            recommendedSize="1200 × 400 px"
                            recommendedRatio="3:1"
                            currentUrl={
                                data.branding.login_logo_url
                            }
                            selectedFile={
                                brandingUploads.login_logo
                            }
                            uploading={
                                brandingUploading.login_logo
                            }
                            error={
                                brandingUploadErrors.login_logo
                            }
                            onSelect={(file) =>
                                setBrandingUploads(
                                    (current) => ({
                                        ...current,
                                        login_logo: file,
                                    }),
                                )
                            }
                            onUpload={() =>
                                uploadBrandingAsset(
                                    "login_logo",
                                    "login_logo_url",
                                )
                            }
                        />

                        <BrandingUpload
                            label="Favicon"
                            type="favicon"
                            recommendedSize="512 × 512 px"
                            recommendedRatio="1:1"
                            currentUrl={
                                data.branding.favicon_url
                            }
                            selectedFile={
                                brandingUploads.favicon
                            }
                            uploading={
                                brandingUploading.favicon
                            }
                            error={
                                brandingUploadErrors.favicon
                            }
                            onSelect={(file) =>
                                setBrandingUploads(
                                    (current) => ({
                                        ...current,
                                        favicon: file,
                                    }),
                                )
                            }
                            onUpload={() =>
                                uploadBrandingAsset(
                                    "favicon",
                                    "favicon_url",
                                )
                            }
                        />

                        <Field
                            label="Footer Text"
                            value={data.branding.footer_text}
                            onChange={(value) =>
                                update("branding", "footer_text", value)
                            }
                        />
                    </Grid>

                    <div className="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        

                        <div className="mt-3 flex min-h-24 items-center justify-center rounded-xl border border-slate-200 bg-white p-4">
                            {data.branding.logo_url ? (
                                <img
                                    src={data.branding.logo_url}
                                    alt={data.branding.name || "Brand logo"}
                                    className="max-h-16 max-w-full object-contain"
                                />
                            ) : (
                                <span className="text-sm text-slate-500">
                                    No logo configured
                                </span>
                            )}
                        </div>
                    </div>
                </Section>

                <Section title="Company Profile">
                    <Grid>
                        <Field
                            label="Company Name"
                            value={data.company.name}
                            onChange={(value) =>
                                update("company", "name", value)
                            }
                        />

                        <Field
                            label="Legal Name"
                            value={data.company.legal_name}
                            onChange={(value) =>
                                update("company", "legal_name", value)
                            }
                        />

                        <Field
                            label="Email"
                            type="email"
                            value={data.company.email}
                            onChange={(value) =>
                                update("company", "email", value)
                            }
                        />

                        <Field
                            label="Phone"
                            value={data.company.phone}
                            onChange={(value) =>
                                update("company", "phone", value)
                            }
                        />

                        <Field
                            label="GST Number"
                            value={data.company.gst_number}
                            onChange={(value) =>
                                update("company", "gst_number", value)
                            }
                        />

                        <Field
                            label="PAN Number"
                            value={data.company.pan_number}
                            onChange={(value) =>
                                update("company", "pan_number", value)
                            }
                        />

                        <Field
                            label="Address"
                            value={data.company.address}
                            onChange={(value) =>
                                update("company", "address", value)
                            }
                            wide
                        />
                    </Grid>
                </Section>

                <Section title="Release Rules">
                    <Grid>
                        <Field
                            label="Minimum Days Before Release"
                            type="number"
                            value={data.release.minimum_days_ahead}
                            onChange={(value) =>
                                update(
                                    "release",
                                    "minimum_days_ahead",
                                    Number(value),
                                )
                            }
                        />

                        <Toggle
                            label="Allow Explicit Content"
                            checked={data.release.allow_explicit}
                            onChange={(checked) =>
                                update("release", "allow_explicit", checked)
                            }
                        />

                        <Toggle
                            label="Artwork Required"
                            checked={data.release.require_artwork}
                            onChange={(checked) =>
                                update("release", "require_artwork", checked)
                            }
                        />

                        <Toggle
                            label="WAV Audio Required"
                            checked={data.release.require_wav}
                            onChange={(checked) =>
                                update("release", "require_wav", checked)
                            }
                        />
                    </Grid>
                </Section>

                <Section title="Upload Rules">
                    <Grid>
                        <Field
                            label="Audio Maximum MB"
                            type="number"
                            value={data.upload.audio_max_mb}
                            onChange={(value) =>
                                update("upload", "audio_max_mb", Number(value))
                            }
                        />

                        <Field
                            label="Artwork Maximum MB"
                            type="number"
                            value={data.upload.artwork_max_mb}
                            onChange={(value) =>
                                update(
                                    "upload",
                                    "artwork_max_mb",
                                    Number(value),
                                )
                            }
                        />

                        <Field
                            label="Report Maximum MB"
                            type="number"
                            value={data.upload.report_max_mb}
                            onChange={(value) =>
                                update("upload", "report_max_mb", Number(value))
                            }
                        />
                    </Grid>

                    <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div className="font-semibold text-amber-800">
                            Current PHP Server Limits
                        </div>

                        <div className="mt-3 grid gap-2 text-sm text-amber-700 sm:grid-cols-2 xl:grid-cols-5">
                            {Object.entries(serverLimits).map(
                                ([key, value]) => (
                                    <div key={key}>
                                        <strong>{key}</strong>: {value}
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                </Section>

                <Section title="Finance & Invoice">
                    <Grid>
                        <Field
                            label="Default Currency"
                            value={data.finance.default_currency}
                            onChange={(value) =>
                                update("finance", "default_currency", value)
                            }
                        />

                        <Field
                            label="Minimum Withdrawal"
                            type="number"
                            value={data.finance.minimum_withdrawal}
                            onChange={(value) =>
                                update(
                                    "finance",
                                    "minimum_withdrawal",
                                    Number(value),
                                )
                            }
                        />

                        <Field
                            label="Default Commission %"
                            type="number"
                            value={data.finance.default_commission_percent}
                            onChange={(value) =>
                                update(
                                    "finance",
                                    "default_commission_percent",
                                    Number(value),
                                )
                            }
                        />

                        <Field
                            label="Invoice Prefix"
                            value={data.invoice.prefix}
                            onChange={(value) =>
                                update("invoice", "prefix", value)
                            }
                        />

                        <Field
                            label="GST %"
                            type="number"
                            value={data.invoice.gst_percent}
                            onChange={(value) =>
                                update("invoice", "gst_percent", Number(value))
                            }
                        />

                        <Field
                            label="TDS %"
                            type="number"
                            value={data.invoice.tds_percent}
                            onChange={(value) =>
                                update("invoice", "tds_percent", Number(value))
                            }
                        />
                    </Grid>
                </Section>

                {Object.keys(errors).length > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        कुछ settings save नहीं हुईं। Entered values check करें।
                    </div>
                )}

                <div className="sticky bottom-4 flex justify-end">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-7 py-3 text-sm font-semibold text-white shadow-lg disabled:opacity-50"
                    >
                        {processing ? "Saving..." : "Save Settings"}
                    </button>
                </div>
            </form>

            <section className="mt-6 w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <UpdatePasswordForm />
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
    onSelect,
    onUpload,
}) {
    const localPreview = selectedFile
        ? URL.createObjectURL(selectedFile)
        : null;

    const preview = localPreview || currentUrl;

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="text-sm font-semibold text-slate-800">
                {label}
            </div>

            <div className="mt-1 text-xs leading-5 text-slate-500">
                Recommended: {recommendedSize}
                {" "}({recommendedRatio})
                {" "}• PNG / WebP / JPG / SVG
                {" "}• Max 5 MB
                {recommendedRatio === "3:1"
                    ? " • Transparent background preferred"
                    : " • Square image required"}
            </div>

            <div className="mt-3 flex min-h-24 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-3">
                {preview ? (
                    <img
                        src={preview}
                        alt={`${label} preview`}
                        className="max-h-16 max-w-full object-contain"
                    />
                ) : (
                    <span className="text-xs text-slate-500">
                        No image configured
                    </span>
                )}
            </div>

            <input
                type="file"
                accept=".jpg,.jpeg,.png,.webp,.svg,.ico,image/*"
                className="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-700"
                onChange={(event) =>
                    onSelect(
                        event.target.files?.[0] || null,
                    )
                }
            />

            {selectedFile && (
                <div className="mt-2 truncate text-xs text-slate-500">
                    Selected: {selectedFile.name}
                </div>
            )}

            {error && (
                <div className="mt-2 text-xs font-medium text-red-600">
                    {error}
                </div>
            )}

            <button
                type="button"
                disabled={!selectedFile || uploading}
                onClick={onUpload}
                className="mt-3 inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40"
            >
                {uploading
                    ? "Uploading..."
                    : currentUrl
                      ? "Upload / Replace"
                      : "Upload"}
            </button>
        </div>
    );
}

function Section({ title, children }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">{title}</h2>

            <div className="mt-5">{children}</div>
        </section>
    );
}

function Grid({ children }) {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {children}
        </div>
    );
}

function Field({ label, value, onChange, type = "text", wide = false }) {
    return (
        <label className={wide ? "md:col-span-2 xl:col-span-3" : ""}>
            <span className="text-sm font-semibold text-slate-700">
                {label}
            </span>

            <input
                type={type}
                value={value ?? ""}
                onChange={(event) => onChange(event.target.value)}
                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
            />
        </label>
    );
}

function Toggle({ label, checked, onChange }) {
    return (
        <label className="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
            />

            <span className="text-sm font-semibold text-slate-700">
                {label}
            </span>
        </label>
    );
}
