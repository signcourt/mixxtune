<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\AuditLogService;
use App\Services\V2\DistributorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * W221 - the distributor's own "My Brand" page: panel name, small line under it, logo.
 * Values only - no HTML / CSS / script / SVG can be uploaded or typed. The logo is re-drawn on the
 * server (GD) into a clean PNG, so nothing but pixels is stored.
 */
class DistributorBrandController extends Controller
{
    private function owner(Request $request, DistributorService $service)
    {
        $d = $service->forUser($request->user());
        abort_unless($d && $service->isOwner($request->user(), $d), 403, 'This page is for the distributor account owner.');
        abort_if((string) $d->status !== 'active', 403, 'Your distributor brand is switched off. Write to Mixx Tune support.');

        return $d;
    }

    public function show(Request $request, DistributorService $service): Response
    {
        $d = $this->owner($request, $service);
        $label = DB::table('labels')->where('id', $d->label_id)->first();

        return Inertia::render('V2/Distributor/Brand', [
            'role' => 'label',
            'distributor' => [
                'brand_name' => $d->brand_name,
                'dashboard_name' => $d->dashboard_name,
                'logo_url' => $service->logoUrl($d),
                'theme' => (string) ($d->theme ?? ''), /* W242 */
                'themes' => DistributorService::THEMES,
                'label_name' => $label->name ?? '',
                'slug' => $d->slug,
                'url' => 'https://'.\App\Support\DistributorHost::hostOf((string) $d->slug), /* W230 */
                'url_ready' => (bool) $d->host_verified_at,
                'sub_labels' => (int) DB::table('labels')->where('parent_label_id', $d->label_id)->whereNull('deleted_at')->count(),
            ],
        ]);
    }

    public function save(Request $request, DistributorService $service): RedirectResponse
    {
        $d = $this->owner($request, $service);

        $data = $request->validate([
            'brand_name' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[^<>{}\\\\]+$/u'],
            'dashboard_name' => ['nullable', 'string', 'max:60', 'regex:/^[^<>{}\\\\]*$/u'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:3072'],
            'remove_logo' => ['nullable', 'boolean'],
            'theme' => ['nullable', 'string', 'in:'.implode(',', DistributorService::THEMES)], /* W242 */
        ], [
            'brand_name.regex' => 'Plain text only.',
            'dashboard_name.regex' => 'Plain text only.',
            'logo.mimes' => 'Upload a PNG, JPG or WEBP picture (no SVG).',
            'logo.max' => 'The logo must be under 3 MB.',
        ]);

        $old = $d->only(['brand_name', 'dashboard_name', 'logo_path']);
        $d->brand_name = trim($data['brand_name']);
        $d->dashboard_name = trim((string) ($data['dashboard_name'] ?? '')) ?: null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('distributors', 'theme')) { /* W242 theme saved */
            $d->theme = ! empty($data['theme']) ? (string) $data['theme'] : null;
        }

        if ($request->hasFile('logo')) {
            $png = $this->cleanPng($request->file('logo')->getRealPath());
            if ($png === null) {
                return back()->withErrors(['logo' => 'That picture could not be read. Try a normal PNG or JPG.']);
            }
            $path = 'distributors/'.$d->id.'/logo-'.Str::lower(Str::random(16)).'.png';
            Storage::disk('public')->put($path, $png);
            $d->logo_path = $path; /* the old file stays on disk - nothing is deleted */
        } elseif (!empty($data['remove_logo'])) {
            $d->logo_path = null;
        }

        $d->updated_by = $request->user()->id;
        $d->save();
        DistributorService::forget();

        try {
            app(AuditLogService::class)->record('distributor.brand_changed', 'labels', 'Distributor #'.$d->id.' ('.$d->slug.') changed his brand: "'.$d->brand_name.'".', $old, $d->only(['brand_name', 'dashboard_name', 'logo_path']), $d, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Brand saved. Your accounts see the new name and logo from the next page they open.');
    }

    /** decode with GD and re-encode as PNG (max 800px wide) - drops metadata and anything that is not pixels */
    private function cleanPng(string $file): ?string
    {
        try {
            if (!function_exists('imagecreatefromstring')) {
                return null;
            }
            $raw = @file_get_contents($file);
            $img = $raw !== false ? @imagecreatefromstring($raw) : false;
            if (!$img) {
                return null;
            }
            $w = imagesx($img);
            $h = imagesy($img);
            if ($w < 16 || $h < 16 || $w > 6000 || $h > 6000) {
                return null;
            }
            $scale = min(1, 800 / $w, 800 / $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $out = imagecreatetruecolor($nw, $nh);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagepng($out, null, 6);
            $png = ob_get_clean();

            return $png ?: null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
