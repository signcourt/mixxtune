import { Head, Link } from "@inertiajs/react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

const operations = [
    {
        title: "Copyright & Claims",
        description:
            "Report copyright issues, incorrect claims, disputes and related rights problems.",
        action: "Open Copyright Support",
        href: "/v2/support/create?category=copyright",
    },
    {
        title: "YouTube Operations",
        description:
            "Handle YouTube copyright, Content ID, strikes, takedowns and channel-related legal requests.",
        action: "Open YouTube Support",
        href: "/v2/support/create?category=copyright",
    },
    {
        title: "Spotify Operations",
        description:
            "Handle Spotify artist, release, rights and profile-related legal/support cases.",
        action: "Open Spotify Support",
        href: "/v2/support/create?category=general",
    },
    {
        title: "Other DSP Operations",
        description:
            "Raise rights, delivery, removal or platform issues for other music services.",
        action: "Open DSP Support",
        href: "/v2/support/create?category=general",
    },
    {
        title: "Rights & Ownership",
        description:
            "Access rights and ownership-related operations without creating a duplicate ownership system.",
        action: "Open Ownership Operations",
        href: "/v2/admin/ownership",
    },
    {
        title: "Legal Requests",
        description:
            "Create a new legal/support request and attach documents or evidence.",
        action: "Create Legal Request",
        href: "/v2/support/create?category=general",
    },
    {
        title: "Case History",
        description:
            "Review previously submitted support/legal cases and their current status.",
        action: "View Case History",
        href: "/v2/support",
    },
];

export default function Index({ role = "artist" }) {
    return (
        <PanelLayout
            role={role}
            title="Legal Operations"
            subtitle="Rights, copyright and platform operations"
        >
            <Head title="Legal Operations" />

            <div className="space-y-5">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-xl font-semibold text-slate-900">
                            Legal Operations
                        </h1>

                        <p className="max-w-3xl text-sm leading-6 text-slate-500">
                            Manage copyright, platform, ownership and legal
                            support operations from one place. Existing
                            support and ownership workflows are reused so
                            duplicate case systems are not created.
                        </p>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {operations.map((operation) => (
                        <article
                            key={operation.title}
                            className="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-violet-300 hover:shadow-md"
                        >
                            <div className="flex-1">
                                <h2 className="text-base font-semibold text-slate-900">
                                    {operation.title}
                                </h2>

                                <p className="mt-2 text-sm leading-6 text-slate-500">
                                    {operation.description}
                                </p>
                            </div>

                            <Link
                                href={operation.href}
                                className="mt-5 inline-flex items-center justify-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-700"
                            >
                                {operation.action}
                            </Link>
                        </article>
                    ))}
                </section>
            </div>
        </PanelLayout>
    );
}
