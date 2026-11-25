<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\DistributionStore;
use App\Models\ReleaseStoreDelivery;
use App\Models\User;
use App\Support\LegacyRelease;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * W218 - one-click Live.
 *
 * Until Mixx Tune has direct store delivery, the team marks a release live by hand.
 * The old way needed Start delivery + three steps per store. This does it in one go:
 *   - the release must be approved / processing / delivered / failed
 *   - UPC must be there; ISRCs are made for new releases, old (WordPress) ones must have them typed
 *   - every chosen store (or every active store) gets a delivery row marked live
 *   - the release itself goes live (owner gets the usual "live" bell)
 * Nothing is deleted; the store rows stay so the real delivery can take over later.
 */
class ReleaseGoLive
{
    public const CAN = ['approved', 'processing', 'delivered', 'failed'];

    public function __construct(
        private readonly ReleaseWorkflowService $workflow,
        private readonly IsrcService $isrc,
        private readonly AuditLogService $audit
    ) {
    }

    public static function scope(int $releaseId): string
    {
        return 'release.go-live:'.$releaseId;
    }

    /** what still stops the release from going live (empty = ready) */
    public function blockers(Release $release): array
    {
        $release->loadMissing('tracks');
        $out = [];
        $status = (string) $release->status;

        if (!in_array($status, self::CAN, true)) {
            $out[] = match ($status) {
                'live' => 'This release is already live.',
                'draft', 'submitted', 'changes_requested', 'rejected' => 'Approve the release first (it is '.str_replace('_', ' ', $status).').',
                'takedown_requested', 'taken_down' => 'This release is taken down - decide the takedown first.',
                default => 'A '.str_replace('_', ' ', $status).' release cannot be marked live.',
            };
        }

        if ($release->tracks->isEmpty()) {
            $out[] = 'The release has no tracks.';
        }

        if (blank($release->upc)) {
            $out[] = 'No UPC yet - enter the UPC first (ISRC & UPC → Pending UPC → Enter UPC).';
        }

        $noIsrc = $release->tracks->filter(fn ($t) => blank($t->isrc))->count();
        if ($noIsrc > 0 && LegacyRelease::is($release)) {
            $out[] = $noIsrc.' old track(s) without ISRC - enter the existing ISRC first (codes are never made for old releases).';
        }

        return $out;
    }

    public function refuseUnless(Release $release): void
    {
        $blockers = $this->blockers($release);
        if ($blockers !== []) {
            throw ValidationException::withMessages(['go_live' => implode(' ', $blockers)]);
        }
    }

    /** @return array{stores:int, isrc_made:int} */
    public function goLive(Release $release, User $user, ?string $note = null): array
    {
        $this->refuseUnless($release);
        $release->loadMissing('tracks');

        $made = 0;
        if (!LegacyRelease::is($release)) {
            foreach ($release->tracks as $track) {
                if (blank($track->isrc)) {
                    $this->isrc->generate($track, $user);
                    $made++;
                }
            }
            $release->refresh();
            $release->loadMissing('tracks');
        }

        $storeIds = $this->storeIds($release);
        if ($storeIds === []) {
            throw ValidationException::withMessages(['go_live' => 'No active store to mark live. Add / activate a store under Distribution & Stores first.']);
        }

        $from = (string) $release->status;
        $note = trim((string) $note) ?: 'Marked live by '.$user->name.' (one-click live).';

        DB::transaction(function () use ($release, $storeIds, $user, $note, $from) {
            $now = now();
            foreach ($storeIds as $storeId) {
                $row = ReleaseStoreDelivery::query()->firstOrCreate(
                    ['release_id' => $release->id, 'distribution_store_id' => $storeId],
                    ['public_id' => (string) Str::ulid(), 'status' => 'pending', 'created_by' => $user->id, 'updated_by' => $user->id]
                );
                if ((string) $row->status === 'live') {
                    continue;
                }
                $row->update([
                    'status' => 'live',
                    'delivered_at' => $row->delivered_at ?: $now,
                    'live_at' => $now,
                    'failed_at' => null,
                    'error_message' => null,
                    'delivery_note' => $note,
                    'updated_by' => $user->id,
                ]);
            }

            /* walk the release to "delivered" quietly (no bells), then the real "live" transition (log, catalogue, owner bell) */
            $quiet = match ($from) {
                'approved', 'failed' => ['processing', 'delivered'],
                'processing' => ['delivered'],
                default => [],
            };
            foreach ($quiet as $step) {
                $fill = ['status' => $step, 'updated_by' => $user->id];
                if ($step === 'delivered' && blank($release->delivered_at)) {
                    $fill['delivered_at'] = $now;
                }
                $release->forceFill($fill)->save();
            }
            $release->refresh();

            $this->workflow->transition($release, 'live', $user, [
                'action' => 'one_click_live',
                'remarks' => $note,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        });

        try {
            $this->audit->record(
                'release.one_click_live',
                'releases',
                'Release "'.$release->title.'" ('.$release->upc.') marked live in one click by '.$user->name.' from '.$from.'. Stores: '.count($storeIds).'. '.$note,
                ['status' => $from],
                ['status' => 'live', 'stores' => count($storeIds), 'release_id' => $release->id],
                $release
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return ['stores' => count($storeIds), 'isrc_made' => $made];
    }

    private function storeIds(Release $release): array
    {
        $chosen = is_array($release->stores) ? array_values(array_unique(array_map('intval', $release->stores))) : [];
        $q = DistributionStore::query()->where('is_active', true);
        if ($chosen !== []) {
            $q->whereIn('id', $chosen);
        }

        return $q->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
