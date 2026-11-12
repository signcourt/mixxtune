<?php

namespace App\Services\Iprs;

use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Iprs\IprsRoyaltyLine;
use App\Models\Iprs\IprsUpload;
use App\Services\Royalties\WalletPostingService;
use App\Services\V2\CatalogueOwnershipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Publishes a mapped IPRS statement to owners.
 *
 * For every matched royalty line it creates a royalty_ledgers row (source_type
 * "iprs_royalty"), applying the owner's existing royalty share (same rate as
 * their digital reports), then credits the owner's wallet through the shared
 * WalletPostingService::creditLedger - so IPRS earnings sit in the same wallet,
 * summed with digital earnings. Idempotent: a line already turned into a ledger
 * (matched by source_key) is skipped, so re-publishing never double-credits.
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
     * @return array{published:int,credited:float,skipped_unmatched:int,skipped_no_owner:int,already:int}
     */
    public function publish(IprsUpload $upload, int $actorId): array
    {
        $month = optional($upload->created_at)->format('Y-m') ?? now()->format('Y-m');

        $lines = $upload->lines()->get();

        $published = 0;
        $already = 0;
        $skippedUnmatched = 0;
        $skippedNoOwner = 0;
        $credited = 0.0;

        foreach ($lines as $line) {
            if ($line->mapping_status !== 'matched' || ! $line->track_id) {
                $skippedUnmatched++;
                continue;
            }

            // Idempotency: this line already has a ledger.
            $exists = DB::table('royalty_ledgers')
                ->where('source_type', 'iprs_royalty')
                ->where('source_key', $line->public_id)
                ->exists();
            if ($exists) {
                $already++;
                continue;
            }

            $track = Track::with('release')->find($line->track_id);
            $owner = $this->resolveBeneficiary($track?->release);

            if (! $owner['user_id'] || ! $owner['beneficiary_type']) {
                $skippedNoOwner++;
                continue;
            }

            $gross = round((float) $line->amount, 8);
            // Owner-share rate for this owner and the statement's royalty type
            // (per-owner rate -> global default -> digital share -> 100).
            $share = $this->rates->ownerShare(
                $owner['beneficiary_type'],
                (int) $owner['beneficiary_id'],
                $upload->royalty_type ?: 'publishing'
            );
            $net = round($gross * $share / 100, 8);

            $ledgerId = DB::transaction(function () use ($line, $track, $owner, $gross, $net, $share, $month, $actorId) {
                $id = DB::table('royalty_ledgers')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'ledger_number' => 'IPRS-'.$line->public_id,
                    'release_id' => $track->release_id,
                    'track_id' => $track->id,
                    'artist_id' => $owner['artist_id'],
                    'user_id' => $owner['user_id'],
                    'label_id' => $owner['label_id'],
                    'revenue_label_mapping_id' => null,
                    'source_type' => 'iprs_royalty',
                    'source_key' => $line->public_id,
                    'beneficiary_type' => $owner['beneficiary_type'],
                    'beneficiary_id' => $owner['beneficiary_id'],
                    'master_label_id' => null,
                    'statement_month' => $month,
                    'reporting_month' => $month,
                    'sale_month' => $month,
                    'store_name' => 'IPRS ROYALTY',
                    'territory' => null,
                    'currency' => 'INR',
                    'gross_amount' => $gross,
                    'label_share' => $owner['beneficiary_type'] === 'label' ? $net : 0,
                    'artist_share' => $owner['beneficiary_type'] === 'artist' ? $net : 0,
                    'split_percentage' => $share,
                    'payable_amount' => $net,
                    'streams' => 0,
                    'status' => 'approved',
                    'metadata' => json_encode([
                        'source' => 'iprs_royalty',
                        'iprs_upload_id' => $line->iprs_upload_id,
                        'iprs_line_public_id' => $line->public_id,
                        'work_int_no' => $line->work_int_no,
                        'iprs_source' => $line->source,
                        'isrc' => $line->isrc,
                    ]),
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $id;
            });

            // Credit the owner's wallet through the shared primitive.
            $this->wallet->creditLedger($ledgerId, (int) $owner['user_id']);

            $published++;
            $credited = round($credited + $net, 8);
        }

        if ($published > 0 || $already > 0) {
            $meta = $upload->metadata ?? [];
            $meta['published'] = [
                'at' => now()->toIso8601String(),
                'by' => $actorId,
                'lines' => $published,
                'credited' => $credited,
            ];
            $upload->metadata = $meta;
            $upload->status = 'distributed';
            $upload->save();
        }

        return [
            'published' => $published,
            'credited' => $credited,
            'skipped_unmatched' => $skippedUnmatched,
            'skipped_no_owner' => $skippedNoOwner,
            'already' => $already,
        ];
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

            return [
                'beneficiary_type' => 'label',
                'beneficiary_id' => $id,
                'label_id' => $id,
                'artist_id' => null,
                'user_id' => $label->user_id ?? null,
                // Same rate the digital reports use (ReportImportService / AgreementResolver): revenue_share_percentage.
                'share' => (float) ($label->revenue_share_percentage ?? 100),
                'name' => $label->name ?? null,
            ];
        }

        if ($type === 'artist' && $id) {
            $artist = DB::table('artists')->where('id', $id)->first();
            if (! $artist) {
                return $empty;
            }

            return [
                'beneficiary_type' => 'artist',
                'beneficiary_id' => $id,
                'label_id' => null,
                'artist_id' => $id,
                'user_id' => $artist->user_id ?? null,
                'share' => (float) ($artist->revenue_share_percentage ?? 100),
                'name' => $artist->stage_name ?? null,
            ];
        }

        return $empty;
    }
}
