<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/*
 * W120 MIXXTUNE_RELEASE_TRASH
 *
 * Super Admin "Delete" of any release (trial / test content):
 *   1. Move to Trash - the release and its tracks disappear from every list, the catalogue,
 *      reports pickers and dashboards (soft delete + catalogue rows taken out). Kept 90 days.
 *   2. Restore - everything comes back as it was.
 *   3. Delete forever (by hand, or by itself after 90 days) - release, tracks, credits, splits,
 *      deliveries, logs, catalogue rows and the poster / audio files are removed. A UPC / ISRC from
 *      the Code lists goes back to the list when the release never reached the stores, else it is
 *      kept aside as "retired" (never given out again).
 * A release that already has money on it (revenue / royalty lines) cannot be deleted - money
 * records must never lose their song.
 */
class ReleaseTrash
{
    public const KEEP_DAYS = 90;

    private const MONEY = '/(royalt|revenue|report_row|ledger|statement|wallet|allocation|earning|payout|invoice|withdraw|recoup|adjust)/';

    private const SKIP = ['releases', 'tracks', 'release_trash', 'upc_codes', 'isrc_codes', 'audit_logs', 'catalogue_items', 'migrations'];

    /** tables that have a release_id / track_id column: [table => [cols]] */
    private function linkedTables(): array
    {
        $out = [];
        foreach (Schema::getTables() as $t) {
            $name = is_array($t) ? ($t['name'] ?? '') : (string) $t;
            if ($name === '' || str_starts_with($name, 'sqlite_')) {
                continue;
            }
            $cols = array_values(array_intersect(['release_id', 'track_id'], Schema::getColumnListing($name)));
            if ($cols) {
                $out[$name] = $cols;
            }
        }

        return $out;
    }

    private function hits(string $table, array $cols, int $releaseId, array $trackIds): int
    {
        return (int) DB::table($table)->where(function ($q) use ($cols, $releaseId, $trackIds) {
            if (in_array('release_id', $cols, true)) {
                $q->orWhere('release_id', $releaseId);
            }
            if (in_array('track_id', $cols, true) && $trackIds) {
                $q->orWhereIn('track_id', $trackIds);
            }
        })->count();
    }

    /** money rows that stop a delete: [table => count] */
    public function moneyLinks(Release $release): array
    {
        $trackIds = Track::withTrashed()->where('release_id', $release->id)->pluck('id')->map(fn ($i) => (int) $i)->all();
        $out = [];
        foreach ($this->linkedTables() as $table => $cols) {
            if (in_array($table, self::SKIP, true) || !preg_match(self::MONEY, $table)) {
                continue;
            }
            $n = $this->hits($table, $cols, (int) $release->id, $trackIds);
            if ($n > 0) {
                $out[$table] = $n;
            }
        }

        return $out;
    }

    public function refuseMoney(Release $release): void
    {
        $money = $this->moneyLinks($release);
        if ($money) {
            $list = implode(', ', array_map(fn ($t, $n) => str_replace('_', ' ', $t).' ('.$n.')', array_keys($money), $money));
            throw ValidationException::withMessages(['release' => 'This release has money on it ('.$list.') - it cannot be deleted. Take it down instead.']);
        }
    }

    public function trash(Release $release, User $by, ?string $reason = null): object
    {
        $this->refuseMoney($release);

        return DB::transaction(function () use ($release, $by, $reason) {
            $release = Release::query()->whereKey($release->id)->lockForUpdate()->firstOrFail();
            $now = now();
            $trackIds = Track::query()->where('release_id', $release->id)->pluck('id')->map(fn ($i) => (int) $i)->all();
            $catalogue = Schema::hasTable('catalogue_items')
                ? DB::table('catalogue_items')->where('release_id', $release->id)->get()->map(fn ($r) => (array) $r)->all()
                : [];

            if ($catalogue) {
                DB::table('catalogue_items')->where('release_id', $release->id)->delete();
            }
            if ($trackIds) {
                Track::query()->whereIn('id', $trackIds)->update(['deleted_at' => $now]);
            }
            $statusBefore = (string) $release->status;
            $release->forceFill(['updated_by' => $by->id])->saveQuietly();
            $release->delete();

            $id = DB::table('release_trash')->insertGetId([
                'release_id' => $release->id,
                'title' => mb_substr((string) $release->title, 0, 255),
                'catalog_number' => $release->catalog_number,
                'upc' => $release->upc,
                'label_name' => $release->label_id ? DB::table('labels')->where('id', $release->label_id)->value('name') : null,
                'artist_name' => mb_substr((string) $release->primary_artist_name, 0, 255),
                'artwork_path' => $release->artwork_path,
                'status_before' => $statusBefore,
                'tracks' => count($trackIds),
                'reason' => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 1000) : null,
                'deleted_by' => $by->id,
                'trashed_at' => $now,
                'purge_after' => $now->copy()->addDays(self::KEEP_DAYS),
                'snapshot' => json_encode(['track_ids' => $trackIds, 'catalogue_items' => $catalogue]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            FinanceAudit::log('release.trashed', 'Release "'.$release->title.'" ('.($release->upc ?: $release->catalog_number).') moved to Trash'.($reason ? ': '.$reason : '').'. Removed for good after '.self::KEEP_DAYS.' days unless restored.', ['release_id' => $release->id, 'status_before' => $statusBefore, 'tracks' => count($trackIds)]);

            return DB::table('release_trash')->find($id);
        });
    }

    public function restore(int $trashId, User $by): Release
    {
        return DB::transaction(function () use ($trashId, $by) {
            $row = DB::table('release_trash')->where('id', $trashId)->lockForUpdate()->first();
            if (!$row) {
                throw ValidationException::withMessages(['release' => 'This release is not in the Trash any more.']);
            }
            $release = Release::withTrashed()->findOrFail($row->release_id);
            $snap = json_decode((string) $row->snapshot, true) ?: [];
            $release->restore();
            $ids = array_map('intval', $snap['track_ids'] ?? []);
            if ($ids) {
                Track::withTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]);
            }
            foreach ($snap['catalogue_items'] ?? [] as $item) {
                if (!DB::table('catalogue_items')->where('id', $item['id'] ?? 0)->exists()) {
                    DB::table('catalogue_items')->insert($item);
                }
            }
            DB::table('release_trash')->where('id', $row->id)->delete();
            FinanceAudit::log('release.restored', 'Release "'.$release->title.'" restored from the Trash by '.$by->name.'.', ['release_id' => $release->id]);

            return $release->fresh();
        });
    }

