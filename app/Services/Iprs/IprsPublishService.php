<?php

namespace App\Services\Iprs;

use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Iprs\IprsHeldRevenue;
use App\Models\Iprs\IprsRoyaltyLine;
use App\Models\Iprs\IprsUpload;
use App\Models\User;
use App\Services\Royalties\WalletPostingService;
use App\Services\V2\CatalogueOwnershipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Publishes a mapped IPRS statement to owners and releases held payments.
 *
 * A matched line is credited to the owner's wallet at their royalty share (via
 * the shared WalletPostingService) ONLY when the owner has an active user. When
 * the owner has no user, or an inactive one, the payment is held in
 * iprs_held_revenues (nothing hits a wallet) until it is released. Idempotent:
 * a line already turned into a ledger, or already held, is never processed twice.
 */
class IprsPublishService
{
    public function __construct(
        private CatalogueOwnershipService $ownership,
        private WalletPostingService $wallet,
        private IprsRateService $rates,
    ) {
    }

    /**
     * @return array{published:int,credited:float,held:int,held_amount:float,skipped_unmatched:int,skipped_no_owner:int,already:int}
     */
    public function publish(IprsUpload $upload, int $actorId): array
    {
        $month = optional($upload->created_at)->format('Y-m') ?? now()->format('Y-m');
        $royaltyType = $upload->royalty_type ?: 'publishing';

        $published = 0;
        $already = 0;
        $held = 0;
        $skippedUnmatched = 0;
        $skippedNoOwner = 0;
        $credited = 0.0;
        $heldAmount = 0.0;

        foreach ($upload->lines()->get() as $line) {
            if ($line->mapping_status !== 'matched' || ! $line->track_id) {
                $skippedUnmatched++;
                continue;
            }

            // Idempotency: already credited, or already held.
            $hasLedger = DB::table('royalty_ledgers')->where('source_type', 'iprs_royalty')->where('source_key', $line->public_id)->exists();
            $hasHold = IprsHeldRevenue::where('iprs_line_id', $line->id)->exists();
            if ($hasLedger || $hasHold) {
                $already++;
                continue;
            }

            $track = Track::with('release')->find($line->track_id);
            $owner = $this->resolveBeneficiary($track?->release);
            if (! $owner['beneficiary_type']) {
                $skippedNoOwner++;
                continue;
            }

            $gross = round((float) $line->amount, 8);
            $share = $this->rates->ownerShare($owner['beneficiary_type'], (int) $owner['beneficiary_id'], $royaltyType);
            $net = round($gross * $share / 100, 8);

            if ($this->isActiveUser($owner['user_id'])) {
                $this->creditLine([
                    'source_key' => $line->public_id,
                    'owner' => $owner,
                    'track_id' => $track->id,
                    'release_id' => $track->release_id,
                    'gross' => $gross,
                    'share' => $share,
                    'net' => $net,
                    'month' => $month,
                    'metadata' => [
                        'source' => 'iprs_royalty',
                        'iprs_upload_id' => $line->iprs_upload_id,
                        'iprs_line_public_id' => $line->public_id,
                        'work_int_no' => $line->work_int_no,
                        'iprs_source' => $line->source,
                        'isrc' => $line->isrc,
                    ],
                ], $actorId);
                $published++;
                $credited = round($credited + $net, 8);
            } else {
                IprsHeldRevenue::create([
                    'public_id' => (string) Str::ulid(),
                    'iprs_upload_id' => $line->iprs_upload_id,
                    'iprs_line_id' => $line->id,
                    'work_int_no' => $line->work_int_no,
                    'owner_type' => $owner['beneficiary_type'],
                    'owner_id' => $owner['beneficiary_id'],
                    'user_id' => $owner['user_id'],
                    'track_id' => $track->id,
                    'release_id' => $track->release_id,
                    'isrc' => $line->isrc,
                    'gross_amount' => $gross,
                    'share_percentage' => $share,
                    'net_amount' => $net,
                    'royalty_type' => $royaltyType,
                    'reason' => $owner['user_id'] ? 'owner_user_inactive' : 'owner_user_missing',
                    'status' => 'held',
                ]);
                $held++;
                $heldAmount = round($heldAmount + $net, 8);
            }
        }

        if ($published > 0 || $held > 0 || $already > 0) {
            $meta = $upload->metadata ?? [];
            $meta['published'] = ['at' => now()->toIso8601String(), 'by' => $actorId, 'lines' => $published, 'credited' => $credited, 'held' => $held, 'held_amount' => $heldAmount];
            $upload->metadata = $meta;
            $upload->status = 'distributed';
            $upload->save();
        }

        return [
            'published' => $published,
            'credited' => $credited,
            'held' => $held,
            'held_amount' => $heldAmount,
            'skipped_unmatched' => $skippedUnmatched,
            'skipped_no_owner' => $skippedNoOwner,
            'already' => $already,
        ];
    }

