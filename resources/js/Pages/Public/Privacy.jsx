import { Head } from '@inertiajs/react';
import PublicLayout from '@/Public/PublicLayout';

export default function Privacy() {
    return (
        <>
            <Head>
                <title>Privacy Policy — Mixx Tune</title>
                <meta
                    name="description"
                    content="Read the Mixx Tune Privacy Policy and learn how account, catalogue and platform information is handled."
                />
            </Head>

            <PublicLayout
                title="Privacy Policy"
                subtitle="How Mixx Tune handles account, catalogue and platform information."
            >
                <LegalContent>
                    <Section title="1. Information we collect">
                        We may collect account information,
                        contact details, artist or label
                        information, catalogue metadata,
                        release data, payment-related
                        information and technical usage data
                        required to operate the platform.
                    </Section>

                    <Section title="2. How information is used">
                        Information may be used to provide
                        distribution services, manage
                        releases, process reports and
                        royalties, maintain accounts,
                        communicate with users and improve
                        platform security and performance.
                    </Section>

                    <Section title="3. Distribution partners">
                        Release metadata and related
                        information may be shared with
                        digital service providers,
                        distribution partners and other
                        service providers where required
                        to deliver music and related
                        services.
                    </Section>

                    <Section title="4. Data security">
                        Mixx Tune applies reasonable
                        technical and organisational
                        safeguards intended to protect
                        platform and account information.
                    </Section>

                    <Section title="5. Your account">
                        Users are responsible for keeping
                        their login credentials secure
                        and for maintaining accurate
                        account and catalogue information.
                    </Section>

                    <Section title="6. Contact">
                        Privacy-related questions may be
                        sent to support@mixxtune.com.
                    </Section>
                </LegalContent>
            </PublicLayout>
        </>
    );
}

function LegalContent({ children }) {
    return (
        <section className="py-16 sm:py-24">
            <div className="mx-auto max-w-4xl px-5 sm:px-6 lg:px-8">
                <div className="space-y-10">
                    {children}
                </div>
            </div>
        </section>
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
