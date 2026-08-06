#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/shared-navigation-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    resources/js/V2/Shared/Navigation \
    resources/js/V2/Shared/Config \
    v2/runtime/state \
    v2/reports

echo "=================================================="
echo "V2 SHARED NAVIGATION ENGINE"
echo "=================================================="

echo "[1/8] Creating backups..."

for FILE in \
    resources/js/V2/Shared/Layouts/PanelLayout.jsx \
    resources/js/V2/Shared/Config/panelNavigation.js \
    resources/js/V2/Shared/Navigation/Sidebar.jsx \
    resources/js/V2/Shared/Navigation/SidebarItem.jsx
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/8] Creating central route registry..."

cat > resources/js/V2/Shared/Config/panelRoutes.js <<'JS'
export const PANEL_ROUTES = {
    common: {
        notifications: '/v2/notifications',
    },

    artist: {
        dashboard: '/v2/dashboard',

        releases: '/v2/releases',
        releasesAll: '/v2/releases',
        releasesLive:
            '/v2/releases?status=live',
        releasesReview:
            '/v2/releases?status=submitted',
        releasesDraft:
            '/v2/releases?status=draft',
        releasesRejected:
            '/v2/releases?status=rejected',

        createRelease:
            '/v2/releases/create',

        catalogue:
            '/v2/catalogue',

        royalties:
            '/v2/royalties',

        wallet:
            '/v2/wallet',

        withdrawals:
            '/v2/withdrawals',

        reports:
            '/v2/reports',

        kyc:
            '/v2/kyc-profile',

        support:
            '/v2/support',

        notifications:
            '/v2/notifications',

        settings:
            '/v2/settings',
    },

    label: {
        dashboard:
            '/v2/label/dashboard',

        artists:
            '/v2/label/artists',

        releases:
            '/v2/releases',
        releasesAll:
            '/v2/releases',
        releasesLive:
            '/v2/releases?status=live',
        releasesReview:
            '/v2/releases?status=submitted',
        releasesDraft:
            '/v2/releases?status=draft',
        releasesRejected:
            '/v2/releases?status=rejected',

        createRelease:
            '/v2/releases/create',

        catalogue:
            '/v2/catalogue',

        royalties:
            '/v2/royalties',

        wallet:
            '/v2/wallet',

        withdrawals:
            '/v2/withdrawals',

        reports:
            '/v2/reports',

        kyc:
            '/v2/kyc-profile',

        support:
            '/v2/support',

        notifications:
            '/v2/notifications',

        settings:
            '/v2/settings',
    },

    admin: {
        dashboard:
            '/v2/admin/dashboard',

        artists:
            '/v2/admin/artists',

        labels:
            '/v2/admin/labels',

        releases:
            '/v2/admin/release-reviews',

        releasesAll:
            '/v2/admin/release-reviews?status=',

        releasesReview:
            '/v2/admin/release-reviews?status=submitted',

        releasesApproved:
            '/v2/admin/release-reviews?status=approved',

        releasesRejected:
            '/v2/admin/release-reviews?status=rejected',

        distribution:
            '/v2/admin/distribution',

        identifiers:
            '/v2/admin/identifiers/pending',

        catalogue:
            '/v2/catalogue',

        stores:
            '/v2/admin/stores',

        royalties:
            '/v2/admin/royalties',

        reports:
            '/v2/admin/reports',

        wallet:
            '/v2/admin/wallet',

        withdrawals:
            '/v2/admin/withdrawals',

        support:
            '/v2/admin/support',

        notifications:
            '/v2/notifications',

        settings:
            '/v2/admin/settings',
    },

    super_admin: {
        dashboard:
            '/v2/admin/dashboard',

        users:
            '/v2/admin/users',

        admins:
            '/v2/admin/admins',

        labels:
            '/v2/admin/labels',

        artists:
            '/v2/admin/artists',

        releases:
            '/v2/admin/release-reviews',

        releasesAll:
            '/v2/admin/release-reviews?status=',

        releasesReview:
            '/v2/admin/release-reviews?status=submitted',

        releasesApproved:
            '/v2/admin/release-reviews?status=approved',

        releasesRejected:
            '/v2/admin/release-reviews?status=rejected',

        distribution:
            '/v2/admin/distribution',

        identifiers:
            '/v2/admin/identifiers/pending',

        stores:
            '/v2/admin/stores',

        catalogue:
            '/v2/catalogue',

        royalties:
            '/v2/admin/royalties',

        reports:
            '/v2/admin/reports',

        finance:
            '/v2/admin/finance',

        invoices:
            '/v2/admin/invoices',

        statements:
            '/v2/admin/statements',

        wallet:
            '/v2/admin/wallet',

        withdrawals:
            '/v2/admin/withdrawals',

        support:
            '/v2/admin/support',

        logs:
            '/v2/admin/logs',

        activity:
            '/v2/admin/activity',

        notifications:
            '/v2/notifications',

        settings:
            '/v2/admin/settings',
    },
};

