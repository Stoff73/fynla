<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\DocumentArticle;
use App\Models\Insights\InsightArticle;

class InsightSeoService
{
    public function metaTags(InsightArticle $article): array
    {
        $title = $article->meta_title ?? $article->title;
        $description = $article->meta_description ?? $article->summary;
        $url = $this->articleUrl($article);
        $image = $this->imageUrl($article->hero_image_card_path);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $article->canonical_url ?? $url,
            'og' => [
                'title' => $title,
                'description' => $description,
                'image' => $image,
                'type' => 'article',
                'url' => $url,
            ],
            'twitter' => [
                'card' => 'summary_large_image',
                'title' => $title,
                'description' => $description,
                'image' => $image,
            ],
        ];
    }

    public function jsonLd(InsightArticle $article): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $article->summary,
            'image' => $this->imageUrl($article->hero_image_card_path),
            'datePublished' => optional($article->published_at)->toIso8601String(),
            'dateModified' => $article->updated_at->toIso8601String(),
            'author' => [
                '@type' => 'Organization',
                'name' => 'Fynla',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Fynla',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => config('app.url').'/images/fynla-logo.png',
                ],
            ],
            'mainEntityOfPage' => $this->articleUrl($article),
        ];
    }

    public function metaTagsForDocument(DocumentArticle $article): array
    {
        $title = $article->title;
        $description = $article->description;
        $url = rtrim(config('app.url'), '/')."/insights/{$article->slug}";
        $image = $this->imageUrl($article->cover_image_path);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $url,
            'og' => [
                'title' => $title,
                'description' => $description,
                'image' => $image,
                'type' => 'article',
                'url' => $url,
            ],
            'twitter' => [
                'card' => 'summary_large_image',
                'title' => $title,
                'description' => $description,
                'image' => $image,
            ],
        ];
    }

    public function jsonLdForDocument(DocumentArticle $article): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $article->description,
            'image' => $this->imageUrl($article->cover_image_path),
            'datePublished' => optional($article->published_at)->toIso8601String(),
            'dateModified' => $article->updated_at->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $article->author_byline ?? $article->author_name ?? 'Fynla',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Fynla',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => config('app.url').'/images/fynla-logo.png',
                ],
            ],
            'mainEntityOfPage' => rtrim(config('app.url'), '/')."/insights/{$article->slug}",
        ];
    }

    private function articleUrl(InsightArticle $article): string
    {
        return rtrim(config('app.url'), '/')."/insights/{$article->slug}";
    }

    private function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/storage/'.$path;
    }
}
