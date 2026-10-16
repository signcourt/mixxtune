import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function LoginOtp({
    maskedEmail,
}) {
    const { brand = {}, flash = {} } = usePage().props;
    const brandName = brand?.name || 'MIXX TUNE';

    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        otp: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('login.otp.verify'));
    };

    const resend = () => {
        post(route('login.otp.resend'), {
            preserveScroll: true,
        });
    };

    return (
        <GuestLayout>
            <Head title={`Verify Login — ${brandName}`} />

            <div className="mb-7 text-center">
                <h1 className="text-2xl font-bold tracking-tight text-slate-950">
                    Verify Login
                </h1>

                <p className="mt-2 text-sm text-slate-600">
                    We sent a 6-digit OTP to
                </p>

                <p className="mt-1 text-sm font-semibold text-slate-900">
                    {maskedEmail}
                </p>
            </div>

            {flash?.status && (
                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    {flash.status}
                </div>
            )}

            <form
                onSubmit={submit}
                className="space-y-5"
            >
                <div>
                    <label
                        htmlFor="otp"
                        className="mb-2 block text-sm font-medium text-slate-700"
                    >
                        Email OTP
                    </label>

                    <input
                        id="otp"
                        type="text"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={6}
                        value={data.otp}
                        autoFocus
                        required
                        placeholder="Enter 6-digit OTP"
                        onChange={(event) =>
                            setData(
                                'otp',
                                event.target.value
                                    .replace(/\D/g, '')
                                    .slice(0, 6)
                            )
                        }
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-center text-xl font-semibold tracking-[0.35em] text-slate-900 outline-none transition placeholder:text-sm placeholder:tracking-normal placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"
                    />

                    <InputError
                        message={errors.otp}
                        className="mt-2"
                    />
                </div>

                <button
                    type="submit"
                    disabled={
                        processing ||
                        data.otp.length !== 6
                    }
                    className="flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {processing
                        ? 'Verifying...'
                        : 'Verify & Login'}
                </button>
            </form>

            <div className="mt-5 flex items-center justify-between text-sm">
                <Link
                    href={route('login')}
                    className="font-medium text-slate-600 hover:text-blue-600"
                >
                    Back to login
                </Link>

                <button
                    type="button"
                    onClick={resend}
                    disabled={processing}
                    className="font-medium text-blue-600 hover:text-blue-700 disabled:opacity-50"
                >
                    Resend OTP
                </button>
            </div>

            <p className="mt-6 text-center text-xs text-slate-500">
                OTP expires in 5 minutes.
            </p>
        </GuestLayout>
    );
}
