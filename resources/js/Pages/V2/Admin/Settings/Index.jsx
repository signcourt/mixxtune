import { Head, useForm } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'super_admin',
    settings = {},
    serverLimits = {},
}) {
    const getValue = (group, key, fallback = '') =>
        settings?.[group]?.[`${group}.${key}`] ?? fallback;

    const {
        data,
        setData,
        patch,
        processing,
        errors,
    } = useForm({
        company: {
            name: getValue('company', 'name', 'MixxTune'),
            legal_name: getValue('company', 'legal_name'),
            email: getValue('company', 'email'),
            phone: getValue('company', 'phone'),
            address: getValue('company', 'address'),
            gst_number: getValue('company', 'gst_number'),
            pan_number: getValue('company', 'pan_number'),
        },

        release: {
            minimum_days_ahead: Number(
                getValue('release', 'minimum_days_ahead', 7)
            ),
            allow_explicit: Boolean(
                getValue('release', 'allow_explicit', true)
            ),
            require_artwork: Boolean(
                getValue('release', 'require_artwork', true)
            ),
            require_wav: Boolean(
                getValue('release', 'require_wav', true)
            ),
        },

        upload: {
            audio_max_mb: Number(
                getValue('upload', 'audio_max_mb', 300)
            ),
            artwork_max_mb: Number(
                getValue('upload', 'artwork_max_mb', 20)
            ),
            report_max_mb: Number(
                getValue('upload', 'report_max_mb', 500)
            ),
        },

        finance: {
            default_currency: getValue(
                'finance',
                'default_currency',
                'INR'
            ),
            minimum_withdrawal: Number(
                getValue('finance', 'minimum_withdrawal', 1000)
            ),
            default_commission_percent: Number(
                getValue(
                    'finance',
                    'default_commission_percent',
                    0
                )
            ),
        },

        invoice: {
            prefix: getValue('invoice', 'prefix', 'INV'),
            gst_percent: Number(
                getValue('invoice', 'gst_percent', 0)
            ),
            tds_percent: Number(
                getValue('invoice', 'tds_percent', 0)
            ),
        },
    });

    const update = (group, field, value) => {
        setData(group, {
            ...data[group],
            [field]: value,
        });
    };

    const submit = (event) => {
        event.preventDefault();

        patch('/v2/admin/settings', {
            preserveScroll: true,
        });
    };

    return (
        <PanelLayout
            role={role}
            title="Master Settings"
            subtitle="Company, release, upload and finance configuration"
        >
            <Head title="Master Settings" />

            <form onSubmit={submit} className="space-y-6">
                <Section title="Company Profile">
                    <Grid>
                        <Field
                            label="Company Name"
                            value={data.company.name}
                            onChange={(value) =>
                                update('company', 'name', value)
                            }
                        />

                        <Field
                            label="Legal Name"
                            value={data.company.legal_name}
                            onChange={(value) =>
                                update('company', 'legal_name', value)
                            }
                        />

                        <Field
                            label="Email"
                            type="email"
                            value={data.company.email}
                            onChange={(value) =>
                                update('company', 'email', value)
                            }
                        />

                        <Field
                            label="Phone"
                            value={data.company.phone}
                            onChange={(value) =>
                                update('company', 'phone', value)
                            }
                        />

                        <Field
                            label="GST Number"
                            value={data.company.gst_number}
                            onChange={(value) =>
                                update('company', 'gst_number', value)
                            }
                        />

                        <Field
                            label="PAN Number"
                            value={data.company.pan_number}
                            onChange={(value) =>
                                update('company', 'pan_number', value)
                            }
                        />

                        <Field
                            label="Address"
                            value={data.company.address}
                            onChange={(value) =>
                                update('company', 'address', value)
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
                                    'release',
                                    'minimum_days_ahead',
                                    Number(value)
                                )
                            }
                        />

                        <Toggle
                            label="Allow Explicit Content"
                            checked={data.release.allow_explicit}
                            onChange={(checked) =>
                                update(
                                    'release',
                                    'allow_explicit',
                                    checked
                                )
                            }
                        />

                        <Toggle
                            label="Artwork Required"
                            checked={data.release.require_artwork}
                            onChange={(checked) =>
                                update(
                                    'release',
                                    'require_artwork',
                                    checked
                                )
                            }
                        />

                        <Toggle
                            label="WAV Audio Required"
                            checked={data.release.require_wav}
                            onChange={(checked) =>
                                update(
                                    'release',
                                    'require_wav',
                                    checked
                                )
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
                                update(
                                    'upload',
                                    'audio_max_mb',
                                    Number(value)
                                )
                            }
                        />

                        <Field
                            label="Artwork Maximum MB"
                            type="number"
                            value={data.upload.artwork_max_mb}
                            onChange={(value) =>
                                update(
                                    'upload',
                                    'artwork_max_mb',
                                    Number(value)
                                )
                            }
                        />

                        <Field
                            label="Report Maximum MB"
                            type="number"
                            value={data.upload.report_max_mb}
                            onChange={(value) =>
                                update(
                                    'upload',
                                    'report_max_mb',
                                    Number(value)
                                )
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
                                )
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
                                update(
                                    'finance',
                                    'default_currency',
                                    value
                                )
                            }
                        />

                        <Field
                            label="Minimum Withdrawal"
                            type="number"
                            value={data.finance.minimum_withdrawal}
                            onChange={(value) =>
                                update(
                                    'finance',
                                    'minimum_withdrawal',
                                    Number(value)
                                )
                            }
                        />

                        <Field
                            label="Default Commission %"
                            type="number"
                            value={
                                data.finance.default_commission_percent
                            }
                            onChange={(value) =>
                                update(
                                    'finance',
                                    'default_commission_percent',
                                    Number(value)
                                )
                            }
                        />

                        <Field
                            label="Invoice Prefix"
                            value={data.invoice.prefix}
                            onChange={(value) =>
                                update('invoice', 'prefix', value)
                            }
                        />

                        <Field
                            label="GST %"
                            type="number"
                            value={data.invoice.gst_percent}
                            onChange={(value) =>
                                update(
                                    'invoice',
                                    'gst_percent',
                                    Number(value)
                                )
                            }
                        />

                        <Field
                            label="TDS %"
                            type="number"
                            value={data.invoice.tds_percent}
                            onChange={(value) =>
                                update(
                                    'invoice',
                                    'tds_percent',
                                    Number(value)
                                )
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
                        {processing ? 'Saving...' : 'Save Settings'}
                    </button>
                </div>
            </form>
        </PanelLayout>
    );
}

function Section({ title, children }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">
                {title}
            </h2>

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

function Field({
    label,
    value,
    onChange,
    type = 'text',
    wide = false,
}) {
    return (
        <label
            className={
                wide
                    ? 'md:col-span-2 xl:col-span-3'
                    : ''
            }
        >
            <span className="text-sm font-semibold text-slate-700">
                {label}
            </span>

            <input
                type={type}
                value={value ?? ''}
                onChange={(event) =>
                    onChange(event.target.value)
                }
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
                onChange={(event) =>
                    onChange(event.target.checked)
                }
            />

            <span className="text-sm font-semibold text-slate-700">
                {label}
            </span>
        </label>
    );
}