export const getPanelRoutes = (
    role = 'artist'
) => {
    const normalizedRole =
        role === 'super-admin'
            ? 'super_admin'
            : role;

    return (
        PANEL_ROUTES[normalizedRole] ??
        PANEL_ROUTES.artist
    );
};
JS


echo "[3/8] Creating role-based navigation config..."

cat > resources/js/V2/Shared/Config/panelNavigation.js <<'JS'
import {
    getPanelRoutes,
} from '@/V2/Shared/Config/panelRoutes';

const icons = {
    dashboard: '⌂',
    releases: '◇',
    create: '+',
    catalogue: '▦',
    royalties: '₹',
    wallet: '▣',
    withdrawals: '⇩',
    reports: '▤',
    profile: '♙',
    support: '◯',
    notifications: '♧',
    settings: '⚙',
    artists: '♙',
    labels: '▱',
    users: '♧',
    distribution: '⇄',
    identifiers: '#',
    stores: '◫',
    finance: '₹',
    invoices: '▤',
    statements: '▥',
    logs: '≡',
    activity: '⌁',
};

const releaseChildren = (
    routes,
    role
) => {
    if (
        role === 'admin' ||
        role === 'super_admin'
    ) {
        return [
            {
                id: 'releases-all',
                label: 'All Releases',
                href: routes.releasesAll,
                match:
                    '/v2/admin/release-reviews',
            },
            {
                id: 'releases-review',
                label: 'In Review',
                href: routes.releasesReview,
                queryStatus: 'submitted',
            },
            {
                id: 'releases-approved',
                label: 'Approved',
                href: routes.releasesApproved,
                queryStatus: 'approved',
            },
            {
                id: 'releases-rejected',
                label: 'Rejected',
                href: routes.releasesRejected,
                queryStatus: 'rejected',
            },
        ];
    }

    return [
        {
            id: 'releases-all',
            label: 'All Releases',
            href: routes.releasesAll,
            match: '/v2/releases',
        },
        {
            id: 'releases-live',
            label: 'Live on Stores',
            href: routes.releasesLive,
            queryStatus: 'live',
        },
        {
            id: 'releases-review',
            label: 'In Review',
            href: routes.releasesReview,
            queryStatus: 'submitted',
        },
        {
            id: 'releases-draft',
            label: 'Drafts',
            href: routes.releasesDraft,
            queryStatus: 'draft',
        },
        {
            id: 'releases-rejected',
            label: 'Rejected',
            href: routes.releasesRejected,
            queryStatus: 'rejected',
        },
    ];
};

const commonOwnerNavigation = (
    routes,
    role
) => [
    {
        id: 'dashboard',
        label: 'Dashboard',
        icon: icons.dashboard,
        href: routes.dashboard,
        permission: 'dashboard.view',
        exact: true,
    },
    {
        id: 'releases',
        label:
            role === 'artist'
                ? 'My Releases'
                : 'Releases',
        icon: icons.releases,
        href: routes.releases,
        permission: 'releases.view',
        children: releaseChildren(
            routes,
            role
        ),
    },
    {
        id: 'create-release',
        label: 'Create Release',
        icon: icons.create,
        href: routes.createRelease,
        permission: 'releases.create',
    },
    {
        id: 'catalogue',
        label: 'Catalogue',
        icon: icons.catalogue,
        href: routes.catalogue,
        permission: 'catalogue.view',
    },
    {
        id: 'royalties',
        label: 'Royalties',
        icon: icons.royalties,
        href: routes.royalties,
        permission: 'royalties.view',
    },
    {
        id: 'wallet',
        label: 'Wallet',
        icon: icons.wallet,
        href: routes.wallet,
        permission: 'wallet.view',
    },
    {
        id: 'withdrawals',
        label: 'Withdrawals',
        icon: icons.withdrawals,
        href: routes.withdrawals,
        permission: 'withdrawals.view',
    },
    {
        id: 'reports',
        label: 'Reports',
        icon: icons.reports,
        href: routes.reports,
        permission: 'reports.view',
    },
    {
        id: 'kyc-profile',
        label: 'KYC & Profile',
        icon: icons.profile,
        href: routes.kyc,
        permission: 'profile.view',
    },
    {
        id: 'support',
        label: 'Support Tickets',
        icon: icons.support,
        href: routes.support,
        permission: 'support.view',
    },
    {
        id: 'notifications',
        label: 'Notifications',
        icon: icons.notifications,
        href: routes.notifications,
        badgeKey: 'notifications',
    },
    {
        id: 'settings',
        label: 'Settings',
        icon: icons.settings,
        href: routes.settings,
        permission: 'settings.view',
    },
];

