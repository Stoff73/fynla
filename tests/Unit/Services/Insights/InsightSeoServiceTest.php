<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Services\Insights\InsightSeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(InsightSeoService::class);
});

it('falls back to title and summary when overrides are absent', function () {
    $article = InsightArticle::factory()->published()->create([
        'title' => 'My Title',
        'summary' => 'My summary',
        'meta_title' => null,
        'meta_description' => null,
    ]);

    $meta = $this->service->metaTags($article);

    expect($meta['title'])->toBe('My Title')
        ->and($meta['description'])->toBe('My summary');
});

it('uses SEO overrides when provided', function () {
    $article = InsightArticle::factory()->published()->create([
        'meta_title' => 'SEO Title',
        'meta_description' => 'SEO Desc',
    ]);

    $meta = $this->service->metaTags($article);

    expect($meta['title'])->toBe('SEO Title')
        ->and($meta['description'])->toBe('SEO Desc');
});

it('includes open graph and twitter card tags', function () {
    $article = InsightArticle::factory()->published()->create([
        'hero_image_card_path' => 'insights/slug/card.webp',
    ]);

    $meta = $this->service->metaTags($article);

    expect($meta['og'])->toHaveKeys(['title', 'description', 'image', 'type', 'url'])
        ->and($meta['og']['type'])->toBe('article')
        ->and($meta['og']['image'])->toContain('/storage/insights/slug/card.webp')
        ->and($meta['twitter']['card'])->toBe('summary_large_image');
});

it('builds a schema.org Article JSON-LD payload', function () {
    $article = InsightArticle::factory()->published()->create([
        'title' => 'JSON-LD Test',
    ]);

    $jsonLd = $this->service->jsonLd($article);

    expect($jsonLd['@context'])->toBe('https://schema.org')
        ->and($jsonLd['@type'])->toBe('Article')
        ->and($jsonLd['headline'])->toBe('JSON-LD Test')
        ->and($jsonLd)->toHaveKeys(['datePublished', 'author', 'image', 'publisher']);
});
