<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PlanActionFundingSelection extends Model
{
    protected $fillable = [
        'user_id',
        'plan_type',
        'action_category',
        'target_account_id',
        'funding_source_type',
        'funding_source_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'target_account_id' => 'integer',
        'funding_source_id' => 'integer',
    ];

    /**
     * Get all funding selections for a user and plan type, keyed by "{category}_{targetAccountId}".
     */
    public static function getForUser(int $userId, string $planType): Collection
    {
        return static::where('user_id', $userId)
            ->where('plan_type', $planType)
            ->get()
            ->keyBy(fn ($row) => $row->action_category.'_'.$row->target_account_id);
    }

    /**
     * Upsert a funding source selection on the composite key.
     */
    public static function upsertSelection(
        int $userId,
        string $planType,
        string $actionCategory,
        int $targetAccountId,
        string $fundingSourceType,
        int $fundingSourceId
    ): static {
        return static::updateOrCreate(
            [
                'user_id' => $userId,
                'plan_type' => $planType,
                'action_category' => $actionCategory,
                'target_account_id' => $targetAccountId,
            ],
            [
                'funding_source_type' => $fundingSourceType,
                'funding_source_id' => $fundingSourceId,
            ]
        );
    }
}
