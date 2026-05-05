<?php

declare(strict_types=1);

use App\Jobs\PublishScheduledInsightsJob;
use App\Models\Insights\InsightArticle;

uses(
    \Tests\TestCase::class,
    \Illuminate\Foundation\Testing\RefreshDatabase::class,
);

it('publishes drafts whose scheduled_at has passed', function () {
    $due = InsightArticle::factory()->scheduled(now()->subMinute())->create();
    $future = InsightArticle::factory()->scheduled(now()->addHour())->create();

    (new PublishScheduledInsightsJob())->handle();

    expect($due->fresh()->status)->toBe('published')
        ->and($due->fresh()->published_at)->not->toBeNull()
        ->and($future->fresh()->status)->toBe('draft');
});

it('does not touch already-published articles', function () {
    $published = InsightArticle::factory()->published()->create(['published_at' => now()->subDay()]);
    $originalPublishedAt = $published->published_at;

    (new PublishScheduledInsightsJob())->handle();

    expect($published->fresh()->published_at->timestamp)->toBe($originalPublishedAt->timestamp);
});
