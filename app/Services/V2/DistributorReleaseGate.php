<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * W224 - distributor review gate.
 *
 * sub-label submits -> the release is "submitted" as always, plus one PENDING row here.
 * While that row is pending the release is hidden from the Mixx Tune review queue and cannot be approved
 * by a Manager. The distributor approves (row -> approved, Mixx Tune staff get the usual "new release" bell),
 * asks for changes or rejects (normal release workflow transitions).
 * A release the distributor submits himself, and every release outside a distributor tree, is not gated.
 */
class DistributorReleaseGate
{
    private static ?bool $ready = null;

    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasTable('distributor_release_reviews') && Schema::hasTable('distributors');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /** ids of releases still waiting for their distributor (sub-query for whereNotIn) */
    public static function pendingIdsQuery(): Builder
    {
        return DB::table('distributor_release_reviews')->where('status', 'pending')->select('release_id');
    }

    /** exclude gated releases from a releases query (no-op before the migration ran) */
    public static function hide($query, string $column = 'id')
    {
        if (self::ready()) {
            $query->whereNotIn($column, self::pendingIdsQuery());
        }

        return $query;
    }

    public function distributorFor(Release $release): ?Distributor
    {
        if (!self::ready() || !$release->label_id) {
            return null;
        }
        $parent = DB::table('labels')->where('id', $release->label_id)->value('parent_label_id');
        if (!$parent) {
            return null; /* the distributor's own master label is not gated */
        }

        return Distributor::query()->where('label_id', (int) $parent)->where('status', 'active')->first();
    }

    public function pendingRow(Release $release): ?object
    {
        if (!self::ready()) {
            return null;
        }

        return DB::table('distributor_release_reviews')->where('release_id', $release->id)->where('status', 'pending')->orderByDesc('id')->first();
    }

    /**
     * Called by the release workflow right after a release became "submitted".
     * Opens the gate when needed and tells the distributor; otherwise runs the normal staff notification.
     */
    public function submitted(Release $release, User $submitter, callable $normalNotification): void
    {
        try {
            $d = $this->distributorFor($release);
            if (!$d || (int) $d->user_id === (int) $submitter->id || in_array((string) $submitter->role, ['admin', 'super_admin'], true)) {
                $normalNotification();

                return;
            }

            DB::table('distributor_release_reviews')->where('release_id', $release->id)->where('status', 'pending')->update(['status' => 'closed', 'updated_at' => now()]);
            DB::table('distributor_release_reviews')->insert([
                'release_id' => $release->id,
                'distributor_id' => $d->id,
                'label_id' => $release->label_id,
                'status' => 'pending',
                'submitted_by' => $submitter->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $owner = User::query()->find($d->user_id);
            if ($owner) {
                app(StaffAlerts::class)->bell([$owner], 'distributor.release_waiting', 'Release waiting for your approval', '"'.$release->title.'" was sent by your sub-label. Approve it to forward it for review.', '/label/distributor/review', 'warning', ['release_id' => $release->id]);
            }
            app(StaffAlerts::class)->bell([$submitter], 'release.submitted', 'Release sent', '"'.$release->title.'" was sent to '.($d->brand_name ?: 'your distributor').' for approval.', '/releases/'.$release->id, 'success', ['release_id' => $release->id]);
        } catch (\Throwable $e) {
            report($e);
            $normalNotification(); /* never lose a submission because of the gate */
        }
    }

    /** a Manager cannot approve past the distributor; a Super Admin can (the row is closed as bypassed) */
    public function staffApproves(Release $release, User $staff): void
    {
        $row = $this->pendingRow($release);
        if (!$row) {
            return;
        }
        if ((string) $staff->role !== 'super_admin') {
            throw ValidationException::withMessages(['release' => 'This release is still waiting for its distributor\'s approval. It comes to the review queue after that.']);
        }
        DB::table('distributor_release_reviews')->where('id', $row->id)->update(['status' => 'approved', 'decided_by' => $staff->id, 'decided_at' => now(), 'note' => 'Approved directly by Super Admin (distributor step skipped).', 'updated_at' => now()]);
    }

    /** rows left pending although the release moved on (changes / rejected / trashed by somebody else) */
    public function closeStale(int $distributorId): void
    {
        DB::table('distributor_release_reviews')
            ->where('distributor_id', $distributorId)->where('status', 'pending')
            ->whereNotIn('release_id', DB::table('releases')->where('status', 'submitted')->whereNull('deleted_at')->select('id'))
            ->update(['status' => 'closed', 'updated_at' => now()]);
    }
}
