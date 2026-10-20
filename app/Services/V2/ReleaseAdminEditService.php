<?php

namespace App\Services\V2;

use App\Models\CatalogueItem;
use App\Models\Distribution\Release;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/*
 * MIXXTUNE_SUPER_ADMIN_FULL_EDIT
 *
 * Bookkeeping for a Super Admin editing a locked (non-draft)
 * release: one audit row per save with old / new values, and the
 * All Catalogue record refreshed so the change is visible at once.
 */
class ReleaseAdminEditService
{
    public function __construct(
        private readonly ReleaseAccessService $access,
        private readonly AuditLogService $audit,
        private readonly CatalogueService $catalogue
    ) {
    }

    public function active(?Request $request, Release $release): bool
    {
        return $this->access->isAdminOverride($request?->user(), $release);
    }

    public function record(
        Release $release,
        string $what,
        array $old,
        array $new,
        ?Request $request = null
    ): void {
        [$old, $new] = $this->onlyChanged($old, $new);

        try {
            $this->audit->record(
                'release.admin_edit',
                'releases',
                'Super admin edited '.$what.' of release #'.$release->id
                    .' "'.$release->title.'" (status: '.$release->status
                    .', UPC: '.($release->upc ?: '-').').',
                $old,
                $new,
                $release,
                $request
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->syncCatalogue($release, $request);
    }

    public function syncCatalogue(Release $release, ?Request $request = null): void
    {
        if (!CatalogueItem::query()->where('release_id', $release->id)->exists()) {
            return;
        }

        try {
            $this->catalogue->syncRelease(
                $release->fresh(),
                $request?->user()
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function trackHasRevenue(int $trackId): bool
    {
        if (!Schema::hasTable('report_rows') || !Schema::hasColumn('report_rows', 'track_id')) {
            return false;
        }

        return DB::table('report_rows')->where('track_id', $trackId)->exists();
    }

    private function onlyChanged(array $old, array $new): array
    {
        $o = [];
        $n = [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            if ($this->normal($before) === $this->normal($after)) {
                continue;
            }

            $o[$key] = $before;
            $n[$key] = $after;
        }

        return [$o, $n];
    }

    private function normal(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $value = (string) ($value ?? '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }

        return $value;
    }
}
