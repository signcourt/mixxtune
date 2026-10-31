<?php

namespace App\Http\Controllers\V2\Office;

use App\Http\Controllers\Controller;
use App\Models\Office\Attendance;
use App\Models\Office\FunctionalRole;
use App\Models\Office\OfficeTask;
use App\Models\Office\TaskTemplate;
use App\Models\User;
use App\Services\V2\FinanceAudit;
use App\Models\Office\OfficeDevice;
use App\Models\Office\Screenshot;
use App\Services\V2\Office\OfficeService;
use App\Services\V2\Office\OfficeSettings;
use App\Services\V2\PermissionService;
use App\Services\V2\StaffAlerts;
use App\Services\V2\SystemSettingService;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/*
 * W127 MIXXTUNE_OFFICE (Super Admin): staff, the role/template library, giving tasks,
 * reviewing the proof, and the time report.
 */
class OfficeAdminController extends Controller
{
    public function __construct(private readonly OfficeService $office)
    {
    }

    private function boss(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can open the Office.');
    }

    /** today's board + the review queue */
    public function index(Request $request, PermissionService $permissions): Response
    {
        $this->boss($request, $permissions);
        $date = Carbon::now(OfficeService::TZ)->toDateString();
        $staff = User::query()->where('role', 'staff')->orderBy('name')->get(['id', 'name']);
        $att = Attendance::query()->whereDate('work_date', $date)->get()->keyBy('user_id');

        $board = $staff->map(function ($u) use ($att) {
            $a = $att->get($u->id);
            /* W129: no login today = absent */
            $state = 'absent'; /* absent | working | idle | break | done */
            if ($a && $a->clock_in_at && !$a->clock_out_at) {
                $state = $a->on_break ? 'break' : (($a->last_ping_at && $a->last_ping_at->gt(now()->subMinutes(3))) ? 'working' : 'idle');
            } elseif ($a && $a->clock_out_at) {
                $state = 'done';
            }

            return [
                'id' => $u->id, 'name' => $u->name, 'state' => $state,
                'clock_in' => $a?->clock_in_at?->timezone(OfficeService::TZ)->format('h:i A'),
                'active' => $a ? $this->hm($a->active_seconds) : '0h 0m',
                'open_tasks' => OfficeTask::query()->where('assigned_to', $u->id)->whereIn('status', OfficeTask::OPEN)->count(),
            ];
        });

        $review = OfficeTask::query()->with('assignee:id,name')->where('status', 'submitted')->orderBy('submitted_at')
            ->get()->map(fn (OfficeTask $t) => [
                'id' => $t->id, 'title' => $t->title, 'who' => $t->assignee?->name,
                'submitted' => $t->submitted_at?->timezone(OfficeService::TZ)->format('d M, h:i A'),
                'proofs' => $t->proofs->map(fn ($p) => ['kind' => $p->kind, 'link' => $p->link, 'path' => $p->path ? '/storage/'.$p->path : null, 'note' => $p->note])->values(),
            ]);

        return Inertia::render('V2/Office/Index', [
            'role' => 'super_admin',
            'board' => $board,
            'review' => $review,
            'counts' => [
                'staff' => $staff->count(),
                'absent' => $board->where('state', 'absent')->count(),
                'open' => OfficeTask::query()->whereIn('status', OfficeTask::OPEN)->count(),
                'review' => $review->count(),
                'overdue' => OfficeTask::query()->whereIn('status', OfficeTask::OPEN)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            ],
        ]);
    }

    private function hm(int $s): string
    {
        return intdiv($s, 3600).'h '.intdiv($s % 3600, 60).'m';
    }

    /* ---- staff ---- */

    public function staff(Request $request, PermissionService $permissions): Response
    {
        $this->boss($request, $permissions);
        $roles = FunctionalRole::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $staff = User::query()->where('role', 'staff')->orderBy('name')->get(['id', 'name', 'email', 'phone', 'account_status'])
            ->map(function ($u) {
                $roleIds = DB::table('office_staff_roles')->where('user_id', $u->id)->pluck('functional_role_id');

                return [
                    'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
                    'active' => $u->account_status === 'active',
                    'roles' => FunctionalRole::query()->whereIn('id', $roleIds)->pluck('name'),
                    'open_tasks' => OfficeTask::query()->where('assigned_to', $u->id)->whereIn('status', OfficeTask::OPEN)->count(),
                ];
            });

        return Inertia::render('V2/Office/Staff', ['role' => 'super_admin', 'staff' => $staff, 'roles' => $roles]);
    }

