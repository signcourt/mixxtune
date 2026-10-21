<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\AuditLogService;
use App\Services\V2\CatalogueOwnershipService;
use App\Services\V2\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/*
 * MIXXTUNE_OWNERSHIP_SAFE_V1 (W56)
 *
 * Super Admin moves a release / track to another owner, or gives a label /
 * artist to another login. The page searches on the server (the whole
 * catalogue, not only the newest 300), shows a preview of what will move,
 * needs a reason and the e-mailed confirmation code, and writes the audit
 * log. History shows names instead of raw ids.
 */
class OwnershipTransferController extends Controller
{
    private const TYPE_LABELS = [
        'release_owner_transfer' => 'Release → new owner',
        'track_release_transfer' => 'Track → other release',
        'label_user_transfer' => 'Label → new login',
        'artist_user_transfer' => 'Artist → new login',
    ];

    public function index(Request $request, PermissionService $permissions): Response
    {
        $this->authorizeSuperAdmin($request, $permissions);

        $type = (string) $request->query('type', '');
        $q = trim((string) $request->query('q', ''));

        $history = DB::table('catalogue_transfers as ct')
            ->leftJoin('users as by_user', 'by_user.id', '=', 'ct.transferred_by')
            ->when(isset(self::TYPE_LABELS[$type]), fn ($query) => $query->where('ct.transfer_type', $type))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
                $query->where(function ($w) use ($like, $q) {
                    $w->whereRaw("ct.reason LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("ct.public_id LIKE ? ESCAPE '!'", [$like]);
                    if (ctype_digit($q)) {
                        $w->orWhere('ct.entity_id', (int) $q);
                    }
                });
            })
            ->orderByDesc('ct.id')
            ->select(['ct.*', 'by_user.name as by_name', 'by_user.email as by_email'])
            ->paginate(25)
            ->withQueryString();

        $history->setCollection($this->describe($history->getCollection()));

