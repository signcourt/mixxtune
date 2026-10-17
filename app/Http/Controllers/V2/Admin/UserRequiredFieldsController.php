<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\AuditLogService;
use App\Services\V2\UserFieldRequirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/* MIXXTUNE_USER_REQUIRED_FIELDS_V1 */
class UserRequiredFieldsController extends Controller
{
    public function update(Request $request, UserFieldRequirementService $fields)
    {
        abort_unless($request->user()?->role === 'super_admin', 403);

        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*' => ['array'],
            'fields.*.*' => ['boolean'],
        ]);

        $old = $fields->all();
        $new = $fields->save($data['fields']);

        $this->audit($request, $old, $new);

        return response()->json([
            'ok' => true,
            'message' => 'Required fields saved.',
            'requiredFields' => $new,
        ]);
    }

    private function audit(Request $request, array $old, array $new): void
    {
        try {
            if (Schema::hasTable('audit_logs')) {
                app(AuditLogService::class)->record(
                    'users.required_fields_updated',
                    'users',
                    'Create User required fields changed.',
                    $old,
                    $new,
                    $request->user(),
                    $request
                );

                return;
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        Log::info('users.required_fields_updated', ['by' => $request->user()?->id, 'old' => $old, 'new' => $new]);
    }
}
