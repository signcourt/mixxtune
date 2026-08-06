#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/master-settings-audit/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Models/System \
    app/Services/V2 \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Admin/Settings \
    resources/js/Pages/V2/Admin/AuditLogs \
    v2/runtime/state

echo "=================================================="
echo "INSTALLING MASTER SETTINGS + AUDIT LOGS"
echo "=================================================="

for FILE in \
    routes/web.php \
    app/Http/Controllers/V2/Admin/SystemSettingsController.php \
    app/Http/Controllers/V2/Admin/AuditLogController.php \
    app/Models/System/SystemSetting.php \
    app/Models/System/AuditLog.php \
    app/Services/V2/SystemSettingService.php \
    app/Services/V2/AuditLogService.php
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done

echo "[1/8] Creating migration..."

MIGRATION="database/migrations/2026_08_01_000014_create_v2_system_settings_audit_tables.php"

if [ ! -f "$MIGRATION" ]; then
cat > "$MIGRATION" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) {
            Schema::create(
                'system_settings',
                function (Blueprint $table) {
                    $table->id();
                    $table->string('group', 100)->default('general');
                    $table->string('key', 190)->unique();
                    $table->longText('value')->nullable();
                    $table->string('type', 30)->default('string');
                    $table->boolean('is_public')->default(false);
                    $table->text('description')->nullable();
                    $table->unsignedBigInteger('updated_by')->nullable();
                    $table->timestamps();
                    $table->index(['group', 'key']);
                }
            );
        }

        if (!Schema::hasTable('audit_logs')) {
            Schema::create(
                'audit_logs',
                function (Blueprint $table) {
                    $table->id();
                    $table->string('public_id', 40)->unique();
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->string('action', 100);
                    $table->string('module', 100)->nullable();
                    $table->string('auditable_type')->nullable();
                    $table->unsignedBigInteger('auditable_id')->nullable();
                    $table->text('description')->nullable();
                    $table->json('old_values')->nullable();
                    $table->json('new_values')->nullable();
                    $table->string('ip_address', 64)->nullable();
                    $table->text('user_agent')->nullable();
                    $table->string('request_method', 10)->nullable();
                    $table->text('request_url')->nullable();
                    $table->timestamps();

                    $table->index(['user_id', 'created_at']);
                    $table->index(['module', 'action']);
                    $table->index(['auditable_type', 'auditable_id']);
                }
            );
        }
    }

    public function down(): void
    {
        // Production audit/settings data intentionally preserved.
    }
};
PHP
fi

echo "[2/8] Creating models..."

cat > app/Models/System/SystemSetting.php <<'PHP'
<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_public',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];
}
PHP

cat > app/Models/System/AuditLog.php <<'PHP'
<?php

namespace App\Models\System;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'action',
        'module',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'request_method',
        'request_url',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
PHP

echo "[3/8] Creating services..."

cat > app/Services/V2/SystemSettingService.php <<'PHP'
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
PHP

cat > app/Services/V2/AuditLogService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\System\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    public function record(
        string $action,
        string $module,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        mixed $auditable = null,
        ?Request $request = null
    ): AuditLog {
        $request ??= request();

        return AuditLog::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $request?->user()?->id,
            'action' => $action,
            'module' => $module,
            'auditable_type' =>
                is_object($auditable)
                    ? get_class($auditable)
                    : null,
            'auditable_id' =>
                is_object($auditable)
                && isset($auditable->id)
                    ? $auditable->id
                    : null,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_method' => $request?->method(),
            'request_url' => $request?->fullUrl(),
        ]);
    }
}
PHP

echo "[4/8] Creating Settings Controller..."

