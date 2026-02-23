<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Goal
 */
class GoalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->goal_name,
            'goal_name' => $this->goal_name,
            'goal_type' => $this->goal_type,
            'custom_goal_type_name' => $this->when(
                $this->goal_type === 'custom',
                $this->custom_goal_type_name
            ),
            'display_goal_type' => $this->display_goal_type,
            'description' => $this->description,
            'target_amount' => $this->target_amount,
            'current_amount' => $this->current_amount,
            'target_date' => $this->target_date?->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'priority' => $this->priority,
            'is_essential' => $this->is_essential,
            'status' => $this->status,
            'assigned_module' => $this->assigned_module,

            // Computed fields
            'progress_percentage' => $this->progress_percentage,
            'amount_remaining' => $this->amount_remaining,
            'days_remaining' => $this->days_remaining,
            'months_remaining' => $this->months_remaining,
            'is_on_track' => $this->is_on_track,
            'current_milestone' => $this->current_milestone,
            'next_milestone' => $this->next_milestone,
            'required_monthly_contribution' => $this->required_monthly_contribution,

            // Contribution tracking
            'monthly_contribution' => $this->monthly_contribution,
            'contribution_frequency' => $this->contribution_frequency,
            'contribution_streak' => $this->contribution_streak,
            'longest_streak' => $this->longest_streak,
            'last_contribution_date' => $this->last_contribution_date?->toDateString(),

            // Ownership
            'ownership_type' => $this->ownership_type,
            'ownership_percentage' => $this->ownership_percentage,

            // Property-specific fields
            'property_location' => $this->when(
                $this->isPropertyGoal(),
                $this->property_location
            ),
            'property_type' => $this->when(
                $this->isPropertyGoal(),
                $this->property_type
            ),
            'is_first_time_buyer' => $this->when(
                $this->isPropertyGoal(),
                $this->is_first_time_buyer
            ),
            'estimated_property_price' => $this->when(
                $this->isPropertyGoal(),
                $this->estimated_property_price
            ),
            'deposit_percentage' => $this->when(
                $this->isPropertyGoal(),
                $this->deposit_percentage
            ),

            // Dependencies
            'dependency_count' => $this->dependsOn()->count(),
            'is_blocked' => $this->isBlocked(),

            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'joint_owner' => $this->whenLoaded('jointOwner', fn () => new UserResource($this->jointOwner)),
            'contributions' => GoalContributionResource::collection($this->whenLoaded('contributions')),
            'linked_savings_account' => $this->whenLoaded('linkedSavingsAccount', fn () => new SavingsAccountResource($this->linkedSavingsAccount)),

            // Links
            'links' => [
                'self' => '/api/goals/'.$this->id,
            ],
        ];
    }
}
