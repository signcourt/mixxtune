<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * MIXXTUNE_CATALOGUE_HEALTH_V1
 *
 * Super Admin, read only: what is missing in the catalogue (poster, UPC,
 * label, date, genre, tracks, ISRC, audio) and on labels (revenue %,
 * owner). Each check opens a list with an edit link and a CSV download.
 * Nothing is changed here.
 */
class CatalogueHealthController extends Controller
{
    private const CHECKS = [
        'no_poster' => ['group' => 'Releases', 'label' => 'No poster', 'kind' => 'release'],
        'no_upc' => ['group' => 'Releases', 'label' => 'No UPC', 'kind' => 'release'],
        'no_label' => ['group' => 'Releases', 'label' => 'Not linked to a label', 'kind' => 'release'],
        'no_date' => ['group' => 'Releases', 'label' => 'No release date', 'kind' => 'release'],
        'no_genre' => ['group' => 'Releases', 'label' => 'No genre', 'kind' => 'release'],
        'no_tracks' => ['group' => 'Releases', 'label' => 'No tracks', 'kind' => 'release'],
        'track_no_isrc' => ['group' => 'Tracks', 'label' => 'Track without ISRC', 'kind' => 'track'],
        'track_no_audio' => ['group' => 'Tracks', 'label' => 'Track without audio file', 'kind' => 'track'],
        'label_no_percent' => ['group' => 'Labels', 'label' => 'Label without revenue %', 'kind' => 'label'],
        'label_no_owner' => ['group' => 'Labels', 'label' => 'Label without owner login', 'kind' => 'label'],
    ];

    private function blank(Builder $q, string $column): Builder
    {
        return $q->where(function ($w) use ($column) {
            $w->whereNull($column)->orWhere($column, '');
        });
    }

    private function releases(): Builder
    {
        return DB::table('releases')
            ->whereNull('releases.deleted_at')
            ->where('releases.status', '!=', 'draft');
    }

    private function query(string $check): Builder
    {
        $kind = self::CHECKS[$check]['kind'];

        if ($kind === 'release') {
            $q = $this->releases()
                ->leftJoin('labels', 'labels.id', '=', 'releases.label_id')
                ->select([
                    'releases.id', 'releases.title', 'releases.primary_artist_name as artist', 'releases.upc',
                    'releases.catalog_number', 'labels.name as label_name', 'releases.status',
                ])
                ->orderBy('releases.id');

            return match ($check) {
                'no_poster' => $this->blank($q, 'releases.artwork_path'),
                'no_upc' => $this->blank($q, 'releases.upc'),
                'no_label' => $q->whereNull('releases.label_id'),
                'no_date' => $q->whereNull('releases.digital_release_date'),
                'no_genre' => $this->blank($q, 'releases.primary_genre'),
                'no_tracks' => $q->whereNotExists(function ($s) {
                    $s->selectRaw('1')->from('tracks')
                        ->whereColumn('tracks.release_id', 'releases.id')
                        ->whereNull('tracks.deleted_at');
                }),
            };
        }

        if ($kind === 'track') {
            $q = DB::table('tracks')
                ->join('releases', 'releases.id', '=', 'tracks.release_id')
                ->whereNull('tracks.deleted_at')
                ->whereNull('releases.deleted_at')
                ->where('releases.status', '!=', 'draft')
                ->select([
                    'tracks.id as track_id', 'tracks.title as track_title', 'tracks.isrc',
                    'releases.id', 'releases.title', 'releases.upc', 'releases.status',
                ])
                ->orderBy('tracks.id');

            return $check === 'track_no_isrc'
                ? $this->blank($q, 'tracks.isrc')
                : $this->blank($q, 'tracks.audio_path');
        }

        $q = DB::table('labels')
            ->whereNull('labels.deleted_at')
            ->leftJoin('users', 'users.id', '=', 'labels.user_id')
            ->select([
                'labels.id', 'labels.name', 'labels.revenue_share_percentage as percent',
                'users.name as owner_name', 'users.email as owner_email', 'labels.status',
            ])
            ->selectSub(function ($s) {
                $s->selectRaw('COUNT(*)')->from('releases')
                    ->whereColumn('releases.label_id', 'labels.id')
                    ->whereNull('releases.deleted_at');
            }, 'release_count')
            ->orderBy('labels.name');

        return $check === 'label_no_percent'
            ? $q->where(function ($w) {
                $w->whereNull('labels.revenue_share_percentage')->orWhere('labels.revenue_share_percentage', '<=', 0);
            })
            : $q->whereNull('labels.user_id');
    }

    private function link(string $kind, object $row): string
    {
        return $kind === 'label'
            ? '/v2/admin/labels?search='.rawurlencode((string) $row->name)
            : '/v2/releases/'.$row->id.'/edit';
    }

    public function index(Request $request): Response
    {
        $counts = [];

        foreach (self::CHECKS as $key => $meta) {
            $counts[] = $meta + ['key' => $key, 'count' => $this->query($key)->count()];
        }

        $check = (string) $request->query('check', '');
        $list = null;

        if (isset(self::CHECKS[$check])) {
            $kind = self::CHECKS[$check]['kind'];
            $page = $this->query($check)->paginate(50)->withQueryString();
            $page->getCollection()->transform(function ($row) use ($kind) {
                $row->edit_url = $this->link($kind, $row);

                return $row;
            });
            $list = ['check' => $check, 'kind' => $kind, 'label' => self::CHECKS[$check]['label'], 'rows' => $page];
        }

        return Inertia::render('V2/Admin/CatalogueHealth/Index', [
            'checks' => $counts,
            'list' => $list,
            'totals' => [
                'releases' => $this->releases()->count(),
                'labels' => DB::table('labels')->whereNull('deleted_at')->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $check = (string) $request->query('check', '');

        abort_unless(isset(self::CHECKS[$check]), 404);

        $kind = self::CHECKS[$check]['kind'];
        $query = $this->query($check);

        $headers = match ($kind) {
            'release' => ['Release ID', 'Title', 'Artist', 'UPC', 'Catalogue Number', 'Label', 'Status'],
            'track' => ['Track ID', 'Track Title', 'ISRC', 'Release ID', 'Release Title', 'UPC', 'Status'],
            'label' => ['Label ID', 'Label', 'Revenue %', 'Owner', 'Owner E-mail', 'Status', 'Releases'],
        };

        return response()->streamDownload(function () use ($query, $kind, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            $code = fn ($v) => trim((string) $v) !== '' ? "\t".trim((string) $v) : '';

            foreach ($query->cursor() as $r) {
                fputcsv($out, match ($kind) {
                    'release' => [$r->id, $r->title, $r->artist, $code($r->upc), $r->catalog_number, $r->label_name, $r->status],
                    'track' => [$r->track_id, $r->track_title, $code($r->isrc), $r->id, $r->title, $code($r->upc), $r->status],
                    'label' => [$r->id, $r->name, $r->percent, $r->owner_name, $r->owner_email, $r->status, $r->release_count],
                });
            }

            fclose($out);
        }, 'catalogue-health-'.$check.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
