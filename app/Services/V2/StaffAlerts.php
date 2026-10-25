<?php

namespace App\Services\V2;

use App\Mail\StaffAlertMail;
use App\Models\Support\PanelNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/*
 * W96 MIXXTUNE_STAFF_ALERTS
 * One place to warn staff: a bell notification in the panel and (optionally) an e-mail.
 */
class StaffAlerts
{
    /** @return Collection<int, User> */
    public function superAdmins(): Collection
    {
        return User::query()->where('role', 'super_admin')->where('account_status', 'active')->orderBy('id')->get();
    }

    public function bell(iterable $users, string $type, string $title, ?string $message, ?string $url, string $severity = 'info', array $data = []): int
    {
        $n = 0;
        foreach ($users as $user) {
            PanelNotification::query()->create([
                'public_id' => (string) Str::ulid(),
                'user_id' => $user->id,
                'type' => $type,
                'title' => mb_substr($title, 0, 250),
                'message' => $message,
                'action_url' => $url,
                'severity' => $severity,
                'category' => 'primary',
                'data' => $data,
            ]);
            $n++;
        }

        return $n;
    }

    /** @param array<int, string> $lines plain text lines (escaped here) */
    public function email(iterable $users, string $subject, array $lines, ?string $url = null): array
    {
        $sent = [];
        foreach ($users as $user) {
            if (!$user->email) {
                continue;
            }
            try {
                Mail::to($user->email)->send(new StaffAlertMail($subject, $user->full_name ?: $user->name ?: 'there', $lines, $url));
                $sent[] = $user->email;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }
}
