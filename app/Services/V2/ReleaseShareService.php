<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Support\DistributorHost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * W238 MIXXTUNE_PROMO_KIT - the public "listen" page of a live release.
 * Shown to the world: poster, title, artist, store buttons, the brand. Nothing else (no owner details, no money, no drafts).
 */
class ReleaseShareService
{
    /** stores the owner can give a link for, in the order they are shown */
    public const STORES = [
        'spotify' => 'Spotify',
        'apple_music' => 'Apple Music',
        'youtube_music' => 'YouTube Music',
        'youtube' => 'YouTube',
        'jiosaavn' => 'JioSaavn',
        'gaana' => 'Gaana',
        'amazon_music' => 'Amazon Music',
        'wynk' => 'Wynk Music',
        'instagram' => 'Instagram',
        'deezer' => 'Deezer', /* W239 */
    ];

    public const LIVE = ['live', 'delivered'];

    public static function ready(): bool
    {
        try {
            return Schema::hasTable('release_share_pages');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function eligible(Release $release): bool
    {
        return in_array((string) $release->status, self::LIVE, true) && empty($release->deleted_at);
    }

    public function page(int $releaseId): ?object
    {
        return self::ready() ? DB::table('release_share_pages')->where('release_id', $releaseId)->first() : null;
    }

    public function byCode(string $code): ?object
    {
        return self::ready() ? DB::table('release_share_pages')->where('code', $code)->first() : null;
    }

    public function newCode(): string
    {
        do {
            $code = Str::lower(Str::random(10));
        } while (DB::table('release_share_pages')->where('code', $code)->exists());

        return $code;
    }

    /** a link a stranger may be sent to: http(s) only, a real host, no spaces, not too long */
    public function cleanUrl($value): ?string
    {
        $url = trim((string) $value);
        if ($url === '' || strlen($url) > 500 || preg_match('/\s/', $url)) {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || $host === '' || ! str_contains($host, '.') || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }

    private function keyForStore(string $slug, string $name): ?string
    {
        $s = preg_replace('/[^a-z0-9]+/', '', strtolower($slug.' '.$name));
        return match (true) {
            str_contains($s, 'spotify') => 'spotify',
            str_contains($s, 'youtubemusic') || str_contains($s, 'ytmusic') => 'youtube_music',
            str_contains($s, 'youtube') => 'youtube',
            str_contains($s, 'apple') || str_contains($s, 'itunes') => 'apple_music',
            str_contains($s, 'saavn') => 'jiosaavn',
            str_contains($s, 'gaana') => 'gaana',
            str_contains($s, 'amazon') => 'amazon_music',
            str_contains($s, 'wynk') => 'wynk',
            str_contains($s, 'instagram') => 'instagram',
            default => null,
        };
    }

    /** store links the delivery desk already recorded for this release: [key => url] */
    public function autoLinks(int $releaseId): array
    {
        $out = [];
        /* W239 agent-links: what the Live Links Agent found by ISRC / UPC comes first */
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('release_link_agent')) {
                $found = DB::table('release_link_agent')->where('release_id', $releaseId)->value('links');
                foreach ((array) json_decode((string) $found, true) as $key => $url) {
                    $clean = $this->cleanUrl($url);
                    if (isset(self::STORES[$key]) && $clean) {
                        $out[$key] = $clean;
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $rows = DB::table('release_store_deliveries as d')
                ->join('distribution_stores as s', 's.id', '=', 'd.distribution_store_id')
                ->where('d.release_id', $releaseId)
                ->whereNotNull('d.store_url')
                ->where('d.store_url', '!=', '')
                ->orderBy('d.id')
                ->get(['s.slug', 's.name', 'd.store_url']);
            foreach ($rows as $r) {
                $key = $this->keyForStore((string) $r->slug, (string) $r->name);
                $url = $this->cleanUrl($r->store_url);
                if ($key && $url && ! isset($out[$key])) {
                    $out[$key] = $url;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $out;
    }

    public function manualLinks(?object $page): array
    {
        $raw = $page && $page->links ? json_decode((string) $page->links, true) : [];
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $key => $url) {
            $clean = $this->cleanUrl($url);
            if (isset(self::STORES[$key]) && $clean) {
                $out[$key] = $clean;
            }
        }

        return $out;
    }

    /** what the public page shows: the owner's link wins over the recorded one */
    public function finalLinks(int $releaseId, ?object $page): array
    {
        $manual = $this->manualLinks($page);
        $auto = $this->autoLinks($releaseId);
        $out = [];
        foreach (self::STORES as $key => $label) {
            $url = $manual[$key] ?? $auto[$key] ?? null;
            if ($url) {
                $out[] = ['key' => $key, 'label' => $label, 'url' => $url];
            }
        }

        return $out;
    }

    public static function shareHost(): string
    {
        return 'share'.DistributorHost::suffix().'.'.DistributorHost::BASE;
    }

    /** the address people are sent to: share.mixxtune.com once its certificate is ready, the normal address until then */
    public function publicUrl(string $code): string
    {
        $ready = false;
        try {
            $ready = (bool) app(SystemSettingService::class)->get('share.host_ready', false);
        } catch (\Throwable $e) {
            $ready = false;
        }
        $base = $ready ? 'https://'.self::shareHost() : rtrim((string) config('app.url'), '/');

        return $base.'/r/'.$code;
    }

    /** whose name stands on the page: the distributor's for a release in his tree, ours otherwise */
    public function brandName(Release $release): string
    {
        try {
            if ($release->label_id && DistributorService::ready()) {
                $label = DB::table('labels')->where('id', $release->label_id)->first(['id', 'parent_label_id']);
                $ids = array_values(array_filter([(int) ($label->id ?? 0), (int) ($label->parent_label_id ?? 0)]));
                $d = $ids ? DB::table('distributors')->whereIn('label_id', $ids)->where('status', 'active')->first(['brand_name', 'label_id']) : null;
                if ($d) {
                    $name = trim((string) $d->brand_name) ?: (string) DB::table('labels')->where('id', $d->label_id)->value('name');
                    if ($name !== '') {
                        return $name;
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return 'Mixx Tune';
    }

    public function hasPoster(Release $release): bool
    {
        return (bool) $release->artwork_path && Storage::disk('public')->exists($release->artwork_path);
    }

    /**
     * The poster as a web-sized JPEG (longest side 1200), made once and kept. Returns an absolute file path,
     * or the original file when the picture cannot be resized here.
     */
    public function posterFile(Release $release): ?string
    {
        if (! $this->hasPoster($release)) {
            return null;
        }
        $disk = Storage::disk('public');
        $source = $disk->path($release->artwork_path);
        $dir = storage_path('app/share-cache');
        $target = $dir.'/'.$release->id.'-'.substr(sha1($release->artwork_path.'|'.@filemtime($source)), 0, 16).'.jpg';
        if (is_file($target) && filesize($target) > 0) {
            return $target;
        }

        try {
            if (! function_exists('imagecreatefromstring')) {
                return $source;
            }
            $info = @getimagesize($source);
            if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40000000) {
                return $source;
            }
            @ini_set('memory_limit', '768M');
            $img = @imagecreatefromstring((string) file_get_contents($source));
            if (! $img) {
                return $source;
            }
            [$w, $h] = [imagesx($img), imagesy($img)];
            $scale = min(1, 1200 / max($w, $h));
            [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
            $out = imagecreatetruecolor($nw, $nh);
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $tmp = $target.'.'.getmypid().'.tmp';
            $ok = imagejpeg($out, $tmp, 86);
            imagedestroy($img);
            imagedestroy($out);
            if ($ok && @rename($tmp, $target)) {
                return $target;
            }
            @unlink($tmp);
        } catch (\Throwable $e) {
            report($e);
        }

        return $source;
    }
}
