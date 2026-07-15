<?php

declare(strict_types=1);

use App\Models\Insights\InsightArticle;
use App\Services\Pipeline\AnthropicOpusClient;
use App\Services\Pipeline\Social\HashtagPicker;

uses(\Tests\TestCase::class);

beforeEach(function () {
    config()->set('pipeline.social.default_hashtag_count', 3);
    config()->set('pipeline.social.hashtag_min', 2);
    config()->set('pipeline.social.hashtag_max', 5);
    config()->set('pipeline.social.banned_hashtags', ['#finance', '#money', '#fyp', '#uk']);
});

function makeArticle(): InsightArticle
{
    $article = new InsightArticle();
    $article->title = 'Your ISA allowance';
    $article->summary = 'How the ISA allowance works.';
    $article->category = 'savings-isa';

    return $article;
}

function fakeAnthropic(string $responseText): AnthropicOpusClient
{
    $mock = Mockery::mock(AnthropicOpusClient::class);
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
    $anthropic = fakeAnthropic(json_encode(['#ISAallowance', '#UKtax', '#personalfinance']));
    $tags = (new HashtagPicker($anthropic))->pick(makeArticle(), 'instagram');

    expect($tags)->toHaveCount(3)
        ->and($tags[0])->toStartWith('#')
        ->and($tags)->toContain('#ISAallowance');
});

it('strips banned hashtags', function () {
    $anthropic = fakeAnthropic(json_encode(['#ISAallowance', '#finance', '#UKtax', '#money']));
    $tags = (new HashtagPicker($anthropic))->pick(makeArticle(), 'instagram');

    expect($tags)->not->toContain('#finance')
        ->and($tags)->not->toContain('#money')
        ->and($tags)->toContain('#ISAallowance')
        ->and($tags)->toContain('#UKtax');
});

it('adds missing leading # and dedupes case-insensitively', function () {
    $anthropic = fakeAnthropic(json_encode(['ISAallowance', '#isaallowance', '#UKtax', '#uktax']));
    $tags = (new HashtagPicker($anthropic))->pick(makeArticle(), 'instagram');

    expect($tags)->toHaveCount(2)
        ->and($tags[0])->toStartWith('#');
});

it('throws when fewer than 2 usable hashtags survive filtering', function () {
    $anthropic = fakeAnthropic(json_encode(['#finance', '#money']));
    expect(fn () => (new HashtagPicker($anthropic))->pick(makeArticle(), 'instagram'))
        ->toThrow(\RuntimeException::class);
});

it('caps at hashtag_max', function () {
    config()->set('pipeline.social.hashtag_max', 3);
    $anthropic = fakeAnthropic(json_encode(['#a1', '#a2', '#a3', '#a4', '#a5']));
    $tags = (new HashtagPicker($anthropic))->pick(makeArticle(), 'instagram');

    expect($tags)->toHaveCount(3);
});