        return Inertia::render('V2/Admin/Ownership/Index', [
            'history' => $history,
            'filters' => ['type' => $type, 'q' => $q],
            'types' => self::TYPE_LABELS,
            'section' => (string) $request->query('section', ''),
        ]);
    }

    /* Search the whole catalogue / logins (max 20 results). */
    public function search(Request $request, PermissionService $permissions): JsonResponse
    {
        $this->authorizeSuperAdmin($request, $permissions);

        $type = (string) $request->query('type', '');
        $q = trim((string) $request->query('q', ''));
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
        $limit = 20;

        $results = match ($type) {
            'release' => DB::table('releases as r')
                ->leftJoin('labels as l', 'l.id', '=', 'r.label_id')
                ->leftJoin('artists as a', 'a.id', '=', 'r.artist_id')
                ->whereNull('r.deleted_at')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                    ->whereRaw("r.title LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("r.upc LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("r.catalog_number LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("r.primary_artist_name LIKE ? ESCAPE '!'", [$like])))
                ->orderByDesc('r.id')->limit($limit)
                ->get(['r.id', 'r.title', 'r.upc', 'r.status', 'r.primary_artist_name', 'l.name as label_name', 'a.stage_name as artist_name', 'r.label_id'])
                ->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'title' => $r->title ?: 'Untitled release',
                    'sub' => trim(($r->primary_artist_name ?: $r->artist_name ?: '—').' · UPC '.($r->upc ?: '—').' · '.($r->label_id ? 'Label: '.$r->label_name : 'Artist owned').' · '.$r->status),
                ]),
            'track' => DB::table('tracks as t')
                ->leftJoin('releases as r', 'r.id', '=', 't.release_id')
                ->whereNull('t.deleted_at')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                    ->whereRaw("t.title LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("t.isrc LIKE ? ESCAPE '!'", [$like])))
                ->orderByDesc('t.id')->limit($limit)
                ->get(['t.id', 't.title', 't.isrc', 't.track_number', 'r.title as release_title', 'r.id as release_id'])
                ->map(fn ($t) => [
                    'id' => (int) $t->id,
                    'title' => $t->title ?: 'Untitled track',
                    'sub' => 'ISRC '.($t->isrc ?: '—').' · track '.$t->track_number.' of "'.($t->release_title ?: '—').'"',
                    'release_id' => (int) $t->release_id,
                ]),
            'label' => DB::table('labels as l')
                ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
                ->whereNull('l.deleted_at')
                ->when($q !== '', fn ($w) => $w->whereRaw("l.name LIKE ? ESCAPE '!'", [$like]))
                ->orderBy('l.name')->limit($limit)
                ->get(['l.id', 'l.name', 'u.email', 'l.revenue_share_percentage'])
                ->map(fn ($l) => [
                    'id' => (int) $l->id,
                    'title' => $l->name,
                    'sub' => 'Login: '.($l->email ?: 'none').' · '.($l->revenue_share_percentage === null ? '% not set' : rtrim(rtrim(number_format((float) $l->revenue_share_percentage, 2), '0'), '.').'%'),
                ]),
            'artist' => DB::table('artists as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->leftJoin('labels as l', 'l.id', '=', 'a.label_id')
                ->whereNull('a.deleted_at')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                    ->whereRaw("a.stage_name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("a.legal_name LIKE ? ESCAPE '!'", [$like])))
                ->orderBy('a.stage_name')->limit($limit)
                ->get(['a.id', 'a.stage_name', 'a.legal_name', 'u.email', 'l.name as label_name'])
                ->map(fn ($a) => [
                    'id' => (int) $a->id,
                    'title' => $a->stage_name ?: $a->legal_name ?: 'Artist #'.$a->id,
                    'sub' => 'Login: '.($a->email ?: 'none').' · Label: '.($a->label_name ?: 'none'),
                ]),
            'label_login', 'artist_login' => DB::table('users')
                ->where('role', $type === 'label_login' ? 'label' : 'artist')
                ->where('account_status', 'active')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like])))
                ->orderBy('name')->limit($limit)
                ->get(['id', 'name', 'email'])
                ->map(fn ($u) => ['id' => (int) $u->id, 'title' => $u->name ?: $u->email, 'sub' => $u->email]),
            default => collect(),
        };

        return response()->json(['results' => $results->values()]);
    }

    /* What will change - shown before the button is pressed. */
    public function preview(Request $request, PermissionService $permissions): JsonResponse
    {
        $this->authorizeSuperAdmin($request, $permissions);

        $kind = (string) $request->query('kind', '');
        $warnings = [];
        $lines = [];

        if ($kind === 'release') {
            $release = DB::table('releases')->where('id', (int) $request->query('release_id'))->whereNull('deleted_at')->first();
            if (!$release) {
                return response()->json(['ok' => false, 'message' => 'Release not found.'], 404);
            }
            $ownerType = (string) $request->query('owner_type');
            $ownerId = (int) $request->query('owner_id');
            $lines[] = 'Now: '.$this->releaseOwnerText($release);
            if ($ownerType === 'label') {
                $label = DB::table('labels')->where('id', $ownerId)->first();
                $lines[] = 'After: Label "'.($label->name ?? '?').'"'.($label && $label->revenue_share_percentage === null ? ' (its % is not set yet)' : ($label ? ' ('.rtrim(rtrim(number_format((float) $label->revenue_share_percentage, 2), '0'), '.').'%)' : ''));
                if ($label && $release->artist_id) {
                    $artistLabel = DB::table('artists')->where('id', $release->artist_id)->value('label_id');
                    if ($artistLabel && (int) $artistLabel !== $ownerId) {
                        $warnings[] = 'The release artist belongs to another label. The artist stays the same; only the owner label changes.';
                    }
                }
                if ((int) $release->label_id === $ownerId) {
                    $warnings[] = 'The release already belongs to this label.';
                }
            } elseif ($ownerType === 'artist') {
                $artist = DB::table('artists')->where('id', $ownerId)->first();
                $lines[] = 'After: owned directly by artist "'.($artist->stage_name ?? '?').'" (no label)';
                if ($release->label_id) {
                    $warnings[] = 'The release leaves its label - the label will no longer earn from it.';
                }
            }
            $lines[] = DB::table('tracks')->where('release_id', $release->id)->whereNull('deleted_at')->count().' track(s) go with it.';
            [$moving, $locked] = $this->rowCounts('release_id', (int) $release->id);
            $lines[] = 'Revenue rows: '.$moving.' still in review can move (option "pending + future"); '.$locked.' in approved / published reports never move.';
            if (in_array((string) $release->status, ['submitted', 'in_review', 'under_review', 'processing'], true)) {
                $warnings[] = 'This release is in review right now ('.$release->status.').';
            }
            if (in_array((string) $release->status, ['live', 'delivered', 'distributed'], true)) {
                $warnings[] = 'This release is live in stores. Store metadata (label name) is not re-sent automatically.';
            }
        } elseif ($kind === 'track') {
            $track = DB::table('tracks')->where('id', (int) $request->query('track_id'))->first();
            $target = DB::table('releases')->where('id', (int) $request->query('release_id'))->first();
            if (!$track || !$target) {
                return response()->json(['ok' => false, 'message' => 'Track or release not found.'], 404);
            }
            $from = DB::table('releases')->where('id', $track->release_id)->first();
            $lines[] = 'Now: track '.$track->track_number.' of "'.($from->title ?? '?').'" - '.($from ? $this->releaseOwnerText($from) : '');
            $clash = DB::table('tracks')->where('release_id', $target->id)->where('id', '!=', $track->id)->where('disc_number', $track->disc_number)->where('track_number', $track->track_number)->exists();
            $number = $clash ? (int) DB::table('tracks')->where('release_id', $target->id)->where('disc_number', $track->disc_number)->max('track_number') + 1 : (int) $track->track_number;
            $lines[] = 'After: track '.$number.' of "'.$target->title.'" - '.$this->releaseOwnerText($target);
            if ($clash) {
                $warnings[] = 'That release already has a track '.$track->track_number.', so this one becomes track '.$number.'.';
            }
            if ((int) $track->release_id === (int) $target->id) {
                $warnings[] = 'The track is already on this release.';
            }
            [$moving, $locked] = $this->rowCounts('track_id', (int) $track->id);
            $lines[] = 'Revenue rows: '.$moving.' still in review can move; '.$locked.' in approved / published reports never move.';
        } elseif (in_array($kind, ['label_login', 'artist_login'], true)) {
            $isLabel = $kind === 'label_login';
            $entity = DB::table($isLabel ? 'labels' : 'artists')->where('id', (int) $request->query($isLabel ? 'label_id' : 'artist_id'))->first();
            $user = DB::table('users')->where('id', (int) $request->query('user_id'))->first();
            if (!$entity || !$user) {
                return response()->json(['ok' => false, 'message' => 'Not found.'], 404);
            }
            $old = $entity->user_id ? DB::table('users')->where('id', $entity->user_id)->value('email') : null;
            $name = $isLabel ? $entity->name : ($entity->stage_name ?: $entity->legal_name);
            $lines[] = 'Now: "'.$name.'" is managed by '.($old ?: 'no login');
            $lines[] = 'After: managed by '.$user->email.' - this login will see its releases, statements and wallet money from now on.';
            if ($isLabel) {
                $lines[] = 'Money already in the old owner\'s wallet stays there. For a full owner change with a new %, use Accounts & Access → Labels → owner.';
            }
            if ((int) $entity->user_id === (int) $user->id) {
                $warnings[] = 'That login already manages it.';
            }
        } else {
            return response()->json(['ok' => false, 'message' => 'Unknown preview.'], 422);
        }

        return response()->json(['ok' => true, 'lines' => $lines, 'warnings' => $warnings]);
    }

    public function transferRelease(Request $request, PermissionService $permissions, CatalogueOwnershipService $ownership): RedirectResponse
    {
        $this->authorizeSuperAdmin($request, $permissions);

        $validated = $request->validate([
            'release_id' => ['required', 'integer', 'exists:releases,id'],
            'owner_type' => ['required', 'in:artist,label'],
            'owner_id' => ['required', 'integer', $request->input('owner_type') === 'artist' ? 'exists:artists,id' : 'exists:labels,id'],
            'scope' => ['required', 'in:future_only,pending_and_future'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], ['reason.required' => 'Write why this is moved (at least 5 letters).', 'reason.min' => 'Write why this is moved (at least 5 letters).']);

        $this->confirmCode($request, 'ownership.release:'.$validated['release_id'].':'.$validated['owner_type'].':'.$validated['owner_id'], 'move release #'.$validated['release_id'].' to another owner');

        $ownership->transferRelease((int) $validated['release_id'], $validated['owner_type'], (int) $validated['owner_id'], $validated['scope'], $validated['reason'], $request->user()->id);

        $this->audit('catalogue.release_owner_transferred', 'Release #'.$validated['release_id'].' moved to '.$validated['owner_type'].' #'.$validated['owner_id'].' ('.$validated['scope'].').', $validated);

        return back()->with('success', 'Release moved to the new owner.'.($validated['scope'] === 'pending_and_future' ? ' Revenue still in review now counts for the new owner.' : ' Only future reports count for the new owner.'));
    }

    public function transferTrack(Request $request, PermissionService $permissions, CatalogueOwnershipService $ownership): RedirectResponse
    {
        $this->authorizeSuperAdmin($request, $permissions);

        $validated = $request->validate([
            'track_id' => ['required', 'integer', 'exists:tracks,id'],
            'release_id' => ['required', 'integer', 'exists:releases,id'],
            'scope' => ['required', 'in:future_only,pending_and_future'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], ['reason.required' => 'Write why this is moved (at least 5 letters).', 'reason.min' => 'Write why this is moved (at least 5 letters).']);

        $this->confirmCode($request, 'ownership.track:'.$validated['track_id'].':'.$validated['release_id'], 'move track #'.$validated['track_id'].' to release #'.$validated['release_id']);

        $ownership->transferTrack((int) $validated['track_id'], (int) $validated['release_id'], $validated['scope'], $validated['reason'], $request->user()->id);

        $this->audit('catalogue.track_moved', 'Track #'.$validated['track_id'].' moved to release #'.$validated['release_id'].'.', $validated);

        return back()->with('success', 'Track moved to the other release.');
    }

    public function transferLabelUser(Request $request, PermissionService $permissions, CatalogueOwnershipService $ownership): RedirectResponse
    {
        $this->authorizeSuperAdmin($request, $permissions);

        $validated = $request->validate([
            'label_id' => ['required', 'integer', 'exists:labels,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], ['reason.required' => 'Write why the login changes (at least 5 letters).', 'reason.min' => 'Write why the login changes (at least 5 letters).']);

        $this->confirmCode($request, 'ownership.label:'.$validated['label_id'].':'.$validated['user_id'], 'give label #'.$validated['label_id'].' to another login');

        $ownership->transferLabelUser((int) $validated['label_id'], (int) $validated['user_id'], $validated['reason'], $request->user()->id);

        $this->audit('catalogue.label_login_changed', 'Label #'.$validated['label_id'].' now managed by user #'.$validated['user_id'].'.', $validated);

        return back()->with('success', 'The label is now managed by the new login.');
    }

    public function transferArtistUser(Request $request, PermissionService $permissions, CatalogueOwnershipService $ownership): RedirectResponse
    {
        $this->authorizeSuperAdmin($request, $permissions);

        $validated = $request->validate([
            'artist_id' => ['required', 'integer', 'exists:artists,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], ['reason.required' => 'Write why the login changes (at least 5 letters).', 'reason.min' => 'Write why the login changes (at least 5 letters).']);

        $this->confirmCode($request, 'ownership.artist:'.$validated['artist_id'].':'.$validated['user_id'], 'give artist #'.$validated['artist_id'].' to another login');

        $ownership->transferArtistUser((int) $validated['artist_id'], (int) $validated['user_id'], $validated['reason'], $request->user()->id);

        $this->audit('catalogue.artist_login_changed', 'Artist #'.$validated['artist_id'].' now managed by user #'.$validated['user_id'].'.', $validated);

        return back()->with('success', 'The artist is now managed by the new login.');
    }

    public function export(Request $request, PermissionService $permissions)
    {
        $this->authorizeSuperAdmin($request, $permissions);

        $rows = $this->describe(
            DB::table('catalogue_transfers as ct')
                ->leftJoin('users as by_user', 'by_user.id', '=', 'ct.transferred_by')
                ->orderByDesc('ct.id')
                ->limit(5000)
                ->get(['ct.*', 'by_user.name as by_name', 'by_user.email as by_email'])
        );

        $this->audit('catalogue.transfers_exported', 'Transfer history exported (CSV).', ['rows' => $rows->count()]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date (IST)', 'Type', 'What', 'From', 'To', 'Revenue', 'Reason', 'By']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['date'], $r['type'], $r['what'], $r['from'], $r['to'], $r['scope'], $r['reason'], $r['by']]);
            }
            fclose($out);
        }, 'ownership-transfers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ---------- helpers ---------- */

    private function confirmCode(Request $request, string $scope, string $what): void
    {
        app(ActionOtpGuard::class)->require($request->user(), $scope, $what);
    }

    private function audit(string $action, string $description, array $values): void
    {
        try {
            app(AuditLogService::class)->record($action, 'catalogue', $description, [], $values, null, request());
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function rowCounts(string $column, int $id): array
    {
        $base = DB::table('report_rows')->where($column, $id);
        $moving = (clone $base)->whereExists(function ($q) {
            $q->selectRaw('1')->from('report_imports as ri')->whereColumn('ri.id', 'report_rows.report_import_id')->where('ri.financial_status', 'review_pending');
        })->count();

        return [$moving, max(0, (clone $base)->count() - $moving)];
    }

    private function releaseOwnerText(object $release): string
    {
        if ($release->label_id) {
            return 'Label "'.(DB::table('labels')->where('id', $release->label_id)->value('name') ?? '?').'"';
        }

        return 'Artist "'.(DB::table('artists')->where('id', $release->artist_id)->value('stage_name') ?? '?').'" (no label)';
    }

    /* History rows with names (one query per kind, not per row). */
    private function describe($rows)
    {
        $decode = fn ($v) => is_array($v) ? $v : (json_decode((string) $v, true) ?: []);
        $ids = ['releases' => [], 'tracks' => [], 'labels' => [], 'artists' => [], 'users' => []];

        foreach ($rows as $r) {
            $old = $decode($r->old_values);
            $new = $decode($r->new_values);
            match ($r->entity_type) {
                'release' => $ids['releases'][] = $r->entity_id,
                'track' => $ids['tracks'][] = $r->entity_id,
                'label' => $ids['labels'][] = $r->entity_id,
                'artist' => $ids['artists'][] = $r->entity_id,
                default => null,
            };
            foreach ([$old, $new] as $v) {
                if (!empty($v['label_id'])) $ids['labels'][] = $v['label_id'];
                if (!empty($v['artist_id'])) $ids['artists'][] = $v['artist_id'];
                if (!empty($v['release_id'])) $ids['releases'][] = $v['release_id'];
                if (!empty($v['user_id'])) $ids['users'][] = $v['user_id'];
            }
        }

        $names = [
            'releases' => DB::table('releases')->whereIn('id', array_unique($ids['releases']))->pluck('title', 'id'),
            'tracks' => DB::table('tracks')->whereIn('id', array_unique($ids['tracks']))->pluck('title', 'id'),
            'labels' => DB::table('labels')->whereIn('id', array_unique($ids['labels']))->pluck('name', 'id'),
            'artists' => DB::table('artists')->whereIn('id', array_unique($ids['artists']))->pluck('stage_name', 'id'),
            'users' => DB::table('users')->whereIn('id', array_unique($ids['users']))->pluck('email', 'id'),
        ];

        $owner = function (array $v) use ($names) {
            if (!empty($v['label_id'])) return 'Label: '.($names['labels'][$v['label_id']] ?? '#'.$v['label_id']);
            if (!empty($v['artist_id'])) return 'Artist: '.($names['artists'][$v['artist_id']] ?? '#'.$v['artist_id']);
            if (!empty($v['release_id'])) return 'Release: '.($names['releases'][$v['release_id']] ?? '#'.$v['release_id']).(isset($v['track_number']) ? ' (track '.$v['track_number'].')' : '');
            if (array_key_exists('user_id', $v)) return $v['user_id'] ? ($names['users'][$v['user_id']] ?? 'user #'.$v['user_id']) : 'no login';

            return '—';
        };

        return collect($rows)->map(function ($r) use ($decode, $names, $owner) {
            $old = $decode($r->old_values);
            $new = $decode($r->new_values);
            $what = match ($r->entity_type) {
                'release' => 'Release: '.($names['releases'][$r->entity_id] ?? '#'.$r->entity_id),
                'track' => 'Track: '.($names['tracks'][$r->entity_id] ?? '#'.$r->entity_id),
                'label' => 'Label: '.($names['labels'][$r->entity_id] ?? '#'.$r->entity_id),
                'artist' => 'Artist: '.($names['artists'][$r->entity_id] ?? '#'.$r->entity_id),
                default => $r->entity_type.' #'.$r->entity_id,
            };
            $at = $r->transferred_at ?: $r->created_at;

            return [
                'id' => (int) $r->id,
                'public_id' => $r->public_id,
                'type' => self::TYPE_LABELS[$r->transfer_type] ?? str_replace('_', ' ', (string) $r->transfer_type),
                'what' => $what,
                'from' => $r->entity_type === 'release' ? ($old['label_id'] ?? null ? $owner(['label_id' => $old['label_id']]) : $owner(['artist_id' => $old['artist_id'] ?? null])) : $owner($old),
                'to' => $r->entity_type === 'release' ? ($new['label_id'] ?? null ? $owner(['label_id' => $new['label_id']]) : $owner(['artist_id' => $new['artist_id'] ?? null])) : $owner($new),
                'scope' => match ($r->revenue_scope) {
                    'pending_and_future' => 'Review + future',
                    'future_only' => 'Future only',
                    'access_only' => 'Login only',
                    default => (string) $r->revenue_scope,
                },
                'reason' => (string) ($r->reason ?? ''),
                'by' => $r->by_name ?: ($r->by_email ?: '—'),
                'date' => $at ? \Illuminate\Support\Carbon::parse($at)->timezone('Asia/Kolkata')->format('d M Y, h:i A') : '—',
            ];
        })->values();
    }

    private function authorizeSuperAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Super Admin access required.');
    }
}
