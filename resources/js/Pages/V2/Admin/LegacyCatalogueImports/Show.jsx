import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    FileSpreadsheet,
    XCircle,
} from 'lucide-react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusStyle = {
    ready: 'bg-emerald-50 text-emerald-700',
    warning: 'bg-amber-50 text-amber-700',
    blocked: 'bg-red-50 text-red-700',
    pending: 'bg-slate-100 text-slate-700',
};

export default function Show({
    catalogueImport,
    dryRun = null,
}) {
    const rows = catalogueImport?.rows ?? [];

    return (
        <PanelLayout
            role="super_admin"
            title="Legacy Catalogue Preview"
            subtitle={catalogueImport?.original_filename ?? 'Import Preview'}
        >
            <Head title="Legacy Catalogue Preview" />

            <div className="space-y-6">
                <div>
                    <Link
                        href="/super-admin/legacy-catalogue-imports"
                        className="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-slate-950"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Legacy Imports
                    </Link>
                </div>

                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-start gap-4">
                        <div className="rounded-2xl bg-violet-50 p-3 text-violet-700">
                            <FileSpreadsheet className="h-6 w-6" />
                        </div>

                        <div>
                            <p className="text-xs font-black uppercase tracking-widest text-violet-600">
                                Validation Preview
                            </p>
                            <h1 className="mt-1 text-2xl font-black text-slate-950">
                                {catalogueImport.original_filename}
                            </h1>
                            <p className="mt-2 text-sm text-slate-500">
                                No live catalogue records are created from this preview page.
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label="Total Rows"
                            value={catalogueImport.total_rows ?? 0}
                            icon={FileSpreadsheet}
                        />

                        <StatCard
                            label="Ready"
                            value={catalogueImport.ready_rows ?? 0}
                            icon={CheckCircle2}
                        />

                        <StatCard
                            label="Warnings"
                            value={catalogueImport.warning_rows ?? 0}
                            icon={AlertTriangle}
                        />

                        <StatCard
                            label="Blocked"
                            value={catalogueImport.blocked_rows ?? 0}
                            icon={XCircle}
                        />
                    </div>
                </section>

                <section className="rounded-3xl border border-blue-200 bg-blue-50/40 p-5 shadow-sm">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-base font-black text-slate-950">
                                Final Import Dry Run
                            </h2>

                            <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-600">
                                Analyse releases, tracks, identifiers and artwork before any live catalogue import.
                            </p>
                        </div>

                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    `/super-admin/legacy-catalogue-imports/${catalogueImport.id}/dry-run`,
                                    {},
                                    {
                                        preserveScroll: true,
                                    }
                                )
                            }
                            className="inline-flex w-fit items-center justify-center rounded-2xl bg-blue-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-800"
                        >
                            Run Final Dry Run
                        </button>
                    </div>

                    {dryRun && (
                        <div className="mt-6">
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <DryStat
                                    label="Release Groups"
                                    value={dryRun.release_groups}
                                />

                                <DryStat
                                    label="New Releases"
                                    value={dryRun.new_releases}
                                />

                                <DryStat
                                    label="New Tracks"
                                    value={dryRun.new_tracks}
                                />

                                <DryStat
                                    label="Artwork Matched"
                                    value={dryRun.artwork_matched_groups}
                                />

                                <DryStat
                                    label="Missing UPC Rows"
                                    value={dryRun.missing_upc_rows}
                                />

                                <DryStat
                                    label="Missing ISRC Rows"
                                    value={dryRun.missing_isrc_rows}
                                />

                                <DryStat
                                    label="Artwork Missing"
                                    value={dryRun.artwork_missing_groups}
                                />

                                <DryStat
                                    label="Conflicts"
                                    value={
                                        (dryRun.upc_conflicts?.length ?? 0) +
                                        (dryRun.isrc_conflicts?.length ?? 0)
                                    }
                                />
                            </div>

                            <div className="mt-5 overflow-x-auto rounded-2xl border border-blue-100 bg-white">
                                <table className="min-w-[1000px] divide-y divide-slate-200">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            {[
                                                'Release',
                                                'UPC',
                                                'Tracks',
                                                'Artwork',
                                                'Status',
                                            ].map((heading) => (
                                                <th
                                                    key={heading}
                                                    className="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                                >
                                                    {heading}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-slate-100">
                                        {(dryRun.release_preview ?? []).map(
                                            (item, index) => (
                                                <tr key={`${item.group_key}-${index}`}>
                                                    <td className="px-4 py-3 text-sm font-bold text-slate-900">
                                                        {item.release_title || '—'}
                                                    </td>

                                                    <td className="px-4 py-3 text-xs font-bold text-slate-700">
                                                        {item.upc || 'MISSING'}
                                                    </td>

                                                    <td className="px-4 py-3 text-sm text-slate-700">
                                                        {item.track_count}
                                                    </td>

                                                    <td className="px-4 py-3 text-sm text-slate-700">
                                                        {item.artwork_path
                                                            ? 'Matched'
                                                            : 'Missing'}
                                                    </td>

                                                    <td className="px-4 py-3 text-sm font-bold text-slate-700">
                                                        {item.status}
                                                    </td>
                                                </tr>
                                            )
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </section>

                <section className="rounded-3xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-base font-black text-slate-950">
                                Missing Label / Artist Approval
                            </h2>

                            <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-600">
                                This creates only catalogue-only Label and Artist records for unmatched rows.
                                No release or track will be imported yet.
                            </p>
                        </div>

                        <button
                            type="button"
                            onClick={() => {
                                if (
                                    window.confirm(
                                        'Create missing catalogue-only Labels and Artists? No releases or tracks will be imported.'
                                    )
                                ) {
                                    router.post(
                                        `/super-admin/legacy-catalogue-imports/${catalogueImport.id}/approve-entities`,
                                        {},
                                        {
                                            preserveScroll: true,
                                        }
                                    );
                                }
                            }}
                            className="inline-flex w-fit items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800"
                        >
                            Approve Missing Entities
                        </button>
                    </div>
                </section>

                {/* FINAL_LIVE_IMPORT_SECTION */}
                <section className="rounded-3xl border border-red-200 bg-red-50/50 p-5 shadow-sm">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div className="max-w-3xl">
                            <h2 className="text-base font-black text-slate-950">
                                Final Live Catalogue Import
                            </h2>

                            <p className="mt-2 text-sm leading-6 text-slate-600">
                                This action permanently creates catalogue
                                releases and tracks from this validated legacy
                                import. Legacy audio files are never attached,
                                and missing UPC or ISRC values remain missing.
                            </p>

                            <div className="mt-4 flex flex-wrap gap-2 text-xs font-bold">
                                <span className="rounded-full bg-white px-3 py-1.5 text-slate-700 ring-1 ring-slate-200">
                                    Rows: {catalogueImport.total_rows ?? 0}
                                </span>

                                <span className="rounded-full bg-white px-3 py-1.5 text-amber-700 ring-1 ring-amber-200">
                                    Warnings: {catalogueImport.warning_rows ?? 0}
                                </span>

                                <span className="rounded-full bg-white px-3 py-1.5 text-red-700 ring-1 ring-red-200">
                                    Blocked: {catalogueImport.blocked_rows ?? 0}
                                </span>

                                <span className="rounded-full bg-white px-3 py-1.5 text-slate-700 ring-1 ring-slate-200">
                                    Status: {catalogueImport.status}
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            disabled={
                                catalogueImport.status !== 'validated' ||
                                Number(catalogueImport.blocked_rows ?? 0) > 0 ||
                                Number(catalogueImport.imported_rows ?? 0) > 0
                            }
                            onClick={() => {
                                const first = window.confirm(
                                    `FINAL LIVE IMPORT\n\nFile: ${catalogueImport.original_filename}\nRows: ${catalogueImport.total_rows ?? 0}\nWarnings: ${catalogueImport.warning_rows ?? 0}\nBlocked: ${catalogueImport.blocked_rows ?? 0}\n\nThis will permanently create catalogue releases and tracks.\n\nContinue?`
                                );

                                if (!first) {
                                    return;
                                }

                                const second = window.confirm(
                                    'FINAL CONFIRMATION\n\nThis import will be committed to the live catalogue.\n\nLegacy audio will NOT be attached.\nMissing UPC/ISRC values will remain missing.\n\nImport now?'
                                );

                                if (!second) {
                                    return;
                                }

                                router.post(
                                    `/super-admin/legacy-catalogue-imports/${catalogueImport.id}/import`,
                                    {},
                                    {
                                        preserveScroll: true,
                                    }
                                );
                            }}
                            className="inline-flex w-fit shrink-0 items-center justify-center rounded-2xl bg-red-700 px-6 py-3 text-sm font-black text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                        >
                            {catalogueImport.status === 'imported'
                                ? `Imported ${catalogueImport.imported_rows ?? 0} Rows`
                                : 'Import Now'}
                        </button>
                    </div>

                    {catalogueImport.status === 'imported' && (
                        <div className="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                            <p className="text-sm font-black text-emerald-800">
                                Import completed successfully.
                            </p>

                            <p className="mt-1 text-xs leading-5 text-emerald-700">
                                {catalogueImport.imported_rows ?? 0} rows
                                were committed to the catalogue.
                            </p>
                        </div>
                    )}
                </section>

                <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-3">
                        <Policy
                            title="Missing ISRC"
                            text="If missing, it stays missing. No identifier is auto-generated."
                        />
                        <Policy
                            title="Missing UPC"
                            text="If missing, it stays missing. No identifier is auto-generated."
                        />
                        <Policy
                            title="Audio"
                            text="Not part of legacy catalogue import."
                        />
                    </div>
                </section>

                <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h2 className="text-lg font-black text-slate-950">
                            Validation Rows
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Previewing up to 500 staged rows.
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-[1200px] divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        '#',
                                        'Status',
                                        'Label',
                                        'Release',
                                        'Artist',
                                        'Label Match',
                                        'Artist Match',
                                        'Track',
                                        'ISRC',
                                        'UPC',
                                        'Artwork',
                                        'Issues',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.map((row) => {
                                    const errors =
                                        row.validation_errors ?? [];

                                    const warnings =
                                        row.validation_warnings ?? [];

                                    return (
                                        <tr
                                            key={row.id}
                                            className="align-top hover:bg-slate-50"
                                        >
                                            <td className="px-4 py-4 text-sm font-bold text-slate-700">
                                                {row.row_number}
                                            </td>

                                            <td className="px-4 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${
                                                        statusStyle[
                                                            row.validation_status
                                                        ] ??
                                                        statusStyle.pending
                                                    }`}
                                                >
                                                    {row.validation_status}
                                                </span>
                                            </td>

                                            <Cell value={row.label_name} />
                                            <Cell value={row.release_title} />
                                            <Cell value={row.primary_artist} />

                                            <ResolutionCell
                                                matched={Boolean(
                                                    row.resolved_label_id
                                                )}
                                                matchedText="Existing Label"
                                                missingText="New Label Needed"
                                            />

                                            <ResolutionCell
                                                matched={Boolean(
                                                    row.resolved_artist_id
                                                )}
                                                matchedText="Existing Artist"
                                                missingText={
                                                    row.resolved_label_id
                                                        ? 'New Artist Needed'
                                                        : 'Waiting for Label'
                                                }
                                            />

                                            <Cell value={row.track_title} />
                                            <CodeCell value={row.isrc} />
                                            <CodeCell value={row.upc} />
                                            <Cell
                                                value={
                                                    row.artwork_filename ||
                                                    'Missing'
                                                }
                                            />

                                            <td className="max-w-[360px] px-4 py-4">
                                                {errors.length === 0 &&
                                                warnings.length === 0 ? (
                                                    <span className="text-sm font-semibold text-emerald-700">
                                                        Ready
                                                    </span>
                                                ) : (
                                                    <div className="space-y-2">
                                                        {errors.map(
                                                            (message, index) => (
                                                                <p
                                                                    key={`e-${index}`}
                                                                    className="text-xs font-semibold text-red-600"
                                                                >
                                                                    {message}
                                                                </p>
                                                            )
                                                        )}

                                                        {warnings.map(
                                                            (message, index) => (
                                                                <p
                                                                    key={`w-${index}`}
                                                                    className="text-xs font-semibold text-amber-600"
                                                                >
                                                                    {message}
                                                                </p>
                                                            )
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {rows.length === 0 && (
                        <div className="px-6 py-12 text-center text-sm text-slate-500">
                            No staged rows found.
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}

function StatCard({ label, value, icon: Icon }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <Icon className="h-5 w-5 text-slate-600" />
            <p className="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </p>
            <p className="mt-1 text-2xl font-black text-slate-950">
                {value}
            </p>
        </div>
    );
}

function Policy({ title, text }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p className="text-sm font-black text-slate-900">
                {title}
            </p>
            <p className="mt-1 text-xs leading-5 text-slate-500">
                {text}
            </p>
        </div>
    );
}

function DryStat({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-blue-100 bg-white p-4">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </p>

            <p className="mt-2 text-2xl font-black text-slate-950">
                {value ?? 0}
            </p>
        </div>
    );
}

function ResolutionCell({
    matched,
    matchedText,
    missingText,
}) {
    return (
        <td className="px-4 py-4">
            <span
                className={`inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ${
                    matched
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-amber-50 text-amber-700'
                }`}
            >
                {matched ? matchedText : missingText}
            </span>
        </td>
    );
}

function Cell({ value }) {
    return (
        <td className="max-w-[220px] px-4 py-4 text-sm text-slate-700">
            {value || '—'}
        </td>
    );
}

function CodeCell({ value }) {
    return (
        <td className="px-4 py-4">
            {value ? (
                <code className="rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-800">
                    {value}
                </code>
            ) : (
                <span className="text-xs font-bold text-red-600">
                    Missing
                </span>
            )}
        </td>
    );
}
