import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const indiaStates = [
    ['AN', 'Andaman and Nicobar Islands'],
    ['AP', 'Andhra Pradesh'],
    ['AR', 'Arunachal Pradesh'],
    ['AS', 'Assam'],
    ['BR', 'Bihar'],
    ['CH', 'Chandigarh'],
    ['CG', 'Chhattisgarh'],
    ['DN', 'Dadra and Nagar Haveli and Daman and Diu'],
    ['DL', 'Delhi'],
    ['GA', 'Goa'],
    ['GJ', 'Gujarat'],
    ['HR', 'Haryana'],
    ['HP', 'Himachal Pradesh'],
    ['JK', 'Jammu and Kashmir'],
    ['JH', 'Jharkhand'],
    ['KA', 'Karnataka'],
    ['KL', 'Kerala'],
    ['LA', 'Ladakh'],
    ['LD', 'Lakshadweep'],
    ['MP', 'Madhya Pradesh'],
    ['MH', 'Maharashtra'],
    ['MN', 'Manipur'],
    ['ML', 'Meghalaya'],
    ['MZ', 'Mizoram'],
    ['NL', 'Nagaland'],
    ['OD', 'Odisha'],
    ['PY', 'Puducherry'],
    ['PB', 'Punjab'],
    ['RJ', 'Rajasthan'],
    ['SK', 'Sikkim'],
    ['TN', 'Tamil Nadu'],
    ['TS', 'Telangana'],
    ['TR', 'Tripura'],
    ['UP', 'Uttar Pradesh'],
    ['UK', 'Uttarakhand'],
    ['WB', 'West Bengal'],
];

