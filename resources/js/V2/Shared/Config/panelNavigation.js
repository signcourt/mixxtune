import {
    Bell,
    BookOpen,
    Boxes,
    CircleDollarSign,
    CirclePlus,
    ChartNoAxesCombined,
    ClipboardList,
    FileBarChart,
    FileText,
    Headphones,
    House,
    Landmark,
    Library,
    ListMusic,
    Settings,
    ShieldCheck,
    Store,
    Tags,
    UserRound,
    UsersRound,
    WalletCards,
    WalletMinimal,
    UserCog,
} from 'lucide-react';

import {
    getPanelRoutes,
} from '@/V2/Shared/Config/panelRoutes';

const icons = {
    dashboard: House,
    releases: Boxes,
    createRelease: CirclePlus,
    catalogue: Library,
    royalties: CircleDollarSign,
    wallet: WalletCards,
    withdrawals: WalletMinimal,
    reports: FileBarChart,
    analytics: ChartNoAxesCombined,
    kyc: UserRound,
    support: Headphones,
    notifications: Bell,
    settings: Settings,

    artists: UsersRound,
    labels: Tags,
    users: UsersRound,
    distribution: Store,
    identifiers: ListMusic,
    stores: Store,
    finance: Landmark,
    invoices: FileText,
    statements: ClipboardList,
    logs: BookOpen,
    activity: FileText,
    permissions: ShieldCheck,
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

const ownerNavigation = (
    routes,
    role
) => {
    const items = [
        {
            id: 'dashboard',
            label: 'Dashboard',
            icon: icons.dashboard,
            href: routes.dashboard,
            permission: 'dashboard.view',
            exact: true,
        },
        {
            id: 'create-release',
            label: 'Create Release',
            icon: icons.createRelease,
            href: routes.createRelease,
            permission: 'releases.create',
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
            id: 'financial',
            label: 'Financial',
            icon: icons.wallet,
            children: [
                {
                    id: 'financial-wallet',
                    label: 'Wallet',
                    href: routes.wallet,
                    permission: 'wallet.view',
                },
                {
                    id: 'financial-royalties',
                    label: 'Royalties',
                    href: routes.royalties,
                    permission: 'royalties.view',
                },
                {
                    id: 'financial-reports',
                    label: 'Reports',
                    href: routes.reports,
                    permission: 'reports.view',
                },
                {
                    id: 'financial-analytics',
                    label: 'Analytics',
                    href: routes.analytics,
                    permission: 'reports.view',
                },
            ],
        },
    ];

    if (role === 'label') {
        items.push(
            {
                id: 'artists',
                label: 'Artists',
                icon: icons.artists,
                href: routes.artists,
            },
            {
                id: 'revenue-sharing',
                label: 'Revenue Sharing',
                icon: icons.royalties,
                href: routes.revenueSharing,
                permission: 'revenue_sharing.manage',
            },
            {
                id: 'user-access',
                label: 'User Access',
                icon: UserCog,
                href: routes.userAccess,
                permission: 'team.manage',
            }
        );
    }

    items.push(
        {
            id: 'kyc-profile',
            label: 'KYC & Profile',
            icon: icons.kyc,
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
        }
    );

    return items;
};

const adminNavigation = (
    routes,
    role
) => {
    const items = [
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
        items.push(
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
                icon: icons.permissions,
                href: routes.admins,
                permission: 'admins.view',
            }
        );
    }

    items.push(
        {
            id: 'labels',
            label: 'Labels',
            icon: icons.labels,
            href: routes.labels,
            permission: 'labels.view',
        },
        ...(role === 'super_admin'
            ? [
                  {
                      id: 'label-hierarchy',
                      label: 'Level Management',
                      icon: icons.labels,
                      href: routes.labelHierarchy,
                      permission: 'labels.view',
                  },
              ]
            : []),
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
            id: 'create-release',
            label: 'Create Release',
            icon: icons.createRelease,
            href: routes.createRelease,
            permission: 'releases.create',
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
            children: [
                {
                    id: 'catalogue-all',
                    label: 'All Catalogue',
                    href: routes.catalogue,
                    permission: 'catalogue.view',
                },
                {
                    id: 'catalogue-ownership',
                    label: 'Ownership Transfer',
                    href: '/v2/admin/ownership',
                    permission: 'catalogue.view',
                },
                {
                    id: 'catalogue-transfer-history',
                    label: 'Transfer History',
                    href: '/v2/admin/ownership',
                    permission: 'catalogue.view',
                },
                {
                    id: 'catalogue-legacy-import',
                    label: 'Legacy Import',
                    href: routes.legacyCatalogueImports,
                    permission: 'catalogue.view',
                },
            ],
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
        },
        {
            id: 'analytics',
            label: 'Analytics',
            icon: icons.analytics,
            href: routes.analytics,
            permission: 'reports.view',
        },
        {
            id: 'revenue-imports',
            label: 'Revenue Imports',
            icon: icons.reports,
            href: '/v2/admin/reports/imports',
            permission: 'reports.view',
        },
        {
            id: 'unmapped-revenue',
            label: 'Unmapped Revenue',
            icon: icons.reports,
            href: routes.unmappedRevenue,
            permission: 'reports.view',
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
            label: 'Withdraw Requests',
            icon: icons.withdrawals,
            href: routes.withdrawals,
            permission: 'withdrawals.manage',
        },
        {
            id: 'kyc-reviews',
            label: 'KYC Reviews',
            icon: icons.kyc,
            href: routes.kycReviews,
            permission: 'withdrawals.manage',
        },
        {
            id: 'support',
            label: 'Support Tickets',
            icon: icons.support,
            href: routes.support,
            permission: 'support.manage',
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
        }
    );

    return items;
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

    return ownerNavigation(
        routes,
        normalizedRole
    );
};

export default getPanelNavigation;
