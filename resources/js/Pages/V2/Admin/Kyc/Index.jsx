import {
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusStyles = {
    submitted:
        'bg-amber-50 text-amber-700 ring-amber-600/20',
    verified:
        'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    rejected:
        'bg-rose-50 text-rose-700 ring-rose-600/20',
};

export default function Index({
    filters = {},
    counts = {},
    profiles = {},
}) {
    const { flash = {}, auth = {} } =
        usePage().props;

    const role =
        auth?.user?.role ??
        'admin';

    const currentStatus =
        filters.status ??
        'submitted';

    const changeStatus = (status) => {
        router.get(
            '/v2/admin/kyc',
            { status },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="KYC Reviews"
            subtitle="Review payout and tax profiles before withdrawals are enabled"
        >
            <Head title="KYC Reviews" />

            <div className="space-y-6">
                {flash.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
                        {flash.success}
                    </div>
                )}

                {flash.error && (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-800">
                        {flash.error}
                    </div>
                )}

                <section className="grid gap-4 md:grid-cols-3">
                    <SummaryCard
                        label="Submitted"
                        value={counts.submitted ?? 0}
                        active={
                            currentStatus ===
                            'submitted'
                        }
                        onClick={() =>
                            changeStatus(
                                'submitted'
                            )
                        }
                    />

                    <SummaryCard
                        label="Verified"
                        value={counts.verified ?? 0}
                        active={
                            currentStatus ===
                            'verified'
                        }
                        onClick={() =>
                            changeStatus(
                                'verified'
                            )
                        }
                    />

                    <SummaryCard
                        label="Rejected"
                        value={counts.rejected ?? 0}
                        active={
                            currentStatus ===
                            'rejected'
                        }
                        onClick={() =>
                            changeStatus(
                                'rejected'
                            )
                        }
                    />
                </section>

                <section className="space-y-4">
                    {(profiles.data ?? []).map(
                        (profile) => (
                            <KycCard
                                key={profile.id}
                                profile={profile}
                            />
                        )
                    )}

                    {(profiles.data ?? []).length ===
                        0 && (
                        <div className="rounded-3xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
                            <p className="text-lg font-bold text-slate-800">
                                No{' '}
                                {currentStatus.replaceAll(
                                    '_',
                                    ' '
                                )}{' '}
                                KYC profiles
                            </p>

                            <p className="mt-2 text-sm text-slate-500">
                                Profiles matching this
                                status will appear here.
                            </p>
                        </div>
                    )}
                </section>

                <Pagination
                    links={profiles.links ?? []}
                />
            </div>
        </PanelLayout>
    );
}

function KycCard({ profile }) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        status: 'verified',
        kyc_notes:
            profile.kyc_notes ?? '',
    });

    const submit = (
        event,
        status
    ) => {
        event.preventDefault();

        setData('status', status);

        router.post(
            `/v2/admin/kyc/${profile.id}/verify`,
            {
                status,
                kyc_notes:
                    data.kyc_notes,
            },
            {
                preserveScroll: true,
            }
        );
    };

    const submitted =
        profile.kyc_status ===
        'submitted';

    return (
        <article className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                <div>
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-lg font-bold text-slate-900">
                            {profile.user?.name ??
                                'Unknown User'}
                        </h2>

                        <StatusBadge
                            status={
                                profile.kyc_status
                            }
                        />
                    </div>

                    <p className="mt-1 text-sm text-slate-500">
                        {profile.user?.email ??
                            'No email'}
                    </p>

                    <p className="mt-1 text-xs text-slate-400">
                        Profile #{profile.id}
                        {' • '}
                        Updated{' '}
                        {formatDate(
                            profile.updated_at
                        )}
                    </p>
                </div>

                <div className="text-right">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Tax Profile
                    </p>

                    <p className="mt-1 font-bold text-slate-800">
                        {profile.gst_number
                            ? 'GST Registered'
                            : 'Non-GST'}
                    </p>
                </div>
            </div>

            <div className="grid gap-6 p-6 xl:grid-cols-3">
                <DetailSection
                    title="Identity & Tax"
                    rows={[
                        [
                            'Account Holder',
                            profile.account_holder_name,
                        ],
                        [
                            'PAN',
                            profile.masked_pan ??
                                'Not available',
                        ],
                        [
                            'GSTIN',
                            profile.gst_number ??
                                'Not registered',
                        ],
                    ]}
                />

                <DetailSection
                    title="Payout Destination"
                    rows={[
                        [
                            'Bank',
                            profile.bank_name,
                        ],
                        [
                            'Account',
                            profile.masked_bank_account ??
                                'Not available',
                        ],
                        [
                            'IFSC',
                            profile.ifsc_code,
                        ],
                        [
                            'UPI',
                            profile.upi_id ??
                                'Not added',
                        ],
                    ]}
                />

                <DetailSection
                    title="Billing Address"
                    rows={[
                        [
                            'Address',
                            profile.address_line_1,
                        ],
                        [
                            'City',
                            profile.city,
                        ],
                        [
                            'State',
                            profile.state,
                        ],
                        [
                            'PIN',
                            profile.postal_code,
                        ],
                        [
                            'Country',
                            profile.country_code,
                        ],
                    ]}
                />
            </div>

            {profile.kyc_notes &&
                !submitted && (
                <div className="mx-6 mb-6 rounded-2xl bg-slate-50 p-4">
                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Review Notes
                    </p>

                    <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">
                        {profile.kyc_notes}
                    </p>
                </div>
            )}

            {submitted && (
                <div className="border-t border-slate-200 bg-slate-50/70 p-6">
                    <label className="block">
                        <span className="text-sm font-semibold text-slate-700">
                            Review Notes
                        </span>

                        <textarea
                            value={
                                data.kyc_notes
                            }
                            onChange={(event) =>
                                setData(
                                    'kyc_notes',
                                    event.target
                                        .value
                                )
                            }
                            className="mt-2 min-h-24 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100"
                            placeholder="Optional approval note; add a clear reason when rejecting."
                        />

                        {errors.kyc_notes && (
                            <p className="mt-1 text-sm text-rose-600">
                                {
                                    errors.kyc_notes
                                }
                            </p>
                        )}
                    </label>

                    <div className="mt-4 flex flex-wrap gap-3">
                        <button
                            type="button"
                            disabled={processing}
                            onClick={(event) =>
                                submit(
                                    event,
                                    'verified'
                                )
                            }
                            className="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                        >
                            Verify KYC
                        </button>

                        <button
                            type="button"
                            disabled={processing}
                            onClick={(event) => {
                                if (
                                    !data.kyc_notes
                                        .trim()
                                ) {
                                    window.alert(
                                        'Please enter a rejection reason.'
                                    );
                                    return;
                                }

                                submit(
                                    event,
                                    'rejected'
                                );
                            }}
                            className="rounded-xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:opacity-50"
                        >
                            Reject KYC
                        </button>
                    </div>
                </div>
            )}
        </article>
    );
}

