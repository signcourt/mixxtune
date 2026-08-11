import {
    Head,
    Link,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'admin',
    artist = {},
}) {
    return (
        <PanelLayout
            role={role}
            title={artist.stage_name}
            subtitle="Artist account details"
        >
            <Head title={artist.stage_name} />

            <div className="space-y-6">
                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-5">
                        <div>
                            <h1 className="text-2xl font-black text-slate-950">
                                {artist.stage_name}
                            </h1>

                            <p className="mt-1 text-sm text-slate-500">
                                {artist.email}
                            </p>

                            <p className="mt-2 text-sm text-slate-500">
                                {artist.label?.name
                                    ?? 'Independent Artist'}
                            </p>
                        </div>

                        <div className="flex gap-3">
                            <Link
                                href="/v2/admin/artists"
                                className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold"
                            >
                                Back
                            </Link>

                            <Link
                                href={`/v2/admin/artists/${artist.id}/edit`}
                                className="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white"
                            >
                                Edit Artist
                            </Link>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Info
                        label="Legal Name"
                        value={artist.legal_name}
                    />

                    <Info
                        label="Phone"
                        value={artist.phone}
                    />

                    <Info
                        label="Country"
                        value={artist.country}
                    />

                    <Info
                        label="Timezone"
                        value={artist.timezone}
                    />

                    <Info
                        label="Currency"
                        value={artist.currency}
                    />

                    <Info
                        label="Account Status"
                        value={artist.account_status}
                    />

                    <Info
                        label="KYC Status"
                        value={artist.kyc_status}
                    />

                    <Info
                        label="Releases"
                        value={artist.releases_count ?? 0}
                    />

                    <Info
                        label="Assigned Admins"
                        value={
                            (artist.assigned_admins ?? [])
                                .map((admin) => admin.name)
                                .join(', ')
                            || 'Unassigned'
                        }
                    />
                </section>
            </div>
        </PanelLayout>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-2 font-semibold text-slate-900">
                {value ?? 'Not provided'}
            </div>
        </div>
    );
}
