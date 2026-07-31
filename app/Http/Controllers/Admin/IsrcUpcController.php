<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class IsrcUpcController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->string('tab')->toString() === 'upc'
            ? 'upc'
            : 'isrc';

        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $tracksQuery = Track::query()
            ->with([
                'release:id,title,catalog_number,upc,label_id',
                'release.label:id,name',
            ]);

        $releasesQuery = Release::query()
            ->withCount('tracks')
            ->with([
                'label:id,name',
            ]);

        if ($search !== '') {
            $tracksQuery->where(function ($query) use ($search) {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('primary_artist_name', 'like', "%{$search}%")
                    ->orWhere('isrc', 'like', "%{$search}%")
                    ->orWhereHas('release', function ($releaseQuery) use ($search) {
                        $releaseQuery
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('catalog_number', 'like', "%{$search}%")
                            ->orWhere('upc', 'like', "%{$search}%");
                    });
            });

            $releasesQuery->where(function ($query) use ($search) {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('primary_artist_name', 'like', "%{$search}%")
                    ->orWhere('catalog_number', 'like', "%{$search}%")
                    ->orWhere('upc', 'like', "%{$search}%");
            });
        }

        if ($status === 'pending') {
            $tracksQuery->where(function ($query) {
                $query
                    ->whereNull('isrc')
                    ->orWhere('isrc', '');
            });

            $releasesQuery->where(function ($query) {
                $query
                    ->whereNull('upc')
                    ->orWhere('upc', '');
            });
        }

        if ($status === 'assigned') {
            $tracksQuery
                ->whereNotNull('isrc')
                ->where('isrc', '!=', '');

            $releasesQuery
                ->whereNotNull('upc')
                ->where('upc', '!=', '');
        }

        return Inertia::render('Admin/IsrcUpc/Index', [
            'tab' => $tab,

            'tracks' => $tracksQuery
                ->latest('updated_at')
                ->paginate(20, ['*'], 'track_page')
                ->withQueryString(),

            'releases' => $releasesQuery
                ->latest('updated_at')
                ->paginate(20, ['*'], 'release_page')
                ->withQueryString(),

            'filters' => [
                'tab' => $tab,
                'status' => $status,
                'search' => $search,
            ],

            'counts' => [
                'tracks_total' => Track::query()->count(),

                'isrc_assigned' => Track::query()
                    ->whereNotNull('isrc')
                    ->where('isrc', '!=', '')
                    ->count(),

                'isrc_pending' => Track::query()
                    ->where(function ($query) {
                        $query
                            ->whereNull('isrc')
                            ->orWhere('isrc', '');
                    })
                    ->count(),

                'releases_total' => Release::query()->count(),

                'upc_assigned' => Release::query()
                    ->whereNotNull('upc')
                    ->where('upc', '!=', '')
                    ->count(),

                'upc_pending' => Release::query()
                    ->where(function ($query) {
                        $query
                            ->whereNull('upc')
                            ->orWhere('upc', '');
                    })
                    ->count(),
            ],
        ]);
    }

    public function updateTrack(Request $request, Track $track)
    {
        $validated = $request->validate([
            'isrc' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tracks', 'isrc')->ignore($track->id),
            ],
        ]);

        $isrc = $this->normaliseIsrc($validated['isrc']);

        if (!preg_match('/^[A-Z]{2}[A-Z0-9]{3}[0-9]{7}$/', $isrc)) {
            return back()->withErrors([
                'isrc' => 'Use a valid 12-character ISRC, for example INMTX2600001.',
            ]);
        }

        $track->update([
            'isrc' => $isrc,
            'isrc_is_auto_generated' => false,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'ISRC updated successfully.');
    }

    public function updateRelease(Request $request, Release $release)
    {
        $validated = $request->validate([
            'upc' => [
                'required',
                'digits:12',
                Rule::unique('releases', 'upc')->ignore($release->id),
            ],
        ]);

        if (!$this->isValidUpc($validated['upc'])) {
            return back()->withErrors([
                'upc' => 'UPC check digit is invalid.',
            ]);
        }

        $release->update([
            'upc' => $validated['upc'],
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'UPC updated successfully.');
    }

    public function bulkGenerateIsrc(Request $request)
    {
        $validated = $request->validate([
            'track_ids' => ['required', 'array', 'min:1'],
            'track_ids.*' => ['integer', 'exists:tracks,id'],
        ]);

        $tracks = Track::query()
            ->whereIn('id', $validated['track_ids'])
            ->get();

        DB::transaction(function () use ($tracks) {
            foreach ($tracks as $track) {
                if (!empty($track->isrc)) {
                    continue;
                }

                $track->update([
                    'isrc' => $this->generateUniqueIsrc($track),
                    'isrc_is_auto_generated' => true,
                    'updated_by' => auth()->id(),
                ]);
            }
        });

        return back()->with(
            'success',
            'ISRCs generated for selected pending tracks.'
        );
    }

    public function bulkGenerateUpc(Request $request)
    {
        $validated = $request->validate([
            'release_ids' => ['required', 'array', 'min:1'],
            'release_ids.*' => ['integer', 'exists:releases,id'],
        ]);

        $releases = Release::query()
            ->whereIn('id', $validated['release_ids'])
            ->get();

        DB::transaction(function () use ($releases) {
            foreach ($releases as $release) {
                if (!empty($release->upc)) {
                    continue;
                }

                $release->update([
                    'upc' => $this->generateUniqueUpc($release),
                    'updated_by' => auth()->id(),
                ]);
            }
        });

        return back()->with(
            'success',
            'UPCs generated for selected pending releases.'
        );
    }

    private function normaliseIsrc(string $isrc): string
    {
        return strtoupper(
            preg_replace('/[^A-Za-z0-9]/', '', $isrc)
        );
    }

    private function generateUniqueIsrc(Track $track): string
    {
        $year = now()->format('y');
        $sequence = max(1, (int) $track->id);

        do {
            $designation = str_pad(
                (string) ($sequence % 100000),
                5,
                '0',
                STR_PAD_LEFT
            );

            $isrc = "INMTX{$year}{$designation}";
            $sequence++;
        } while (
            Track::query()
                ->where('isrc', $isrc)
                ->whereKeyNot($track->id)
                ->exists()
        );

        return $isrc;
    }

    private function generateUniqueUpc(Release $release): string
    {
        $sequence = max(1, (int) $release->id);

        do {
            // 890 prefix + 8-digit release sequence = 11 digits.
            $base = '890'.str_pad(
                (string) ($sequence % 100000000),
                8,
                '0',
                STR_PAD_LEFT
            );

            $upc = $base.$this->calculateUpcCheckDigit($base);
            $sequence++;
        } while (
            Release::query()
                ->where('upc', $upc)
                ->whereKeyNot($release->id)
                ->exists()
        );

        return $upc;
    }

    private function calculateUpcCheckDigit(string $base): int
    {
        $sum = 0;

        for ($index = 0; $index < 11; $index++) {
            $digit = (int) $base[$index];
            $sum += $index % 2 === 0 ? $digit * 3 : $digit;
        }

        return (10 - ($sum % 10)) % 10;
    }

    private function isValidUpc(string $upc): bool
    {
        return strlen($upc) === 12
            && $this->calculateUpcCheckDigit(substr($upc, 0, 11))
                === (int) $upc[11];
    }
}
