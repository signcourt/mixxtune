export const PANEL_ROUTES = {
    common: {
        notifications: "/notifications",
    },

    artist: {
        dashboard: "/artist/dashboard",

        releases: "/artist/releases",
        releasesAll: "/artist/releases",
        releasesLive: "/artist/releases?status=live",
        releasesReview: "/artist/releases?status=submitted",
        releasesDraft: "/artist/releases?status=draft",
        releasesRejected: "/artist/releases?status=rejected",
        releasesChanges: "/artist/releases?status=changes_requested",

        createRelease: "/artist/releases/create",

        catalogue: "/artist/catalogue",

        royalties: "/artist/royalties",

        wallet: "/artist/wallet",

        withdrawals: "/artist/withdrawals",

        reports: "/artist/reports",
        analytics: "/analytics",

        kyc: "/artist/kyc",

        support: "/artist/support",

        notifications: "/notifications",

        settings: "/artist/settings",

        statements: "/artist/statements",

        invoices: "/artist/invoices",
    },

    label: {
        dashboard: "/label/dashboard",

        artists: "/label/artists",

        revenueSharing: "/label/revenue-sharing",

        userAccess: "/label/user-access",

        releases: "/releases",
        releasesAll: "/releases",
        releasesLive: "/releases?status=live",
        releasesReview: "/releases?status=submitted",
        releasesDraft: "/releases?status=draft",
        releasesRejected: "/releases?status=rejected",
        releasesChanges: "/releases?status=changes_requested",

        createRelease: "/releases/create",

        catalogue: "/catalogue",

        royalties: "/royalties",

        wallet: "/wallet",

        withdrawals: "/withdrawals",

        reports: "/reports",
        analytics: "/analytics",

        kyc: "/kyc-profile",

        support: "/support",

        notifications: "/notifications",

        settings: "/label/settings",
    },

    admin: {
        dashboard: "/admin/dashboard",

        artists: "/admin/artists",

        labels: "/admin/labels",
        labelHierarchy: "/super-admin/label-hierarchy",

        releases: "/releases",

        releasesAll: "/releases",

        releasesReview: "/admin/release-reviews?status=submitted",

        releasesApproved: "/admin/release-reviews?status=approved",

        releasesRejected: "/admin/release-reviews?status=rejected",

        releasesChanges: "/admin/release-reviews?status=changes_requested",

        releasesProcessing: "/admin/release-reviews?status=processing",

        releasesDraft: "/releases?status=draft",

        createRelease: "/releases/create",

        distribution: "/admin/distribution",

        identifiers: "/admin/identifiers/pending",

        catalogue: "/catalogue",

        stores: "/admin/stores",

        royalties: "/admin/royalties",

        reports: "/admin/reports",
        analytics: "/analytics",
        unmappedRevenue: "/super-admin/unmapped-revenue",

        wallet: "/admin/wallet",

        withdrawals: "/admin/withdrawals",

        kycReviews: "/admin/kyc",

        support: "/admin/support",

        notifications: "/notifications",

        settings: "/admin/settings",
    },

    super_admin: {
        dashboard: "/super-admin/dashboard",

        users: "/admin/users",

        admins: "/admin/admins",

        labels: "/admin/labels",
        labelHierarchy: "/super-admin/label-hierarchy",

        artists: "/admin/artists",

        releases: "/admin/release-reviews",

        /* MIXXTUNE_NAV_RELEASES_CATALOGUE: "All Releases" = every stage (was the review queue, which only showed In Review) */
        releasesAll: "/releases",

        releasesReview: "/admin/release-reviews?status=submitted",

        releasesApproved: "/admin/release-reviews?status=approved",

        releasesRejected: "/admin/release-reviews?status=rejected",

        releasesChanges: "/admin/release-reviews?status=changes_requested",

        releasesProcessing: "/admin/release-reviews?status=processing",

        releasesDraft: "/releases?status=draft",

        createRelease: "/releases/create",

        distribution: "/admin/distribution",

        identifiers: "/admin/identifiers/pending",

        stores: "/admin/stores",

        catalogue: "/catalogue",
        legacyCatalogueImports: "/super-admin/legacy-catalogue-imports",

        royalties: "/admin/royalties",

        reports: "/admin/reports",
        analytics: "/analytics",
        unmappedRevenue: "/super-admin/unmapped-revenue",

        finance: "/admin/finance",

        invoices: "/admin/invoices",

        statements: "/admin/statements",

        wallet: "/admin/wallet",

        withdrawals: "/admin/withdrawals",

        kycReviews: "/admin/kyc",

        support: "/admin/support",

        logs: "/admin/audit-logs",

        activity: "/admin/audit-logs",

        notifications: "/notifications",

        settings: "/admin/settings",
    },
};

export const getPanelRoutes = (role = "artist") => {
    const normalizedRole = role === "super-admin" ? "super_admin" : role;

    return PANEL_ROUTES[normalizedRole] ?? PANEL_ROUTES.artist;
};
