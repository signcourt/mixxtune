import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Public/PublicLayout';

const plans = [
    {
        name: 'Artist',
        price: 'Custom',
        description:
            'For independent artists managing their own catalogue.',
        features: [
            'Release management',
            'Worldwide distribution',
            'Analytics & reports',
            'Royalty statements',
            'Wallet & withdrawals',
        ],
    },
    {
        name: 'Label',
        price: 'Custom',
        description:
            'For labels managing multiple artists and releases.',
        features: [
            'Multiple artists',
            'Catalogue management',
            'Advanced analytics',
            'Royalty operations',
            'Team workflows',
        ],
        featured: true,
    },
];

export default function Pricing() {
    return (
        <>
            <Head title="Pricing — Mixx Tune" />

            <PublicLayout
                title="Simple plans for artists and labels"
                subtitle="Choose the account type that matches your music business. Commercial terms can be configured according to your catalogue and service requirements."
            >
                <section className="py-20 sm:py-28">
                    <div className="mx-auto grid max-w-5xl gap-6 px-5 sm:px-6 md:grid-cols-2 lg:px-8">
                        {plans.map((plan) => (
                            <article
                                key={plan.name}
                                className={`rounded-[2rem] border p-8 ${
                                    plan.featured
                                        ? 'border-violet-300 bg-violet-50 shadow-xl shadow-violet-900/10'
                                        : 'border-slate-200 bg-white'
                                }`}
                            >
                                <div className="text-sm font-black uppercase tracking-[0.2em] text-violet-600">
                                    {plan.name}
                                </div>

                                <div className="mt-5 text-4xl font-black text-slate-950">
                                    {plan.price}
                                </div>

                                <p className="mt-4 leading-7 text-slate-600">
                                    {plan.description}
                                </p>

                                <div className="mt-7 space-y-3">
                                    {plan.features.map((feature) => (
                                        <div
                                            key={feature}
                                            className="font-semibold text-slate-700"
                                        >
                                            ✓ {feature}
                                        </div>
                                    ))}
                                </div>

                                <Link
                                    href="/register"
                                    className="mt-8 inline-flex w-full justify-center rounded-xl bg-slate-950 px-5 py-3.5 font-black text-white hover:bg-violet-700"
                                >
                                    Get Started
                                </Link>
                            </article>
                        ))}
                    </div>
                </section>
            </PublicLayout>
        </>
    );
}
