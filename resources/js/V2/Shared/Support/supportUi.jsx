/* MIXXTUNE_SUPPORT_DESK_V1: shared labels and small pieces for support pages */
import { Paperclip } from "lucide-react";

export const STATUS = {
    open: { staff: "New", owner: "Sent — waiting for us", cls: "bg-sky-50 text-sky-700" },
    customer_reply: { staff: "Customer replied", owner: "Sent — waiting for us", cls: "bg-amber-50 text-amber-800" },
    admin_reply: { staff: "We replied", owner: "We replied — your turn", cls: "bg-violet-50 text-violet-700" },
    waiting: { staff: "Waiting for customer", owner: "We need something from you", cls: "bg-orange-50 text-orange-700" },
    closed: { staff: "Closed", owner: "Closed", cls: "bg-slate-100 text-slate-600" },
};

export const PRIORITY = {
    low: { label: "Low", cls: "bg-slate-100 text-slate-600" },
    normal: { label: "Normal", cls: "bg-slate-100 text-slate-700" },
    high: { label: "High", cls: "bg-orange-50 text-orange-700" },
    urgent: { label: "Urgent", cls: "bg-red-50 text-red-700" },
};

export function StatusPill({ status, side = "staff" }) {
    const s = STATUS[status] ?? { staff: status, owner: status, cls: "bg-slate-100 text-slate-600" };
    return <span className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold ${s.cls}`}>{s[side]}</span>;
}

export function PriorityPill({ priority }) {
    const p = PRIORITY[priority] ?? PRIORITY.normal;
    return <span className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold ${p.cls}`}>{p.label}</span>;
}

const size = (b) => (!b ? "" : b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

export function Message({ m, ownerSide = false }) {
    const mine = ownerSide ? !m.from_staff : m.from_staff;
    const tone = m.is_internal
        ? "border-amber-200 bg-amber-50"
        : mine
          ? "border-violet-200 bg-violet-50"
          : "border-slate-200 bg-white";

    return (
        <div className={`rounded-2xl border p-4 shadow-sm ${tone} ${mine ? "sm:ml-10" : "sm:mr-10"}`}>
            <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                <span className="font-semibold text-slate-800">
                    {m.author}
                    {m.from_staff && !ownerSide && !m.is_internal ? " · team" : ""}
                    {ownerSide && m.from_staff ? " · Mixx Tune support" : ""}
                </span>
                <span className="text-slate-500">
                    {m.is_internal && <b className="mr-2 text-amber-700">Internal note — customer cannot see</b>}
                    {m.created_at}
                </span>
            </div>
            <div className="mt-2 whitespace-pre-wrap break-words text-sm text-slate-700">{m.message}</div>
            {m.attachments?.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {m.attachments.map((f) => (
                        <a key={f.url} href={f.url} className="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-violet-300">
                            <Paperclip className="h-3.5 w-3.5 shrink-0" />
                            <span className="truncate">{f.name}</span>
                            <span className="shrink-0 font-normal text-slate-400">{size(f.size)}</span>
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}

export function FilePicker({ files, onChange, error }) {
    return (
        <div>
            <label className="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <Paperclip className="h-3.5 w-3.5" /> Attach files
                <input
                    type="file"
                    multiple
                    accept=".jpg,.jpeg,.png,.pdf,.csv,.txt,.xlsx,.zip,.wav,.mp3"
                    className="hidden"
                    onChange={(e) => onChange(Array.from(e.target.files ?? []).slice(0, 5))}
                />
            </label>
            <span className="ml-2 text-xs text-slate-500">
                {files.length ? files.map((f) => f.name).join(", ") : "Up to 5 files · 20 MB each · JPG, PNG, PDF, CSV, XLSX, ZIP, WAV, MP3"}
            </span>
            {error && <div className="mt-1 text-xs font-medium text-red-600">{error}</div>}
        </div>
    );
}
