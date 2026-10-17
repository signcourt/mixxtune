<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\Storage;

/*
 * MIXXTUNE_USER_REQUIRED_FIELDS_V1
 * Which details the Users > Create form demands, per role. Stored as a
 * small JSON file (no DB change). Name, email, role, revenue share and
 * country code are always required (locked) and are not configurable.
 */
class UserFieldRequirementService
{
    private const DISK = 'local';

    private const LABELS = [
        'username' => 'Username',
        'phone' => 'Phone',
        'address_line_1' => 'Address Line 1',
        'address_line_2' => 'Address Line 2',
        'city' => 'City',
        'state' => 'State',
        'postal_code' => 'Postal Code',
        'pan_number' => 'PAN Number',
        'gst_number' => 'GST Number',
        'account_holder_name' => 'Account Holder Name',
        'bank_account_number' => 'Bank Account Number',
        'bank_name' => 'Bank Name',
        'ifsc_code' => 'IFSC Code',
        'branch_name' => 'Branch Name',
        'upi_id' => 'UPI ID',
    ];

    private const PAYEE_DEFAULTS = [
        'username' => false,
        'phone' => false,
        'address_line_1' => true,
        'address_line_2' => false,
        'city' => true,
        'state' => true,
        'postal_code' => true,
        'pan_number' => true,
        'gst_number' => false,
        'account_holder_name' => true,
        'bank_account_number' => true,
        'bank_name' => true,
        'ifsc_code' => true,
        'branch_name' => false,
        'upi_id' => false,
    ];

    private const LOCKED = [
        'label' => ['name' => 'Owner Name', 'email' => 'Email', 'revenue_share_percentage' => 'Revenue Share (%)', 'country_code' => 'Country'],
        'artist' => ['name' => 'Artist Name', 'email' => 'Email', 'revenue_share_percentage' => 'Revenue Share (%)', 'country_code' => 'Country'],
        'admin' => ['name' => 'Manager Name', 'email' => 'Email'],
    ];

    private ?array $cache = null;

    public static function defaults(): array
    {
        return [
            'label' => self::PAYEE_DEFAULTS,
            'artist' => self::PAYEE_DEFAULTS,
            'admin' => ['username' => false, 'phone' => false],
        ];
    }

    public static function options(): array
    {
        $fields = [];
        $locked = [];

        foreach (self::defaults() as $role => $map) {
            foreach (array_keys($map) as $key) {
                $fields[$role][] = ['key' => $key, 'label' => self::LABELS[$key] ?? $key];
            }
            foreach (self::LOCKED[$role] as $key => $label) {
                $locked[$role][] = ['key' => $key, 'label' => $label];
            }
        }

        return ['fields' => $fields, 'locked' => $locked];
    }

    private function path(): string
    {
        return app()->runningUnitTests()
            ? 'mixxtune-testing/user-required-fields.json'
            : 'mixxtune-settings/user-required-fields.json';
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stored = [];

        try {
            $disk = Storage::disk(self::DISK);
            if ($disk->exists($this->path())) {
                $decoded = json_decode((string) $disk->get($this->path()), true);
                if (is_array($decoded)) {
                    $stored = $decoded;
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $this->cache = $this->merge($stored);
    }

    private function merge(array $input): array
    {
        $out = self::defaults();

        foreach ($out as $role => $map) {
            foreach ($map as $key => $default) {
                if (isset($input[$role]) && is_array($input[$role]) && array_key_exists($key, $input[$role])) {
                    $out[$role][$key] = filter_var($input[$role][$key], FILTER_VALIDATE_BOOLEAN);
                }
            }
        }

        return $out;
    }

    public function isRequired(?string $role, string $field): bool
    {
        $all = $this->all();

        return (bool) ($all[(string) $role][$field] ?? false);
    }

    public function save(array $input): array
    {
        $clean = $this->merge(array_replace_recursive($this->all(), $input));

        Storage::disk(self::DISK)->put($this->path(), json_encode($clean, JSON_PRETTY_PRINT));

        return $this->cache = $clean;
    }
}
