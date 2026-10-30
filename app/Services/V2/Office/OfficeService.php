<?php

namespace App\Services\V2\Office;

use App\Models\Office\Attendance;
use App\Models\Office\BreakSession;
use App\Models\Office\OfficeTask;
use App\Models\Office\TaskChecklistItem;
use App\Models\Office\TaskProof;
use App\Models\Office\TaskTimer;
use App\Models\User;
use App\Services\V2\FinanceAudit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * W127 MIXXTUNE_OFFICE
 * The office day and the tasks: Clock In/Out, break, the Mixx Tune-tab active time, the task timer,
 * the checklist, and the status flow (assigned -> ... -> submitted -> verified). Kept here so the
 * controllers stay thin and the maths is tested in one place.
 */
class OfficeService
{
    /* a ping may add at most this many seconds, so a closed laptop does not pile up time */
    public const PING_CAP = 90;

    public const TZ = 'Asia/Kolkata';

    public function status(string $role): string
    {
        return $role;
    }

    /** seconds between two moments, always a non-negative whole number (Carbon 3 diffs are signed) */
    private function secs(?Carbon $a, ?Carbon $b): int
    {
        if (!$a || !$b) {
            return 0;
        }

        return (int) abs($a->diffInSeconds($b));
    }

    public function today(User $user): Attendance
    {
        $date = Carbon::now(self::TZ)->startOfDay();
        $a = Attendance::query()->where('user_id', $user->id)->whereDate('work_date', $date->toDateString())->first();
        if (!$a) {
            $a = Attendance::query()->create(['user_id' => $user->id, 'work_date' => $date, 'active_seconds' => 0, 'break_seconds' => 0]);
        }

        return $a;
    }

    public function clockIn(User $user): Attendance
    {
        $a = $this->today($user);
        if (!$a->clock_in_at) {
            $a->forceFill(['clock_in_at' => now(), 'last_ping_at' => now(), 'clock_out_at' => null, 'confirmed_at' => null])->save();
        } elseif ($a->clock_out_at) {
            /* came back after clocking out: reopen the same day */
            $a->forceFill(['clock_out_at' => null, 'confirmed_at' => null, 'last_ping_at' => now()])->save();
        }

        return $a->fresh();
    }

    /** heartbeat from the open Mixx Tune tab; adds the active time (capped) when clocked in and not on break */
    public function ping(User $user): Attendance
    {
        $a = $this->today($user);
        if (!$a->clock_in_at || $a->clock_out_at) {
            return $a;
        }
        if (!$a->on_break && $a->last_ping_at) {
            $add = min(self::PING_CAP, $this->secs(now(), $a->last_ping_at));
            $a->active_seconds += $add;
        }
        $a->last_ping_at = now();
        $a->save();

        return $a;
    }

    public function startBreak(User $user): Attendance
    {
        $a = $this->today($user);
        abort_unless($a->clock_in_at && !$a->clock_out_at, 422, 'Clock in first.');
        if ($a->on_break) {
            return $a;
        }
        $this->ping($user);
        $a->refresh();
        BreakSession::query()->create(['attendance_id' => $a->id, 'started_at' => now()]);
        $a->forceFill(['on_break' => true])->save();

        return $a->fresh();
    }

    public function endBreak(User $user): Attendance
    {
        $a = $this->today($user);
        if (!$a->on_break) {
            return $a;
        }
        $open = BreakSession::query()->where('attendance_id', $a->id)->whereNull('ended_at')->orderByDesc('id')->first();
        if ($open) {
            $secs = $this->secs(now(), $open->started_at);
            $open->forceFill(['ended_at' => now(), 'seconds' => $secs])->save();
            $a->break_seconds += $secs;
        }
        $a->forceFill(['on_break' => false, 'last_ping_at' => now()])->save();

        return $a->fresh();
    }

