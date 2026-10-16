import { Head } from "@inertiajs/react";

export default function Maintenance({ maintenance = {} }) {
    return (
        <>
            <Head title="Scheduled Maintenance" />

            <div className="min-h-screen bg-slate-100 px-5 py-10 text-slate-900">
                <div className="mx-auto flex min-h-[80vh] max-w-3xl items-center justify-center">
                    <div className="w-full rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl sm:p-12">
                        <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-900 text-3xl text-white">
                            ⚙
                        </div>

                        <h1 className="mt-6 text-3xl font-bold">
                            Scheduled Maintenance
                        </h1>

                        <p className="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-500">
                            {maintenance.message ||
                                "Mixx Tune is undergoing scheduled maintenance. Please check back shortly."}
                        </p>

                        <div className="mt-7 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-medium text-amber-900">
                            The Mixx Tune platform is temporarily unavailable
                            while scheduled maintenance is in progress.
                        </div>

                        <p className="mt-5 text-sm text-slate-400">
                            Please check back shortly.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
