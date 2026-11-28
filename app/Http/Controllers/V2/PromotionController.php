<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use App\Services\V2\PromotionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/** W241 MIXXTUNE_PROMOTION - the "Promotion" menu: Promo kit list, promotion packages, promotion requests. */
class PromotionController extends Controller
{
    public function __construct(private readonly PermissionService $permissions, private readonly PromotionService $promotion, private readonly ReleaseShareService $share)
    {
    }

    private function role(Request $request): string
    {
        $role = $this->permissions->role($request->user());
        abort_unless(in_array($role, ['super_admin', 'admin', 'label', 'artist'], true), 403);

        return $role;
    }

    private function staff(Request $request, bool $superOnly = false): string
    {
        $role = $this->permissions->role($request->user());
        abort_unless(in_array($role, $superOnly ? ['super_admin'] : ['super_admin', 'admin'], true), 403);

        return $role;
    }

    /** every live release of the viewer's circle, with the state of its promo page */
    public function promoKit(Request $request): Response
    {
        $role = $this->role($request);
        $this->permissions->authorize($request->user(), 'releases.view');
        $search = mb_substr(trim((string) $request->input('search', '')), 0, 120);
        $show = in_array($request->input('show'), ['on', 'none'], true) ? (string) $request->input('show') : '';
        $ready = ReleaseShareService::ready();

        $q = $this->promotion->releases($request->user());
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $q->where(function ($b) use ($like, $search) {
                $b->where('releases.title', 'like', $like)->orWhere('releases.primary_artist_name', 'like', $like)->orWhere('releases.upc', 'like', $like);
                if (ctype_digit($search)) {
                    $b->orWhere('releases.id', (int) $search);
                }
            });
        }
        if ($ready && $show === 'on') {
            $q->whereIn('releases.id', DB::table('release_share_pages')->where('enabled', 1)->select('release_id'));
        }
        if ($ready && $show === 'none') {
            $q->whereNotIn('releases.id', DB::table('release_share_pages')->where('enabled', 1)->select('release_id'));
        }

        $rows = $q->orderByDesc('releases.digital_release_date')->orderByDesc('releases.id')
            ->paginate(25, ['releases.id', 'releases.title', 'releases.primary_artist_name', 'releases.label_id', 'releases.digital_release_date', 'releases.upc'])
            ->withQueryString();

        $ids = $rows->getCollection()->pluck('id')->all();
        $pages = $ready && $ids ? DB::table('release_share_pages')->whereIn('release_id', $ids)->get()->keyBy('release_id') : collect();
        $found = collect();
        try {
            if ($ids && Schema::hasTable('release_link_agent')) {
                $found = DB::table('release_link_agent')->whereIn('release_id', $ids)->pluck('links', 'release_id');
            }
        } catch (\Throwable $e) {
            report($e);
        }
        $labels = DB::table('labels')->whereIn('id', $rows->getCollection()->pluck('label_id')->filter()->unique()->all() ?: [0])->pluck('name', 'id');

        $rows->getCollection()->transform(function ($r) use ($pages, $found, $labels) {
            $page = $pages->get($r->id);
            $manual = $page && $page->links ? (array) json_decode((string) $page->links, true) : [];
            $auto = $found->has($r->id) ? (array) json_decode((string) $found->get($r->id), true) : [];

            return [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'artist' => (string) $r->primary_artist_name,
                'label' => (string) ($labels[$r->label_id] ?? ''),
                'release_date' => $r->digital_release_date ? substr((string) $r->digital_release_date, 0, 10) : null,
                'page_on' => (bool) ($page->enabled ?? false),
                'url' => $page && $page->enabled ? $this->share->publicUrl((string) $page->code) : null,
                'links' => count(array_filter(array_merge($auto, $manual))),
                'views' => (int) ($page->views ?? 0),
            ];
        });

