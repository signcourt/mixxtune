<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\AuditLogService;
use App\Services\V2\DistributorReleaseGate;
use App\Services\V2\DistributorService;
use App\Services\V2\NotificationService;
use App\Services\V2\ReleaseWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** W224 - the distributor's review page: approve & forward, ask for changes, reject. */
class DistributorReviewController extends Controller
{
    private function owner(Request $request, DistributorService $service)
    {
        $d = $service->forUser($request->user());
        abort_unless($d && $service->isOwner($request->user(), $d), 403, 'This page is for the distributor account owner.');
        abort_if((string) $d->status !== 'active', 403, 'Your distributor account is switched off. Write to support.');
        abort_unless(DistributorReleaseGate::ready(), 503, 'Review is not set up yet.');

        return $d;
    }

    public function index(Request $request, DistributorService $service, DistributorReleaseGate $gate): Response
    {
        $d = $this->owner($request, $service);
        $gate->closeStale((int) $d->id);

        $rows = DB::table('distributor_release_reviews as r')
            ->join('releases', 'releases.id', '=', 'r.release_id')
            ->leftJoin('labels', 'labels.id', '=', 'releases.label_id')
            ->leftJoin('users', 'users.id', '=', 'r.submitted_by')
            ->where('r.distributor_id', $d->id)
            ->whereNull('releases.deleted_at')
            ->orderByRaw("r.status = 'pending' DESC")->orderByDesc('r.id')->limit(200)
            ->get(['r.id', 'r.status', 'r.note', 'r.created_at', 'r.decided_at', 'releases.id as release_id', 'releases.title', 'releases.primary_artist_name', 'releases.release_type', 'releases.digital_release_date as release_date', 'releases.upc', 'releases.artwork_path', 'releases.status as release_status', 'labels.name as label_name', 'users.name as sent_by']);

        $tracks = DB::table('tracks')->whereIn('release_id', $rows->pluck('release_id')->all() ?: [0])->whereNull('deleted_at')->selectRaw('release_id, COUNT(*) c')->groupBy('release_id')->pluck('c', 'release_id');

        return Inertia::render('V2/Distributor/Review', [
            'role' => 'label',
            'rows' => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'status' => $r->status,
                'note' => $r->note,
                'sent_at' => $r->created_at,
                'decided_at' => $r->decided_at,
                'release_id' => (int) $r->release_id,
                'title' => $r->title,
                'artist' => $r->primary_artist_name,
                'type' => $r->release_type,
                'release_date' => $r->release_date,
                'upc' => $r->upc,
                'artwork' => $r->artwork_path ? '/storage/'.ltrim((string) $r->artwork_path, '/') : null,
                'release_status' => $r->release_status,
                'label' => $r->label_name,
                'sent_by' => $r->sent_by,
                'tracks' => (int) ($tracks[$r->release_id] ?? 0),
            ])->values(),
            'pending' => (int) $rows->where('status', 'pending')->count(),
        ]);
    }

    public function decide(Request $request, int $review, DistributorService $service, ReleaseWorkflowService $workflow): RedirectResponse
    {
        $d = $this->owner($request, $service);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'changes', 'reject'])],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:action,changes', 'required_if:action,reject'],
        ], ['note.required_if' => 'Write what must be changed / why it is rejected.']);

        $row = DB::table('distributor_release_reviews')->where('id', $review)->where('distributor_id', $d->id)->first();
        abort_unless($row, 404);
        if ((string) $row->status !== 'pending') {
            return back()->with('error', 'This release was already decided.');
        }

        $release = Release::query()->whereKey($row->release_id)->whereNull('deleted_at')->first();
        /* only releases of his own sub-labels, and only while they wait */
        $inTree = $release && DB::table('labels')->where('id', $release->label_id)->where('parent_label_id', $d->label_id)->exists();
        if (!$release || !$inTree || (string) $release->status !== 'submitted') {
            DB::table('distributor_release_reviews')->where('id', $row->id)->update(['status' => 'closed', 'updated_at' => now()]);

            return back()->with('error', 'This release is no longer waiting for approval.');
        }

        $user = $request->user();
        $note = trim((string) ($data['note'] ?? '')) ?: null;
        $close = fn (string $status) => DB::table('distributor_release_reviews')->where('id', $row->id)->update(['status' => $status, 'decided_by' => $user->id, 'decided_at' => now(), 'note' => $note, 'updated_at' => now()]);

        if ($data['action'] === 'approve') {
            $close('approved');
            try {
                app(NotificationService::class)->releaseSubmitted($release); /* now Mixx Tune staff hear about it */
            } catch (\Throwable $e) {
                report($e);
            }
            $msg = '"'.$release->title.'" approved and forwarded to Mixx Tune for review.';
        } elseif ($data['action'] === 'changes') {
            DB::transaction(function () use ($workflow, $release, $user, $note, $close) {
                $close('changes');
                $workflow->transition($release, 'changes_requested', $user, ['action' => 'distributor_changes_requested', 'remarks' => $note, 'notes' => $note, 'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent()]);
            });
            $msg = '"'.$release->title.'" sent back to the sub-label for changes.';
        } else {
            DB::transaction(function () use ($workflow, $release, $user, $note, $close) {
                $close('rejected');
                $workflow->transition($release, 'rejected', $user, ['action' => 'distributor_rejected', 'remarks' => $note, 'reason' => $note, 'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent()]);
            });
            $msg = '"'.$release->title.'" rejected.';
        }

        try {
            app(AuditLogService::class)->record('distributor.release_'.$data['action'], 'releases', 'Distributor #'.$d->id.' ('.$d->slug.') '.$data['action'].' release "'.$release->title.'" (#'.$release->id.').'.($note ? ' Note: '.$note : ''), [], ['release_id' => $release->id, 'action' => $data['action']], $release, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', $msg);
    }
}
