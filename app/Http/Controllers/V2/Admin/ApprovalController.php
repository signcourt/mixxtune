<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\FinanceAudit;
use App\Services\V2\MakerChecker;
use App\Services\V2\PermissionService;
use App\Services\V2\StaffAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/* W118 MIXXTUNE_MAKER_CHECKER: Administration -> Second approval (Super Admin) */
class ApprovalController extends Controller
{
    private function superAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can do this.');
    }

    public function index(Request $request, PermissionService $permissions): Response
    {
        $this->superAdmin($request, $permissions);
        $rows = DB::table('pending_approvals as a')
            ->leftJoin('users as r', 'r.id', '=', 'a.requested_by')
            ->leftJoin('users as d', 'd.id', '=', 'a.decided_by')
            ->leftJoin('users as u', 'u.id', '=', 'a.used_by')
            ->orderByRaw("a.status = 'open' desc")->orderByDesc('a.id')->limit(100)
            ->get(['a.*', 'r.name as requested_name', 'd.name as decided_name', 'u.name as used_name'])
            ->map(fn ($a) => [
                'id' => $a->id, 'what' => $a->what, 'status' => $a->status, 'note' => $a->note, 'url' => $a->url,
                'mine' => (int) $a->requested_by === (int) $request->user()->id,
                'requested' => $a->requested_name, 'decided' => $a->decided_name, 'used' => $a->used_name,
                'at' => \Illuminate\Support\Carbon::parse($a->created_at)->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'decided_at' => $a->decided_at ? \Illuminate\Support\Carbon::parse($a->decided_at)->timezone('Asia/Kolkata')->format('d M, h:i A') : null,
                'expired' => $a->status === 'approved' && $a->decided_at && \Illuminate\Support\Carbon::parse($a->decided_at)->lt(now()->subHours(MakerChecker::VALID_HOURS)),
            ]);

        return Inertia::render('V2/Admin/Approvals/Index', [
            'role' => 'super_admin',
            'on' => MakerChecker::on(),
            'superAdmins' => MakerChecker::activeSuperAdmins(),
            'validHours' => MakerChecker::VALID_HOURS,
            'rows' => $rows,
        ]);
    }

    public function decide(Request $request, int $approval, PermissionService $permissions): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:500']]);
        $row = DB::table('pending_approvals')->where('id', $approval)->first();
        abort_unless($row, 404);
        if ($row->status !== 'open') {
            return back()->withErrors(['approval' => 'This request was already decided.']);
        }
        if ((int) $row->requested_by === (int) $request->user()->id) {
            return back()->withErrors(['approval' => 'You asked for this yourself - another Super Admin has to decide it.']);
        }
        app(ActionOtpGuard::class)->require($request->user(), 'approval.decide:'.$row->id, ($data['decision'] === 'approve' ? 'approve' : 'reject').' "'.$row->what.'"');
        $n = DB::table('pending_approvals')->where('id', $row->id)->where('status', 'open')
            ->update(['status' => $data['decision'] === 'approve' ? 'approved' : 'rejected', 'decided_by' => $request->user()->id, 'decided_at' => now(), 'note' => $data['note'] ?? null, 'updated_at' => now()]);
        if ($n !== 1) {
            return back()->withErrors(['approval' => 'This request was already decided.']);
        }
        FinanceAudit::log('approval.'.($data['decision'] === 'approve' ? 'approved' : 'rejected'), 'Second approval '.$data['decision'].'d: '.$row->what.'.', ['approval_id' => $row->id]);
        try {
            if ($req = \App\Models\User::query()->find($row->requested_by)) {
                app(StaffAlerts::class)->bell([$req], 'approval.decided', $data['decision'] === 'approve' ? 'Approved - you can go ahead' : 'Not approved',
                    ($request->user()->name ?: 'A Super Admin').' '.($data['decision'] === 'approve' ? 'approved' : 'rejected').': '.$row->what.(($data['note'] ?? '') !== '' ? ' - '.$data['note'] : '').($data['decision'] === 'approve' ? ' Press the same button again within '.MakerChecker::VALID_HOURS.' hours.' : ''),
                    $row->url ?: '/admin/approvals', $data['decision'] === 'approve' ? 'success' : 'warning');
            }
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with('success', $data['decision'] === 'approve' ? 'Approved - the other Super Admin can now do it (once, within '.MakerChecker::VALID_HOURS.' hours).' : 'Rejected.');
    }

    public function toggle(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $on = $request->boolean('on');
        if ($on && MakerChecker::activeSuperAdmins() < 2) {
            return back()->withErrors(['approval' => 'Second approval needs at least 2 active Super Admins - add the second one first, otherwise every payout would wait for ever.']);
        }
        /* switching OFF is itself a money safety change: it needs the other Super Admin's yes too */
        app(ActionOtpGuard::class)->require($request->user(), $on ? 'makerchecker.on' : 'makerchecker.off', $on ? 'switch Second approval ON' : 'switch Second approval OFF');
        DB::table('system_settings')->updateOrInsert(['key' => MakerChecker::KEY], ['group' => 'finance', 'value' => $on ? '1' : '0', 'type' => 'boolean', 'is_public' => false, 'description' => 'W118 second approval for money actions', 'updated_at' => now(), 'created_at' => now()]);
        try {
            \Illuminate\Support\Facades\Cache::forget('system_setting:'.MakerChecker::KEY);
        } catch (Throwable) {
        }
        FinanceAudit::log('approval.switched', 'Second approval switched '.($on ? 'ON' : 'OFF').' by '.$request->user()->name.'.', ['on' => $on]);

        return back()->with('success', 'Second approval is now '.($on ? 'ON' : 'OFF').'.');
    }
}
