import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ArtistSetPassword({ artist }) {
    const { data, setData, post, processing, errors } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post('/set-password');
    };

    return (
        <GuestLayout>
            <Head title="Create Password" />

            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-slate-900">
                    Create Your Password
                </h1>

                <p className="mt-2 text-sm text-slate-500">
                    Your email has been verified successfully.
                </p>
            </div>

            <div className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p className="text-sm font-semibold text-emerald-800">
                    {artist?.name}
                </p>

                <p className="mt-1 text-sm text-emerald-700">
                    {artist?.email}
                </p>
            </div>

            <form onSubmit={submit}>
                <div>
                    <label className="text-sm font-semibold text-slate-700">
                        New Password
                    </label>

                    <input
                        type="password"
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-slate-400 focus:ring-0"
                        placeholder="Minimum 8 characters"
                        autoComplete="new-password"
                        autoFocus
                    />

                    {errors.password && (
                        <p className="mt-2 text-xs text-red-600">
                            {errors.password}
                        </p>
                    )}
                </div>

                <div className="mt-5">
                    <label className="text-sm font-semibold text-slate-700">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        value={data.password_confirmation}
                        onChange={(event) =>
                            setData(
                                'password_confirmation',
                                event.target.value
                            )
                        }
                        className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-slate-400 focus:ring-0"
                        placeholder="Repeat password"
                        autoComplete="new-password"
                    />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="mt-6 w-full rounded-xl bg-[#0d1526] px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {processing
                        ? 'Creating Account...'
                        : 'Create My Account'}
                </button>
            </form>
        </GuestLayout>
    );
}
