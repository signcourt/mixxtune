import { router, usePage } from "@inertiajs/react";
import { Star, X } from "lucide-react";
import { useState } from "react";

/* MIXXTUNE_NOTIFY_TARGETED_V1: highlighted notice from Super Admin, shown until opened or hidden */
const TONE = {
    info: "border-sky-200 bg-sky-50 text-sky-900",
    success: "border-emerald-200 bg-emerald-50 text-emerald-900",
    warning: "border-amber-300 bg-amber-50 text-amber-900",
    danger: "border-red-200 bg-red-50 text-red-900",
};

export default function HighlightNoticeBanner() {
    const notice = usePage().props?.highlightNotice;
    const [busy, setBusy] = useState(false);

    if (!notice?.id) return null;

    const send = (stay) => {
        setBusy(true);
        router.patch(
            `/notifications/${notice.id}/read${stay ? "?stay=1" : ""}`,
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    return (
        <div className={`mb-5 flex flex-col gap-3 rounded-2xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between ${TONE[notice.severity] ?? TONE.info}`}>
            <div className="flex min-w-0 items-start gap-2.5">
                <Star className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <div className="min-w-0">
                    <div className="break-words text-sm font-bold">{notice.title}</div>
                    <div className="mt-0.5 line-clamp-2 break-words text-sm opacity-90">{notice.message}</div>
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => send(!notice.action_url)}
                    className="h-9 rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white disabled:opacity-50"
                >
                    {notice.action_url ? "Open" : "OK, got it"}
                </button>
                {notice.action_url && (
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => send(true)}
                        aria-label="Hide"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-black/5 disabled:opacity-50"
                    >
                        <X className="h-4 w-4" />
                    </button>
                )}
            </div>
        </div>
    );
}
