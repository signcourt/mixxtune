<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * W58 MIXXTUNE_LEGACY_IDENTIFIER_RULE
 *
 * Old catalogue (WordPress migration "WP-..." catalogue numbers, or rows
 * brought in by the Legacy Import page) already has its real UPC / ISRC
 * from the previous distributor. New codes must never be generated for it
 * automatically - the existing code has to be typed in.
 */
class LegacyRelease
{
    public static function is(?object $release): bool
    {
        if (!$release || empty($release->id)) {
            return false;
        }

        if (str_starts_with((string) ($release->catalog_number ?? ''), 'WP-')) {
            return true;
        }

        static $hasTable = null;
        $hasTable ??= Schema::hasTable('legacy_catalogue_import_rows');

        return $hasTable && DB::table('legacy_catalogue_import_rows')->where('release_id', $release->id)->exists();
    }

    public static function message(string $what = 'UPC / ISRC'): string
    {
        return 'This is an old catalogue release: its '.$what.' is never generated automatically. Enter the existing code from the previous distributor instead.';
    }
}