    /** numbers for the logout popup */
    public function daySummary(User $user): array
    {
        $a = $this->today($user);
        $this->pauseAllTimers($user); /* so the running task time is counted */
        $a->refresh();
        $in = $a->clock_in_at;
        $shift = $this->secs(now(), $in);
        $break = $a->break_seconds + ($a->on_break ? $this->openBreakSeconds($a) : 0);
        $work = max(0, $shift - $break);
        $taskSecs = (int) TaskTimer::query()->whereHas('task', fn ($q) => $q->where('assigned_to', $user->id))
            ->where('user_id', $user->id)->whereDate('started_at', Carbon::now(self::TZ)->toDateString())->sum('seconds');
        $done = OfficeTask::query()->where('assigned_to', $user->id)->where('status', 'verified')->whereDate('verified_at', Carbon::now(self::TZ)->toDateString())->count();
        $submitted = OfficeTask::query()->where('assigned_to', $user->id)->whereDate('submitted_at', Carbon::now(self::TZ)->toDateString())->count();

        return [
            'clock_in' => $in?->timezone(self::TZ)->format('h:i A'),
            'shift_seconds' => $shift,
            'work_seconds' => $work,
            'active_seconds' => $a->active_seconds,
            'break_seconds' => $break,
            'task_seconds' => $taskSecs,
            'tasks_done' => $done,
            'tasks_submitted' => $submitted,
            'on_break' => $a->on_break,
        ];
    }

    private function openBreakSeconds(Attendance $a): int
    {
        $open = BreakSession::query()->where('attendance_id', $a->id)->whereNull('ended_at')->orderByDesc('id')->first();

        return $open ? $this->secs(now(), $open->started_at) : 0;
    }

    public function clockOut(User $user, ?string $note = null): Attendance
    {
        $a = $this->today($user);
        abort_unless($a->clock_in_at, 422, 'You have not clocked in today.');
        if ($a->on_break) {
            $this->endBreak($user);
            $a->refresh();
        }
        $this->ping($user);
        $this->pauseAllTimers($user);
        $a->refresh();
        $s = $this->daySummary($user);
        $a->forceFill([
            'clock_out_at' => now(),
            'confirmed_at' => now(),
            'task_seconds' => $s['task_seconds'],
            'tasks_done' => $s['tasks_done'],
            'tasks_submitted' => $s['tasks_submitted'],
        ])->save();
        FinanceAudit::log('office.clock_out', $user->name.' clocked out. Work '.$this->hm($s['work_seconds']).', active '.$this->hm($s['active_seconds']).', break '.$this->hm($s['break_seconds']).'.', ['user_id' => $user->id], null, []);

        return $a->fresh();
    }

    private function hm(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h.'h '.$m.'m';
    }

    /* ---- tasks ---- */

    public function startTimer(User $user, OfficeTask $task): OfficeTask
    {
        $this->ownTask($user, $task);
        abort_if(in_array($task->status, ['verified', 'cancelled', 'submitted'], true), 422, 'This task cannot be worked on now.');
        $this->pauseAllTimers($user);
        TaskTimer::query()->create(['task_id' => $task->id, 'user_id' => $user->id, 'started_at' => now()]);
        if (in_array($task->status, ['assigned', 'accepted'], true)) {
            $this->move($task, 'in_progress', $user);
        }
        if (!$task->started_at) {
            $task->forceFill(['started_at' => now()])->save();
        }

        return $task->fresh();
    }

    public function pauseTimer(User $user, ?OfficeTask $task = null): void
    {
        $this->pauseAllTimers($user, $task?->id);
        if ($task) {
            $this->syncWorked($task);
        }
    }

    private function pauseAllTimers(User $user, ?int $onlyTaskId = null): void
    {
        $q = TaskTimer::query()->where('user_id', $user->id)->whereNull('ended_at');
        if ($onlyTaskId) {
            $q->where('task_id', $onlyTaskId);
        }
        foreach ($q->get() as $timer) {
            $secs = $this->secs(now(), $timer->started_at);
            $timer->forceFill(['ended_at' => now(), 'seconds' => $secs])->save();
            $this->syncWorked(OfficeTask::query()->find($timer->task_id));
        }
    }

    private function syncWorked(?OfficeTask $task): void
    {
        if (!$task) {
            return;
        }
        $total = (int) TaskTimer::query()->where('task_id', $task->id)->sum('seconds');
        $task->forceFill(['worked_seconds' => $total])->save();
    }

    public function runningTimerTaskId(User $user): ?int
    {
        return TaskTimer::query()->where('user_id', $user->id)->whereNull('ended_at')->orderByDesc('id')->value('task_id');
    }

    public function toggleItem(User $user, TaskChecklistItem $item, bool $done): OfficeTask
    {
        $task = OfficeTask::query()->findOrFail($item->task_id);
        $this->ownTask($user, $task);
        $item->forceFill(['is_done' => $done, 'done_at' => $done ? now() : null])->save();
        $this->recomputeProgress($task);

        return $task->fresh();
    }

