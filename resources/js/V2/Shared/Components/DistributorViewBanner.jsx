import { router, usePage } from "@inertiajs/react";

/* W237: shown while a distributor has one of his sub-labels' panels open (view only) */
export default function DistributorViewBanner() {
    const { auth = {}, distributorView = null } = usePage().props;
    if (!distributorView?.active) return null;

    return (
        <div className="sticky top-0 z-[100] border-b border-sky-600 bg-sky-500 px-4 py-3 text-white shadow-md" data-keep-wording>
            <div className="mx-auto flex w-full flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="text-sm font-bold">Viewing a sub-label · view only</div>
                    <div className="text-xs font-medium">
                        You are looking at the panel of <strong>{auth?.user?.name ?? "your sub-label"}</strong>. Nothing can be changed from here.
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => router.post("/label/distributor/view/stop", {}, { preserveScroll: false })}
                    className="rounded-xl bg-white px-4 py-2 text-sm font-bold text-sky-700 shadow hover:bg-sky-50"
                >
                    Back to my account{distributorView.owner ? ` (${distributorView.owner})` : ""}
                </button>
            </div>
        </div>
    );
}
