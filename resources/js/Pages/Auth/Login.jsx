import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({
    status,
    canResetPassword,
}) {
    const { brand = {} } = usePage().props;
    const brandName = brand?.name || "MIXX TUNE";

    const [showPassword, setShowPassword] = useState(false);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        login: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title={`Login — ${brandName}`} />

            <div className="mb-7 text-center">
                <h1 className="text-2xl font-bold tracking-tight text-slate-950">
                    Login
                </h1>
            </div>

            {status && (
                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label
                        htmlFor="login"
                        className="mb-2 block text-sm font-medium text-slate-700"
                    >
                        Email or username
                    </label>

                    <input
                        id="login"
                        type="text"
                        name="login"
                        value={data.login}
                        autoComplete="username"
                        autoFocus
                        required
                        placeholder="Enter email or username"
                        onChange={(event) =>
                            setData('login', event.target.value)
                        }
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"
                    />

                    <InputError
                        message={errors.login}
                        className="mt-2"
                    />
                </div>

                <div>
                    <div className="mb-2 flex items-center justify-between">
                        <label
                            htmlFor="password"
                            className="text-sm font-medium text-slate-700"
                        >
                            Password
                        </label>

                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-xs font-medium text-blue-600 hover:text-blue-700"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>

                    <div className="relative">
                        <input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            autoComplete="current-password"
                            required
                            placeholder="Enter password"
                            onChange={(event) =>
                                setData('password', event.target.value)
                            }
                            className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 pr-16 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"
                        />

                        <button
                            type="button"
                            onClick={() =>
                                setShowPassword((value) => !value)
                            }
                            className="absolute inset-y-0 right-0 px-4 text-xs font-medium text-slate-500 hover:text-blue-600"
                        >
                            {showPassword ? 'Hide' : 'Show'}
                        </button>
                    </div>

                    <InputError
                        message={errors.password}
                        className="mt-2"
                    />
                </div>

                <label className="flex cursor-pointer items-center gap-3">
                    <Checkbox
                        name="remember"
                        checked={data.remember}
                        onChange={(event) =>
                            setData('remember', event.target.checked)
                        }
                    />

                    <span className="text-sm text-slate-600">
                        Remember me
                    </span>
                </label>

                <button
                    type="submit"
                    disabled={processing}
                    className="flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {processing ? 'Signing in...' : 'Login'}
                </button>
            </form>
        </GuestLayout>
    );
}
