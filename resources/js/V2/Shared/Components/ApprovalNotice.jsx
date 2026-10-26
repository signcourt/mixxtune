import { Link, usePage } from "@inertiajs/react";
import { ShieldCheck, X } from "lucide-react";
import { useEffect, useState } from "react";

/* W118 MIXXTUNE_MAKER_CHECKER: on any page, show why a money action is waiting for a second approval */
export default function ApprovalNotice() {
    const page = usePage();
    const message = page.props?.errors?.approval;
    const [shown, setShown] = useState(null);
    useEffect(() => {
        if (message) setShown(message);
    }, [page, message]);
    if (!shown || page.component === "V2/Admin/Approvals/Index") return null;

    return (
        <div role="status" className="fixed inset-x-3 bottom-4 z-[60] mx-auto flex max-w-xl items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-xl">
            <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0" />
            <div className="min-w-0 flex-1">
                <div>{shown}</div>
                <Link href="/admin/approvals" className="mt-1 inline-block text-xs font-bold underline">
                    Open Second approval
                </Link>
            </div>
            <button type="button" onClick={() => setShown(null)} aria-label="Close" className="rounded p-1 hover:bg-amber-100">
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
