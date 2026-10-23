import React, {
    useMemo,
    useRef,
    useState,
} from "react";
import { router } from "@inertiajs/react";

/* W54: status words + colours used by the list and the review */
const STATUS = {
    review_required: ["Waiting for review", "bg-amber-100 text-amber-700"],
    approved: ["Approved - publish next", "bg-blue-100 text-blue-700"],
    published: ["Published to ledger", "bg-emerald-100 text-emerald-700"],
};
const statusInfo = (s) =>
    STATUS[s] || [String(s || "unknown").replaceAll("_", " "), "bg-slate-100 text-slate-600"];

const csrfToken = () =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";

const money = (value, currency = "INR") => {
    const number = Number(value || 0);

    try {
        return new Intl.NumberFormat("en-IN", {
            style: "currency",
            currency,
            maximumFractionDigits: 2,
        }).format(number);
    } catch {
        return `${currency} ${number.toFixed(2)}`;
    }
};

const apiErrors = async (response) => {
    let payload = {};

    try {
        payload = await response.json();
    } catch {
        // Keep generic fallback below.
    }

    if (payload?.errors) {
        const first = Object.values(
            payload.errors
        )
            .flat()
            .filter(Boolean)[0];

        if (first) {
            return String(first);
        }
    }

    return (
        payload?.message ||
        `Request failed (${response.status})`
    );
};

