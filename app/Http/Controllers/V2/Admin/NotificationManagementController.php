<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Support\PanelNotification;
use App\Models\User;
use App\Services\V2\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/*
 * MIXXTUNE_NOTIFY_TARGETED_V1
 *
 * Super Admin sends a notification to: everyone, a role (artists / labels /
 * managers / super admins), chosen people (search by name / e-mail), one
 * label (owner + team, optionally its artists), a country, a state or a
 * client ID. "Highlight" shows it as a banner at the top of the person's
 * panel until they open or hide it. Every send is in the audit log and the
 * history shows who opened it.
 */
class NotificationManagementController extends Controller
{
    private const TARGETS = ['all', 'role', 'users', 'label', 'country', 'state', 'client_id'];

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user() && $request->user()->role === 'super_admin', 403);
    }

    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        $distinct = fn (string $column) => User::query()
            ->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column)->values();

        $active = User::query()->where('account_status', 'active');

        return Inertia::render('V2/Admin/Notifications/Index', [
            'countries' => $distinct('country'),
            'states' => $distinct('state_code'),
            'roleCounts' => [
                'all' => (clone $active)->count(),
                'artist' => (clone $active)->where('role', 'artist')->count(),
                'label' => (clone $active)->where('role', 'label')->count(),
                'admin' => (clone $active)->where('role', 'admin')->count(),
                'super_admin' => (clone $active)->where('role', 'super_admin')->count(),
            ],
            'recentBroadcasts' => $this->history(),
        ]);
    }

    private function history(): Collection
    {
        $rows = PanelNotification::query()
            ->where('type', 'like', 'broadcast.%')
            ->orderByDesc('id')
            ->limit(3000)
            ->get(['id', 'public_id', 'title', 'message', 'severity', 'category', 'data', 'read_at', 'created_at']);

        return $rows
            ->groupBy(fn ($item) => data_get($item->data, 'broadcast_id', $item->public_id))
            ->map(function ($items, $broadcastId) {
                $first = $items->first();

                return [
                    'broadcast_id' => $broadcastId,
                    'title' => $first->title,
                    'message' => $first->message,
                    'severity' => $first->severity,
                    'category' => $first->category,
                    'highlight' => (bool) data_get($first->data, 'highlight', false),
                    'target_type' => data_get($first->data, 'target_type', 'unknown'),
                    'target_label' => data_get($first->data, 'target_label') ?? data_get($first->data, 'target_value'),
                    'recipients' => $items->count(),
                    'opened' => $items->whereNotNull('read_at')->count(),
                    'created_at' => optional($first->created_at)->format('d M Y, h:i A'),
                ];
            })
            ->values()
            ->take(25);
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(['primary', 'announcement', 'promotion'])],
            'severity' => ['required', Rule::in(['info', 'success', 'warning', 'danger'])],
            'highlight' => ['nullable', 'boolean'],
            'action_url' => ['nullable', 'string', 'max:500', 'regex:/^(\/(?!\/)|https:\/\/)/'],
            'target_type' => ['required', Rule::in(self::TARGETS)],
            'target_value' => ['nullable', 'string', 'max:255'],
            'target_users' => ['nullable', 'array', 'max:500'],
            'target_users.*' => ['integer'],
            'include_label_artists' => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'action_url.regex' => 'Link must start with / (a page of this site) or https://',
            'target_users.max' => 'Choose at most 500 people at once.',
        ];
    }

    /**
     * @return array{0: Collection<int,int>, 1: string}  user ids + readable target
     */
    private function recipients(array $v): array
    {
        $type = $v['target_type'];
        $value = trim((string) ($v['target_value'] ?? ''));
        $active = User::query()->where('account_status', 'active');

        switch ($type) {
            case 'all':
                return [$active->pluck('id'), 'Everyone'];

            case 'role':
                $names = ['artist' => 'All artists', 'label' => 'All labels', 'admin' => 'All managers', 'super_admin' => 'All super admins'];
                abort_unless(isset($names[$value]), 422, 'Choose a role.');

                return [$active->where('role', $value)->pluck('id'), $names[$value]];

            case 'users':
                $ids = collect($v['target_users'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
                $people = $active->whereIn('id', $ids)->get(['id', 'name']);
                $label = $people->count() === 1
                    ? $people->first()->name
                    : $people->count().' chosen people';

                return [$people->pluck('id'), $label];

            case 'label':
                $label = DB::table('labels')->whereNull('deleted_at')->where('id', (int) $value)->first(['id', 'name', 'user_id']);
                if (!$label) {
                    return [collect(), 'Label'];
                }

                $ids = collect([$label->user_id]);

                if (Schema::hasTable('label_team_members')) {
                    $ids = $ids->merge(
                        DB::table('label_team_members')->where('label_id', $label->id)->where('status', 'active')->pluck('user_id')
                    );
                }

                if (!empty($v['include_label_artists'])) {
                    $ids = $ids->merge(
                        DB::table('artists')->where('label_id', $label->id)->whereNull('deleted_at')->whereNotNull('user_id')->pluck('user_id')
                    );
                }

                $ids = $ids->filter()->unique();

                return [
                    $active->whereIn('id', $ids)->pluck('id'),
                    'Label: '.$label->name.(!empty($v['include_label_artists']) ? ' (+ its artists)' : ''),
                ];

            case 'country':
                return [$active->where('country', $value)->pluck('id'), 'Country: '.$value];

            case 'state':
                return [$active->where('state_code', $value)->pluck('id'), 'State: '.$value];

            case 'client_id':
                return [$active->where('client_id', $value)->pluck('id'), 'Client ID: '.$value];
        }

        return [collect(), $type];
    }

    private function needsValue(array $v): ?string
    {
        $type = $v['target_type'];

        if ($type === 'users' && empty($v['target_users'])) {
            return 'Search and choose at least one person.';
        }

        if (in_array($type, ['role', 'label', 'country', 'state', 'client_id'], true) && blank($v['target_value'] ?? null)) {
            return 'Please select or enter a target.';
        }

        return null;
    }

    /** How many people a target reaches (nothing is sent). */
    public function preview(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $v = $request->validate(array_intersect_key($this->rules(), array_flip([
            'target_type', 'target_value', 'target_users', 'target_users.*', 'include_label_artists',
        ])));

        if ($problem = $this->needsValue($v)) {
            return response()->json(['count' => 0, 'label' => '', 'sample' => [], 'problem' => $problem]);
        }

        [$ids, $label] = $this->recipients($v);

        $sample = User::query()->whereIn('id', $ids->take(6))->get(['name', 'email', 'role'])
            ->map(fn ($u) => $u->name.' ('.$u->role.')')->values();

        return response()->json(['count' => $ids->count(), 'label' => $label, 'sample' => $sample]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['users' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        $users = User::query()
            ->where('account_status', 'active')
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('client_id', 'like', $like))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'role']);

        return response()->json(['users' => $users]);
    }

    public function searchLabels(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['labels' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        $labels = DB::table('labels')
            ->leftJoin('users', 'users.id', '=', 'labels.user_id')
            ->whereNull('labels.deleted_at')
            ->where('labels.name', 'like', $like)
            ->orderBy('labels.name')
            ->limit(20)
            ->get(['labels.id', 'labels.name', 'users.name as owner_name']);

        return response()->json(['labels' => $labels]);
    }

    /** Who received a broadcast and who opened it. */
    public function recipientsList(Request $request, string $broadcast): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        abort_unless(strlen(preg_replace('/[^A-Za-z0-9]/', '', $broadcast)) >= 20, 404);

        $rows = PanelNotification::query()
            ->where('panel_notifications.type', 'like', 'broadcast.%')
            ->where(fn ($w) => $w->where('panel_notifications.data', 'like', '%'.preg_replace('/[^A-Za-z0-9]/', '', $broadcast).'%')
                ->orWhere('panel_notifications.public_id', $broadcast))
            ->join('users', 'users.id', '=', 'panel_notifications.user_id')
            ->orderByRaw('panel_notifications.read_at IS NULL')
            ->orderBy('users.name')
            ->limit(1000)
            ->get(['users.name', 'users.email', 'users.role', 'panel_notifications.read_at', 'panel_notifications.dismissed_at']);

        return response()->json([
            'recipients' => $rows->map(fn ($r) => [
                'name' => $r->name,
                'email' => $r->email,
                'role' => $r->role,
                'opened_at' => $r->read_at ? \Illuminate\Support\Carbon::parse($r->read_at)->timezone('Asia/Kolkata')->format('d M, h:i A') : null,
                'removed' => (bool) $r->dismissed_at,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate($this->rules(), $this->messages());

        if ($problem = $this->needsValue($validated)) {
            return back()->withErrors(['target_value' => $problem])->withInput();
        }

        [$ids, $targetLabel] = $this->recipients($validated);

        if ($ids->isEmpty()) {
            return back()->withErrors(['target_value' => 'No active users matched this target.'])->withInput();
        }

        $broadcastId = (string) Str::ulid();
        $now = now();
        $highlight = (bool) ($validated['highlight'] ?? false);

        $data = json_encode([
            'broadcast_id' => $broadcastId,
            'target_type' => $validated['target_type'],
            'target_value' => $validated['target_type'] === 'users'
                ? implode(',', $ids->all())
                : ($validated['target_value'] ?? null),
            'target_label' => $targetLabel,
            'highlight' => $highlight,
            'sent_by' => $request->user()->id,
        ]);

        $rows = $ids->map(fn ($userId) => [
            'public_id' => (string) Str::ulid(),
            'user_id' => $userId,
            'type' => 'broadcast.manual',
            'title' => $validated['title'],
            'message' => $validated['message'],
            'action_url' => ($validated['action_url'] ?? null) ?: null,
            'severity' => $validated['severity'],
            'category' => $validated['category'],
            'related_type' => null,
            'related_id' => null,
            'data' => $data,
            'read_at' => null,
            'dismissed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('panel_notifications')->insert($chunk);
            }
        });

        try {
            app(AuditLogService::class)->record(
                'notification.broadcast_sent',
                'notifications',
                'Notification "'.$validated['title'].'" sent to '.$targetLabel.' ('.count($rows).' people).',
                [],
                [
                    'broadcast_id' => $broadcastId,
                    'category' => $validated['category'],
                    'highlight' => $highlight,
                    'target' => $targetLabel,
                    'recipients' => count($rows),
                ],
                null,
                $request
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return back()->with(
            'success',
            count($rows).' notification(s) sent to '.$targetLabel.($highlight ? ' (highlighted on their dashboard)' : '').'.'
        );
    }
}
