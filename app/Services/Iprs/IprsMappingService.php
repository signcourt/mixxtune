<?php

namespace App\Services\Iprs;

use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Iprs\IprsRoyaltyLine;
use App\Models\Iprs\IprsUpload;
use App\Services\V2\CatalogueOwnershipService;
use Illuminate\Support\Facades\DB;

/*
 * Maps an IPRS work (WORK INT NO) to a catalogue track.
 *
 * The chosen track keeps the work number on itself (tracks.iprs_work_no), so a
 * future statement carrying the same work number maps to the track on its own.
 * Mapping a work matches every royalty line of that work (across all uploads)
 * and resolves the owner (label/artist -> user) through CatalogueOwnershipService.
 */
class IprsMappingService
{
    /** @return array<int,array<string,mixed>> */
    public function searchTracks(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';
        $isrcNorm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $query));

        $tracks = Track::query()
            ->with(['release.label', 'release.artist'])
            ->where(function ($q) use ($like, $isrcNorm) {
                $q->where('title', 'like', $like);
                if ($isrcNorm !== '') {
                    $q->orWhereRaw("UPPER(REPLACE(REPLACE(isrc,'-',''),' ','')) LIKE ?", ['%'.$isrcNorm.'%']);
                    $q->orWhere('iprs_work_no', 'like', $like);
                }
            })
            ->orderBy('title')
            ->limit($limit)
            ->get();

        return $tracks->map(function (Track $t) {
            $release = $t->release;
            $label = $release?->label;
            $artist = $release?->artist;

            return [
                'track_id' => $t->id,
                'public_id' => $t->public_id,
                'title' => $t->title,
                'isrc' => $t->isrc,
                'upc' => $release?->upc,
                'release_title' => $release?->title,
                'owner_name' => $label->name ?? $artist->stage_name ?? $t->primary_artist_name ?? '—',
                'owner_type' => $label ? 'label' : ($artist ? 'artist' : null),
                'current_work_no' => $t->iprs_work_no,
                // Singer + writers, so an admin can tell same-titled songs apart
                // by matching against the IPRS work's credits.
                'primary_artist' => $t->primary_artist_name,
                'featuring_artist' => $t->featuring_artist_name,
                'composer' => $t->composer_name,
                'author' => $t->author_name,
                'music_director' => $t->music_director_name,
                'publisher' => $t->publisher_name,
            ];
        })->all();
    }

    /**
     * Map a work to a track: store the work number on the track and match every
     * royalty line of that work. One work maps to exactly one track.
     *
     * @return array{matched_lines:int,track_title:?string,isrc:?string,owner_name:?string}
     */
    public function mapWork(string $workNo, int $trackId, ?int $actorId = null): array
    {
        $workNo = trim($workNo);

        return DB::transaction(function () use ($workNo, $trackId) {
            $track = Track::with(['release.label', 'release.artist'])->findOrFail($trackId);

            // One work -> one track: drop the work number from any other track.
            Track::where('iprs_work_no', $workNo)->where('id', '!=', $track->id)->update(['iprs_work_no' => null]);

            $track->iprs_work_no = $workNo;
            $track->save();

            $owner = $this->resolveOwner($track->release);

            $matched = IprsRoyaltyLine::where('work_int_no', $workNo)->update([
                'mapping_status' => 'matched',
                'track_id' => $track->id,
                'release_id' => $track->release_id,
                'isrc' => $track->isrc,
                'upc' => $track->release?->upc,
                'client_id' => $owner['user_id'],
            ]);

            return [
                'matched_lines' => $matched,
                'track_title' => $track->title,
                'isrc' => $track->isrc,
                'owner_name' => $owner['name'],
            ];
        });
    }

    /** Remove a work's mapping: clear the work number from the track and unmatch its lines. */
    public function unmapWork(string $workNo): void
    {
        $workNo = trim($workNo);

        DB::transaction(function () use ($workNo) {
            Track::where('iprs_work_no', $workNo)->update(['iprs_work_no' => null]);

            IprsRoyaltyLine::where('work_int_no', $workNo)->update([
                'mapping_status' => 'unmatched',
                'track_id' => null,
                'release_id' => null,
                'isrc' => null,
                'upc' => null,
                'client_id' => null,
            ]);
        });
    }

    /**
     * Auto-map an upload's lines against tracks that already carry the work number.
     * Returns how many works were auto-matched.
     */
    public function autoMapUpload(IprsUpload $upload): int
    {
        $workNos = $upload->lines()->distinct()->pluck('work_int_no');
        $mappedWorks = 0;

        foreach ($workNos as $workNo) {
            $track = Track::with(['release.label', 'release.artist'])
                ->where('iprs_work_no', $workNo)
                ->first();

            if (! $track) {
                continue;
            }

            $owner = $this->resolveOwner($track->release);

            IprsRoyaltyLine::where('iprs_upload_id', $upload->id)
                ->where('work_int_no', $workNo)
                ->update([
                    'mapping_status' => 'matched',
                    'track_id' => $track->id,
                    'release_id' => $track->release_id,
                    'isrc' => $track->isrc,
                    'upc' => $track->release?->upc,
                    'client_id' => $owner['user_id'],
                ]);

            $mappedWorks++;
        }

        return $mappedWorks;
    }

    /** @return array{type:?string,user_id:?int,name:?string,label_id:?int,artist_id:?int} */
    private function resolveOwner(?Release $release): array
    {
        $empty = ['type' => null, 'user_id' => null, 'name' => null, 'label_id' => null, 'artist_id' => null];

        if (! $release) {
            return $empty;
        }

        $owner = app(CatalogueOwnershipService::class)->resolveReleaseOwner($release);
        $type = $owner['type'] ?? null;
        $id = isset($owner['id']) ? (int) $owner['id'] : null;

        if ($type === 'label' && $id) {
            $label = DB::table('labels')->where('id', $id)->first();

            return ['type' => 'label', 'user_id' => $label->user_id ?? null, 'name' => $label->name ?? null, 'label_id' => $id, 'artist_id' => null];
        }

        if ($type === 'artist' && $id) {
            $artist = DB::table('artists')->where('id', $id)->first();

            return ['type' => 'artist', 'user_id' => $artist->user_id ?? null, 'name' => $artist->stage_name ?? $artist->legal_name ?? null, 'label_id' => null, 'artist_id' => $id];
        }

        return $empty;
    }
}
