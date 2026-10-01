<?php

namespace App\Modules\Core\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail for financial, access, permission and configuration actions.
 */
class AuditService
{
    public static function log(string $module, string $action, ?Model $subject = null, ?string $description = null, ?array $old = null, ?array $new = null): void
    {
        $request = request();
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => $module,
            'action' => $action,
            'auditable_type' => $subject ? class_basename($subject) : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description ? mb_substr($description, 0, 500) : null,
            'old_values' => $old ? self::scrub($old) : null,
            'new_values' => $new ? self::scrub($new) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }

    /** Log an update using the model's dirty attributes (call before save()). */
    public static function logChanges(string $module, Model $model, string $action = 'updated', ?string $description = null): void
    {
        $dirty = $model->getDirty();
        if (! $dirty) return;
        $old = array_intersect_key($model->getOriginal(), $dirty);
        self::log($module, $action, $model, $description, $old, $dirty);
    }

    private static function scrub(array $values): array
    {
        foreach (['password', 'remember_token', 'id_number', 'passport_no', 'national_id', 'attendance_pin', 'token_hash', 'api_token_hash'] as $secret) {
            if (array_key_exists($secret, $values)) {
                $values[$secret] = '[redacted]';
            }
        }
        return $values;
    }
}
