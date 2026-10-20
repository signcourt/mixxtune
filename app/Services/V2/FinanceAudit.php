<?php

namespace App\Services\V2;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/*
 * MIXXTUNE_FINANCE_SAFETY_V1
 *
 * One line in the audit log for every action that moves or schedules
 * money (report approval / deletion, statement approval, hold release,
 * wallet adjustments, recoupment, withdrawals). A failing audit write is
 * reported but never blocks the money action itself.
 */
class FinanceAudit
{
    public static function log(
        string $action,
        string $description,
        array $new = [],
        mixed $auditable = null,
        array $old = []
    ): void {
        try {
            app(AuditLogService::class)->record(
                $action,
                'finance',
                $description,
                $old,
                $new,
                $auditable,
                request()
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function pick(?Model $model, array $keys): array
    {
        if (!$model) {
            return [];
        }

        $attributes = $model->getAttributes();

        $values = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $attributes)) {
                $values[$key] = $attributes[$key];
            }
        }

        return $values;
    }
}
