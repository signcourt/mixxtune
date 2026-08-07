export const PANEL_ROUTES = {
    common: {
        notifications: '/v2/notifications',
    },

    artist: {
        dashboard:
            '/artist/dashboard',

        releases:
            '/artist/releases',
        releasesAll:
            '/artist/releases',
        releasesLive:
            '/artist/releases?status=live',
        releasesReview:
            '/artist/releases?status=submitted',
        releasesDraft:
            '/artist/releases?status=draft',
        releasesRejected:
            '/artist/releases?status=rejected',

        createRelease:
            '/artist/releases/create',

        catalogue:
            '/artist/catalogue',

        royalties:
            '/artist/royalties',

        wallet:
            '/artist/wallet',

        withdrawals:
            '/artist/withdrawals',

        reports:
            '/artist/reports',

        kyc:
            '/artist/kyc',

        support:
            '/artist/support',

        notifications:
            '/artist/notifications',

        settings:
            '/profile',

        statements:
            '/artist/statements',

        invoices:
            '/artist/invoices',
    },

    label: {
        dashboard:
            '/label/dashboard',

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
            '/profile',
    },

    admin: {
        dashboard:
            '/admin/dashboard',

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
            '/super-admin/dashboard',

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
