<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Services\V2\FinanceAudit;
use App\Services\V2\StaffAlerts;

/**
 * W210 - Takedown workflow.
 *  - Super Admin takes a release down directly (OTP in the controller).
 *  - A Manager asks for a takedown of a release in his assigned scope; the request lands in
 *    Administration -> Approvals (pending_approvals, scope release.takedown:<id>) and is carried
 *    out the moment a Super Admin approves it. Labels / artists never get a button.
 *  Nothing is deleted: the release keeps every detail, only its status becomes taken_down and
 *  every store row is marked takedown. Every step is written to release_takedowns + the audit log.
 */
class ReleaseTakedown
{
    /** statuses from which a release can be taken down (it reached, or is reaching, the stores) */
    public const CAN_TAKE_DOWN = ['approved', 'processing', 'delivered', 'live', 'failed', 'takedown_requested'];

    public static function scope(int $releaseId): string
    {
        return 'release.takedown:'.$releaseId;
    }

    public function refuseUnless(Release $release): void
    {
        if (!in_array((string) $release->status, self::CAN_TAKE_DOWN, true)) {
            throw ValidationException::withMessages(['takedown' => match ((string) $release->status) {
                'taken_down', 'takedown' => 'This release is already taken down.',
                'draft', 'submitted', 'changes_requested', 'rejected' => 'This release never went to the stores - delete it (Trash) instead of a takedown.',
                default => 'A takedown is only possible for a release that was sent to the stores (status: '.$release->status.').',
            }]);
        }
    }

