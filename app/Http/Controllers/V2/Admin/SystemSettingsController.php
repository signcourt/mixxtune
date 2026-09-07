<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use App\Services\V2\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'branding.name' => [
                'required',
                'string',
                'max:255',
            ],

            'branding.subtitle' => [
                'nullable',
                'string',
                'max:255',
            ],

            'branding.logo_url' => [
                'required',
                'string',
                'max:1000',
            ],

            'branding.login_logo_url' => [
                'required',
                'string',
                'max:1000',
            ],

            'branding.favicon_url' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'branding.footer_text' => [
                'nullable',
                'string',
                'max:500',
            ],

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

    public function uploadBrandingAsset(
        Request $request,
        PermissionService $permissions
    ) {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'type' => [
                'required',
                'in:panel_logo,login_logo,favicon',
            ],

            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,svg,ico',
                'max:5120',
            ],
        ]);

        $file = $request->file('file');

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        $allowedExtensions = [
            'jpg',
            'jpeg',
            'png',
            'webp',
            'svg',
            'ico',
        ];

        abort_unless(
            in_array(
                $extension,
                $allowedExtensions,
                true
            ),
            422,
            'Unsupported branding file type.'
        );

        $directory =
            'branding/' .
            $validated['type'];

        $filename =
            $validated['type'] .
            '-' .
            now()->format('YmdHis') .
            '-' .
            bin2hex(random_bytes(4)) .
            '.' .
            $extension;

        $path = $file->storeAs(
            $directory,
            $filename,
            'public'
        );

        abort_unless(
            is_string($path) &&
            $path !== '',
            500,
            'Unable to store branding asset.'
        );

        return response()->json([
            'success' => true,

            'type' =>
                $validated['type'],

            'path' =>
                '/storage/' . $path,

            'url' =>
                Storage::disk('public')->url(
                    $path
                ),
        ]);
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
            'branding' => [
                'branding.name' => 'MIXX TUNE',
                'branding.subtitle' => '',
                'branding.logo_url' =>
                    '/images/mixx-tune-login-logo.svg',
                'branding.login_logo_url' =>
                    '/images/mixx-tune-login-logo.svg',
                'branding.favicon_url' => '',
                'branding.footer_text' => '',
            ],

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
            'branding' => [
                'name' => 'string',
                'subtitle' => 'string',
                'logo_url' => 'string',
                'login_logo_url' => 'string',
                'favicon_url' => 'string',
                'footer_text' => 'string',
            ],

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
