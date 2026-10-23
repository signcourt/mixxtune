import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck, Landmark, Lock, MapPin, ReceiptText, ShieldCheck, Unlock } from 'lucide-react';

/*
 * W80 MIXXTUNE_KYC_EDIT_LOCK
 * The owner fills KYC + bank once and sends it for verification. After that
 * it is locked; a change needs a Support request, the signed change form and
 * a Super Admin opening it again for one change.
 */

const STATE_TEXT = {
    first_time: 'Fill your KYC and bank details once, then send them for verification.',
    sent_back: 'Our team sent your KYC back. Correct it and send it again.',
    unlocked: 'Your KYC is open for one change. Make the change and send it for verification.',
    waiting: 'Your KYC is waiting for verification by our team. It is locked until then.',
    verified: 'Your KYC is verified and locked.',
    staff: 'Payout KYC is only for Label and Artist accounts.',
};

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

function DisplayField({ label, value, mono = false, wide = false }) {
    return (
        <div className={`rounded-lg border border-slate-200 bg-slate-50/70 px-3 py-2 ${wide ? 'md:col-span-2' : ''}`}>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-0.5 break-words text-sm font-semibold text-slate-800 ${mono ? 'font-mono' : ''}`}>{value || 'Not added'}</p>
        </div>
    );
}

function Section({ icon: Icon, title, description, children }) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div className="flex items-start gap-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-700">
                    <Icon className="h-[18px] w-[18px]" />
                </div>
                <div>
                    <h2 className="text-[15px] font-bold text-slate-900">{title}</h2>
                    {description && <p className="mt-0.5 text-xs text-slate-500">{description}</p>}
                </div>
            </div>
            <div className="mt-3 grid gap-3 md:grid-cols-2">{children}</div>
        </section>
    );
}

function Input({ label, value, onChange, error, required = false, placeholder = '', hint = null, upper = false, inputMode }) {
    return (
        <label>
            <span className="text-xs font-semibold text-slate-600">
                {label} {required && <span className="text-red-500">*</span>}
            </span>
            <input
                type="text"
                value={value ?? ''}
                inputMode={inputMode}
                placeholder={placeholder}
                onChange={(event) => onChange(upper ? event.target.value.toUpperCase().replace(/\s+/g, '') : event.target.value)}
                className={`mt-1 w-full rounded-lg border bg-white px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100 ${error ? 'border-red-400' : 'border-slate-300'}`}
            />
            {error ? <span className="mt-1 block text-xs text-red-600">{error}</span> : hint ? <span className="mt-1 block text-[11px] text-slate-400">{hint}</span> : null}
        </label>
    );
}

export default function Edit({ role, profile, editState = { can_edit: false, state: 'staff' } }) {
    const { flash = {}, errors: pageErrors = {} } = usePage().props;
    const status = profile?.kyc_status || 'pending';
    const canEdit = Boolean(editState?.can_edit);

    const form = useForm({
        account_holder_name: profile?.account_holder_name ?? '',
        bank_account_number: '',
        ifsc_code: profile?.ifsc_code ?? '',
        bank_name: profile?.bank_name ?? '',
        branch_name: profile?.branch_name ?? '',
        pan_number: '',
        gst_number: profile?.gst_number ?? '',
        address_line_1: profile?.address_line_1 ?? '',
        address_line_2: profile?.address_line_2 ?? '',
        city: profile?.city ?? '',
        state: profile?.state ?? '',
        postal_code: profile?.postal_code ?? '',
        submit: false,
    });

    const save = (submit) => {
        if (submit && !window.confirm('Send your KYC and bank details for verification? After this they are locked until our team checks them.')) {
            return;
        }
        form.transform((d) => ({ ...d, submit }));
        form.post('/kyc-profile', { preserveScroll: true });
    };

    const fullAddress = [profile?.address_line_1, profile?.address_line_2, profile?.city, profile?.state, profile?.postal_code, profile?.country_code].filter(Boolean).join(', ');
    const err = form.errors;

    return (
        <PanelLayout role={role} title="KYC & Payout Profile" subtitle="Your tax, bank and payout details">
            <Head title="KYC & Payout Profile" />

            <div className="mx-auto max-w-5xl space-y-4">
                <section className="rounded-xl border border-violet-200 bg-gradient-to-r from-violet-50 to-white p-4 shadow-sm sm:p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-3">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700">
                                {canEdit ? <Unlock className="h-5 w-5" /> : <Lock className="h-5 w-5" />}
                            </div>
                            <div>
                                <h1 className="text-base font-bold text-slate-900">{canEdit ? 'Your KYC is open' : 'Your KYC is locked'}</h1>
                                <p className="mt-0.5 max-w-2xl text-sm text-slate-600">
                                    {STATE_TEXT[editState?.state] ?? ''}
                                    {editState?.unlock_expires && <> Open until <b>{editState.unlock_expires}</b>.</>}
                                </p>
                            </div>
                        </div>
                        <span className={`inline-flex w-fit items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold capitalize ${statusClasses(status)}`}>
                            <BadgeCheck className="h-4 w-4" />
                            {status}
                        </span>
                    </div>

                    {flash.success && <p className="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">{flash.success}</p>}
                    {(pageErrors.kyc || err.kyc) && <p className="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">{pageErrors.kyc || err.kyc}</p>}
                    {profile?.kyc_notes && (
                        <p className="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">
                            <b>Note from Mixx Tune:</b> {profile.kyc_notes}
                        </p>
                    )}
                </section>

                {canEdit ? (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            save(true);
                        }}
                        className="space-y-4"
                    >
                        <Section icon={ReceiptText} title="Tax" description="As written on your PAN card">
                            <Input
                                label="PAN"
                                value={form.data.pan_number}
                                onChange={(v) => form.setData('pan_number', v)}
                                error={err.pan_number}
                                required={!profile?.has_pan}
                                upper
                                placeholder={profile?.masked_pan ? `Saved: ${profile.masked_pan}` : 'ABCDE1234F'}
                                hint={profile?.has_pan ? 'Leave empty to keep the saved PAN.' : null}
                            />
                            <Input label="GSTIN (if you have one)" value={form.data.gst_number} onChange={(v) => form.setData('gst_number', v)} error={err.gst_number} upper placeholder="15 characters" />
                        </Section>

                        <Section icon={Landmark} title="Bank account" description="Royalty payouts go to this account">
                            <Input label="Account holder name" value={form.data.account_holder_name} onChange={(v) => form.setData('account_holder_name', v)} error={err.account_holder_name} required hint="Exactly as in the bank (should match PAN)." />
                            <Input
                                label="Account number"
                                value={form.data.bank_account_number}
                                onChange={(v) => form.setData('bank_account_number', v.replace(/\s+/g, ''))}
                                error={err.bank_account_number}
                                required={!profile?.has_bank_account}
                                inputMode="numeric"
                                placeholder={profile?.masked_bank_account ? `Saved: ${profile.masked_bank_account}` : ''}
                                hint={profile?.has_bank_account ? 'Leave empty to keep the saved account.' : null}
                            />
                            <Input label="IFSC" value={form.data.ifsc_code} onChange={(v) => form.setData('ifsc_code', v)} error={err.ifsc_code} required upper placeholder="SBIN0001234" />
                            <Input label="Bank name" value={form.data.bank_name} onChange={(v) => form.setData('bank_name', v)} error={err.bank_name} required />
                            <Input label="Branch" value={form.data.branch_name} onChange={(v) => form.setData('branch_name', v)} error={err.branch_name} />
                        </Section>

                        <Section icon={MapPin} title="Address" description="Address for your payout profile">
                            <Input label="Address line 1" value={form.data.address_line_1} onChange={(v) => form.setData('address_line_1', v)} error={err.address_line_1} required />
                            <Input label="Address line 2" value={form.data.address_line_2} onChange={(v) => form.setData('address_line_2', v)} error={err.address_line_2} />
                            <Input label="City" value={form.data.city} onChange={(v) => form.setData('city', v)} error={err.city} required />
                            <Input label="State" value={form.data.state} onChange={(v) => form.setData('state', v)} error={err.state} required />
                            <Input label="PIN code" value={form.data.postal_code} onChange={(v) => form.setData('postal_code', v.replace(/\D+/g, ''))} error={err.postal_code} required inputMode="numeric" />
                        </Section>

                        <div className="sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur">
                            <p className="text-xs text-slate-500">If you change a saved bank account, a code is e-mailed to you and payouts wait 48 hours for safety.</p>
                            <div className="flex gap-2">
                                <button type="button" onClick={() => save(false)} disabled={form.processing} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                                    Save draft
                                </button>
                                <button type="submit" disabled={form.processing} className="rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">
                                    {form.processing ? 'Saving...' : 'Send for verification'}
                                </button>
                            </div>
                        </div>
                    </form>
                ) : (
                    <>
                        {profile ? (
                            <>
                                <div className="grid gap-4 xl:grid-cols-2">
                                    <Section icon={Landmark} title="Bank account" description="Royalty settlement account">
                                        <DisplayField label="Account holder" value={profile.account_holder_name} />
                                        <DisplayField label="Account number" value={profile.masked_bank_account} mono />
                                        <DisplayField label="Bank" value={profile.bank_name} />
                                        <DisplayField label="IFSC" value={profile.ifsc_code} mono />
                                        <DisplayField label="Branch" value={profile.branch_name} />
                                    </Section>
                                    <Section icon={ReceiptText} title="Tax" description="Tax identity on your account">
                                        <DisplayField label="PAN" value={profile.masked_pan} mono />
                                        <DisplayField label="GSTIN" value={profile.gst_number} mono />
                                        <DisplayField label="Verified at" value={profile.verified_at} />
                                    </Section>
                                </div>
                                <Section icon={MapPin} title="Address" description="Address on your payout profile">
                                    <DisplayField label="Address" value={fullAddress} wide />
                                </Section>
                            </>
                        ) : (
                            <section className="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">No payout profile yet.</section>
                        )}

                        {editState?.state === 'verified' && (
                            <section className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-start gap-3 text-sm text-slate-600">
                                    <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-slate-400" />
                                    <span>
                                        Need to change your bank account or PAN? Send us a request. We send you a change form to sign; after we receive it, your KYC opens for one change.
                                    </span>
                                </div>
                                <Link
                                    href={`/support/create?category=kyc`}
                                    className="shrink-0 rounded-lg bg-violet-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-violet-700"
                                >
                                    Request a change
                                </Link>
                            </section>
                        )}
                    </>
                )}
            </div>
        </PanelLayout>
    );
}
