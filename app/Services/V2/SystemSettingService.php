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
            function () use ($key) {
                $row = SystemSetting::query()
                    ->where('key', $key)
                    ->first([
                        'value',
                        'type',
                    ]);

                if (!$row) {
                    return null;
                }

                return [
                    'value' => $row->value,
                    'type' => $row->type,
                ];
            }
        );

        if (!is_array($setting)) {
            return $default;
        }

        return $this->castValue(
            $setting['value'] ?? null,
            $setting['type'] ?? 'string'
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

    /**
     * Central Mixx Tune branding configuration.
     *
     * All authenticated panels and guest/auth pages can consume the
     * same branding values through the shared Inertia `brand` prop.
     */
    public function branding(): array
    {
        return [
            'logo_url' => $this->get(
                'branding.logo_url',
                '/images/mixx-tune-login-logo.svg'
            ),

            'name' => $this->get(
                'branding.name',
                'MIXX TUNE'
            ),

            'subtitle' => $this->get(
                'branding.subtitle',
                ''
            ),

            'login_logo_url' => $this->get(
                'branding.login_logo_url',
                '/images/mixx-tune-login-logo.svg'
            ),

            'favicon_url' => $this->get(
                'branding.favicon_url',
                ''
            ),

            'footer_text' => $this->get(
                'branding.footer_text',
                ''
            ),
        ];
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
