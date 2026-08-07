import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const stores = [
    'Spotify',
    'Apple Music',
    'YouTube Music',
    'Amazon Music',
    'JioSaavn',
    'Gaana',
    'Instagram',
    'Facebook',
];

const features = [
    {
        number: '01',
        title: 'Worldwide Distribution',
        description:
            'Deliver your music to leading streaming platforms and digital stores across the world.',
    },
    {
        number: '02',
        title: 'Royalty Analytics',
        description:
            'Understand streams, earnings, stores, countries and track performance from one dashboard.',
    },
    {
        number: '03',
        title: 'Fast Release Workflow',
        description:
            'Create releases, upload WAV files, add artwork and submit your music through a guided process.',
    },
    {
        number: '04',
        title: 'Artist & Label Panels',
        description:
            'Dedicated workspaces for independent artists, labels, administrators and internal teams.',
    },
    {
        number: '05',
        title: 'Transparent Wallet',
        description:
            'Review statements, available balance, withdrawals, invoices and payment history clearly.',
    },
    {
        number: '06',
        title: 'Professional Support',
        description:
            'Get structured support for releases, metadata, royalties, deliveries and account operations.',
    },
];

const steps = [
    {
        step: '01',
        title: 'Create your account',
        text: 'Register as an artist or label and complete your profile.',
    },
    {
        step: '02',
        title: 'Upload your release',
        text: 'Add release details, WAV tracks, artwork and contributors.',
    },
    {
        step: '03',
        title: 'Choose stores',
        text: 'Select platforms, territories and the planned release date.',
    },
    {
        step: '04',
        title: 'Track performance',
        text: 'View delivery progress, analytics, royalties and statements.',
    },
];

const faqs = [
    {
        question: 'Which audio format is accepted?',
        answer:
            'Mixx Tune accepts high-quality WAV audio files for music distribution.',
    },
    {
        question: 'Can labels manage multiple artists?',
        answer:
            'Yes. Label accounts can manage artists, releases, catalogue, reports, royalties and team workflows.',
    },
    {
        question: 'Where can I view my earnings?',
        answer:
            'Your dashboard provides royalty reports, statements, wallet balance, invoices and withdrawal history.',
    },
    {
        question: 'Can I distribute music worldwide?',
        answer:
            'Yes. You can choose worldwide distribution or configure release territories according to your requirements.',
    },
];

function LogoMark() {
    return (
        <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 via-fuchsia-500 to-orange-400 shadow-lg shadow-violet-500/25">
                <span className="text-xl font-black text-white">M</span>
            </div>

            <div>
                <div className="text-xl font-black tracking-tight text-slate-950">
                    MIXX TUNE
                </div>
                <div className="text-[10px] font-bold uppercase tracking-[0.3em] text-violet-600">
                    Music Distribution
                </div>
            </div>
        </div>
    );
}

function ArrowIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            className="h-5 w-5"
            aria-hidden="true"
        >
            <path
                d="M5 12h14M13 6l6 6-6 6"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function CheckIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            className="h-5 w-5"
            aria-hidden="true"
        >
            <path
                d="m5 12 4 4L19 6"
                stroke="currentColor"
                strokeWidth="2.2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function MenuIcon({ open }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            className="h-6 w-6"
            aria-hidden="true"
        >
            {open ? (
                <>
                    <path
                        d="M6 6l12 12"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                    />
                    <path
                        d="M18 6 6 18"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                    />
                </>
            ) : (
                <>
                    <path
                        d="M4 7h16"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                    />
                    <path
                        d="M4 12h16"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                    />
                    <path
                        d="M4 17h16"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                    />
                </>
            )}
        </svg>
    );
}

