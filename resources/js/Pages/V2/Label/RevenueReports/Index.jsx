import { Head, Link } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/* W137 MIXXTUNE_REPORT_COLUMN_PREFS — the label's list of revenue reports */
export default function Index({ reports = [], labelRate = 0 }) {
    return (
        <PanelLayout>
            <Head title="My Revenue Reports" />
            <div className="mx-auto max-w-5xl px-4 py-6">
                <h1 className="text-xl font-bold text-slate-800 dark:text-slate-100">My Revenue Reports</h1>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Your label rate is <b>{(labelRate * 100).toFixed(2)}%</b>. Earning = revenue × your rate.
                </p>

                {reports.length === 0 ? (
                    <div className="mt-8 rounded-xl border border-slate-200 p-8 text-center text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        No reports have been shared with you yet.
                    </div>
                ) : (
                    <div className="mt-5 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-left text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">Report</th>
                                    <th className="px-4 py-3 font-semibold">Month</th>
                                    <th className="px-4 py-3 font-semibold text-right">Rows</th>
                                    <th className="px-4 py-3 font-semibold text-right">Earning (₹)</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {reports.map((r) => (
                                    <tr key={r.id} className="text-slate-700 dark:text-slate-200">
                                        <td className="px-4 py-3">{r.title}</td>
                                        <td className="px-4 py-3">{r.month ? String(r.month).slice(0, 7) : '—'}</td>
                                        <td className="px-4 py-3 text-right">{r.rows}</td>
                                        <td className="px-4 py-3 text-right font-semibold">
                                            {Number(r.earning).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={`/label/revenue-reports/${r.id}`}
                                                className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700"
                                            >
                                                Open
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}
