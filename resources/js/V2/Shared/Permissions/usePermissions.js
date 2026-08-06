import { can, cannot, normalizeRole } from './permissions';

export default function usePermissions({
    role = 'artist',
    permissions = null,
} = {}) {
    const normalizedRole = normalizeRole(role);

    return {
        role: normalizedRole,

        can: (permission) =>
            can(
                normalizedRole,
                permission,
                permissions
            ),

        cannot: (permission) =>
            cannot(
                normalizedRole,
                permission,
                permissions
            ),

        isSuperAdmin:
            normalizedRole === 'super_admin',

        isAdmin:
            normalizedRole === 'admin',

        isLabel:
            normalizedRole === 'label',

        isArtist:
            normalizedRole === 'artist',
    };
}
