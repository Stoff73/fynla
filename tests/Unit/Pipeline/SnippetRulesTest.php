<?php

declare(strict_types=1);

use App\Services\Pipeline\AnthropicOpusClient;
use App\Services\Pipeline\HighlightSelectorService;
use App\Services\Pipeline\SnippetValidatorService;
use Illuminate\Support\Facades\Config;

uses(\Tests\TestCase::class);

beforeEach(function () {
    Config::set('pipeline.video.snippet_threshold_seconds', 30);
    Config::set('pipeline.video.snippet_target_seconds', 15);
    Config::set('pipeline.video.snippet_min_seconds', 12);
    Config::set('pipeline.video.snippet_max_seconds', 18);
    Config::set('pipeline.video.snippet_count', 3);
    Config::set('pipeline.video.snippet_validation_enabled', true);
});

afterEach(function () {
    Mockery::close();
});

function fakeClient(string $json): AnthropicOpusClient
{
    $mock = Mockery::mock(AnthropicOpusClient::class);
    $mock->shouldReceive('complete')->andReturn(['text' => $json, 'usage' => [], 'gbp' => 0.0]);

    return $mock;
}

function transcriptFixture(): array
{
    $segments = [];
    for ($t = 0; $t < 90; $t += 5) {
        $segments[] = ['start' => (float) $t, 'end' => (float) ($t + 5), 'text' => "Sentence at {$t} seconds."];
    }

    return ['segments' => $segments, 'text' => 'full transcript'];
}

it('clamps a selected snippet to the 12-18s window', function () {
    // Selector proposes a 40s moment — far too long for a snippet.
    $client = fakeClient(json_encode(['highlights' => [
        ['start' => 10.0, 'end' => 50.0, 'reason' => 'too long'],
    ]]));

    $picked = (new HighlightSelectorService($client))->select('T', 's', transcriptFixture(), 3);

    expect($picked)->toHaveCount(1)
        ->and($picked[0]['end'] - $picked[0]['start'])->toBe(18.0);
});

it('drops a selected snippet shorter than the minimum', function () {
    $client = fakeClient(json_encode(['highlights' => [
        ['start' => 10.0, 'end' => 15.0, 'reason' => 'only 5s'],
        ['start' => 30.0, 'end' => 45.0, 'reason' => 'good 15s'],
    ]]));

    $picked = (new HighlightSelectorService($client))->select('T', 's', transcriptFixture(), 3);

    expect($picked)->toHaveCount(1)
        ->and($picked[0]['start'])->toBe(30.0);
});

it('validator keeps a valid snippet and applies the corrected cut points', function () {
    $client = fakeClient(json_encode(['snippets' => [
        ['valid' => true, 'start' => 32.0, 'end' => 47.0, 'reason' => 'snapped to sentence'],
    ]]));

    $out = (new SnippetValidatorService($client))->validate(
        [['start' => 30.0, 'end' => 45.0, 'reason' => 'original']],
        transcriptFixture(),
        90.0,
    );

    expect($out)->toHaveCount(1)
        ->and($out[0]['start'])->toBe(32.0)
        ->and($out[0]['end'])->toBe(47.0);
});

it('validator drops a snippet it marks invalid', function () {
    $client = fakeClient(json_encode(['snippets' => [
        ['valid' => true, 'start' => 30.0, 'end' => 45.0, 'reason' => 'good'],
        ['valid' => false, 'reason' => 'starts mid-sentence, no hook'],
    ]]));

    $out = (new SnippetValidatorService($client))->validate(
        [
            ['start' => 30.0, 'end' => 45.0, 'reason' => 'a'],
            ['start' => 60.0, 'end' => 75.0, 'reason' => 'b'],
        ],
        transcriptFixture(),
        90.0,
    );

    expect($out)->toHaveCount(1)
        ->and($out[0]['start'])->toBe(30.0);
});

it('validator falls back to the original ranges when the AI check errors', function () {
    $mock = Mockery::mock(AnthropicOpusClient::class);
    $mock->shouldReceive('complete')->andThrow(new RuntimeException('api down'));

    $original = [['start' => 30.0, 'end' => 45.0, 'reason' => 'original']];
    $out = (new SnippetValidatorService($mock))->validate($original, transcriptFixture(), 90.0);

    expect($out)->toBe($original);
});

it('validator is a no-op when disabled', function () {
    Config::set('pipeline.video.snippet_validation_enabled', false);
    $mock = Mockery::mock(AnthropicOpusClient::class);
    $mock->shouldNotReceive('complete');

    $original = [['start' => 30.0, 'end' => 45.0, 'reason' => 'original']];
    expect((new SnippetValidatorService($mock))->validate($original, transcriptFixture(), 90.0))
        ->toBe($original);
});
