<?php

namespace App\Services\V2;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * W204 MIXXTUNE_PENDING_WORK
 * One list of everything waiting for a Super Admin (or, scoped to their own
 * labels/artists, a Manager): KYC to review, releases to review, withdrawals to
 * approve/pay, poster change requests, approvals, support tickets, join requests,
 * pending deliveries. Read-only: counts + the page to open. Every block is
 * guarded so a missing table/column can never break the dashboard.
 */
final class PendingWorkService
{
    public function __construct(
        private readonly AdminAssignmentService $assignments,
        private readonly PermissionService $permissions,
    ) {
    }

    /** @return array{role:string,total:int,items:array<int,array<string,mixed>>,generated_at:string} */
    public function forUser(User $user): array
    {
        $role = $this->permissions->role($user);
        $items = [];

        if ($role === 'super_admin') {
            $items = $this->superAdminItems();
        } elseif ($role === 'admin') {
            $items = $this->managerItems($user);
        }

        $items = array_values(array_filter($items, fn ($i) => ($i['count'] ?? 0) > 0));

        return [
            'role' => $role,
            'total' => array_sum(array_column($items, 'count')),
            'items' => $items,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function superAdminItems(): array
    {
        return [
            $this->item('kyc', 'KYC to review', 'Payout / tax profiles submitted and waiting', '/admin/kyc', 'amber',
                fn () => DB::table('payout_profiles')->where('kyc_status', 'submitted')->count(), 'payout_profiles'),

            $this->item('releases', 'Releases to review', 'Submitted by labels / artists', '/admin/release-reviews', 'violet',
                fn () => DB::table('releases')->whereNull('deleted_at')->where('status', 'submitted')->count(), 'releases'),

            $this->withdrawalItem(null),

            $this->item('posters', 'Poster change requests', 'Artwork changes waiting for approval', '/admin/poster-requests', 'pink',
                fn () => DB::table('poster_change_requests')->where('status', 'pending')->count(), 'poster_change_requests'),

            $this->item('approvals', 'Approvals', 'Actions waiting for your yes/no', '/admin/approvals', 'blue',
                fn () => DB::table('pending_approvals')->where('status', 'pending')->count(), 'pending_approvals', 'status'),

            $this->item('support', 'Support tickets', 'Open tickets', '/admin/support', 'emerald',
                fn () => DB::table('support_tickets')->whereNotIn('status', ['closed', 'resolved'])->count(), 'support_tickets'),

            $this->item('join', 'Join requests', 'New sign-ups from the website', '/admin/join-requests', 'cyan',
                fn () => DB::table('website_join_requests')->whereIn('status', ['pending', 'new', 'open'])->count(), 'website_join_requests', 'status'),

            $this->item('deliveries', 'Deliveries pending', 'Store deliveries not yet sent', '/admin/delivery-status', 'slate',
                fn () => DB::table('release_store_deliveries')->where('status', 'pending')->count(), 'release_store_deliveries'),

            $this->item('approved_not_sent', 'Approved, not sent to stores', 'Start processing to deliver', '/admin/distribution', 'amber',
                fn () => DB::table('releases')->whereNull('deleted_at')->where('status', 'approved')
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('release_store_deliveries')->whereColumn('release_store_deliveries.release_id', 'releases.id'))
                    ->count(), 'release_store_deliveries', 'release_id'),

            $this->item('masters_without_login', 'Master labels without login', 'No owner account linked', '/admin/labels', 'slate',
                fn () => DB::table('labels')->whereNull('deleted_at')->whereNull('parent_label_id')->whereNull('user_id')->count(), 'labels', 'parent_label_id'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function managerItems(User $user): array
    {
        $labelIds = $this->assignments->labelIds($user)->map(fn ($v) => (int) $v)->all();
        $artistIds = $this->assignments->artistIds($user)->map(fn ($v) => (int) $v)->all();
        $userIds = $this->scopeUserIds($labelIds, $artistIds);

        $releaseScope = function ($q) use ($labelIds, $artistIds) {
            $q->where(function ($w) use ($labelIds, $artistIds) {
                $w->whereRaw('1 = 0');
                if ($labelIds) {
                    $w->orWhereIn('label_id', $labelIds);
                }
                if ($artistIds) {
                    $w->orWhereIn('artist_id', $artistIds);
                }
            });
        };

        return [
            $this->item('releases', 'Releases to review', 'Submitted by your labels / artists', '/admin/release-reviews', 'violet',
                fn () => tap(DB::table('releases')->whereNull('deleted_at')->where('status', 'submitted'), $releaseScope)->count(), 'releases'),

            $this->withdrawalItem($userIds),

            $this->item('posters', 'Poster change requests', 'From your labels / artists', '/admin/poster-requests', 'pink',
                function () use ($releaseScope) {
                    $ids = tap(DB::table('releases')->whereNull('deleted_at')->select('id'), $releaseScope);
                    return DB::table('poster_change_requests')->where('status', 'pending')->whereIn('release_id', $ids)->count();
                }, 'poster_change_requests', 'release_id'),

            $this->item('support', 'Support tickets', 'Open tickets from your accounts', '/admin/support', 'emerald',
                fn () => DB::table('support_tickets')->whereNotIn('status', ['closed', 'resolved'])->whereIn('user_id', $userIds ?: [0])->count(), 'support_tickets', 'user_id'),
        ];
    }

    /** @param array<int,int>|null $userIds null = everyone */
    private function withdrawalItem(?array $userIds): array
    {
        $base = function () use ($userIds) {
            $q = DB::table('withdrawals');
            if ($userIds !== null) {
                $q->whereIn('user_id', $userIds ?: [0]);
            }
            return $q;
        };

        $pending = $this->safe(fn () => (clone $base())->where('status', 'pending')->selectRaw('COUNT(*) c, COALESCE(SUM(amount),0) a')->first(), 'withdrawals');
        $toPay = $this->safe(fn () => (clone $base())->whereIn('status', ['approved', 'processing'])->selectRaw('COUNT(*) c, COALESCE(SUM(amount),0) a')->first(), 'withdrawals');

        $pc = (int) ($pending->c ?? 0);
        $tc = (int) ($toPay->c ?? 0);
        $sub = [];
        if ($pc) {
            $sub[] = $pc.' to approve (₹'.number_format((float) ($pending->a ?? 0), 2).')';
        }
        if ($tc) {
            $sub[] = $tc.' approved, to pay (₹'.number_format((float) ($toPay->a ?? 0), 2).')';
        }

        return [
            'key' => 'withdrawals',
            'label' => 'Wallet withdrawals',
            'hint' => $sub ? implode(' · ', $sub) : 'Nothing waiting',
            'url' => $pc ? '/admin/withdrawals?status=pending' : '/admin/withdrawals?status=approved',
            'tone' => 'green',
            'count' => $pc + $tc,
        ];
    }

    /**
     * @param array<int,int> $labelIds
     * @param array<int,int> $artistIds
     * @return array<int,int>
     */
    private function scopeUserIds(array $labelIds, array $artistIds): array
    {
        $ids = [];
        if ($labelIds && Schema::hasColumn('labels', 'user_id')) {
            $ids = array_merge($ids, DB::table('labels')->whereIn('id', $labelIds)->whereNotNull('user_id')->pluck('user_id')->all());
        }
        if ($artistIds && Schema::hasTable('artists') && Schema::hasColumn('artists', 'user_id')) {
            $ids = array_merge($ids, DB::table('artists')->whereIn('id', $artistIds)->whereNotNull('user_id')->pluck('user_id')->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function item(string $key, string $label, string $hint, string $url, string $tone, \Closure $count, string $table, ?string $column = null): array
    {
        $n = 0;
        if (Schema::hasTable($table) && ($column === null || Schema::hasColumn($table, $column))) {
            $n = (int) $this->safe($count, $table, 0);
        }

        return compact('key', 'label', 'hint', 'url', 'tone') + ['count' => $n];
    }

    private function safe(\Closure $fn, string $what, mixed $default = null): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);
            \Illuminate\Support\Facades\Log::warning('W204 pending-work count failed for '.$what.': '.$e->getMessage());
            return $default;
        }
    }
}
