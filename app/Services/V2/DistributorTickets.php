<?php

namespace App\Services\V2;

use App\Models\Distributor;
use App\Models\Support\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * W234 MIXXTUNE_DISTRIBUTOR_TICKETS
 * A sub-label's support ticket belongs to its distributor: he is told, he sees it, he answers it.
 * Mixx Tune staff still see every ticket in Support Management (nothing is hidden from a Super Admin).
 * A switched-off distributor gets nothing: his sub-labels' tickets go to Mixx Tune staff as before.
 */
class DistributorTickets
{
    /** logins of the distributor's sub-labels: their owners and their active team members (never the distributor himself) */
    public static function subUserIds(Distributor $d): array
    {
        $children = DB::table('labels')->where('parent_label_id', $d->label_id)->whereNull('deleted_at')->get(['id', 'user_id']);
        $ids = $children->pluck('user_id')->filter()->map(fn ($v) => (int) $v)->all();

        try {
            $labelIds = $children->pluck('id')->map(fn ($v) => (int) $v)->all();
            if ($labelIds) {
                $team = DB::table('label_team_members')->whereIn('label_id', $labelIds)->where('status', 'active')->pluck('user_id')->map(fn ($v) => (int) $v)->all();
                $ids = array_merge($ids, $team);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return array_values(array_diff(array_unique($ids), [(int) $d->user_id]));
    }

    /** the active distributor whose sub-label this user is; null for everybody else */
    public static function ofUser(?int $userId): ?Distributor
    {
        try {
            if (! $userId || ! DistributorService::ready()) {
                return null;
            }
            $user = User::query()->find($userId);
            $ctx = $user ? app(DistributorMoneyService::class)->subContext($user) : null;
            $d = $ctx['distributor'] ?? null;

            return $d && (string) $d->status === 'active' ? $d : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** may this user (a distributor owner) open this ticket? */
    public static function canSee(?User $user, SupportTicket $ticket): bool
    {
        try {
            if (! $user) {
                return false;
            }
            $svc = app(DistributorService::class);
            $d = $svc->forUser($user);
            if (! $d || ! $svc->isOwner($user, $d) || (string) $d->status !== 'active') {
                return false;
            }

            return in_array((int) $ticket->user_id, self::subUserIds($d), true);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
