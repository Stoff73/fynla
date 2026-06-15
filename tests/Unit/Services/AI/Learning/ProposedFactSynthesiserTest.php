<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Learning\ProposedFactSynthesiser;
use Illuminate\Support\Facades\Http;

it('returns durable user-scoped candidate facts from a conversation', function () {
    config(['services.xai.api_key' => 'test-key']);
    Http::fake(['api.x.ai/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'facts' => [[
                'fact_id' => 'retires-2041', 'title' => 'Plans to retire 2041',
                'body' => 'User stated they want to retire in 2041.',
                'valid_from' => null, 'valid_to' => null,
            ]],
        ])]]],
    ], 200)]);

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $facts = app(ProposedFactSynthesiser::class)->synthesise($conv->id, 'User: I want to retire at 60.');

    expect($facts)->toHaveCount(1)->and($facts[0]['fact_id'])->toBe('retires-2041');
});

it('returns empty array when api key is not configured', function () {
    config(['services.xai.api_key' => '']);
    Http::fake();

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $facts = app(ProposedFactSynthesiser::class)->synthesise($conv->id, 'User: I want to retire at 60.');

    Http::assertNothingSent();
    expect($facts)->toBe([]);
});

it('returns empty array on non-successful http response', function () {
    config(['services.xai.api_key' => 'test-key']);
    Http::fake(['api.x.ai/*' => Http::response([], 500)]);

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $facts = app(ProposedFactSynthesiser::class)->synthesise($conv->id, 'User: tell me about pensions.');

    expect($facts)->toBe([]);
});

it('returns empty array when provider returns malformed json content', function () {
    config(['services.xai.api_key' => 'test-key']);
    Http::fake(['api.x.ai/*' => Http::response([
        'choices' => [['message' => ['content' => 'this is not json at all']]],
    ], 200)]);

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $facts = app(ProposedFactSynthesiser::class)->synthesise($conv->id, 'User: how much ISA can I use?');

    expect($facts)->toBe([]);
});

it('returns empty array when provider returns an empty facts array', function () {
    config(['services.xai.api_key' => 'test-key']);
    Http::fake(['api.x.ai/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode(['facts' => []])]]],
    ], 200)]);

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $facts = app(ProposedFactSynthesiser::class)->synthesise($conv->id, 'User: what is an ISA?');

    expect($facts)->toBe([]);
});
