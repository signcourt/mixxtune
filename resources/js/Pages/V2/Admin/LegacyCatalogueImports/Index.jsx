import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    FileSpreadsheet,
    ShieldCheck,
    UploadCloud,
    XCircle,
} from 'lucide-react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const badgeClass = (status) => {
    if (status === 'validated') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (status === 'validated_with_blocks') {
        return 'bg-amber-50 text-amber-700';
    }

    if (status === 'failed') {
        return 'bg-red-50 text-red-700';
    }

    return 'bg-slate-100 text-slate-700';
};

export default function Index({ imports = [] }) {
    const form = useForm({
        catalogue_file: null,
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            '/super-admin/legacy-catalogue-imports',
            {
                forceFormData: true,
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role="super_admin"
            title="Legacy Catalogue Import"
            subtitle="Bulk metadata import for previous catalogue"
        >
            <Head title="Legacy Catalogue Import" />

            <div className="space-y-6">
                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div className="flex items-center gap-2">
                                <ShieldCheck className="h-5 w-5 text-violet-600" />
                                <span className="text-xs font-black uppercase tracking-widest text-violet-600">
                                    Super Admin Only
                                </span>
                            </div>

                            <h1 className="mt-3 text-2xl font-black text-slate-950">
                                Legacy Catalogue Bulk Upload
                            </h1>

                            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                                Upload CSV, XLS or XLSX metadata for previous catalogue.
                                Audio is intentionally excluded from this workflow.
                                Missing ISRC or UPC will remain empty and will never be generated automatically.
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 grid gap-3 md:grid-cols-3">
                        <Rule
                            icon={CheckCircle2}
                            title="Identifiers preserved"
                            text="Existing ISRC and UPC values are used as supplied."
                        />

                        <Rule
                            icon={XCircle}
                            title="No auto-generation"
                            text="Missing ISRC or UPC is never generated automatically."
                        />

                        <Rule
                            icon={AlertCircle}
                            title="No audio upload"
                            text="Legacy metadata import does not upload or require audio."
                        />
                    </div>
                </section>

                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center gap-3">
                        <div className="rounded-2xl bg-violet-50 p-3 text-violet-700">
                            <UploadCloud className="h-5 w-5" />
                        </div>

                        <div>
                            <h2 className="text-lg font-black text-slate-950">
                                Upload Catalogue File
                            </h2>
                            <p className="text-sm text-slate-500">
                                Accepted formats: CSV, XLS, XLSX
                            </p>
                        </div>
                    </div>

                    <form
                        onSubmit={submit}
                        className="mt-6"
                    >
                        <label className="block cursor-pointer rounded-3xl border-2 border-dashed border-slate-300 bg-slate-50 p-8 text-center transition hover:border-violet-400 hover:bg-violet-50/30">
                            <FileSpreadsheet className="mx-auto h-10 w-10 text-slate-400" />

                            <p className="mt-3 text-sm font-bold text-slate-800">
                                Choose catalogue file
                            </p>

                            <p className="mt-1 text-xs text-slate-500">
                                Maximum 50 MB
                            </p>

                            <input
                                type="file"
                                accept=".csv,.xls,.xlsx"
                                className="sr-only"
                                onChange={(event) =>
                                    form.setData(
                                        'catalogue_file',
                                        event.target.files?.[0] ?? null
                                    )
                                }
                            />
                        </label>

                        {form.data.catalogue_file && (
                            <div className="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <p className="text-sm font-bold text-slate-900">
                                    {form.data.catalogue_file.name}
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    Selected for validation
                                </p>
                            </div>
                        )}

                        {form.errors.catalogue_file && (
                            <p className="mt-3 text-sm font-semibold text-red-600">
                                {form.errors.catalogue_file}
                            </p>
                        )}

                        <div className="mt-5 flex justify-end">
                            <button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    !form.data.catalogue_file
                                }
                                className="inline-flex items-center gap-2 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <UploadCloud className="h-4 w-4" />
                                {form.processing
                                    ? 'Validating...'
                                    : 'Upload & Validate'}
                            </button>
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h2 className="text-lg font-black text-slate-950">
                            Recent Imports
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Latest staged catalogue files and validation results.
                        </p>
                    </div>

                    {imports.length === 0 ? (
                        <div className="px-6 py-12 text-center text-sm text-slate-500">
                            No legacy catalogue imports yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {[
                                            'File',
                                            'Status',
                                            'Total',
                                            'Ready',
                                            'Warning',
                                            'Blocked',
                                            'Action',
                                        ].map((heading) => (
                                            <th
                                                key={heading}
                                                className="whitespace-nowrap px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                            >
                                                {heading}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-100">
                                    {imports.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-slate-50"
                                        >
                                            <td className="px-5 py-4">
                                                <p className="text-sm font-bold text-slate-900">
                                                    {item.original_filename}
                                                </p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {item.public_id}
                                                </p>
                                            </td>

                                            <td className="px-5 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${badgeClass(
                                                        item.status
                                                    )}`}
                                                >
                                                    {item.status}
                                                </span>
                                            </td>

                                            <td className="px-5 py-4 text-sm text-slate-700">
                                                {item.total_rows ?? 0}
                                            </td>

                                            <td className="px-5 py-4 text-sm font-bold text-emerald-700">
                                                {item.ready_rows ?? 0}
                                            </td>

                                            <td className="px-5 py-4 text-sm font-bold text-amber-700">
                                                {item.warning_rows ?? 0}
                                            </td>

                                            <td className="px-5 py-4 text-sm font-bold text-red-700">
                                                {item.blocked_rows ?? 0}
                                            </td>

                                            <td className="px-5 py-4">
                                                <Link
                                                    href={`/super-admin/legacy-catalogue-imports/${item.id}`}
                                                    className="text-sm font-bold text-violet-700 hover:text-violet-900"
                                                >
                                                    Preview
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}

function Rule({ icon: Icon, title, text }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <Icon className="h-5 w-5 text-slate-700" />
            <p className="mt-3 text-sm font-black text-slate-900">
                {title}
            </p>
            <p className="mt-1 text-xs leading-5 text-slate-500">
                {text}
            </p>
        </div>
    );
}
