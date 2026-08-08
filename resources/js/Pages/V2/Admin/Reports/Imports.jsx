import {
    Head,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Imports({
    role = 'admin',
    imports = {},
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        report_file: null,
    });

    const submit = (event) => {
        event.preventDefault();

        post(
            '/v2/admin/reports/imports',
            {
                forceFormData: true,

                onSuccess: () => {
                    reset();
                },
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Report Imports"
            subtitle="Upload DSP CSV reports"
        >
            <Head title="Report Imports" />

            <div className="space-y-6">
                <form
                    onSubmit={submit}
                    className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h2 className="text-lg font-semibold text-slate-900">
                        Upload Report CSV
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Maximum file size: 500 MB.
                    </p>

                    <input
                        type="file"
                        accept=".csv,.txt"
                        onChange={(event) =>
                            setData(
                                'report_file',
                                event.target.files?.[0] ??
                                    null
                            )
                        }
                        className="mt-5 block w-full rounded-xl border border-slate-300 p-3 text-sm"
                    />

                    {errors.report_file && (
                        <div className="mt-2 text-sm text-red-600">
                            {errors.report_file}
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={
                            processing ||
                            !data.report_file
                        }
                        className="mt-5 rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {processing
                            ? 'Importing...'
                            : 'Upload & Import'}
                    </button>
                </form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'File',
                                    'Status',
                                    'Total',
                                    'Imported',
                                    'Duplicate',
                                    'Failed',
                                    'Uploaded',
                                ].map((heading) => (
                                    <th
                                        key={heading}
                                        className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {(imports.data ?? []).map(
                                (item) => (
                                    <tr key={item.id}>
                                        <Cell>
                                            {item.original_filename}
                                        </Cell>

                                        <Cell>
                                            {item.status}
                                        </Cell>

                                        <Cell>
                                            {item.total_rows}
                                        </Cell>

                                        <Cell>
                                            {item.imported_rows}
                                        </Cell>

                                        <Cell>
                                            {item.duplicate_rows}
                                        </Cell>

                                        <Cell>
                                            {item.failed_rows}
                                        </Cell>

                                        <Cell>
                                            {item.created_at}
                                        </Cell>
                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
