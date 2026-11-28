<?php

namespace App\Services\V2;

use App\Mail\StaffAlertMail;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * W241 MIXXTUNE_PROMOTION
 * - which live releases a login may open a Promo kit for (the same circle as the Releases list:
 *   Super Admin all, Manager his assigned labels/artists, label owner his labels + direct sub-labels
 *   (so a distributor: all his content), team member his allowed part, artist his own)
 * - promotion packages and requests
 */
class PromotionService
{
    public const STATUSES = ['new' => 'New', 'in_talk' => 'In discussion', 'accepted' => 'Accepted', 'done' => 'Done', 'declined' => 'Declined'];

    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public static function ready(): bool
    {
        try {
            return Schema::hasTable('promotion_packages') && Schema::hasTable('promotion_requests');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** live releases this login may make a Promo kit for */
    public function releases(User $user): Builder
    {
        $role = $this->permissions->role($user);
        $q = Release::query()->whereNull('releases.deleted_at')->whereIn('releases.status', ReleaseShareService::LIVE);

        if ($role === 'super_admin') {
            return $q;
        }

        if ($role === 'admin') {
            $a = app(AdminAssignmentService::class);
            $artistIds = $a->artistIds($user);
            $labelIds = $a->labelIds($user);
            if ($artistIds->isEmpty() && $labelIds->isEmpty()) {
                return $q->whereRaw('1 = 0');
            }

            return $q->where(function ($b) use ($artistIds, $labelIds) {
                $b->whereIn('releases.artist_id', $artistIds->all() ?: [0])->orWhereIn('releases.label_id', $labelIds->all() ?: [0]);
            });
        }

        if ($role === 'artist') {
            $artist = Artist::query()->where('user_id', $user->id)->whereNull('deleted_at')->first();

            return $artist ? $q->where('releases.artist_id', $artist->id) : $q->whereRaw('1 = 0');
        }

        if ($role === 'label') {
            $team = app(LabelTeamAccessService::class);
            $member = $team->membership($user);
            if ($member) {
                $labelIds = $team->accessibleLabelIds($user);
                $artistIds = $team->accessibleArtistIds($user);
                if ($labelIds->isEmpty() && $artistIds->isEmpty()) {
                    return $q->whereRaw('1 = 0');
                }
                if ($member->scope_level === 'entire_label') {
                    return $q->whereIn('releases.label_id', $labelIds->all() ?: [0]);
                }

                return $q->where(function ($b) use ($labelIds, $artistIds) {
                    $b->whereIn('releases.artist_id', $artistIds->all() ?: [0])
                        ->orWhere(function ($c) use ($labelIds) {
                            $c->whereNull('releases.artist_id')->whereIn('releases.label_id', $labelIds->all() ?: [0]);
                        });
                });
            }

            $own = Label::query()->where('user_id', $user->id)->whereNull('deleted_at')->pluck('id')->map(fn ($v) => (int) $v);
            if ($own->isEmpty()) {
                return $q->whereRaw('1 = 0');
            }
            $children = Label::query()->whereIn('parent_label_id', $own)->whereNull('deleted_at')->pluck('id')->map(fn ($v) => (int) $v);

            return $q->whereIn('releases.label_id', $own->merge($children)->unique()->values()->all());
        }

        return $q->whereRaw('1 = 0');
    }

    public function isStaff(User $user): bool
    {
        return in_array($this->permissions->role($user), ['super_admin', 'admin'], true);
    }

    /** a new request: bell to every active Super Admin and Admin, e-mail to the Super Admins */
    public function tellStaff(int $requestId, User $from, string $what): void
    {
        try {
            $staff = User::query()->whereIn('role', ['admin', 'super_admin'])->where('account_status', 'active')->get();
            foreach ($staff as $u) {
                app(PanelNotificationService::class)->send($u, 'promotion.request', 'New promotion request', ($from->name ?: 'A label').': '.$what, '/admin/promotion/requests');
                if ((string) $u->role === 'super_admin' && $u->email) {
                    try {
                        Mail::to($u->email)->send(new StaffAlertMail('New promotion request', (string) ($u->name ?: 'there'), [($from->name ?: 'A label').' asked for a promotion: '.$what, 'Open Promotion > Requests in the dashboard to answer.'], url('/admin/promotion/requests')));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** the answer: bell always, e-mail only where it carries our own name */
    public function tellRequester(object $row, string $statusLabel): void
    {
        try {
            $u = User::query()->find($row->user_id);
            if (! $u) {
                return;
            }
            app(PanelNotificationService::class)->send($u, 'promotion.reply', 'Your promotion request: '.$statusLabel, mb_substr((string) ($row->staff_reply ?: 'Open Promotion to see the answer.'), 0, 180), '/promotion/services');
            if ($u->email && DistributorService::mailBrand($u) === null) {
                Mail::to($u->email)->send(new StaffAlertMail('Your promotion request: '.$statusLabel, (string) ($u->name ?: 'there'), array_values(array_filter([(string) ($row->package_name ?: 'Promotion request'), (string) $row->staff_reply])), url('/promotion/services')));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function openCount(): int
    {
        try {
            return self::ready() ? (int) DB::table('promotion_requests')->where('status', 'new')->count() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