const adminNavigation = (
    routes,
    role
) => {
    const navigation = [
        {
            id: 'dashboard',
            label: 'Dashboard',
            icon: icons.dashboard,
            href: routes.dashboard,
            permission: 'dashboard.view',
            exact: true,
        },
    ];

    if (role === 'super_admin') {
        navigation.push(
            {
                id: 'users',
                label: 'Users',
                icon: icons.users,
                href: routes.users,
                permission: 'users.view',
            },
            {
                id: 'admins',
                label: 'Admins',
                icon: icons.users,
                href: routes.admins,
                permission: 'admins.view',
            }
        );
    }

    navigation.push(
        {
            id: 'labels',
            label: 'Labels',
            icon: icons.labels,
            href: routes.labels,
            permission: 'labels.view',
        },
        {
            id: 'artists',
            label: 'Artists',
            icon: icons.artists,
            href: routes.artists,
            permission: 'artists.view',
        },
        {
            id: 'releases',
            label: 'Releases',
            icon: icons.releases,
            href: routes.releases,
            permission: 'releases.review',
            children: releaseChildren(
                routes,
                role
            ),
        },
        {
            id: 'distribution',
            label: 'Distribution',
            icon: icons.distribution,
            href: routes.distribution,
            permission: 'delivery.view',
        },
        {
            id: 'identifiers',
            label: 'ISRC & UPC',
            icon: icons.identifiers,
            href: routes.identifiers,
            permission: 'identifiers.view',
        },
        {
            id: 'stores',
            label: 'DSP Stores',
            icon: icons.stores,
            href: routes.stores,
            permission: 'stores.view',
        },
        {
            id: 'catalogue',
            label: 'Catalogue',
            icon: icons.catalogue,
            href: routes.catalogue,
            permission: 'catalogue.view',
        },
        {
            id: 'royalties',
            label: 'Royalties',
            icon: icons.royalties,
            href: routes.royalties,
            permission: 'royalties.view',
        },
        {
            id: 'reports',
            label: 'Reports',
            icon: icons.reports,
            href: routes.reports,
            permission: 'reports.view',
        }
    );

    if (role === 'super_admin') {
        navigation.push(
            {
                id: 'finance',
                label: 'Finance',
                icon: icons.finance,
                href: routes.finance,
                permission: 'finance.view',
            },
            {
                id: 'invoices',
                label: 'Invoices',
                icon: icons.invoices,
                href: routes.invoices,
                permission: 'invoices.view',
            },
            {
                id: 'statements',
                label: 'Statements',
                icon: icons.statements,
                href: routes.statements,
                permission: 'statements.view',
            }
        );
    }

    navigation.push(
        {
            id: 'wallet',
            label: 'Wallet',
            icon: icons.wallet,
            href: routes.wallet,
            permission: 'wallet.view',
        },
        {
            id: 'withdrawals',
            label: 'Withdraw Requests',
            icon: icons.withdrawals,
            href: routes.withdrawals,
            permission: 'withdrawals.manage',
        },
        {
            id: 'support',
            label: 'Support Tickets',
            icon: icons.support,
            href: routes.support,
            permission: 'support.manage',
        }
    );

    if (role === 'super_admin') {
        navigation.push(
            {
                id: 'logs',
                label: 'System Logs',
                icon: icons.logs,
                href: routes.logs,
                permission: 'logs.view',
            },
            {
                id: 'activity',
                label: 'Activity',
                icon: icons.activity,
                href: routes.activity,
                permission: 'activity.view',
            }
        );
    }

    navigation.push(
        {
            id: 'notifications',
            label: 'Notifications',
            icon: icons.notifications,
            href: routes.notifications,
            badgeKey: 'notifications',
        },
        {
            id: 'settings',
            label: 'Settings',
            icon: icons.settings,
            href: routes.settings,
            permission: 'settings.view',
        }
    );

    return navigation;
};

