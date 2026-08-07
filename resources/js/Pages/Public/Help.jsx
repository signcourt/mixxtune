import {
    Head,
    Link,
} from '@inertiajs/react';

import PublicLayout from '@/Public/PublicLayout';

const categories = [
    {
        title: 'Getting Started',
        text:
            'Account setup, profile completion and onboarding.',
    },
    {
        title: 'Create a Release',
        text:
            'Metadata, artists, WAV audio, artwork and contributors.',
    },
    {
        title: 'Distribution',
        text:
            'Stores, territories, release dates and delivery status.',
    },
    {
        title: 'Reports & Analytics',
        text:
            'Streams, earnings, countries, stores and reporting periods.',
    },
    {
        title: 'Royalties',
        text:
            'Royalty statements, earnings and catalogue attribution.',
    },
    {
        title: 'Wallet & Withdrawals',
        text:
            'Balances, withdrawals, payment status and finance history.',
    },
];

export default function Help() {
    return (
        <>
            <Head>
                <title>Help Center — Mixx Tune</title>

                <meta
                    name="description"
                    content="Mixx Tune Help Center for releases, distribution, analytics, royalties, wallets and account support."
                />
            </Head>

            <PublicLayout
                title="Help Center"
                subtitle="Find guidance for releases, distribution, reports, royalties and account operations."
            >
                <section className="py-20 sm:py-28">
                    <div className="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {categories.map(
                                (category) => (
                                    <article
                                        key={
                                            category.title
                                        }
                                        className="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:border-violet-200"
                                    >
                                        <h2 className="text-xl font-black text-slate-950">
                                            {
                                                category.title
                                            }
                                        </h2>

                                        <p className="mt-3 leading-7 text-slate-600">
                                            {
                                                category.text
                                            }
                                        </p>
                                    </article>
                                )
                            )}
                        </div>

                        <div className="mt-16 rounded-[2rem] bg-slate-950 p-10 text-center text-white">
                            <h2 className="text-3xl font-black">
                                Need more help?
                            </h2>

                            <p className="mx-auto mt-4 max-w-xl text-slate-300">
                                Contact the Mixx Tune
                                support team for account,
                                release, royalty or
                                technical assistance.
                            </p>

                            <Link
                                href="/contact"
                                className="mt-7 inline-flex rounded-xl bg-white px-6 py-3.5 font-black text-slate-950"
                            >
                                Contact Support
                            </Link>
                        </div>
                    </div>
                </section>
            </PublicLayout>
        </>
    );
}
