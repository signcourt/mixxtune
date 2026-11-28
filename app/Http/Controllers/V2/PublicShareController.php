<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\ReleaseShareService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * W238 - the public listen page. No login. It answers only for a page that is switched on AND whose release is
 * live; everything else is a plain 404, so a draft or a taken-down release can never be seen here.
 */
class PublicShareController extends Controller
{
    private function find(string $code, ReleaseShareService $share): array
    {
        $page = $share->byCode($code);
        abort_unless($page && $page->enabled, 404);
        $release = Release::query()->find($page->release_id);
        abort_unless($release && $share->eligible($release), 404);

        return [$page, $release];
    }

    public function show(Request $request, string $code, ReleaseShareService $share)
    {
        [$page, $release] = $this->find($code, $share);

        try {
            DB::table('release_share_pages')->where('id', $page->id)->increment('views');
        } catch (\Throwable $e) {
            // counting a visit must never break the page
        }

        $links = $share->finalLinks((int) $release->id, $page);
        $url = $share->publicUrl((string) $page->code);
        $title = trim((string) $release->title);
        $artist = trim((string) $release->primary_artist_name);
        $names = array_column($links, 'label');

        return response()
            ->view('share.release', [
                'title' => $title,
                'artist' => $artist,
                'brand' => $share->brandName($release),
                'links' => $links,
                'url' => $url,
                'poster' => $share->hasPoster($release) ? $url.'/poster' : null,
                'description' => $names ? 'Out now. Listen on '.implode(', ', array_slice($names, 0, 5)).'.' : 'Out now.',
                'shareText' => trim($title.($artist !== '' ? ' - '.$artist : '')).' | Out now',
            ])
            ->header('Cache-Control', 'public, max-age=120')
            ->header('X-Robots-Tag', 'index, follow');
    }

    public function poster(string $code, ReleaseShareService $share)
    {
        [, $release] = $this->find($code, $share);
        $file = $share->posterFile($release);
        abort_unless($file && is_file($file), 404);

        return response()->file($file, [
            'Content-Type' => str_ends_with(strtolower($file), '.png') ? 'image/png' : 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
