<?php

namespace App\Services\V2;

use App\Models\System\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettingService
{
    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        $setting = Cache::remember(
            "system_setting:{$key}",
            3600,
            fn () => SystemSetting::query()
                ->where('key', $key)
                ->first()
        );

        if (!$setting) {
            return $default;
        }

        return $this->castValue(
            $setting->value,
            $setting->type
        );
    }

    public function set(
        string $key,
        mixed $value,
        string $group = 'general',
        string $type = 'string',
        ?int $updatedBy = null
    ): SystemSetting {
        $setting = SystemSetting::query()
            ->updateOrCreate(
                ['key' => $key],
                [
                    'group' => $group,
                    'value' => $this->serializeValue(
                        $value,
                        $type
                    ),
                    'type' => $type,
                    'updated_by' => $updatedBy,
                ]
            );

        Cache::forget(
            "system_setting:{$key}"
        );

        return $setting;
    }

    public function grouped(): array
    {
        return SystemSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(
                fn ($rows) => $rows
                    ->mapWithKeys(
                        fn ($setting) => [
                            $setting->key =>
                                $this->castValue(
                                    $setting->value,
                                    $setting->type
                                ),
                        ]
                    )
                    ->all()
            )
            ->all();
    }

    private function castValue(
        mixed $value,
        string $type
    ): mixed {
        return match ($type) {
            'boolean' => filter_var(
                $value,
                FILTER_VALIDATE_BOOLEAN
            ),

            'integer' => (int) $value,

            'decimal',
            'float' => (float) $value,

            'array',
            'json' => json_decode(
                $value ?: '[]',
                true
            ) ?: [],

            default => $value,
        };
    }

    private function serializeValue(
        mixed $value,
        string $type
    ): string {
        return match ($type) {
            'boolean' => $value ? '1' : '0',

            'array',
            'json' => json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
            ),

            default => (string) $value,
        };
    }
}
