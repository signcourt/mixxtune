import { Link, usePage } from '@inertiajs/react';
import { panelNavigation } from '../Config/panelNavigation';

export default function PanelLayout({
    role = 'artist',
    title,
    subtitle = null,
    children,
}) {
    const { url } = usePage();
    const items = panelNavigation[role] ?? panelNavigation.artist;

    return (
        <div className="min-h-screen bg-slate-100">
            <div className="flex min-h-screen">
                <aside className="hidden w-72 shrink-0 bg-slate-950 p-5 text-white lg:block">
                    <div className="text-xl font-bold">
                        Mixx Tune V2
                    </div>

                    <div className="mt-1 text-xs uppercase tracking-widest text-slate-400">
                        {role.replace('_', ' ')}
                    </div>

                    <nav className="mt-8 space-y-2">
                        {items.map((item) => {
                            const active =
                                url === item.href ||
                                url.startsWith(`${item.href}/`);

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`block rounded-xl px-4 py-3 text-sm font-semibold transition ${
                                        active
                                            ? 'bg-violet-600 text-white'
                                            : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <main className="min-w-0 flex-1">
                    <header className="border-b border-slate-200 bg-white px-6 py-5">
                        <h1 className="text-xl font-bold text-slate-900">
                            {title}
                        </h1>

                        {subtitle && (
                            <p className="mt-1 text-sm text-slate-500">
                                {subtitle}
                            </p>
                        )}
                    </header>

                    <div className="p-6">{children}</div>
                </main>
            </div>
        </div>
    );
}
