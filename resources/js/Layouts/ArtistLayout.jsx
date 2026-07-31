import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const menuGroups = [
    {
        title: 'MAIN',
        items: [
            { label: 'Dashboard', icon: '⌂', href: '/dashboard' },
        ],
    },
    {
        title: 'MUSIC',
        items: [
            { label: 'Create Release', icon: '+', href: '/releases/create' },
            { label: 'My Releases', icon: '♫', href: '/releases' },
            { label: 'Catalogue', icon: '▤', href: '/catalogue' },
        ],
    },
    {
        title: 'FINANCE',
        items: [
            { label: 'Royalties', icon: '₹', href: '/royalties' },
            { label: 'Wallet', icon: '▰', href: '/wallet' },
            { label: 'Withdrawals', icon: '↗', href: '/withdrawals' },
            { label: 'Statements', icon: '▥', href: '/statements' },
            { label: 'Invoices', icon: '□', href: '/invoices' },
        ],
    },
    {
        title: 'ACCOUNT',
        items: [
            { label: 'Profile & KYC', icon: '◉', href: '/profile' },
            { label: 'Support', icon: '?', href: '/support' },
            { label: 'Settings', icon: '⚙', href: '/settings' },
        ],
    },
];

export default function ArtistLayout({
    children,
    title = 'Dashboard',
    subtitle = 'Manage your music distribution account',
}) {
    const { auth } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const currentPath = window.location.pathname;

    const logout = () => {
        router.post('/logout');
    };

    return (
        <div className="min-h-screen bg-[#f5f7fb] text-slate-900">
            {sidebarOpen && (
                <button
                    type="button"
                    aria-label="Close sidebar"
                    onClick={() => setSidebarOpen(false)}
                    className="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"
                />
            )}

            <aside
                className={`fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white transition-transform duration-300 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                } lg:translate-x-0`}
            >
                <div className="flex h-20 items-center border-b border-slate-200 px-6">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#0d1526] text-sm font-black text-white">
                        MT
                    </div>

                    <div className="ml-3">
                        <div className="text-lg font-bold tracking-wide text-slate-900">
                            Mixx Tune
                        </div>
                        <div className="text-xs text-slate-500">
                            Artist Portal
                        </div>
                    </div>
                </div>

                <nav className="flex-1 overflow-y-auto px-4 py-6">
                    {menuGroups.map((group) => (
                        <div key={group.title} className="mb-7">
                            <p className="mb-2 px-3 text-[10px] font-semibold tracking-[0.18em] text-slate-400">
                                {group.title}
                            </p>

                            <div className="space-y-1">
                                {group.items.map((item) => {
                                    const active =
                                        currentPath === item.href ||
                                        currentPath.startsWith(
                                            `${item.href}/`
                                        );

                                    return (
                                        <Link
                                            key={item.label}
                                            href={item.href}
                                            onClick={() =>
                                                setSidebarOpen(false)
                                            }
                                            className={`flex items-center rounded-xl px-3 py-3 text-sm font-medium transition ${
                                                active
                                                    ? 'bg-[#0d1526] text-white shadow-sm'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                            }`}
                                        >
                                            <span
                                                className={`mr-3 flex h-8 w-8 items-center justify-center rounded-lg text-base ${
                                                    active
                                                        ? 'bg-white/10'
                                                        : 'bg-slate-100'
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

                <div className="border-t border-slate-200 p-4">
                    <button
                        type="button"
                        onClick={logout}
                        className="flex w-full items-center rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-red-50 hover:text-red-600"
                    >
                        <span className="mr-3 flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100">
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
                                {subtitle}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            className="relative flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white"
                        >
                            ♢
                            <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500" />
                        </button>

                        <div className="flex items-center rounded-xl border border-slate-200 bg-white p-1.5 pr-3">
                            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-[#0d1526] text-sm font-bold text-white">
                                {auth?.user?.name
                                    ?.charAt(0)
                                    ?.toUpperCase() || 'A'}
                            </div>

                            <div className="ml-2 hidden sm:block">
                                <p className="max-w-36 truncate text-sm font-semibold text-slate-800">
                                    {auth?.user?.name || 'Artist'}
                                </p>
                                <p className="max-w-36 truncate text-[11px] text-slate-500">
                                    {auth?.user?.email || ''}
                                </p>
                            </div>
                        </div>
                    </div>
                </header>

                <main className="p-5 lg:p-8">{children}</main>
            </div>
        </div>
    );
}