export const getPanelNavigation = (
    role = 'artist'
) => {
    const normalizedRole =
        role === 'super-admin'
            ? 'super_admin'
            : role;

    const routes = getPanelRoutes(
        normalizedRole
    );

    if (
        normalizedRole === 'admin' ||
        normalizedRole === 'super_admin'
    ) {
        return adminNavigation(
            routes,
            normalizedRole
        );
    }

    const navigation =
        commonOwnerNavigation(
            routes,
            normalizedRole
        );

    if (normalizedRole === 'label') {
        navigation.splice(1, 0, {
            id: 'artists',
            label: 'Artists',
            icon: icons.artists,
            href: routes.artists,
            permission: 'artists.view',
        });
    }

    return navigation;
};

export default getPanelNavigation;
JS


echo "[4/8] Creating shared Sidebar component..."

cat > resources/js/V2/Shared/Navigation/Sidebar.jsx <<'JSX'
import {
    Link,
    usePage,
} from '@inertiajs/react';

import {
    useEffect,
    useMemo,
    useState,
} from 'react';

import {
    getPanelNavigation,
} from '@/V2/Shared/Config/panelNavigation';

const normalizePath = (url = '') =>
    url.split('?')[0].replace(/\/+$/, '') ||
    '/';

const getStatusQuery = () => {
    if (
        typeof window === 'undefined'
    ) {
        return '';
    }

    return new URLSearchParams(
        window.location.search
    ).get('status') ?? '';
};

const permissionAllowed = (
    item,
    permissions
) => {
    if (!item.permission) {
        return true;
    }

    if (
        !Array.isArray(permissions) ||
        permissions.length === 0
    ) {
        return true;
    }

    return permissions.includes(
        item.permission
    );
};

