import { Head, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/* W221 - the distributor's own brand: panel name, small line under it, logo. Plain values only. */
export default function Brand({ role = 'label', distributor = {} }) {
    const { errors = {} } = usePage().props;
    const [name, setName] = useState(distributor.brand_name || distributor.label_name || '');
    const [sub, setSub] = useState(distributor.dashboard_name || '');
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(distributor.logo_url || '');
    const [remove, setRemove] = useState(false);
    const [busy, setBusy] = useState(false);
    const [localErr, setLocalErr] = useState('');
    const fileRef = useRef(null);

    const onFile = (e) => {
        const f = e.target.files && e.target.files[0];
        e.target.value = '';
        if (!f) return;
        if (!/\.(png|jpe?g|webp)$/i.test(f.name)) { setLocalErr('Upload a PNG, JPG or WEBP picture.'); return; }
        if (f.size > 3 * 1024 * 1024) { setLocalErr('The logo must be under 3 MB.'); return; }
        setLocalErr('');
        setFile(f);
        setRemove(false);
        setPreview(URL.createObjectURL(f));
    };

    const save = () => {
        if (busy || name.trim().length < 2) return;
        setBusy(true);
        router.post('/label/distributor/brand', { brand_name: name, dashboard_name: sub, logo: file, remove_logo: remove ? 1 : 0 }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: () => setFile(null),
        });
    };

    return (
        <PanelLayout role={role} title="My Brand" subtitle="The name and logo your accounts see in the panel">
            <Head title="My Brand" />

            {distributor.url && (
                <div className={`mb-5 rounded-2xl border px-5 py-4 text-sm ${distributor.url_ready ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-50 text-slate-600'}`}>
                    {distributor.url_ready
                        ? <>Your own address: <a href={distributor.url} className="font-mono font-semibold underline">{distributor.url.replace('https://', '')}</a>. Give it to your sub-labels - they log in there and see only your name and logo.</>
                        : <>Your own address <span className="font-mono font-semibold">{distributor.url.replace('https://', '')}</span> is being set up. It works within a few minutes; until then use the normal address.</>}
                </div>
            )}

            <div className="grid gap-5 lg:grid-cols-3">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <h2 className="text-lg font-semibold text-slate-900">Panel brand</h2>
                    <p className="mt-1 text-sm text-slate-500">Shown at the top-left of the panel for you and for every sub-label under you ({distributor.sub_labels || 0} now).</p>

                    <label className="mt-5 block text-sm font-semibold text-slate-700">
                        Panel name <span className="text-rose-600">*</span>
                        <input value={name} onChange={(e) => setName(e.target.value)} maxLength={60} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900" />
                        {errors.brand_name && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.brand_name}</span>}
                    </label>

                    <label className="mt-4 block text-sm font-semibold text-slate-700">
                        Small line under the name
                        <input value={sub} onChange={(e) => setSub(e.target.value)} maxLength={60} placeholder="e.g. ARTIST PANEL" className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900" />
                        {errors.dashboard_name && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.dashboard_name}</span>}
                    </label>

                    <div className="mt-4">
                        <div className="text-sm font-semibold text-slate-700">Logo</div>
                        <div className="mt-2 flex flex-wrap items-center gap-4">
                            <div className="grid h-20 w-20 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                {preview && !remove ? <img src={preview} alt="" className="h-full w-full object-contain" /> : <span className="text-xs text-slate-400">No logo</span>}
                            </div>
                            <input ref={fileRef} type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" className="hidden" onChange={onFile} />
                            <button type="button" onClick={() => fileRef.current && fileRef.current.click()} className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Choose picture</button>
                            {(preview && !remove) && <button type="button" onClick={() => { setRemove(true); setFile(null); }} className="text-sm font-semibold text-rose-600 hover:underline">Remove</button>}
                        </div>
                        <p className="mt-2 text-xs text-slate-500">Square PNG / JPG / WEBP, up to 3 MB. 512 × 512 px looks best.</p>
                        {(localErr || errors.logo) && <p className="mt-1 text-xs font-semibold text-rose-600">{localErr || errors.logo}</p>}
                    </div>

                    <div className="mt-6 flex justify-end">
                        <button type="button" disabled={busy || name.trim().length < 2} onClick={save} className="rounded-xl bg-violet-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50">
                            {busy ? 'Saving…' : 'Save brand'}
                        </button>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">Preview</h2>
                    <div className="mt-4 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-900 p-4">
                        <div className="grid h-11 w-11 place-items-center overflow-hidden rounded-lg bg-slate-800">
                            {preview && !remove ? <img src={preview} alt="" className="h-full w-full object-contain" /> : <span className="text-sm font-bold text-white">{name.slice(0, 1).toUpperCase() || '?'}</span>}
                        </div>
                        <div>
                            <div className="text-sm font-bold tracking-wide text-white">{name || 'Your name'}</div>
                            {sub && <div className="text-[10px] font-semibold uppercase tracking-[0.25em] text-slate-400">{sub}</div>}
                        </div>
                    </div>
                    <p className="mt-4 text-xs leading-5 text-slate-500">Releases, reports and wallet work exactly as before. Only the name and logo of the panel change.</p>
                </section>
            </div>
        </PanelLayout>
    );
}
