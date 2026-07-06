<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal owner representation for shared-asset payloads.
 *
 * Jointly owned records (properties, investments, business interests, chattels,
 * goals, mortgages, savings) nest their primary and joint owner so the UI can
 * show a name against a shared asset. Only identity fields are exposed here —
 * never the co-owner's email, income, expenditure, or other PII, which the full
 * UserResource would otherwise leak to the other owner.
 */
class MinimalUserResource extends JsonResource
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
            'first_name' => $this->first_name,
            'surname' => $this->surname,
        ];
    }
}
