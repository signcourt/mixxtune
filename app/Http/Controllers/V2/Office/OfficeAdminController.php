<?php

namespace App\Http\Controllers\V2\Office;

use App\Http\Controllers\Controller;
use App\Models\Office\Attendance;
use App\Models\Office\FunctionalRole;
use App\Models\Office\OfficeTask;
use App\Models\Office\TaskTemplate;
use App\Models\User;
use App\Services\V2\FinanceAudit;
use App\Services\V2\Office\OfficeService;
use App\Services\V2\PermissionService;
use App\Services\V2\StaffAlerts;
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
        $att = Attendance::query()->where('work_date', $date)->get()->keyBy('user_id');

        $board = $staff->map(function ($u) use ($att) {
            $a = $att->get($u->id);
            $state = 'off'; /* off | working | idle | break */
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
}
