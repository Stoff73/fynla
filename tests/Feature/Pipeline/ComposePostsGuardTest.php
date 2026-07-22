<?php

declare(strict_types=1);

use App\Jobs\Pipeline\ComposePostsJob;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\ClipApproval;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelinePost;
use App\Services\Pipeline\Social\HashtagPicker;
use App\Services\Pipeline\Social\PostComposer;
use App\Services\Pipeline\Social\UtmLinkBuilder;
use Illuminate\Support\Facades\Config;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.enabled', true);
});

afterEach(function () {
    Mockery::close();
});

it('holds composition when the source article is not live (no dead-link posts)', function () {
    $draft = InsightArticle::factory()->create(['status' => 'draft']);
    $article = PipelineArticle::create([
        'insight_article_id' => $draft->id,
        'status' => 'rendered',
        'clip_paths' => ['storage/app/social/video/x/clip-1.mp4'],
    ]);

    // The guard returns before any Anthropic call, so real services are fine.
    (new ComposePostsJob($article))->handle(
        app(PostComposer::class),
        app(HashtagPicker::class),
        app(UtmLinkBuilder::class),
    );

    expect(PipelinePost::where('pipeline_article_id', $article->id)->count())->toBe(0);
    // Held, not failed — publishing then re-running compose should proceed.
    expect($article->fresh()->status)->toBe('rendered');
});

it('composes only the approved clips, skipping rejected ones', function () {
    // Mock the LLM-backed services so compose is deterministic + offline.
    $composer = Mockery::mock(PostComposer::class);
    $composer->shouldReceive('compose')->andReturn(['A' => 'caption A', 'B' => 'caption B', 'audience_b' => 'x']);
    app()->instance(PostComposer::class, $composer);

    $hashtags = Mockery::mock(HashtagPicker::class);
    $hashtags->shouldReceive('pick')->andReturn(['#a', '#b']);
    app()->instance(HashtagPicker::class, $hashtags);

    $published = InsightArticle::factory()->published()->create();
    $article = PipelineArticle::create([
        'insight_article_id' => $published->id,
        'status' => 'rendered',
        'clip_paths' => [
            'storage/app/social/video/x/clip-1.mp4',
            'storage/app/social/video/x/clip-2.mp4',
        ],
    ]);

    // Clip 1 approved, clip 2 rejected (excluded from the run).
    foreach ([[1, 'approved', 'short'], [2, 'rejected', 'full']] as [$idx, $status, $kind]) {
        ClipApproval::create([
            'pipeline_article_id' => $article->id,
            'clip_index' => $idx,
            'clip_path' => "storage/app/social/video/x/clip-{$idx}.mp4",
            'clip_kind' => $kind,
            'status' => $status,
            'approve_token' => str_repeat((string) $idx, 48),
            'reject_token' => str_repeat((string) ($idx + 5), 48),
            'token_expires_at' => now()->addHours(48),
            'scheduled_at' => now()->addDay(),
        ]);
    }

    (new ComposePostsJob($article))->handle(
        app(PostComposer::class),
        app(HashtagPicker::class),
        app(UtmLinkBuilder::class),
    );

    $indices = PipelinePost::where('pipeline_article_id', $article->id)
        ->pluck('clip_index')->unique()->sort()->values()->all();

    // Only clip 1 (approved) was composed; clip 2 (rejected) was skipped.
    expect($indices)->toBe([1]);
    // 3 platforms x 2 variants for the single approved clip.
    expect(PipelinePost::where('pipeline_article_id', $article->id)->count())->toBe(6);
});
