<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** W238 - "Promo kit" of a release: its public listen page, the store links and the share picture. */
class ReleasePromoController extends Controller
{
    public function show(Request $request, Release $release, ReleaseAccessService $access, ReleaseShareService $share, PermissionService $permissions): Response
    {
        $access->authorizeView($request->user(), $release);

        $page = $share->page((int) $release->id);
        $manual = $share->manualLinks($page);
        $auto = $share->autoLinks((int) $release->id);
        $stores = [];
        foreach (ReleaseShareService::STORES as $key => $label) {
            $stores[] = ['key' => $key, 'label' => $label, 'url' => $manual[$key] ?? '', 'auto_url' => $auto[$key] ?? ''];
        }

        return Inertia::render('V2/Releases/Promo', [
            'role' => $permissions->role($request->user()),
            'release' => [
                'id' => (int) $release->id,
                'title' => (string) $release->title,
                'artist' => (string) $release->primary_artist_name,
                'status' => (string) $release->status,
                'has_poster' => $share->hasPoster($release),
                'poster_url' => '/releases/'.$release->id.'/promo/poster',
            ],
            'can_find' => in_array($permissions->role($request->user()), ['super_admin', 'admin'], true) && \App\Services\V2\ReleaseLinkAgent::ready(), /* W239 */
            'ready' => ReleaseShareService::ready(),
            'eligible' => $share->eligible($release),
            'brand_name' => $share->brandName($release),
            'page' => $page ? [
                'enabled' => (bool) $page->enabled,
                'url' => $share->publicUrl((string) $page->code),
                'views' => (int) $page->views,
            ] : null,
            'stores' => $stores,
        ]);
    }

    /** the web-sized poster for the panel preview and the share picture (signed-in viewers of this release only) */
    public function poster(Request $request, Release $release, ReleaseAccessService $access, ReleaseShareService $share)
    {
        $access->authorizeView($request->user(), $release);
        $file = $share->posterFile($release);
        abort_unless($file && is_file($file), 404);

        return response()->file($file, [
            'Content-Type' => str_ends_with(strtolower($file), '.png') ? 'image/png' : 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function save(Request $request, Release $release, ReleaseAccessService $access, ReleaseShareService $share, AuditLogService $audit): RedirectResponse
    {
        $access->authorizeView($request->user(), $release);
        abort_unless(ReleaseShareService::ready(), 503, 'The promo kit is not set up yet.');

        if (! $share->eligible($release)) {
            return back()->withErrors(['promo' => 'The promo page opens once the release is live in stores.']);
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'links' => ['nullable', 'array'],
            'links.*' => ['nullable', 'string', 'max:500'],
        ]);

        $links = [];
        $bad = [];
        foreach ((array) ($data['links'] ?? []) as $key => $value) {
            if (! isset(ReleaseShareService::STORES[$key]) || trim((string) $value) === '') {
                continue;
            }
            $clean = $share->cleanUrl($value);
            if ($clean === null) {
                $bad[] = ReleaseShareService::STORES[$key];

                continue;
            }
            $links[$key] = $clean;
        }
        if ($bad) {
            return back()->withErrors(['promo' => 'This is not a proper link: '.implode(', ', $bad).'. Paste the full address from the store.'])->withInput();
        }

        $page = $share->page((int) $release->id);
        $now = now();
        if ($page) {
            DB::table('release_share_pages')->where('id', $page->id)->update([
                'enabled' => (bool) $data['enabled'],
                'links' => json_encode($links),
                'updated_at' => $now,
            ]);
        } else {
            DB::table('release_share_pages')->insert([
                'release_id' => (int) $release->id,
                'code' => $share->newCode(),
                'enabled' => (bool) $data['enabled'],
                'links' => json_encode($links),
                'views' => 0,
                'created_by' => (int) $request->user()->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        try {
            $audit->record('release.promo_saved', 'releases', 'Promo page of release #'.$release->id.' "'.$release->title.'" saved ('.($data['enabled'] ? 'on' : 'off').', '.count($links).' own link(s)).', [], ['enabled' => (bool) $data['enabled'], 'links' => array_keys($links)], $release, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', $data['enabled'] ? 'Promo page saved. It is public now.' : 'Promo page saved and switched off.');
    }
}
