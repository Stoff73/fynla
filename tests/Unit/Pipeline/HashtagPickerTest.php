<?php

declare(strict_types=1);

use App\Services\Pipeline\PipelineAiClient;
use App\Services\Pipeline\Social\HashtagPicker;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('pipeline.social.default_hashtag_count', 3);
    config()->set('pipeline.social.hashtag_min', 2);
    config()->set('pipeline.social.hashtag_max', 5);
    config()->set('pipeline.social.banned_hashtags', ['#finance', '#money', '#fyp', '#uk']);
});

function fakePipelineAi(string $responseText): PipelineAiClient
{
    $mock = Mockery::mock(PipelineAiClient::class);
    $mock->shouldReceive('complete')->andReturn([
        'text' => $responseText,
        'usage' => [],
        'gbp' => 0.0,
    ]);

    return $mock;
}

afterEach(function () {
    Mockery::close();
});

it('returns 2 to 5 hashtags with a leading #', function () {
    $ai = fakePipelineAi(json_encode(['#ISAallowance', '#UKtax', '#personalfinance']));
    $tags = (new HashtagPicker($ai))->pick('Your ISA allowance', 'How the ISA allowance works.', 'instagram');

    expect($tags)->toHaveCount(3)
        ->and($tags[0])->toStartWith('#')
        ->and($tags)->toContain('#ISAallowance');
});

it('strips banned hashtags', function () {
    $ai = fakePipelineAi(json_encode(['#ISAallowance', '#finance', '#UKtax', '#money']));
    $tags = (new HashtagPicker($ai))->pick('Your ISA allowance', 'How the ISA allowance works.', 'instagram');

    expect($tags)->not->toContain('#finance')
        ->and($tags)->not->toContain('#money')
        ->and($tags)->toContain('#ISAallowance')
        ->and($tags)->toContain('#UKtax');
});

it('adds missing leading # and dedupes case-insensitively', function () {
    $ai = fakePipelineAi(json_encode(['ISAallowance', '#isaallowance', '#UKtax', '#uktax']));
    $tags = (new HashtagPicker($ai))->pick('Your ISA allowance', 'How the ISA allowance works.', 'instagram');

    expect($tags)->toHaveCount(2)
        ->and($tags[0])->toStartWith('#');
});

it('throws when fewer than 2 usable hashtags survive filtering', function () {
    $ai = fakePipelineAi(json_encode(['#finance', '#money']));
    expect(fn () => (new HashtagPicker($ai))->pick('Your ISA allowance', 'How the ISA allowance works.', 'instagram'))
        ->toThrow(RuntimeException::class);
});

it('caps at hashtag_max', function () {
    config()->set('pipeline.social.hashtag_max', 3);
    $ai = fakePipelineAi(json_encode(['#a1', '#a2', '#a3', '#a4', '#a5']));
    $tags = (new HashtagPicker($ai))->pick('Your ISA allowance', 'How the ISA allowance works.', 'instagram');

    expect($tags)->toHaveCount(3);
});
