import { useEffect, useMemo, useState } from "react";
import { router } from "@inertiajs/react";
import { MascotCharacter } from "@/V2/Shared/Mascot";

function getRemaining(target) {
    if (!target) return 0;

    return Math.max(
        0,
        new Date(target).getTime() - Date.now()
    );
}

function formatCountdown(milliseconds) {
    const totalSeconds = Math.floor(
        milliseconds / 1000
    );

    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor(
        (totalSeconds % 3600) / 60
    );
    const seconds = totalSeconds % 60;

    return [
        String(hours).padStart(2, "0"),
        String(minutes).padStart(2, "0"),
        String(seconds).padStart(2, "0"),
    ].join(":");
}

function formatTime(value) {
    if (!value) return "";

    return new Intl.DateTimeFormat("en-IN", {
        hour: "2-digit",
        minute: "2-digit",
        hour12: true,
        timeZone: "Asia/Kolkata",
    }).format(new Date(value));
}

export default function MaintenanceExperience({
    maintenance,
    children,
}) {
    const [now, setNow] = useState(Date.now());
    const [understood, setUnderstood] = useState(false);

    /*
     * Keep browser countdown alive.
     */
    useEffect(() => {
        const timer = window.setInterval(() => {
            setNow(Date.now());
        }, 1000);

        return () => {
            window.clearInterval(timer);
        };
    }, []);

    /*
     * IMPORTANT:
     * Refresh only the shared maintenance prop.
     *
     * This allows an already-open Label/Artist/Manager
     * dashboard to detect:
     *
     * OFF -> scheduled
     * scheduled -> active
     * active -> finished
     *
     * without a manual browser refresh.
     */
    useEffect(() => {
        const refreshMaintenance = () => {
            router.reload({
                only: ["maintenance"],
                preserveScroll: true,
                preserveState: true,
                replace: true,
            });
        };

        const timer = window.setInterval(
            refreshMaintenance,
            15000
        );

        return () => {
            window.clearInterval(timer);
        };
    }, []);

    const startsAt = maintenance?.starts_at
        ? new Date(
              maintenance.starts_at
          ).getTime()
        : null;

    const endsAt = maintenance?.ends_at
        ? new Date(
              maintenance.ends_at
          ).getTime()
        : null;

    const isScheduled =
        Boolean(maintenance?.enabled) &&
        Boolean(maintenance?.scheduled) &&
        !maintenance?.active &&
        !maintenance?.is_super_admin &&
        startsAt &&
        now < startsAt;

    const isActive =
        Boolean(maintenance?.enabled) &&
        Boolean(maintenance?.active) &&
        !maintenance?.is_super_admin &&
        startsAt &&
        endsAt &&
        now >= startsAt &&
        now < endsAt;

    const countdown = useMemo(() => {
        if (!endsAt) {
            return "00:00:00";
        }

        return formatCountdown(
            getRemaining(maintenance.ends_at)
        );
    }, [endsAt, now, maintenance?.ends_at]);

    /*
     * Active maintenance:
     * replace CENTRAL content only.
     */
    if (isActive) {
        return (
            <div className="min-h-[calc(100vh-8rem)]">
                <div className="flex min-h-[calc(100vh-11rem)] items-center justify-center">
                    <section className="w-full max-w-4xl overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-8 shadow-xl lg:p-12">
                        <div className="text-center">

                            <div className="mx-auto mb-6 max-w-sm">
                                <MascotCharacter
                                    activity="working"
                                />
                            </div>

                            <div className="text-xs font-bold uppercase tracking-[0.2em] text-violet-600">
                                Scheduled Maintenance
                            </div>

                            <h2 className="mt-3 text-3xl font-bold tracking-tight text-slate-900">
                                We’re working on Mixx Tune
                            </h2>

                            <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-500">
                                {maintenance?.message ||
                                    "Mixx Tune is undergoing scheduled maintenance. Please check back shortly."}
                            </p>

                            <div className="mx-auto mt-8 max-w-md rounded-2xl border border-slate-200 bg-slate-50 px-6 py-5">
                                <div className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                                    Maintenance ends at
                                </div>

                                <div className="mt-2 text-lg font-semibold text-slate-800">
                                    {formatTime(
                                        maintenance?.ends_at
                                    )}
                                </div>

                                <div className="mt-4 font-mono text-4xl font-bold tracking-wider text-slate-900">
                                    {countdown}
                                </div>

                                <div className="mt-2 text-xs text-slate-400">
                                    Live countdown
                                </div>
                            </div>

                        </div>
                    </section>
                </div>
            </div>
        );
    }

    /*
     * Scheduled notification before maintenance starts.
     *
     * ONLY "I Understand".
     */
    return (
        <>
            {isScheduled && !understood && (
                <div className="sticky top-0 z-40 mb-5 overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-lg">
                    <div className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">

                        <div className="flex items-start gap-3">
                            <div className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-lg">
                                ⚙
                            </div>

                            <div>
                                <div className="font-semibold text-slate-900">
                                    Scheduled Maintenance
                                </div>

                                <div className="mt-1 text-sm text-slate-500">
                                    Mixx Tune maintenance is scheduled from{" "}
                                    <span className="font-semibold text-slate-700">
                                        {formatTime(
                                            maintenance?.starts_at
                                        )}
                                    </span>{" "}
                                    to{" "}
                                    <span className="font-semibold text-slate-700">
                                        {formatTime(
                                            maintenance?.ends_at
                                        )}
                                    </span>
                                    .
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            onClick={() =>
                                setUnderstood(true)
                            }
                            className="shrink-0 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                        >
                            I Understand
                        </button>

                    </div>
                </div>
            )}

            {children}
        </>
    );
}
