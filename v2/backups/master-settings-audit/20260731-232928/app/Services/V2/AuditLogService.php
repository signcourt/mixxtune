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