    /**
     * Release held payments whose owner now has an active user.
     * Optionally limited to one owner.
     *
     * @return array{released:int,credited:float,still_held:int}
     */
    public function release(int $actorId, ?string $ownerType = null, ?int $ownerId = null): array
    {
        $query = IprsHeldRevenue::where('status', 'held');
        if ($ownerType && $ownerId) {
            $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
        }

        $released = 0;
        $credited = 0.0;
        $stillHeld = 0;

        foreach ($query->get() as $hold) {
            // Re-resolve the owner's current user and active status.
            $userId = $this->ownerUserId($hold->owner_type, (int) $hold->owner_id);
            if (! $this->isActiveUser($userId)) {
                $stillHeld++;
                continue;
            }

            // Idempotency: a ledger may already exist for this line.
            $line = IprsRoyaltyLine::find($hold->iprs_line_id);
            $sourceKey = $line?->public_id ?? ('hold-'.$hold->public_id);
            $already = DB::table('royalty_ledgers')->where('source_type', 'iprs_royalty')->where('source_key', $sourceKey)->exists();

            if (! $already) {
                $ledgerId = $this->creditLine([
                    'source_key' => $sourceKey,
                    'owner' => [
                        'beneficiary_type' => $hold->owner_type,
                        'beneficiary_id' => (int) $hold->owner_id,
                        'label_id' => $hold->owner_type === 'label' ? (int) $hold->owner_id : null,
                        'artist_id' => $hold->owner_type === 'artist' ? (int) $hold->owner_id : null,
                        'user_id' => $userId,
                    ],
                    'track_id' => $hold->track_id,
                    'release_id' => $hold->release_id,
                    'gross' => (float) $hold->gross_amount,
                    'share' => (float) $hold->share_percentage,
                    'net' => (float) $hold->net_amount,
                    'month' => optional($hold->created_at)->format('Y-m') ?? now()->format('Y-m'),
                    'metadata' => [
                        'source' => 'iprs_royalty',
                        'released_from_hold' => $hold->public_id,
                        'work_int_no' => $hold->work_int_no,
                        'isrc' => $hold->isrc,
                    ],
                ], $actorId);
                $hold->royalty_ledger_id = $ledgerId;
            }

            $hold->status = 'released';
            $hold->user_id = $userId;
            $hold->released_at = now();
            $hold->released_by = $actorId;
            $hold->save();

            $released++;
            $credited = round($credited + (float) $hold->net_amount, 8);
        }

        return ['released' => $released, 'credited' => $credited, 'still_held' => $stillHeld];
    }

    /** Insert an approved iprs_royalty ledger row and credit the owner's wallet. Returns ledger id. */
    private function creditLine(array $ctx, int $actorId): int
    {
        $owner = $ctx['owner'];

        $ledgerId = DB::transaction(function () use ($ctx, $owner, $actorId) {
            return DB::table('royalty_ledgers')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'ledger_number' => 'IPRS-'.Str::upper(Str::random(12)),
                'release_id' => $ctx['release_id'],
                'track_id' => $ctx['track_id'],
                'artist_id' => $owner['artist_id'],
                'user_id' => $owner['user_id'],
                'label_id' => $owner['label_id'],
                'revenue_label_mapping_id' => null,
                'source_type' => 'iprs_royalty',
                'source_key' => $ctx['source_key'],
                'beneficiary_type' => $owner['beneficiary_type'],
                'beneficiary_id' => $owner['beneficiary_id'],
                'master_label_id' => null,
                'statement_month' => $ctx['month'],
                'reporting_month' => $ctx['month'],
                'sale_month' => $ctx['month'],
                'store_name' => 'IPRS ROYALTY',
                'territory' => null,
                'currency' => 'INR',
                'gross_amount' => $ctx['gross'],
                'label_share' => $owner['beneficiary_type'] === 'label' ? $ctx['net'] : 0,
                'artist_share' => $owner['beneficiary_type'] === 'artist' ? $ctx['net'] : 0,
                'split_percentage' => $ctx['share'],
                'payable_amount' => $ctx['net'],
                'streams' => 0,
                'status' => 'approved',
                'metadata' => json_encode($ctx['metadata']),
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->wallet->creditLedger($ledgerId, (int) $owner['user_id']);

        return $ledgerId;
    }

    private function isActiveUser(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return User::where('id', $userId)->where('account_status', 'active')->exists();
    }

    private function ownerUserId(string $ownerType, int $ownerId): ?int
    {
        $table = $ownerType === 'label' ? 'labels' : ($ownerType === 'artist' ? 'artists' : null);
        if (! $table) {
            return null;
        }
        $uid = DB::table($table)->where('id', $ownerId)->value('user_id');

        return $uid ? (int) $uid : null;
    }

    /** @return array{beneficiary_type:?string,beneficiary_id:?int,label_id:?int,artist_id:?int,user_id:?int,share:float,name:?string} */
    private function resolveBeneficiary(?Release $release): array
    {
        $empty = ['beneficiary_type' => null, 'beneficiary_id' => null, 'label_id' => null, 'artist_id' => null, 'user_id' => null, 'share' => 0.0, 'name' => null];
        if (! $release) {
            return $empty;
        }

        $owner = $this->ownership->resolveReleaseOwner($release);
        $type = $owner['type'] ?? null;
        $id = isset($owner['id']) ? (int) $owner['id'] : null;

        if ($type === 'label' && $id) {
            $label = DB::table('labels')->where('id', $id)->first();
            if (! $label) {
                return $empty;
            }

            return ['beneficiary_type' => 'label', 'beneficiary_id' => $id, 'label_id' => $id, 'artist_id' => null, 'user_id' => $label->user_id ?? null, 'share' => (float) ($label->revenue_share_percentage ?? 100), 'name' => $label->name ?? null];
        }

        if ($type === 'artist' && $id) {
            $artist = DB::table('artists')->where('id', $id)->first();
            if (! $artist) {
                return $empty;
            }

            return ['beneficiary_type' => 'artist', 'beneficiary_id' => $id, 'label_id' => null, 'artist_id' => $id, 'user_id' => $artist->user_id ?? null, 'share' => (float) ($artist->revenue_share_percentage ?? 100), 'name' => $artist->stage_name ?? null];
        }

        return $empty;
    }
}
