import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Edit({
    role = 'admin',
    artist = {},
}) {
    const {
        data,
        setData,
        patch,
        processing,
        errors,
    } = useForm({
        stage_name: artist.stage_name ?? '',
        legal_name: artist.legal_name ?? '',
        email: artist.email ?? '',
        phone: artist.phone ?? '',
        country: artist.country ?? 'India',
        timezone:
            artist.timezone ?? 'Asia/Kolkata',
        currency: artist.currency ?? 'INR',
        account_status:
            artist.account_status ?? 'active',
        kyc_status:
            artist.kyc_status ?? 'pending',
        can_create_releases:
            Boolean(
                artist.can_create_releases
            ),
        can_receive_splits:
            Boolean(
                artist.can_receive_splits
            ),
    });

    const submit = (event) => {
        event.preventDefault();

        patch(
            `/v2/admin/artists/${artist.id}`,
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Edit Artist"
            subtitle={artist.stage_name}
        >
            <Head title="Edit Artist" />

            <form
                onSubmit={submit}
                className="space-y-6"
            >
                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <Field
                            label="Stage Name"
                            value={data.stage_name}
                            error={errors.stage_name}
                            onChange={(value) =>
                                setData(
                                    'stage_name',
                                    value
                                )
                            }
                        />

                        <Field
                            label="Legal Name"
                            value={data.legal_name}
                            error={errors.legal_name}
                            onChange={(value) =>
                                setData(
                                    'legal_name',
                                    value
                                )
                            }
                        />

                        <Field
                            label="Email"
                            type="email"
                            value={data.email}
                            error={errors.email}
                            onChange={(value) =>
                                setData(
                                    'email',
                                    value
                                )
                            }
                        />

                        <Field
                            label="Phone"
                            value={data.phone}
                            error={errors.phone}
                            onChange={(value) =>
                                setData(
                                    'phone',
                                    value
                                )
                            }
                        />

                        <Field
                            label="Country"
                            value={data.country}
                            error={errors.country}
                            onChange={(value) =>
                                setData(
                                    'country',
                                    value
                                )
                            }
                        />

                        <Field
                            label="Timezone"
                            value={data.timezone}
                            error={errors.timezone}
                            onChange={(value) =>
                                setData(
                                    'timezone',
                                    value
                                )
                            }
                        />

                        <Field
                            label="Currency"
                            value={data.currency}
                            error={errors.currency}
                            onChange={(value) =>
                                setData(
                                    'currency',
                                    value
                                        .toUpperCase()
                                        .slice(0, 3)
                                )
                            }
                        />

                        <SelectField
                            label="Account Status"
                            value={data.account_status}
                            error={
                                errors.account_status
                            }
                            options={[
                                ['active', 'Active'],
                                ['pending', 'Pending'],
                                [
                                    'suspended',
                                    'Suspended',
                                ],
                            ]}
                            onChange={(value) =>
                                setData(
                                    'account_status',
                                    value
                                )
                            }
                        />

                        <SelectField
                            label="KYC Status"
                            value={data.kyc_status}
                            error={errors.kyc_status}
                            options={[
                                ['pending', 'Pending'],
                                [
                                    'verified',
                                    'Verified',
                                ],
                                [
                                    'rejected',
                                    'Rejected',
                                ],
                            ]}
                            onChange={(value) =>
                                setData(
                                    'kyc_status',
                                    value
                                )
                            }
                        />
                    </div>
                </section>

                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-bold text-slate-950">
                        Artist Permissions
                    </h2>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <Toggle
                            title="Create Releases"
                            checked={
                                data.can_create_releases
                            }
                            onChange={(checked) =>
                                setData(
                                    'can_create_releases',
                                    checked
                                )
                            }
                        />

                        <Toggle
                            title="Receive Splits"
                            checked={
                                data.can_receive_splits
                            }
                            onChange={(checked) =>
                                setData(
                                    'can_receive_splits',
                                    checked
                                )
                            }
                        />
                    </div>
                </section>

                <div className="flex justify-end gap-3">
                    <Link
                        href="/v2/admin/artists"
                        className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {processing
                            ? 'Saving...'
                            : 'Save Changes'}
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
    error,
    type = 'text',
}) {
    return (
        <label>
            <span className="text-sm font-semibold text-slate-700">
                {label}
            </span>

            <input
                type={type}
                value={value}
                onChange={(event) =>
                    onChange(event.target.value)
                }
                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
            />

            {error && (
                <p className="mt-1 text-xs text-red-600">
                    {error}
                </p>
            )}
        </label>
    );
}

function SelectField({
    label,
    value,
    onChange,
    options,
    error,
}) {
    return (
        <label>
            <span className="text-sm font-semibold text-slate-700">
                {label}
            </span>

            <select
                value={value}
                onChange={(event) =>
                    onChange(event.target.value)
                }
                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
            >
                {options.map(
                    ([optionValue, text]) => (
                        <option
                            key={optionValue}
                            value={optionValue}
                        >
                            {text}
                        </option>
                    )
                )}
            </select>

            {error && (
                <p className="mt-1 text-xs text-red-600">
                    {error}
                </p>
            )}
        </label>
    );
}

function Toggle({
    title,
    checked,
    onChange,
}) {
    return (
        <label className="flex items-center justify-between rounded-2xl border border-slate-200 p-4">
            <span className="font-semibold text-slate-800">
                {title}
            </span>

            <input
                type="checkbox"
                checked={checked}
                onChange={(event) =>
                    onChange(event.target.checked)
                }
                className="h-5 w-5"
            />
        </label>
    );
}
