<?php

namespace App\Services\V2;

use App\Models\User;
use Illuminate\Support\Collection;

/*
 * MIXXTUNE_INVITATION_REMINDERS_V1
 * Owners (label / artist) and managers who have not accepted their invitation get a
 * reminder e-mail with a fresh 7-day link.
 *
 * Settings (Master Settings -> Invitation Reminders):
 *   invitation.reminders_enabled    on / off (default on)
 *   invitation.reminder_every_days  days after the last e-mail (default 3)
 *   invitation.reminder_max         reminders per person (default 3)
 *
 * Safety: only invitations whose last e-mail is between "every days" and 30 days old
 * are reminded, so very old invitations are never e-mailed by surprise.
 */
class InvitationReminderService
{
    public const MAX_AGE_DAYS = 30;

    public const OPEN_STATUSES = ['sent', 'expired'];

    public function __construct(
        private readonly SystemSettingService $settings,
        private readonly UserInvitationService $invitations,
        private readonly AuditLogService $audit
    ) {
    }

    public function config(): array
    {
        return [
            'enabled' => (bool) $this->settings->get('invitation.reminders_enabled', true),
            'every_days' => max(1, min(30, (int) $this->settings->get('invitation.reminder_every_days', 3))),
            'max' => max(0, min(10, (int) $this->settings->get('invitation.reminder_max', 3))),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function due(): Collection
    {
        $config = $this->config();

        if ($config['max'] === 0) {
            return collect();
        }

        return User::query()
            ->whereIn('role', ['label', 'artist', 'admin'])
            ->whereIn('invitation_status', self::OPEN_STATUSES)
            ->whereNull('invitation_accepted_at')
            ->where(function ($query) {
                $query->whereNull('account_status')->orWhere('account_status', '!=', 'active');
            })
            ->whereNotNull('invitation_sent_at')
            ->where('invitation_sent_at', '<=', now()->subDays($config['every_days']))
            ->where('invitation_sent_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            // invitation_count includes the first invitation
            ->where(function ($query) use ($config) {
                $query->whereNull('invitation_count')->orWhere('invitation_count', '<=', $config['max']);
            })
            ->whereNotNull('email')
            ->orderBy('invitation_sent_at')
            ->get();
    }

    /**
     * @return array{sent:int, failed:int, skipped:bool, people:array<int, string>}
     */
    public function run(bool $dryRun = false, bool $ignoreSwitch = false): array
    {
        $config = $this->config();

        if (!$config['enabled'] && !$ignoreSwitch) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'people' => []];
        }

        $sent = 0;
        $failed = 0;
        $people = [];

        foreach ($this->due() as $user) {
            $people[] = BankChangeGuard::maskEmail($user->email).' (#'.$user->id.', '.$user->role.', reminder '.max(1, (int) $user->invitation_count).' of '.$config['max'].')';

            if ($dryRun) {
                continue;
            }

            try {
                $this->invitations->send($user, true);
                $sent++;

                $this->audit->record(
                    'user.invitation_reminder_sent',
                    'users',
                    'Invitation reminder sent to '.$user->email.'.',
                    [],
                    ['invitation_count' => (int) $user->fresh()->invitation_count],
                    $user
                );
            } catch (\Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => false, 'people' => $people];
    }
}
