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
    recoupment: Landmark,
    invoices: FileText,
    statements: ClipboardList,
    logs: BookOpen,
    activity: FileText,
    permissions: ShieldCheck,
};

/*
 * MIXXTUNE_NAV_RELEASES_CATALOGUE
 * Releases = the work flow (drafts -> review -> approved -> processing).
 * Catalogue = finished releases (approved / delivered / live) + tools.
 * Every release status now has exactly one place in the menu.
 */
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
                id: 'releases-draft',
                label: 'My Drafts',
                href: routes.releasesDraft,
                queryStatus: 'draft',
            },
            {
                id: 'releases-review',
                label: 'In Review',
                href: routes.releasesReview,
                queryStatus: 'submitted',
            },
            {
                id: 'releases-changes',
                label: 'Changes Requested',
                href: routes.releasesChanges,
                queryStatus: 'changes_requested',
            },
            {
                id: 'releases-approved',
                label: 'Approved',
                href: routes.releasesApproved,
                queryStatus: 'approved',
            },
            {
                id: 'releases-processing',
                label: 'Processing',
                href: routes.releasesProcessing,
                queryStatus: 'processing',
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
            id: 'releases-draft',
            label: 'Drafts',
            href: routes.releasesDraft,
            queryStatus: 'draft',
        },
        {
            id: 'releases-review',
            label: 'In Review',
            href: routes.releasesReview,
            queryStatus: 'submitted',
        },
        {
            id: 'releases-changes',
            label: 'Changes Requested',
            href: routes.releasesChanges,
            queryStatus: 'changes_requested',
        },
        {
            id: 'releases-live',
            label: 'Live',
            href: routes.releasesLive,
            queryStatus: 'live',
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
        {
            id: 'legal-operations',
            label: 'Legal Operations',
            icon: ShieldCheck,
            href: '/v2/legal-operations',
            children: [
                {
                    id: 'legal-copyright-claims',
                    label: 'Copyright & Claims',
                    href: '/v2/support/create?category=copyright&legal_section=copyright',
                },
                {
                    id: 'legal-youtube',
                    label: 'YouTube Operations',
                    href: '/v2/support/create?category=copyright&legal_section=youtube',
                },
                {
                    id: 'legal-spotify',
                    label: 'Spotify Operations',
                    href: '/v2/support/create?category=general&legal_section=spotify',
                },
                {
                    id: 'legal-dsp',
                    label: 'Other DSP Operations',
                    href: '/v2/support/create?category=general&legal_section=dsp',
                },
                {
                    id: 'legal-rights',
                    label: 'Rights & Ownership',
                    href: '/v2/support/create?category=general&legal_section=rights',
                },
                {
                    id: 'legal-requests',
                    label: 'Legal Requests',
                    href: '/v2/support/create?category=general&legal_section=request',
                },
                {
                    id: 'legal-history',
                    label: 'Case History',
                    href: '/v2/support?legal_section=history',
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
            permission: 'support.create',
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

    items.push(
        {
            id: 'account-management',
            label: 'Accounts & Access',
            icon: icons.users,
            children: [
                ...(role === 'super_admin'
                    ? [
                          {
                              id: 'account-users',
                              label: 'Users',
                              href: routes.users,
                              permission: 'users.view',
                          },
                          {
                              id: 'account-managers',
                              label: 'Managers',
                              href: routes.admins,
                              permission: 'admins.view',
                          },
                      ]
                    : []),
                {
                    id: 'account-labels',
                    label: 'Labels',
                    href: routes.labels,
                    permission: 'labels.view',
                },
                ...(role === 'super_admin'
                    ? [
                          {
                              id: 'account-level-management',
                              label: 'Level Management',
                              href: routes.labelHierarchy,
                              permission: 'labels.view',
                          },
                      ]
                    : []),
                {
                    id: 'account-artists',
                    label: 'Artists',
                    href: routes.artists,
                    permission: 'artists.view',
                },
            ],
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
            id: 'distribution-operations',
            label: 'Distribution & Stores',
            icon: icons.distribution,
            children: [
                {
                    id: 'distribution-delivery',
                    label: 'Distribution',
                    href: routes.distribution,
                    permission: 'delivery.view',
                },
                {
                    id: 'distribution-identifiers',
                    label: 'ISRC & UPC',
                    href: routes.identifiers,
                    permission: 'identifiers.view',
                },
                {
                    id: 'distribution-stores',
                    label: 'DSP Stores',
                    href: routes.stores,
                    permission: 'stores.view',
                },
            ],
        },
        {
            id: 'catalogue',
            label: 'Catalogue',
            icon: icons.catalogue,
            href: routes.catalogue,
            permission: 'catalogue.view',
            children:
                role === 'super_admin'
                    ? [
                          {
                              id: 'catalogue-all',
                              label: 'All Catalogue',
                              href: routes.catalogue,
                              permission: 'catalogue.view',
                              /* W57: tools that now open from the All Catalogue page keep this item lit */
                              activePrefixes: [
                                  '/super-admin/catalogue-health',
                                  '/super-admin/posters',
                                  routes.legacyCatalogueImports,
                              ],
                          },
                          {
                              /* W57: Transfer History is a tab inside this page */
                              id: 'catalogue-ownership',
                              label: 'Ownership Transfer',
                              href: '/v2/admin/ownership',
                              permission: 'catalogue.view',
                          },
                      ]
                    : [
                          {
                              id: 'catalogue-all',
                              label: 'All Catalogue',
                              href: routes.catalogue,
                              permission: 'catalogue.view',
                          },
                      ],
        },
        {
            id: 'financial',
            label: 'Financial',
            icon: icons.wallet,
            children: [
                // MIXXTUNE_NAV_FINANCE_ROYALTIES_V1: admin statement finance page
                {
                    id: 'financial-finance-royalties',
                    label: 'Finance & Royalties',
                    href: '/v2/admin/finance',
                },
                /* MIXXTUNE_FINANCE_CLEANUP_V1: statement lists are in Finance & Royalties / Statements;
                   this page is only the IPRS / PRS rights royalty upload (super admin) */
                ...(role === 'super_admin'
                    ? [
                          {
                              id: 'financial-royalties',
                              label: 'Rights Royalties',
                              href: routes.royalties,
                              permission: 'royalties.view',
                          },
                      ]
                    : []),
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
                {
                    id: 'financial-revenue-imports',
                    label: 'Revenue Imports',
                    href: '/v2/admin/reports/imports',
                    permission: 'reports.view',
                },
                /* MIXXTUNE_FINANCE_CLEANUP_V1: the page itself is super admin only */
                ...(role === 'super_admin'
                    ? [
                          {
                              id: 'financial-unmapped-revenue',
                              label: 'Unmapped Revenue',
                              href: routes.unmappedRevenue,
                              permission: 'reports.view',
                          },
                      ]
                    : []),
                {
                    id: 'financial-wallet',
                    label: 'Wallet',
                    href: routes.wallet,
                    permission: 'wallet.view',
                },
                ...(role === 'super_admin'
                    ? [
                          // MIXXTUNE_OWNER_STATEMENT_V1: statements + e-mail to owners
                          {
                              id: 'financial-statements',
                              label: 'Statements',
                              href: '/v2/admin/statements',
                          },
                          {
                              id: 'financial-invoices',
                              label: 'Invoices',
                              href: '/v2/admin/invoices',
                          },
                          {
                              id: 'financial-recoupment-advances',
                              label: 'Recoupment & Advances',
                              href: '/super-admin/finance/recoupment',
                          },
                          {
                              id: 'financial-adjustments',
                              label: 'Adjustments',
                              href: '/super-admin/finance/adjustments',
                          },
                      ]
                    : []),
                {
                    id: 'financial-withdrawals',
                    label: 'Withdraw Requests',
                    href: routes.withdrawals,
                    permission: 'withdrawals.manage',
                },
            ],
        },
        {
            id: 'administration',
            label: 'Administration',
            icon: icons.settings,
            children: [
                {
                    id: 'administration-kyc',
                    label: 'KYC Reviews',
                    href: routes.kycReviews,
                    permission: 'withdrawals.manage',
                },
                {
                    id: 'administration-support',
                    label: 'Support Tickets',
                    href: routes.support,
                    permission: 'support.manage',
                },
                {
                    id: 'administration-notifications',
                    label: 'Notifications',
                    href: routes.notifications,
                    badgeKey: 'notifications',
                },
                /* MIXXTUNE_NOTIFY_TARGETED_V1 */
                ...(role === 'super_admin'
                    ? [{
                          id: 'administration-send-notification',
                          label: 'Send Notification',
                          href: '/v2/admin/notification-management',
                      }]
                    : []),
                {
                    id: 'administration-settings',
                    label: 'Settings',
                    href: routes.settings,
                    permission: 'settings.view',
                },
            ],
        },
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
