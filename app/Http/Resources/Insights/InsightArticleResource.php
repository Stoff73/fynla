<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsightArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'summary' => $this->summary,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'authors' => $this->authors ?? [],
            'hero_image' => $this->heroImageUrls(),
            'body_blocks' => $this->body_blocks ?? [],
            'template_id' => $this->template_id,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'is_bespoke' => $this->is_bespoke,
            'bespoke_component' => $this->bespoke_component,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'scheduled_at' => optional($this->scheduled_at)->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
        ];
    }

    private function heroImageUrls(): array
    {
        $base = rtrim(config('app.url'), '/').'/storage/';

        return [
            'full' => $this->hero_image_path ? $base.$this->hero_image_path : null,
            'card' => $this->hero_image_card_path ? $base.$this->hero_image_card_path : null,
            'thumb' => $this->hero_image_thumb_path ? $base.$this->hero_image_thumb_path : null,
        ];
    }
}
