import { Head } from '@inertiajs/react';
import PublicLayout from '@/Public/PublicLayout';

export default function Contact() {
    return (
        <>
            <Head>
                <title>Contact Mixx Tune — Artist & Label Support</title>

                <meta
                    name="description"
                    content="Contact Mixx Tune for music distribution, artist, label, catalogue, reporting and account support."
                />

                <link
                    rel="canonical"
                    href="https://www.mixxtune.com/contact"
                />

                <meta
                    property="og:title"
                    content="Contact Mixx Tune — Artist & Label Support"
                />

                <meta
                    property="og:description"
                    content="Contact Mixx Tune for music distribution, artist, label, catalogue, reporting and account support."
                />

                <meta
                    property="og:type"
                    content="website"
                />

                <meta
                    property="og:url"
                    content="https://www.mixxtune.com/contact"
                />

                <meta
                    name="twitter:card"
                    content="summary_large_image"
                />
            </Head>

            <PublicLayout
                title="Talk to Mixx Tune"
                subtitle="Questions about distribution, catalogue management, royalties or your account? Contact our team."
            >
                <section className="py-20 sm:py-28">
                    <div className="mx-auto grid max-w-5xl gap-6 px-5 sm:px-6 md:grid-cols-2 lg:px-8">
                        <a
                            href="mailto:support@mixxtune.com"
                            className="rounded-[2rem] border border-slate-200 bg-white p-8 shadow-sm transition hover:border-violet-300"
                        >
                            <div className="text-sm font-black uppercase tracking-[0.2em] text-violet-600">
                                Support
                            </div>

                            <div className="mt-4 text-2xl font-black text-slate-950">
                                support@mixxtune.com
                            </div>

                            <p className="mt-4 leading-7 text-slate-600">
                                Account, release, royalty and technical
                                support.
                            </p>
                        </a>

                        <div className="rounded-[2rem] bg-slate-950 p-8 text-white">
                            <div className="text-sm font-black uppercase tracking-[0.2em] text-fuchsia-400">
                                Business
                            </div>

                            <div className="mt-4 text-2xl font-black">
                                Artist & Label Partnerships
                            </div>

                            <p className="mt-4 leading-7 text-slate-300">
                                Contact us to discuss catalogue onboarding,
                                distribution requirements and label services.
                            </p>
                        </div>
                    </div>
                </section>
            </PublicLayout>
        </>
    );
}
