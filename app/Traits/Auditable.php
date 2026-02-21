<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLog;

/**
 * Trait to automatically audit model changes (create, update, delete).
 *
 * Usage:
 * 1. Add `use Auditable;` to your model
 * 2. Optionally define `$auditableFields` to limit which fields are audited
 * 3. Optionally define `$auditExcludeFields` to exclude specific fields
 */
trait Auditable
{
    /**
     * Boot the auditable trait.
     */
    public static function bootAuditable(): void
    {
        // Log creation
        static::created(function ($model) {
            if ($model->shouldAudit()) {
                $model->auditCreated();
            }
        });

        // Log updates
        static::updated(function ($model) {
            if ($model->shouldAudit() && $model->wasChanged()) {
                $model->auditUpdated();
            }
        });

        // Log deletions
        static::deleted(function ($model) {
            if ($model->shouldAudit()) {
                $model->auditDeleted();
            }
        });
    }

    /**
     * Determine if this model should be audited.
     * Override in model to add custom logic.
     */
    public function shouldAudit(): bool
    {
        // Don't audit in testing environment unless explicitly enabled
        if (app()->runningUnitTests() && ! $this->auditInTests()) {
            return false;
        }

        // Don't audit preview users' data changes
        $user = auth()->user();
        if ($user && $user->is_preview_user) {
            return false;
        }

        return true;
    }

    /**
     * Whether to audit in test environment.
     */
    protected function auditInTests(): bool
    {
        return false;
    }

    /**
     * Get the fields that should be audited.
     */
    protected function getAuditableFields(): array
    {
        if (property_exists($this, 'auditableFields')) {
            return $this->auditableFields;
        }

        return [];
    }

    /**
     * Get fields that should be excluded from auditing.
     */
    protected function getAuditExcludeFields(): array
    {
        $defaults = ['created_at', 'updated_at', 'remember_token', 'password', 'mfa_secret', 'mfa_recovery_codes'];

        if (property_exists($this, 'auditExcludeFields')) {
            return array_merge($defaults, $this->auditExcludeFields);
        }

        return $defaults;
    }

    /**
     * Filter changes to only include auditable fields.
     */
    protected function filterAuditableChanges(array $changes): array
    {
        $auditableFields = $this->getAuditableFields();
        $excludeFields = $this->getAuditExcludeFields();

        // If auditableFields is empty, audit all fields except excluded ones
        if (empty($auditableFields)) {
            return array_diff_key($changes, array_flip($excludeFields));
        }

        // Otherwise, only audit specified fields
        return array_intersect_key($changes, array_flip($auditableFields));
    }

    /**
     * Log model creation.
     */
    protected function auditCreated(): void
    {
        $newValues = $this->filterAuditableChanges($this->getAttributes());

        if (empty($newValues)) {
            return;
        }

        AuditLog::logDataChange(
            AuditLog::ACTION_CREATED,
            $this,
            null,
            $newValues
        );
    }

    /**
     * Log model update.
     */
    protected function auditUpdated(): void
    {
        $changes = $this->getChanges();
        $filteredChanges = $this->filterAuditableChanges($changes);

        if (empty($filteredChanges)) {
            return;
        }

        // Get old values for changed fields
        $oldValues = [];
        foreach (array_keys($filteredChanges) as $key) {
            $oldValues[$key] = $this->getOriginal($key);
        }

        AuditLog::logDataChange(
            AuditLog::ACTION_UPDATED,
            $this,
            $oldValues,
            $filteredChanges
        );
    }

    /**
     * Log model deletion.
     */
    protected function auditDeleted(): void
    {
        $oldValues = $this->filterAuditableChanges($this->getAttributes());

        if (empty($oldValues)) {
            return;
        }

        AuditLog::logDataChange(
            AuditLog::ACTION_DELETED,
            $this,
            $oldValues,
            null
        );
    }

    /**
     * Get the model's audit history.
     */
    public function auditLogs()
    {
        return AuditLog::byModel(get_class($this), $this->getKey())
            ->orderByDesc('created_at');
    }
}
