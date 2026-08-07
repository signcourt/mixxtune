import {
    Head,
    Link,
} from '@inertiajs/react';

import {
    ArrowRight,
    BarChart3,
    CheckCircle2,
    ChevronRight,
    Globe2,
    Headphones,
    Layers3,
    Menu,
    Music2,
    Play,
    ShieldCheck,
    Sparkles,
    WalletCards,
    X,
} from 'lucide-react';

import {
    useState,
} from 'react';

const stores = [
    'Spotify',
    'Apple Music',
    'YouTube Music',
    'Amazon Music',
    'JioSaavn',
    'Deezer',
    'TIDAL',
    'TikTok',
];

const stats = [
    {
        value: '150+',
        label: 'Stores & platforms',
    },
    {
        value: '190+',
        label: 'Countries & territories',
    },
    {
        value: '24/7',
        label: 'Catalogue access',
    },
    {
        value: '1',
        label: 'Connected platform',
    },
];

const features = [
    {
        icon: Globe2,
        title: 'Worldwide Distribution',
        text:
            'Deliver music to major streaming and download platforms through one professional workflow.',
    },
    {
        icon: BarChart3,
        title: 'Analytics & Reports',
        text:
            'Track streams, stores, countries and earnings through clear operational reporting.',
    },
    {
        icon: WalletCards,
        title: 'Royalties & Wallet',
        text:
            'Manage statements, balances and withdrawals from a connected finance workflow.',
    },
    {
        icon: Layers3,
        title: 'Catalogue Management',
        text:
            'Keep artists, releases, tracks, identifiers and ownership information organised.',
    },
    {
        icon: ShieldCheck,
        title: 'Rights-Aware Workflow',
        text:
            'Structured metadata, review, delivery and access controls for professional operations.',
    },
    {
        icon: Headphones,
        title: 'Built for Artists & Labels',
        text:
            'One platform for independent artists, labels and internal administration teams.',
    },
];

const steps = [
    {
        number: '01',
        title: 'Create your release',
        text:
            'Add release metadata, artists, artwork and release date.',
    },
    {
        number: '02',
        title: 'Upload your tracks',
        text:
            'Add WAV masters, track metadata, contributors and identifiers.',
    },
    {
        number: '03',
        title: 'Choose stores & territories',
        text:
            'Select distribution destinations and release territories.',
    },
    {
        number: '04',
        title: 'Review & submit',
        text:
            'Validate the release and send it into the distribution workflow.',
    },
];

const faqs = [
    {
        q: 'Who can use Mixx Tune?',
        a:
            'Mixx Tune is designed for independent artists, labels and music businesses that need distribution, catalogue management, reporting and royalty workflows.',
    },
    {
        q: 'Can labels manage multiple artists?',
        a:
            'Yes. Label accounts are designed to manage multiple artists, releases and catalogue operations from one panel.',
    },
    {
        q: 'Can I track delivery status?',
        a:
            'Yes. Releases can move through review, processing, delivery and live states with store-level operational visibility.',
    },
    {
        q: 'Does Mixx Tune include analytics?',
        a:
            'Yes. Reporting can include streams, earnings, platforms, countries and top-performing catalogue data where reporting data is available.',
    },
];

