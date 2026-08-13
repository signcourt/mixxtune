import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import { Head } from '@inertiajs/react';
import {
    BadgeCheck,
    Landmark,
    MapPin,
    ReceiptText,
    ShieldCheck,
} from 'lucide-react';

function DisplayField({ label, value, mono = false, wide = false }) {
    return (
        <div
            className={[
                'rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3',
                wide ? 'md:col-span-2' : '',
            ].join(' ')}
        >
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                {label}
            </p>

            <p
                className={[
                    'mt-1 break-words text-sm font-semibold text-slate-800',
                    mono ? 'font-mono' : '',
                ].join(' ')}
            >
                {value || 'Not available'}
            </p>
        </div>
    );
}

function Section({ icon: Icon, title, description, children }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
                    <Icon className="h-5 w-5" />
                </div>

                <div>
                    <h2 className="text-base font-semibold text-slate-900">
                        {title}
                    </h2>

                    {description && (
                        <p className="mt-0.5 text-xs text-slate-500">
                            {description}
                        </p>
                    )}
                </div>
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-2">
                {children}
            </div>
        </section>
    );
}

function statusClasses(status) {
    switch (status) {
        case 'verified':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700';

        case 'rejected':
            return 'border-rose-200 bg-rose-50 text-rose-700';

        case 'submitted':
            return 'border-amber-200 bg-amber-50 text-amber-700';

        default:
            return 'border-slate-200 bg-slate-50 text-slate-600';
    }
}

export default function Edit({ role, profile }) {
    const status = profile?.kyc_status || 'pending';

    const fullAddress = [
        profile?.address_line_1,
        profile?.address_line_2,
        profile?.city,
        profile?.state,
        profile?.postal_code,
        profile?.country_code,
    ]
        .filter(Boolean)
        .join(', ');

    return (
        <PanelLayout
            role={role}
            title="KYC & Payout Profile"
            subtitle="Your account, tax and payout information"
        >
            <Head title="KYC & Payout Profile" />

            <div className="mx-auto max-w-6xl space-y-4">
                <section className="rounded-2xl border border-violet-200 bg-gradient-to-r from-violet-50 to-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-3">
                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                                <ShieldCheck className="h-5 w-5" />
                            </div>

                            <div>
                                <h1 className="text-lg font-semibold text-slate-900">
                                    Account Information
                                </h1>

                                <p className="mt-1 max-w-2xl text-sm text-slate-500">
                                    These details are managed by Mixx Tune administration
                                    and cannot be changed from this account.
                                </p>
                            </div>
                        </div>

                        <span
                            className={[
                                'inline-flex w-fit items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold capitalize',
                                statusClasses(status),
                            ].join(' ')}
                        >
                            <BadgeCheck className="h-4 w-4" />
                            {status}
                        </span>
                    </div>
                </section>

                {!profile ? (
                    <section className="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                        <ShieldCheck className="mx-auto h-9 w-9 text-slate-300" />

                        <h2 className="mt-3 text-base font-semibold text-slate-900">
                            Profile details not available
                        </h2>

                        <p className="mx-auto mt-1 max-w-xl text-sm text-slate-500">
                            Your KYC and payout information has not yet been
                            configured by the administrator.
                        </p>
                    </section>
                ) : (
                    <>
                        <div className="grid gap-4 xl:grid-cols-2">
                            <Section
                                icon={Landmark}
                                title="Bank & Payout Details"
                                description="Royalty settlement account"
                            >
                                <DisplayField
                                    label="Account Holder"
                                    value={profile.account_holder_name}
                                />

                                <DisplayField
                                    label="Bank Account"
                                    value={profile.masked_bank_account}
                                    mono
                                />

                                <DisplayField
                                    label="Bank Name"
                                    value={profile.bank_name}
                                />

                                <DisplayField
                                    label="IFSC Code"
                                    value={profile.ifsc_code}
                                    mono
                                />

                                <DisplayField
                                    label="Branch"
                                    value={profile.branch_name}
                                />

                                <DisplayField
                                    label="UPI ID"
                                    value={profile.upi_id}
                                />
                            </Section>

                            <Section
                                icon={ReceiptText}
                                title="Tax Information"
                                description="Tax identity registered with your account"
                            >
                                <DisplayField
                                    label="PAN"
                                    value={profile.masked_pan}
                                    mono
                                />

                                <DisplayField
                                    label="GSTIN"
                                    value={profile.gst_number}
                                    mono
                                />

                                <DisplayField
                                    label="KYC Status"
                                    value={status}
                                />

                                <DisplayField
                                    label="Verified At"
                                    value={profile.verified_at}
                                />
                            </Section>
                        </div>

                        <Section
                            icon={MapPin}
                            title="Registered Address"
                            description="Address attached to your payout profile"
                        >
                            <DisplayField
                                label="Address"
                                value={fullAddress}
                                wide
                            />
                        </Section>
                    </>
                )}

                <div className="flex items-center gap-2 px-1 text-xs text-slate-400">
                    <ShieldCheck className="h-4 w-4" />
                    Payout and KYC information can only be changed by Super Admin.
                </div>
            </div>
        </PanelLayout>
    );
}
