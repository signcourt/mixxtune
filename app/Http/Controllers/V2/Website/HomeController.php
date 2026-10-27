<?php

namespace App\Http\Controllers\V2\Website;

use App\Http\Controllers\Controller;
use App\Services\V2\AuditLogService;
use App\Services\V2\StaffAlerts;
use App\Services\V2\Website\HomepageContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/*
 * W121 MIXXTUNE_WEBSITE_HOMEPAGE + MIXXTUNE_WEBSITE_JOIN
 * The public homepage (mixxtune.com) and the "Join" form on it.
 * A sent form becomes a row in Website -> Join Requests for the Super Admin.
 */
class HomeController extends Controller
{
    public function index(HomepageContent $content): Response
    {
        return Inertia::render('Public/Home', [
            'content' => $content->get(),
        ]);
    }

    public function join(Request $request, HomepageContent $homepage): RedirectResponse
    {
        $content = $homepage->get();

        /* a hidden box people never see; robots fill it in. Answer as if it worked, keep nothing. */
        $trap = $request->input('website');
        if (is_array($trap) || trim((string) $trap) !== '') {
            return back()->with('success', $content['join']['thanks']);
        }

        /* 10 tries a minute (typos count) and 30 sent forms a day from one address; told in the form, not an error page */
        $ip = (string) $request->ip();
        if (RateLimiter::tooManyAttempts('join-form:'.$ip, 10) || RateLimiter::tooManyAttempts('join-form-day:'.$ip, 30)) {
            return back()->withErrors(['join' => 'Too many tries. Please wait a minute and send it again.']);
        }
        RateLimiter::hit('join-form:'.$ip, 60);

        if (!is_array($request->input('answers', []))) {
            return back()->withErrors(['join' => 'Please fill in the form again.']);
        }
        $request->validate($homepage->rules($content), [], $homepage->attributes($content));

        $answers = [];
        $values = [];
        foreach ($content['join']['fields'] as $f) {
            if (!$f['show']) {
                continue;
            }
            $raw = $request->input('answers.'.$f['id']);
            $value = is_scalar($raw) ? trim((string) $raw) : '';
            $value = mb_substr(strip_tags($value), 0, $f['type'] === 'textarea' ? 2000 : 190);
            $values[$f['id']] = $value;
            $answers[] = ['id' => $f['id'], 'label' => $f['label'], 'value' => $value];
        }

        $phone = mb_substr($values['phone'] ?? '', 0, 40);
        $name = mb_substr($values['name'] ?? '', 0, 190);

        /* the same phone again within 10 minutes (double click, page reload): keep the first one */
        $digits = fn ($p) => substr(preg_replace('/\D+/', '', (string) $p), -10);
        $recent = DB::table('website_join_requests')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->pluck('phone')
            ->contains(fn ($p) => $digits($p) !== '' && $digits($p) === $digits($phone));
        if ($recent) {
            return back()->with('success', $content['join']['thanks']);
        }
        RateLimiter::hit('join-form-day:'.$ip, 86400);

        $id = DB::table('website_join_requests')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'phone' => $phone,
            'email' => ($values['email'] ?? '') !== '' ? mb_substr($values['email'], 0, 190) : null,
            'role' => ($values['role'] ?? '') !== '' ? mb_substr($values['role'], 0, 120) : null,
            'brand' => ($values['brand'] ?? '') !== '' ? mb_substr($values['brand'], 0, 190) : null,
            'city' => ($values['city'] ?? '') !== '' ? mb_substr($values['city'], 0, 190) : null,
            'message' => ($values['message'] ?? '') !== '' ? $values['message'] : null,
            'answers' => json_encode($answers, JSON_UNESCAPED_UNICODE),
            'status' => 'new',
            'source' => 'homepage',
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('website_join_request_notes')->insert([
            'join_request_id' => $id,
            'user_id' => null,
            'kind' => 'system',
            'body' => 'Request received from the website.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(AuditLogService::class)->record('join_request.created', 'website', 'Join request from the homepage: '.$name, [], ['join_request_id' => $id, 'role' => $values['role'] ?? null]);
        } catch (Throwable $e) {
            report($e);
        }

        $this->tellSuperAdmins($content, $id, $name, $phone, $values);

        return back()->with('success', $content['join']['thanks']);
    }

    private function tellSuperAdmins(array $content, int $id, string $name, string $phone, array $values): void
    {
        try {
            $alerts = app(StaffAlerts::class);
            $admins = $alerts->superAdmins();
            $url = '/admin/join-requests?open='.$id;
            $about = trim(implode(' · ', array_filter([$values['role'] ?? '', $values['brand'] ?? '', $values['city'] ?? ''])));
            if ($content['join']['notify_bell']) {
                $alerts->bell($admins, 'join.request', 'New join request: '.$name, trim($about.' · '.$phone, ' ·'), $url, 'info', ['join_request_id' => $id]);
            }
            if ($content['join']['notify_email']) {
                $lines = ['A new join request came from the homepage.', 'Name: '.$name, 'Phone: '.$phone];
                foreach (['role' => 'Joining as', 'brand' => 'Label / artist', 'city' => 'City', 'email' => 'Email'] as $k => $label) {
                    if (($values[$k] ?? '') !== '') {
                        $lines[] = $label.': '.$values[$k];
                    }
                }
                $lines[] = 'Open Website → Join Requests to call them and keep notes.';
                $alerts->email($admins, 'New join request: '.$name, $lines, url($url));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
