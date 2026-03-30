<?php

declare(strict_types=1);

namespace App\Models\Investment;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentScenario extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'scenario_name',
        'description',
        'scenario_type',
        'template_name',
        'parameters',
        'results',
        'comparison_data',
        'is_saved',
        'monte_carlo_job_id',
        'completed_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'results' => 'array',
        'comparison_data' => 'array',
        'is_saved' => 'boolean',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the scenario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get saved scenarios only
     */
    public function scopeSaved(Builder $query): Builder
    {
        return $query->where('is_saved', true);
    }

    /**
     * Scope to get completed scenarios
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get scenarios by type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('scenario_type', $type);
    }

    /**
     * Check if scenario is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if scenario is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Mark scenario as completed
     */
    public function markAsCompleted(array $results): void
    {
        $this->update([
            'status' => 'completed',
            'results' => $results,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark scenario as failed
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }
}