    public function recomputeProgress(OfficeTask $task): void
    {
        $items = TaskChecklistItem::query()->where('task_id', $task->id)->get();
        if ($items->isEmpty()) {
            return; /* no checklist: progress is set by hand/by submit */
        }
        $total = max(1, (int) $items->sum('weight'));
        $done = (int) $items->where('is_done', true)->sum('weight');
        $task->forceFill(['progress' => (int) round(100 * $done / $total)])->save();
    }

    public function accept(User $user, OfficeTask $task): OfficeTask
    {
        $this->ownTask($user, $task);
        if ($task->status === 'assigned') {
            $task->forceFill(['accepted_at' => now()])->save();
            $this->move($task, 'accepted', $user);
        }

        return $task->fresh();
    }

    public function block(User $user, OfficeTask $task, string $why): OfficeTask
    {
        $this->ownTask($user, $task);
        $task->forceFill(['blocker_note' => mb_substr($why, 0, 1000)])->save();
        $this->pauseAllTimers($user, $task->id);
        $this->move($task, 'blocked', $user, $why);

        return $task->fresh();
    }

    public function submit(User $user, OfficeTask $task, array $proof): OfficeTask
    {
        $this->ownTask($user, $task);
        abort_if(in_array($task->status, ['verified', 'cancelled'], true), 422, 'This task is closed.');
        $this->pauseAllTimers($user, $task->id);
        if (($proof['kind'] ?? 'note') !== 'none') {
            TaskProof::query()->create([
                'task_id' => $task->id,
                'kind' => $proof['kind'] ?? 'note',
                'link' => $proof['link'] ?? null,
                'path' => $proof['path'] ?? null,
                'note' => $proof['note'] ?? null,
                'submitted_by' => $user->id,
                'created_at' => now(),
            ]);
        }
        $task->forceFill(['submitted_at' => now(), 'progress' => max($task->progress, 100)])->save();
        $this->move($task, 'submitted', $user);

        return $task->fresh();
    }

    public function verify(User $reviewer, OfficeTask $task, ?string $note = null): OfficeTask
    {
        $task->forceFill(['verified_at' => now(), 'reviewed_by' => $reviewer->id, 'review_note' => $note, 'progress' => 100])->save();
        $this->move($task, 'verified', $reviewer, $note);

        return $task->fresh();
    }

    public function requestChanges(User $reviewer, OfficeTask $task, string $note): OfficeTask
    {
        $task->forceFill(['reviewed_by' => $reviewer->id, 'review_note' => mb_substr($note, 0, 1000)])->save();
        $this->move($task, 'changes_requested', $reviewer, $note);

        return $task->fresh();
    }

    private function move(OfficeTask $task, string $to, User $by, ?string $note = null): void
    {
        $from = $task->status;
        $task->forceFill(['status' => $to])->save();
        DB::table('office_task_status_logs')->insert([
            'task_id' => $task->id, 'from_status' => $from, 'to_status' => $to,
            'note' => $note ? mb_substr($note, 0, 1000) : null, 'acted_by' => $by->id, 'created_at' => now(),
        ]);
    }

    private function ownTask(User $user, OfficeTask $task): void
    {
        if ((int) $task->assigned_to !== (int) $user->id) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('This task is not yours.');
        }
    }

    /** make an actual task from a template (or a custom one) for a staff member */
    public function createTask(User $creator, array $data): OfficeTask
    {
        $task = OfficeTask::query()->create([
            'public_id' => (string) Str::ulid(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'functional_role_id' => $data['functional_role_id'] ?? null,
            'assigned_to' => $data['assigned_to'],
            'created_by' => $creator->id,
            'release_id' => $data['release_id'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'assigned',
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'due_at' => !empty($data['due_at']) ? Carbon::parse($data['due_at'])->utc() : null,
        ]);
        $sort = 0;
        foreach ($data['checklist'] ?? [] as $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            TaskChecklistItem::query()->create(['task_id' => $task->id, 'text' => mb_substr($text, 0, 300), 'weight' => 1, 'sort' => $sort++]);
        }
        DB::table('office_task_status_logs')->insert(['task_id' => $task->id, 'from_status' => null, 'to_status' => 'assigned', 'acted_by' => $creator->id, 'created_at' => now()]);

        return $task->fresh();
    }
}
