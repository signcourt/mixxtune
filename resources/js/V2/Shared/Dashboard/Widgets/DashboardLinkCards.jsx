import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function DashboardLinkCards({
    items = [],
}) {
    return (
        <section className="grid gap-5 md:grid-cols-3">
            {items.map(
                ({
                    icon: Icon,
                    title,
                    description,
                    href,
                }) => (
                    <Link
                        key={`${title}-${href}`}
                        href={href}
                        className="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-violet-200 hover:shadow-lg"
                    >
                        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-100 text-violet-700">
                            {Icon && <Icon size={22} />}
                        </div>

                        <h3 className="mt-5 text-lg font-black text-slate-950">
                            {title}
                        </h3>

                        <p className="mt-2 text-sm leading-6 text-slate-500">
                            {description}
                        </p>

                        <div className="mt-5 inline-flex items-center gap-2 text-sm font-bold text-violet-700">
                            Open
                            <ArrowRight
                                size={16}
                                className="transition group-hover:translate-x-1"
                            />
                        </div>
                    </Link>
                )
            )}
        </section>
    );
}