export default function Sidebar({
    role = 'artist',
    permissions = [],
    notificationCount = 0,
    brandName = 'BACKSTAGE',
    brandSubtitle = 'MIXX TUNE',
    logoUrl = null,
    mobileOpen = false,
    onMobileClose = () => {},
}) {
    const {
        url,
    } = usePage();

    const [collapsed, setCollapsed] =
        useState(false);

    const [openMenus, setOpenMenus] =
        useState({});

    const currentPath = normalizePath(
        url
    );

    const currentStatus =
        getStatusQuery();

    const navigation = useMemo(
        () =>
            getPanelNavigation(
                role
            ).filter((item) =>
                permissionAllowed(
                    item,
                    permissions
                )
            ),
        [role, permissions]
    );

    useEffect(() => {
        const activeParent =
            navigation.find((item) =>
                item.children?.some(
                    (child) =>
                        isChildActive(
                            child,
                            currentPath,
                            currentStatus
                        )
                )
            );

        if (activeParent) {
            setOpenMenus(
                (current) => ({
                    ...current,
                    [activeParent.id]:
                        true,
                })
            );
        }
    }, [
        currentPath,
        currentStatus,
        navigation,
    ]);

    const toggleSubmenu = (id) => {
        setOpenMenus((current) => ({
            ...current,
            [id]: !current[id],
        }));
    };

    const sidebarWidth = collapsed
        ? 'lg:w-[88px]'
        : 'lg:w-[272px]';

    return (
        <>
            {mobileOpen && (
                <button
                    type="button"
                    aria-label="Close sidebar"
                    onClick={onMobileClose}
                    className="fixed inset-0 z-40 bg-slate-950/60 lg:hidden"
                />
            )}

            <aside
                className={[
                    'fixed inset-y-0 left-0 z-50 flex w-[272px] flex-col border-r border-white/10 bg-gradient-to-b from-[#080d18] via-[#0b111d] to-[#101724] text-white shadow-2xl transition-all duration-300',
                    sidebarWidth,
                    mobileOpen
                        ? 'translate-x-0'
                        : '-translate-x-full lg:translate-x-0',
                ].join(' ')}
            >
                <div
                    className={[
                        'flex h-24 items-center border-b border-white/10 px-5',
                        collapsed
                            ? 'justify-center'
                            : 'gap-3',
                    ].join(' ')}
                >
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-violet-500/50 bg-slate-900 shadow-lg">
                        {logoUrl ? (
                            <img
                                src={logoUrl}
                                alt={brandName}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <span className="text-sm font-bold text-violet-300">
                                MT
                            </span>
                        )}
                    </div>

                    {!collapsed && (
                        <div className="min-w-0">
                            <div className="truncate text-lg font-bold tracking-wide">
                                {brandName}
                            </div>

                            <div className="mt-0.5 truncate text-[10px] font-semibold tracking-[0.3em] text-slate-400">
                                {brandSubtitle}
                            </div>
                        </div>
                    )}
                </div>

                <nav className="flex-1 overflow-y-auto px-3 py-5">
                    <div className="space-y-1.5">
                        {navigation.map(
                            (item) => (
                                <SidebarItem
                                    key={item.id}
                                    item={item}
                                    collapsed={
                                        collapsed
                                    }
                                    open={
                                        Boolean(
                                            openMenus[
                                                item.id
                                            ]
                                        )
                                    }
                                    currentPath={
                                        currentPath
                                    }
                                    currentStatus={
                                        currentStatus
                                    }
                                    notificationCount={
                                        notificationCount
                                    }
                                    onToggle={() =>
                                        toggleSubmenu(
                                            item.id
                                        )
                                    }
                                    onNavigate={
                                        onMobileClose
                                    }
                                />
                            )
                        )}
                    </div>
                </nav>

                <div className="border-t border-white/10 p-3">
                    <button
                        type="button"
                        onClick={() =>
                            setCollapsed(
                                (value) =>
                                    !value
                            )
                        }
                        className="flex w-full items-center justify-center rounded-xl px-3 py-3 text-violet-400 transition hover:bg-white/5 hover:text-violet-300"
                        title={
                            collapsed
                                ? 'Expand sidebar'
                                : 'Collapse sidebar'
                        }
                    >
                        <span
                            className={[
                                'text-xl transition-transform duration-300',
                                collapsed
                                    ? 'rotate-180'
                                    : '',
                            ].join(' ')}
                        >
                            ‹
                        </span>
                    </button>
                </div>
            </aside>
        </>
    );
}

