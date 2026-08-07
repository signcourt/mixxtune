import { Head } from '@inertiajs/react';
import PublicLayout from '@/Public/PublicLayout';

export default function About() {
    return (
        <>
            <Head title="About — Mixx Tune" />

            <PublicLayout
                title="Built for modern music businesses"
                subtitle="Mixx Tune brings distribution, catalogue management, analytics, royalties and operational workflows into one platform."
            >
                <section className="py-20 sm:py-28">
                    <div className="mx-auto max-w-5xl px-5 sm:px-6 lg:px-8">
                        <div className="grid gap-10 lg:grid-cols-2">
                            <div>
                                <h2 className="text-3xl font-black text-slate-950">
                                    Our approach
                                </h2>

                                <p className="mt-5 text-lg leading-8 text-slate-600">
                                    Music companies should not need separate
                                    systems for releases, store delivery,
                                    royalties, reporting, statements,
                                    catalogue management and support.
                                </p>

                                <p className="mt-5 text-lg leading-8 text-slate-600">
                                    Mixx Tune is designed as one connected
                                    operating system for artists, labels and
                                    music administration teams.
                                </p>
                            </div>

                            <div className="grid gap-4">
                                {[
                                    [
                                        'Artist-first',
                                        'Clear release and royalty workflows.',
                                    ],
                                    [
                                        'Label-ready',
                                        'Manage multiple artists and catalogues.',
                                    ],
                                    [
                                        'Operational',
                                        'Built for real distribution workflows.',
                                    ],
                                    [
                                        'Transparent',
                                        'Clear analytics, finance and statements.',
                                    ],
                                ].map(([title, text]) => (
                                    <div
                                        key={title}
                                        className="rounded-2xl border border-slate-200 bg-slate-50 p-6"
                                    >
                                        <div className="font-black text-slate-950">
                                            {title}
                                        </div>

                                        <div className="mt-2 text-slate-600">
                                            {text}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </section>
            </PublicLayout>
        </>
    );
}
