<?php

namespace App\Http\Controllers\V2\Office;

use App\Http\Controllers\Controller;
use App\Models\Office\OfficeTask;
use App\Models\Office\TaskChecklistItem;
use App\Models\Office\OfficeDevice;
use App\Services\V2\Office\OfficeMonitor;
use App\Services\V2\Office\OfficeService;
use App\Services\V2\Office\OfficeSettings;
use App\Services\V2\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/*
 * W127 MIXXTUNE_OFFICE: a staff member's own office - today's tasks, Clock In/Out, break,
 * the task timer, the checklist, submitting proof, and the logout day-summary.
 */
class StaffOfficeController extends Controller
{
    public function __construct(private readonly OfficeService $office)
    {
    }

    private function staff(Request $request, PermissionService $permissions): void
    {
        $role = $permissions->role($request->user());
        abort_unless(in_array($role, ['staff', 'super_admin', 'admin'], true), 403, 'Your account is not an office account.');
    }

    public function index(Request $request, PermissionService $permissions): Response
    {
        $this->staff($request, $permissions);
        $user = $request->user();
        $a = $this->office->today($user);

        /* order so the ones needing attention come first; done in PHP to stay DB-portable */
        $rank = ['changes_requested' => 0, 'blocked' => 1, 'in_progress' => 2, 'accepted' => 3, 'assigned' => 4, 'submitted' => 5];
        $tasks = OfficeTask::query()->with(['items'])->where('assigned_to', $user->id)
            ->whereIn('status', array_merge(OfficeTask::OPEN, ['submitted']))->get()
            ->sortBy(fn (OfficeTask $t) => [$rank[$t->status] ?? 9, $t->due_at ? $t->due_at->timestamp : PHP_INT_MAX])
            ->values()
            ->map(fn (OfficeTask $t) => $this->card($t));

        $device = OfficeDevice::query()->where('user_id', $user->id)->where('approved', true)->orderByDesc('last_seen_at')->first();

        return Inertia::render('V2/Office/MyOffice', [
            'role' => $permissions->role($user),
            'attendance' => $this->att($a),
            'runningTaskId' => $this->office->runningTimerTaskId($user),
            'tasks' => $tasks,
            'summary' => $this->office->daySummary($user),
            'monitoring' => OfficeSettings::monitoringEnabled(),
            'device' => $device ? [
                'platform' => $device->platform,
                'name' => $device->device_name,
                'online' => $device->last_seen_at && $device->last_seen_at->gt(now()->subMinutes(3)),
                'screenshot_minutes' => OfficeSettings::screenshotMinutes(),
            ] : null,
        ]);
    }

    /* W128 MIXXTUNE_OFFICE_MONITOR: staff connects this office computer (consent is shown in the UI) */
    public function pairCode(Request $request, PermissionService $permissions, OfficeMonitor $monitor)
    {
        $this->staff($request, $permissions);
        $data = $request->validate(['device_name' => ['nullable', 'string', 'max:190'], 'platform' => ['nullable', 'in:windows,mac']]);

        return response()->json($monitor->makePairCode($request->user(), $data['device_name'] ?? null, $data['platform'] ?? null));
    }

    private function att($a): array
    {
        return [
            'clocked_in' => (bool) $a->clock_in_at && !$a->clock_out_at,
            'on_break' => (bool) $a->on_break,
            'clock_in_at' => $a->clock_in_at?->timezone(OfficeService::TZ)->format('h:i A'),
            'work_date' => Carbon::parse($a->work_date)->timezone(OfficeService::TZ)->format('d M Y'),
        ];
    }

