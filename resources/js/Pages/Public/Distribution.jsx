import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Public/PublicLayout';

const stores = [
    'Spotify',
    'Apple Music',
    'YouTube Music',
    'Amazon Music',
    'JioSaavn',
    'Gaana',
    'Deezer',
    'TIDAL',
];

const features = [
    'Worldwide store delivery',
    'Territory controls',
    'Release-date scheduling',
    'Store-by-store delivery status',
    'Takedown workflow',
    'Metadata validation',
];

export default function Distribution() {
    return (
        <>
            <Head>
                <title>Music Distribution — Mixx Tune</title>

                <meta
                    name="description"
                    content="Distribute your music worldwide with Mixx Tune and manage stores, territories, releases and delivery workflows."
                />

                <link
                    rel="canonical"
                    href="https://www.mixxtune.com/distribution"
                />

                <meta
                    property="og:title"
                    content="Music Distribution — Mixx Tune"
                />

                <meta
                    property="og:description"
                    content="Distribute your music worldwide with Mixx Tune and manage stores, territories, releases and delivery workflows."
                />

                <meta
                    property="og:type"
                    content="website"
                />

                <meta
                    property="og:url"
                    content="https://www.mixxtune.com/distribution"
                />

                <meta
                    name="twitter:card"
                    content="summary_large_image"
                />
            </Head>

            <PublicLayout
                title="Distribute your music worldwide"
                subtitle="Send your releases to leading streaming and download platforms through a professional release workflow."
            >
                <section className="py-20 sm:py-28">
                    <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            {stores.map((store) => (
                                <div
                                    key={store}
                                    className="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center text-lg font-black text-slate-800"
                                >
                                    {store}
                                </div>
                            ))}
                        </div>

                        <div className="mt-20 grid gap-10 lg:grid-cols-2 lg:items-center">
                            <div>
                                <p className="text-sm font-black uppercase tracking-[0.2em] text-violet-600">
                                    Release workflow
                                </p>

                                <h2 className="mt-4 text-4xl font-black tracking-tight text-slate-950">
                                    One workflow from upload to live
                                </h2>

                                <p className="mt-5 text-lg leading-8 text-slate-600">
                                    Add metadata, WAV audio, artwork,
                                    contributors, stores, territories and
                                    release dates in one guided process.
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {features.map((item) => (
                                    <div
                                        key={item}
                                        className="rounded-2xl border border-slate-200 bg-white p-5 font-bold text-slate-800 shadow-sm"
                                    >
                                        ✓ {item}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="mt-20 rounded-[2rem] bg-slate-950 p-10 text-center text-white sm:p-14">
                            <h3 className="text-3xl font-black">
                                Ready to release?
                            </h3>

                            <p className="mx-auto mt-4 max-w-2xl text-slate-300">
                                Create your account and start preparing your
                                first release.
                            </p>

                            <Link
                                href="/register"
                                className="mt-7 inline-flex rounded-xl bg-white px-6 py-3.5 font-black text-slate-950"
                            >
                                Start Distribution
                            </Link>
                        </div>
                    </div>
                </section>
            </PublicLayout>
        </>
    );
}
