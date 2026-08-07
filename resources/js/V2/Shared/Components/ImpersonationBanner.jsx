import {
    router,
    usePage,
} from '@inertiajs/react';

export default function ImpersonationBanner() {
    const {
        auth = {},
        impersonation = {},
    } = usePage().props;

    if (!impersonation?.active) {
        return null;
    }

    const stopImpersonation = () => {
        const confirmed = window.confirm(
            'Return to the Super Admin account?'
        );

        if (!confirmed) {
            return;
        }

        router.post(
            '/v2/impersonation/stop',
            {},
            {
                preserveScroll: false,
            }
        );
    };

    return (
        <div className="sticky top-0 z-[100] border-b border-amber-500 bg-amber-400 px-4 py-3 text-amber-950 shadow-md">
            <div className="mx-auto flex w-full flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 items-center justify-center rounded-full bg-amber-950 text-lg text-white">
                        ⚠
                    </span>

                    <div>
                        <div className="text-sm font-bold">
                            Impersonation Mode Active
                        </div>

                        <div className="text-xs font-medium">
                            You are viewing the panel as{' '}
                            <strong>
                                {auth?.user?.name ??
                                    'another user'}
                            </strong>

                            {auth?.user?.role
                                ? ` (${String(
                                      auth.user.role
                                  ).replaceAll(
                                      '_',
                                      ' '
                                  )})`
                                : ''}
                            .
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    onClick={stopImpersonation}
                    className="rounded-xl bg-slate-950 px-5 py-2.5 text-xs font-bold text-white transition hover:bg-slate-800"
                >
                    Return to Super Admin
                </button>
            </div>
        </div>
    );
}
