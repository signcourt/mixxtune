import { Head, Link } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/* W137 MIXXTUNE_REPORT_COLUMN_PREFS — one report: shared columns + Label Rate + Earning */
export default function Show({ report = {}, header = [], rows = [], totalEarning = 0 }) {
    return (
        <PanelLayout>
            <Head title={`Report — ${report.title || ''}`} />
            <div className="mx-auto max-w-6xl px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Link href="/label/revenue-reports" className="text-xs text-slate-500 hover:underline dark:text-slate-400">
                            ← All reports
                        </Link>
                        <h1 className="mt-1 text-xl font-bold text-slate-800 dark:text-slate-100">{report.title}</h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            {report.month ? String(report.month).slice(0, 7) : ''} · Total earning ₹
                            {Number(totalEarning).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </p>
                    </div>
                    <a
                        href={`/label/revenue-reports/${report.id}/export`}
                        className="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"
                    >
                        Download CSV
                    </a>
                </div>

                <div className="mt-5 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                {header.map((h) => (
                                    <th key={h} className="whitespace-nowrap px-3 py-2.5 font-semibold">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {rows.map((row, i) => (
                                <tr key={i} className="text-slate-700 dark:text-slate-200">
                                    {header.map((h) => (
                                        <td key={h} className="whitespace-nowrap px-3 py-2">
                                            {row[h] ?? ''}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {rows.length === 0 && (
                    <p className="mt-4 text-center text-sm text-slate-500">No rows.</p>
                )}
            </div>
        </PanelLayout>
    );
}