        return Inertia::render('V2/Promotion/PromoKit', [
            'role' => $role,
            'ready' => $ready,
            'rows' => $rows,
            'filters' => ['search' => $search, 'show' => $show],
            'show_label' => $role !== 'artist',
        ]);
    }

    /** label / artist side: the packages on offer, the request form, my own requests */
    public function services(Request $request): Response|RedirectResponse
    {
        $role = $this->role($request);
        if ($this->promotion->isStaff($request->user())) {
            return redirect('/admin/promotion/requests');
        }
        $ready = PromotionService::ready();
        $user = $request->user();

        return Inertia::render('V2/Promotion/Services', [
            'role' => $role,
            'ready' => $ready,
            'packages' => $ready ? DB::table('promotion_packages')->where('active', 1)->orderBy('sort')->orderBy('id')->get(['id', 'name', 'details', 'price_text'])->map(fn ($p) => ['id' => (int) $p->id, 'name' => (string) $p->name, 'details' => (string) $p->details, 'price' => (string) $p->price_text])->all() : [],
            'releases' => $this->promotion->releases($user)->orderByDesc('releases.digital_release_date')->orderByDesc('releases.id')->limit(300)->get(['releases.id', 'releases.title', 'releases.primary_artist_name'])->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'artist' => (string) $r->primary_artist_name])->all(),
            'mine' => $ready ? DB::table('promotion_requests as p')->leftJoin('releases as r', 'r.id', '=', 'p.release_id')->where('p.user_id', $user->id)->orderByDesc('p.id')->limit(50)->get(['p.*', 'r.title as release_title'])->map(fn ($p) => $this->row($p))->all() : [],
            'statuses' => PromotionService::STATUSES,
        ]);
    }

    public function send(Request $request, ReleaseAccessService $access): RedirectResponse
    {
        $this->role($request);
        abort_if($this->promotion->isStaff($request->user()), 403);
        abort_unless(PromotionService::ready(), 503, 'Promotion is not set up yet.');
        $user = $request->user();

        $data = $request->validate([
            'package_id' => ['nullable', 'integer'],
            'release_id' => ['nullable', 'integer'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'budget' => ['nullable', 'string', 'max:100'],
            'contact' => ['nullable', 'string', 'max:100'],
        ]);

        if (DB::table('promotion_requests')->where('user_id', $user->id)->where('created_at', '>=', now()->subDay())->count() >= 5) {
            return back()->withErrors(['promotion' => 'You have sent 5 requests in the last 24 hours. Please wait for an answer.']);
        }

        $package = ! empty($data['package_id']) ? DB::table('promotion_packages')->where('id', (int) $data['package_id'])->where('active', 1)->first() : null;
        if (! empty($data['package_id']) && ! $package) {
            return back()->withErrors(['promotion' => 'This package is not on offer any more.']);
        }
        $release = null;
        if (! empty($data['release_id'])) {
            $release = Release::query()->whereNull('deleted_at')->find((int) $data['release_id']);
            if (! $release || ! $access->canAccess($user, $release)) {
                return back()->withErrors(['promotion' => 'Choose one of your own releases.']);
            }
        }

        $id = DB::table('promotion_requests')->insertGetId([
            'user_id' => (int) $user->id,
            'release_id' => $release ? (int) $release->id : null,
            'package_id' => $package ? (int) $package->id : null,
            'package_name' => $package ? (string) $package->name : null,
            'message' => trim(strip_tags((string) $data['message'])),
            'budget' => trim(strip_tags((string) ($data['budget'] ?? ''))) ?: null,
            'contact' => trim(strip_tags((string) ($data['contact'] ?? ''))) ?: null,
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->promotion->tellStaff($id, $user, ($package ? $package->name : 'custom promotion').($release ? ' for "'.$release->title.'"' : ''));

        return back()->with('success', 'Request sent. You will get the answer here and on the bell.');
    }

    /** staff: every request */
    public function requests(Request $request): Response
    {
        $role = $this->staff($request);
        $ready = PromotionService::ready();
        $status = array_key_exists((string) $request->input('status'), PromotionService::STATUSES) ? (string) $request->input('status') : '';
        $rows = null;
        $counts = [];

        if ($ready) {
            $q = DB::table('promotion_requests as p')->leftJoin('releases as r', 'r.id', '=', 'p.release_id')->leftJoin('users as u', 'u.id', '=', 'p.user_id');
            if ($status !== '') {
                $q->where('p.status', $status);
            }
            $rows = $q->orderByRaw("p.status = 'new' desc")->orderByDesc('p.id')->paginate(25, ['p.*', 'r.title as release_title', 'u.name as user_name', 'u.email as user_email', 'u.phone as user_phone'])->withQueryString();
            $rows->getCollection()->transform(fn ($p) => $this->row($p) + ['user' => (string) ($p->user_name ?? ''), 'email' => (string) ($p->user_email ?? ''), 'phone' => (string) ($p->user_phone ?? '')]);
            $counts = DB::table('promotion_requests')->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all();
        }

        return Inertia::render('V2/Promotion/Requests', [
            'role' => $role,
            'ready' => $ready,
            'rows' => $rows,
            'status' => $status,
            'statuses' => PromotionService::STATUSES,
            'counts' => $counts,
        ]);
    }

    public function reply(Request $request, int $promotionRequest, AuditLogService $audit): RedirectResponse
    {
        $this->staff($request);
        abort_unless(PromotionService::ready(), 503);
        $row = DB::table('promotion_requests')->where('id', $promotionRequest)->first();
        abort_unless($row, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(PromotionService::STATUSES))],
            'staff_reply' => ['nullable', 'string', 'max:2000'],
        ]);
        $reply = trim(strip_tags((string) ($data['staff_reply'] ?? '')));

        DB::table('promotion_requests')->where('id', $row->id)->update([
            'status' => $data['status'],
            'staff_reply' => $reply !== '' ? $reply : $row->staff_reply,
            'replied_by' => (int) $request->user()->id,
            'replied_at' => now(),
            'updated_at' => now(),
        ]);
        $fresh = DB::table('promotion_requests')->where('id', $row->id)->first();
        if ($fresh->status !== $row->status || $reply !== '') {
            $this->promotion->tellRequester($fresh, PromotionService::STATUSES[$fresh->status] ?? $fresh->status);
        }
        try {
            $audit->record('promotion.request_answered', 'promotion', 'Promotion request #'.$row->id.' set to '.$data['status'].'.', ['status' => $row->status], ['status' => $data['status']], null, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Saved. The label has been told.');
    }

    /** Super Admin: the packages on offer */
    public function packages(Request $request): Response
    {
        $role = $this->staff($request, true);
        $ready = PromotionService::ready();

        return Inertia::render('V2/Promotion/Packages', [
            'role' => $role,
            'ready' => $ready,
            'packages' => $ready ? DB::table('promotion_packages')->orderBy('sort')->orderBy('id')->get()->map(fn ($p) => ['id' => (int) $p->id, 'name' => (string) $p->name, 'details' => (string) $p->details, 'price' => (string) $p->price_text, 'active' => (bool) $p->active, 'sort' => (int) $p->sort])->all() : [],
        ]);
    }

    public function savePackage(Request $request, AuditLogService $audit): RedirectResponse
    {
        $this->staff($request, true);
        abort_unless(PromotionService::ready(), 503);
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'details' => ['nullable', 'string', 'max:3000'],
            'price' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['required', 'boolean'],
        ]);
        $values = [
            'name' => trim(strip_tags((string) $data['name'])),
            'details' => trim(strip_tags((string) ($data['details'] ?? ''))) ?: null,
            'price_text' => trim(strip_tags((string) ($data['price'] ?? ''))) ?: null,
            'sort' => (int) ($data['sort'] ?? 0),
            'active' => (bool) $data['active'],
            'updated_at' => now(),
        ];
        if (! empty($data['id'])) {
            abort_unless(DB::table('promotion_packages')->where('id', (int) $data['id'])->exists(), 404);
            DB::table('promotion_packages')->where('id', (int) $data['id'])->update($values);
        } else {
            DB::table('promotion_packages')->insert($values + ['created_by' => (int) $request->user()->id, 'created_at' => now()]);
        }
        try {
            $audit->record('promotion.package_saved', 'promotion', 'Promotion package "'.$values['name'].'" saved ('.($values['active'] ? 'on offer' : 'hidden').').', [], ['name' => $values['name'], 'price' => $values['price_text'], 'active' => $values['active']], null, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Package saved.');
    }

    private function row(object $p): array
    {
        return [
            'id' => (int) $p->id,
            'package' => (string) ($p->package_name ?: 'Custom promotion'),
            'release_id' => $p->release_id ? (int) $p->release_id : null,
            'release' => (string) ($p->release_title ?? ''),
            'message' => (string) $p->message,
            'budget' => (string) ($p->budget ?? ''),
            'contact' => (string) ($p->contact ?? ''),
            'status' => (string) $p->status,
            'reply' => (string) ($p->staff_reply ?? ''),
            'sent_at' => $p->created_at ? substr((string) $p->created_at, 0, 16) : null,
            'replied_at' => $p->replied_at ? substr((string) $p->replied_at, 0, 16) : null,
        ];
    }
}