function SidebarItem({
    item,
    collapsed,
    open,
    currentPath,
    currentStatus,
    notificationCount,
    onToggle,
    onNavigate,
}) {
    const hasChildren =
        Array.isArray(item.children) &&
        item.children.length > 0;

    const active =
        isItemActive(
            item,
            currentPath
        ) ||
        item.children?.some(
            (child) =>
                isChildActive(
                    child,
                    currentPath,
                    currentStatus
                )
        );

    const badge =
        item.badgeKey ===
        'notifications'
            ? notificationCount
            : 0;

    if (hasChildren) {
        return (
            <div>
                <button
                    type="button"
                    onClick={onToggle}
                    title={
                        collapsed
                            ? item.label
                            : undefined
                    }
                    className={[
                        'group flex w-full items-center rounded-xl py-3 transition-all duration-200',
                        collapsed
                            ? 'justify-center px-2'
                            : 'gap-3 px-3',
                        active
                            ? 'bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg shadow-violet-950/30'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white',
                    ].join(' ')}
                >
                    <NavIcon
                        icon={item.icon}
                        active={active}
                    />

                    {!collapsed && (
                        <>
                            <span className="min-w-0 flex-1 truncate text-left text-sm font-medium">
                                {item.label}
                            </span>

                            <span
                                className={[
                                    'text-xs transition-transform duration-200',
                                    open
                                        ? 'rotate-180'
                                        : '',
                                ].join(' ')}
                            >
                               ⌄
                            </span>
                        </>
                    )}
                </button>

                {!collapsed && open && (
                    <div className="ml-7 mt-2 border-l border-slate-700/80 pl-4">
                        <div className="space-y-1">
                            {item.children.map(
                                (child) => {
                                    const childActive =
                                        isChildActive(
                                            child,
                                            currentPath,
                                            currentStatus
                                        );

                                    return (
                                        <Link
                                            key={
                                                child.id
                                            }
                                            href={
                                                child.href
                                            }
                                            onClick={
                                                onNavigate
                                            }
                                            className={[
                                                'group flex items-center gap-3 rounded-lg px-2 py-2 text-sm transition',
                                                childActive
                                                    ? 'font-semibold text-violet-400'
                                                    : 'text-slate-400 hover:text-white',
                                            ].join(
                                                ' '
                                            )}
                                        >
                                            <span
                                                className={[
                                                    'h-2 w-2 rounded-full border',
                                                    childActive
                                                        ? 'border-violet-500 bg-violet-500'
                                                        : 'border-slate-600 bg-transparent group-hover:border-slate-400',
                                                ].join(
                                                    ' '
                                                )}
                                            />

                                            <span className="truncate">
                                                {
                                                    child.label
                                                }
                                            </span>
                                        </Link>
                                    );
                                }
                            )}
                        </div>
                    </div>
                )}
            </div>
        );
    }

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            title={
                collapsed
                    ? item.label
                    : undefined
            }
            className={[
                'group flex items-center rounded-xl py-3 transition-all duration-200',
                collapsed
                    ? 'justify-center px-2'
                    : 'gap-3 px-3',
                active
                    ? 'bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg shadow-violet-950/30'
                    : 'text-slate-300 hover:bg-white/10 hover:text-white',
            ].join(' ')}
        >
            <NavIcon
                icon={item.icon}
                active={active}
            />

            {!collapsed && (
                <>
                    <span className="min-w-0 flex-1 truncate text-sm font-medium">
                        {item.label}
                    </span>

                    {Number(badge) > 0 && (
                        <span className="flex min-w-7 items-center justify-center rounded-full bg-violet-700 px-2 py-1 text-xs font-bold text-white">
                            {Number(badge) >
                            99
                                ? '99+'
                                : badge}
                        </span>
                    )}
                </>
            )}

            {collapsed &&
                Number(badge) > 0 && (
                    <span className="absolute ml-8 mt-[-28px] flex h-5 min-w-5 items-center justify-center rounded-full bg-violet-600 px-1 text-[10px] font-bold">
                        {Number(badge) >
                        9
                            ? '9+'
                            : badge}
                    </span>
                )}
        </Link>
    );
}

function NavIcon({
    icon,
    active,
}) {
    return (
        <span
            className={[
                'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border text-lg transition',
                active
                    ? 'border-white/20 bg-white/15 text-white'
                    : 'border-transparent text-slate-300 group-hover:text-white',
            ].join(' ')}
        >
            {icon}
        </span>
    );
}

function isItemActive(
    item,
    currentPath
) {
    const itemPath = normalizePath(
        item.href
    );

    if (item.exact) {
        return currentPath === itemPath;
    }

    if (
        item.id === 'releases'
    ) {
        return (
            currentPath === itemPath ||
            currentPath.startsWith(
                `${itemPath}/`
            )
        );
    }

    return (
        currentPath === itemPath ||
        currentPath.startsWith(
            `${itemPath}/`
        )
    );
}

function isChildActive(
    child,
    currentPath,
    currentStatus
) {
    const childPath =
        normalizePath(child.href);

    if (
        currentPath !== childPath
    ) {
        return false;
    }

    if (child.queryStatus) {
        return (
            currentStatus ===
            child.queryStatus
        );
    }

    if (
        child.id === 'releases-all'
    ) {
        return currentStatus === '';
    }

    return true;
}
JSX


echo "[5/8] Creating shared PanelLayout..."

cat > resources/js/V2/Shared/Layouts/PanelLayout.jsx <<'JSX'
import {
    usePage,
} from '@inertiajs/react';

import {
    useState,
} from 'react';

import Sidebar from '@/V2/Shared/Navigation/Sidebar';

