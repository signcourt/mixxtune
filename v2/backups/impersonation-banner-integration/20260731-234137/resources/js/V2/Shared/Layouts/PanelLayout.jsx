import {
    usePage,
} from '@inertiajs/react';

import {
    useState,
} from 'react';

import Sidebar from '@/V2/Shared/Navigation/Sidebar';

export default function PanelLayout({
    role = null,
    permissions = null,
    title = '',
    subtitle = '',
    children,
}) {
    const page = usePage();

    const props =
        page.props ?? {};

    const resolvedRole =
        role ??
        props.role ??
        props.auth?.role ??
        props.auth?.user?.role ??
        'artist';

    const resolvedPermissions =
        permissions ??
        props.permissions ??
        props.auth?.permissions ??
        [];

    const notificationCount =
        props.notificationCount ??
        props.unreadNotificationCount ??
        props.auth?.unread_notifications ??
        0;

    const [mobileOpen, setMobileOpen] =
        useState(false);

    return (
        <div className="v2-panel-density min-h-screen bg-slate-100 text-slate-900">
            <Sidebar
                role={resolvedRole}
                permissions={
                    resolvedPermissions
                }
                notificationCount={
                    notificationCount
                }
                logoUrl={
                    props.brand?.logo_url ??
                    null
                }
                brandName={
                    props.brand?.name ??
                    'BACKSTAGE'
                }
                brandSubtitle={
                    props.brand?.subtitle ??
                    'MIXX TUNE'
                }
                mobileOpen={mobileOpen}
                onMobileClose={() =>
                    setMobileOpen(false)
                }
            />

            <div className="min-h-screen transition-all duration-300 lg:pl-[272px]">
                <header className="sticky top-0 z-30 flex min-h-20 items-center justify-between border-b border-slate-200 bg-white/95 px-5 backdrop-blur lg:px-7">
                    <div className="flex items-center gap-4">
                        <button
                            type="button"
                            onClick={() =>
                                setMobileOpen(true)
                            }
                            className="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-xl text-slate-700 shadow-sm lg:hidden"
                        >
                            ☰
                        </button>

                        <div>
                            {title && (
                                <h1 className="text-lg font-semibold text-slate-900">
                                    {title}
                                </h1>
                            )}

                            {subtitle && (
                                <p className="mt-0.5 text-sm text-slate-500">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="hidden text-right sm:block">
                            <div className="text-sm font-semibold text-slate-900">
                                {props.auth?.user
                                    ?.name ??
                                    'Account'}
                            </div>

                            <div className="text-xs text-slate-500">
                                {props.auth?.user
                                    ?.email ?? ''}
                            </div>
                        </div>

                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 font-bold text-white">
                            {(
                                props.auth?.user
                                    ?.name ??
                                'A'
                            )
                                .charAt(0)
                                .toUpperCase()}
                        </div>
                    </div>
                </header>

                <main className="p-5 lg:p-7">
                    {children}
                </main>
            </div>
        </div>
    );
}
