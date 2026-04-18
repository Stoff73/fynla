<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsightTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'body_blocks' => $this->body_blocks ?? [],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
