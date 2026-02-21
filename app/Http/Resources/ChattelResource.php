<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Traits\CalculatesOwnershipShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Chattel
 */
class ChattelResource extends JsonResource
{
    use CalculatesOwnershipShare;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'household_id' => $this->household_id,
            'joint_owner_id' => $this->joint_owner_id,
            'trust_id' => $this->trust_id,
            'name' => $this->name,
            'description' => $this->description,
            'chattel_type' => $this->chattel_type,
            'category' => $this->chattel_type,
            'ownership_type' => $this->ownership_type,
            'ownership_percentage' => $this->ownership_percentage,
            'purchase_price' => $this->purchase_price,
            'current_value' => $this->current_value,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'valuation_date' => $this->valuation_date?->toDateString(),
            'country' => $this->country,

            // Vehicle-specific fields
            'make' => $this->when(
                $this->chattel_type === 'vehicle',
                $this->make
            ),
            'model' => $this->when(
                $this->chattel_type === 'vehicle',
                $this->model
            ),
            'year' => $this->when(
                $this->chattel_type === 'vehicle',
                $this->year
            ),
            'registration_number' => $this->when(
                $this->chattel_type === 'vehicle',
                $this->registration_number
            ),

            // Computed fields - enrichment data
            'appreciation' => $this->when(
                $this->purchase_price !== null && $this->current_value !== null,
                fn () => $this->current_value - $this->purchase_price
            ),
            'full_value' => (float) $this->current_value,
            'user_share' => $userId ? $this->calculateUserShare($this->resource, $userId) : 0.0,
            'is_primary_owner' => $userId ? $this->user_id === $userId : null,
            'is_shared' => in_array($this->ownership_type, ['joint', 'tenants_in_common'], true),
            'is_wasting_asset' => $this->chattel_type === 'vehicle',

            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'joint_owner' => $this->whenLoaded('jointOwner', fn () => new UserResource($this->jointOwner)),
            'household' => $this->whenLoaded('household'),
            'trust' => $this->whenLoaded('trust'),

            // Links
            'links' => [
                'self' => '/api/chattels/'.$this->id,
            ],
        ];
    }
}
