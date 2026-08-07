import {
    Head,
    Link,
    useForm,
    usePage,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusStyles = {
    verified:
        'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    submitted:
        'bg-blue-50 text-blue-700 ring-blue-600/20',
    pending:
        'bg-amber-50 text-amber-700 ring-amber-600/20',
    rejected:
        'bg-rose-50 text-rose-700 ring-rose-600/20',
    not_submitted:
        'bg-slate-100 text-slate-700 ring-slate-600/20',
};

export default function Edit({
    role = 'artist',
    profile = null,
}) {
    const { flash = {} } = usePage().props;

    const {
        data,
        setData,
        patch,
        processing,
        errors,
        recentlySuccessful,
    } = useForm({
        account_holder_name:
            profile?.account_holder_name ?? '',

        bank_account_number: '',

        bank_name:
            profile?.bank_name ?? '',

        ifsc_code:
            profile?.ifsc_code ?? '',

        branch_name:
            profile?.branch_name ?? '',

        upi_id:
            profile?.upi_id ?? '',

        pan_number: '',

        gst_number:
            profile?.gst_number ?? '',

        address_line_1:
            profile?.address_line_1 ?? '',

        address_line_2:
            profile?.address_line_2 ?? '',

        city:
            profile?.city ?? '',

        state:
            profile?.state ?? '',

        postal_code:
            profile?.postal_code ?? '',

        country_code:
            profile?.country_code ?? 'IN',
    });

    const submit = (event) => {
        event.preventDefault();

        patch('/v2/kyc-profile', {
            preserveScroll: true,
        });
    };

    const completionChecks = [
        Boolean(data.account_holder_name),
        Boolean(
            profile?.masked_bank_account ||
            data.bank_account_number
        ),
        Boolean(data.bank_name),
        Boolean(data.ifsc_code),
        Boolean(
            profile?.masked_pan ||
            data.pan_number
        ),
        Boolean(data.address_line_1),
        Boolean(data.city),
        Boolean(data.state),
        Boolean(data.postal_code),
    ];

    const completedCount =
        completionChecks.filter(Boolean).length;

    const completionPercentage = Math.round(
        (
            completedCount /
            completionChecks.length
        ) * 100
    );

    const normalizedStatus =
        profile?.kyc_status ??
        'not_submitted';

    return (
        <PanelLayout
            role={role}
            title="KYC & Payout Profile"
            subtitle="Secure your royalty payout details"
        >
            <Head title="KYC & Payout Profile" />

            <div className="mx-auto max-w-6xl space-y-6">
                {(flash.success ||
                    recentlySuccessful) && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                        {flash.success ??
                            'Profile saved successfully.'}
                    </div>
                )}

                <section className="grid gap-5 lg:grid-cols-[1.35fr_0.65fr]">
                    <div className="rounded-3xl bg-slate-950 p-6 text-white shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-violet-300">
                                    Profile completion
                                </p>

                                <h2 className="mt-3 text-3xl font-bold">
                                    {completionPercentage}%
                                </h2>

                                <p className="mt-2 max-w-xl text-sm leading-6 text-slate-300">
                                    Complete your identity,
                                    bank and address details
                                    to enable royalty
                                    withdrawals.
                                </p>
                            </div>

                            <StatusBadge
                                status={
                                    normalizedStatus
                                }
                            />
                        </div>

                        <div className="mt-6 h-3 overflow-hidden rounded-full bg-white/10">
                            <div
                                className="h-full rounded-full bg-violet-400 transition-all duration-500"
                                style={{
                                    width:
                                        `${completionPercentage}%`,
                                }}
                            />
                        </div>

                        <div className="mt-5 grid gap-3 sm:grid-cols-3">
                            <MiniStatus
                                label="Identity"
                                complete={Boolean(
                                    profile?.masked_pan ||
                                    data.pan_number
                                )}
                            />

                            <MiniStatus
                                label="Bank Account"
                                complete={Boolean(
                                    profile?.masked_bank_account ||
                                    data.bank_account_number
                                )}
                            />

                            <MiniStatus
                                label="Address"
                                complete={Boolean(
                                    data.address_line_1 &&
                                    data.city &&
                                    data.state &&
                                    data.postal_code
                                )}
                            />
                        </div>
                    </div>

                    <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Saved payout account
                        </p>

                        <p className="mt-4 text-2xl font-bold text-slate-900">
                            {profile?.masked_bank_account ??
                                'Not added'}
                        </p>

                        <p className="mt-2 text-sm text-slate-500">
                            {profile?.bank_name ??
                                'Add verified bank or UPI details'}
                        </p>

                        {profile?.upi_id && (
                            <div className="mt-4 rounded-2xl bg-slate-50 p-4">
                                <p className="text-xs font-semibold uppercase text-slate-500">
                                    UPI ID
                                </p>

                                <p className="mt-1 font-semibold text-slate-900">
                                    {profile.upi_id}
                                </p>
                            </div>
                        )}

                        <Link
                            href="/v2/withdrawals"
                            className="mt-5 inline-flex rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            View withdrawals
                        </Link>
                    </div>
                </section>

                {normalizedStatus === 'submitted' && (
                    <div className="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-800">
                        Your payout profile has been
                        submitted. Withdrawals will be
                        enabled after Admin verification.
                    </div>
                )}

                {normalizedStatus === 'rejected' && (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
                        Your verification requires
                        correction. Review the details and
                        submit the profile again.
                        {profile?.kyc_notes && (
                            <div className="mt-2 font-semibold">
                                Note: {profile.kyc_notes}
                            </div>
                        )}
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="space-y-6"
                >
                    <FormSection
                        title="Bank & Payment Details"
                        description="Add the payout account where royalty withdrawals should be sent."
                    >
                        <Field
                            label="Account Holder Name"
                            required
                            error={
                                errors.account_holder_name
                            }
                        >
                            <input
                                type="text"
                                value={
                                    data.account_holder_name
                                }
                                onChange={(event) =>
                                    setData(
                                        'account_holder_name',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                                placeholder="Name as per bank account"
                            />
                        </Field>

                        <Field
                            label="Bank Account Number"
                            error={
                                errors.bank_account_number
                            }
                        >
                            <input
                                type="text"
                                inputMode="numeric"
                                value={
                                    data.bank_account_number
                                }
                                onChange={(event) =>
                                    setData(
                                        'bank_account_number',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                                placeholder={
                                    profile
                                        ? 'Leave blank to keep saved account'
                                        : 'Enter account number'
                                }
                            />
                        </Field>

                        <Field
                            label="Bank Name"
                            error={errors.bank_name}
                        >
                            <input
                                type="text"
                                value={data.bank_name}
                                onChange={(event) =>
                                    setData(
                                        'bank_name',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                                placeholder="Bank name"
                            />
                        </Field>

                        <Field
                            label="IFSC Code"
                            error={errors.ifsc_code}
                        >
                            <input
                                type="text"
                                value={data.ifsc_code}
                                onChange={(event) =>
                                    setData(
                                        'ifsc_code',
                                        event.target.value.toUpperCase()
                                    )
                                }
                                className={inputClass}
                                placeholder="Example: HDFC0001234"
                            />
                        </Field>

                        <Field
                            label="Branch Name"
                            error={errors.branch_name}
                        >
                            <input
                                type="text"
                                value={data.branch_name}
                                onChange={(event) =>
                                    setData(
                                        'branch_name',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                                placeholder="Bank branch"
                            />
                        </Field>

                        <Field
                            label="UPI ID"
                            error={errors.upi_id}
                        >
                            <input
                                type="text"
                                value={data.upi_id}
                                onChange={(event) =>
                                    setData(
                                        'upi_id',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                                placeholder="name@bank"
                            />
                        </Field>
                    </FormSection>

                    <FormSection
                        title="Tax Information"
                        description="Tax details help generate compliant payout and invoice records."
                    >
                        <Field
                            label="PAN Number"
                            error={errors.pan_number}
                        >
                            <input
                                type="text"
                                value={data.pan_number}
                                onChange={(event) =>
                                    setData(
                                        'pan_number',
                                        event.target.value.toUpperCase()
                                    )
                                }
                                className={inputClass}
                                placeholder={
                                    profile
                                        ? 'Leave blank to keep saved PAN'
                                        : 'Enter PAN'
                                }
                            />
                        </Field>

                        <Field
                            label="GST Number"
                            error={errors.gst_number}
                        >
                            <input
                                type="text"
                                value={data.gst_number}
                                onChange={(event) =>
                                    setData(
                                        'gst_number',
                                        event.target.value.toUpperCase()
                                    )
                                }
                                className={inputClass}
                                placeholder="Optional GSTIN"
                            />
                        </Field>
                    </FormSection>

                    <FormSection
                        title="Billing Address"
                        description="This address will be used for payout and invoice records."
                    >
                        <Field
                            label="Address Line 1"
                            required
                            error={
                                errors.address_line_1
                            }
                            wide
                        >
                            <input
                                type="text"
                                value={
                                    data.address_line_1
                                }
                                onChange={(event) =>
                                    setData(
                                        'address_line_1',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                            />
                        </Field>

                        <Field
                            label="Address Line 2"
                            error={
                                errors.address_line_2
                            }
                            wide
                        >
                            <input
                                type="text"
                                value={
                                    data.address_line_2
                                }
                                onChange={(event) =>
                                    setData(
                                        'address_line_2',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                            />
                        </Field>

                        <Field
                            label="City"
                            required
                            error={errors.city}
                        >
                            <input
                                type="text"
                                value={data.city}
                                onChange={(event) =>
                                    setData(
                                        'city',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                            />
                        </Field>

                        <Field
                            label="State"
                            required
                            error={errors.state}
                        >
                            <input
                                type="text"
                                value={data.state}
                                onChange={(event) =>
                                    setData(
                                        'state',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                            />
                        </Field>

                        <Field
                            label="Postal Code"
                            required
                            error={
                                errors.postal_code
                            }
                        >
                            <input
                                type="text"
                                value={data.postal_code}
                                onChange={(event) =>
                                    setData(
                                        'postal_code',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                            />
                        </Field>

                        <Field
                            label="Country Code"
                            required
                            error={
                                errors.country_code
                            }
                        >
                            <input
                                type="text"
                                maxLength="2"
                                value={data.country_code}
                                onChange={(event) =>
                                    setData(
                                        'country_code',
                                        event.target.value.toUpperCase()
                                    )
                                }
                                className={inputClass}
                                placeholder="IN"
                            />
                        </Field>
                    </FormSection>

                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div>
                            <p className="font-semibold text-slate-900">
                                Submit payout profile
                            </p>

                            <p className="mt-1 text-sm text-slate-500">
                                Your profile will move to
                                verification after saving.
                            </p>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-violet-600 px-7 py-3 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing
                                ? 'Saving...'
                                : profile
                                  ? 'Update Profile'
                                  : 'Save & Submit'}
                        </button>
                    </div>
                </form>
            </div>
        </PanelLayout>
    );
}

const inputClass =
    'mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100';

function StatusBadge({ status }) {
    return (
        <span
            className={[
                'inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ring-1 ring-inset',
                statusStyles[status] ??
                    statusStyles.not_submitted,
            ].join(' ')}
        >
            {status.replaceAll('_', ' ')}
        </span>
    );
}

function MiniStatus({
    label,
    complete,
}) {
    return (
        <div className="rounded-2xl bg-white/5 p-4">
            <div className="text-sm font-semibold">
                {complete ? '✓' : '○'} {label}
            </div>

            <div className="mt-1 text-xs text-slate-400">
                {complete
                    ? 'Completed'
                    : 'Required'}
            </div>
        </div>
    );
}

function FormSection({
    title,
    description,
    children,
}) {
    return (
        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-6 border-b border-slate-100 pb-5">
                <h2 className="text-lg font-bold text-slate-900">
                    {title}
                </h2>

                <p className="mt-1 text-sm text-slate-500">
                    {description}
                </p>
            </div>

            <div className="grid gap-5 md:grid-cols-2">
                {children}
            </div>
        </section>
    );
}

function Field({
    label,
    required = false,
    error,
    wide = false,
    children,
}) {
    return (
        <label
            className={
                wide ? 'md:col-span-2' : ''
            }
        >
            <span className="text-sm font-semibold text-slate-700">
                {label}

                {required && (
                    <span className="ml-1 text-rose-500">
                        *
                    </span>
                )}
            </span>

            {children}

            {error && (
                <div className="mt-1 text-sm text-rose-600">
                    {error}
                </div>
            )}
        </label>
    );
}