function DetailSection({
    title,
    rows,
}) {
    return (
        <div>
            <h3 className="text-sm font-bold uppercase tracking-wide text-slate-500">
                {title}
            </h3>

            <dl className="mt-4 space-y-3">
                {rows.map(
                    ([label, value]) => (
                        <div
                            key={label}
                            className="rounded-xl bg-slate-50 px-4 py-3"
                        >
                            <dt className="text-xs font-semibold text-slate-400">
                                {label}
                            </dt>

                            <dd className="mt-1 break-words text-sm font-semibold text-slate-800">
                                {value || '—'}
                            </dd>
                        </div>
                    )
                )}
            </dl>
        </div>
    );
}

function SummaryCard({
    label,
    value,
    active,
    onClick,
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={[
                'rounded-2xl border bg-white p-5 text-left shadow-sm transition',
                active
                    ? 'border-violet-400 ring-4 ring-violet-100'
                    : 'border-slate-200 hover:border-violet-300',
            ].join(' ')}
        >
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </p>

            <p className="mt-3 text-3xl font-bold text-slate-900">
                {value}
            </p>
        </button>
    );
}

function StatusBadge({ status }) {
    const normalized =
        status ?? 'submitted';

    return (
        <span
            className={[
                'inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ring-1 ring-inset',
                statusStyles[normalized] ??
                    statusStyles.submitted,
            ].join(' ')}
        >
            {normalized.replaceAll(
                '_',
                ' '
            )}
        </span>
    );
}

function Pagination({ links }) {
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-center gap-2">
            {links.map((link, index) => (
                <Link
                    key={`${link.label}-${index}`}
                    href={link.url ?? '#'}
                    preserveScroll
                    className={[
                        'rounded-lg border px-3 py-2 text-sm font-semibold',
                        link.active
                            ? 'border-violet-600 bg-violet-600 text-white'
                            : 'border-slate-200 bg-white text-slate-600',
                        !link.url
                            ? 'pointer-events-none opacity-40'
                            : '',
                    ].join(' ')}
                    dangerouslySetInnerHTML={{
                        __html: link.label,
                    }}
                />
            ))}
        </div>
    );
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(
        value
    ).toLocaleDateString(
        'en-IN',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }
    );
}
