import { Head, useForm } from '@inertiajs/react';

export default function UserSetPassword({
    invitedUser,
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(route(
            'single.invitation.password.store'
        ));
    };

    const roleLabel = {
        artist: 'Artist',
        label: 'Label',
        admin: 'Manager',
    }[invitedUser.role] ?? invitedUser.role;

    return (
        <>
            <Head title="Create Password" />

            <main className="min-h-screen bg-slate-950 px-4 py-12">
                <div className="mx-auto w-full max-w-md">
                    <div className="rounded-3xl border border-white/10 bg-white p-8 shadow-2xl">
                        <div className="mb-8 text-center">
                            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 text-xl font-bold text-white">
                                MT
                            </div>

                            <h1 className="text-2xl font-bold text-slate-900">
                                Welcome to Mixx Tune
                            </h1>

                            <p className="mt-2 text-sm text-slate-500">
                                Create your password to activate your {roleLabel} account.
                            </p>
                        </div>

                        <div className="mb-6 rounded-2xl bg-slate-50 p-4">
                            <p className="font-semibold text-slate-900">
                                {invitedUser.name}
                            </p>

                            <p className="text-sm font-semibold text-blue-600">
                                @{invitedUser.username}
                            </p>

                            <p className="text-sm text-slate-500">
                                {invitedUser.email}
                            </p>

                            <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-blue-600">
                                {roleLabel}
                            </p>
                        </div>

                        <form
                            onSubmit={submit}
                            className="space-y-5"
                        >
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700">
                                    New password
                                </label>

                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={(event) =>
                                        setData(
                                            'password',
                                            event.target.value
                                        )
                                    }
                                    className="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    autoComplete="new-password"
                                    required
                                />

                                {errors.password && (
                                    <p className="mt-2 text-sm text-red-600">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700">
                                    Confirm password
                                </label>

                                <input
                                    type="password"
                                    value={
                                        data.password_confirmation
                                    }
                                    onChange={(event) =>
                                        setData(
                                            'password_confirmation',
                                            event.target.value
                                        )
                                    }
                                    className="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    autoComplete="new-password"
                                    required
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing
                                    ? 'Activating account...'
                                    : 'Activate Account'}
                            </button>
                        </form>

                        <p className="mt-6 text-center text-xs text-slate-400">
                            Your invitation is protected by a secure, expiring link.
                        </p>
                    </div>
                </div>
            </main>
        </>
    );
}
