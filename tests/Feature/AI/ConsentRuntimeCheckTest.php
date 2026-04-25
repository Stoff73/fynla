<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

/**
 * Helper — give the user the canonical ai_chat consent so the runtime
 * guard lets the request through.
 */
function grantAiChatConsent(User $user): void
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
}

/**
 * Mock CoordinatingAgent::chatWithPromptOverride — the only method
 * AdviceFyn calls. Yields the supplied event sequence so tests don't
 * have to spin up the LLM stack.
 *
 * @param  callable():\Generator  $generatorFactory
 */
function bindAdviceFynStubGenerator(callable $generatorFactory): void
{
    test()->mock(CoordinatingAgent::class, function ($mock) use ($generatorFactory) {
        $mock->shouldReceive('chatWithPromptOverride')
            ->andReturnUsing($generatorFactory);
    });
}

/**
 * Parse a `data: ...\n\n` SSE byte stream into a Collection of decoded events.
 */
function parseSseEvents(string $raw): \Illuminate\Support\Collection
{
    return collect(explode("\n\n", $raw))
        ->filter(fn ($c) => str_starts_with(trim($c), 'data:'))
        ->map(fn ($c) => json_decode(preg_replace('/^data:\s*/', '', trim($c)), true))
        ->filter()
        ->values();
}

// ─── sendMessage 403 guard ──────────────────────────────────────────────

it('blocks sendMessage with 403 consent_required when ai_chat consent is missing', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'is_preview_user' => false,
    ]);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    $response = $this->actingAs($user)
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", [
            'message' => 'hello',
        ]);

    $response->assertStatus(403)
        ->assertExactJson([
            'error' => 'consent_required',
            'required' => 'ai_chat',
        ]);
});

it('blocks sendMessage when consent was withdrawn (consented=false row exists)', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'is_preview_user' => false,
    ]);
    grantAiChatConsent($user);
    app(ConsentService::class)->withdrawConsent($user, UserConsent::TYPE_AI_CHAT);

    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    $response = $this->actingAs($user)
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", [
            'message' => 'hello',
        ]);

    $response->assertStatus(403)
        ->assertJsonFragment(['error' => 'consent_required', 'required' => 'ai_chat']);
});

it('allows sendMessage to stream when ai_chat consent is granted', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'is_preview_user' => false,
    ]);
    grantAiChatConsent($user);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    bindAdviceFynStubGenerator(function () {
        return (function () {
            yield ['type' => 'content', 'text' => 'Hello'];
            yield ['type' => 'done'];
        })();
    });

    $response = $this->actingAs($user)
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", [
            'message' => 'hi',
        ]);

    $response->assertOk();
    $events = parseSseEvents($response->streamedContent());

    expect($events->pluck('type')->all())->toBe(['content', 'done']);
    expect($events->pluck('type')->filter(fn ($t) => $t === 'consent_required'))->toBeEmpty();
});

// ─── startOnboarding 403 guard ──────────────────────────────────────────

it('blocks startOnboarding with 403 consent_required when ai_chat consent is missing', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'is_preview_user' => false,
        'onboarding_fyn_step' => null,
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/ai-chat/onboarding/start');

    $response->assertStatus(403)
        ->assertExactJson([
            'error' => 'consent_required',
            'required' => 'ai_chat',
        ]);
});

it('allows startOnboarding to stream when ai_chat consent is granted', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'is_preview_user' => false,
        'onboarding_fyn_step' => null,
    ]);
    grantAiChatConsent($user);

    $response = $this->actingAs($user)
        ->postJson('/api/ai-chat/onboarding/start');

    $response->assertOk();
    $events = parseSseEvents($response->streamedContent());

    // The first event is the conversation_created envelope. The director
    // then emits the path_choice turn — we don't pin its exact shape here,
    // just that the stream opened successfully past the consent gate.
    expect($events->isNotEmpty())->toBeTrue();
    expect($events->first()['type'])->toBe('conversation_created');
    expect($events->pluck('type')->filter(fn ($t) => $t === 'consent_required'))->toBeEmpty();
});

// ─── Mid-stream withdrawal ──────────────────────────────────────────────

it('emits consent_required SSE and closes the stream when consent is withdrawn mid-stream', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'is_preview_user' => false,
    ]);
    grantAiChatConsent($user);
    $conv = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    // Generator yields content, then withdraws consent before the next yield.
    // The mid-stream re-check should fire on the second iteration and abort
    // before the second content event reaches the client.
    bindAdviceFynStubGenerator(function () use ($user) {
        return (function () use ($user) {
            yield ['type' => 'content', 'text' => 'first'];
            // Mid-stream consent withdrawal — simulates the user hitting
            // PUT /api/user/consents while the stream is in flight.
            UserConsent::where('user_id', $user->id)
                ->where('consent_type', UserConsent::TYPE_AI_CHAT)
                ->update(['consented' => false]);
            yield ['type' => 'content', 'text' => 'second'];
            yield ['type' => 'done'];
        })();
    });

    $response = $this->actingAs($user)
        ->postJson("/api/ai-chat/conversations/{$conv->id}/messages", [
            'message' => 'hi',
        ]);

    $response->assertOk();
    $events = parseSseEvents($response->streamedContent());

    $types = $events->pluck('type')->all();

    // First content event got through, then consent_required, then nothing
    // (no 'second' content, no 'done').
    expect($types)->toBe(['content', 'consent_required']);

    $consentEvent = $events->firstWhere('type', 'consent_required');
    expect($consentEvent['required'] ?? null)->toBe('ai_chat');
});
