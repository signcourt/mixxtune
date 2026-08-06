export const rolePermissions = {
    super_admin: ['*'],

    admin: [
        'dashboard.view',
        'releases.view',
        'releases.create',
        'releases.update',
            'contributors.view',
            'contributors.manage',
            'splits.view',
            'splits.manage',
        'releases.review',
        'releases.approve',
        'releases.reject',
            'releases.request_changes',
            'releases.processing',
        'artists.view',
        'labels.view',
        'catalogue.view',
            'catalogue.manage',
            'catalogue.sync',
            'catalogue.export',
        'reports.view',
        'delivery.manage',
            'audio_validation.view',
            'audio_validation.run',
            'audio_validation.override',
            'identifiers.view',
            'identifiers.assign',
            'identifiers.generate',
            'identifiers.bulk_assign',
            'identifiers.unassign',
            'delivery.view',
            'delivery.retry',
            'delivery.mark_delivered',
            'delivery.mark_live',
            'delivery.mark_failed',
            'delivery.takedown',
        'support.manage',
    ],

    label: [
        'dashboard.view',
        'releases.view',
        'releases.create',
        'releases.update',
        'releases.submit',
        'artists.view',
        'artists.manage',
        'catalogue.view',
        'reports.view',
        'royalties.view',
        'wallet.view',
        'withdrawals.create',
        'support.create',
        'profile.update',
    ],

    artist: [
        'dashboard.view',
        'releases.view',
        'releases.create',
        'releases.update',
        'releases.submit',
        'catalogue.view',
        'reports.view',
        'royalties.view',
        'wallet.view',
        'withdrawals.create',
        'support.create',
        'profile.update',
    ],
};

export function normalizeRole(role) {
    const normalized = String(role || 'artist')
        .trim()
        .toLowerCase();

    return rolePermissions[normalized]
        ? normalized
        : 'artist';
}

export function can(role, permission, serverPermissions = null) {
    const permissions = Array.isArray(serverPermissions)
        ? serverPermissions
        : rolePermissions[normalizeRole(role)];

    return permissions.includes('*')
        || permissions.includes(permission);
}

export function cannot(
    role,
    permission,
    serverPermissions = null
) {
    return !can(role, permission, serverPermissions);
}
