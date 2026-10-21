<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\ReleaseAdminEditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * MIXXTUNE_BULK_POSTERS_V1
 *
 * Super Admin: add posters to many releases at once. Each image file is
 * named after the release's UPC or catalogue number. Only an exact match
 * is used (never a guess); drafts are never touched; an existing poster
 * is kept unless "replace" is chosen. Old files stay on disk, every save
 * is written to the audit log and the All Catalogue record is re-synced.
 */
class BulkPosterController extends Controller
{
    public function __construct(
        private readonly ReleaseAdminEditService $adminEdit
    ) {
    }

    private function base()
    {
        return Release::query()->where('releases.status', '!=', 'draft');
    }

    private function missing($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('releases.artwork_path')->orWhere('releases.artwork_path', '');
        });
    }

    public function index(): Response
    {
        $total = $this->base()->count();
        $without = $this->missing($this->base())->count();

        return Inertia::render('V2/Admin/BulkPosters/Index', [
            'counts' => [
                'total' => $total,
                'with' => $total - $without,
                'without' => $without,
            ],
            'limits' => [
                'max_kb' => 20480,
                'min_px' => 500,
                'store_px' => 3000,
            ],
        ]);
    }

    public function missingCsv(): StreamedResponse
    {
        $query = $this->missing($this->base())
            ->leftJoin('labels', 'labels.id', '=', 'releases.label_id')
            ->orderBy('releases.id')
            ->select([
                'releases.id', 'releases.upc', 'releases.catalog_number', 'releases.title',
                'releases.primary_artist_name', 'labels.name as label_name', 'releases.status',
                'releases.digital_release_date',
            ]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Release ID', 'UPC', 'Catalogue Number', 'Title', 'Artist', 'Label', 'Status', 'Release Date', 'Poster file name to use']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    $upc = trim((string) $row->upc);
                    fputcsv($out, [
                        $row->id,
                        $upc !== '' ? "\t".$upc : '',
                        $row->catalog_number,
                        $row->title,
                        $row->primary_artist_name,
                        $row->label_name,
                        $row->status,
                        $row->digital_release_date ? substr((string) $row->digital_release_date, 0, 10) : '',
                        ($upc !== '' ? $upc : $row->catalog_number).'.jpg',
                    ]);
                }
            });

            fclose($out);
        }, 'releases-without-poster-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Check file names only (nothing is saved).
     */
    public function match(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'names' => ['required', 'array', 'max:1000'],
            'names.*' => ['required', 'string', 'max:255'],
            'replace' => ['nullable', 'boolean'],
        ]);

        $replace = (bool) ($validated['replace'] ?? false);

        $results = [];

        foreach ($validated['names'] as $name) {
            $results[] = $this->describe($name, $replace);
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Save one poster file.
     */
    public function upload(Request $request): JsonResponse
    {
        $replace = $request->boolean('replace');

        $file = $request->file('poster');

        $name = $file ? $file->getClientOriginalName() : '';

        $check = $this->describe($name, $replace);

        if ($check['result'] !== 'ready') {
            return response()->json($check);
        }

        $validator = Validator::make($request->all(), [
            'poster' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:20480', 'dimensions:min_width=500,min_height=500'],
        ], [
            'poster.dimensions' => 'Image must be at least 500 × 500 pixels.',
            'poster.mimes' => 'Only JPG or PNG images.',
            'poster.max' => 'Image must be 20 MB or smaller.',
        ]);

        if ($validator->fails()) {
            return response()->json(array_merge($check, [
                'result' => 'invalid',
                'message' => $validator->errors()->first('poster'),
            ]));
        }

        $release = Release::query()->findOrFail($check['release']['id']);

        return DB::transaction(function () use ($release, $file, $replace, $request, $check) {
            $release = Release::query()->lockForUpdate()->findOrFail($release->id);

            $old = (string) ($release->artwork_path ?? '');

            if ($old !== '' && !$replace) {
                return response()->json(array_merge($check, [
                    'result' => 'has_poster',
                    'message' => 'Already has a poster (kept).',
                ]));
            }

            $path = $file->store('releases/artwork', 'public');

            $release->forceFill(['artwork_path' => $path])->save();

            $this->adminEdit->record(
                $release,
                'poster (bulk upload, file '.$file->getClientOriginalName().')',
                ['artwork_path' => $old !== '' ? $old : null],
                ['artwork_path' => $path],
                $request
            );

            [$w, $h] = @getimagesize($file->getRealPath()) ?: [0, 0];

            $note = ($w === 3000 && $h === 3000)
                ? ''
                : ' Note: '.$w.' × '.$h.' px; store delivery needs 3000 × 3000.';

            return response()->json(array_merge($check, [
                'result' => 'saved',
                'message' => ($old !== '' ? 'Poster replaced.' : 'Poster saved.').$note,
                'artwork_path' => $path,
            ]));
        });
    }

    /**
     * Which release a file name points to, by exact UPC or catalogue number.
     */
    private function describe(string $name, bool $replace): array
    {
        $base = ['file' => $name, 'release' => null];

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return $base + ['result' => 'invalid', 'message' => 'Only .jpg, .jpeg or .png files.'];
        }

        $stem = trim(pathinfo($name, PATHINFO_FILENAME));

        if ($stem === '') {
            return $base + ['result' => 'not_found', 'message' => 'File name is empty.'];
        }

        $release = null;
        $by = null;

        $digits = preg_replace('/[\s-]+/', '', $stem);

        if (preg_match('/^\d{11,14}$/', $digits)) {
            $variants = array_values(array_unique(array_filter([
                $digits,
                ltrim($digits, '0'),
                '0'.$digits,
                '00'.$digits,
            ], fn ($v) => $v !== '' && strlen($v) <= 14)));

            $found = Release::query()->whereIn('upc', $variants)->get();

            if ($found->count() > 1) {
                return $base + ['result' => 'ambiguous', 'message' => 'More than one release has this UPC (with / without leading 0). Use the catalogue number instead.'];
            }

            $release = $found->first();
            $by = $release ? 'UPC' : null;
        }

        if (!$release) {
            $found = Release::query()->whereRaw('LOWER(catalog_number) = ?', [mb_strtolower($stem)])->get();

            if ($found->count() > 1) {
                return $base + ['result' => 'ambiguous', 'message' => 'More than one release has this catalogue number.'];
            }

            $release = $found->first();
            $by = $release ? 'catalogue number' : null;
        }

        if (!$release) {
            return $base + ['result' => 'not_found', 'message' => 'No release has this UPC or catalogue number.'];
        }

        $info = [
            'id' => $release->id,
            'title' => $release->title,
            'artist' => $release->primary_artist_name,
            'upc' => $release->upc,
            'catalog_number' => $release->catalog_number,
            'status' => $release->status,
            'has_poster' => trim((string) $release->artwork_path) !== '',
            'matched_by' => $by,
        ];

        if ($release->status === 'draft') {
            return ['file' => $name, 'release' => $info, 'result' => 'draft', 'message' => 'Release is a draft (owner is still working on it); not changed.'];
        }

        if ($info['has_poster'] && !$replace) {
            return ['file' => $name, 'release' => $info, 'result' => 'has_poster', 'message' => 'Already has a poster (kept). Tick "Replace existing posters" to change it.'];
        }

        return [
            'file' => $name,
            'release' => $info,
            'result' => 'ready',
            'message' => $info['has_poster'] ? 'Will replace the current poster.' : 'Will add the poster.',
        ];
    }
}