export default function Welcome({
    auth = {},
}) {
    const [menuOpen, setMenuOpen] =
        useState(false);

    const [openFaq, setOpenFaq] =
        useState(0);

    return (
        <>
            <Head>
                <title>
                    Mixx Tune — Music Distribution for
                    Artists & Labels
                </title>

                <meta
                    name="description"
                    content="Distribute music worldwide, manage your catalogue, track analytics and handle royalties through Mixx Tune."
                />

                <link
                    rel="canonical"
                    href="https://www.mixxtune.com/"
                />

                <meta
                    property="og:title"
                    content="Mixx Tune — Music Distribution for Artists & Labels"
                />

                <meta
                    property="og:description"
                    content="Worldwide music distribution, catalogue management, analytics and royalty operations in one platform."
                />

                <meta
                    property="og:type"
                    content="website"
                />

                <meta
                    property="og:url"
                    content="https://www.mixxtune.com/"
                />

                <meta
                    name="twitter:card"
                    content="summary_large_image"
                />
            </Head>

            <div className="min-h-screen overflow-x-hidden bg-white text-slate-950">
                <header className="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-slate-950/85 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-6 lg:px-8">
                        <Link
                            href="/"
                            className="flex items-center gap-3"
                        >
                            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 via-fuchsia-500 to-orange-400 text-lg font-black text-white shadow-lg shadow-violet-500/30">
                                M
                            </div>

                            <div>
                                <div className="text-lg font-black tracking-tight text-white">
                                    MIXX TUNE
                                </div>

                                <div className="text-[9px] font-bold uppercase tracking-[0.28em] text-violet-300">
                                    Music Distribution
                                </div>
                            </div>
                        </Link>

                        <nav className="hidden items-center gap-8 lg:flex">
                            {[
                                ['Features', '#features'],
                                ['How it Works', '#workflow'],
                                ['Analytics', '#analytics'],
                                ['Distribution', '/distribution'],
                                ['Pricing', '/pricing'],
                            ].map(
                                ([label, href]) => (
                                    <a
                                        key={href}
                                        href={href}
                                        className="text-sm font-semibold text-slate-300 transition hover:text-white"
                                    >
                                        {label}
                                    </a>
                                )
                            )}
                        </nav>

                        <div className="hidden items-center gap-2 lg:flex">
                            {auth?.user ? (
                                <Link
                                    href="/dashboard"
                                    className="rounded-xl bg-white px-5 py-2.5 text-sm font-black text-slate-950"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href="/login"
                                        className="rounded-xl px-4 py-2.5 text-sm font-bold text-slate-300 hover:bg-white/10 hover:text-white"
                                    >
                                        Log in
                                    </Link>

                                    <Link
                                        href="/register"
                                        className="rounded-xl bg-white px-5 py-2.5 text-sm font-black text-slate-950 transition hover:bg-violet-100"
                                    >
                                        Get Started
                                    </Link>
                                </>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={() =>
                                setMenuOpen(
                                    (value) => !value
                                )
                            }
                            className="rounded-xl border border-white/10 p-2.5 text-white lg:hidden"
                        >
                            {menuOpen ? (
                                <X size={21} />
                            ) : (
                                <Menu size={21} />
                            )}
                        </button>
                    </div>

                    {menuOpen && (
                        <div className="border-t border-white/10 bg-slate-950 px-5 py-5 lg:hidden">
                            <div className="space-y-1">
                                {[
                                    ['Features', '#features'],
                                    ['How it Works', '#workflow'],
                                    ['Analytics', '#analytics'],
                                    ['Distribution', '/distribution'],
                                    ['Pricing', '/pricing'],
                                    ['About', '/about'],
                                ].map(
                                    ([label, href]) => (
                                        <a
                                            key={href}
                                            href={href}
                                            className="block rounded-xl px-4 py-3 font-semibold text-slate-300 hover:bg-white/10 hover:text-white"
                                        >
                                            {label}
                                        </a>
                                    )
                                )}
                            </div>

                            <div className="mt-4 grid grid-cols-2 gap-2">
                                <Link
                                    href="/login"
                                    className="rounded-xl border border-white/10 px-4 py-3 text-center font-bold text-white"
                                >
                                    Log in
                                </Link>

                                <Link
                                    href="/register"
                                    className="rounded-xl bg-white px-4 py-3 text-center font-black text-slate-950"
                                >
                                    Get Started
                                </Link>
                            </div>
                        </div>
                    )}
                </header>

                <main>
                    <section className="relative overflow-hidden bg-slate-950 pb-24 pt-36 text-white sm:pb-32 sm:pt-44">
                        <div className="absolute inset-0">
                            <div className="absolute -left-40 top-10 h-[32rem] w-[32rem] rounded-full bg-violet-600/30 blur-[110px]" />
                            <div className="absolute right-[-10rem] top-40 h-[28rem] w-[28rem] rounded-full bg-fuchsia-500/20 blur-[110px]" />
                            <div className="absolute bottom-[-12rem] left-1/3 h-[24rem] w-[24rem] rounded-full bg-cyan-500/10 blur-[110px]" />
                        </div>

                        <div className="absolute inset-0 opacity-[0.07]">
                            <div
                                className="h-full w-full"
                                style={{
                                    backgroundImage:
                                        'linear-gradient(rgba(255,255,255,.25) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.25) 1px, transparent 1px)',
                                    backgroundSize:
                                        '54px 54px',
                                }}
                            />
                        </div>

                        <div className="relative mx-auto grid max-w-7xl gap-16 px-5 sm:px-6 lg:grid-cols-[1.05fr_.95fr] lg:items-center lg:px-8">
                            <div>
                                <div className="inline-flex items-center gap-2 rounded-full border border-violet-400/30 bg-violet-400/10 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-violet-200">
                                    <Sparkles size={14} />
                                    Built for modern music
                                    businesses
                                </div>

                                <h1 className="mt-7 max-w-4xl text-5xl font-black leading-[0.98] tracking-[-0.045em] sm:text-6xl lg:text-7xl">
                                    Your music.
                                    <span className="block bg-gradient-to-r from-violet-300 via-fuchsia-300 to-orange-200 bg-clip-text text-transparent">
                                        Everywhere it belongs.
                                    </span>
                                </h1>

                                <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-300 sm:text-xl">
                                    Distribute releases,
                                    manage your catalogue,
                                    track performance and
                                    operate royalties through
                                    one connected platform.
                                </p>

                                <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                    <Link
                                        href="/register"
                                        className="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-6 py-4 text-sm font-black text-slate-950 shadow-xl shadow-black/20 transition hover:-translate-y-0.5"
                                    >
                                        Start distributing
                                        <ArrowRight
                                            size={17}
                                        />
                                    </Link>

                                    <a
                                        href="#workflow"
                                        className="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/5 px-6 py-4 text-sm font-black text-white backdrop-blur transition hover:bg-white/10"
                                    >
                                        <Play
                                            size={16}
                                            fill="currentColor"
                                        />
                                        See how it works
                                    </a>
                                </div>

                                <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-400">
                                    {[
                                        'Artists',
                                        'Labels',
                                        'Catalogue teams',
                                    ].map((item) => (
                                        <span
                                            key={item}
                                            className="inline-flex items-center gap-2"
                                        >
                                            <CheckCircle2
                                                size={16}
                                                className="text-emerald-400"
                                            />
                                            {item}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            <div className="relative">
                                <div className="absolute -inset-8 rounded-[3rem] bg-gradient-to-br from-violet-500/20 to-fuchsia-500/10 blur-2xl" />

                                <div className="relative rounded-[2rem] border border-white/10 bg-white/[0.07] p-4 shadow-2xl shadow-black/40 backdrop-blur-xl sm:p-6">
                                    <div className="rounded-[1.5rem] border border-white/10 bg-slate-900/90 p-5">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <div className="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">
                                                    Artist Overview
                                                </div>

                                                <div className="mt-1 text-lg font-black">
                                                    Performance
                                                </div>
                                            </div>

                                            <div className="rounded-xl bg-emerald-400/10 px-3 py-2 text-xs font-black text-emerald-300">
                                                Live
                                            </div>
                                        </div>

                                        <div className="mt-6 grid grid-cols-3 gap-3">
                                            {[
                                                [
                                                    'Streams',
                                                    '2.4M',
                                                ],
                                                [
                                                    'Revenue',
                                                    '₹8.6L',
                                                ],
                                                [
                                                    'Releases',
                                                    '42',
                                                ],
                                            ].map(
                                                ([
                                                    label,
                                                    value,
                                                ]) => (
                                                    <div
                                                        key={
                                                            label
                                                        }
                                                        className="rounded-2xl border border-white/10 bg-white/[0.04] p-4"
                                                    >
                                                        <div className="text-xs text-slate-500">
                                                            {
                                                                label
                                                            }
                                                        </div>

                                                        <div className="mt-2 text-xl font-black">
                                                            {
                                                                value
                                                            }
                                                        </div>
                                                    </div>
                                                )
                                            )}
                                        </div>

                                        <div className="mt-5 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                                            <div className="flex h-44 items-end gap-2">
                                                {[
                                                    32,
                                                    48,
                                                    38,
                                                    63,
                                                    56,
                                                    78,
                                                    69,
                                                    92,
                                                    84,
                                                    100,
                                                    88,
                                                    112,
                                                ].map(
                                                    (
                                                        height,
                                                        index
                                                    ) => (
                                                        <div
                                                            key={
                                                                index
                                                            }
                                                            className="flex-1 rounded-t-lg bg-gradient-to-t from-violet-600 to-fuchsia-400"
                                                            style={{
                                                                height: `${height}px`,
                                                                opacity:
                                                                    0.45 +
                                                                    index *
                                                                        0.04,
                                                            }}
                                                        />
                                                    )
                                                )}
                                            </div>

                                            <div className="mt-4 flex items-center justify-between text-xs text-slate-500">
                                                <span>
                                                    Revenue trend
                                                </span>
                                                <span className="font-bold text-emerald-300">
                                                    +18.4%
                                                </span>
                                            </div>
                                        </div>

                                        <div className="mt-5 grid grid-cols-2 gap-3">
                                            {[
                                                [
                                                    'Top Platform',
                                                    'Spotify',
                                                ],
                                                [
                                                    'Top Country',
                                                    'India',
                                                ],
                                            ].map(
                                                ([
                                                    label,
                                                    value,
                                                ]) => (
                                                    <div
                                                        key={
                                                            label
                                                        }
                                                        className="rounded-2xl border border-white/10 bg-white/[0.03] p-4"
                                                    >
                                                        <div className="text-xs text-slate-500">
                                                            {
                                                                label
                                                            }
                                                        </div>

                                                        <div className="mt-1 font-black">
                                                            {
                                                                value
                                                            }
                                                        </div>
                                                    </div>
                                                )
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="absolute -left-6 top-16 hidden rounded-2xl border border-white/10 bg-white/10 p-4 shadow-xl backdrop-blur-xl sm:block">
                                    <Music2
                                        size={20}
                                        className="text-violet-200"
                                    />
                                    <div className="mt-2 text-xs font-black">
                                        Release Live
                                    </div>
                                </div>

                                <div className="absolute -right-6 bottom-20 hidden rounded-2xl border border-white/10 bg-white/10 p-4 shadow-xl backdrop-blur-xl sm:block">
                                    <BarChart3
                                        size={20}
                                        className="text-emerald-300"
                                    />
                                    <div className="mt-2 text-xs font-black">
                                        Analytics Ready
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-b border-slate-200 bg-white py-9">
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <p className="text-center text-xs font-black uppercase tracking-[0.2em] text-slate-400">
                                Distribute to leading
                                digital music platforms
                            </p>

                            <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                                {stores.map(
                                    (store) => (
                                        <div
                                            key={store}
                                            className="flex min-h-16 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 px-4 text-center text-sm font-black text-slate-700 transition hover:-translate-y-0.5 hover:border-violet-200 hover:bg-white hover:shadow-sm"
                                        >
                                            {store}
                                        </div>
                                    )
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="bg-slate-50 py-14">
                        <div className="mx-auto grid max-w-7xl gap-4 px-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
                            {stats.map(
                                (stat) => (
                                    <div
                                        key={stat.label}
                                        className="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm"
                                    >
                                        <div className="text-4xl font-black tracking-tight text-slate-950">
                                            {stat.value}
                                        </div>

                                        <div className="mt-2 text-sm font-semibold text-slate-500">
                                            {stat.label}
                                        </div>
                                    </div>
                                )
                            )}
                        </div>
                    </section>

                    <section
                        id="features"
                        className="bg-white py-24 sm:py-32"
                    >
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-3xl text-center">
                                <div className="text-sm font-black uppercase tracking-[0.2em] text-violet-600">
                                    One connected platform
                                </div>

                                <h2 className="mt-4 text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">
                                    More than distribution
                                </h2>

                                <p className="mt-5 text-lg leading-8 text-slate-600">
                                    Mixx Tune combines
                                    release operations,
                                    catalogue management,
                                    analytics and finance
                                    workflows in one system.
                                </p>
                            </div>

                            <div className="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                {features.map(
                                    (feature) => {
                                        const Icon =
                                            feature.icon;

                                        return (
                                            <article
                                                key={
                                                    feature.title
                                                }
                                                className="group rounded-[1.75rem] border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:border-violet-200 hover:shadow-xl hover:shadow-violet-900/5"
                                            >
                                                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-600 transition group-hover:bg-violet-600 group-hover:text-white">
                                                    <Icon
                                                        size={
                                                            22
                                                        }
                                                    />
                                                </div>

                                                <h3 className="mt-6 text-xl font-black text-slate-950">
                                                    {
                                                        feature.title
                                                    }
                                                </h3>

                                                <p className="mt-3 leading-7 text-slate-600">
                                                    {
                                                        feature.text
                                                    }
                                                </p>
                                            </article>
                                        );
                                    }
                                )}
                            </div>
                        </div>
                    </section>

                    <section
                        id="workflow"
                        className="bg-slate-950 py-24 text-white sm:py-32"
                    >
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="grid gap-14 lg:grid-cols-[.85fr_1.15fr] lg:items-start">
                                <div className="lg:sticky lg:top-28">
                                    <div className="text-sm font-black uppercase tracking-[0.2em] text-violet-300">
                                        Simple workflow
                                    </div>

                                    <h2 className="mt-4 text-4xl font-black tracking-tight sm:text-5xl">
                                        From upload to
                                        distribution
                                    </h2>

                                    <p className="mt-5 max-w-xl text-lg leading-8 text-slate-400">
                                        A guided release
                                        process keeps
                                        metadata, audio,
                                        stores, territories
                                        and review organised.
                                    </p>

                                    <Link
                                        href="/distribution"
                                        className="mt-8 inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-5 py-3 font-black text-white hover:bg-white/10"
                                    >
                                        Explore distribution
                                        <ChevronRight
                                            size={17}
                                        />
                                    </Link>
                                </div>

                                <div className="space-y-4">
                                    {steps.map(
                                        (step) => (
                                            <div
                                                key={
                                                    step.number
                                                }
                                                className="grid gap-5 rounded-[1.75rem] border border-white/10 bg-white/[0.04] p-6 sm:grid-cols-[72px_1fr] sm:p-7"
                                            >
                                                <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-lg font-black text-slate-950">
                                                    {
                                                        step.number
                                                    }
                                                </div>

                                                <div>
                                                    <h3 className="text-xl font-black">
                                                        {
                                                            step.title
                                                        }
                                                    </h3>

                                                    <p className="mt-2 leading-7 text-slate-400">
                                                        {
                                                            step.text
                                                        }
                                                    </p>
                                                </div>
                                            </div>
                                        )
                                    )}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="analytics"
                        className="bg-white py-24 sm:py-32"
                    >
                        <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <div>
                                    <div className="text-sm font-black uppercase tracking-[0.2em] text-violet-600">
                                        Analytics built in
                                    </div>

                                    <h2 className="mt-4 text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">
                                        See what your
                                        catalogue is doing
                                    </h2>

                                    <p className="mt-5 text-lg leading-8 text-slate-600">
                                        Understand
                                        performance across
                                        reporting periods,
                                        platforms, countries
                                        and catalogue.
                                    </p>

                                    <div className="mt-8 space-y-4">
                                        {[
                                            'Streams and earnings',
                                            'Platform performance',
                                            'Country performance',
                                            'Top tracks and catalogue',
                                        ].map(
                                            (item) => (
                                                <div
                                                    key={
                                                        item
                                                    }
                                                    className="flex items-center gap-3 font-semibold text-slate-700"
                                                >
                                                    <CheckCircle2
                                                        size={
                                                            19
                                                        }
                                                        className="text-emerald-500"
                                                    />
                                                    {item}
                                                </div>
                                            )
                                        )}
                                    </div>
                                </div>

                                <div className="rounded-[2rem] border border-slate-200 bg-slate-50 p-5 shadow-xl shadow-slate-900/5 sm:p-7">
                                    <div className="rounded-[1.5rem] border border-slate-200 bg-white p-6">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <div className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">
                                                    Revenue
                                                </div>

                                                <div className="mt-2 text-3xl font-black">
                                                    ₹8,63,240
                                                </div>
                                            </div>

                                            <div className="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-600">
                                                +18.4%
                                            </div>
                                        </div>

                                        <div className="mt-8 flex h-48 items-end gap-2">
                                            {[
                                                26,
                                                36,
                                                31,
                                                48,
                                                44,
                                                61,
                                                58,
                                                73,
                                                68,
                                                87,
                                                79,
                                                96,
                                            ].map(
                                                (
                                                    height,
                                                    index
                                                ) => (
                                                    <div
                                                        key={
                                                            index
                                                        }
                                                        className="flex-1 rounded-t-lg bg-gradient-to-t from-violet-600 to-fuchsia-400"
                                                        style={{
                                                            height: `${height}%`,
                                                        }}
                                                    />
                                                )
                                            )}
                                        </div>

                                        <div className="mt-6 grid grid-cols-2 gap-3">
                                            <div className="rounded-2xl bg-slate-50 p-4">
                                                <div className="text-xs text-slate-400">
                                                    Streams
                                                </div>

                                                <div className="mt-1 text-lg font-black">
                                                    2.4M
                                                </div>
                                            </div>

                                            <div className="rounded-2xl bg-slate-50 p-4">
                                                <div className="text-xs text-slate-400">
                                                    Sale Units
                                                </div>

                                                <div className="mt-1 text-lg font-black">
                                                    18.2K
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="bg-slate-50 py-24 sm:py-32">
                        <div className="mx-auto max-w-4xl px-5 sm:px-6 lg:px-8">
                            <div className="text-center">
                                <div className="text-sm font-black uppercase tracking-[0.2em] text-violet-600">
                                    FAQ
                                </div>

                                <h2 className="mt-4 text-4xl font-black tracking-tight text-slate-950">
                                    Common questions
                                </h2>
                            </div>

                            <div className="mt-12 space-y-3">
                                {faqs.map(
                                    (item, index) => {
                                        const open =
                                            openFaq ===
                                            index;

                                        return (
                                            <button
                                                key={
                                                    item.q
                                                }
                                                type="button"
                                                onClick={() =>
                                                    setOpenFaq(
                                                        open
                                                            ? -1
                                                            : index
                                                    )
                                                }
                                                className="w-full rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-sm"
                                            >
                                                <div className="flex items-center justify-between gap-5">
                                                    <span className="font-black text-slate-950">
                                                        {
                                                            item.q
                                                        }
                                                    </span>

                                                    <span className="text-xl font-light text-slate-400">
                                                        {open
                                                            ? '−'
                                                            : '+'}
                                                    </span>
                                                </div>

                                                {open && (
                                                    <p className="mt-4 max-w-3xl leading-7 text-slate-600">
                                                        {
                                                            item.a
                                                        }
                                                    </p>
                                                )}
                                            </button>
                                        );
                                    }
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="bg-white px-5 py-24 sm:px-6 sm:py-32 lg:px-8">
                        <div className="relative mx-auto max-w-6xl overflow-hidden rounded-[2.5rem] bg-slate-950 px-6 py-16 text-center text-white shadow-2xl sm:px-12 sm:py-20">
                            <div className="absolute -left-20 top-0 h-72 w-72 rounded-full bg-violet-600/30 blur-3xl" />
                            <div className="absolute -right-20 bottom-0 h-72 w-72 rounded-full bg-fuchsia-500/20 blur-3xl" />

                            <div className="relative">
                                <h2 className="text-4xl font-black tracking-tight sm:text-5xl">
                                    Ready to move your music
                                    forward?
                                </h2>

                                <p className="mx-auto mt-5 max-w-2xl text-lg leading-8 text-slate-300">
                                    Build your catalogue,
                                    prepare releases and run
                                    distribution operations
                                    from one platform.
                                </p>

                                <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                                    <Link
                                        href="/register"
                                        className="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-6 py-4 font-black text-slate-950"
                                    >
                                        Create account
                                        <ArrowRight
                                            size={17}
                                        />
                                    </Link>

                                    <Link
                                        href="/contact"
                                        className="rounded-2xl border border-white/15 bg-white/5 px-6 py-4 font-black text-white"
                                    >
                                        Contact us
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

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

                                <Link
                                    href="/privacy"
                                    className="block hover:text-white"
                                >
                                    Privacy
                                </Link>

                                <Link
                                    href="/terms"
                                    className="block hover:text-white"
                                >
                                    Terms
                                </Link>

                                <a
                                    href="mailto:support@mixxtune.com"
                                    className="block hover:text-white"
                                >
                                    support@mixxtune.com
                                </a>
                            </div>
                        </div>
                    </div>

                    <div className="border-t border-white/10 px-5 py-6 text-center text-xs text-slate-500">
                        © {new Date().getFullYear()}{' '}
                        Mixx Tune. All rights reserved.
                    </div>
                </footer>
            </div>
        </>
    );
}
