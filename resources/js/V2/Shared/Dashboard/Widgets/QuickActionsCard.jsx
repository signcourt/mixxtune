import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function QuickActionsCard({
    actions = [],
    title = 'Quick Actions',
    subtitle = 'Frequently used tools',
}) {
    return (
        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h3 className="text-lg font-black text-slate-950">
                    {title}
                </h3>

                <p className="mt-1 text-sm text-slate-500">
                    {subtitle}
                </p>
            </div>

            <div className="mt-5 space-y-3">
                {actions.map(
                    ({
                        label,
                        description,
                        href,
                        icon: Icon,
                    }) => (
                        <Link
                            key={`${label}-${href}`}
                            href={href}
                            className="group flex items-center gap-4 rounded-2xl border border-slate-200 px-4 py-4 transition hover:border-violet-200 hover:bg-violet-50/50"
                        >
                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 transition group-hover:bg-violet-100 group-hover:text-violet-700">
                                {Icon && <Icon size={20} />}
                            </div>

                            <div className="min-w-0 flex-1">
                                <p className="font-bold text-slate-900">
                                    {label}
                                </p>

                                {description && (
                                    <p className="mt-0.5 truncate text-xs text-slate-500">
                                        {description}
                                    </p>
                                )}
                            </div>

                            <ArrowRight
                                size={17}
                                className="text-slate-400 transition group-hover:translate-x-1 group-hover:text-violet-700"
                            />
                        </Link>
                    )
                )}
            </div>
        </section>
    );
}
