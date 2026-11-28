<?php

namespace App\Services\V2;

use App\Mail\StaffAlertMail;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * W239 MIXXTUNE_LIVE_LINKS_AGENT
 * One job only: when a release reaches its release date and is live, find its store links and put them on
 * its promo page, then tell the owner the song is live.
 *
 * Links are found by the release's own codes, never by guessing from a name alone:
 *   Apple Music - UPC (Apple's public lookup, no key)
 *   Deezer      - ISRC (public, no key)
 *   Spotify     - ISRC (needs the two keys of a free Spotify developer app)
 *   YouTube     - search, accepted only when title AND an auto-generated "Topic" channel match (needs a Google API key)
 * JioSaavn, Gaana, Wynk, Amazon Music, Instagram have no such door: those links stay manual.
 */
class ReleaseLinkAgent
{
    public const RETRY_HOURS = 6;
    public const GIVE_UP_DAYS = 21;

    public function __construct(private readonly SystemSettingService $settings, private readonly ReleaseShareService $share)
    {
    }

    public static function ready(): bool
    {
        try {
            return Schema::hasTable('release_link_agent') && ReleaseShareService::ready();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('promo_agent.enabled', false);
    }

    /** releases with a release date before this day are left alone (no flood of old songs on day one) */
    public function since(): string
    {
        return (string) ($this->settings->get('promo_agent.since', '') ?: now('Asia/Kolkata')->toDateString());
    }

    private function secret(string $key): string
    {
        try {
            $raw = (string) $this->settings->get($key, '');

            return $raw === '' ? '' : (string) Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function saveSecret(string $key, string $value, ?int $userId): void
    {
        $this->settings->set($key, $value === '' ? '' : Crypt::encryptString($value), 'promo_agent', 'string', $userId);
    }

    public function sources(): array
    {
        return [
            'apple_music' => true,
            'deezer' => true,
            'spotify' => $this->secret('promo_agent.spotify_id') !== '' && $this->secret('promo_agent.spotify_secret') !== '',
            'youtube' => $this->secret('promo_agent.youtube_key') !== '',
        ];
    }

    /** releases the hourly run should look at now */
    public function due(int $limit = 8)
    {
        $today = now('Asia/Kolkata')->toDateString();
        $from = max($this->since(), now('Asia/Kolkata')->subDays(self::GIVE_UP_DAYS)->toDateString());

        return Release::query()
            ->leftJoin('release_link_agent as a', 'a.release_id', '=', 'releases.id')
            ->whereIn('releases.status', ReleaseShareService::LIVE)
            ->whereNotNull('releases.digital_release_date')
            ->whereDate('releases.digital_release_date', '<=', $today)
            ->whereDate('releases.digital_release_date', '>=', $from)
            ->where(function ($q) {
                $q->whereNull('a.id')
                    ->orWhere(function ($w) {
                        $w->whereIn('a.status', ['searching', 'partial'])
                            ->where(fn ($t) => $t->whereNull('a.last_checked_at')->orWhere('a.last_checked_at', '<', now()->subHours(self::RETRY_HOURS)));
                    });
            })
            ->orderBy('a.last_checked_at')
            ->orderBy('releases.digital_release_date')
            ->limit($limit)
            ->get(['releases.*']);
    }

    private function get(string $url, array $query = [], array $headers = []): ?array
    {
        try {
            $res = Http::timeout(15)->retry(1, 400, throw: false)->withHeaders($headers + ['Accept' => 'application/json'])->get($url, $query);

            return $res->successful() ? (array) $res->json() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function norm(string $text): string
    {
        $t = mb_strtolower($text);
        $t = preg_replace('/\((feat|ft)\.?[^)]*\)|\s(feat|ft)\.?\s.*$/u', '', $t);
        $t = preg_replace('/\s*-\s*(single|ep)$/u', '', (string) $t);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $t);
    }

    private function apple(string $upc): ?string
    {
        if ($upc === '') {
            return null;
        }
        foreach (['in', 'us'] as $country) {
            $d = $this->get('https://itunes.apple.com/lookup', ['upc' => $upc, 'country' => $country]);
            $r = $d['results'][0] ?? null;
            $url = $r['collectionViewUrl'] ?? $r['trackViewUrl'] ?? null;
            if ($url) {
                return preg_replace('/[?&]uo=\d+$/', '', (string) $url);
            }
        }

        return null;
    }

    private function deezer(string $isrc): ?string
    {
        if ($isrc === '') {
            return null;
        }
        $d = $this->get('https://api.deezer.com/track/isrc:'.$isrc);

        return ($d && empty($d['error']) && ! empty($d['link']) && strtoupper((string) ($d['isrc'] ?? $isrc)) === $isrc) ? (string) $d['link'] : null;
    }

    private function spotifyToken(): ?string
    {
        $id = $this->secret('promo_agent.spotify_id');
        $secret = $this->secret('promo_agent.spotify_secret');
        if ($id === '' || $secret === '') {
            return null;
        }

        return Cache::remember('w239:spotify-token:'.substr(sha1($id), 0, 12), 3000, function () use ($id, $secret) {
            try {
                $res = Http::timeout(15)->asForm()->withBasicAuth($id, $secret)->post('https://accounts.spotify.com/api/token', ['grant_type' => 'client_credentials']);

                return $res->successful() ? (string) ($res->json('access_token') ?? '') : '';
            } catch (\Throwable $e) {
                return '';
            }
        }) ?: null;
    }

    private function spotify(string $isrc, bool $album): ?string
    {
        $token = $isrc !== '' ? $this->spotifyToken() : null;
        if (! $token) {
            return null;
        }
        $d = $this->get('https://api.spotify.com/v1/search', ['q' => 'isrc:'.$isrc, 'type' => 'track', 'limit' => 5], ['Authorization' => 'Bearer '.$token]);
        foreach ((array) ($d['tracks']['items'] ?? []) as $item) {
            if (strtoupper((string) ($item['external_ids']['isrc'] ?? '')) !== $isrc) {
                continue;
            }
            $url = $album ? ($item['album']['external_urls']['spotify'] ?? null) : null;

            return (string) ($url ?: ($item['external_urls']['spotify'] ?? '')) ?: null;
        }

        return null;
    }

    /** YouTube has no code lookup: accept only an auto-generated "<artist> - Topic" video whose title is exactly ours */
    private function youtube(string $title, string $artist): ?string
    {
        $key = $this->secret('promo_agent.youtube_key');
        if ($key === '' || $title === '') {
            return null;
        }
        $dayKey = 'w239:youtube-count:'.now()->toDateString();
        if ((int) Cache::get($dayKey, 0) >= 80) {
            return null; /* Google's free daily allowance is about 100 searches */
        }
        Cache::put($dayKey, (int) Cache::get($dayKey, 0) + 1, 90000);

        $d = $this->get('https://www.googleapis.com/youtube/v3/search', ['part' => 'snippet', 'type' => 'video', 'maxResults' => 8, 'q' => trim($title.' '.$artist), 'key' => $key]);
        $want = $this->norm($title);
        foreach ((array) ($d['items'] ?? []) as $item) {
            $channel = (string) ($item['snippet']['channelTitle'] ?? '');
            $id = (string) ($item['id']['videoId'] ?? '');
            if ($id !== '' && str_ends_with($channel, ' - Topic') && $this->norm(html_entity_decode((string) ($item['snippet']['title'] ?? ''), ENT_QUOTES)) === $want) {
                return $id;
            }
        }

        return null;
    }

    /** look once for one release; returns a short human line about what happened */
    public function check(Release $release, bool $notify = true): string
    {
        $row = DB::table('release_link_agent')->where('release_id', $release->id)->first();
        $links = $row && $row->links ? (array) json_decode((string) $row->links, true) : [];

        $tracks = DB::table('tracks')->where('release_id', $release->id)->whereNull('deleted_at')->orderBy('id')->get(['title', 'isrc']);
        $isrc = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) ($tracks->first()->isrc ?? '')));
        $upc = (string) preg_replace('/\D/', '', (string) $release->upc);
        $album = $tracks->count() > 1;
        $sources = $this->sources();

        if (empty($links['apple_music']) && ($url = $this->apple($upc))) {
            $links['apple_music'] = $url;
        }
        if (empty($links['deezer']) && ($url = $this->deezer($isrc))) {
            $links['deezer'] = $url;
        }
        if ($sources['spotify'] && empty($links['spotify']) && ($url = $this->spotify($isrc, $album))) {
            $links['spotify'] = $url;
        }
        if ($sources['youtube'] && empty($links['youtube']) && ! $album && ($id = $this->youtube((string) ($tracks->first()->title ?? $release->title), (string) $release->primary_artist_name))) {
            $links['youtube'] = 'https://www.youtube.com/watch?v='.$id;
            $links['youtube_music'] = 'https://music.youtube.com/watch?v='.$id;
        }

        foreach ($links as $k => $u) {
            if (! isset(ReleaseShareService::STORES[$k]) || ! $this->share->cleanUrl($u)) {
                unset($links[$k]);
            }
        }

        $wanted = array_keys(array_filter($sources));
        $missing = array_values(array_diff($wanted, array_keys($links)));
        $days = $release->digital_release_date ? (int) floor(abs((strtotime(now('Asia/Kolkata')->toDateString()) - strtotime($release->digital_release_date->toDateString())) / 86400)) : 0;
        $status = ! $links ? 'searching' : ($missing ? 'partial' : 'found');
        if ($status !== 'found' && $days >= self::GIVE_UP_DAYS) {
            $status = $links ? 'found' : 'gave_up';
        }
        $note = ($links ? 'Found: '.implode(', ', array_map(fn ($k) => ReleaseShareService::STORES[$k], array_keys($links))).'.' : 'Nothing found yet.')
            .($missing && $status !== 'found' ? ' Still looking for: '.implode(', ', array_map(fn ($k) => ReleaseShareService::STORES[$k] ?? $k, $missing)).'.' : '')
            .($isrc === '' && $upc === '' ? ' This release has no ISRC and no UPC.' : '');

        $now = now();
        $first = $links && ! ($row->found_at ?? null);
        $data = [
            'status' => $status,
            'links' => json_encode($links),
            'attempts' => (int) ($row->attempts ?? 0) + 1,
            'last_note' => mb_substr($note, 0, 500),
            'last_checked_at' => $now,
            'updated_at' => $now,
        ] + ($first ? ['found_at' => $now] : []);
        if ($row) {
            DB::table('release_link_agent')->where('id', $row->id)->update($data);
        } else {
            DB::table('release_link_agent')->insert($data + ['release_id' => $release->id, 'created_at' => $now]);
        }

        if ($first) {
            /* the promo page is made (switched on) the first time links exist; an owner who switched his page off keeps it off */
            if (! $this->share->page((int) $release->id)) {
                DB::table('release_share_pages')->insert([
                    'release_id' => (int) $release->id,
                    'code' => $this->share->newCode(),
                    'enabled' => true,
                    'links' => json_encode([]),
                    'views' => 0,
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            if ($notify) {
                $this->notifyOwner($release);
            }
        }

        return $note;
    }

    public function owner(Release $release): ?User
    {
        $uid = $release->label_id ? DB::table('labels')->where('id', $release->label_id)->value('user_id') : null;
        if (! $uid && $release->artist_id) {
            $uid = DB::table('artists')->where('id', $release->artist_id)->value('user_id');
        }

        return $uid ? User::query()->find($uid) : null;
    }

    /** the number to write to: the owner's profile phone, else the label's; digits only, 91 added to a bare 10-digit number */
    public function phone(Release $release, ?User $owner): string
    {
        $raw = (string) ($owner?->phone ?: ($release->label_id ? DB::table('labels')->where('id', $release->label_id)->value('phone') : ''));
        $digits = ltrim((string) preg_replace('/\D/', '', $raw), '0');
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15 ? $digits : '';
    }

    public function message(Release $release, ?User $owner): string
    {
        $page = $this->share->page((int) $release->id);
        $url = $page ? $this->share->publicUrl((string) $page->code) : '';
        $name = trim((string) ($owner?->name ?: ''));

        return 'Hello'.($name !== '' ? ' '.$name : '').', your song "'.$release->title.'" is live now. Listen and share: '.$url."\n- ".$this->share->brandName($release);
    }

    private function notifyOwner(Release $release): void
    {
        try {
            $owner = $this->owner($release);
            if (! $owner) {
                return;
            }
            $page = $this->share->page((int) $release->id);
            $url = $page ? $this->share->publicUrl((string) $page->code) : '';

            app(PanelNotificationService::class)->send(
                $owner,
                'release.live_links',
                'Your song is live',
                '"'.$release->title.'" is out now. Open the Promo kit to share its link.',
                '/releases/'.$release->id.'/promo'
            );

            /* e-mail only where it carries our own name (a distributor's sub-label gets the bell) */
            if ($owner->email && DistributorService::mailBrand($owner) === null) {
                Mail::to($owner->email)->send(new StaffAlertMail(
                    'Your song "'.$release->title.'" is live',
                    (string) ($owner->name ?: 'there'),
                    ['"'.$release->title.'" by '.$release->primary_artist_name.' is out now.', 'Share this link with your listeners - it has the poster and the store buttons.', 'You can add more store links in the Promo kit of this release.'],
                    $url ?: null
                ));
            }

            DB::table('release_link_agent')->where('release_id', $release->id)->update(['notified_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
