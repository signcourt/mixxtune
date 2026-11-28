import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/*
 * W238 MIXXTUNE_PROMO_KIT - "Promo kit" of one release:
 * the public listen page (on / off, address, share buttons), the store links, and a share picture made in the browser.
 */

function wrap(ctx, text, maxWidth, maxLines) {
    const words = String(text || "").split(/\s+/).filter(Boolean);
    const lines = [];
    let line = "";
    for (const w of words) {
        const next = line ? `${line} ${w}` : w;
        if (ctx.measureText(next).width > maxWidth && line) {
            lines.push(line);
            line = w;
        } else {
            line = next;
        }
    }
    if (line) lines.push(line);
    if (lines.length > maxLines) {
        const cut = lines.slice(0, maxLines);
        cut[maxLines - 1] = `${cut[maxLines - 1].replace(/.{2}$/, "")}…`;
        return cut;
    }
    return lines;
}

export default function Promo({ role = "label", release, ready = true, eligible = false, brand_name = "", page = null, stores = [], can_find = false }) {
    const w239Props = usePage().props ?? {};
    const flash = w239Props.flash ?? {};
    const w239Errors = w239Props.errors ?? {};
    const startLinks = {};
    stores.forEach((s) => { startLinks[s.key] = s.url || ""; });
    const form = useForm({ enabled: page ? !!page.enabled : true, links: startLinks });
    const [copied, setCopied] = useState(false);
    const [making, setMaking] = useState(false);
    const [picError, setPicError] = useState("");
    const canvasRef = useRef(null);

    const save = (e) => {
        e.preventDefault();
        form.post(`/releases/${release.id}/promo`, { preserveScroll: true });
    };

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(page.url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        } catch (e) {
            setCopied(false);
        }
    };

    const shownStores = stores.filter((s) => (form.data.links[s.key] || s.auto_url)).map((s) => s.label);
    const shareText = `${release.title}${release.artist ? ` - ${release.artist}` : ""} | Out now`;

    const makePicture = async () => {
        setPicError("");
        setMaking(true);
        try {
            const W = 1080, H = 1350;
            const c = canvasRef.current;
            c.width = W; c.height = H;
            const ctx = c.getContext("2d");
            const g = ctx.createLinearGradient(0, 0, 0, H);
            g.addColorStop(0, "#3b1d7a"); g.addColorStop(0.55, "#150f2b"); g.addColorStop(1, "#0e0b1a");
            ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);

            if (release.has_poster) {
                const img = await new Promise((resolve, reject) => {
                    const i = new Image();
                    i.onload = () => resolve(i);
                    i.onerror = () => reject(new Error("poster"));
                    i.src = `${release.poster_url}?t=${Date.now()}`;
                });
                const S = 840, x = (W - S) / 2, y = 90, r = 36;
                ctx.save();
                ctx.beginPath();
                ctx.moveTo(x + r, y); ctx.arcTo(x + S, y, x + S, y + S, r); ctx.arcTo(x + S, y + S, x, y + S, r); ctx.arcTo(x, y + S, x, y, r); ctx.arcTo(x, y, x + S, y, r);
                ctx.closePath(); ctx.clip();
                const side = Math.min(img.width, img.height);
                ctx.drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, x, y, S, S);
                ctx.restore();
            }

            try { await document.fonts.load("700 60px Figtree"); } catch (e) { /* system font is fine */ }
            ctx.textAlign = "center"; ctx.textBaseline = "alphabetic";
            ctx.fillStyle = "#7c3aed";
            const tagW = 220, tagY = 975;
            ctx.beginPath(); ctx.roundRect ? ctx.roundRect((W - tagW) / 2, tagY, tagW, 48, 24) : ctx.rect((W - tagW) / 2, tagY, tagW, 48); ctx.fill();
            ctx.fillStyle = "#ffffff"; ctx.font = "700 24px Figtree, system-ui, sans-serif"; ctx.fillText("OUT NOW", W / 2, tagY + 33);

            ctx.font = "700 60px Figtree, system-ui, sans-serif"; ctx.fillStyle = "#ffffff";
            let y = 1100;
            for (const line of wrap(ctx, release.title, W - 140, 2)) { ctx.fillText(line, W / 2, y); y += 68; }
            if (release.artist) {
                ctx.font = "500 36px Figtree, system-ui, sans-serif"; ctx.fillStyle = "#c9c3e3";
                ctx.fillText(wrap(ctx, release.artist, W - 160, 1)[0] || "", W / 2, y + 2); y += 54;
            }
            if (shownStores.length) {
                ctx.font = "600 26px Figtree, system-ui, sans-serif"; ctx.fillStyle = "#a79fce";
                ctx.fillText(wrap(ctx, `Listen on ${shownStores.join(" · ")}`, W - 120, 1)[0] || "", W / 2, Math.min(y + 14, 1262));
            }
            if (brand_name) {
                ctx.font = "600 22px Figtree, system-ui, sans-serif"; ctx.fillStyle = "#8f88b5";
                ctx.fillText(`Distributed by ${brand_name}`, W / 2, H - 34);
            }

            const blob = await new Promise((resolve) => c.toBlob(resolve, "image/png"));
            if (!blob) throw new Error("picture");
            const a = document.createElement("a");
            a.href = URL.createObjectURL(blob);
            a.download = `${String(release.title || "release").replace(/[^A-Za-z0-9 _-]+/g, "").trim() || "release"} - share.png`;
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 4000);
        } catch (e) {
            setPicError("The picture could not be made. Check that the release has a poster and try again.");
        } finally {
            setMaking(false);
        }
    };

    return (
        <PanelLayout role={role} title="Promo kit" subtitle={`${release.title}${release.artist ? ` · ${release.artist}` : ""}`}>
            <Head title={`Promo kit - ${release.title}`} />

            <div className="mx-auto w-full max-w-5xl space-y-4">
                <Link href={`/releases/${release.id}`} className="inline-block text-sm font-semibold text-violet-700">← Back to the release</Link>

                {flash.success && <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{flash.success}</div>}
                {(form.errors.promo || w239Errors.check) && <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{form.errors.promo || w239Errors.check}</div>}

                {!ready || !eligible ? (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                        <div className="font-bold">The promo page opens once the release is live in stores.</div>
                        <div className="mt-1">This release is "{String(release.status).replaceAll("_", " ")}" right now. Nothing about it is public.</div>
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
                        <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            {release.has_poster ? (
                                <img src={release.poster_url} alt="" className="aspect-square w-full rounded-xl bg-slate-100 object-cover" />
                            ) : (
                                <div className="flex aspect-square w-full items-center justify-center rounded-xl bg-slate-100 text-sm text-slate-500">No poster on this release</div>
                            )}
                            <button type="button" onClick={makePicture} disabled={making || !release.has_poster} className="mt-3 h-11 w-full rounded-xl bg-slate-900 text-sm font-bold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40">
                                {making ? "Making the picture…" : "Download share picture"}
                            </button>
                            <p className="mt-2 text-xs text-slate-500">A 1080 × 1350 picture with the poster, the title and the store names - for WhatsApp status, Instagram or Facebook.</p>
                            {picError && <p className="mt-2 text-xs font-semibold text-rose-600">{picError}</p>}
                            <canvas ref={canvasRef} className="hidden" />
                        </section>

                        <form onSubmit={save} className="space-y-4">
                            <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div className="text-base font-bold text-slate-900">Public listen page</div>
                                        <div className="text-xs text-slate-500">Anyone with the link sees the poster, the title and your store buttons. Nothing else.</div>
                                    </div>
                                    <label className="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" checked={!!form.data.enabled} onChange={(e) => form.setData("enabled", e.target.checked)} className="h-4 w-4 rounded border-slate-300" />
                                        Page is on
                                    </label>
                                </div>

                                {page ? (
                                    <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <div className="break-all text-sm font-semibold text-slate-900" data-keep-wording>{page.url}</div>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <button type="button" onClick={copy} className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-100">{copied ? "Copied" : "Copy link"}</button>
                                            {page.enabled && (
                                                <>
                                                    <a href={page.url} target="_blank" rel="noopener noreferrer" className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-100">Open page</a>
                                                    <a href={`https://wa.me/?text=${encodeURIComponent(`${shareText} ${page.url}`)}`} target="_blank" rel="noopener noreferrer" className="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50">WhatsApp</a>
                                                    <a href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(page.url)}`} target="_blank" rel="noopener noreferrer" className="rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-50">Facebook</a>
                                                </>
                                            )}
                                        </div>
                                        <div className="mt-2 text-xs text-slate-500">{page.enabled ? `Opened ${page.views} time${page.views === 1 ? "" : "s"}.` : "The page is switched off - the link shows nothing."}</div>
                                    </div>
                                ) : (
                                    <div className="mt-4 rounded-xl border border-dashed border-slate-300 px-3 py-3 text-sm text-slate-600">Press Save to make the page. You get its link right after.</div>
                                )}
                            </section>

                            <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="text-base font-bold text-slate-900">Store links</div>
                                    {/* W239: staff can make the Live Links Agent look right now */}
                                    {can_find && (
                                        <button type="button" onClick={() => router.post(`/admin/promo-agent/${release.id}/check`, {}, { preserveScroll: true })} className="rounded-lg border border-violet-300 px-3 py-1.5 text-xs font-bold text-violet-700 hover:bg-violet-50">Find store links now</button>
                                    )}
                                </div>
                                <div className="text-xs text-slate-500">Open the song in each store, copy the address and paste it here. A store with no link gets no button.</div>
                                <div className="mt-3 space-y-3">
                                    {stores.map((s) => (
                                        <div key={s.key}>
                                            <label htmlFor={`w238-${s.key}`} className="block text-xs font-semibold text-slate-700">{s.label}</label>
                                            <input
                                                id={`w238-${s.key}`}
                                                type="url"
                                                inputMode="url"
                                                value={form.data.links[s.key] ?? ""}
                                                onChange={(e) => form.setData("links", { ...form.data.links, [s.key]: e.target.value })}
                                                placeholder={s.auto_url ? s.auto_url : "https://…"}
                                                className="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900"
                                            />
                                            {s.auto_url && !form.data.links[s.key] && <div className="mt-1 break-all text-[11px] text-emerald-700">Found automatically and used: {s.auto_url}</div>}
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <div className="flex justify-end">
                                <button type="submit" disabled={form.processing} className="h-11 rounded-xl bg-violet-600 px-6 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-50">{form.processing ? "Saving…" : "Save"}</button>
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}
