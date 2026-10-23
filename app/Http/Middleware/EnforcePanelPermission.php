<?php

namespace App\Http\Middleware;

use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnforcePanelPermission
{
    public function __construct(
        private readonly PermissionService $permissions
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        /*
         * Authentication middleware will handle guests.
         * Only V2 panel URLs are checked here.
         */
        /*
         * W92: panel pages no longer start with /v2. The rules below are
         * written for the panel addresses; the role areas (/super-admin,
         * /artist, /label, /admin/dashboard ...) keep their own role checks.
         */
        if (!$user) {
            return $next($request);
        }

        $requiredPermission =
            $this->requiredPermission(
                $request
            );

        if (!$requiredPermission) {
            return $next($request);
        }

        if (
            $this->permissions->allows(
                $user,
                $requiredPermission
            )
        ) {
            return $next($request);
        }

        $this->recordDeniedAccess(
            $request,
            $requiredPermission
        );

        abort(
            403,
            'You do not have permission to access this section.'
        );
    }

    private function requiredPermission(
        Request $request
    ): ?string {
        $method = strtoupper(
            $request->method()
        );

        /*
         * Super Admin user management.
         */
        if (
            $request->is(
                'admin/users*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'users.view'
                : 'users.manage';
        }

        /*
         * Master settings and audit logs.
         */
        if (
            $request->is(
                'admin/settings*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'settings.view'
                : 'settings.manage';
        }

        if (
            $request->is(
                'admin/audit-logs*'
            )
        ) {
            return 'audit_logs.view';
        }

        /*
         * Catalogue.
         */
        if (
            $request->is(
                'catalogue*'
            )
            || $request->is(
                'admin/catalogue*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'catalogue.view'
                : 'catalogue.manage';
        }

        /*
         * Reports.
         */
        if (
            $request->is(
                'reports*'
            )
            || $request->is(
                'admin/reports*'
            )
        ) {
            return 'reports.view';
        }

        /*
         * Royalties.
         */
        if (
            $request->is(
                'royalties*'
            )
            || $request->is(
                'admin/royalties*'
            )
        ) {
            return 'royalties.view';
        }

        /*
         * Wallet.
         */
        if (
            $request->is(
                'wallet*'
            )
            || $request->is(
                'admin/wallet*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'wallet.view'
                : 'wallet.manage';
        }

        /*
         * Withdrawals.
         */
        if (
            $request->is(
                'admin/withdrawals*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'withdrawals.view'
                : 'withdrawals.manage';
        }

        if (
            $request->is(
                'withdrawals*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'withdrawals.view'
                : 'withdrawals.create';
        }

        /*
         * Legal Operations.
         */
        if (
            $request->is(
                'legal-operations*'
            )
        ) {
            return 'legal.operations.view';
        }

        /*
         * Support tickets.
         */
        if (
            $request->is(
                'admin/support*'
            )
        ) {
            return 'support.manage';
        }

        if (
            $request->is(
                'support*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'support.create'
                : 'support.create';
        }

        /*
         * DSP delivery and distribution.
         */
        if (
            $request->is(
                'admin/distribution*'
            )
            || $request->is(
                'admin/delivery*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'delivery.view'
                : 'delivery.manage';
        }

        /*
         * ISRC and UPC management.
         */
        if (
            $request->is(
                'admin/identifiers*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'identifiers.view'
                : 'identifiers.assign';
        }

        /*
         * Release creation must be checked before
         * the general releases pattern.
         */
        if (
            $request->is(
                'releases/create'
            )
        ) {
            return 'releases.create';
        }

        if (
            $request->is(
                'releases*'
            )
        ) {
            if (
                in_array(
                    $method,
                    ['GET', 'HEAD'],
                    true
                )
            ) {
                return 'releases.view';
            }

            return 'releases.update';
        }

        /*
         * Admin release review and processing.
         */
        if (
            $request->is(
                'admin/release-reviews*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'releases.review'
                : 'releases.processing';
        }

        /*
         * Notifications and dashboards remain
         * available to authenticated users.
         */
        return null;
    }

    private function recordDeniedAccess(
        Request $request,
        string $permission
    ): void {
        try {
            app(
                AuditLogService::class
            )->record(
                'permission.denied',
                'permissions',
                sprintf(
                    'Access denied for %s on %s.',
                    $permission,
                    $request->path()
                ),
                [],
                [
                    'required_permission' =>
                        $permission,

                    'method' =>
                        $request->method(),

                    'path' =>
                        $request->path(),
                ],
                $request->user(),
                $request
            );
        } catch (Throwable) {
            /*
             * Permission enforcement must continue
             * even if audit logging is unavailable.
             */
        }
    }
}
