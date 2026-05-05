<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * List-view shape for a DocumentArticle, matching InsightArticleListResource
 * so the InsightsHubPage can render mixed sources from one merged list.
 */
class DocumentArticleAsInsightListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = rtrim(config('app.url'), '/').'/storage/';

        $authors = [];
        if ($this->author_byline) {
            $authors[] = $this->author_byline;
        } elseif ($this->author_name) {
            $authors[] = $this->author_name;
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->description,
            'category' => null,
            'tags' => [],
            'authors' => $authors,
            'image_card' => $this->cover_image_path ? $base.$this->cover_image_path : null,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'is_featured' => false,
            'is_bespoke' => false,
            'source' => 'document',
        ];
    }
}
