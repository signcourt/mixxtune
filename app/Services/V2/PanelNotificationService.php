<?php

namespace App\Services\V2;

use App\Models\Support\PanelNotification;
use App\Models\User;
use Illuminate\Support\Str;

class PanelNotificationService
{
    public function send(
        User $user,
        string $type,
        string $title,
        ?string $message = null,
        ?string $actionUrl = null,
        array $data = []
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
                $actionUrl,

            'data' =>
                $data,
        ]);
    }
}