export default function RightsRoyaltyUploadPanel({ role = "label", imports = [] }) {
    const isSuperAdmin = role === "super_admin";

    /*
     * MIXXTUNE_RIGHTS_ROYALTY_UPLOAD_UI_V1
     *
     * Upload → inspect headers → map columns →
     * process → review.
     *
     * Review -> approve -> publish ledger lifecycle.
     *
     * Wallet posting remains intentionally separate.
     */

    const fileRef = useRef(null);

    const [open, setOpen] = useState(false);
    const [step, setStep] = useState(1);

    const [source, setSource] =
        useState("IPRS");

    const [statementMonth, setStatementMonth] =
        useState("");

    const [currency, setCurrency] =
        useState("INR");

    const [file, setFile] =
        useState(null);

    const [inspection, setInspection] =
        useState(null);

    const [mapping, setMapping] = useState({
        isrc: "",
        amount: "",
        track_title: "",
        rights_type: "",
        territory: "",
    });

    const [result, setResult] =
        useState(null);

    const [rows, setRows] =
        useState([]);

    const [pagination, setPagination] =
        useState(null);

    const [busy, setBusy] =
        useState(false);

    const [error, setError] =
        useState("");

    /* W54: e-mailed code box for approve / publish */
    const [otpFor, setOtpFor] = useState(null);
    const [otpMessage, setOtpMessage] = useState("");
    const [otpCode, setOtpCode] = useState("");
    const [otpInfo, setOtpInfo] = useState("");

    const headers = useMemo(
        () => inspection?.headers || [],
        [inspection]
    );

    const reset = () => {
        setStep(1);
        setSource("IPRS");
        setStatementMonth("");
        setCurrency("INR");
        setFile(null);
        setInspection(null);

        setMapping({
            isrc: "",
            amount: "",
            track_title: "",
            rights_type: "",
            territory: "",
        });

        setResult(null);
        setRows([]);
        setPagination(null);
        setError("");
        setOtpFor(null);
        setOtpMessage("");
        setOtpCode("");
        setOtpInfo("");

        if (fileRef.current) {
            fileRef.current.value = "";
        }
    };

    const close = () => {
        if (busy) return;

        setOpen(false);
        reset();

        /* W54: list shows the new / changed report at once */
        router.reload({ only: ["rightsImports"], preserveScroll: true });
    };

    /* W54: open an earlier upload straight at its review */
    const openImport = async (publicId) => {
        reset();
        setOpen(true);
        setStep(3);
        setBusy(true);

        try {
            await loadReview(publicId);
        } catch (e) {
            setError(e?.message || "Unable to open this report.");
        } finally {
            setBusy(false);
        }
    };

    const inspectFile = async () => {
        setError("");

        if (!file) {
            setError(
                "Please select a CSV or Excel report."
            );
            return;
        }

        setBusy(true);

        try {
            const body = new FormData();
            body.append("report_file", file);

            const response = await fetch(
                "/admin/royalties/rights/inspect",
                {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN":
                            csrfToken(),
                        Accept: "application/json",
                    },
                    body,
                    credentials: "same-origin",
                }
            );

            if (!response.ok) {
                throw new Error(
                    await apiErrors(response)
                );
            }

            const data =
                await response.json();

            setInspection(data);

            setMapping({
                isrc:
                    data?.suggested_mapping
                        ?.isrc || "",

                amount:
                    data?.suggested_mapping
                        ?.amount || "",

                track_title:
                    data?.suggested_mapping
                        ?.track_title || "",

                rights_type:
                    data?.suggested_mapping
                        ?.rights_type || "",

                territory:
                    data?.suggested_mapping
                        ?.territory || "",
            });

            setStep(2);
        } catch (e) {
            setError(
                e?.message ||
                    "Unable to inspect report."
            );
        } finally {
            setBusy(false);
        }
    };

    const processReport = async () => {
        setError("");

        if (!statementMonth) {
            setError(
                "Statement month is required."
            );
            return;
        }

        if (!mapping.isrc) {
            setError(
                "Please map the ISRC column."
            );
            return;
        }

        if (!mapping.amount) {
            setError(
                "Please map the Amount column."
            );
            return;
        }

        setBusy(true);

        try {
            const body = new FormData();

            body.append("source", source);
            body.append(
                "statement_month",
                statementMonth
            );
            body.append(
                "currency",
                currency.toUpperCase()
            );
            body.append(
                "report_file",
                file
            );

            Object.entries(mapping).forEach(
                ([key, value]) => {
                    if (value) {
                        body.append(
                            `mapping[${key}]`,
                            value
                        );
                    }
                }
            );

            const response = await fetch(
                "/admin/royalties/rights/imports",
                {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN":
                            csrfToken(),
                        Accept: "application/json",
                    },
                    body,
                    credentials: "same-origin",
                }
            );

            if (!response.ok) {
                throw new Error(
                    await apiErrors(response)
                );
            }

            const data =
                await response.json();

            setResult(data.import);

            await loadReview(
                data.import.public_id
            );

            setStep(3);
        } catch (e) {
            setError(
                e?.message ||
                    "Unable to process report."
            );
        } finally {
            setBusy(false);
        }
    };

    const loadReview = async (
        publicId,
        page = 1
    ) => {
        const response = await fetch(
            `/admin/royalties/rights/imports/${encodeURIComponent(
                publicId
            )}?page=${page}`,
            {
                headers: {
                    Accept: "application/json",
                },
                credentials: "same-origin",
            }
        );

        if (!response.ok) {
            throw new Error(
                await apiErrors(response)
            );
        }

        const data = await response.json();

        setResult(data.import);
        setRows(data?.rows?.data || []);
        setPagination(data.rows || null);
    };

    /*
     * MIXXTUNE_RIGHTS_LIFECYCLE_UI_V1
     *
     * Super Admin only.
     * Approval freezes mapping/allocation snapshots.
     * Publishing creates royalty ledgers only.
     * No wallet balance mutation happens here.
     */
    const runLifecycleAction = async (action) => {
        if (
            !isSuperAdmin ||
            !result?.public_id ||
            busy
        ) {
            return;
        }

        setError("");
        setBusy(true);

        try {
            const response = await fetch(
                `/admin/royalties/rights/imports/${encodeURIComponent(
                    result.public_id
                )}/${action}`,
                {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN":
                            csrfToken(),
                        Accept: "application/json",
                    },
                    credentials: "same-origin",
                }
            );

            if (!response.ok) {
                /* W54: a code was e-mailed - show the code box */
                if (response.status === 422) {
                    let payload = {};
                    try {
                        payload = await response.clone().json();
                    } catch {
                        payload = {};
                    }
                    const otpError = payload?.errors?.action_otp;
                    if (otpError) {
                        setOtpFor(action);
                        setOtpMessage(Array.isArray(otpError) ? otpError[0] : String(otpError));
                        setOtpCode("");
                        setOtpInfo("");
                        return;
                    }
                }

                throw new Error(
                    await apiErrors(response)
                );
            }

            const data =
                await response.json();

            setOtpFor(null);

            if (data?.import) {
                setResult(data.import);
            }

            /*
             * Reload review after lifecycle transition so
             * status + source rows remain server-authoritative.
             */
            await loadReview(
                result.public_id,
                pagination?.current_page || 1
            );
        } catch (e) {
            setError(
                e?.message ||
                    `Unable to ${action} report.`
            );
        } finally {
            setBusy(false);
        }
    };

    /* W54: enter the code, then the same action runs again by itself */
    const confirmOtp = async () => {
        if (!otpFor || busy) return;
        setError("");
        setOtpInfo("");
        setBusy(true);
        let ok = false;
        try {
            const response = await fetch("/admin/action-otp/unlock", {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": csrfToken(),
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                credentials: "same-origin",
                body: JSON.stringify({ otp: otpCode.trim() }),
            });
            if (!response.ok) {
                setOtpInfo(await apiErrors(response));
            } else {
                ok = true;
            }
        } catch (e) {
            setOtpInfo(e?.message || "Could not check the code.");
        } finally {
            setBusy(false);
        }
        if (ok) {
            const action = otpFor;
            setOtpFor(null);
            await runLifecycleAction(action);
        }
    };

    const resendOtp = async () => {
        if (busy) return;
        setOtpInfo("");
        try {
            const response = await fetch("/admin/action-otp/resend", {
                method: "POST",
                headers: { "X-CSRF-TOKEN": csrfToken(), Accept: "application/json" },
                credentials: "same-origin",
            });
            const data = response.ok ? await response.json() : null;
            setOtpInfo(data?.message || (await apiErrors(response)));
        } catch (e) {
            setOtpInfo(e?.message || "Could not send a new code.");
        }
    };

    const approveReport = () =>
        runLifecycleAction("approve");

    const publishReport = () =>
        runLifecycleAction("publish");

    const mappedPercent = result?.total_rows
        ? Math.round(
              (Number(result.mapped_rows || 0) /
                  Number(result.total_rows)) *
                  100
          )
        : 0;

    return (
        <>
            {isSuperAdmin && (
            <div className="mb-6 rounded-2xl border border-violet-200 bg-gradient-to-r from-violet-50 via-white to-indigo-50 p-5 shadow-sm">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div className="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">
                            Rights Royalties
                        </div>

                        <h2 className="mt-1 text-xl font-bold text-slate-900">
                            IPRS / PRS / TPL Royalty Reports
                        </h2>

                        <p className="mt-1 max-w-3xl text-sm text-slate-600">
                            Upload a rights statement,
                            map its columns and review
                            ISRC-based catalogue
                            allocations before any
                            financial publishing.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => {
                            reset();
                            setOpen(true);
                        }}
                        className="inline-flex items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-violet-700"
                    >
                        + Upload Royalty Report
                    </button>
                </div>
            </div>
            )}

            {/* W54: every uploaded rights report, so an earlier one can be opened, approved and published */}
            {isSuperAdmin && (
                <div className="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                        <div>
                            <h3 className="text-base font-bold text-slate-900">Uploaded rights reports</h3>
                            <p className="mt-0.5 text-xs text-slate-500">
                                Open a report to check its matching, approve it and publish it to the royalty ledger.
                            </p>
                        </div>
                        <div className="text-xs font-semibold text-slate-500">
                            {imports.length} report(s)
                        </div>
                    </div>

                    {imports.length === 0 ? (
                        <div className="px-5 py-10 text-center text-sm text-slate-500">
                            No rights report uploaded yet. Press <b>+ Upload Royalty Report</b> to add an IPRS / PRS / TPL file.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-5 py-3">Report</th>
                                        <th className="px-5 py-3">Month</th>
                                        <th className="px-5 py-3 text-right">Rows matched</th>
                                        <th className="px-5 py-3 text-right">Amount</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {imports.map((item) => {
                                        const [label, tone] = statusInfo(item.status);
                                        return (
                                            <tr key={item.public_id} className="hover:bg-slate-50">
                                                <td className="px-5 py-3">
                                                    <div className="font-semibold text-slate-900">{item.source}</div>
                                                    <div className="max-w-[260px] truncate text-xs text-slate-500" title={item.file}>
                                                        {item.file} · {item.uploaded}
                                                    </div>
                                                </td>
                                                <td className="px-5 py-3 font-medium text-slate-700">{item.statement_month}</td>
                                                <td className="px-5 py-3 text-right text-slate-700">
                                                    {Number(item.mapped_rows).toLocaleString("en-IN")} / {Number(item.total_rows).toLocaleString("en-IN")}
                                                    {item.unmapped_rows > 0 && (
                                                        <div className="text-xs text-amber-700">
                                                            {Number(item.unmapped_rows).toLocaleString("en-IN")} not matched
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-5 py-3 text-right">
                                                    <div className="font-semibold text-slate-900">{money(item.gross_amount, item.currency)}</div>
                                                    {item.unmapped_amount > 0 && (
                                                        <div className="text-xs text-amber-700">
                                                            {money(item.unmapped_amount, item.currency)} not matched
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-5 py-3">
                                                    <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${tone}`}>{label}</span>
                                                    {(item.published || item.approved) && (
                                                        <div className="mt-1 text-xs text-slate-400">
                                                            {item.published ? `published ${item.published}` : `approved ${item.approved}`}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-5 py-3 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => openImport(item.public_id)}
                                                        className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                    >
                                                        {item.status === "review_required" ? "Review" : item.status === "approved" ? "Publish" : "Open"}
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}

            {open && isSuperAdmin && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm">
                    <div className="max-h-[92vh] w-full max-w-6xl overflow-hidden rounded-3xl bg-white shadow-2xl">
                        <div className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                            <div>
                                <h3 className="text-xl font-bold text-slate-900">
                                    {step === 3 && result
                                        ? `${result.source} rights report · ${result.statement_month}`
                                        : "Upload Royalty Report"}
                                </h3>

                                <p className="mt-1 text-sm text-slate-500">
                                    Step {step} of 3
                                    {" — "}
                                    {step === 1
                                        ? "Report Details"
                                        : step === 2
                                          ? "Column Mapping"
                                          : "Mapping Review"}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={close}
                                disabled={busy}
                                className="rounded-lg px-3 py-2 text-sm font-bold text-slate-500 hover:bg-slate-100"
                            >
                                Close
                            </button>
                        </div>

                        <div className="flex gap-2 border-b border-slate-100 px-6 py-4">
                            {[1, 2, 3].map(
                                (number) => (
                                    <div
                                        key={number}
                                        className={[
                                            "h-2 flex-1 rounded-full",
                                            number <= step
                                                ? "bg-violet-600"
                                                : "bg-slate-200",
                                        ].join(" ")}
                                    />
                                )
                            )}
                        </div>

                        <div className="max-h-[72vh] overflow-y-auto p-6">
                            {error && (
                                <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                                    {error}
                                </div>
                            )}

                            {step === 1 && (
                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field label="Royalty Source">
                                        <select
                                            value={source}
                                            onChange={(e) =>
                                                setSource(
                                                    e.target
                                                        .value
                                                )
                                            }
                                            className="w-full rounded-xl border-slate-300"
                                        >
                                            <option value="IPRS">
                                                IPRS
                                            </option>
                                            <option value="PRS">
                                                PRS
                                            </option>
                                            <option value="TPL">
                                                TPL
                                            </option>
                                            <option value="OTHER">
                                                Other
                                            </option>
                                        </select>
                                    </Field>

                                    <Field label="Statement Month">
                                        <input
                                            type="month"
                                            value={
                                                statementMonth
                                            }
                                            onChange={(e) =>
                                                setStatementMonth(
                                                    e.target
                                                        .value
                                                )
                                            }
                                            className="w-full rounded-xl border-slate-300"
                                        />
                                    </Field>

                                    <Field label="Currency">
                                        <input
                                            type="text"
                                            maxLength={3}
                                            value={currency}
                                            onChange={(e) =>
                                                setCurrency(
                                                    e.target.value.toUpperCase()
                                                )
                                            }
                                            className="w-full rounded-xl border-slate-300 uppercase"
                                        />
                                    </Field>

                                    <Field label="Royalty File">
                                        <input
                                            ref={fileRef}
                                            type="file"
                                            accept=".csv,.txt,.xlsx,.xls"
                                            onChange={(e) =>
                                                setFile(
                                                    e.target
                                                        .files?.[0] ||
                                                        null
                                                )
                                            }
                                            className="block w-full rounded-xl border border-slate-300 p-2 text-sm"
                                        />
                                    </Field>

                                    <div className="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                        Accepted:
                                        CSV, XLSX and XLS.
                                        The next step detects
                                        report headers before
                                        importing any royalty
                                        rows.
                                    </div>

                                    <div className="md:col-span-2 flex justify-end">
                                        <button
                                            type="button"
                                            disabled={
                                                busy ||
                                                !file ||
                                                !statementMonth
                                            }
                                            onClick={
                                                inspectFile
                                            }
                                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {busy
                                                ? "Reading Report..."
                                                : "Continue to Column Mapping"}
                                        </button>
                                    </div>
                                </div>
                            )}

                            {step === 2 && (
                                <>
                                    <div className="mb-6 grid gap-4 md:grid-cols-3">
                                        <InfoCard
                                            label="Detected Rows"
                                            value={
                                                inspection?.row_count ||
                                                0
                                            }
                                        />
                                        <InfoCard
                                            label="Detected Columns"
                                            value={
                                                headers.length
                                            }
                                        />
                                        <InfoCard
                                            label="Source"
                                            value={
                                                source
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-5 md:grid-cols-2">
                                        <ColumnSelect
                                            label="ISRC *"
                                            value={
                                                mapping.isrc
                                            }
                                            headers={
                                                headers
                                            }
                                            onChange={(
                                                value
                                            ) =>
                                                setMapping(
                                                    (old) => ({
                                                        ...old,
                                                        isrc: value,
                                                    })
                                                )
                                            }
                                        />

                                        <ColumnSelect
                                            label="Amount *"
                                            value={
                                                mapping.amount
                                            }
                                            headers={
                                                headers
                                            }
                                            onChange={(
                                                value
                                            ) =>
                                                setMapping(
                                                    (old) => ({
                                                        ...old,
                                                        amount: value,
                                                    })
                                                )
                                            }
                                        />

                                        <ColumnSelect
                                            label="Track Title"
                                            value={
                                                mapping.track_title
                                            }
                                            headers={
                                                headers
                                            }
                                            optional
                                            onChange={(
                                                value
                                            ) =>
                                                setMapping(
                                                    (old) => ({
                                                        ...old,
                                                        track_title:
                                                            value,
                                                    })
                                                )
                                            }
                                        />

                                        <ColumnSelect
                                            label="Rights Type"
                                            value={
                                                mapping.rights_type
                                            }
                                            headers={
                                                headers
                                            }
                                            optional
                                            onChange={(
                                                value
                                            ) =>
                                                setMapping(
                                                    (old) => ({
                                                        ...old,
                                                        rights_type:
                                                            value,
                                                    })
                                                )
                                            }
                                        />

                                        <ColumnSelect
                                            label="Territory"
                                            value={
                                                mapping.territory
                                            }
                                            headers={
                                                headers
                                            }
                                            optional
                                            onChange={(
                                                value
                                            ) =>
                                                setMapping(
                                                    (old) => ({
                                                        ...old,
                                                        territory:
                                                            value,
                                                    })
                                                )
                                            }
                                        />
                                    </div>

                                    {inspection?.preview
                                        ?.length > 0 && (
                                        <div className="mt-7">
                                            <h4 className="mb-3 text-sm font-bold text-slate-900">
                                                File Preview
                                            </h4>

                                            <div className="overflow-x-auto rounded-xl border border-slate-200">
                                                <table className="min-w-full divide-y divide-slate-200 text-xs">
                                                    <thead className="bg-slate-50">
                                                        <tr>
                                                            {headers.map(
                                                                (
                                                                    header,
                                                                    i
                                                                ) => (
                                                                    <th
                                                                        key={
                                                                            i
                                                                        }
                                                                        className="whitespace-nowrap px-3 py-3 text-left font-bold text-slate-600"
                                                                    >
                                                                        {header ||
                                                                            `Column ${i + 1}`}
                                                                    </th>
                                                                )
                                                            )}
                                                        </tr>
                                                    </thead>

                                                    <tbody className="divide-y divide-slate-100 bg-white">
                                                        {inspection.preview
                                                            .slice(
                                                                0,
                                                                5
                                                            )
                                                            .map(
                                                                (
                                                                    row,
                                                                    rowIndex
                                                                ) => (
                                                                    <tr
                                                                        key={
                                                                            rowIndex
                                                                        }
                                                                    >
                                                                        {headers.map(
                                                                            (
                                                                                header,
                                                                                i
                                                                            ) => (
                                                                                <td
                                                                                    key={
                                                                                        i
                                                                                    }
                                                                                    className="max-w-[220px] truncate whitespace-nowrap px-3 py-3 text-slate-600"
                                                                                >
                                                                                    {String(
                                                                                        row[
                                                                                            header ||
                                                                                                `Column ${i + 1}`
                                                                                        ] ??
                                                                                            ""
                                                                                    )}
                                                                                </td>
                                                                            )
                                                                        )}
                                                                    </tr>
                                                                )
                                                            )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}

                                    <div className="mt-6 flex justify-between">
                                        <button
                                            type="button"
                                            disabled={
                                                busy
                                            }
                                            onClick={() =>
                                                setStep(1)
                                            }
                                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700"
                                        >
                                            Back
                                        </button>

                                        <button
                                            type="button"
                                            disabled={
                                                busy ||
                                                !mapping.isrc ||
                                                !mapping.amount
                                            }
                                            onClick={
                                                processReport
                                            }
                                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {busy
                                                ? "Processing..."
                                                : "Process Royalty Report"}
                                        </button>
                                    </div>
                                </>
                            )}

                            {step === 3 &&
                                result && (
                                    <>
                                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                            <InfoCard
                                                label="Gross Royalty"
                                                value={money(
                                                    result.gross_amount,
                                                    result.currency
                                                )}
                                            />

                                            <InfoCard
                                                label="Mapped"
                                                value={`${result.mapped_rows || 0} / ${result.total_rows || 0}`}
                                                sub={`${mappedPercent}%`}
                                            />

                                            <InfoCard
                                                label="Beneficiary Share"
                                                value={money(
                                                    result.beneficiary_amount,
                                                    result.currency
                                                )}
                                            />

                                            <InfoCard
                                                label="Company Share"
                                                value={money(
                                                    result.company_amount,
                                                    result.currency
                                                )}
                                            />
                                        </div>

                                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                                            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                                                <div className="text-xs font-bold uppercase text-emerald-700">
                                                    Mapped
                                                </div>
                                                <div className="mt-1 text-2xl font-bold text-emerald-900">
                                                    {result.mapped_rows ||
                                                        0}
                                                </div>
                                                <div className="mt-1 text-sm text-emerald-700">
                                                    {money(
                                                        result.mapped_amount,
                                                        result.currency
                                                    )}
                                                </div>
                                            </div>

                                            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                                <div className="text-xs font-bold uppercase text-amber-700">
                                                    Unmapped
                                                </div>
                                                <div className="mt-1 text-2xl font-bold text-amber-900">
                                                    {result.unmapped_rows ||
                                                        0}
                                                </div>
                                                <div className="mt-1 text-sm text-amber-700">
                                                    {money(
                                                        result.unmapped_amount,
                                                        result.currency
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-7 overflow-hidden rounded-2xl border border-slate-200">
                                            <div className="border-b border-slate-200 bg-slate-50 px-4 py-3">
                                                <h4 className="font-bold text-slate-900">
                                                    ISRC Mapping Review
                                                </h4>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    No wallet
                                                    balance is
                                                    affected at
                                                    this stage.
                                                </p>
                                            </div>

                                            <div className="overflow-x-auto">
                                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                                    <thead className="bg-white">
                                                        <tr>
                                                            {[
                                                                "Row",
                                                                "ISRC",
                                                                "Track",
                                                                "Gross",
                                                                "Rate",
                                                                "Beneficiary",
                                                                "Company",
                                                                "Status",
                                                                "Reason",
                                                            ].map(
                                                                (
                                                                    heading
                                                                ) => (
                                                                    <th
                                                                        key={
                                                                            heading
                                                                        }
                                                                        className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                                                    >
                                                                        {
                                                                            heading
                                                                        }
                                                                    </th>
                                                                )
                                                            )}
                                                        </tr>
                                                    </thead>

                                                    <tbody className="divide-y divide-slate-100 bg-white">
                                                        {rows.map(
                                                            (
                                                                row
                                                            ) => (
                                                                <tr
                                                                    key={
                                                                        row.public_id ||
                                                                        row.id
                                                                    }
                                                                >
                                                                    <td className="px-4 py-3 text-slate-500">
                                                                        {
                                                                            row.source_row_number
                                                                        }
                                                                    </td>

                                                                    <td className="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-slate-800">
                                                                        {row.isrc ||
                                                                            "—"}
                                                                    </td>

                                                                    <td className="max-w-[240px] truncate px-4 py-3 text-slate-700">
                                                                        {row.track_title ||
                                                                            "—"}
                                                                    </td>

                                                                    <td className="whitespace-nowrap px-4 py-3">
                                                                        {money(
                                                                            row.gross_amount,
                                                                            result.currency
                                                                        )}
                                                                    </td>

                                                                    <td className="whitespace-nowrap px-4 py-3">
                                                                        {row.royalty_percentage !=
                                                                        null
                                                                            ? `${Number(
                                                                                  row.royalty_percentage
                                                                              ).toFixed(
                                                                                  2
                                                                              )}%`
                                                                            : "—"}
                                                                    </td>

                                                                    <td className="whitespace-nowrap px-4 py-3">
                                                                        {row.beneficiary_amount !=
                                                                        null
                                                                            ? money(
                                                                                  row.beneficiary_amount,
                                                                                  result.currency
                                                                              )
                                                                            : "—"}
                                                                    </td>

                                                                    <td className="whitespace-nowrap px-4 py-3">
                                                                        {row.company_amount !=
                                                                        null
                                                                            ? money(
                                                                                  row.company_amount,
                                                                                  result.currency
                                                                              )
                                                                            : "—"}
                                                                    </td>

                                                                    <td className="px-4 py-3">
                                                                        <span
                                                                            className={[
                                                                                "inline-flex rounded-full px-2.5 py-1 text-xs font-bold",
                                                                                row.mapping_status ===
                                                                                "mapped"
                                                                                    ? "bg-emerald-100 text-emerald-700"
                                                                                    : "bg-amber-100 text-amber-700",
                                                                            ].join(
                                                                                " "
                                                                            )}
                                                                        >
                                                                            {row.mapping_status ||
                                                                                "unmapped"}
                                                                        </span>
                                                                    </td>

                                                                    <td className="max-w-[260px] px-4 py-3 text-xs text-slate-500">
                                                                        {row.mapping_reason ||
                                                                            "—"}
                                                                    </td>
                                                                </tr>
                                                            )
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        {pagination &&
                                            pagination.last_page >
                                                1 && (
                                                <div className="mt-5 flex items-center justify-between">
                                                    <div className="text-sm text-slate-500">
                                                        Page{" "}
                                                        {
                                                            pagination.current_page
                                                        }{" "}
                                                        of{" "}
                                                        {
                                                            pagination.last_page
                                                        }
                                                    </div>

                                                    <div className="flex gap-2">
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                busy ||
                                                                pagination.current_page <=
                                                                    1
                                                            }
                                                            onClick={async () => {
                                                                setBusy(
                                                                    true
                                                                );

                                                                try {
                                                                    await loadReview(
                                                                        result.public_id,
                                                                        pagination.current_page -
                                                                            1
                                                                    );
                                                                } catch (
                                                                    e
                                                                ) {
                                                                    setError(
                                                                        e.message
                                                                    );
                                                                } finally {
                                                                    setBusy(
                                                                        false
                                                                    );
                                                                }
                                                            }}
                                                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold disabled:opacity-40"
                                                        >
                                                            Previous
                                                        </button>

                                                        <button
                                                            type="button"
                                                            disabled={
                                                                busy ||
                                                                pagination.current_page >=
                                                                    pagination.last_page
                                                            }
                                                            onClick={async () => {
                                                                setBusy(
                                                                    true
                                                                );

                                                                try {
                                                                    await loadReview(
                                                                        result.public_id,
                                                                        pagination.current_page +
                                                                            1
                                                                    );
                                                                } catch (
                                                                    e
                                                                ) {
                                                                    setError(
                                                                        e.message
                                                                    );
                                                                } finally {
                                                                    setBusy(
                                                                        false
                                                                    );
                                                                }
                                                            }}
                                                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold disabled:opacity-40"
                                                        >
                                                            Next
                                                        </button>
                                                    </div>
                                                </div>
                                            )}

                                        {otpFor && (
                                            <div className="mt-6 rounded-2xl border border-violet-200 bg-violet-50 p-4">
                                                <div className="text-sm font-bold text-violet-900">
                                                    Confirm: {otpFor === "approve" ? "approve this report" : "publish this report to the ledger"}
                                                </div>
                                                <p className="mt-1 text-xs text-violet-800">{otpMessage}</p>
                                                <p className="mt-1 text-xs font-semibold text-violet-900">After "Confirm code" the action runs by itself.</p>
                                                <div className="mt-3 flex flex-wrap items-center gap-2">
                                                    <input
                                                        value={otpCode}
                                                        onChange={(e) => setOtpCode(e.target.value.replace(/\D/g, "").slice(0, 6))}
                                                        onKeyDown={(e) => { if (e.key === "Enter" && otpCode.length === 6) confirmOtp(); }}
                                                        inputMode="numeric"
                                                        placeholder="6-digit code"
                                                        className={`w-40 rounded-xl border border-violet-300 bg-white px-3 py-2 text-center ${otpCode ? "text-lg font-bold tracking-[0.3em]" : "text-sm"}`}
                                                    />
                                                    <button
                                                        type="button"
                                                        disabled={busy || otpCode.length !== 6}
                                                        onClick={confirmOtp}
                                                        className="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-50"
                                                    >
                                                        {busy ? "Working..." : "Confirm code"}
                                                    </button>
                                                    <button type="button" disabled={busy} onClick={resendOtp} className="rounded-xl border border-violet-300 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700">
                                                        Send a new code
                                                    </button>
                                                    <button type="button" disabled={busy} onClick={() => setOtpFor(null)} className="px-2 text-sm font-semibold text-slate-500">
                                                        Cancel
                                                    </button>
                                                </div>
                                                {otpInfo && <div className="mt-2 text-xs font-semibold text-violet-900">{otpInfo}</div>}
                                            </div>
                                        )}

                                        <div className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                                <div>
                                                    <div className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                        Import Status
                                                    </div>

                                                    <div className="mt-1 flex flex-wrap items-center gap-2">
                                                        <span
                                                            className={[
                                                                "inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide",
                                                                result.status ===
                                                                "published"
                                                                    ? "bg-emerald-100 text-emerald-700"
                                                                    : result.status ===
                                                                        "approved"
                                                                      ? "bg-blue-100 text-blue-700"
                                                                      : "bg-amber-100 text-amber-700",
                                                            ].join(
                                                                " "
                                                            )}
                                                        >
                                                            {statusInfo(result.status || "review_required")[0]}
                                                        </span>

                                                        {Number(
                                                            result.unmapped_rows ||
                                                                0
                                                        ) > 0 && (
                                                            <span className="text-xs font-semibold text-amber-700">
                                                                {
                                                                    result.unmapped_rows
                                                                }{" "}
                                                                unmapped row(s)
                                                                will remain
                                                                excluded from
                                                                publishing.
                                                            </span>
                                                        )}
                                                    </div>

                                                    <p className="mt-2 max-w-2xl text-xs text-slate-500">
                                                        Approval freezes the
                                                        current mapping and
                                                        allocation snapshot.
                                                        Publishing creates
                                                        royalty ledger entries
                                                        only. Wallet balances
                                                        are not credited here.
                                                    </p>
                                                </div>

                                                <div className="flex flex-wrap justify-end gap-2">
                                                    {isSuperAdmin &&
                                                        result.status ===
                                                            "review_required" && (
                                                            <button
                                                                type="button"
                                                                disabled={
                                                                    busy ||
                                                                    Number(
                                                                        result.mapped_rows ||
                                                                            0
                                                                    ) === 0
                                                                }
                                                                onClick={
                                                                    approveReport
                                                                }
                                                                className="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                            >
                                                                {busy
                                                                    ? "Working..."
                                                                    : "Approve Report"}
                                                            </button>
                                                        )}

                                                    {isSuperAdmin &&
                                                        result.status ===
                                                            "approved" && (
                                                            <button
                                                                type="button"
                                                                disabled={
                                                                    busy
                                                                }
                                                                onClick={
                                                                    publishReport
                                                                }
                                                                className="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                            >
                                                                {busy
                                                                    ? "Publishing..."
                                                                    : "Publish to Ledger"}
                                                            </button>
                                                        )}

                                                    {result.status ===
                                                        "published" && (
                                                        <>
                                                            <div className="inline-flex items-center rounded-xl bg-emerald-100 px-4 py-3 text-sm font-bold text-emerald-700">
                                                                Published to Ledger
                                                            </div>

                                                            {/* MIXXTUNE_RIGHTS_REVIEW_PUBLISHED_LEDGERS_V1 */}
                                                            {isSuperAdmin &&
                                                                result?.public_id && (
                                                                    <a
                                                                        href={`/super-admin/royalty-ledgers?rights_import=${encodeURIComponent(
                                                                            result.public_id
                                                                        )}`}
                                                                        className="inline-flex items-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-violet-700"
                                                                    >
                                                                        Review Published Ledgers
                                                                    </a>
                                                                )}
                                                        </>
                                                    )}

                                                    <button
                                                        type="button"
                                                        disabled={
                                                            busy
                                                        }
                                                        onClick={
                                                            close
                                                        }
                                                        className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                                    >
                                                        Done
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </>
                                )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

function Field({ label, children }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-bold text-slate-700">
                {label}
            </span>
            {children}
        </label>
    );
}

function ColumnSelect({
    label,
    value,
    headers,
    onChange,
    optional = false,
}) {
    return (
        <Field label={label}>
            <select
                value={value}
                onChange={(e) =>
                    onChange(e.target.value)
                }
                className="w-full rounded-xl border-slate-300"
            >
                <option value="">
                    {optional
                        ? "Not mapped"
                        : "Select column"}
                </option>

                {headers.map(
                    (header, index) => (
                        <option
                            key={`${header}-${index}`}
                            value={header}
                            disabled={!header}
                        >
                            {header ||
                                `Column ${index + 1}`}
                        </option>
                    )
                )}
            </select>
        </Field>
    );
}

function InfoCard({
    label,
    value,
    sub = null,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-xl font-bold text-slate-900">
                {value}
            </div>

            {sub && (
                <div className="mt-1 text-xs font-semibold text-violet-600">
                    {sub}
                </div>
            )}
        </div>
    );
}
