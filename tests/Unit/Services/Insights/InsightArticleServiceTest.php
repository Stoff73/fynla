<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightTemplate;
use App\Models\User;
use App\Services\Insights\InsightArticleService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Reset auth between tests — the observer reads auth()->id() for saved_by,
    // so a stale user id from a prior test (whose User row has already been
    // rolled back) would produce a FK violation on the revision insert.
    \Illuminate\Support\Facades\Auth::logout();

    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->service = app(InsightArticleService::class);
});

it('creates an article as draft by default', function () {
    $article = $this->service->create([
        'title' => 'Test Article',
        'summary' => 'Short summary',
        'category' => 'pensions',
    ], $this->admin);

    expect($article->status)->toBe('draft')
        ->and($article->slug)->toBe('test-article')
        ->and($article->author_id)->toBe($this->admin->id);
});

it('generates a unique slug when one collides', function () {
    InsightArticle::factory()->create(['slug' => 'test-article']);

    $article = $this->service->create([
        'title' => 'Test Article',
        'summary' => 's',
        'category' => 'pensions',
    ], $this->admin);

    expect($article->slug)->toBe('test-article-2');
});

it('publishes an article and sets published_at', function () {
    $article = InsightArticle::factory()->create(['status' => 'draft']);

    $this->service->publish($article);

    expect($article->fresh()->status)->toBe('published')
        ->and($article->fresh()->published_at)->not->toBeNull();
});

it('auto-unfeatures previously featured article when featuring a new one', function () {
    $previous = InsightArticle::factory()->published()->featured()->create();
    $next = InsightArticle::factory()->published()->create();

    $this->service->setFeatured($next);

    expect($previous->fresh()->is_featured)->toBeFalse()
        ->and($next->fresh()->is_featured)->toBeTrue();
});

it('archives an article without deleting it', function () {
    $article = InsightArticle::factory()->published()->create();

    $this->service->archive($article);

    expect($article->fresh()->status)->toBe('archived')
        ->and($article->fresh()->deleted_at)->toBeNull();
});

it('writes a revision on every update via the observer', function () {
    $article = InsightArticle::factory()->create();
    $baselineRevisions = $article->fresh()->revisions()->count();

    $this->service->update($article, ['title' => 'New Title'], $this->admin);

    $latest = $article->fresh()->revisions()->first();

    expect($article->fresh()->revisions()->count())->toBe($baselineRevisions + 1)
        ->and($latest->title)->toBe('New Title');
});

it('resyncs blocks from the article template', function () {
    $template = InsightTemplate::factory()->create([
        'body_blocks' => [['type' => 'heading', 'level' => 2, 'text' => 'Fresh']],
    ]);
    $article = InsightArticle::factory()->create([
        'template_id' => $template->id,
        'body_blocks' => [['type' => 'heading', 'level' => 2, 'text' => 'Old']],
    ]);

    $this->service->resyncFromTemplate($article, $this->admin);

    expect($article->fresh()->body_blocks)->toEqual([
        ['type' => 'heading', 'level' => 2, 'text' => 'Fresh'],
    ]);
});

it('returns null for featured article when nothing is featured', function () {
    InsightArticle::factory()->count(3)->published()->create();

    expect($this->service->getFeatured())->toBeNull();
});

it('returns the featured article when one is flagged', function () {
    InsightArticle::factory()->count(2)->published()->create();
    $featured = InsightArticle::factory()->published()->featured()->create();

    expect($this->service->getFeatured()->id)->toBe($featured->id);
});