    /** Super Admin direct takedown, or the approved Manager request */
    public function takeDown(Release $release, User $by, string $reason, ?int $requestRowId = null): void
    {
        $this->refuseUnless($release);
        $reason = mb_substr(trim($reason), 0, 1000);

        DB::transaction(function () use ($release, $by, $reason, $requestRowId) {
            $release = Release::query()->whereKey($release->id)->lockForUpdate()->firstOrFail();
            $before = (string) $release->status;
            $now = now();

            $release->forceFill(['status' => 'taken_down', 'updated_by' => $by->id])->saveQuietly();

            if (Schema::hasTable('release_store_deliveries')) {
                $set = ['status' => 'takedown', 'updated_at' => $now];
                if (Schema::hasColumn('release_store_deliveries', 'takedown_at')) {
                    $set['takedown_at'] = $now;
                }
                if (Schema::hasColumn('release_store_deliveries', 'taken_down_at')) {
                    $set['taken_down_at'] = $now;
                }
                DB::table('release_store_deliveries')->where('release_id', $release->id)->where('status', '!=', 'takedown')->update($set);
            }

            if ($requestRowId) {
                DB::table('release_takedowns')->where('id', $requestRowId)->update([
                    'status' => 'done', 'decided_by' => $by->id, 'decided_at' => $now, 'done_at' => $now, 'updated_at' => $now,
                ]);
            } else {
                DB::table('release_takedowns')->insert([
                    'release_id' => $release->id, 'status' => 'done', 'reason' => $reason, 'status_before' => $before,
                    'requested_by' => $by->id, 'decided_by' => $by->id,
                    'requested_at' => $now, 'decided_at' => $now, 'done_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            FinanceAudit::log('release.taken_down', 'Release "'.$release->title.'" ('.($release->upc ?: $release->catalog_number).') taken down from all stores by '.$by->name.'. Reason: '.$reason.'. Details and history stay on the release.', [
                'release_id' => $release->id, 'status_before' => $before, 'request_row' => $requestRowId,
            ]);
        });
    }

    /** a Manager's request: release is flagged, Super Admins get it in Approvals + bell */
    public function request(Release $release, User $manager, string $reason): void
    {
        $this->refuseUnless($release);
        if ((string) $release->status === 'takedown_requested') {
            throw ValidationException::withMessages(['takedown' => 'A takedown request for this release is already waiting for a Super Admin.']);
        }
        $reason = mb_substr(trim($reason), 0, 1000);

        DB::transaction(function () use ($release, $manager, $reason) {
            $now = now();
            $before = (string) $release->status;
            $rowId = DB::table('release_takedowns')->insertGetId([
                'release_id' => $release->id, 'status' => 'requested', 'reason' => $reason, 'status_before' => $before,
                'requested_by' => $manager->id, 'requested_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $what = 'take down the release "'.$release->title.'" ('.($release->upc ?: $release->catalog_number).') from all stores - asked by Manager '.$manager->name.': '.$reason;
            $approvalId = DB::table('pending_approvals')->insertGetId([
                'scope' => self::scope((int) $release->id), 'what' => mb_substr($what, 0, 2000), 'url' => '/releases/'.$release->id,
                'requested_by' => $manager->id, 'status' => 'open', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('release_takedowns')->where('id', $rowId)->update(['approval_id' => $approvalId]);
            $release->forceFill(['status' => 'takedown_requested', 'updated_by' => $manager->id])->saveQuietly();

            FinanceAudit::log('release.takedown_requested', 'Manager '.$manager->name.' asked to take down "'.$release->title.'": '.$reason, [
                'release_id' => $release->id, 'approval_id' => $approvalId, 'status_before' => $before,
            ]);
            try {
                $alerts = app(StaffAlerts::class);
                $alerts->bell($alerts->superAdmins(), 'takedown.requested', 'Takedown request', $manager->name.' asks to take down "'.$release->title.'": '.$reason, '/admin/approvals', 'warning');
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /** called from Approvals when a Super Admin decides a release.takedown:* request; returns the flash text */
    public function decideRequest(object $approvalRow, User $superAdmin, bool $approve, ?string $note): string
    {
        $releaseId = (int) substr((string) $approvalRow->scope, strlen('release.takedown:'));
        $release = Release::query()->find($releaseId);
        $row = DB::table('release_takedowns')->where('approval_id', $approvalRow->id)->orderByDesc('id')->first()
            ?: DB::table('release_takedowns')->where('release_id', $releaseId)->where('status', 'requested')->orderByDesc('id')->first();

        if (!$release) {
            if ($row) {
                DB::table('release_takedowns')->where('id', $row->id)->update(['status' => 'rejected', 'decided_by' => $superAdmin->id, 'decided_at' => now(), 'note' => 'Release no longer exists', 'updated_at' => now()]);
            }

            return 'The release is not in the catalogue any more - nothing to take down.';
        }

        if ($approve) {
            if ((string) $release->status === 'takedown_requested') {
                /* allowed from takedown_requested; takeDown() records status_before from the request row */
                $this->takeDown($release, $superAdmin, 'Approved Manager request'.($note ? ' - '.$note : '').($row ? ': '.$row->reason : ''), $row->id ?? null);
            } elseif (in_array((string) $release->status, ['taken_down', 'takedown'], true)) {
                return 'This release was already taken down.';
            } else {
                $this->takeDown($release, $superAdmin, 'Approved Manager request'.($note ? ' - '.$note : ''), $row->id ?? null);
            }
            DB::table('pending_approvals')->where('id', $approvalRow->id)->update(['used_at' => now(), 'used_by' => $superAdmin->id, 'updated_at' => now()]);

            return 'Approved - "'.$release->title.'" is taken down from all stores. Its details stay on the release.';
        }

        /* rejected: the release goes back to what it was */
        $back = $row && $row->status_before ? (string) $row->status_before : 'live';
        if ((string) $release->status === 'takedown_requested') {
            $release->forceFill(['status' => $back, 'updated_by' => $superAdmin->id])->saveQuietly();
        }
        if ($row) {
            DB::table('release_takedowns')->where('id', $row->id)->update(['status' => 'rejected', 'decided_by' => $superAdmin->id, 'decided_at' => now(), 'note' => $note, 'updated_at' => now()]);
        }
        FinanceAudit::log('release.takedown_rejected', 'Takedown request for "'.$release->title.'" rejected by '.$superAdmin->name.($note ? ': '.$note : '').'. Status back to '.$back.'.', ['release_id' => $release->id]);

        return 'Not approved - "'.$release->title.'" stays '.$back.'. The Manager has been told.';
    }

    /** the open request of a release, for the page props */
    public function openRequest(Release $release): ?array
    {
        if (!Schema::hasTable('release_takedowns')) {
            return null;
        }
        $r = DB::table('release_takedowns as t')->leftJoin('users as u', 'u.id', '=', 't.requested_by')
            ->where('t.release_id', $release->id)->where('t.status', 'requested')->orderByDesc('t.id')
            ->first(['t.id', 't.reason', 't.requested_at', 't.approval_id', 'u.name as by']);

        return $r ? ['id' => (int) $r->id, 'reason' => $r->reason, 'by' => $r->by, 'at' => (string) $r->requested_at, 'approval_id' => $r->approval_id] : null;
    }
}
