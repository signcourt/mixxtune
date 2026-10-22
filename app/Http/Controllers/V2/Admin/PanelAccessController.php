<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\AuditLogService;
use App\Services\V2\PanelAccess;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/*
 * W81 MIXXTUNE_PANEL_ACCESS
 * Super Admin page: which sidebar tabs Label, Artist and Manager accounts
 * get by default. One account can still be allowed / blocked per tab on
 * Edit User.
 */
class PanelAccessController extends Controller
{
    private function superAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can change panel access.');
    }

    public function index(Request $request, PermissionService $permissions, PanelAccess $access): Response
    {
        $this->superAdmin($request, $permissions);

        $ownSettings = $access->ready()
            ? DB::table('panel_access_overrides')->join('users', 'users.id', '=', 'panel_access_overrides.user_id')
                ->selectRaw('users.role, COUNT(DISTINCT panel_access_overrides.user_id) as total')->groupBy('users.role')->pluck('total', 'role')->all()
            : [];

        $roles = [];
        foreach (PanelAccess::ROLE_NAMES as $role => $name) {
            $values = $access->roleDefaults($role);
            $tabs = [];
            foreach ($access->tabs($role) as $key => $tab) {
                $tabs[] = ['key' => $key, 'name' => $tab[0], 'help' => $tab[1], 'standard' => $access->standardDefault($role, $key), 'value' => $values[$key]];
            }
            $roles[] = [
                'role' => $role,
                'name' => $name,
                'panel' => PanelAccess::ROLES[$role] === 'owner' ? 'Owner panel' : 'Manager panel',
                'accounts' => DB::table('users')->where('role', $role)->count(),
                'own_settings' => (int) ($ownSettings[$role] ?? 0),
                'tabs' => $tabs,
            ];
        }

        return Inertia::render('V2/Admin/PanelAccess/Index', [
            'role' => 'super_admin',
            'roles' => $roles,
            'ready' => $access->ready(),
        ]);
    }

    public function update(Request $request, PermissionService $permissions, PanelAccess $access, AuditLogService $audit): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        abort_unless($access->ready(), 503, 'Panel access tables are missing.');

        $data = $request->validate([
            'defaults' => ['required', 'array'],
            'defaults.*' => ['array'],
            'defaults.*.*' => ['boolean'],
        ]);

        $saved = [];
        foreach ($data['defaults'] as $role => $values) {
            if (!isset(PanelAccess::ROLES[$role])) {
                continue;
            }
            $values = array_intersect_key($values, $access->tabs($role));
            $changed = $access->saveRoleDefaults($role, $values, (int) $request->user()->id);
            if (!$changed) {
                continue;
            }
            $name = PanelAccess::ROLE_NAMES[$role];
            $saved[] = $name;
            $audit->record(
                'panel.role_defaults_changed',
                'permissions',
                "Default panel access for {$name} accounts changed: ".implode(', ', array_map(fn ($k, $c) => $k.' '.($c['to'] ? 'on' : 'off'), array_keys($changed), $changed)).'.',
                array_map(fn ($c) => $c['from'], $changed),
                array_map(fn ($c) => $c['to'], $changed),
                null,
                $request
            );
        }

        return back()->with('success', $saved ? 'Saved: '.implode(', ', $saved).' panel access.' : 'Nothing changed.');
    }
}
