<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\GoalContribution
 */
class GoalContributionResource extends JsonResource
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
            'goal_id' => $this->goal_id,
            'amount' => $this->amount,
            'contribution_date' => $this->contribution_date?->toDateString(),
            'contribution_type' => $this->contribution_type,
            'notes' => $this->notes,
            'goal_balance_after' => $this->goal_balance_after,
            'streak_qualifying' => $this->streak_qualifying,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'goal' => $this->whenLoaded('goal', fn () => new GoalResource($this->goal)),
        ];
    }
}
