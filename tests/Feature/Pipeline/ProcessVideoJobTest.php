<?php

declare(strict_types=1);

use App\Jobs\Pipeline\ProcessVideoJob;
use App\Mail\Pipeline\ClipsReadyForReviewMail;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\OAuthCredential;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelineRun;
use App\Services\Pipeline\CaptionBuilder;
use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\GoogleSheetsService;
use App\Services\Pipeline\HighlightSelectorService;
use App\Services\Pipeline\LocalWhisperTranscriber;
use App\Services\Pipeline\VideoCropService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.enabled', true);
    Config::set('pipeline.google.oauth_client_id', 'test-client-id');
    Config::set('pipeline.google.oauth_client_secret', 'test-client-secret');
    Config::set('pipeline.google.oauth_redirect_uri', 'http://localhost:8000/pipeline/oauth/google/callback');
    Config::set('pipeline.google.drive_folder_id', 'FOLDER123');
    Config::set('pipeline.google.tracker_sheet_id', 'SHEET123');
    Config::set('pipeline.anthropic.api_key', 'test-anthropic-key');
    Config::set('pipeline.cost.per_day_gbp', 1.00);
    Cache::flush();

    OAuthCredential::create([
        'provider' => 'google',
        'account_email' => 'test@fynla.org',
        'access_token' => 'test-access-token',
        'refresh_token' => 'test-refresh-token',
        'expires_at' => now()->addHour(),
        'scopes' => ['https://www.googleapis.com/auth/drive.file'],
    ]);

    Mail::fake();
});

it('skips articles not in a runnable state', function () {
    $article = InsightArticle::factory()->published()->create();
    $pipelineArticle = PipelineArticle::create([
        'insight_article_id' => $article->id,
        'status' => 'published',
        'source_video_drive_file_id' => 'FILE1',
    ]);

    Http::fake();

    (new ProcessVideoJob($pipelineArticle))->handle(
        app(GoogleDriveService::class),
        app(GoogleSheetsService::class),
        app(LocalWhisperTranscriber::class),
        app(HighlightSelectorService::class),
        app(CaptionBuilder::class),
        app(VideoCropService::class),
    );

    $pipelineArticle->refresh();
    expect($pipelineArticle->status)->toBe('published');
    Http::assertNothingSent();
    Mail::assertNothingQueued();
});

it('records a fail run when the source video record is missing its Drive file ID', function () {
    $article = InsightArticle::factory()->published()->create();
    $pipelineArticle = PipelineArticle::create([
        'insight_article_id' => $article->id,
        'status' => 'rendering',
        'source_video_drive_file_id' => null,
    ]);

    Http::fake([
        'googleapis.com/drive/v3/files/*' => Http::response('', 404),
    ]);

    expect(fn () => (new ProcessVideoJob($pipelineArticle))->handle(
        app(GoogleDriveService::class),
        app(GoogleSheetsService::class),
        app(LocalWhisperTranscriber::class),
        app(HighlightSelectorService::class),
        app(CaptionBuilder::class),
        app(VideoCropService::class),
    ))->toThrow(\RuntimeException::class);

    $pipelineArticle->refresh();
    expect($pipelineArticle->status)->toBe('failed')
        ->and($pipelineArticle->retry_count)->toBe(1);

    expect(PipelineRun::where('stage', 'video')->where('status', 'error')->count())->toBe(1);
});
