import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({
    children,
    title = 'Welcome back',
    subtitle = 'Sign in to continue to your Mixx Tune account.',
}) {
    return (
        <main className="relative min-h-screen overflow-hidden bg-slate-950">
            <div className="absolute inset-0">
                <div className="absolute -left-28 -top-28 h-80 w-80 rounded-full bg-blue-600/25 blur-3xl" />
                <div className="absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-violet-600/20 blur-3xl" />

                <div
                    className="absolute inset-0 opacity-[0.06]"
                    style={{
                        backgroundImage:
                            'linear-gradient(rgba(255,255,255,.8) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.8) 1px, transparent 1px)',
                        backgroundSize: '42px 42px',
                    }}
                />
            </div>

            <div className="relative mx-auto grid min-h-screen max-w-7xl items-stretch lg:grid-cols-[1.05fr_.95fr]">
                <section className="hidden flex-col justify-between px-14 py-12 text-white lg:flex">
                    <Link href="/" className="w-fit">
                        <ApplicationLogo
                            className="[&_div:nth-child(2)>div:first-child]:text-white"
                        />
                    </Link>

                    <div className="max-w-xl">
                        <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-blue-200 backdrop-blur">
                            <span className="h-2 w-2 rounded-full bg-emerald-400" />
                            Distribution platform online
                        </div>

                        <h1 className="text-5xl font-black leading-[1.08] tracking-tight">
                            Your music.
                            <br />
                            Your catalogue.
                            <br />
                            <span className="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">
                                One powerful platform.
                            </span>
                        </h1>

                        <p className="mt-6 max-w-lg text-base leading-7 text-slate-300">
                            Manage releases, royalties, reports,
                            catalogue, distribution and payments
                            from one secure Mixx Tune account.
                        </p>

                        <div className="mt-10 grid max-w-lg grid-cols-3 gap-3">
                            {[
                                ['Global', 'Distribution'],
                                ['Secure', 'Catalogue'],
                                ['Clear', 'Royalties'],
                            ].map(([value, label]) => (
                                <div
                                    key={label}
                                    className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur"
                                >
                                    <div className="font-bold text-white">
                                        {value}
                                    </div>

                                    <div className="mt-1 text-xs text-slate-400">
                                        {label}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <p className="text-xs text-slate-500">
                        © {new Date().getFullYear()} Mixx Tune.
                        All rights reserved.
                    </p>
                </section>

                <section className="flex min-h-screen items-center justify-center px-4 py-8 sm:px-8 lg:bg-slate-50">
                    <div className="w-full max-w-md">
                        <div className="mb-7 flex justify-center lg:hidden">
                            <Link href="/">
                                <ApplicationLogo
                                    className="[&_div:nth-child(2)>div:first-child]:text-white"
                                />
                            </Link>
                        </div>

                        <div className="rounded-[28px] border border-white/10 bg-white p-6 shadow-2xl shadow-black/20 sm:p-9">
                            <div className="mb-8">
                                <h2 className="text-3xl font-black tracking-tight text-slate-950">
                                    {title}
                                </h2>

                                <p className="mt-2 text-sm leading-6 text-slate-500">
                                    {subtitle}
                                </p>
                            </div>

                            {children}
                        </div>

                        <div className="mt-6 text-center text-xs text-slate-400">
                            Protected by secure authentication and
                            encrypted sessions.
                        </div>
                    </div>
                </section>
            </div>
        </main>
    );
}
