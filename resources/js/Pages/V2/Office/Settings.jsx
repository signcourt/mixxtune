import { Head, useForm } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';

/* W128 MIXXTUNE_OFFICE_MONITOR: Super Admin sets the monitoring rules */
export default function Settings({ settings }) {
    const { data, setData, post, processing } = useForm({
        monitoring_enabled: !!settings.monitoring_enabled,
        screenshot_minutes: settings.screenshot_minutes,
        idle_minutes: settings.idle_minutes,
        work_start: settings.work_start,
        work_end: settings.work_end,
        rustdesk_host: settings.rustdesk_host || '',
    });
    const inp = 'mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-violet-400';

    return (
        <PanelLayout role="super_admin" title="Office Settings" subtitle="Monitoring, screenshots, idle alerts and live screen">
            <Head title="Office Settings" />
            <PageMessages />
            <div className="max-w-2xl space-y-5">
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <label className="flex items-start gap-3">
                        <input type="checkbox" checked={data.monitoring_enabled} onChange={(e) => setData('monitoring_enabled', e.target.checked)} className="mt-1 h-5 w-5 rounded border-slate-300 text-violet-600" />
                        <span>
                            <span className="font-semibold text-slate-900">Desktop monitoring is {data.monitoring_enabled ? 'ON' : 'OFF'}</span>
                            <span className="block text-sm text-slate-500">When ON, the office computers&apos; agent records active/idle time, the front app and screenshots. Staff see a notice and must agree, and a running indicator shows on their screen. Off by default.</span>
                        </span>
                    </label>
                </div>

                <div className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                    <label className="block"><span className="text-sm font-semibold text-slate-700">Screenshot every (minutes)</span>
                        <input type="number" min={1} max={120} value={data.screenshot_minutes} onChange={(e) => setData('screenshot_minutes', Number(e.target.value))} className={inp} /></label>
                    <label className="block"><span className="text-sm font-semibold text-slate-700">Idle alert after (minutes)</span>
                        <input type="number" min={1} max={180} value={data.idle_minutes} onChange={(e) => setData('idle_minutes', Number(e.target.value))} className={inp} /></label>
                    <label className="block"><span className="text-sm font-semibold text-slate-700">Work start</span>
                        <input type="time" value={data.work_start} onChange={(e) => setData('work_start', e.target.value)} className={inp} /></label>
                    <label className="block"><span className="text-sm font-semibold text-slate-700">Work end</span>
                        <input type="time" value={data.work_end} onChange={(e) => setData('work_end', e.target.value)} className={inp} /></label>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <label className="block"><span className="text-sm font-semibold text-slate-700">RustDesk server (for live screen)</span>
                        <input value={data.rustdesk_host} onChange={(e) => setData('rustdesk_host', e.target.value)} placeholder="e.g. rustdesk.mixxtune.com" className={inp} /></label>
                    <p className="mt-2 text-xs text-slate-500">Free, self-hosted live screen for Windows and Mac. When you connect, a bar shows on the staff member&apos;s screen that they are being viewed. Set the server here after it is installed (next step).</p>
                </div>

                <button onClick={() => post('/office/admin/settings', { preserveScroll: true })} disabled={processing} className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">{processing ? 'Saving…' : 'Save settings'}</button>
            </div>
        </PanelLayout>
    );
}
