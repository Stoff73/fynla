<?php

declare(strict_types=1);

use App\Models\Pipeline\PipelineCampaign;
use App\Services\Pipeline\Social\UtmLinkBuilder;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.url', 'https://fynla.org');
    $this->builder = new UtmLinkBuilder();
});

it('builds a UTM URL from an insight article slug', function () {
    $article = new App\Models\Insights\InsightArticle();
    $article->slug = 'isa-allowance-2025-26';

    $url = $this->builder->forArticle($article, 'instagram', 1);

    expect($url)->toContain('https://fynla.org/insights/isa-allowance-2025-26?')
        ->and($url)->toContain('utm_source=instagram')
        ->and($url)->toContain('utm_medium=reel_9x16')
        ->and($url)->toContain('utm_campaign=isa-allowance-2025-26')
        ->and($url)->toContain('utm_content=clip-1');
});

it('builds a UTM URL for a campaign with its own landing page + slug', function () {
    $campaign = PipelineCampaign::create([
        'name' => 'Spring ISA drive',
        'slug' => 'spring-isa-2026',
        'landing_url' => 'https://fynla.org/campaigns/spring-isa',
    ]);

    $url = $this->builder->forCampaign($campaign, 'facebook', 2);

    expect($url)->toStartWith('https://fynla.org/campaigns/spring-isa?')
        ->and($url)->toContain('utm_source=facebook')
        ->and($url)->toContain('utm_campaign=spring-isa-2026')
        ->and($url)->toContain('utm_content=clip-2');
});

it('preserves existing query parameters on a custom URL', function () {
    $url = $this->builder->forCustomUrl(
        'https://fynla.org/pricing?ref=partner',
        'tiktok',
        'pricing-push',
        3,
    );

    expect($url)->toContain('?ref=partner&utm_source=tiktok')
        ->and($url)->toContain('utm_content=clip-3');
});

it('rejects an unknown platform', function () {
    $article = new App\Models\Insights\InsightArticle();
    $article->slug = 'x';

    expect(fn () => $this->builder->forArticle($article, 'linkedin', 1))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejects a clip index below 1', function () {
    $article = new App\Models\Insights\InsightArticle();
    $article->slug = 'x';

    expect(fn () => $this->builder->forArticle($article, 'instagram', 0))
        ->toThrow(\InvalidArgumentException::class);
});
