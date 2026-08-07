import { can } from './permissions';

export default function Can({
    role = 'artist',
    permission,
    permissions = null,
    fallback = null,
    children,
}) {
    if (!can(role, permission, permissions)) {
        return fallback;
    }

    return children;
}
