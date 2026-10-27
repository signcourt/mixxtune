<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use App\Services\V2\Website\HomepageContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/*
 * W121 MIXXTUNE_WEBSITE_HOMEPAGE + MIXXTUNE_WEBSITE_JOIN (Super Admin only)
 * Website -> Homepage: the homepage words, sections and the Join form questions.
 * Website -> Join Requests: people who pressed Join; call, note, follow up.
 */
class WebsiteController extends Controller
{
    public const STATUSES = [
        'new' => 'New',
        'called' => 'Called',
        'discussion' => 'In discussion',
        'joined' => 'Joined',
        'not_interested' => 'Not interested',
        'spam' => 'Spam',
    ];

    private const TZ = 'Asia/Kolkata';

    private function superAdmin(Request $request): void
    {
        abort_unless(app(PermissionService::class)->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can do this.');
    }

    /* ---------------- Website -> Homepage ---------------- */

    public function homepage(Request $request, HomepageContent $content): Response
    {
        $this->superAdmin($request);

        $row = DB::table('system_settings')->where('key', HomepageContent::KEY)->first(['updated_at', 'updated_by']);
        $by = $row?->updated_by ? DB::table('users')->where('id', $row->updated_by)->value('name') : null;

        return Inertia::render('V2/Admin/Website/Homepage', [
            'role' => 'super_admin',
            'content' => $content->get(),
            'defaults' => HomepageContent::defaults(),
            'types' => HomepageContent::TYPES,
            'locked' => array_keys(HomepageContent::LOCKED),
            'maxFields' => HomepageContent::MAX_FIELDS,
            'published' => $row ? [
                'at' => $row->updated_at ? Carbon::parse($row->updated_at)->timezone(self::TZ)->format('d M Y, h:i A') : null,
                'by' => $by,
            ] : null,
        ]);
    }

    public function publish(Request $request, HomepageContent $content): RedirectResponse
    {
        $this->superAdmin($request);
        $request->validate([
            'content' => ['required', 'array'],
            'content.join.fields' => ['required', 'array', 'min:2', 'max:'.HomepageContent::MAX_FIELDS],
        ], [], ['content.join.fields' => 'questions']);

        $old = $content->get();
        $new = $content->publish((array) $request->input('content'), $request->user()->id);

        try {
            app(AuditLogService::class)->record('website.homepage_published', 'website', 'Homepage and Join form published.', $old, $new);
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Published. The homepage shows the new words now.');
    }

    /* ---------------- Website -> Join Requests ---------------- */

    public function requests(Request $request): Response
    {
        $this->superAdmin($request);

        $tab = is_string($request->query('tab')) ? $request->query('tab') : 'new';
        $tabs = array_merge(['today' => 'Follow-up today'], self::STATUSES, ['all' => 'All']);
        if (!isset($tabs[$tab])) {
            $tab = 'new';
        }
        $q = is_string($request->query('q')) ? trim(mb_substr($request->query('q'), 0, 80)) : '';
        $endOfToday = now(self::TZ)->endOfDay()->utc();

        $base = fn () => DB::table('website_join_requests');
        $counts = [];
        foreach (array_keys($tabs) as $t) {
            $counts[$t] = $this->tabQuery($base(), $t, $endOfToday)->count();
        }

        $query = $this->tabQuery($base(), $tab, $endOfToday);
        if ($q !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                foreach (['name', 'phone', 'email', 'brand', 'city', 'role'] as $col) {
                    $w->orWhere($col, 'like', $like);
                }
            });
        }
        $tab === 'today' ? $query->orderBy('next_follow_up_at') : $query->orderByDesc('id');
        $page = $query->paginate(30, ['id', 'name', 'phone', 'brand', 'city', 'role', 'status', 'created_at', 'next_follow_up_at'])->withQueryString();

        $rows = collect($page->items())->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'phone' => $r->phone,
            'brand' => $r->brand,
            'city' => $r->city,
            'role' => $r->role,
            'status' => $r->status,
            'received' => $this->when($r->created_at),
            'follow' => $r->next_follow_up_at ? $this->when($r->next_follow_up_at) : null,
            'follow_due' => $r->next_follow_up_at
                && Carbon::parse($r->next_follow_up_at)->lte($endOfToday)
                && !in_array($r->status, ['joined', 'not_interested', 'spam'], true),
        ]);

        $openId = is_string($request->query('open')) ? (int) $request->query('open') : 0;
        $open = $openId > 0 ? $this->detail($openId) : null;

        return Inertia::render('V2/Admin/Website/JoinRequests', [
            'role' => 'super_admin',
            'tab' => $tab,
            'q' => $q,
            'tabs' => collect($tabs)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => $counts[$key]])->values(),
            'statuses' => collect(self::STATUSES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'rows' => $rows,
            'pagination' => [
                'current' => $page->currentPage(),
                'last' => $page->lastPage(),
                'total' => $page->total(),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
            'open' => $open,
            'staff' => DB::table('users')->whereIn('role', ['super_admin', 'admin'])->where('account_status', 'active')
                ->orderByRaw("role = 'super_admin' desc")->orderBy('name')->get(['id', 'name', 'role'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name.($u->role === 'super_admin' ? ' (Super Admin)' : ' (Manager)')]),
        ]);
    }

    public function update(Request $request, int $joinRequest): RedirectResponse
    {
        $this->superAdmin($request);
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(self::STATUSES))],
            'next_follow_up_at' => ['nullable', 'date'],
            'handled_by' => ['nullable', 'integer'],
        ], [], ['next_follow_up_at' => 'next follow-up']);

        $row = DB::table('website_join_requests')->where('id', $joinRequest)->first();
        abort_unless($row, 404);

        $handledBy = $data['handled_by'] ?? null;
        if ($handledBy && !DB::table('users')->where('id', $handledBy)->whereIn('role', ['super_admin', 'admin'])->exists()) {
            return back()->withErrors(['handled_by' => 'Pick a Super Admin or a manager.']);
        }

        $follow = !empty($data['next_follow_up_at'])
            ? Carbon::parse($data['next_follow_up_at'], self::TZ)->utc()
            : null;

        $changes = [];
        if ($row->status !== $data['status']) {
            $changes[] = 'Status: '.(self::STATUSES[$row->status] ?? $row->status).' → '.self::STATUSES[$data['status']];
        }
        $oldFollow = $row->next_follow_up_at ? Carbon::parse($row->next_follow_up_at)->format('Y-m-d H:i') : null;
        if ($oldFollow !== $follow?->format('Y-m-d H:i')) {
            $changes[] = $follow ? 'Next follow-up: '.$follow->copy()->timezone(self::TZ)->format('d M Y, h:i A') : 'Next follow-up removed';
        }
        if ((int) $row->handled_by !== (int) $handledBy) {
            $changes[] = 'Handled by: '.($handledBy ? (string) DB::table('users')->where('id', $handledBy)->value('name') : 'nobody');
        }

        if ($changes === []) {
            return back()->with('success', 'Nothing changed.');
        }

        DB::table('website_join_requests')->where('id', $row->id)->update([
            'status' => $data['status'],
            'next_follow_up_at' => $follow,
            'handled_by' => $handledBy ?: null,
            'updated_at' => now(),
        ]);
        DB::table('website_join_request_notes')->insert([
            'join_request_id' => $row->id,
            'user_id' => $request->user()->id,
            'kind' => 'status',
            'body' => implode(' · ', $changes),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(AuditLogService::class)->record('join_request.updated', 'website', 'Join request #'.$row->id.' ('.$row->name.'): '.implode(' · ', $changes), [
                'status' => $row->status, 'next_follow_up_at' => $row->next_follow_up_at, 'handled_by' => $row->handled_by,
            ], [
                'status' => $data['status'], 'next_follow_up_at' => $follow?->toDateTimeString(), 'handled_by' => $handledBy,
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Saved.');
    }

    public function note(Request $request, int $joinRequest): RedirectResponse
    {
        $this->superAdmin($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']], [], ['body' => 'note']);
        $row = DB::table('website_join_requests')->where('id', $joinRequest)->first(['id', 'name']);
        abort_unless($row, 404);

        DB::table('website_join_request_notes')->insert([
            'join_request_id' => $row->id,
            'user_id' => $request->user()->id,
            'kind' => 'note',
            'body' => trim(strip_tags($data['body'])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('website_join_requests')->where('id', $row->id)->update(['updated_at' => now()]);

        try {
            app(AuditLogService::class)->record('join_request.note_added', 'website', 'Note on join request #'.$row->id.' ('.$row->name.')', [], ['join_request_id' => $row->id]);
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Note added.');
    }

    private function tabQuery($query, string $tab, Carbon $endOfToday)
    {
        return match ($tab) {
            'all' => $query,
            'today' => $query->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', $endOfToday)
                ->whereNotIn('status', ['joined', 'not_interested', 'spam']),
            default => $query->where('status', $tab),
        };
    }

    private function detail(int $id): ?array
    {
        $r = DB::table('website_join_requests')->where('id', $id)->first();
        if (!$r) {
            return null;
        }

        $answers = json_decode((string) $r->answers, true);
        $answers = is_array($answers) ? array_values(array_filter($answers, fn ($a) => is_array($a) && isset($a['label']))) : [];

        $notes = DB::table('website_join_request_notes as n')
            ->leftJoin('users as u', 'u.id', '=', 'n.user_id')
            ->where('n.join_request_id', $id)
            ->orderByDesc('n.id')
            ->limit(200)
            ->get(['n.id', 'n.kind', 'n.body', 'n.created_at', 'u.name as by'])
            ->map(fn ($n) => ['id' => $n->id, 'kind' => $n->kind, 'body' => $n->body, 'by' => $n->by, 'at' => $this->when($n->created_at)]);

        $digits = preg_replace('/\D+/', '', (string) $r->phone);
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '91'.substr($digits, 1);
        }

        return [
            'id' => $r->id,
            'name' => $r->name,
            'phone' => $r->phone,
            'tel' => preg_replace('/[^0-9+]/', '', (string) $r->phone),
            'whatsapp' => $digits !== '' ? 'https://wa.me/'.$digits : null,
            'email' => $r->email,
            'role' => $r->role,
            'brand' => $r->brand,
            'city' => $r->city,
            'status' => $r->status,
            'handled_by' => $r->handled_by,
            'next_follow_up_at' => $r->next_follow_up_at ? Carbon::parse($r->next_follow_up_at)->timezone(self::TZ)->format('Y-m-d\TH:i') : '',
            'received' => $this->when($r->created_at),
            'answers' => array_map(fn ($a) => ['label' => (string) $a['label'], 'value' => (string) ($a['value'] ?? '')], $answers),
            'notes' => $notes,
        ];
    }

    private function when($ts): string
    {
        $t = Carbon::parse($ts)->timezone(self::TZ);
        $today = now(self::TZ);
        if ($t->isSameDay($today)) {
            return 'Today, '.$t->format('h:i A');
        }
        if ($t->isSameDay($today->copy()->subDay())) {
            return 'Yesterday, '.$t->format('h:i A');
        }
        if ($t->isSameDay($today->copy()->addDay())) {
            return 'Tomorrow, '.$t->format('h:i A');
        }

        return $t->format('d M Y, h:i A');
    }
}
