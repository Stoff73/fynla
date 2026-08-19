<?php

declare(strict_types=1);

use App\Jobs\Pipeline\ComposePostsJob;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\ClipApproval;
use App\Models\Pipeline\PipelineArticle;
use App\Services\Insights\InsightArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.enabled', true);
    Bus::fake();
});

function heldPipelineArticle(InsightArticle $source): PipelineArticle
{
    return PipelineArticle::create([
        'insight_article_id' => $source->id,
        'status' => 'rendered',
        'clip_paths' => ['storage/app/social/video/x/clip-1.mp4'],
    ]);
}

it('composes the waiting clips when a founder publishes the article', function () {
    $draft = InsightArticle::factory()->create(['status' => 'draft']);
    heldPipelineArticle($draft);

    app(InsightArticleService::class)->publish($draft);

    Bus::assertDispatchedTimes(ComposePostsJob::class, 1);
});

it('still waits for clip approval when clips are pending', function () {
    $draft = InsightArticle::factory()->create(['status' => 'draft']);
    $pipelineArticle = heldPipelineArticle($draft);

    ClipApproval::create([
        'pipeline_article_id' => $pipelineArticle->id,
        'clip_index' => 0,
        'clip_kind' => 'short',
        'clip_path' => 'storage/app/social/video/x/clip-1.mp4',
        'status' => 'pending',
        'approve_token' => 'ok-'.uniqid(),
        'reject_token' => 'no-'.uniqid(),
        'token_expires_at' => now()->addDays(3),
        'scheduled_at' => now()->addDay(),
    ]);

    app(InsightArticleService::class)->publish($draft);

    Bus::assertNotDispatched(ComposePostsJob::class);
});

it('composes the approved clips when the article is published after approval', function () {
    $draft = InsightArticle::factory()->create(['status' => 'draft']);
    $pipelineArticle = heldPipelineArticle($draft);

    ClipApproval::create([
        'pipeline_article_id' => $pipelineArticle->id,
        'clip_index' => 0,
        'clip_kind' => 'short',
        'clip_path' => 'storage/app/social/video/x/clip-1.mp4',
        'status' => 'approved',
        'approve_token' => 'ok-'.uniqid(),
        'reject_token' => 'no-'.uniqid(),
        'token_expires_at' => now()->addDays(3),
        'scheduled_at' => now()->addDay(),
        'approved_at' => now(),
    ]);

    app(InsightArticleService::class)->publish($draft);

    Bus::assertDispatchedTimes(ComposePostsJob::class, 1);
});

it('leaves an unrelated article edit alone', function () {
    $published = InsightArticle::factory()->published()->create();
    heldPipelineArticle($published);

    $published->update(['title' => 'A new title']);

    Bus::assertNotDispatched(ComposePostsJob::class);
});
