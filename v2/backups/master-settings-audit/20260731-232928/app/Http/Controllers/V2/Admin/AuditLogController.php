<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\System\AuditLog;
use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $module = trim(
            (string) $request->input(
                'module',
                ''
            )
        );

        $query = AuditLog::query()
            ->with(
                'user:id,name,email'
            );

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'action',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'ip_address',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($module !== '') {
            $query->where(
                'module',
                $module
            );
        }

        return Inertia::render(
            'V2/Admin/AuditLogs/Index',
            [
                'role' => $role,

                'filters' => [
                    'search' => $search,
                    'module' => $module,
                ],

                'modules' =>
                    AuditLog::query()
                        ->whereNotNull('module')
                        ->distinct()
                        ->orderBy('module')
                        ->pluck('module'),

                'logs' =>
                    $query
                        ->orderByDesc('id')
                        ->paginate(50)
                        ->withQueryString(),
            ]
        );
    }
}
