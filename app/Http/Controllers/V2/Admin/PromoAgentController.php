<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseLinkAgent;
use App\Services\V2\ReleaseShareService;
use App\Services\V2\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** W239 - staff screen of the Live Links Agent: what it found, the on/off switch, the keys, "Send on WhatsApp". */
class PromoAgentController extends Controller
{
    public function __construct(private readonly PermissionService $permissions, private readonly ReleaseLinkAgent $agent, private readonly ReleaseShareService $share)
    {
    }

    private function staff(Request $request, bool $superOnly = false): string
    {
        $role = $this->permissions->role($request->user());
        abort_unless(in_array($role, $superOnly ? ['super_admin'] : ['super_admin', 'admin'], true), 403);

        return $role;
    }

    public function index(Request $request): Response
    {
        $role = $this->staff($request);
        $ready = ReleaseLinkAgent::ready();
        $rows = null;

        if ($ready) {
            $rows = DB::table('release_link_agent as a')
                ->join('releases as r', 'r.id', '=', 'a.release_id')
                ->whereNull('r.deleted_at')
                ->orderByDesc('a.found_at')
                ->orderByDesc('a.id')
                ->paginate(30, ['a.*', 'r.title', 'r.primary_artist_name', 'r.digital_release_date', 'r.status as release_status'])
                ->withQueryString();

            $rows->getCollection()->transform(function ($a) {
                $release = Release::query()->find($a->release_id);
                $owner = $release ? $this->agent->owner($release) : null;
                $page = $this->share->page((int) $a->release_id);
                $links = $a->links ? (array) json_decode((string) $a->links, true) : [];
                $phone = $release ? $this->agent->phone($release, $owner) : '';

                return [
                    'release_id' => (int) $a->release_id,
                    'title' => (string) $a->title,
                    'artist' => (string) $a->primary_artist_name,
                    'release_date' => $a->digital_release_date ? substr((string) $a->digital_release_date, 0, 10) : null,
                    'status' => (string) $a->status,
                    'note' => (string) $a->last_note,
                    'stores' => array_values(array_map(fn ($k) => ReleaseShareService::STORES[$k] ?? $k, array_keys($links))),
                    'attempts' => (int) $a->attempts,
                    'checked' => $a->last_checked_at ? \Illuminate\Support\Carbon::parse($a->last_checked_at)->timezone('Asia/Kolkata')->format('d M, h:i A') : null,
                    'owner' => (string) ($owner?->name ?? ''),
                    'has_phone' => $phone !== '',
                    'page_on' => (bool) ($page->enabled ?? false),
                    'url' => $page ? $this->share->publicUrl((string) $page->code) : null,
                    'whatsapp' => ($phone !== '' && $page && $page->enabled && $release) ? 'https://wa.me/'.$phone.'?text='.rawurlencode($this->agent->message($release, $owner)) : null,
                    'whatsapp_sent' => $a->whatsapp_sent_at ? \Illuminate\Support\Carbon::parse($a->whatsapp_sent_at)->timezone('Asia/Kolkata')->format('d M, h:i A') : null,
                ];
            });
        }

        $sources = $this->agent->sources();

        return Inertia::render('V2/Admin/PromoAgent/Index', [
            'role' => $role,
            'ready' => $ready,
            'enabled' => $this->agent->enabled(),
            'since' => $this->agent->since(),
            'sources' => $sources,
            'can_manage' => $role === 'super_admin',
            'rows' => $rows,
            'waiting' => $ready && $this->agent->enabled() ? $this->agent->due(50)->count() : 0,
        ]);
    }

    public function toggle(Request $request, SystemSettingService $settings, AuditLogService $audit): RedirectResponse
    {
        $this->staff($request, true);
        $on = $request->boolean('enabled');
        if ($on && ! $settings->get('promo_agent.since', '')) {
            /* from today on: songs released earlier are not swept up automatically */
            $settings->set('promo_agent.since', now('Asia/Kolkata')->toDateString(), 'promo_agent', 'string', $request->user()->id);
        }
        $settings->set('promo_agent.enabled', $on, 'promo_agent', 'boolean', $request->user()->id);
        $audit->record('promo_agent.switched', 'settings', 'Live Links Agent switched '.($on ? 'on' : 'off').'.', [], ['enabled' => $on], null, $request);

        return back()->with('success', $on ? 'The agent is on. It looks every hour.' : 'The agent is off.');
    }

    public function keys(Request $request, AuditLogService $audit): RedirectResponse
    {
        $this->staff($request, true);
        $data = $request->validate([
            'spotify_id' => ['nullable', 'string', 'max:200'],
            'spotify_secret' => ['nullable', 'string', 'max:200'],
            'youtube_key' => ['nullable', 'string', 'max:200'],
            'clear' => ['nullable', 'array'],
            'clear.*' => ['string', 'in:spotify,youtube'],
        ]);
        $uid = (int) $request->user()->id;
        $changed = [];
        if (in_array('spotify', (array) ($data['clear'] ?? []), true)) {
            $this->agent->saveSecret('promo_agent.spotify_id', '', $uid);
            $this->agent->saveSecret('promo_agent.spotify_secret', '', $uid);
            $changed[] = 'spotify removed';
        } elseif (trim((string) ($data['spotify_id'] ?? '')) !== '' && trim((string) ($data['spotify_secret'] ?? '')) !== '') {
            $this->agent->saveSecret('promo_agent.spotify_id', trim($data['spotify_id']), $uid);
            $this->agent->saveSecret('promo_agent.spotify_secret', trim($data['spotify_secret']), $uid);
            $changed[] = 'spotify saved';
        }
        if (in_array('youtube', (array) ($data['clear'] ?? []), true)) {
            $this->agent->saveSecret('promo_agent.youtube_key', '', $uid);
            $changed[] = 'youtube removed';
        } elseif (trim((string) ($data['youtube_key'] ?? '')) !== '') {
            $this->agent->saveSecret('promo_agent.youtube_key', trim($data['youtube_key']), $uid);
            $changed[] = 'youtube saved';
        }
        if (! $changed) {
            return back()->withErrors(['keys' => 'Nothing to save. For Spotify both boxes are needed.']);
        }
        \Illuminate\Support\Facades\Cache::forget('w239:spotify-token:'.substr(sha1(trim((string) ($data['spotify_id'] ?? ''))), 0, 12));
        $audit->record('promo_agent.keys', 'settings', 'Live Links Agent keys changed: '.implode(', ', $changed).'.', [], ['changed' => $changed], null, $request);

        return back()->with('success', 'Keys: '.implode(', ', $changed).'.');
    }

    /** look right now for one release (any live release, also an older one) */
    public function check(Request $request, Release $release): RedirectResponse
    {
        $this->staff($request);
        abort_unless(ReleaseLinkAgent::ready(), 503);
        if (! $this->share->eligible($release)) {
            return back()->withErrors(['check' => 'This release is not live, so there is nothing to look for.']);
        }
        $note = $this->agent->check($release, $request->boolean('notify'));

        return back()->with('success', '"'.$release->title.'": '.$note);
    }

    public function sent(Request $request, Release $release): RedirectResponse
    {
        $this->staff($request);
        DB::table('release_link_agent')->where('release_id', $release->id)->update(['whatsapp_sent_at' => now(), 'whatsapp_sent_by' => (int) $request->user()->id]);

        return back();
    }
}
