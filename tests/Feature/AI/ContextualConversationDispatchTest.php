<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AI\ContextualConversation\ConversationModeResolver;
use App\Services\AI\Loop\ConcurrentTurnQueue;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Fyn\ScriptedAnthropicClient;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Cache::put('ai_provider', 'anthropic');
    app()->instance(Client::class, new ScriptedAnthropicClient([]));
});

afterEach(fn () => Mockery::close());

function grantContextualDispatchConsent(User $user): void
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
}

function contextualDispatchEvents(string $raw): array
{
    return collect(explode("\n\n", $raw))
        ->filter(fn ($chunk) => str_starts_with(trim($chunk), 'data:'))
        ->map(fn ($chunk) => json_decode(preg_replace('/^data:\s*/', '', trim($chunk)), true))
        ->filter()
        ->values()
        ->all();
}

function stubContextualAdvicePath(string $sentinel): void
{
    test()->mock(CoordinatingAgent::class, function ($mock) use ($sentinel): void {
        $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();
        $mock->shouldReceive('setConfirmedCaptureFacts')->zeroOrMoreTimes();
        $mock->shouldReceive('chatWithPromptOverride')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function () use ($sentinel) {
                return (function () use ($sentinel) {
                    yield ['type' => 'content', 'text' => $sentinel];
                    yield ['type' => 'done'];
                })();
            });
    });
}

function activeOnboardingUser(): User
{
    return User::factory()->create([
        'onboarding_completed' => false,
        'active_campaign' => null,
        'onboarding_fyn_step' => 'base_work',
        'is_preview_user' => false,
    ]);
}

function surfaceActionConversation(User $user): AiConversation
{
    return AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'metadata' => [
            'source' => 'surface_action',
            'mode' => 'surface_action',
            'action' => 'add',
            'resource_type' => 'savings',
            'resource_id' => null,
        ],
    ]);
}

it('routes a surface-action message to advice while global onboarding is active', function (): void {
    $user = activeOnboardingUser();
    grantContextualDispatchConsent($user);
    $conversation = surfaceActionConversation($user);
    stubContextualAdvicePath('surface-advice');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", [
            'message' => 'I want to add an account',
        ])
        ->assertOk();

    $events = contextualDispatchEvents($response->streamedContent());
    expect(array_column($events, 'type'))->toContain('thinking')
        ->and(array_column(
            array_filter($events, fn ($event) => ($event['type'] ?? '') === 'content'),
            'text',
        ))->toContain('surface-advice');
});

it('routes a queued surface-action message to advice while global onboarding is active', function (): void {
    $user = activeOnboardingUser();
    grantContextualDispatchConsent($user);
    $conversation = surfaceActionConversation($user);
    $queued = app(ConcurrentTurnQueue::class)->enqueue($conversation, 'queued surface action');
    stubContextualAdvicePath('queued-surface-advice');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conversation->id}/messages/{$queued->id}/stream")
        ->assertOk();

    $events = contextualDispatchEvents($response->streamedContent());
    expect(array_column($events, 'type'))->toContain('thinking')
        ->and(array_column(
            array_filter($events, fn ($event) => ($event['type'] ?? '') === 'content'),
            'text',
        ))->toContain('queued-surface-advice');
});

it('keeps onboarding actions out of a surface-action conversation', function (): void {
    $user = activeOnboardingUser();
    $conversation = surfaceActionConversation($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conversation->id}/action", [
            'action' => 'continue',
        ])
        ->assertOk();

    $text = implode(' ', array_column(
        array_filter(
            contextualDispatchEvents($response->streamedContent()),
            fn ($event) => ($event['type'] ?? '') === 'content',
        ),
        'text',
    ));
    expect($text)->toContain("I'm not sure what to do with that right now.");
});

it('uses immutable typed metadata before the legacy user-state fallback', function (): void {
    $resolver = app(ConversationModeResolver::class);
    $activeUser = activeOnboardingUser();
    $completedUser = User::factory()->create([
        'onboarding_completed' => true,
        'active_campaign' => null,
        'onboarding_fyn_step' => null,
    ]);

    $surface = surfaceActionConversation($activeUser);
    $onboarding = AiConversation::create([
        'user_id' => $completedUser->id,
        'status' => 'active',
        'model_used' => 'test',
        'metadata' => ['source' => 'fyn_onboarding'],
    ]);
    $legacyActive = AiConversation::create([
        'user_id' => $activeUser->id,
        'status' => 'active',
        'model_used' => 'test',
        'metadata' => null,
    ]);
    $legacyCompleted = AiConversation::create([
        'user_id' => $completedUser->id,
        'status' => 'active',
        'model_used' => 'test',
        'metadata' => null,
    ]);

    expect($resolver->routesToOnboarding($surface, $activeUser))->toBeFalse()
        ->and($resolver->routesToOnboarding($onboarding, $completedUser))->toBeTrue()
        ->and($resolver->routesToOnboarding($legacyActive, $activeUser))->toBeTrue()
        ->and($resolver->routesToOnboarding($legacyCompleted, $completedUser))->toBeFalse();
});
