<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxConfigurationAudit extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tax_configuration_id',
        'changed_by_user_id',
        'change_type',
        'before_state',
        'after_state',
        'changed_fields',
        'rationale',
        'ip_address',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'changed_fields' => 'array',
    ];

    /**
     * Valid change types.
     */
    public const CHANGE_TYPES = [
        'created',
        'updated',
        'activated',
        'deactivated',
        'duplicated',
    ];

    /**
     * Get the tax configuration that was changed.
     */
    public function taxConfiguration(): BelongsTo
    {
        return $this->belongsTo(TaxConfiguration::class);
    }

    /**
     * Get the user who made the change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /**
     * Create an audit record for a tax configuration change.
     */
    public static function log(
        TaxConfiguration $config,
        string $changeType,
        ?array $beforeState = null,
        ?int $userId = null,
        ?string $rationale = null,
        ?string $ipAddress = null
    ): self {
        $changedFields = null;

        // Calculate changed fields if we have before and after states
        if ($beforeState !== null && $config->config_data !== null) {
            $changedFields = self::calculateChangedFields($beforeState, $config->config_data);
        }

        return self::create([
            'tax_configuration_id' => $config->id,
            'changed_by_user_id' => $userId,
            'change_type' => $changeType,
            'before_state' => $beforeState,
            'after_state' => $config->config_data,
            'changed_fields' => $changedFields,
            'rationale' => $rationale,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Calculate which fields changed between two config states.
     */
    private static function calculateChangedFields(array $before, array $after, string $prefix = ''): array
    {
        $changes = [];

        // Check all keys in the after state
        foreach ($after as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (! array_key_exists($key, $before)) {
                $changes[] = ['field' => $fullKey, 'type' => 'added'];
            } elseif (is_array($value) && is_array($before[$key])) {
                $nestedChanges = self::calculateChangedFields($before[$key], $value, $fullKey);
                $changes = array_merge($changes, $nestedChanges);
            } elseif ($value !== $before[$key]) {
                $changes[] = [
                    'field' => $fullKey,
                    'type' => 'modified',
                    'from' => $before[$key],
                    'to' => $value,
                ];
            }
        }

        // Check for removed keys
        foreach ($before as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (! array_key_exists($key, $after)) {
                $changes[] = ['field' => $fullKey, 'type' => 'removed'];
            }
        }

        return $changes;
    }

    /**
     * Get a human-readable summary of the change.
     */
    public function getSummaryAttribute(): string
    {
        $user = $this->changedBy?->name ?? 'System';
        $taxYear = $this->taxConfiguration?->tax_year ?? 'Unknown';

        return match ($this->change_type) {
            'created' => "{$user} created tax configuration for {$taxYear}",
            'updated' => "{$user} updated tax configuration for {$taxYear}",
            'activated' => "{$user} activated tax year {$taxYear}",
            'deactivated' => "{$user} deactivated tax year {$taxYear}",
            'duplicated' => "{$user} duplicated tax configuration to create {$taxYear}",
            default => "{$user} made changes to {$taxYear}",
        };
    }
}
