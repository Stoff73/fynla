<?php

declare(strict_types=1);

use App\Jobs\Pipeline\ProcessInsightArticleJob;
use App\Mail\Pipeline\ScriptReadyForReviewMail;
use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\OAuthCredential;
use App\Models\Pipeline\PipelineArticle;
use App\Models\Pipeline\PipelineRun;
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
    Config::set('pipeline.anthropic.model', 'claude-opus-4-7');
    Config::set('pipeline.notifications.script_ready_to', 'marketing@fynla.org');
    Config::set('pipeline.cost.per_day_gbp', 2.00);
    Config::set('pipeline.cost.per_request_gbp', 0.30);
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

function fakeAnthropicScriptJson(): string
{
    return json_encode([
        'title' => 'The ISA allowance you nearly missed',
        'hook' => 'You know that £20,000 you keep meaning to move into an ISA? Yeah, that one.',
        'script' => str_repeat('This is the body of a light-hearted script about ISAs and why they matter. It walks through the annual allowance, explains what happens if you use it and what happens if you do not, all with a friendly tone that a non-finance viewer can follow easily and enjoyably. ', 3),
        'analogy_used' => 'The ISA allowance is like a supermarket loyalty card that only clears once a year.',
        'key_concept' => 'The £20,000 annual ISA allowance resets on 6 April — use it or lose it.',
        'cta_text' => 'Have a poke around Fynla to see your remaining ISA headroom.',
        'estimated_duration_seconds' => 60,
    ]);
}

function fakeAnthropicResponse(?string $bodyJson = null): array
{
    return [
        'id' => 'msg_test',
        'model' => 'claude-opus-4-7',
        'stop_reason' => 'end_turn',
        'content' => [
            ['type' => 'text', 'text' => $bodyJson ?? fakeAnthropicScriptJson()],
        ],
        'usage' => [
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
        ],
    ];
}

it('runs the full happy path: script → drive → sheet → email', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicResponse(), 200),
        'googleapis.com/upload/drive/v3/files*' => Http::response([
            'id' => 'DOC_ID_123',
            'name' => 'Script — Test',
            'webViewLink' => 'https://docs.google.com/document/d/DOC_ID_123/edit',
        ], 200),
        'sheets.googleapis.com/v4/spreadsheets/SHEET123/values/*' => Http::response([
            'updates' => ['updatedRange' => 'Pipeline!A2:H2'],
        ], 200),
    ]);

    $article = InsightArticle::factory()->published()->create();
    $pipelineArticle = PipelineArticle::create([
        'insight_article_id' => $article->id,
        'status' => 'detected',
    ]);

    (new ProcessInsightArticleJob($pipelineArticle))->handle(
        app(App\Services\Pipeline\VideoScriptGeneratorService::class),
        app(App\Services\Pipeline\Google\GoogleDriveService::class),
        app(App\Services\Pipeline\Google\GoogleSheetsService::class),
    );

    $pipelineArticle->refresh();

    expect($pipelineArticle->status)->toBe('scripted')
        ->and($pipelineArticle->script_drive_file_id)->toBe('DOC_ID_123')
        ->and($pipelineArticle->script_drive_url)->toBe('https://docs.google.com/document/d/DOC_ID_123/edit')
        ->and($pipelineArticle->script_model)->toBe('claude-opus-4-7')
        ->and($pipelineArticle->script_prompt_version)->toBe('v1')
        ->and($pipelineArticle->script_cost_gbp)->toBeGreaterThan(0)
        ->and($pipelineArticle->tracking_sheet_row_id)->toBe('2')
        ->and($pipelineArticle->script_generated_at)->not->toBeNull();

    expect(PipelineRun::where('stage', 'script')->where('status', 'success')->count())->toBe(1);

    Mail::assertQueued(ScriptReadyForReviewMail::class, function ($mail) use ($pipelineArticle) {
        return $mail->hasTo('marketing@fynla.org')
            && $mail->pipelineArticle->is($pipelineArticle);
    });
});

it('marks the article failed and records the error when Anthropic returns invalid JSON', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeAnthropicResponse('not json at all'), 200),
    ]);

    $article = InsightArticle::factory()->published()->create();
    $pipelineArticle = PipelineArticle::create([
        'insight_article_id' => $article->id,
        'status' => 'detected',
    ]);

    $job = new ProcessInsightArticleJob($pipelineArticle);

    expect(fn () => $job->handle(
        app(App\Services\Pipeline\VideoScriptGeneratorService::class),
        app(App\Services\Pipeline\Google\GoogleDriveService::class),
        app(App\Services\Pipeline\Google\GoogleSheetsService::class),
    ))->toThrow(\RuntimeException::class);

    $pipelineArticle->refresh();

    expect($pipelineArticle->status)->toBe('failed')
        ->and($pipelineArticle->retry_count)->toBe(1)
        ->and($pipelineArticle->last_error)->toContain('invalid');

    expect(PipelineRun::where('stage', 'script')->where('status', 'error')->count())->toBe(1);
    Mail::assertNothingQueued();
});

it('marks the article failed when the daily cost cap is already exhausted', function () {
    Cache::put('pipeline.cost.day.'.now()->toDateString(), 20001, now()->endOfDay());

    Http::fake();

    $article = InsightArticle::factory()->published()->create();
    $pipelineArticle = PipelineArticle::create([
        'insight_article_id' => $article->id,
        'status' => 'detected',
    ]);

    $job = new ProcessInsightArticleJob($pipelineArticle);

    expect(fn () => $job->handle(
        app(App\Services\Pipeline\VideoScriptGeneratorService::class),
        app(App\Services\Pipeline\Google\GoogleDriveService::class),
        app(App\Services\Pipeline\Google\GoogleSheetsService::class),
    ))->toThrow(\RuntimeException::class, 'daily cost cap');

    $pipelineArticle->refresh();

    expect($pipelineArticle->status)->toBe('failed');
    Http::assertNothingSent();
});

it('skips articles that are not in a runnable state', function () {
    $article = InsightArticle::factory()->published()->create();
    $pipelineArticle = PipelineArticle::create([
        'insight_article_id' => $article->id,
        'status' => 'scripted',
    ]);

    Http::fake();

    (new ProcessInsightArticleJob($pipelineArticle))->handle(
        app(App\Services\Pipeline\VideoScriptGeneratorService::class),
        app(App\Services\Pipeline\Google\GoogleDriveService::class),
        app(App\Services\Pipeline\Google\GoogleSheetsService::class),
    );

    $pipelineArticle->refresh();
    expect($pipelineArticle->status)->toBe('scripted');
    Http::assertNothingSent();
    Mail::assertNothingQueued();
});