export default function Create({
    role = 'admin',
    labels = [],
    admins = [],
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        stage_name: '',
        legal_name: '',
        username: '',
        email: '',
        phone: '',
        country: 'India',
        state_code: 'DL',
        timezone: 'Asia/Kolkata',
        currency: 'INR',
        account_status: 'active',
        can_create_releases: true,
        can_receive_splits: true,
        send_invitation: true,
        label_id: '',
        admin_id: '',
    });

    const generateUsername = () => {
        const source =
            data.stage_name
            || data.email
            || 'artist';

        const normalized = source
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 40);

        setData(
            'username',
            normalized.length >= 4
                ? normalized
                : `${normalized || 'artist'}-account`
        );
    };

    const submit = (event) => {
        event.preventDefault();

        post(
            '/v2/admin/artists',
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Create Artist"
            subtitle="Create and provision a new artist account" 
        >
            <Head title="Create Artist" />

            <form
                onSubmit={submit}
                className="space-y-6"
            >
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Artist Account
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Create the login account, artist profile,
                                permissions and invitation.
                            </p>
                        </div>

                        <Link
                            href="/v2/admin/artists"
                            className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            Back to Artists
                        </Link>
                    </div>

                    <div className="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <label>
                            <span className="text-sm font-semibold text-slate-700">
                                Label
                            </span>

                            <select
                                value={data.label_id}
                                onChange={(event) =>
                                    setData(
                                        'label_id',
                                        event.target.value
                                    )
                                }
                                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="">
                                    Independent / No Label
                                </option>

                                {labels.map((label) => (
                                    <option
                                        key={label.id}
                                        value={label.id}
                                    >
                                        {label.name}
                                    </option>
                                ))}
                            </select>

                            {errors.label_id && (
                                <p className="mt-1 text-xs text-red-600">
                                    {errors.label_id}
                                </p>
                            )}
                        </label>

                        {role === 'super_admin' && (
                            <label>
                                <span className="text-sm font-semibold text-slate-700">
                                    Assign Manager
                                </span>

                                <select
                                    value={data.admin_id}
                                    onChange={(event) =>
                                        setData(
                                            'admin_id',
                                            event.target.value
                                        )
                                    }
                                    className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                                >
                                    <option value="">
                                        No Manager Assignment
                                    </option>

                                    {admins.map((admin) => (
                                        <option
                                            key={admin.id}
                                            value={admin.id}
                                        >
                                            {admin.name} — {admin.email}
                                        </option>
                                    ))}
                                </select>

                                {errors.admin_id && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.admin_id}
                                    </p>
                                )}
                            </label>
                        )}

                        <Field
                            label="Stage Name"
                            value={data.stage_name}
                            error={errors.stage_name}
                            required
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

                        <div>
                            <Field
                                label="Username"
                                value={data.username}
                                error={errors.username}
                                onChange={(value) =>
                                    setData(
                                        'username',
                                        value
                                            .toLowerCase()
                                            .replace(
                                                /[^a-z0-9-]/g,
                                                ''
                                            )
                                    )
                                }
                            />

                            <button
                                type="button"
                                onClick={
                                    generateUsername
                                }
                                className="mt-2 text-xs font-semibold text-violet-600 hover:text-violet-700"
                            >
                                Generate username
                            </button>

                            <p className="mt-1 text-xs text-slate-400">
                                Leave blank for automatic
                                unique username.
                            </p>
                        </div>

                        <Field
                            label="Email"
                            type="email"
                            value={data.email}
                            error={errors.email}
                            required
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
                            required
                            onChange={(value) =>
                                setData(
                                    'country',
                                    value
                                )
                            }
                        />

                        <label>
                            <span className="text-sm font-semibold text-slate-700">
                                State / Region
                            </span>

                            <select
                                value={data.state_code}
                                onChange={(event) =>
                                    setData(
                                        'state_code',
                                        event.target.value
                                    )
                                }
                                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                                required
                            >
                                {indiaStates.map(
                                    ([code, name]) => (
                                        <option
                                            key={code}
                                            value={code}
                                        >
                                            {name} ({code})
                                        </option>
                                    )
                                )}
                            </select>

                            {errors.state_code && (
                                <p className="mt-1 text-xs text-red-600">
                                    {errors.state_code}
                                </p>
                            )}
                        </label>

                        <Field
                            label="Timezone"
                            value={data.timezone}
                            error={errors.timezone}
                            required
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
                            required
                            onChange={(value) =>
                                setData(
                                    'currency',
                                    value
                                        .toUpperCase()
                                        .slice(0, 3)
                                )
                            }
                        />

                        <label>
                            <span className="text-sm font-semibold text-slate-700">
                                Account Status
                            </span>

                            <select
                                value={
                                    data.account_status
                                }
                                onChange={(event) =>
                                    setData(
                                        'account_status',
                                        event.target
                                            .value
                                    )
                                }
                                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="active">
                                    Active
                                </option>

                                <option value="pending">
                                    Pending
                                </option>
                            </select>

                            {errors.account_status && (
                                <p className="mt-1 text-xs text-red-600">
                                    {
                                        errors.account_status
                                    }
                                </p>
                            )}
                        </label>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Artist Permissions
                    </h2>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <Toggle
                            title="Create Releases"
                            description="Artist can create and submit new releases."
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
                            description="Artist can receive royalty split allocations."
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

                        <Toggle
                            title="Send Invitation"
                            description="Send a secure password setup email to this artist."
                            checked={
                                data.send_invitation
                            }
                            onChange={(checked) =>
                                setData(
                                    'send_invitation',
                                    checked
                                )
                            }
                        />
                    </div>
                </section>

                {Object.keys(errors).length >
                    0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        Some information could not
                        be saved. Please review the
                        highlighted fields.
                    </div>
                )}

                <div className="sticky bottom-4 z-20 flex justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                    <Link
                        href="/v2/admin/artists"
                        className="rounded-xl border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-7 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {processing
                            ? 'Creating Artist...'
                            : 'Create Artist'}
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
    type = 'text',
    error,
    required = false,
}) {
    return (
        <label>
            <span className="text-sm font-semibold text-slate-700">
                {label}

                {required && (
                    <span className="ml-1 text-red-500">
                        *
                    </span>
                )}
            </span>

            <input
                type={type}
                value={value}
                required={required}
                onChange={(event) =>
                    onChange(
                        event.target.value
                    )
                }
                className={[
                    'mt-2 w-full rounded-xl border px-4 py-3 text-sm outline-none focus:ring-4',
                    error
                        ? 'border-red-400 focus:ring-red-100'
                        : 'border-slate-300 focus:border-violet-500 focus:ring-violet-500/10',
                ].join(' ')}
            />

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
    description,
    checked,
    onChange,
}) {
    return (
        <label
            className={[
                'flex cursor-pointer gap-4 rounded-2xl border p-5 transition',
                checked
                    ? 'border-violet-300 bg-violet-50'
                    : 'border-slate-200 bg-white',
            ].join(' ')}
        >
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) =>
                    onChange(
                        event.target.checked
                    )
                }
                className="mt-1"
            />

            <div>
                <div className="font-semibold text-slate-900">
                    {title}
                </div>

                <div className="mt-1 text-sm leading-6 text-slate-500">
                    {description}
                </div>
            </div>
        </label>
    );
}