cat > app/Http/Controllers/V2/Admin/SystemSettingsController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use App\Services\V2\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        SystemSettingService $settings
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        return Inertia::render(
            'V2/Admin/Settings/Index',
            [
                'role' => 'super_admin',

                'settings' =>
                    array_replace_recursive(
                        $this->defaults(),
                        $settings->grouped()
                    ),

                'serverLimits' => [
                    'upload_max_filesize' =>
                        ini_get('upload_max_filesize'),

                    'post_max_size' =>
                        ini_get('post_max_size'),

                    'memory_limit' =>
                        ini_get('memory_limit'),

                    'max_execution_time' =>
                        ini_get('max_execution_time'),

                    'max_input_time' =>
                        ini_get('max_input_time'),
                ],
            ]
        );
    }

    public function update(
        Request $request,
        PermissionService $permissions,
        SystemSettingService $settings,
        AuditLogService $audit
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'company.name' => [
                'required',
                'string',
                'max:255',
            ],

            'company.legal_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'company.email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'company.phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'company.address' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'company.gst_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'company.pan_number' => [
                'nullable',
                'string',
                'max:20',
            ],

            'release.minimum_days_ahead' => [
                'required',
                'integer',
                'min:0',
                'max:365',
            ],

            'release.allow_explicit' => [
                'required',
                'boolean',
            ],

            'release.require_artwork' => [
                'required',
                'boolean',
            ],

            'release.require_wav' => [
                'required',
                'boolean',
            ],

            'upload.audio_max_mb' => [
                'required',
                'integer',
                'min:1',
                'max:2048',
            ],

            'upload.artwork_max_mb' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],

            'upload.report_max_mb' => [
                'required',
                'integer',
                'min:1',
                'max:2048',
            ],

            'finance.default_currency' => [
                'required',
                'string',
                'max:10',
            ],

            'finance.minimum_withdrawal' => [
                'required',
                'numeric',
                'min:0',
            ],

            'finance.default_commission_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'invoice.prefix' => [
                'required',
                'string',
                'max:20',
            ],

            'invoice.gst_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'invoice.tds_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        $oldValues = $settings->grouped();

        foreach (
            $this->schema()
            as $group => $fields
        ) {
            foreach ($fields as $key => $type) {
                $settings->set(
                    "{$group}.{$key}",
                    data_get(
                        $validated,
                        "{$group}.{$key}"
                    ),
                    $group,
                    $type,
                    $request->user()->id
                );
            }
        }

        $newValues = $settings->grouped();

        $audit->record(
            'settings.updated',
            'settings',
            'Master system settings updated.',
            $oldValues,
            $newValues,
            null,
            $request
        );

        return back()->with(
            'success',
            'System settings saved.'
        );
    }

    private function authorizeSuperAdmin(
        Request $request,
        PermissionService $permissions
    ): void {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403,
            'Super Admin access required.'
        );
    }

    private function defaults(): array
    {
        return [
            'company' => [
                'company.name' =>
                    config(
                        'app.name',
                        'MixxTune'
                    ),

                'company.legal_name' => '',
                'company.email' =>
                    config('mail.from.address'),

                'company.phone' => '',
                'company.address' => '',
                'company.gst_number' => '',
                'company.pan_number' => '',
            ],

            'release' => [
                'release.minimum_days_ahead' => 7,
                'release.allow_explicit' => true,
                'release.require_artwork' => true,
                'release.require_wav' => true,
            ],

            'upload' => [
                'upload.audio_max_mb' => 300,
                'upload.artwork_max_mb' => 20,
                'upload.report_max_mb' => 500,
            ],

            'finance' => [
                'finance.default_currency' => 'INR',
                'finance.minimum_withdrawal' => 1000,
                'finance.default_commission_percent' => 0,
            ],

            'invoice' => [
                'invoice.prefix' => 'INV',
                'invoice.gst_percent' => 0,
                'invoice.tds_percent' => 0,
            ],
        ];
    }

    private function schema(): array
    {
        return [
            'company' => [
                'name' => 'string',
                'legal_name' => 'string',
                'email' => 'string',
                'phone' => 'string',
                'address' => 'string',
                'gst_number' => 'string',
                'pan_number' => 'string',
            ],

            'release' => [
                'minimum_days_ahead' => 'integer',
                'allow_explicit' => 'boolean',
                'require_artwork' => 'boolean',
                'require_wav' => 'boolean',
            ],

            'upload' => [
                'audio_max_mb' => 'integer',
                'artwork_max_mb' => 'integer',
                'report_max_mb' => 'integer',
            ],

            'finance' => [
                'default_currency' => 'string',
                'minimum_withdrawal' => 'decimal',
                'default_commission_percent' =>
                    'decimal',
            ],

            'invoice' => [
                'prefix' => 'string',
                'gst_percent' => 'decimal',
                'tds_percent' => 'decimal',
            ],
        ];
    }
}
PHP

