<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('injects meta tags for a published article', function () {
    InsightArticle::factory()->published()->create([
        'slug' => 'seo-test',
        'title' => 'Test Title',
        'summary' => 'A summary',
    ]);

    $response = $this->get('/insights/seo-test')->assertOk();

    $response->assertSee('<title>Test Title</title>', false)
        ->assertSee('name="description" content="A summary"', false)
        ->assertSee('property="og:type" content="article"', false)
        ->assertSee('application/ld+json', false);
});

it('skips bespoke articles (no server-injected article meta)', function () {
    InsightArticle::factory()->published()->bespoke()->create([
        'slug' => 'legacy-test',
        'title' => 'Legacy',
    ]);

    $response = $this->get('/insights/legacy-test')->assertOk();

    // The static app.blade.php og:type is "website"; if the middleware had
    // pushed an article override, we'd see `og:type" content="article"`.
    $response->assertDontSee('property="og:type" content="article"', false);
});

it('no-ops when article not found', function () {
    $this->get('/insights/nonexistent-article')->assertOk();
});
