import { Head, Link, router, usePage } from "@inertiajs/react";

import { useEffect, useRef, useState } from "react";

import Sidebar from "@/V2/Shared/Navigation/Sidebar";
import ImpersonationBanner from "@/V2/Shared/Components/ImpersonationBanner";

export default function PanelLayout({
    role = null,
    permissions = null,
    title = "",
    subtitle = "",
    children,
}) {
    const page = usePage();

    const props = page.props ?? {};

    const user = props.auth?.user ?? {};

    const resolvedRole =
        role ?? props.role ?? props.auth?.role ?? user.role ?? "artist";

    const resolvedPermissions =
        permissions ?? props.permissions ?? props.auth?.permissions ?? [];

    const notificationCount =
        props.notificationCount ??
        props.unreadNotificationCount ??
        props.auth?.unread_notifications ??
        0;

    const headerNotifications = props.headerNotifications ?? [];

    const [mobileOpen, setMobileOpen] = useState(false);

    const [profileOpen, setProfileOpen] = useState(false);

    const [notificationOpen, setNotificationOpen] = useState(false);

    const profileRef = useRef(null);
    const notificationRef = useRef(null);

    useEffect(() => {
        const handleOutsideClick = (event) => {
            if (
                profileRef.current &&
                !profileRef.current.contains(event.target)
            ) {
                setProfileOpen(false);
            }

            if (
                notificationRef.current &&
                !notificationRef.current.contains(event.target)
            ) {
                setNotificationOpen(false);
            }
        };

        const handleEscape = (event) => {
            if (event.key === "Escape") {
                setProfileOpen(false);
                setNotificationOpen(false);
            }
        };

        document.addEventListener("mousedown", handleOutsideClick);

        document.addEventListener("keydown", handleEscape);

        return () => {
            document.removeEventListener("mousedown", handleOutsideClick);

            document.removeEventListener("keydown", handleEscape);
        };
    }, []);

    const logout = () => {
        setProfileOpen(false);

        router.post("/logout");
    };

    const accountName = user.username || user.name || "Account";

    const settingsHref =
        resolvedRole === "super_admin"
            ? "/super-admin/settings"
            : resolvedRole === "admin"
              ? "/admin/settings"
              : resolvedRole === "label"
                ? "/label/settings"
                : "/artist/settings";

    const accountClientId =
        user.client_id ||
        (["label", "artist"].includes(user.role)
            ? "Client ID pending"
            : user.email || "");

    const accountInitial = (user.username || user.name || "A")
        .charAt(0)
        .toUpperCase();

    const isLabel = resolvedRole === "label";
    const isArtist = resolvedRole === "artist";
    const isOwnerAccount = isLabel || isArtist;

    const kycHref = isArtist
        ? "/artist/kyc"
        : "/v2/kyc-profile";

    return (
        <>
            {props.brand?.favicon_url && (
                <Head>
                    <link
                        rel="icon"
                        href={props.brand.favicon_url}
                    />
                </Head>
            )}

            <ImpersonationBanner />

            <div className="v2-panel-density min-h-screen bg-slate-100 text-slate-900">
                <Sidebar
                    role={resolvedRole}
                    permissions={resolvedPermissions}
                    notificationCount={notificationCount}
                    logoUrl={props.brand?.logo_url ?? null}
                    brandName={props.brand?.name ?? "MIXX TUNE"}
                    brandSubtitle={props.brand?.subtitle ?? "BACKSTAGE"}
                    mobileOpen={mobileOpen}
                    onMobileClose={() => setMobileOpen(false)}
                />

                <div className="min-h-screen transition-all duration-300 lg:pl-[272px]">
                    <header className="sticky top-0 z-30 flex min-h-20 items-center justify-between border-b border-slate-200 bg-white/95 px-5 backdrop-blur lg:px-7">
                        <div className="flex items-center gap-4">
                            <button
                                type="button"
                                onClick={() => setMobileOpen(true)}
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

                        <div className="flex items-center gap-2">
                            <div ref={notificationRef} className="relative">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setNotificationOpen((open) => !open);
                                        setProfileOpen(false);
                                    }}
                                    className="relative flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-100 hover:text-slate-950"
                                    aria-label="Notifications"
                                    aria-expanded={notificationOpen}
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="1.8"
                                        className="h-5 w-5"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9a6 6 0 00-12 0v.75a8.967 8.967 0 01-2.312 6.022 23.848 23.848 0 005.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
                                        />
                                    </svg>

                                    {notificationCount > 0 && (
                                        <span className="absolute -right-1.5 -top-1.5 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white">
                                            {notificationCount > 99
                                                ? "99+"
                                                : notificationCount}
                                        </span>
                                    )}
                                </button>

                                {notificationOpen && (
                                    <div className="absolute right-0 mt-3 w-[360px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                                        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                                            <div>
                                                <div className="font-semibold text-slate-900">
                                                    Notifications
                                                </div>

                                                <div className="mt-0.5 text-xs text-slate-500">
                                                    {notificationCount} unread
                                                </div>
                                            </div>

                                            {notificationCount > 0 && (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        router.patch(
                                                            "/v2/notifications/read-all",
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                                onSuccess: () =>
                                                                    setNotificationOpen(
                                                                        false,
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                    className="text-xs font-semibold text-violet-600 transition hover:text-violet-800"
                                                >
                                                    Mark all read
                                                </button>
                                            )}
                                        </div>

                                        <div className="max-h-[360px] overflow-y-auto">
                                            {headerNotifications.length ===
                                            0 ? (
                                                <div className="px-5 py-10 text-center">
                                                    <div className="text-sm font-semibold text-slate-700">
                                                        No notifications
                                                    </div>

                                                    <div className="mt-1 text-xs text-slate-400">
                                                        You're all caught up.
                                                    </div>
                                                </div>
                                            ) : (
                                                headerNotifications.map(
                                                    (notification) => (
                                                        <button
                                                            key={
                                                                notification.id
                                                            }
                                                            type="button"
                                                            onClick={() => {
                                                                setNotificationOpen(
                                                                    false,
                                                                );

                                                                router.patch(
                                                                    `/v2/notifications/${notification.id}/read`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                        onSuccess:
                                                                            () => {
                                                                                if (
                                                                                    notification.action_url
                                                                                ) {
                                                                                    router.visit(
                                                                                        notification.action_url,
                                                                                    );
                                                                                }
                                                                            },
                                                                    },
                                                                );
                                                            }}
                                                            className={[
                                                                "block w-full border-b border-slate-100 px-5 py-4 text-left transition last:border-b-0 hover:bg-slate-50",
                                                                notification.read_at
                                                                    ? "bg-white"
                                                                    : "bg-violet-50/60",
                                                            ].join(" ")}
                                                        >
                                                            <div className="flex gap-3">
                                                                <div
                                                                    className={[
                                                                        "mt-1 h-2.5 w-2.5 shrink-0 rounded-full",
                                                                        notification.read_at
                                                                            ? "bg-slate-300"
                                                                            : "bg-violet-500",
                                                                    ].join(" ")}
                                                                />

                                                                <div className="min-w-0 flex-1">
                                                                    <div className="truncate text-sm font-semibold text-slate-900">
                                                                        {
                                                                            notification.title
                                                                        }
                                                                    </div>

                                                                    <div className="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">
                                                                        {
                                                                            notification.message
                                                                        }
                                                                    </div>

                                                                    <div className="mt-2 text-[11px] text-slate-400">
                                                                        {
                                                                            notification.created_at
                                                                        }
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    ),
                                                )
                                            )}
                                        </div>

                                        <Link
                                            href="/v2/notifications"
                                            onClick={() =>
                                                setNotificationOpen(false)
                                            }
                                            className="block border-t border-slate-100 bg-slate-50 px-5 py-3.5 text-center text-sm font-semibold text-violet-700 transition hover:bg-slate-100"
                                        >
                                            View All Notifications
                                        </Link>
                                    </div>
                                )}
                            </div>

                            <div ref={profileRef} className="relative">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setProfileOpen((open) => !open)
                                    }
                                    className="flex items-center gap-3 rounded-2xl px-2 py-1.5 transition hover:bg-slate-100"
                                    aria-expanded={profileOpen}
                                >
                                    <div className="hidden text-right sm:block">
                                        <div className="text-sm font-semibold text-slate-900">
                                            {accountName}
                                        </div>

                                        <div className="text-xs text-slate-500">
                                            {accountClientId}
                                        </div>
                                    </div>

                                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 font-bold text-white shadow-sm">
                                        {accountInitial}
                                    </div>

                                    <svg
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        className={`hidden h-4 w-4 text-slate-500 transition-transform sm:block ${
                                            profileOpen ? "rotate-180" : ""
                                        }`}
                                    >
                                        <path
                                            fillRule="evenodd"
                                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z"
                                            clipRule="evenodd"
                                        />
                                    </svg>
                                </button>

                                {profileOpen && (
                                    <div className="absolute right-0 mt-3 w-72 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                                        <div className="border-b border-slate-100 px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 font-bold text-white">
                                                    {accountInitial}
                                                </div>

                                                <div className="min-w-0">
                                                    <div className="truncate text-sm font-semibold text-slate-900">
                                                        {accountName}
                                                    </div>

                                                    <div className="truncate text-xs text-slate-500">
                                                        {accountClientId}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="p-2">
                                            <Link
                                                href="/profile"
                                                onClick={() =>
                                                    setProfileOpen(false)
                                                }
                                                className="flex w-full items-center rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950"
                                            >
                                                Profile
                                            </Link>

                                            <Link
                                                href={settingsHref}
                                                onClick={() =>
                                                    setProfileOpen(false)
                                                }
                                                className="flex w-full items-center rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950"
                                            >
                                                Settings
                                            </Link>

                                            {isLabel && (
                                                <>
                                                    <Link
                                                        href="/v2/label/artists"
                                                        onClick={() =>
                                                            setProfileOpen(false)
                                                        }
                                                        className="flex w-full items-center rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950"
                                                    >
                                                        Artists
                                                    </Link>

                                                    <Link
                                                        href="/v2/label/user-access"
                                                        onClick={() =>
                                                            setProfileOpen(false)
                                                        }
                                                        className="flex w-full items-center rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950"
                                                    >
                                                        User Access
                                                    </Link>

                                                    <Link
                                                        href="/v2/label/revenue-sharing"
                                                        onClick={() =>
                                                            setProfileOpen(false)
                                                        }
                                                        className="flex w-full items-center rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950"
                                                    >
                                                        Revenue Sharing
                                                    </Link>
                                                </>
                                            )}

                                            {isOwnerAccount && (
                                                <Link
                                                    href={kycHref}
                                                    onClick={() =>
                                                        setProfileOpen(false)
                                                    }
                                                    className="flex w-full items-center rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950"
                                                >
                                                    KYC & Profile
                                                </Link>
                                            )}
                                        </div>

                                        <div className="border-t border-slate-100 p-2">
                                            <button
                                                type="button"
                                                onClick={logout}
                                                className="flex w-full items-center rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-red-600 transition hover:bg-red-50"
                                            >
                                                Logout
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </header>

                    <main className="p-5 lg:p-7">{children}</main>
                    {props.brand?.footer_text && (
                        <footer className="border-t border-slate-200 bg-white px-5 py-4 text-center text-xs text-slate-500 lg:px-7">
                            {props.brand.footer_text}
                        </footer>
                    )}
                </div>
            </div>
        </>
    );
}