    public function addStaff(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->boss($request, $permissions);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'roles' => ['array'],
            'roles.*' => ['integer', 'exists:office_functional_roles,id'],
        ]);
        $password = Str::password(10, true, true, false);
        $user = User::query()->create([
            'name' => $data['name'], 'email' => mb_strtolower($data['email']), 'phone' => $data['phone'] ?? null,
            'role' => 'staff', 'account_status' => 'active', 'kyc_status' => 'not_required',
            'password' => $password, 'email_verified_at' => now(),
        ]);
        foreach ($data['roles'] ?? [] as $rid) {
            DB::table('office_staff_roles')->insertOrIgnore(['user_id' => $user->id, 'functional_role_id' => $rid, 'created_at' => now(), 'updated_at' => now()]);
        }
        FinanceAudit::log('office.staff_added', 'Office staff "'.$user->name.'" ('.$user->email.') added.', ['user_id' => $user->id]);

        return back()->with('success', 'Staff added. Login: '.$user->email.'  ·  one-time password: '.$password.'  (give it to them; they get an e-mailed code at login).');
    }

    public function setStaffRoles(Request $request, int $user, PermissionService $permissions): RedirectResponse
    {
        $this->boss($request, $permissions);
        $u = User::query()->where('role', 'staff')->findOrFail($user);
        $data = $request->validate(['roles' => ['array'], 'roles.*' => ['integer', 'exists:office_functional_roles,id'], 'active' => ['boolean']]);
        DB::table('office_staff_roles')->where('user_id', $u->id)->delete();
        foreach ($data['roles'] ?? [] as $rid) {
            DB::table('office_staff_roles')->insertOrIgnore(['user_id' => $u->id, 'functional_role_id' => $rid, 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($request->has('active')) {
            $u->forceFill(['account_status' => $request->boolean('active') ? 'active' : 'suspended'])->save();
        }

        return back()->with('success', 'Saved.');
    }

    /* ---- role & template library ---- */

    public function library(Request $request, PermissionService $permissions): Response
    {
        $this->boss($request, $permissions);
        $roles = FunctionalRole::query()->withCount('templates')->orderBy('name')->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'description' => $r->description, 'templates' => $r->templates_count]);
        $templates = TaskTemplate::query()->orderBy('functional_role_id')->orderBy('title')->get()
            ->map(fn (TaskTemplate $t) => ['id' => $t->id, 'role_id' => $t->functional_role_id, 'title' => $t->title, 'description' => $t->description, 'priority' => $t->priority, 'proof_type' => $t->proof_type, 'checklist' => $t->checklist ?? []]);

        return Inertia::render('V2/Office/Library', ['role' => 'super_admin', 'roles' => $roles, 'templates' => $templates]);
    }

    public function addRole(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->boss($request, $permissions);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:1000']]);
        FunctionalRole::query()->create([
            'public_id' => (string) Str::ulid(), 'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'description' => $data['description'] ?? null, 'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Role added.');
    }

    public function addTemplate(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->boss($request, $permissions);
        $data = $request->validate([
            'functional_role_id' => ['nullable', 'integer', 'exists:office_functional_roles,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'proof_type' => ['nullable', Rule::in(['none', 'link', 'file', 'note'])],
            'checklist' => ['array'],
            'checklist.*' => ['string', 'max:300'],
        ]);
        TaskTemplate::query()->create([
            'public_id' => (string) Str::ulid(), 'functional_role_id' => $data['functional_role_id'] ?? null,
            'title' => $data['title'], 'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'normal', 'proof_type' => $data['proof_type'] ?? 'none',
            'checklist' => array_values(array_filter(array_map('trim', $data['checklist'] ?? []))),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Template added.');
    }

    /* ---- give a task ---- */

    public function assign(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->boss($request, $permissions);
        $data = $request->validate([
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'staff')],
            'template_id' => ['nullable', 'integer', 'exists:office_task_templates,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'due_at' => ['nullable', 'date'],
            'checklist' => ['array'],
            'checklist.*' => ['string', 'max:300'],
        ]);
        $tpl = isset($data['template_id']) ? TaskTemplate::query()->find($data['template_id']) : null;
        $task = $this->office->createTask($request->user(), [
            'assigned_to' => $data['assigned_to'],
            'template_id' => $tpl?->id,
            'functional_role_id' => $tpl?->functional_role_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? $tpl?->description,
            'priority' => $data['priority'] ?? ($tpl?->priority ?? 'normal'),
            'estimated_minutes' => $data['estimated_minutes'] ?? $tpl?->sla_minutes,
            /* the picker gives an India-time wall clock; store it as UTC */
            'due_at' => !empty($data['due_at']) ? Carbon::parse($data['due_at'], OfficeService::TZ) : null,
            'checklist' => $data['checklist'] ?? ($tpl?->checklist ?? []),
        ]);
        $assignee = User::query()->find($data['assigned_to']);
        try {
            app(StaffAlerts::class)->bell([$assignee], 'office.task_assigned', 'New task: '.$task->title, 'You have a new task in My Office.', '/office', 'info');
        } catch (\Throwable) {
        }

        return back()->with('success', 'Task given to '.$assignee?->name.'.');
    }

    /* ---- review ---- */

    public function verify(Request $request, int $task, PermissionService $permissions): RedirectResponse
    {
        $this->boss($request, $permissions);
        $t = OfficeTask::query()->findOrFail($task);
        $this->office->verify($request->user(), $t, $request->input('note'));
        try {
            app(StaffAlerts::class)->bell([User::query()->find($t->assigned_to)], 'office.task_verified', 'Task approved: '.$t->title, 'Your task was approved. Well done!', '/office', 'success');
        } catch (\Throwable) {
        }

        return back()->with('success', 'Approved.');
    }

    public function requestChanges(Request $request, int $task, PermissionService $permissions): RedirectResponse
    {
        $this->boss($request, $permissions);
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $t = OfficeTask::query()->findOrFail($task);
        $this->office->requestChanges($request->user(), $t, $data['note']);
        try {
            app(StaffAlerts::class)->bell([User::query()->find($t->assigned_to)], 'office.task_changes', 'Changes asked: '.$t->title, $data['note'], '/office', 'warning');
        } catch (\Throwable) {
        }

        return back()->with('success', 'Sent back for changes.');
    }

    /* ---- time report ---- */

    public function report(Request $request, PermissionService $permissions): Response
    {
        $this->boss($request, $permissions);
        $range = $request->input('range', 'week');
        $tz = OfficeService::TZ;
        $from = match ($range) {
            'today' => Carbon::now($tz)->startOfDay(),
            'month' => Carbon::now($tz)->startOfMonth(),
            default => Carbon::now($tz)->subDays(6)->startOfDay(),
        };
        $rows = DB::table('office_attendance as a')->join('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.work_date', '>=', $from->toDateString())
            ->groupBy('a.user_id', 'u.name')
            ->orderBy('u.name')
            ->get([
                'u.name', 'a.user_id',
                DB::raw('count(*) as days'),
                DB::raw('sum(a.active_seconds) as active'),
                DB::raw('sum(a.break_seconds) as brk'),
                DB::raw('sum(a.task_seconds) as task'),
                DB::raw('sum(a.tasks_done) as done'),
            ])->map(fn ($r) => [
                'name' => $r->name, 'days' => (int) $r->days,
                'active' => $this->hm((int) $r->active), 'break' => $this->hm((int) $r->brk),
                'task' => $this->hm((int) $r->task), 'done' => (int) $r->done,
                'active_seconds' => (int) $r->active,
            ]);

        return Inertia::render('V2/Office/Report', ['role' => 'super_admin', 'range' => $range, 'rows' => $rows]);
    }

    /* ---- W128 monitoring: settings ---- */

    public function settings(Request $request, PermissionService $permissions): Response
    {
        $this->boss($request, $permissions);

        return Inertia::render('V2/Office/Settings', ['role' => 'super_admin', 'settings' => OfficeSettings::all()]);
    }

    public function saveSettings(Request $request, PermissionService $permissions, SystemSettingService $settings): RedirectResponse
    {
        $this->boss($request, $permissions);
        $data = $request->validate([
            'monitoring_enabled' => ['required', 'boolean'],
            'screenshot_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'idle_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'work_start' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i'],
            'rustdesk_host' => ['nullable', 'string', 'max:190'],
        ]);
        $settings->set('office.monitoring_enabled', $data['monitoring_enabled'], 'office', 'boolean', $request->user()->id);
        $settings->set('office.screenshot_minutes', $data['screenshot_minutes'], 'office', 'integer', $request->user()->id);
        $settings->set('office.idle_minutes', $data['idle_minutes'], 'office', 'integer', $request->user()->id);
        $settings->set('office.work_start', $data['work_start'], 'office', 'string', $request->user()->id);
        $settings->set('office.work_end', $data['work_end'], 'office', 'string', $request->user()->id);
        $settings->set('office.rustdesk_host', $data['rustdesk_host'] ?? '', 'office', 'string', $request->user()->id);
        FinanceAudit::log('office.settings_saved', 'Office monitoring settings saved (monitoring '.($data['monitoring_enabled'] ? 'ON' : 'OFF').', screenshot every '.$data['screenshot_minutes'].' min, idle '.$data['idle_minutes'].' min).', []);

        return back()->with('success', 'Settings saved.');
    }

    /* ---- W128 monitoring: live & activity ---- */

    public function activity(Request $request, PermissionService $permissions): Response
    {
        $this->boss($request, $permissions);
        $date = Carbon::now(OfficeService::TZ)->toDateString();
        $staff = User::query()->where('role', 'staff')->orderBy('name')->get(['id', 'name']);
        $att = \App\Models\Office\Attendance::query()->whereDate('work_date', $date)->get()->keyBy('user_id');

        $rows = $staff->map(function ($u) use ($att) {
            $d = OfficeDevice::query()->where('user_id', $u->id)->where('approved', true)->orderByDesc('last_seen_at')->first();
            $a = $att->get($u->id);
            $online = $d && $d->last_seen_at && $d->last_seen_at->gt(now()->subMinutes(3));
            $active = $online && $d->last_active_at && $d->last_active_at->gt(now()->subMinutes(2));

            return [
                'id' => $u->id, 'name' => $u->name,
                'has_agent' => (bool) $d,
                'platform' => $d?->platform,
                'online' => $online,
                'state' => !$d ? 'no_agent' : (!$online ? 'offline' : ($active ? 'active' : 'idle')),
                'foreground_app' => $d?->foreground_app,
                'last_active' => $d?->last_active_at?->timezone(OfficeService::TZ)->format('h:i A'),
                'rustdesk_id' => $d?->rustdesk_id,
                'device_id' => $d?->id,
                'comp_active' => $this->hm((int) ($a->comp_active_seconds ?? 0)),
                'comp_idle' => $this->hm((int) ($a->comp_idle_seconds ?? 0)),
                'shots' => Screenshot::query()->where('user_id', $u->id)->where('work_date', Carbon::now(OfficeService::TZ)->toDateString())
                    ->orderByDesc('id')->limit(8)->get()
                    ->map(fn (Screenshot $s) => ['url' => '/office/admin/screenshot/'.$s->id, 'at' => $s->taken_at?->timezone(OfficeService::TZ)->format('h:i A'), 'app' => $s->foreground_app])->values(),
            ];
        });

        return Inertia::render('V2/Office/Activity', [
            'role' => 'super_admin',
            'rows' => $rows,
            'settings' => OfficeSettings::all(),
        ]);
    }

    public function setRustdesk(Request $request, int $device, PermissionService $permissions): RedirectResponse
    {
        $this->boss($request, $permissions);
        $data = $request->validate(['rustdesk_id' => ['nullable', 'string', 'max:40']]);
        OfficeDevice::query()->findOrFail($device)->forceFill(['rustdesk_id' => $data['rustdesk_id'] ?? null])->save();

        return back()->with('success', 'RustDesk ID saved.');
    }

    public function screenshot(Request $request, int $shot, PermissionService $permissions)
    {
        $this->boss($request, $permissions);
        $s = Screenshot::query()->findOrFail($shot);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($s->path), 404);

        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($s->path));
    }
}
