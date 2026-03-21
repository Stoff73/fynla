<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatIfScenarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scenario_type' => $this->scenario_type,
            'parameters' => $this->parameters,
            'affected_modules' => $this->affected_modules,
            'created_via' => $this->created_via,
            'ai_narrative' => $this->ai_narrative,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
