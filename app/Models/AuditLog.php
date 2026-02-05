<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_type',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Event type constants
     */
    public const EVENT_AUTH = 'auth';

    public const EVENT_DATA_ACCESS = 'data_access';

    public const EVENT_DATA_CHANGE = 'data_change';

    public const EVENT_ADMIN = 'admin';

    public const EVENT_GDPR = 'gdpr';

    /**
     * Auth action constants
     */
    public const ACTION_LOGIN_ATTEMPT = 'login_attempt';

    public const ACTION_LOGIN_SUCCESS = 'login_success';

    public const ACTION_LOGIN_FAILED = 'login_failed';

    public const ACTION_LOGOUT = 'logout';

    public const ACTION_MFA_ENABLED = 'mfa_enabled';

    public const ACTION_MFA_DISABLED = 'mfa_disabled';

    public const ACTION_MFA_VERIFIED = 'mfa_verified';

    public const ACTION_PASSWORD_CHANGED = 'password_changed';

    public const ACTION_PASSWORD_RESET_REQUESTED = 'password_reset_requested';

    public const ACTION_PASSWORD_RESET_COMPLETED = 'password_reset_completed';

    public const ACTION_SESSION_REVOKED = 'session_revoked';

    /**
     * Data action constants
     */
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    /**
     * GDPR action constants
     */
    public const ACTION_EXPORT_REQUESTED = 'export_requested';

    public const ACTION_EXPORT_COMPLETED = 'export_completed';

    public const ACTION_ERASURE_REQUESTED = 'erasure_requested';

    public const ACTION_ERASURE_COMPLETED = 'erasure_completed';

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the auditable model
     */
    public function auditable(): ?Model
    {
        if (! $this->model_type || ! $this->model_id) {
            return null;
        }

        return $this->model_type::find($this->model_id);
    }

    /**
     * Create an audit log entry
     */
    public static function log(
        string $eventType,
        string $action,
        ?int $userId = null,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'user_id' => $userId ?? auth()->id(),
            'event_type' => $eventType,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log an authentication event
     */
    public static function logAuth(string $action, ?int $userId = null, ?array $metadata = null): self
    {
        return self::log(self::EVENT_AUTH, $action, $userId, null, null, null, null, $metadata);
    }

    /**
     * Log a data change event
     */
    public static function logDataChange(
        string $action,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null
    ): self {
        return self::log(
            self::EVENT_DATA_CHANGE,
            $action,
            auth()->id(),
            get_class($model),
            $model->getKey(),
            $oldValues,
            $newValues
        );
    }

    /**
     * Log a GDPR event
     */
    public static function logGDPR(string $action, int $userId, ?array $metadata = null): self
    {
        return self::log(self::EVENT_GDPR, $action, $userId, null, null, null, null, $metadata);
    }

    /**
     * Log an admin action
     */
    public static function logAdmin(string $action, ?array $metadata = null): self
    {
        return self::log(self::EVENT_ADMIN, $action, auth()->id(), null, null, null, null, $metadata);
    }

    /**
     * Get human-readable action name
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_LOGIN_ATTEMPT => 'Login attempted',
            self::ACTION_LOGIN_SUCCESS => 'Login successful',
            self::ACTION_LOGIN_FAILED => 'Login failed',
            self::ACTION_LOGOUT => 'Logged out',
            self::ACTION_MFA_ENABLED => 'MFA enabled',
            self::ACTION_MFA_DISABLED => 'MFA disabled',
            self::ACTION_MFA_VERIFIED => 'MFA verified',
            self::ACTION_PASSWORD_CHANGED => 'Password changed',
            self::ACTION_PASSWORD_RESET_REQUESTED => 'Password reset requested',
            self::ACTION_PASSWORD_RESET_COMPLETED => 'Password reset completed',
            self::ACTION_SESSION_REVOKED => 'Session revoked',
            self::ACTION_CREATED => 'Created',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_DELETED => 'Deleted',
            self::ACTION_EXPORT_REQUESTED => 'Data export requested',
            self::ACTION_EXPORT_COMPLETED => 'Data export completed',
            self::ACTION_ERASURE_REQUESTED => 'Account deletion requested',
            self::ACTION_ERASURE_COMPLETED => 'Account deletion completed',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Scope to filter by event type
     */
    public function scopeByEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by model
     */
    public function scopeByModel(Builder $query, string $modelType, ?int $modelId = null): Builder
    {
        $query->where('model_type', $modelType);

        if ($modelId) {
            $query->where('model_id', $modelId);
        }

        return $query;
    }

    /**
     * Scope for recent entries
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
