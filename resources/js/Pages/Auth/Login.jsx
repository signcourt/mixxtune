import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({
    status,
    canResetPassword,
}) {
    const [showPassword, setShowPassword] =
        useState(false);

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
        <GuestLayout
            title="Welcome back"
            subtitle="Use your registered email and password. Your account role will automatically open the correct panel."
        >
            <Head title="Login — Mixx Tune" />

            {status && (
                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    {status}
                </div>
            )}

            <form
                onSubmit={submit}
                className="space-y-5"
            >
                <div>
                    <label
                        htmlFor="login"
                        className="mb-2 block text-sm font-semibold text-slate-700"
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
                        placeholder="Email address or username"
                        onChange={(event) =>
                            setData(
                                'login',
                                event.target.value
                            )
                        }
                        className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
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
                            className="text-sm font-semibold text-slate-700"
                        >
                            Password
                        </label>

                        {canResetPassword && (
                            <Link
                                href={route(
                                    'password.request'
                                )}
                                className="text-xs font-semibold text-blue-600 transition hover:text-blue-700"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>

                    <div className="relative">
                        <input
                            id="password"
                            type={
                                showPassword
                                    ? 'text'
                                    : 'password'
                            }
                            name="password"
                            value={data.password}
                            autoComplete="current-password"
                            required
                            placeholder="Enter your password"
                            onChange={(event) =>
                                setData(
                                    'password',
                                    event.target.value
                                )
                            }
                            className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 pr-20 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10"
                        />

                        <button
                            type="button"
                            onClick={() =>
                                setShowPassword(
                                    (value) => !value
                                )
                            }
                            className="absolute inset-y-0 right-0 px-4 text-xs font-bold text-slate-500 hover:text-blue-600"
                        >
                            {showPassword
                                ? 'Hide'
                                : 'Show'}
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
                            setData(
                                'remember',
                                event.target.checked
                            )
                        }
                    />

                    <span className="text-sm text-slate-600">
                        Keep me signed in
                    </span>
                </label>

                <button
                    type="submit"
                    disabled={processing}
                    className="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {processing
                        ? 'Signing in...'
                        : 'Sign in to Mixx Tune'}
                </button>
            </form>

            <div className="mt-7 border-t border-slate-100 pt-5 text-center">
                <p className="text-xs leading-5 text-slate-400">
                    Artist, Label, Admin and Super Admin
                    accounts use the same secure login.
                </p>
            </div>
        </GuestLayout>
    );
}
