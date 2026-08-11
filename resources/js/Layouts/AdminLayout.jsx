import { Link, router, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";

const menuGroups = [
    {
        title: "MAIN",
        items: [{ label: "Dashboard", icon: "⌂", href: "/dashboard" }],
    },
    {
        title: "CATALOGUE",
        items: [
            { label: "Artists", icon: "◉", href: "/artists" },
            { label: "Labels", icon: "▣", href: "/labels" },
            { label: "Releases", icon: "♫", href: "/releases" },
            { label: "Release Reviews", icon: "✓", href: "/release-reviews" },
            {
                label: "Delivery Status",
                icon: "↗",
                href: "/delivery-status",
            },
            { label: "Catalogue", icon: "▤", href: "/catalogue" },
            { label: "ISRC & UPC", icon: "#", href: "/isrc-upc" },
        ],
    },
    {
        title: "FINANCE",
        items: [
            { label: "Royalties", icon: "₹", href: "/admin/royalties" },
            { label: "Wallet", icon: "▰", href: "/admin/wallet" },
            { label: "Withdrawals", icon: "↗", href: "/admin/withdrawals" },
            { label: "Reports", icon: "▥", href: "/admin/reports" },
            { label: "Invoices", icon: "□", href: "/v2/admin/invoices" },
        ],
    },
    {
        title: "MANAGEMENT",
        items: [
            { label: "KYC & Profiles", icon: "✓", href: "/v2/admin/kyc" },
            { label: "Support Tickets", icon: "?", href: "/admin/support" },
            { label: "Settings", icon: "⚙", href: "/admin/settings" },
            {
                label: "DSP Management",
                icon: "◈",
                href: "/settings/distribution-stores",
            },
        ],
    },
];

export default function AdminLayout({ children, title = "Dashboard" }) {
    const { auth } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const profileRef = useRef(null);

    const user = auth?.user || {};

    const role = user.role || "admin";

    const isSuperAdmin = role === "super_admin";

    const accountName =
        user.username ||
        user.name ||
        (isSuperAdmin ? "Super Admin" : "Administrator");

    const accountSecondary = user.client_id || user.email || "";

    const accountInitial = accountName.charAt(0).toUpperCase();

    const settingsHref = isSuperAdmin
        ? "/super-admin/settings"
        : "/admin/settings";

    useEffect(() => {
        const outside = (event) => {
            if (
                profileRef.current &&
                !profileRef.current.contains(event.target)
            ) {
                setProfileOpen(false);
            }
        };

        const escape = (event) => {
            if (event.key === "Escape") {
                setProfileOpen(false);
            }
        };

        document.addEventListener("mousedown", outside);

        document.addEventListener("keydown", escape);

        return () => {
            document.removeEventListener("mousedown", outside);

            document.removeEventListener("keydown", escape);
        };
    }, []);

    const logout = () => {
        setProfileOpen(false);
        router.post("/logout");
    };

    return (
        <div className="min-h-screen bg-[#f4f6fa] text-slate-900">
            {sidebarOpen && (
                <button
                    type="button"
                    aria-label="Close sidebar"
                    onClick={() => setSidebarOpen(false)}
                    className="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"
                />
            )}

            <aside
                className={`fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-[#0d1526] text-white transition-transform duration-300 ${
                    sidebarOpen ? "translate-x-0" : "-translate-x-full"
                } lg:translate-x-0`}
            >
                <div className="flex h-20 items-center border-b border-white/10 px-7">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-lg font-black text-[#0d1526]">
                        MT
                    </div>

                    <div className="ml-3">
                        <div className="text-lg font-bold tracking-wide">
                            Mixx Tune
                        </div>
                        <div className="text-xs text-slate-400">
                            Admin Portal
                        </div>
                    </div>
                </div>

                <nav className="flex-1 overflow-y-auto px-4 py-6">
                    {menuGroups.map((group) => (
                        <div key={group.title} className="mb-7">
                            <p className="mb-2 px-3 text-[10px] font-semibold tracking-[0.18em] text-slate-500">
                                {group.title}
                            </p>

                            <div className="space-y-1">
                                {group.items.map((item) => {
                                    const active =
                                        window.location.pathname === item.href;

                                    return (
                                        <Link
                                            key={item.label}
                                            href={item.href}
                                            onClick={() =>
                                                setSidebarOpen(false)
                                            }
                                            className={`flex items-center rounded-xl px-3 py-3 text-sm font-medium transition ${
                                                active
                                                    ? "bg-white text-[#0d1526] shadow-lg"
                                                    : "text-slate-300 hover:bg-white/10 hover:text-white"
                                            }`}
                                        >
                                            <span
                                                className={`mr-3 flex h-8 w-8 items-center justify-center rounded-lg text-base ${
                                                    active
                                                        ? "bg-[#0d1526] text-white"
                                                        : "bg-white/5"
                                                }`}
                                            >
                                                {item.icon}
                                            </span>

                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </nav>

                <div className="border-t border-white/10 p-4">
                    <button
                        type="button"
                        onClick={logout}
                        className="flex w-full items-center rounded-xl px-3 py-3 text-sm font-medium text-slate-300 transition hover:bg-red-500/15 hover:text-red-300"
                    >
                        <span className="mr-3 flex h-8 w-8 items-center justify-center rounded-lg bg-white/5">
                            ↪
                        </span>
                        Logout
                    </button>
                </div>
            </aside>

            <div className="lg:pl-72">
                <header className="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-slate-200 bg-white/95 px-5 backdrop-blur lg:px-8">
                    <div className="flex items-center">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(true)}
                            className="mr-4 rounded-lg border border-slate-200 px-3 py-2 lg:hidden"
                        >
                            ☰
                        </button>

                        <div>
                            <h1 className="text-xl font-bold text-slate-900">
                                {title}
                            </h1>
                            <p className="text-xs text-slate-500">
                                Manage your distribution platform
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="hidden items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 md:flex">
                            <span className="mr-2 text-slate-400">⌕</span>
                            <input
                                type="search"
                                placeholder="Search..."
                                className="w-44 border-0 bg-transparent p-0 text-sm outline-none ring-0 focus:ring-0"
                            />
                        </div>

                        <button
                            type="button"
                            className="relative flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white"
                        >
                            ♢
                            <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500" />
                        </button>

                        <div ref={profileRef} className="relative">
                            <button
                                type="button"
                                onClick={() => setProfileOpen(!profileOpen)}
                                className="flex items-center rounded-xl border border-slate-200 bg-white p-1.5 pr-3 transition hover:bg-slate-50"
                            >
                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-[#0d1526] text-sm font-bold text-white">
                                    {accountInitial}
                                </div>

                                <div className="ml-2 hidden text-left sm:block">
                                    <p className="max-w-36 truncate text-sm font-semibold text-slate-800">
                                        {accountName}
                                    </p>

                                    <p className="max-w-40 truncate text-[11px] text-slate-500">
                                        {accountSecondary}
                                    </p>
                                </div>

                                <svg
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    className={`ml-2 hidden h-4 w-4 text-slate-500 transition-transform sm:block ${
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
                                <div className="absolute right-0 z-50 mt-3 w-72 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
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
                                                    {accountSecondary}
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

                <main className="p-5 lg:p-8">{children}</main>
            </div>
        </div>
    );
}
