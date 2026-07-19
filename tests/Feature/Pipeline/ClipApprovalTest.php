<?php

declare(strict_types=1);

use App\Jobs\Pipeline\ComposePostsJob;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\ClipApproval;
use App\Models\Pipeline\PipelineArticle;
use App\Models\User;
use App\Services\Pipeline\ClipApprovalService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.enabled', true);
    Config::set('pipeline.clip_approval.enabled', true);
    Config::set('pipeline.clip_approval.auto_approve_minutes_before_post', 10);
    Bus::fake();
    $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
});

function makePipelineArticle(int $clipCount = 2): PipelineArticle
{
    $insight = InsightArticle::factory()->published()->create();

    return PipelineArticle::create([
        'insight_article_id' => $insight->id,
        'status' => 'rendered',
        'clip_paths' => array_map(fn ($i) => "storage/app/social/video/x/clip-{$i}.mp4", range(1, $clipCount)),
    ]);
}

it('creates one ClipApproval row per clip on the article', function () {
    $article = makePipelineArticle(3);
    $approvals = app(ClipApprovalService::class)->createForArticle($article);

    expect($approvals)->toHaveCount(3);
    expect(ClipApproval::where('pipeline_article_id', $article->id)->count())->toBe(3);
    expect($approvals[0]->status)->toBe('pending')
        ->and(strlen($approvals[0]->approve_token))->toBe(48)
        ->and(strlen($approvals[0]->reject_token))->toBe(48)
        ->and($approvals[0]->scheduled_at)->not->toBeNull();
});

it('is idempotent — recreating does not duplicate rows', function () {
    $article = makePipelineArticle(2);
    app(ClipApprovalService::class)->createForArticle($article);
    app(ClipApprovalService::class)->createForArticle($article);

    expect(ClipApproval::where('pipeline_article_id', $article->id)->count())->toBe(2);
});

it('approve() transitions status and stamps the actor', function () {
    $article = makePipelineArticle(1);
    $approval = app(ClipApprovalService::class)->createForArticle($article)[0];
    $actor = User::factory()->create();

    app(ClipApprovalService::class)->approve($approval->fresh(), actorId: $actor->id);

    $approval->refresh();
    expect($approval->status)->toBe('approved')
        ->and($approval->approved_by)->toBe($actor->id)
        ->and($approval->approved_at)->not->toBeNull();
});

it('reject() blocks downstream — ComposePostsJob is NOT dispatched', function () {
    $article = makePipelineArticle(2);
    $approvals = app(ClipApprovalService::class)->createForArticle($article);

    app(ClipApprovalService::class)->approve($approvals[0]->fresh());
    app(ClipApprovalService::class)->reject($approvals[1]->fresh(), 'Bad framing');

    Bus::assertNotDispatched(ComposePostsJob::class);
});

it('dispatches ComposePostsJob only when EVERY clip on the article is approved', function () {
    $article = makePipelineArticle(2);
    $approvals = app(ClipApprovalService::class)->createForArticle($article);

    app(ClipApprovalService::class)->approve($approvals[0]->fresh());
    Bus::assertNotDispatched(ComposePostsJob::class);

    app(ClipApprovalService::class)->approve($approvals[1]->fresh());
    Bus::assertDispatched(ComposePostsJob::class, 1);
});

it('approveAllForArticle() decides every pending clip in one call', function () {
    $article = makePipelineArticle(3);
    app(ClipApprovalService::class)->createForArticle($article);

    $decided = app(ClipApprovalService::class)->approveAllForArticle($article);

    expect($decided)->toHaveCount(3);
    expect(ClipApproval::where('pipeline_article_id', $article->id)
        ->whereIn('status', ['approved', 'auto_approved'])
        ->count())->toBe(3);
    Bus::assertDispatched(ComposePostsJob::class, 1);
});

it('autoApproveDue() promotes pending clips within N minutes of their scheduled slot', function () {
    $article = makePipelineArticle(2);
    $approvals = app(ClipApprovalService::class)->createForArticle($article);

    // Force one approval's scheduled_at to be 5 minutes from now — within the
    // 10-minute auto-approve window.
    $approvals[0]->update(['scheduled_at' => now()->addMinutes(5)]);
    $approvals[1]->update(['scheduled_at' => now()->addDays(2)]);

    $fired = app(ClipApprovalService::class)->autoApproveDue(minutesBefore: 10);

    expect($fired)->toHaveCount(1);
    expect($approvals[0]->fresh()->status)->toBe('auto_approved');
    expect($approvals[1]->fresh()->status)->toBe('pending');
});

it('magic-link approve endpoint records the decision via email', function () {
    $article = makePipelineArticle(1);
    $approval = app(ClipApprovalService::class)->createForArticle($article)[0];

    $this->get('/pipeline/clips/approve/'.$approval->approve_token)
        ->assertOk()
        ->assertSee('Clip approved');

    $approval->refresh();
    expect($approval->status)->toBe('approved')
        ->and($approval->approved_via_email)->toBeTrue();
});

it('magic-link approve rejects an already-decided token with 410', function () {
    $article = makePipelineArticle(1);
    $approval = app(ClipApprovalService::class)->createForArticle($article)[0];
    app(ClipApprovalService::class)->approve($approval->fresh());

    $this->get('/pipeline/clips/approve/'.$approval->approve_token)
        ->assertStatus(410);
});

it('magic-link reject records the reason and blocks downstream', function () {
    $article = makePipelineArticle(1);
    $approval = app(ClipApprovalService::class)->createForArticle($article)[0];

    $this->get('/pipeline/clips/reject/'.$approval->reject_token)
        ->assertOk()
        ->assertSee('Clip rejected');

    $approval->refresh();
    expect($approval->status)->toBe('rejected')
        ->and($approval->rejection_reason)->toContain('email');
    Bus::assertNotDispatched(ComposePostsJob::class);
});
