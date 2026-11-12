<?php

namespace App\Services\Iprs;

use App\Models\Iprs\IprsRoyaltyRate;
use Illuminate\Support\Facades\DB;

/*
 * Resolves and manages per-owner, per-type IPRS royalty rates.
 * owner_share_percentage is what the owner keeps.
 *
 * Resolution order for a rate:
 *   1. this owner's rate for this royalty type
 *   2. the global default for this royalty type
 *   3. the owner's digital revenue-share %
 *   4. 100 (owner keeps everything)
 */
class IprsRateService
{
    public const TYPES = ['publishing', 'ppl', 'mechanical'];

    /** The owner's share % (what they keep) for a royalty type. */
    public function ownerShare(string $ownerType, int $ownerId, string $royaltyType = 'publishing'): float
    {
        $specific = IprsRoyaltyRate::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('royalty_type', $royaltyType)
            ->value('owner_share_percentage');
        if ($specific !== null) {
            return (float) $specific;
        }

        $default = IprsRoyaltyRate::where('owner_type', 'default')
            ->where('owner_id', 0)
            ->where('royalty_type', $royaltyType)
            ->value('owner_share_percentage');
        if ($default !== null) {
            return (float) $default;
        }

        // Fall back to the owner's digital revenue-share.
        $table = $ownerType === 'label' ? 'labels' : ($ownerType === 'artist' ? 'artists' : null);
        if ($table) {
            $share = DB::table($table)->where('id', $ownerId)->value('revenue_share_percentage');
            if ($share !== null) {
                return (float) $share;
            }
        }

        return 100.0;
    }

    public function setRate(string $ownerType, int $ownerId, string $royaltyType, float $share, ?int $actorId = null): void
    {
        $share = max(0, min(100, $share));

        IprsRoyaltyRate::updateOrCreate(
            ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'royalty_type' => $royaltyType],
            ['owner_share_percentage' => $share, 'updated_by' => $actorId]
        );
    }

    public function clearRate(string $ownerType, int $ownerId, string $royaltyType): void
    {
        IprsRoyaltyRate::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('royalty_type', $royaltyType)
            ->delete();
    }

    /** Global default rates keyed by type. @return array<string,?float> */
    public function defaults(): array
    {
        $rows = IprsRoyaltyRate::where('owner_type', 'default')->where('owner_id', 0)->get();
        $out = array_fill_keys(self::TYPES, null);
        foreach ($rows as $r) {
            $out[$r->royalty_type] = (float) $r->owner_share_percentage;
        }

        return $out;
    }

    /** A specific owner's set rates keyed by type (null where not set). @return array<string,?float> */
    public function ratesFor(string $ownerType, int $ownerId): array
    {
        $rows = IprsRoyaltyRate::where('owner_type', $ownerType)->where('owner_id', $ownerId)->get();
        $out = array_fill_keys(self::TYPES, null);
        foreach ($rows as $r) {
            $out[$r->royalty_type] = (float) $r->owner_share_percentage;
        }

        return $out;
    }

    /**
     * Labels (and artists) with their set IPRS rates, for the rates screen.
     * @return array<int,array<string,mixed>>
     */
    public function listOwners(string $q = '', int $limit = 25): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($q)).'%';

        $labels = DB::table('labels')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', $like))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'revenue_share_percentage']);

        $out = [];
        foreach ($labels as $l) {
            $out[] = [
                'owner_type' => 'label',
                'owner_id' => (int) $l->id,
                'name' => $l->name,
                'digital_share' => $l->revenue_share_percentage !== null ? (float) $l->revenue_share_percentage : null,
                'rates' => $this->ratesFor('label', (int) $l->id),
            ];
        }

        return $out;
    }
}
