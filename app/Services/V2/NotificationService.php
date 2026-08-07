<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\PanelNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private readonly AdminAssignmentService $assignments
    ) {
    }

    public function send(
        User $user,
        string $type,
        string $title,
        ?string $message = null,
        array $options = []
    ): PanelNotification {
        return PanelNotification::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'user_id' =>
                $user->id,

            'type' =>
                $type,

            'title' =>
                $title,

            'message' =>
                $message,

            'action_url' =>
                $options['action_url']
                ?? null,

            'severity' =>
                $options['severity']
                ?? 'info',

            'related_type' =>
                $options['related_type']
                ?? null,

            'related_id' =>
                $options['related_id']
                ?? null,

            'data' =>
                $options['data']
                ?? [],
        ]);
    }

    public function sendToUsers(
        iterable $users,
        string $type,
        string $title,
        ?string $message = null,
        array $options = []
    ): Collection {
        $notifications = collect();

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $notifications->push(
                $this->send(
                    $user,
                    $type,
                    $title,
                    $message,
                    $options
                )
            );
        }

        return $notifications;
    }

    public function releaseSubmitted(
        Release $release
    ): void {
        $this->sendToAdmins(
            $release,
            'release.submitted',
            'New release submitted',
            "'{$release->title}' is waiting for review.",
            'warning'
        );

        $this->sendToOwner(
            $release,
            'release.submitted',
            'Release submitted',
            "'{$release->title}' was submitted for review.",
            'success'
        );
    }

    public function releaseApproved(
        Release $release
    ): void {
        $this->sendToOwner(
            $release,
            'release.approved',
            'Release approved',
            "'{$release->title}' has been approved.",
            'success'
        );
    }

    public function releaseRejected(
        Release $release,
        ?string $reason = null
    ): void {
        $message =
            "'{$release->title}' was rejected.";

        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        $this->sendToOwner(
            $release,
            'release.rejected',
            'Release rejected',
            $message,
            'danger'
        );
    }

    public function changesRequested(
        Release $release,
        ?string $notes = null
    ): void {
        $message =
            "Changes were requested for '{$release->title}'.";

        if ($notes) {
            $message .= " Notes: {$notes}";
        }

        $this->sendToOwner(
            $release,
            'release.changes_requested',
            'Changes requested',
            $message,
            'warning'
        );
    }

    public function processingStarted(
        Release $release
    ): void {
        $this->sendToOwner(
            $release,
            'release.processing',
            'Distribution started',
            "'{$release->title}' is now processing.",
            'info'
        );
    }

    public function delivered(
        Release $release
    ): void {
        $this->sendToOwner(
            $release,
            'release.delivered',
            'Release delivered',
            "'{$release->title}' has been delivered to stores.",
            'success'
        );
    }

    public function live(
        Release $release
    ): void {
        $this->sendToOwner(
            $release,
            'release.live',
            'Release is live',
            "'{$release->title}' is now live.",
            'success'
        );
    }

    public function identifierAssigned(
        Release $release,
        string $identifierType,
        string $identifierCode
    ): void {
        $type = strtolower(
            $identifierType
        );

        $this->sendToOwner(
            $release,
            "identifier.{$type}.assigned",
            strtoupper($type) . ' assigned',
            strtoupper($type)
                . " {$identifierCode} was assigned to '{$release->title}'.",
            'success'
        );
    }

    public function unreadFor(
        User $user,
        int $limit = 50
    ): Collection {
        return PanelNotification::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull(
                'dismissed_at'
            )
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function unreadCount(
        User $user
    ): int {
        return PanelNotification::query()
            ->where(
                'user_id',
                $user->id
            )
            ->unread()
            ->count();
    }

    public function markRead(
        PanelNotification $notification,
        User $user
    ): PanelNotification {
        abort_unless(
            (int) $notification->user_id
                === (int) $user->id,
            403,
            'You cannot update this notification.'
        );

        if (!$notification->read_at) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return $notification->fresh();
    }

    public function markAllRead(
        User $user
    ): int {
        return PanelNotification::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }

    public function dismiss(
        PanelNotification $notification,
        User $user
    ): void {
        abort_unless(
            (int) $notification->user_id
                === (int) $user->id,
            403,
            'You cannot dismiss this notification.'
        );

        $notification->update([
            'dismissed_at' => now(),
        ]);
    }

    private function sendToOwner(
        Release $release,
        string $type,
        string $title,
        string $message,
        string $severity
    ): void {
        $users = $this->ownerUsers(
            $release
        );

        $this->sendToUsers(
            $users,
            $type,
            $title,
            $message,
            [
                'severity' =>
                    $severity,

                'action_url' =>
                    "/v2/releases/{$release->id}/edit",

                'related_type' =>
                    Release::class,

                'related_id' =>
                    $release->id,

                'data' => [
                    'release_id' =>
                        $release->id,

                    'status' =>
                        $release->status,
                ],
            ]
        );
    }

    private function sendToAdmins(
        Release $release,
        string $type,
        string $title,
        string $message,
        string $severity
    ): void {
        $query = User::query()
            ->whereIn(
                'role',
                [
                    'admin',
                    'super_admin',
                ]
            );

        $adminIds = collect();

        if ($release->artist_id) {
            $adminIds = $adminIds->merge(
                DB::table(
                    'admin_artist_assignments'
                )
                    ->where(
                        'artist_id',
                        $release->artist_id
                    )
                    ->pluck('user_id')
            );
        }

        if ($release->label_id) {
            $adminIds = $adminIds->merge(
                DB::table(
                    'admin_label_assignments'
                )
                    ->where(
                        'label_id',
                        $release->label_id
                    )
                    ->pluck('user_id')
            );
        }

        $adminIds = $adminIds
            ->unique()
            ->values();

        if ($adminIds->isNotEmpty()) {
            $query->where(
                function ($builder) use (
                    $adminIds
                ) {
                    $builder
                        ->where(
                            'role',
                            'super_admin'
                        )
                        ->orWhereIn(
                            'id',
                            $adminIds
                        );
                }
            );
        } else {
            $query->where(
                'role',
                'super_admin'
            );
        }

        $this->sendToUsers(
            $query->get(),
            $type,
            $title,
            $message,
            [
                'severity' =>
                    $severity,

                'action_url' =>
                    "/v2/admin/release-reviews/{$release->id}",

                'related_type' =>
                    Release::class,

                'related_id' =>
                    $release->id,
            ]
        );
    }

    private function ownerUsers(
        Release $release
    ): Collection {
        $users = collect();

        $artist = Artist::query()
            ->find(
                $release->artist_id
            );

        if ($artist?->user_id) {
            $user = User::query()->find(
                $artist->user_id
            );

            if ($user) {
                $users->push($user);
            }
        }

        $label = Label::query()
            ->find(
                $release->label_id
            );

        if ($label?->user_id) {
            $user = User::query()->find(
                $label->user_id
            );

            if ($user) {
                $users->push($user);
            }
        }

        return $users
            ->unique('id')
            ->values();
    }
}
