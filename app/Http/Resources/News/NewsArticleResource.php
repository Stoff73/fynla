<?php

declare(strict_types=1);

namespace App\Http\Resources\News;

use App\Models\News\NewsArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NewsArticle
 */
class NewsArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'slug'             => $this->slug,
            'title'            => $this->title,
            'summary'          => $this->summary,
            'body'             => $this->body,
            'image_url'        => $this->image_url,
            'author_name'      => $this->author_name,
            'published_at'     => optional($this->published_at)->toIso8601String(),
            'meta_title'       => $this->meta_title,
            'meta_description' => $this->meta_description,
        ];
    }
}
