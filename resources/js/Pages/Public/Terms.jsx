import { Head } from '@inertiajs/react';
import PublicLayout from '@/Public/PublicLayout';

export default function Terms() {
    return (
        <>
            <Head>
                <title>Terms of Service — Mixx Tune</title>

                <meta
                    name="description"
                    content="Read the Mixx Tune terms of service for use of the platform, distribution tools and account services."
                />

                <link
                    rel="canonical"
                    href="https://www.mixxtune.com/terms"
                />

                <meta
                    property="og:title"
                    content="Terms of Service — Mixx Tune"
                />

                <meta
                    property="og:description"
                    content="Read the Mixx Tune terms of service for use of the platform, distribution tools and account services."
                />

                <meta
                    property="og:type"
                    content="website"
                />

                <meta
                    property="og:url"
                    content="https://www.mixxtune.com/terms"
                />

                <meta
                    name="twitter:card"
                    content="summary_large_image"
                />
                <title>Terms of Service — Mixx Tune</title>
                <meta
                    name="description"
                    content="Review the terms governing use of the Mixx Tune music distribution and catalogue management platform."
                />
            </Head>

            <PublicLayout
                title="Terms of Service"
                subtitle="Terms governing access to and use of the Mixx Tune platform."
            >
                <section className="py-16 sm:py-24">
                    <div className="mx-auto max-w-4xl space-y-10 px-5 sm:px-6 lg:px-8">
                        <Section title="1. Platform use">
                            Users may use Mixx Tune only
                            for lawful music distribution,
                            catalogue management, royalty,
                            reporting and related
                            activities.
                        </Section>

                        <Section title="2. Rights and permissions">
                            Users must have all necessary
                            rights, licences, permissions
                            and authority for music,
                            artwork, metadata and other
                            material submitted through
                            the platform.
                        </Section>

                        <Section title="3. Accurate metadata">
                            Users are responsible for
                            supplying complete and
                            accurate release, track,
                            artist, contributor, ownership
                            and payment information.
                        </Section>

                        <Section title="4. Distribution">
                            Delivery to digital service
                            providers may be subject to
                            store-specific requirements,
                            review processes, timelines
                            and policies.
                        </Section>

                        <Section title="5. Royalties and reports">
                            Earnings and performance data
                            depend on reporting received
                            from digital service providers
                            and relevant partners.
                        </Section>

                        <Section title="6. Suspension">
                            Access may be restricted where
                            misuse, rights disputes,
                            fraudulent activity, security
                            risks or material policy
                            violations are identified.
                        </Section>

                        <Section title="7. Contact">
                            Questions regarding these
                            terms may be sent to
                            support@mixxtune.com.
                        </Section>
                    </div>
                </section>
            </PublicLayout>
        </>
    );
}

function Section({
    title,
    children,
}) {
    return (
        <section>
            <h2 className="text-2xl font-black text-slate-950">
                {title}
            </h2>

            <p className="mt-4 text-base leading-8 text-slate-600">
                {children}
            </p>
        </section>
    );
}
