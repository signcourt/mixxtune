import {
    Link,
} from '@inertiajs/react';

import {
    Menu,
    X,
} from 'lucide-react';

import {
    useState,
} from 'react';

const nav = [
    ['Home', '/'],
    ['Distribution', '/distribution'],
    ['Pricing', '/pricing'],
    ['About', '/about'],
    ['Help', '/help'],
    ['Contact', '/contact'],
];

function Logo() {
    return (
        <Link
            href="/"
            className="flex items-center gap-3"
        >
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 via-fuchsia-500 to-orange-400 text-lg font-black text-white shadow-lg shadow-violet-500/20">
                M
            </div>

            <div>
                <div className="text-lg font-black tracking-tight text-slate-950">
                    MIXX TUNE
                </div>

                <div className="text-[9px] font-bold uppercase tracking-[0.28em] text-violet-600">
                    Music Distribution
                </div>
            </div>
        </Link>
    );
}

export default function PublicLayout({
    children,
    title,
    subtitle,
}) {
    const [menuOpen, setMenuOpen] =
        useState(false);

    return (
        <div className="min-h-screen bg-white text-slate-900">
            <header className="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
                <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-6 lg:px-8">
                    <Logo />

                    <nav className="hidden items-center gap-7 lg:flex">
                        {nav.map(([label, href]) => (
                            <Link
                                key={href}
                                href={href}
                                className="text-sm font-semibold text-slate-600 transition hover:text-violet-600"
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>

                    <div className="hidden items-center gap-2 lg:flex">
                        <Link
                            href="/login"
                            className="rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
                        >
                            Log in
                        </Link>

                        <Link
                            href="/register"
                            className="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-black text-white transition hover:bg-violet-700"
                        >
                            Get Started
                        </Link>
                    </div>

                    <button
                        type="button"
                        onClick={() =>
                            setMenuOpen(
                                (value) => !value
                            )
                        }
                        className="rounded-xl border border-slate-200 p-2.5 text-slate-900 lg:hidden"
                    >
                        {menuOpen ? (
                            <X size={21} />
                        ) : (
                            <Menu size={21} />
                        )}
                    </button>
                </div>

                {menuOpen && (
                    <div className="border-t border-slate-200 bg-white px-5 py-5 lg:hidden">
                        <div className="space-y-1">
                            {nav.map(
                                ([label, href]) => (
                                    <Link
                                        key={href}
                                        href={href}
                                        className="block rounded-xl px-4 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        {label}
                                    </Link>
                                )
                            )}
                        </div>

                        <div className="mt-4 grid grid-cols-2 gap-2">
                            <Link
                                href="/login"
                                className="rounded-xl border border-slate-200 px-4 py-3 text-center font-bold text-slate-900"
                            >
                                Log in
                            </Link>

                            <Link
                                href="/register"
                                className="rounded-xl bg-slate-950 px-4 py-3 text-center font-black text-white"
                            >
                                Get Started
                            </Link>
                        </div>
                    </div>
                )}
            </header>

            {(title || subtitle) && (
                <section className="relative overflow-hidden bg-slate-950 py-20 text-white sm:py-28">
                    <div className="absolute -left-20 top-0 h-72 w-72 rounded-full bg-violet-600/30 blur-3xl" />
                    <div className="absolute -right-20 bottom-0 h-72 w-72 rounded-full bg-fuchsia-500/20 blur-3xl" />

                    <div className="relative mx-auto max-w-5xl px-5 text-center sm:px-6 lg:px-8">
                        <div className="mx-auto mb-5 h-1.5 w-16 rounded-full bg-gradient-to-r from-violet-500 via-fuchsia-500 to-orange-400" />

                        <h1 className="text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">
                            {title}
                        </h1>

                        {subtitle && (
                            <p className="mx-auto mt-6 max-w-3xl text-lg leading-8 text-slate-300">
                                {subtitle}
                            </p>
                        )}
                    </div>
                </section>
            )}

            <main>{children}</main>

            <footer className="border-t border-slate-200 bg-slate-950 text-slate-400">
                <div className="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:px-6 md:grid-cols-4 lg:px-8">
                    <div className="md:col-span-2">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 via-fuchsia-500 to-orange-400 font-black text-white">
                                M
                            </div>

                            <div className="font-black text-white">
                                MIXX TUNE
                            </div>
                        </div>

                        <p className="mt-4 max-w-md text-sm leading-7">
                            Music distribution,
                            catalogue management,
                            analytics and royalty
                            operations for artists and
                            labels.
                        </p>
                    </div>

                    <div>
                        <div className="font-black text-white">
                            Platform
                        </div>

                        <div className="mt-4 space-y-3 text-sm">
                            <Link
                                href="/distribution"
                                className="block hover:text-white"
                            >
                                Distribution
                            </Link>

                            <Link
                                href="/pricing"
                                className="block hover:text-white"
                            >
                                Pricing
                            </Link>

                            <Link
                                href="/about"
                                className="block hover:text-white"
                            >
                                About
                            </Link>

                            <Link
                                href="/help"
                                className="block hover:text-white"
                            >
                                Help Center
                            </Link>
                        </div>
                    </div>

                    <div>
                        <div className="font-black text-white">
                            Support
                        </div>

                        <div className="mt-4 space-y-3 text-sm">
                            <Link
                                href="/contact"
                                className="block hover:text-white"
                            >
                                Contact
                            </Link>

                            <a
                                href="mailto:support@mixxtune.com"
                                className="block hover:text-white"
                            >
                                support@mixxtune.com
                            </a>

                            <Link
                                href="/privacy"
                                className="block hover:text-white"
                            >
                                Privacy Policy
                            </Link>

                            <Link
                                href="/terms"
                                className="block hover:text-white"
                            >
                                Terms of Service
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="border-t border-white/10 px-5 py-6 text-center text-xs text-slate-500">
                    © {new Date().getFullYear()}{' '}
                    Mixx Tune. All rights reserved.
                </div>
            </footer>
        </div>
    );
}