    /** remove a trashed release for good; returns the title */
    public function purge(int $trashId, ?User $by = null): string
    {
        $row = DB::table('release_trash')->where('id', $trashId)->first();
        if (!$row) {
            throw ValidationException::withMessages(['release' => 'This release is not in the Trash any more.']);
        }
        $release = Release::withTrashed()->find($row->release_id);
        if (!$release) {
            DB::table('release_trash')->where('id', $row->id)->delete();

            return (string) $row->title;
        }
        $this->refuseMoney($release);

        $tracks = Track::withTrashed()->where('release_id', $release->id)->get();
        $trackIds = $tracks->pluck('id')->map(fn ($i) => (int) $i)->all();
        $files = array_values(array_filter(array_merge([$release->artwork_path], $tracks->pluck('audio_path')->all())));
        $reached = in_array((string) $row->status_before, ['processing', 'delivered', 'live', 'failed', 'takedown_requested', 'taken_down'], true)
            || $release->delivered_at || $release->live_at;

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($release, $trackIds, $row, $reached) {
                foreach ($this->linkedTables() as $table => $cols) {
                    if (in_array($table, self::SKIP, true)) {
                        continue;
                    }
                    if (in_array('track_id', $cols, true) && $trackIds) {
                        DB::table($table)->whereIn('track_id', $trackIds)->delete();
                    }
                    if (in_array('release_id', $cols, true)) {
                        DB::table($table)->where('release_id', $release->id)->delete();
                    }
                }
                if (Schema::hasTable('catalogue_items')) {
                    DB::table('catalogue_items')->where('release_id', $release->id)->delete();
                }
                /* codes from the Code lists: back to the list, or kept aside when the song reached stores */
                $free = fn (string $table, string $col, array $ids) => $ids && Schema::hasTable($table)
                    ? DB::table($table)->whereIn($col, $ids)->update([
                        'status' => $reached ? 'retired' : 'available',
                        $col => null,
                        'notes' => $reached ? 'Release "'.$row->title.'" was deleted after it reached stores - not given out again.' : 'Back in the list: release "'.$row->title.'" was deleted before it reached stores.',
                        'updated_at' => now(),
                    ])
                    : 0;
                $free('upc_codes', 'release_id', [(int) $release->id]);
                $free('isrc_codes', 'track_id', $trackIds);

                DB::table('tracks')->whereIn('id', $trackIds ?: [0])->delete();
                DB::table('releases')->where('id', $release->id)->delete();
                DB::table('release_trash')->where('id', $row->id)->delete();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        /* files: only when no other release / track still points at the same file */
        foreach (array_unique($files) as $path) {
            try {
                $used = DB::table('releases')->where('artwork_path', $path)->exists() || DB::table('tracks')->where('audio_path', $path)->exists();
                if (!$used && !str_starts_with((string) $path, 'http')) {
                    Storage::disk('public')->delete($path);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        FinanceAudit::log('release.purged', 'Release "'.$row->title.'" ('.($row->upc ?: $row->catalog_number).') deleted for good'.($by ? ' by '.$by->name : ' after '.self::KEEP_DAYS.' days in the Trash').'. Tracks: '.count($trackIds).'.', ['release_id' => (int) $row->release_id, 'upc' => $row->upc]);

        return (string) $row->title;
    }

    /** the daily clean-up: everything older than 90 days in the Trash */
    public function purgeDue(): array
    {
        $done = 0;
        $kept = [];
        foreach (DB::table('release_trash')->where('purge_after', '<=', now())->orderBy('id')->pluck('id') as $id) {
            try {
                $this->purge((int) $id);
                $done++;
            } catch (Throwable $e) {
                $kept[] = $id.': '.$e->getMessage();
            }
        }

        return ['purged' => $done, 'kept' => $kept];
    }
}
