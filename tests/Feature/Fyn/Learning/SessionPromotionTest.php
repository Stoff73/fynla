<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\ProposedSemanticFact;
use App\Models\User;
use App\Services\AI\ConversationSummariser;
use Illuminate\Support\Facades\Http;

it('stages proposed facts on summarise when learning is enabled', function () {
    config(['fyn.learning_enabled' => true, 'services.xai.api_key' => 'k']);

    Http::fake(['api.x.ai/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => json_encode(['summary' => 's', 'topics' => [], 'entities_mentioned' => [], 'intents_stated' => []])]]]], 200)
        ->push(['choices' => [['message' => ['content' => json_encode(['facts' => [['fact_id' => 'risk-averse', 'title' => 'Risk averse', 'body' => 'User is cautious.', 'valid_from' => null, 'valid_to' => null]]])]]]], 200),
    ]);

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'user', 'content' => 'I am cautious with money']);

    app(ConversationSummariser::class)->summarise($conv->id);

    expect(ProposedSemanticFact::where('user_id', $user->id)->where('status', 'pending')->count())->toBe(1);
});

it('does NOT stage facts when learning is disabled', function () {
    config(['fyn.learning_enabled' => false, 'services.xai.api_key' => 'k']);
    Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 's', 'topics' => [], 'entities_mentioned' => [], 'intents_stated' => []])]]]], 200)]);

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'user', 'content' => 'hi']);

    app(ConversationSummariser::class)->summarise($conv->id);

    expect(ProposedSemanticFact::count())->toBe(0);
    Http::assertSentCount(1);
});

it('still saves the index summary when a proposed fact has a malformed date', function () {
    config(['fyn.learning_enabled' => true, 'services.xai.api_key' => 'k']);

    Http::fake(['api.x.ai/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => json_encode(['summary' => 'persisted summary', 'topics' => [], 'entities_mentioned' => [], 'intents_stated' => []])]]]], 200)
        ->push(['choices' => [['message' => ['content' => json_encode(['facts' => [['fact_id' => 'bad-date', 'title' => 'Bad date', 'body' => 'Has a broken valid_from.', 'valid_from' => 'not-a-date', 'valid_to' => null]]])]]]], 200),
    ]);

    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'user', 'content' => 'I am cautious with money']);

    app(ConversationSummariser::class)->summarise($conv->id);

    expect($conv->fresh()->summary)->toBe('persisted summary');
});