    private function card(OfficeTask $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'description' => $t->description,
            'priority' => $t->priority,
            'status' => $t->status,
            'progress' => (int) $t->progress,
            'due_at' => $t->due_at?->timezone(OfficeService::TZ)->toIso8601String(),
            'due_text' => $t->due_at?->timezone(OfficeService::TZ)->format('d M, h:i A'),
            'overdue' => $t->due_at && $t->due_at->isPast() && in_array($t->status, OfficeTask::OPEN, true),
            'worked_seconds' => (int) $t->worked_seconds,
            'review_note' => $t->status === 'changes_requested' ? $t->review_note : null,
            'items' => $t->items->map(fn (TaskChecklistItem $i) => ['id' => $i->id, 'text' => $i->text, 'is_done' => $i->is_done])->values(),
        ];
    }

    public function clockIn(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->staff($request, $permissions);
        $this->office->clockIn($request->user());

        return back()->with('success', 'Clocked in. Have a good day!');
    }

    public function ping(Request $request, PermissionService $permissions): JsonResponse
    {
        $this->staff($request, $permissions);
        $a = $this->office->ping($request->user());

        return response()->json(['active_seconds' => $a->active_seconds, 'on_break' => $a->on_break]);
    }

    public function startBreak(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->staff($request, $permissions);
        $this->office->startBreak($request->user());

        return back();
    }

    public function endBreak(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->staff($request, $permissions);
        $this->office->endBreak($request->user());

        return back();
    }

    public function summary(Request $request, PermissionService $permissions): JsonResponse
    {
        $this->staff($request, $permissions);

        return response()->json($this->office->daySummary($request->user()));
    }

    public function clockOut(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->staff($request, $permissions);
        $this->office->clockOut($request->user(), $request->input('note'));

        return back()->with('success', 'Clocked out. Your time is saved.');
    }

    /* ---- task actions ---- */

    private function myTask(Request $request, int $id): OfficeTask
    {
        $t = OfficeTask::query()->findOrFail($id);
        abort_unless((int) $t->assigned_to === (int) $request->user()->id, 403);

        return $t;
    }

    /* W129 MIXXTUNE_OFFICE_LOGIN: office staff must be clocked in to work on a task */
    private function requireClockedIn(Request $request, PermissionService $permissions): void
    {
        if ($permissions->role($request->user()) !== 'staff') {
            return; /* Super Admin / Manager may act without clocking in */
        }
        $a = $this->office->today($request->user());
        abort_unless($a->clock_in_at && !$a->clock_out_at, 422, 'Clock in first — you are not clocked in.');
    }

    public function accept(Request $request, int $task, PermissionService $permissions): RedirectResponse
    {
        $this->staff($request, $permissions);
        $t = $this->myTask($request, $task);
        $this->requireClockedIn($request, $permissions);
        $this->office->accept($request->user(), $t);

        return back();
    }

    public function startTimer(Request $request, int $task, PermissionService $permissions): RedirectResponse
    {
        $this->staff($request, $permissions);
        $t = $this->myTask($request, $task);
        $this->requireClockedIn($request, $permissions);
        $this->office->startTimer($request->user(), $t);

        return back();
    }

    public function pauseTimer(Request $request, int $task, PermissionService $permissions): RedirectResponse
    {
        $this->staff($request, $permissions);
        $this->office->pauseTimer($request->user(), $this->myTask($request, $task));

        return back();
    }

    public function toggleItem(Request $request, int $task, int $item, PermissionService $permissions): RedirectResponse
    {
        $this->staff($request, $permissions);
        $t = $this->myTask($request, $task);
        $this->requireClockedIn($request, $permissions);
        $it = TaskChecklistItem::query()->where('task_id', $t->id)->findOrFail($item);
        $this->office->toggleItem($request->user(), $it, $request->boolean('done'));

        return back();
    }

    public function block(Request $request, int $task, PermissionService $permissions): RedirectResponse
    {
        $this->staff($request, $permissions);
        $t = $this->myTask($request, $task);
        $this->requireClockedIn($request, $permissions);
        $data = $request->validate(['why' => ['required', 'string', 'max:1000']]);
        $this->office->block($request->user(), $t, $data['why']);

        return back()->with('success', 'The task is marked blocked and the office is told.');
    }

    public function submit(Request $request, int $task, PermissionService $permissions): RedirectResponse
    {
        $this->staff($request, $permissions);
        $t = $this->myTask($request, $task);
        $this->requireClockedIn($request, $permissions);
        $data = $request->validate([
            'kind' => ['required', 'in:link,file,note,none'],
            'link' => ['nullable', 'url', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:51200'],
        ]);
        $path = null;
        if ($data['kind'] === 'file' && $request->hasFile('file')) {
            $path = $request->file('file')->store('office/proofs/'.$task, 'public');
        }
        $this->office->submit($request->user(), $t, [
            'kind' => $data['kind'], 'link' => $data['link'] ?? null, 'note' => $data['note'] ?? null, 'path' => $path,
        ]);

        return back()->with('success', 'Sent for review.');
    }
}
