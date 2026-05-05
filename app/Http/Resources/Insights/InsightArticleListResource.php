<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsightArticleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = rtrim(config('app.url'), '/').'/storage/';

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'authors' => $this->authors ?? [],
            'image_card' => $this->hero_image_card_path ? $base.$this->hero_image_card_path : null,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'is_featured' => $this->is_featured,
            'is_bespoke' => $this->is_bespoke,
        ];
    }
}
