import {
    router,
    usePage,
} from '@inertiajs/react';

export default function ImpersonationBanner() {
    const {
        auth = {},
        impersonation = null,
    } = usePage().props;

    if (!impersonation?.active) {
        return null;
    }

    const stop = () => {
        if (
            !window.confirm(
                'Return to the Super Admin account?'
            )
        ) {
            return;
        }

        router.post(
            '/v2/impersonation/stop'
        );
    };

    return (
        <div className="sticky top-0 z-[100] flex flex-wrap items-center justify-between gap-3 bg-amber-400 px-5 py-3 text-sm text-amber-950 shadow-md">
            <div>
                <strong>
                    Impersonation active:
                </strong>{' '}
                You are viewing the panel as{' '}
                <strong>
                    {auth?.user?.name ??
                        'another user'}
                </strong>
                .
            </div>

            <button
                type="button"
                onClick={stop}
                className="rounded-lg bg-slate-950 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800"
            >
                Return to Super Admin
            </button>
        </div>
    );
}
