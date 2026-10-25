<?php

namespace App\Services\V2;

use App\Models\Core\AccountAgreement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * W94 MIXXTUNE_ACCOUNT_AGREEMENTS
 * Ends on = signed on + term - 1 day (signed 23 Oct 2026 for 3 years -> ends 22 Oct 2029).
 * Days are counted in India time. From notice_days (90) before the end the
 * agreement is "due"; it stays due after the end until it is renewed or
 * marked "not renewing".
 */
class AccountAgreements
{
    public const TZ = 'Asia/Kolkata';
    public const NOTICE_DAYS = 90;
    public const ROLES = ['label', 'artist'];

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ)->startOfDay();
    }

    public static function endsOn(string $signedOn, int $termMonths): CarbonImmutable
    {
        return CarbonImmutable::parse($signedOn, self::TZ)->startOfDay()->addMonthsNoOverflow($termMonths)->subDay();
    }

    public static function daysLeft(AccountAgreement $a): int
    {
        $end = CarbonImmutable::parse($a->ends_on->toDateString(), self::TZ)->startOfDay();

        return (int) self::today()->diffInDays($end, false);
    }

    public function active(int $userId): ?AccountAgreement
    {
        return AccountAgreement::query()->where('user_id', $userId)->where('status', 'active')->latest('id')->first();
    }

    public static function termText(int $months): string
    {
        return $months % 12 === 0 ? ($months / 12).' year'.($months === 12 ? '' : 's') : $months.' months';
    }

    public function row(AccountAgreement $a): array
    {
        $left = self::daysLeft($a);

        return [
            'id' => (int) $a->id,
            'signed_on' => $a->signed_on->toDateString(),
            'term_months' => (int) $a->term_months,
            'term' => self::termText((int) $a->term_months),
            'ends_on' => $a->ends_on->toDateString(),
            'days_left' => $left,
            'notice_days' => (int) $a->notice_days,
            'due' => $a->status === 'active' && $left <= (int) $a->notice_days,
            'status' => $a->status,
            'notes' => $a->notes,
            'has_document' => !empty($a->document_path),
            'document_name' => $a->document_name,
            'close_reason' => $a->close_reason,
            'closed_at' => $a->closed_at ? $a->closed_at->copy()->timezone(self::TZ)->format('d M Y') : null,
        ];
    }

    /* Users > Edit: the current agreement + all earlier ones */
    public function forUser(User $user): ?array
    {
        if (!in_array((string) $user->role, self::ROLES, true)) {
            return null;
        }

        $all = AccountAgreement::query()->where('user_id', $user->id)->orderByDesc('signed_on')->orderByDesc('id')->get();
        $active = $all->firstWhere('status', 'active');

        return [
            'current' => $active ? $this->row($active) : null,
            'history' => $all->reject(fn ($a) => $active && $a->id === $active->id)->map(fn ($a) => $this->row($a))->values()->all(),
            'notice_days' => self::NOTICE_DAYS,
            'today' => self::today()->toDateString(),
        ];
    }

    /* Super Admin dashboard: every active agreement inside its notice period (or already ended) */
    public function due(int $limit = 200): array
    {
        $horizon = self::today()->addDays(366)->toDateString();
        $rows = AccountAgreement::query()
            ->where('status', 'active')
            ->where('ends_on', '<=', $horizon)
            ->orderBy('ends_on')
            ->limit($limit * 2)
            ->get()
            ->filter(fn ($a) => self::daysLeft($a) <= (int) $a->notice_days)
            ->take($limit);

        if ($rows->isEmpty()) {
            return [];
        }

        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get(['id', 'name', 'full_name', 'email', 'role'])->keyBy('id');
        $labels = DB::table('labels')->whereIn('user_id', $rows->pluck('user_id'))->whereNull('deleted_at')->orderBy('id')
            ->get(['user_id', 'name'])->groupBy('user_id');

        return $rows->map(function ($a) use ($users, $labels) {
            $u = $users->get($a->user_id);
            $names = collect($labels->get($a->user_id, []))->pluck('name')->filter()->unique()->values();

            return $this->row($a) + [
                'user_id' => (int) $a->user_id,
                'account' => $u ? ($u->name ?: $u->email) : 'User #'.$a->user_id,
                'person' => $u?->full_name,
                'role' => $u?->role,
                'labels' => $names->take(3)->all(),
                'more_labels' => max(0, $names->count() - 3),
            ];
        })->values()->all();
    }

    /* the dashboard pop-up opens once a day per Super Admin (India date) */
    public function popupDue(User $viewer): bool
    {
        return Cache::get($this->seenKey($viewer)) !== self::today()->toDateString();
    }

    public function markSeen(User $viewer): void
    {
        Cache::put($this->seenKey($viewer), self::today()->toDateString(), now()->addDays(3));
    }

    private function seenKey(User $viewer): string
    {
        return 'account-agreements:popup-seen:'.$viewer->id;
    }
}
