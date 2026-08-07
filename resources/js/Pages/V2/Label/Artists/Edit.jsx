import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Edit({
    role = 'label',
    label = {},
    artist = {},
}) {
    const {
        data,
        setData,
        patch,
        processing,
        errors,
    } = useForm({
        stage_name:
            artist.stage_name ?? '',

        legal_name:
            artist.legal_name ?? '',

        username:
            artist.username ?? '',

        email:
            artist.email ?? '',

        phone:
            artist.phone ?? '',

        country:
            artist.country ?? 'India',

        timezone:
            artist.timezone ?? 'Asia/Kolkata',

        currency:
            artist.currency ?? 'INR',

        account_status:
            artist.account_status ?? 'active',

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
            `/v2/label/artists/${artist.id}`,
            {
                preserveScroll: true,
            }
        );
    };

    const postAction = (
        action,
        confirmation
    ) => {
        if (
            confirmation
            && !window.confirm(
                confirmation
            )
        ) {
            return;
        }

        router.post(
            `/v2/label/artists/${artist.id}/${action}`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Edit Artist"
            subtitle={`Manage ${artist.stage_name}`}
        >
            <Head
                title={`Edit ${artist.stage_name}`}
            />

            <form
                onSubmit={submit}
                className="space-y-6"
            >
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Artist Information
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Label: {label.name}
                                {' · '}
                                {artist.public_id}
                            </p>
                        </div>

                        <Link
                            href="/v2/label/artists"
                            className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            Back to Artists
                        </Link>
                    </div>

                    <div className="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
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

                        <Field
                            label="Username"
                            value={data.username}
                            error={errors.username}
                            required
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
                                        event.target.value
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

                                <option value="suspended">
                                    Suspended
                                </option>
                            </select>

                            {errors.account_status && (
                                <p className="mt-1 text-xs text-red-600">
                                    {errors.account_status}
                                </p>
                            )}
                        </label>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Permissions
                    </h2>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <Toggle
                            title="Create Releases"
                            description="Allow this artist to create and submit releases."
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
                            title="Receive Royalty Splits"
                            description="Allow royalty allocations for this artist."
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

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Account Actions
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Invitation: {
                            artist.invitation_status
                            ?? 'unknown'
                        }
                        {' · '}
                        Last login: {
                            artist.last_login_at
                            ?? 'Never'
                        }
                    </p>

                    <div className="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={() =>
                                postAction(
                                    'resend-invitation'
                                )
                            }
                            className="rounded-xl border border-violet-300 bg-violet-50 px-4 py-3 text-sm font-semibold text-violet-700"
                        >
                            Resend Invitation
                        </button>

                        <button
                            type="button"
                            onClick={() =>
                                postAction(
                                    'reset-password',
                                    'Send a new secure password setup email?'
                                )
                            }
                            className="rounded-xl border border-blue-300 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-700"
                        >
                            Reset Password
                        </button>

                        <button
                            type="button"
                            onClick={() =>
                                postAction(
                                    'toggle-status',
                                    artist.account_status
                                        === 'active'
                                        ? 'Suspend this artist?'
                                        : 'Activate this artist?'
                                )
                            }
                            className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700"
                        >
                            {artist.account_status
                                === 'active'
                                ? 'Suspend Artist'
                                : 'Activate Artist'}
                        </button>
                    </div>
                </section>

                {Object.keys(errors).length >
                    0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        Please review the highlighted fields.
                    </div>
                )}

                <div className="sticky bottom-4 z-20 flex justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                    <Link
                        href="/v2/label/artists"
                        className="rounded-xl border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-7 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
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
                    onChange(event.target.value)
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