echo "[5/8] Creating Audit Controller..."

cat > app/Http/Controllers/V2/Admin/AuditLogController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\System\AuditLog;
use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $module = trim(
            (string) $request->input(
                'module',
                ''
            )
        );

        $query = AuditLog::query()
            ->with(
                'user:id,name,email'
            );

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'action',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'ip_address',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($module !== '') {
            $query->where(
                'module',
                $module
            );
        }

        return Inertia::render(
            'V2/Admin/AuditLogs/Index',
            [
                'role' => $role,

                'filters' => [
                    'search' => $search,
                    'module' => $module,
                ],

                'modules' =>
                    AuditLog::query()
                        ->whereNotNull('module')
                        ->distinct()
                        ->orderBy('module')
                        ->pluck('module'),

                'logs' =>
                    $query
                        ->orderByDesc('id')
                        ->paginate(50)
                        ->withQueryString(),
            ]
        );
    }
}
PHP

echo "[6/8] Creating Settings Page..."

cat > resources/js/Pages/V2/Admin/Settings/Index.jsx <<'JSX'
import { Head, useForm } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'super_admin',
    settings = {},
    serverLimits = {},
}) {
    const getValue = (group, key, fallback = '') =>
        settings?.[group]?.[`${group}.${key}`] ?? fallback;

    const {
        data,
        setData,
        patch,
        processing,
        errors,
    } = useForm({
        company: {
            name: getValue('company', 'name', 'MixxTune'),
            legal_name: getValue('company', 'legal_name'),
            email: getValue('company', 'email'),
            phone: getValue('company', 'phone'),
            address: getValue('company', 'address'),
            gst_number: getValue('company', 'gst_number'),
            pan_number: getValue('company', 'pan_number'),
        },

        release: {
            minimum_days_ahead: Number(
                getValue('release', 'minimum_days_ahead', 7)
            ),
            allow_explicit: Boolean(
                getValue('release', 'allow_explicit', true)
            ),
            require_artwork: Boolean(
                getValue('release', 'require_artwork', true)
            ),
            require_wav: Boolean(
                getValue('release', 'require_wav', true)
            ),
        },

        upload: {
            audio_max_mb: Number(
                getValue('upload', 'audio_max_mb', 300)
            ),
            artwork_max_mb: Number(
                getValue('upload', 'artwork_max_mb', 20)
            ),
            report_max_mb: Number(
                getValue('upload', 'report_max_mb', 500)
            ),
        },

        finance: {
            default_currency: getValue(
                'finance',
                'default_currency',
                'INR'
            ),
            minimum_withdrawal: Number(
                getValue('finance', 'minimum_withdrawal', 1000)
            ),
            default_commission_percent: Number(
                getValue(
                    'finance',
                    'default_commission_percent',
                    0
                )
            ),
        },

        invoice: {
            prefix: getValue('invoice', 'prefix', 'INV'),
            gst_percent: Number(
                getValue('invoice', 'gst_percent', 0)
            ),
            tds_percent: Number(
                getValue('invoice', 'tds_percent', 0)
            ),
        },
    });

    const update = (group, field, value) => {
        setData(group, {
            ...data[group],
            [field]: value,
        });
    };

    const submit = (event) => {
        event.preventDefault();

        patch('/v2/admin/settings', {
            preserveScroll: true,
        });
    };

    return (
        <PanelLayout
            role={role}
            title="Master Settings"
            subtitle="Company, release, upload and finance configuration"
        >
            <Head title="Master Settings" />

            <form onSubmit={submit} className="space-y-6">
                <Section title="Company Profile">
                    <Grid>
                        <Field
                            label="Company Name"
                            value={data.company.name}
                            onChange={(value) =>
                                update('company', 'name', value)
                            }
                        />

                        <Field
                            label="Legal Name"
                            value={data.company.legal_name}
                            onChange={(value) =>
                                update('company', 'legal_name', value)
                            }
                        />

                        <Field
                            label="Email"
                            type="email"
                            value={data.company.email}
                            onChange={(value) =>
                                update('company', 'email', value)
                            }
                        />

                        <Field
                            label="Phone"
                            value={data.company.phone}
                            onChange={(value) =>
                                update('company', 'phone', value)
                            }
                        />

                        <Field
                            label="GST Number"
                            value={data.company.gst_number}
                            onChange={(value) =>
                                update('company', 'gst_number', value)
                            }
                        />

                        <Field
                            label="PAN Number"
                            value={data.company.pan_number}
                            onChange={(value) =>
                                update('company', 'pan_number', value)
                            }
                        />

                        <Field
                            label="Address"
                            value={data.company.address}
                            onChange={(value) =>
                                update('company', 'address', value)
                            }
                            wide
                        />
                    </Grid>
                </Section>

                <Section title="Release Rules">
                    <Grid>
                        <Field
                            label="Minimum Days Before Release"
                            type="number"
                            value={data.release.minimum_days_ahead}
                            onChange={(value) =>
                                update(
                                    'release',
                                    'minimum_days_ahead',
                                    Number(value)
                                )
                            }
                        />

                        <Toggle
                            label="Allow Explicit Content"
                            checked={data.release.allow_explicit}
                            onChange={(checked) =>
                                update(
                                    'release',
                                    'allow_explicit',
                                    checked
                                )
                            }
                        />

                        <Toggle
                            label="Artwork Required"
                            checked={data.release.require_artwork}
                            onChange={(checked) =>
                                update(
                                    'release',
                                    'require_artwork',
                                    checked
                                )
                            }
                        />

                        <Toggle
                            label="WAV Audio Required"
                            checked={data.release.require_wav}
                            onChange={(checked) =>
                                update(
                                    'release',
                                    'require_wav',
                                    checked
                                )
                            }
                        />
                    </Grid>
                </Section>

                <Section title="Upload Rules">
                    <Grid>
                        <Field
                            label="Audio Maximum MB"
                            type="number"
                            value={data.upload.audio_max_mb}
                            onChange={(value) =>
                                update(
                                    'upload',
                                    'audio_max_mb',
                                    Number(value)
                                )
                            }
                        />

                        <Field
                            label="Artwork Maximum MB"
                            type="number"
                            value={data.upload.artwork_max_mb}
                            onChange={(value) =>
                                update(
                                    'upload',
                                    'artwork_max_mb',
                                    Number(value)
                                )
                            }
                        />

                        <Field
                            label="Report Maximum MB"
                            type="number"
                            value={data.upload.report_max_mb}
                            onChange={(value) =>
                                update(
                                    'upload',
                                    'report_max_mb',
                                    Number(value)
                                )
                            }
                        />
                    </Grid>

                    <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div className="font-semibold text-amber-800">
                            Current PHP Server Limits
                        </div>

                        <div className="mt-3 grid gap-2 text-sm text-amber-700 sm:grid-cols-2 xl:grid-cols-5">
                            {Object.entries(serverLimits).map(
                                ([key, value]) => (
                                    <div key={key}>
                                        <strong>{key}</strong>: {value}
                                    </div>
                                )
                            )}
                        </div>
                    </div>
                </Section>

                <Section title="Finance & Invoice">
                    <Grid>
                        <Field
                            label="Default Currency"
                            value={data.finance.default_currency}
                            onChange={(value) =>
                                update(
                                    'finance',
                                    'default_currency',
                                    value
                                )
                            }
                        />

                        <Field
                            label="Minimum Withdrawal"
                            type="number"
                            value={data.finance.minimum_withdrawal}
                            onChange={(value) =>
                                update(
                                    'finance',
                                    'minimum_withdrawal',
                                    Number(value)
                                )
                            }
                        />

                        <Field
                            label="Default Commission %"
                            type="number"
                            value={
                                data.finance.default_commission_percent
                            }
                            onChange={(value) =>
                                update(
                                    'finance',
                                    'default_commission_percent',
                                    Number(value)
                                )
                            }
                        />

                        <Field
                            label="Invoice Prefix"
                            value={data.invoice.prefix}
                            onChange={(value) =>
                                update('invoice', 'prefix', value)
                            }
                        />

                        <Field
                            label="GST %"
                            type="number"
                            value={data.invoice.gst_percent}
                            onChange={(value) =>
                                update(
                                    'invoice',
                                    'gst_percent',
                                    Number(value)
                                )
                            }
                        />

                        <Field
                            label="TDS %"
                            type="number"
                            value={data.invoice.tds_percent}
                            onChange={(value) =>
                                update(
                                    'invoice',
                                    'tds_percent',
                                    Number(value)
                                )
                            }
                        />
                    </Grid>
                </Section>

                {Object.keys(errors).length > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        कुछ settings save नहीं हुईं। Entered values check करें।
                    </div>
                )}

                <div className="sticky bottom-4 flex justify-end">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-7 py-3 text-sm font-semibold text-white shadow-lg disabled:opacity-50"
                    >
                        {processing ? 'Saving...' : 'Save Settings'}
                    </button>
                </div>
            </form>
        </PanelLayout>
    );
}

