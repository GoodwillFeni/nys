<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * Log a create action
     */
    public static function logCreate(Model $model, Request $request, ?string $description = null): void
    {
        self::log('created', $model, null, $model->getAttributes(), $request, $description);
    }

    /**
     * Log an update action
     */
    public static function logUpdate(Model $model, array $oldValues, Request $request, ?string $description = null): void
    {
        self::log('updated', $model, $oldValues, $model->getAttributes(), $request, $description);
    }

    /**
     * Log a delete action
     */
    public static function logDelete(Model $model, Request $request, ?string $description = null): void
    {
        self::log('deleted', $model, $model->getAttributes(), null, $request, $description);
    }

    /**
     * Generic log method
     */
    protected static function log(
        string $action,
        Model $model,
        ?array $oldValues,
        ?array $newValues,
        Request $request,
        ?string $description = null
    ): void {
        $user = $request->user();
        $accountId = self::getAccountId($request);

        // Generate description if not provided
        if (!$description) {
            $modelName = class_basename($model);
            $description = ucfirst($action) . " {$modelName}";
            if ($model->id) {
                $description .= " (ID: {$model->id})";
            }
        }

        AuditLog::create([
            'user_id' => $user?->id,
            'account_id' => $accountId,
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->id ?? null,
            'old_values' => $oldValues ? self::sanitizeValues($oldValues) : null,
            'new_values' => $newValues ? self::sanitizeValues($newValues) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'description' => $description
        ]);
    }

    /**
     * Get account ID from request header or active account
     */
    protected static function getAccountId(Request $request): ?int
    {
        // Try to get from header first
        $accountId = $request->header('X-Account-ID');
        if ($accountId) {
            return (int) $accountId;
        }

        // Fallback to user's first account
        $user = $request->user();
        if ($user) {
            $account = $user->accounts()->first();
            return $account?->id;
        }

        return null;
    }

    /**
     * Sanitize values to remove sensitive data
     */
    protected static function sanitizeValues(array $values): array
    {
        $sensitiveFields = ['password', 'password_hash', 'token', 'api_key', 'secret'];

        foreach ($sensitiveFields as $field) {
            if (isset($values[$field])) {
                $values[$field] = '***REDACTED***';
            }
        }

        return $values;
    }
}