export default function PanelLayout({
    role = null,
    permissions = null,
    title = '',
    subtitle = '',
    children,
}) {
    const page = usePage();

    const props =
        page.props ?? {};

    const resolvedRole =
        role ??
        props.role ??
        props.auth?.role ??
        props.auth?.user?.role ??
        'artist';

    const resolvedPermissions =
        permissions ??
        props.permissions ??
        props.auth?.permissions ??
        [];

    const notificationCount =
        props.notificationCount ??
        props.unreadNotificationCount ??
        props.auth?.unread_notifications ??
        0;

    const [mobileOpen, setMobileOpen] =
        useState(false);

    return (
        <div className="min-h-screen bg-slate-100 text-slate-900">
            <Sidebar
                role={resolvedRole}
                permissions={
                    resolvedPermissions
                }
                notificationCount={
                    notificationCount
                }
                logoUrl={
                    props.brand?.logo_url ??
                    null
                }
                brandName={
                    props.brand?.name ??
                    'BACKSTAGE'
                }
                brandSubtitle={
                    props.brand?.subtitle ??
                    'MIXX TUNE'
                }
                mobileOpen={mobileOpen}
                onMobileClose={() =>
                    setMobileOpen(false)
                }
            />

            <div className="min-h-screen transition-all duration-300 lg:pl-[272px]">
                <header className="sticky top-0 z-30 flex min-h-20 items-center justify-between border-b border-slate-200 bg-white/95 px-5 backdrop-blur lg:px-7">
                    <div className="flex items-center gap-4">
                        <button
                            type="button"
                            onClick={() =>
                                setMobileOpen(true)
                            }
                            className="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-xl text-slate-700 shadow-sm lg:hidden"
                        >
                            ☰
                        </button>

                        <div>
                            {title && (
                                <h1 className="text-lg font-semibold text-slate-900">
                                    {title}
                                </h1>
                            )}

                            {subtitle && (
                                <p className="mt-0.5 text-sm text-slate-500">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="hidden text-right sm:block">
                            <div className="text-sm font-semibold text-slate-900">
                                {props.auth?.user
                                    ?.name ??
                                    'Account'}
                            </div>

                            <div className="text-xs text-slate-500">
                                {props.auth?.user
                                    ?.email ?? ''}
                            </div>
                        </div>

                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 font-bold text-white">
                            {(
                                props.auth?.user
                                    ?.name ??
                                'A'
                            )
                                .charAt(0)
                                .toUpperCase()}
                        </div>
                    </div>
                </header>

                <main className="p-5 lg:p-7">
                    {children}
                </main>
            </div>
        </div>
    );
}
JSX


echo "[6/8] Creating backend navigation URL report..."

php artisan route:list --json > \
    v2/reports/laravel-routes.json

python3 - <<'PY'
from pathlib import Path
import json
import re

routes_file = Path(
    "v2/reports/laravel-routes.json"
)

report_file = Path(
    "v2/reports/navigation-route-report.txt"
)

route_data = json.loads(
    routes_file.read_text()
)

registered = set()

for route in route_data:
    uri = '/' + str(
        route.get('uri', '')
    ).lstrip('/')

    registered.add(
        re.sub(
            r'\{[^}]+\}',
            '*',
            uri
        )
    )

config = Path(
    "resources/js/V2/Shared/Config/panelRoutes.js"
).read_text()

urls = sorted(
    set(
        re.findall(
            r"'(/v2/[^']+)'",
            config
        )
    )
)

lines = [
    "V2 NAVIGATION ROUTE REPORT",
    "=" * 50,
    "",
]

for url in urls:
    path = url.split('?')[0]

    exact = path in registered

    prefix_match = any(
        route.startswith(path + '/')
        or path.startswith(
            route.rstrip('*')
        )
        for route in registered
    )

    status = (
        "REGISTERED"
        if exact or prefix_match
        else "MODULE ROUTE PENDING"
    )

    lines.append(
        f"{status:22} {url}"
    )

report_file.write_text(
    "\n".join(lines) + "\n"
)

print(report_file.read_text())
PY


echo "[7/8] Building frontend..."

npm run build

php artisan optimize:clear


echo "[8/8] Saving installation state..."

printf '{\n  "module": "SharedNavigationEngine",\n  "installed": true,\n  "version": "3.2.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/shared-navigation-engine-installed.json

echo ""
echo "===== NAVIGATION FILES ====="

ls -lah \
resources/js/V2/Shared/Config/panelRoutes.js \
resources/js/V2/Shared/Config/panelNavigation.js \
resources/js/V2/Shared/Navigation/Sidebar.jsx \
resources/js/V2/Shared/Layouts/PanelLayout.jsx

echo ""
echo "===== INSTALLATION STATE ====="

cat \
v2/runtime/state/shared-navigation-engine-installed.json

echo ""
echo "=================================================="
echo "SHARED NAVIGATION ENGINE INSTALLED"
echo "=================================================="

echo ""
echo "Route report:"
echo "$PROJECT/v2/reports/navigation-route-report.txt"

echo ""
echo "Backup:"
echo "$BACKUP"