function Section({ title, children }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">
                {title}
            </h2>

            <div className="mt-5">{children}</div>
        </section>
    );
}

function Grid({ children }) {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {children}
        </div>
    );
}

function Field({
    label,
    value,
    onChange,
    type = 'text',
    wide = false,
}) {
    return (
        <label
            className={
                wide
                    ? 'md:col-span-2 xl:col-span-3'
                    : ''
            }
        >
            <span className="text-sm font-semibold text-slate-700">
                {label}
            </span>

            <input
                type={type}
                value={value ?? ''}
                onChange={(event) =>
                    onChange(event.target.value)
                }
                className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
            />
        </label>
    );
}

function Toggle({ label, checked, onChange }) {
    return (
        <label className="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) =>
                    onChange(event.target.checked)
                }
            />

            <span className="text-sm font-semibold text-slate-700">
                {label}
            </span>
        </label>
    );
}
JSX

echo "[7/8] Creating Audit Logs Page..."

cat > resources/js/Pages/V2/Admin/AuditLogs/Index.jsx <<'JSX'
import { Head, Link, router } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    logs = {},
    modules = [],
    filters = {},
}) {
    const update = (changes) => {
        router.get(
            '/v2/admin/audit-logs',
            {
                ...filters,
                ...changes,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Audit Logs"
            subtitle="System changes and user activity"
        >
            <Head title="Audit Logs" />

            <div className="space-y-5">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-[1fr_220px_auto]">
                        <input
                            type="search"
                            defaultValue={filters.search ?? ''}
                            placeholder="Search action, description or IP..."
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    update({
                                        search:
                                            event.currentTarget.value,
                                    });
                                }
                            }}
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <select
                            value={filters.module ?? ''}
                            onChange={(event) =>
                                update({
                                    module: event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All Modules</option>

                            {modules.map((module) => (
                                <option
                                    key={module}
                                    value={module}
                                >
                                    {module}
                                </option>
                            ))}
                        </select>

                        <button
                            type="button"
                            onClick={() =>
                                router.get(
                                    '/v2/admin/audit-logs'
                                )
                            }
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                        >
                            Clear
                        </button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'Date',
                                        'User',
                                        'Module',
                                        'Action',
                                        'Description',
                                        'IP',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {(logs.data ?? []).map((log) => (
                                    <tr key={log.id}>
                                        <Cell>
                                            {log.created_at}
                                        </Cell>

                                        <Cell>
                                            <div className="font-semibold text-slate-900">
                                                {log.user?.name ??
                                                    'System'}
                                            </div>

                                            <div className="text-xs text-slate-500">
                                                {log.user?.email ?? ''}
                                            </div>
                                        </Cell>

                                        <Cell>
                                            {log.module ?? '—'}
                                        </Cell>

                                        <Cell>{log.action}</Cell>

                                        <Cell>
                                            {log.description ?? '—'}
                                        </Cell>

                                        <Cell>
                                            {log.ip_address ?? '—'}
                                        </Cell>
                                    </tr>
                                ))}

                                {(logs.data ?? []).length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            अभी कोई audit log नहीं है।
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {logs.links && (
                    <div className="flex flex-wrap justify-center gap-2">
                        {logs.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={[
                                    'rounded-lg border px-3 py-2 text-sm',
                                    link.active
                                        ? 'border-violet-600 bg-violet-600 text-white'
                                        : 'border-slate-300 bg-white text-slate-700',
                                    !link.url
                                        ? 'pointer-events-none opacity-40'
                                        : '',
                                ].join(' ')}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX

echo "[8/8] Adding routes, migrating and building..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.admin.settings.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/settings',
        [\App\Http\Controllers\V2\Admin\SystemSettingsController::class, 'index']
    )
    ->name('v2.admin.settings.index');
""",

    "v2.admin.settings.update": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/settings',
        [\App\Http\Controllers\V2\Admin\SystemSettingsController::class, 'update']
    )
    ->name('v2.admin.settings.update');
""",

    "v2.admin.audit-logs.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/audit-logs',
        [\App\Http\Controllers\V2\Admin\AuditLogController::class, 'index']
    )
    ->name('v2.admin.audit-logs.index');
""",
}

added = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        added += 1

path.write_text(text)

print(f"{added} settings/audit routes added.")
PY

echo ""
echo "===== RUNNING MIGRATION ====="

php artisan migrate --force

echo ""
echo "===== PHP SYNTAX CHECKS ====="

php -l app/Models/System/SystemSetting.php
php -l app/Models/System/AuditLog.php
php -l app/Services/V2/SystemSettingService.php
php -l app/Services/V2/AuditLogService.php
php -l app/Http/Controllers/V2/Admin/SystemSettingsController.php
php -l app/Http/Controllers/V2/Admin/AuditLogController.php
php -l routes/web.php

echo ""
echo "===== FRONTEND BUILD ====="

npm run build

echo ""
echo "===== CLEARING CACHE ====="

php artisan optimize:clear

printf '{\n  "module": "MasterSettingsAudit",\n  "installed": true,\n  "version": "4.6.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/master-settings-audit-installed.json

echo ""
echo "===== SETTINGS & AUDIT ROUTES ====="

php artisan route:list | grep -E \
"v2/admin/(settings|audit-logs)"

echo ""
echo "=================================================="
echo "MASTER SETTINGS + AUDIT LOGS INSTALLED"
echo "=================================================="

cat v2/runtime/state/master-settings-audit-installed.json

echo ""
echo "Settings:"
echo "https://admin.mixxtune.com/v2/admin/settings"

echo ""
echo "Audit Logs:"
echo "https://admin.mixxtune.com/v2/admin/audit-logs"

echo ""
echo "Backup:"
echo "$BACKUP"
