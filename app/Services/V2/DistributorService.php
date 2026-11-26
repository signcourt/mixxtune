<?php

namespace App\Services\V2;

use App\Models\Distributor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W221 - who is a distributor, who sits in his tree, and which brand a logged-in user sees.
 * A distributor is a ROOT label (labels.parent_label_id IS NULL) with a row in `distributors`.
 * His tree = that label + its direct sub-labels. Nothing in `labels` is changed.
 */
class DistributorService
{
    private static ?bool $ready = null;

    /** @var array<int, ?Distributor> */
    private static array $memo = [];

    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasTable('distributors');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /** the distributor this user belongs to (owner, or owner of one of his sub-labels); staff never */
    public function forUser(?User $user): ?Distributor
    {
        if (!$user || !self::ready() || in_array((string) $user->role, ['admin', 'super_admin'], true)) {
            return null;
        }
        $uid = (int) $user->id;
        if (array_key_exists($uid, self::$memo)) {
            return self::$memo[$uid];
        }

        $found = Distributor::query()->where('user_id', $uid)->first();
        if (!$found) {
            $labels = DB::table('labels')->where('user_id', $uid)->whereNull('deleted_at')->get(['id', 'parent_label_id']);
            $ids = $labels->pluck('id')->merge($labels->pluck('parent_label_id'))->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
            if ($ids) {
                $found = Distributor::query()->whereIn('label_id', $ids)->first();
            }
        }

        /* W225: staff of a label (team members) belong to that label's distributor too */
        if (!$found) {
            try {
                $teamLabel = DB::table('label_team_members')->where('user_id', $uid)->where('status', 'active')->value('label_id');
                if ($teamLabel) {
                    $parent = DB::table('labels')->where('id', $teamLabel)->value('parent_label_id');
                    $found = Distributor::query()->whereIn('label_id', array_values(array_filter([(int) $teamLabel, (int) $parent])))->first();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return self::$memo[$uid] = $found;
    }

    public function isOwner(?User $user, ?Distributor $d = null): bool
    {
        $d ??= $this->forUser($user);

        return $user && $d && (int) $d->user_id === (int) $user->id;
    }

    public function logoUrl(?Distributor $d): ?string
    {
        return $d && $d->logo_path ? '/storage/'.ltrim((string) $d->logo_path, '/') : null;
    }

    /** the brand array every page gets: the distributor's own for his tree, ours for everybody else */
    public function brand(array $default, ?User $user): array
    {
        try {
            $d = $this->forUser($user);
            if (!$d || (string) $d->status !== 'active') {
                return $default;
            }
            $name = trim((string) $d->brand_name);
            if ($name === '') {
                $name = (string) (DB::table('labels')->where('id', $d->label_id)->value('name') ?: $default['name']);
            }
            $logo = $this->logoUrl($d);

            return array_merge($default, [
                'name' => $name,
                'subtitle' => trim((string) $d->dashboard_name),
                'logo_url' => $logo ?: '',
                'login_logo_url' => $logo ?: ($default['login_logo_url'] ?? ''),
                'footer_text' => '',
                'distributor' => true,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return $default;
        }
    }

    /** small prop for the sidebar: null for everybody who is not in a distributor tree */
    public function shared(?User $user): ?array
    {
        try {
            $d = $this->forUser($user);
            if (!$d) {
                return null;
            }

            return ['id' => (int) $d->id, 'is_owner' => $this->isOwner($user, $d), 'status' => (string) $d->status,
                'is_sub' => app(DistributorMoneyService::class)->subContext($user) !== null]; /* W226 */
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * W222: turn a freshly created label login into a distributor (called from Create User).
     * Root label = his oldest master label. Returns null when he owns no master label.
     */
    public function makeFromUser(User $user, User $actor): ?Distributor
    {
        if (!self::ready() || (string) $user->role !== 'label') {
            return null;
        }
        $existing = Distributor::query()->where('user_id', $user->id)->first();
        if ($existing) {
            return $existing;
        }
        $label = DB::table('labels')->where('user_id', $user->id)->whereNull('deleted_at')->whereNull('parent_label_id')->orderBy('id')->first();
        if (!$label || Distributor::query()->where('label_id', $label->id)->exists()) {
            return null;
        }

        $base = \Illuminate\Support\Str::slug((string) $label->name) ?: 'distributor';
        $base = substr($base, 0, 36);
        if (strlen($base) < 3 || in_array($base, ['www', 'mixxtune', 'admin', 'artist', 'label', 'home', 'staging', 'mail', 'api', 'app', 'panel', 'support'], true)) {
            $base = 'dist-'.$base;
        }
        $slug = $base;
        $n = 2;
        while (Distributor::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        $d = Distributor::query()->create([
            'label_id' => (int) $label->id,
            'user_id' => (int) $user->id,
            'slug' => $slug,
            'brand_name' => (string) $label->name,
            'status' => 'active',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        self::forget();

        return $d;
    }

    /**
     * W227: the brand name a mail to this person carries. A distributor's sub-labels (and staff in his tree)
     * get the distributor's name; everybody else - the distributor himself too, he is Mixx Tune's customer -
     * gets null, which means the normal Mixx Tune name.
     */
    public static function mailBrand($user): ?string
    {
        try {
            if (!$user instanceof User || !self::ready()) {
                return null;
            }
            self::forget(); /* a login made a moment ago may just have been hung under a distributor */
            $svc = app(self::class);
            $d = $svc->forUser($user);
            if (!$d || (string) $d->status !== 'active' || $svc->isOwner($user, $d)) {
                return null;
            }
            $name = trim((string) $d->brand_name);
            if ($name === '') {
                $name = trim((string) DB::table('labels')->where('id', $d->label_id)->value('name'));
            }

            return $name !== '' ? $name : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function forget(): void
    {
        self::$memo = [];
    }
}
