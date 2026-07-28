<?php

declare(strict_types=1);

use App\Models\DocumentArticle;
use App\Models\User;
use App\Services\Pipeline\ArticlePublishScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    Cache::flush();
    Config::set('pipeline.articles.publish_slots', [
        ['day' => 'Tuesday', 'time' => '08:00'],
        ['day' => 'Thursday', 'time' => '08:00'],
    ]);
    Config::set('pipeline.articles.min_gap_hours', 24);
    Config::set('pipeline.articles.min_lead_minutes', 30);
});

it('publishes immediately when no date is given', function () {
    $article = DocumentArticle::factory()->create([
        'imported_by' => $this->admin->id,
        'title' => 'A title',
        'html_body' => '<p>Body</p>',
        'status' => 'draft',
    ]);
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/admin/documents/{$article->id}/publish")
        ->assertOk()
        ->assertJsonPath('scheduled', false);

    expect($article->fresh()->published_at->isFuture())->toBeFalse();
});

it('accepts a future publish date and reports it as scheduled', function () {
    $article = DocumentArticle::factory()->create([
        'imported_by' => $this->admin->id,
        'title' => 'A title',
        'html_body' => '<p>Body</p>',
        'status' => 'draft',
    ]);
    Sanctum::actingAs($this->admin);

    $when = CarbonImmutable::now()->addDays(3)->setTime(8, 0);

    $this->postJson("/api/admin/documents/{$article->id}/publish", [
        'published_at' => $when->toIso8601String(),
    ])->assertOk()->assertJsonPath('scheduled', true);

    $fresh = $article->fresh();
    expect($fresh->status)->toBe('published')
        ->and($fresh->isScheduled())->toBeTrue();
});

it('keeps a scheduled article off public surfaces until its time', function () {
    DocumentArticle::factory()->create([
        'imported_by' => $this->admin->id,
        'slug' => 'future-piece',
        'status' => 'published',
        'published_at' => CarbonImmutable::now()->addDays(2),
    ]);

    // Not in the listing, and not reachable at its own URL.
    expect(DocumentArticle::live()->count())->toBe(0);
    $this->getJson('/api/insights/future-piece')->assertNotFound();
});

it('shows a scheduled article once its publish time has passed', function () {
    DocumentArticle::factory()->create([
        'imported_by' => $this->admin->id,
        'slug' => 'past-piece',
        'status' => 'published',
        'published_at' => CarbonImmutable::now()->subMinute(),
    ]);

    expect(DocumentArticle::live()->count())->toBe(1);
    $this->getJson('/api/insights/past-piece')->assertOk();
});

it('recommends a preferred slot in the future', function () {
    $article = DocumentArticle::factory()->create(['imported_by' => $this->admin->id]);
    Sanctum::actingAs($this->admin);

    $response = $this->getJson("/api/admin/documents/{$article->id}/publish-recommendation")
        ->assertOk()
        ->assertJsonStructure(['data' => ['recommended_at', 'recommended_label', 'reason', 'source']]);

    $at = CarbonImmutable::parse($response->json('data.recommended_at'));
    expect($at->isFuture())->toBeTrue()
        ->and(in_array($at->format('l'), ['Tuesday', 'Thursday'], true))->toBeTrue()
        ->and($response->json('data.source'))->toBe('default');
});

it('skips a slot that clashes with another article and says so', function () {
    $scheduler = app(ArticlePublishScheduler::class);

    // Take the slot the scheduler would otherwise choose.
    $first = $scheduler->recommend()['at'];
    DocumentArticle::factory()->create([
        'imported_by' => $this->admin->id,
        'status' => 'published',
        'published_at' => $first,
    ]);

    $second = $scheduler->recommend();

    expect($second['at']->toIso8601String())->not->toBe($first->toIso8601String())
        ->and($second['clashes_avoided'])->toBeGreaterThan(0)
        ->and($second['reason'])->toContain('another article');
});

it('prefers analytics slots when the recalculator has written them', function () {
    Cache::put(ArticlePublishScheduler::OVERRIDE_CACHE_KEY, [
        ['day' => 'Monday', 'time' => '19:00'],
    ], 600);

    $result = app(ArticlePublishScheduler::class)->recommend();

    expect($result['source'])->toBe('analytics')
        ->and($result['at']->format('l'))->toBe('Monday')
        ->and($result['reason'])->toContain('Google Analytics');
});

it('turns analytics rows into one slot per day, busiest first', function () {
    $slots = app(ArticlePublishScheduler::class)->slotsFromAnalytics([
        ['day' => 'Monday', 'hour' => 19, 'sessions' => 500],
        ['day' => 'Monday', 'hour' => 20, 'sessions' => 400],  // same day — dropped
        ['day' => 'Friday', 'hour' => 9, 'sessions' => 300],
        ['day' => 'Sunday', 'hour' => 7, 'sessions' => 3],     // below confidence floor
    ]);

    expect($slots)->toBe([
        ['day' => 'Monday', 'time' => '19:00'],
        ['day' => 'Friday', 'time' => '09:00'],
    ]);
});
