<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActuarialLifeTableResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'age' => (int) $this->age,
            'gender' => $this->gender,
            'life_expectancy_years' => (float) $this->life_expectancy_years,
            'probability_of_death' => (float) $this->probability_of_death,
            'table_year' => $this->table_year,
            'table_source' => $this->table_source,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