export default function Welcome({ auth }) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [openFaq, setOpenFaq] = useState(0);

    return (
        <>
            <Head>
                <title>Mixx Tune — Music Distribution for Artists & Labels</title>
                <meta
                    name="description"
                    content="Distribute music worldwide, manage releases, analyse streams and track royalties with Mixx Tune."
                />
                <meta
                    name="keywords"
                    content="music distribution, digital music distribution, artist dashboard, label dashboard, royalties, Mixx Tune"
                />
            </Head>

            <div className="min-h-screen overflow-x-hidden bg-white text-slate-900">
                <header className="fixed inset-x-0 top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-6 lg:px-8">
                        <a href="#home" aria-label="Mixx Tune home">
                            <LogoMark />
                        </a>

                        <nav className="hidden items-center gap-8 lg:flex">
                            <a
                                href="#features"
                                className="text-sm font-semibold text-slate-600 transition hover:text-violet-600"
                            >
                                Features
                            </a>
                            <a
                                href="#how-it-works"
                                className="text-sm font-semibold text-slate-600 transition hover:text-violet-600"
                            >
                                How It Works
                            </a>
                            <a
                                href="#analytics"
                                className="text-sm font-semibold text-slate-600 transition hover:text-violet-600"
                            >
                                Analytics
                            </a>
                            <a
                                href="#faq"
                                className="text-sm font-semibold text-slate-600 transition hover:text-violet-600"
                            >
                                FAQ
                            </a>
                        </nav>

                        <div className="hidden items-center gap-3 lg:flex">
                            {auth?.user ? (
                                <Link
                                    href="/dashboard"
                                    className="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white transition hover:bg-violet-700"
                                >
                                    Open Dashboard
                                    <ArrowIcon />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href="/login"
                                        className="rounded-xl px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
                                    >
                                        Log in
                                    </Link>

                                    <Link
                                        href="/register"
                                        className="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-slate-950/15 transition hover:-translate-y-0.5 hover:bg-violet-700"
                                    >
                                        Get Started
                                        <ArrowIcon />
                                    </Link>
                                </>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={() =>
                                setMobileMenuOpen((current) => !current)
                            }
                            className="rounded-xl border border-slate-200 p-2.5 text-slate-700 lg:hidden"
                            aria-label="Toggle navigation"
                        >
                            <MenuIcon open={mobileMenuOpen} />
                        </button>
                    </div>

                    {mobileMenuOpen && (
                        <div className="border-t border-slate-200 bg-white px-5 py-5 lg:hidden">
                            <div className="mx-auto flex max-w-7xl flex-col gap-2">
                                {[
                                    ['Features', '#features'],
                                    ['How It Works', '#how-it-works'],
                                    ['Analytics', '#analytics'],
                                    ['FAQ', '#faq'],
                                ].map(([label, href]) => (
                                    <a
                                        key={label}
                                        href={href}
                                        onClick={() =>
                                            setMobileMenuOpen(false)
                                        }
                                        className="rounded-xl px-4 py-3 font-semibold text-slate-700 hover:bg-slate-100"
                                    >
                                        {label}
                                    </a>
                                ))}

                                <div className="mt-3 grid grid-cols-2 gap-3">
                                    <Link
                                        href="/login"
                                        className="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href="/register"
                                        className="rounded-xl bg-slate-950 px-4 py-3 text-center text-sm font-bold text-white"
                                    >
                                        Get Started
                                    </Link>
                                </div>
                            </div>
                        </div>
                    )}
                </header>

                <main>
                    <section
                        id="home"
                        className="relative overflow-hidden bg-slate-950 pb-24 pt-32 sm:pb-32 sm:pt-40"
                    >
                        <div className="absolute inset-0">
                            <div className="absolute -left-32 top-10 h-96 w-96 rounded-full bg-violet-600/30 blur-3xl" />
                            <div className="absolute -right-32 top-32 h-96 w-96 rounded-full bg-fuchsia-500/20 blur-3xl" />
                            <div className="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-orange-400/10 blur-3xl" />
                        </div>

                        <div className="relative mx-auto grid max-w-7xl items-center gap-16 px-5 sm:px-6 lg:grid-cols-[1.05fr_.95fr] lg:px-8">
                            <div>
                                <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-violet-200 backdrop-blur">
                                    <span className="h-2 w-2 rounded-full bg-emerald-400" />
                                    Built for independent music
                                </div>

                                <h1 className="max-w-4xl text-5xl font-black leading-[1.03] tracking-[-0.045em] text-white sm:text-6xl lg:text-7xl">
                                    Your music.
                                    <span className="block bg-gradient-to-r from-violet-400 via-fuchsia-400 to-orange-300 bg-clip-text text-transparent">
                                        Everywhere it belongs.
                                    </span>
                                </h1>

                                <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-300 sm:text-xl">
                                    Distribute releases worldwide, manage your
                                    catalogue, understand performance and
                                    collect royalties from one professional
                                    platform.
                                </p>

                                <div className="mt-10 flex flex-col gap-4 sm:flex-row">
                                    <Link
                                        href="/register"
                                        className="inline-flex items-center justify-center gap-3 rounded-2xl bg-white px-7 py-4 text-base font-black text-slate-950 shadow-2xl shadow-white/10 transition hover:-translate-y-1 hover:bg-violet-100"
                                    >
                                        Start Distributing
                                        <ArrowIcon />
                                    </Link>

                                    <a
                                        href="#how-it-works"
                                        className="inline-flex items-center justify-center rounded-2xl border border-white/20 bg-white/5 px-7 py-4 text-base font-bold text-white backdrop-blur transition hover:bg-white/10"
                                    >
                                        See How It Works
                                    </a>
                                </div>

                                <div className="mt-10 flex flex-wrap gap-x-7 gap-y-3 text-sm font-semibold text-slate-300">
                                    {[
                                        'Artist & label accounts',
                                        'Worldwide stores',
                                        'Royalty reporting',
                                    ].map((item) => (
                                        <div
                                            key={item}
                                            className="flex items-center gap-2"
                                        >
                                            <span className="text-emerald-400">
                                                <CheckIcon />
                                            </span>
                                            {item}
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="relative">
                                <div className="absolute -inset-8 rounded-[3rem] bg-gradient-to-br from-violet-600/30 to-fuchsia-500/10 blur-2xl" />

                                <div className="relative rounded-[2rem] border border-white/15 bg-white/10 p-4 shadow-2xl backdrop-blur-xl">
                                    <div className="rounded-[1.5rem] bg-white p-5 sm:p-7">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">
                                                    Monthly performance
                                                </p>
                                                <h2 className="mt-2 text-2xl font-black text-slate-950">
                                                    Artist Overview
                                                </h2>
                                            </div>

                                            <div className="rounded-2xl bg-violet-100 px-3 py-2 text-xs font-black text-violet-700">
                                                LIVE
                                            </div>
                                        </div>

                                        <div className="mt-7 grid grid-cols-3 gap-3">
                                            {[
                                                ['Streams', '2.4M', '+18.6%'],
                                                ['Revenue', '₹4.8L', '+12.4%'],
                                                ['Listeners', '684K', '+22.1%'],
                                            ].map(([label, value, growth]) => (
                                                <div
                                                    key={label}
                                                    className="rounded-2xl bg-slate-50 p-4"
                                                >
                                                    <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                                        {label}
                                                    </p>
                                                    <p className="mt-2 text-lg font-black text-slate-950 sm:text-xl">
                                                        {value}
                                                    </p>
                                                    <p className="mt-1 text-xs font-bold text-emerald-600">
                                                        {growth}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>

                                        <div className="mt-7 rounded-2xl bg-slate-950 p-5">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                                        Performance
                                                    </p>
                                                    <p className="mt-1 text-sm font-bold text-white">
                                                        Last 7 months
                                                    </p>
                                                </div>

                                                <p className="text-sm font-black text-emerald-400">
                                                    +24.8%
                                                </p>
                                            </div>

                                            <div className="mt-8 flex h-40 items-end gap-3">
                                                {[38, 54, 47, 67, 74, 86, 100].map(
                                                    (height, index) => (
                                                        <div
                                                            key={index}
                                                            className="flex flex-1 items-end"
                                                            style={{
                                                                height: '100%',
                                                            }}
                                                        >
                                                            <div
                                                                className="w-full rounded-t-lg bg-gradient-to-t from-violet-700 to-fuchsia-400"
                                                                style={{
                                                                    height: `${height}%`,
                                                                }}
                                                            />
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>

                                        <div className="mt-5 flex items-center justify-between rounded-2xl border border-slate-200 p-4">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-100 text-lg">
                                                    ♪
                                                </div>
                                                <div>
                                                    <p className="text-sm font-black text-slate-950">
                                                        Latest release
                                                    </p>
                                                    <p className="text-xs font-semibold text-slate-500">
                                                        Delivered to 24 stores
                                                    </p>
                                                </div>
                                            </div>

                                            <span className="rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-black text-emerald-700">
                                                Live
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-b border-slate-200 bg-white py-8">
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <p className="text-center text-xs font-black uppercase tracking-[0.25em] text-slate-400">
                                Distribute to leading music platforms
                            </p>

                            <div className="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                                {stores.map((store) => (
                                    <div
                                        key={store}
                                        className="flex min-h-16 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 px-3 text-center text-sm font-black text-slate-700 transition hover:-translate-y-1 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700"
                                    >
                                        {store}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        id="features"
                        className="bg-slate-50 py-24 sm:py-32"
                    >
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                <p className="text-sm font-black uppercase tracking-[0.22em] text-violet-600">
                                    Complete music infrastructure
                                </p>
                                <h2 className="mt-4 text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">
                                    Everything needed to release and grow
                                </h2>
                                <p className="mt-6 text-lg leading-8 text-slate-600">
                                    A unified system for distribution,
                                    catalogue operations, analytics, royalties
                                    and finance.
                                </p>
                            </div>

                            <div className="mt-16 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                {features.map((feature) => (
                                    <article
                                        key={feature.number}
                                        className="group rounded-[1.75rem] border border-slate-200 bg-white p-7 transition duration-300 hover:-translate-y-2 hover:border-violet-200 hover:shadow-2xl hover:shadow-violet-900/10"
                                    >
                                        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-950 text-sm font-black text-white transition group-hover:bg-violet-600">
                                            {feature.number}
                                        </div>

                                        <h3 className="mt-7 text-xl font-black text-slate-950">
                                            {feature.title}
                                        </h3>

                                        <p className="mt-3 leading-7 text-slate-600">
                                            {feature.description}
                                        </p>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        id="how-it-works"
                        className="bg-white py-24 sm:py-32"
                    >
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="grid gap-14 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
                                <div className="lg:sticky lg:top-28">
                                    <p className="text-sm font-black uppercase tracking-[0.22em] text-violet-600">
                                        Simple workflow
                                    </p>

                                    <h2 className="mt-4 text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">
                                        From your studio to global listeners
                                    </h2>

                                    <p className="mt-6 max-w-lg text-lg leading-8 text-slate-600">
                                        A guided release workflow keeps
                                        metadata, audio, artwork, stores and
                                        territory organised.
                                    </p>

                                    <Link
                                        href="/register"
                                        className="mt-8 inline-flex items-center gap-3 rounded-2xl bg-violet-600 px-6 py-4 font-black text-white transition hover:bg-slate-950"
                                    >
                                        Create Account
                                        <ArrowIcon />
                                    </Link>
                                </div>

                                <div className="space-y-4">
                                    {steps.map((item) => (
                                        <article
                                            key={item.step}
                                            className="flex gap-5 rounded-[1.75rem] border border-slate-200 bg-slate-50 p-6 sm:gap-7 sm:p-8"
                                        >
                                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white text-lg font-black text-violet-600 shadow-sm">
                                                {item.step}
                                            </div>

                                            <div>
                                                <h3 className="text-xl font-black text-slate-950">
                                                    {item.title}
                                                </h3>
                                                <p className="mt-2 leading-7 text-slate-600">
                                                    {item.text}
                                                </p>
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="analytics"
                        className="overflow-hidden bg-slate-950 py-24 text-white sm:py-32"
                    >
                        <div className="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-6 lg:grid-cols-2 lg:px-8">
                            <div>
                                <p className="text-sm font-black uppercase tracking-[0.22em] text-fuchsia-400">
                                    Actionable analytics
                                </p>

                                <h2 className="mt-4 text-4xl font-black tracking-tight sm:text-5xl">
                                    Know exactly how your music performs
                                </h2>

                                <p className="mt-6 max-w-xl text-lg leading-8 text-slate-300">
                                    Compare streams and revenue by reporting
                                    month, sale month, store, country, release
                                    and track.
                                </p>

                                <div className="mt-9 grid gap-4 sm:grid-cols-2">
                                    {[
                                        'Store performance',
                                        'Country insights',
                                        'Track growth',
                                        'Monthly comparison',
                                        'Revenue analysis',
                                        'Downloadable reports',
                                    ].map((item) => (
                                        <div
                                            key={item}
                                            className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3"
                                        >
                                            <span className="text-emerald-400">
                                                <CheckIcon />
                                            </span>
                                            <span className="font-bold text-slate-200">
                                                {item}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="rounded-[2rem] border border-white/10 bg-white/5 p-5 backdrop-blur sm:p-7">
                                <div className="grid grid-cols-2 gap-4">
                                    {[
                                        ['Total Streams', '18.2M'],
                                        ['Net Earnings', '₹12.6L'],
                                        ['Active Tracks', '284'],
                                        ['Live Releases', '96'],
                                    ].map(([label, value]) => (
                                        <div
                                            key={label}
                                            className="rounded-2xl bg-white p-5 text-slate-950"
                                        >
                                            <p className="text-xs font-black uppercase tracking-wider text-slate-400">
                                                {label}
                                            </p>
                                            <p className="mt-3 text-2xl font-black sm:text-3xl">
                                                {value}
                                            </p>
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-4 rounded-2xl bg-gradient-to-br from-violet-600 to-fuchsia-600 p-6">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="text-sm font-bold text-violet-100">
                                                Growth this month
                                            </p>
                                            <p className="mt-2 text-4xl font-black">
                                                +32.8%
                                            </p>
                                        </div>
                                        <div className="rounded-xl bg-white/15 px-3 py-2 text-sm font-black">
                                            Analytics
                                        </div>
                                    </div>

                                    <div className="mt-9 flex h-28 items-end gap-2">
                                        {[30, 42, 38, 56, 48, 67, 64, 78, 88, 100].map(
                                            (height, index) => (
                                                <div
                                                    key={index}
                                                    className="flex-1 rounded-t-md bg-white/80"
                                                    style={{
                                                        height: `${height}%`,
                                                    }}
                                                />
                                            ),
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="faq" className="bg-slate-50 py-24 sm:py-32">
                        <div className="mx-auto max-w-4xl px-5 sm:px-6 lg:px-8">
                            <div className="text-center">
                                <p className="text-sm font-black uppercase tracking-[0.22em] text-violet-600">
                                    Frequently asked questions
                                </p>
                                <h2 className="mt-4 text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">
                                    Clear answers before you release
                                </h2>
                            </div>

                            <div className="mt-14 space-y-4">
                                {faqs.map((faq, index) => {
                                    const isOpen = openFaq === index;

                                    return (
                                        <article
                                            key={faq.question}
                                            className="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                                        >
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setOpenFaq(
                                                        isOpen ? -1 : index,
                                                    )
                                                }
                                                className="flex w-full items-center justify-between gap-5 p-6 text-left"
                                            >
                                                <span className="text-lg font-black text-slate-950">
                                                    {faq.question}
                                                </span>
                                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xl font-bold text-violet-600">
                                                    {isOpen ? '−' : '+'}
                                                </span>
                                            </button>

                                            {isOpen && (
                                                <div className="border-t border-slate-100 px-6 pb-6 pt-5 leading-7 text-slate-600">
                                                    {faq.answer}
                                                </div>
                                            )}
                                        </article>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    <section className="bg-white px-5 py-24 sm:px-6 sm:py-32 lg:px-8">
                        <div className="relative mx-auto max-w-7xl overflow-hidden rounded-[2.5rem] bg-slate-950 px-6 py-16 text-center sm:px-12 sm:py-20">
                            <div className="absolute -left-20 -top-20 h-72 w-72 rounded-full bg-violet-600/40 blur-3xl" />
                            <div className="absolute -bottom-24 -right-20 h-72 w-72 rounded-full bg-fuchsia-500/30 blur-3xl" />

                            <div className="relative mx-auto max-w-3xl">
                                <p className="text-sm font-black uppercase tracking-[0.22em] text-violet-300">
                                    Ready to release?
                                </p>

                                <h2 className="mt-5 text-4xl font-black tracking-tight text-white sm:text-5xl">
                                    Build your music business with Mixx Tune
                                </h2>

                                <p className="mt-6 text-lg leading-8 text-slate-300">
                                    Join a professional distribution ecosystem
                                    designed for artists, labels and music
                                    teams.
                                </p>

                                <div className="mt-9 flex flex-col justify-center gap-4 sm:flex-row">
                                    <Link
                                        href="/register"
                                        className="inline-flex items-center justify-center gap-3 rounded-2xl bg-white px-7 py-4 font-black text-slate-950 transition hover:bg-violet-100"
                                    >
                                        Create Free Account
                                        <ArrowIcon />
                                    </Link>

                                    <Link
                                        href="/login"
                                        className="rounded-2xl border border-white/20 px-7 py-4 font-black text-white transition hover:bg-white/10"
                                    >
                                        Sign In
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-slate-200 bg-white">
                    <div className="mx-auto max-w-7xl px-5 py-14 sm:px-6 lg:px-8">
                        <div className="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
                            <div className="lg:col-span-2">
                                <LogoMark />
                                <p className="mt-5 max-w-md leading-7 text-slate-600">
                                    Digital music distribution, catalogue
                                    management, analytics and royalty
                                    infrastructure for modern music businesses.
                                </p>
                            </div>

                            <div>
                                <h3 className="text-sm font-black uppercase tracking-wider text-slate-950">
                                    Platform
                                </h3>
                                <div className="mt-5 space-y-3 text-sm font-semibold text-slate-600">
                                    <a
                                        href="#features"
                                        className="block hover:text-violet-600"
                                    >
                                        Features
                                    </a>
                                    <a
                                        href="#analytics"
                                        className="block hover:text-violet-600"
                                    >
                                        Analytics
                                    </a>
                                    <Link
                                        href="/login"
                                        className="block hover:text-violet-600"
                                    >
                                        Login
                                    </Link>
                                    <Link
                                        href="/register"
                                        className="block hover:text-violet-600"
                                    >
                                        Register
                                    </Link>
                                </div>
                            </div>

                            <div>
                                <h3 className="text-sm font-black uppercase tracking-wider text-slate-950">
                                    Company
                                </h3>
                                <div className="mt-5 space-y-3 text-sm font-semibold text-slate-600">
                                    <a
                                        href="#how-it-works"
                                        className="block hover:text-violet-600"
                                    >
                                        How It Works
                                    </a>
                                    <a
                                        href="#faq"
                                        className="block hover:text-violet-600"
                                    >
                                        FAQ
                                    </a>
                                    <a
                                        href="mailto:support@mixxtune.com"
                                        className="block hover:text-violet-600"
                                    >
                                        Support
                                    </a>
                                    <a
                                        href="mailto:support@mixxtune.com"
                                        className="block hover:text-violet-600"
                                    >
                                        Contact
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div className="mt-12 flex flex-col gap-4 border-t border-slate-200 pt-7 text-sm font-semibold text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                            <p>
                                © {new Date().getFullYear()} Mixx Tune
                                Entertainment. All rights reserved.
                            </p>
                            <p>Music distribution made professional.</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
