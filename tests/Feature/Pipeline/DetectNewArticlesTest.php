<?php

declare(strict_types=1);

use App\Jobs\Pipeline\ProcessInsightArticleJob;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelineRun;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.enabled', true);
    Bus::fake();
});

it('exits without dispatching when the pipeline is disabled', function () {
    Config::set('pipeline.enabled', false);

    InsightArticle::factory()->published()->create();

    $this->artisan('pipeline:detect-new-articles')
        ->expectsOutputToContain('Pipeline is disabled')
        ->assertExitCode(0);

    expect(PipelineArticle::count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('does nothing when there are no published articles', function () {
    InsightArticle::factory()->count(3)->create();

    $this->artisan('pipeline:detect-new-articles')
        ->expectsOutputToContain('No new articles')
        ->assertExitCode(0);

    expect(PipelineArticle::count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('creates a pipeline_articles row and dispatches a job per newly published article', function () {
    $articles = InsightArticle::factory()->published()->count(2)->create();

    $this->artisan('pipeline:detect-new-articles')->assertExitCode(0);

    expect(PipelineArticle::count())->toBe(2);
    Bus::assertDispatchedTimes(ProcessInsightArticleJob::class, 2);

    foreach ($articles as $article) {
        $pa = PipelineArticle::where('insight_article_id', $article->id)->first();
        expect($pa)->not->toBeNull()
            ->and($pa->status)->toBe('detected');
    }
});

it('records a successful detect run in pipeline_runs per article', function () {
    InsightArticle::factory()->published()->count(2)->create();

    $this->artisan('pipeline:detect-new-articles')->assertExitCode(0);

    expect(PipelineRun::where('stage', 'detect')->where('status', 'success')->count())->toBe(2);
});

it('does not re-dispatch articles already in the pipeline', function () {
    $article = InsightArticle::factory()->published()->create();
    PipelineArticle::create([
        'insight_article_id' => $article->id,
        'status' => 'scripted',
    ]);

    $this->artisan('pipeline:detect-new-articles')
        ->expectsOutputToContain('No new articles')
        ->assertExitCode(0);

    expect(PipelineArticle::count())->toBe(1);
    Bus::assertNothingDispatched();
});

it('respects --dry-run and does not create rows or dispatch jobs', function () {
    InsightArticle::factory()->published()->count(2)->create();

    $this->artisan('pipeline:detect-new-articles', ['--dry-run' => true])
        ->expectsOutputToContain('dry run')
        ->assertExitCode(0);

    expect(PipelineArticle::count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('does not pick up draft or archived articles', function () {
    InsightArticle::factory()->create(['status' => 'draft']);
    InsightArticle::factory()->create(['status' => 'archived']);
    InsightArticle::factory()->published()->create();

    $this->artisan('pipeline:detect-new-articles')->assertExitCode(0);

    expect(PipelineArticle::count())->toBe(1);
    Bus::assertDispatchedTimes(ProcessInsightArticleJob::class, 1);
});
