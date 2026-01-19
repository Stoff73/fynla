<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /**
     * Log an authentication event
     */
    public function logAuth(string $action, ?User $user = null, array $metadata = []): AuditLog
    {
        return AuditLog::logAuth($action, $user?->id, $metadata ?: null);
    }

    /**
     * Log a data access event
     */
    public function logDataAccess(string $action, ?Model $model = null, array $metadata = []): AuditLog
    {
        return AuditLog::log(
            AuditLog::EVENT_DATA_ACCESS,
            $action,
            auth()->id(),
            $model ? get_class($model) : null,
            $model?->getKey(),
            null,
            null,
            $metadata ?: null
        );
    }

    /**
     * Log a data change event
     */
    public function logDataChange(
        string $action,
        Model $model,
        array $oldValues = [],
        array $newValues = []
    ): AuditLog {
        return AuditLog::logDataChange(
            $action,
            $model,
            $oldValues ?: null,
            $newValues ?: null
        );
    }

    /**
     * Log an admin action
     */
    public function logAdmin(string $action, array $metadata = []): AuditLog
    {
        return AuditLog::logAdmin($action, $metadata ?: null);
    }

    /**
     * Log a GDPR event
     */
    public function logGDPR(string $action, int $userId, array $metadata = []): AuditLog
    {
        return AuditLog::logGDPR($action, $userId, $metadata ?: null);
    }

    /**
     * Get audit logs for a user
     */
    public function getUserLogs(User $user, int $limit = 100): \Illuminate\Support\Collection
    {
        return AuditLog::byUser($user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent auth logs
     */
    public function getRecentAuthLogs(int $limit = 100): \Illuminate\Support\Collection
    {
        return AuditLog::byEventType(AuditLog::EVENT_AUTH)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs for a specific model
     */
    public function getModelLogs(string $modelType, ?int $modelId = null, int $limit = 100): \Illuminate\Support\Collection
    {
        return AuditLog::byModel($modelType, $modelId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get failed login attempts
     */
    public function getFailedLoginAttempts(int $hours = 24, int $limit = 100): \Illuminate\Support\Collection
    {
        return AuditLog::byEventType(AuditLog::EVENT_AUTH)
            ->where('action', AuditLog::ACTION_LOGIN_FAILED)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
