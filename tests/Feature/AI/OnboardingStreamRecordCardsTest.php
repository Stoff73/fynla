<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Fyn\ScriptedAnthropicClient;
use Tests\Support\Fyn\Sse;

uses(RefreshDatabase::class);

// CSJ 2026-09-15 (prod, /m, income step): after a save the screen showed the
// "Got it" acknowledgement, the state machine's own gate question with its
// Yes/No bubbles, AND a third "Updated Income. [View Income]" card in the same
// turn. The canonical verify sequence is capture → the ONE gate question →
// No → navigate to the page, which is where the record is seen. Record cards
// therefore never render mid-onboarding, on any surface. The director still
// yields the events (its own accounting keys on them); the controller's
// egress is the one place they are withheld from clients.

it('streams only the acknowledgement and the gate question after an income capture — no record card', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Cache::put('ai_provider', 'anthropic');
    app()->instance(Client::class, new ScriptedAnthropicClient([]));

    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_EXPENDITURE,
        'employment_status' => 'employed',
    ]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'metadata' => ['source' => 'fyn_onboarding'],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => '2400']);

    $response->assertOk();
    $events = collect(Sse::frames($response->streamedContent()));
    $types = $events->pluck('type')->all();

    expect((float) $user->fresh()->monthly_expenditure)->toBe(2400.0)
        ->and($types)->toContain('onboarding_advance')
        ->and($types)->not->toContain('entity_updated')
        ->and($types)->not->toContain('entity_created')
        ->and($types)->not->toContain('capture_complete');
});

it('keeps record cards on the advice stream once onboarding is complete', function (): void {
    Cache::put('ai_provider', 'anthropic');
    app()->instance(Client::class, new ScriptedAnthropicClient([]));

    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test']);

    test()->mock(CoordinatingAgent::class, function ($mock): void {
        $mock->shouldReceive('chatWithPromptOverride')->andReturnUsing(fn () => (function () {
            yield ['type' => 'content', 'text' => 'Added.'];
            yield ['type' => 'entity_created', 'entity_type' => 'savings_account', 'entity_id' => 7, 'name' => 'Halifax Joint Savings'];
            yield ['type' => 'capture_complete', 'summary' => 'Saved.', 'records_created' => [], 'fields_updated' => []];
            yield ['type' => 'done'];
        })());
    });

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'add my halifax account']);

    $response->assertOk();
    $types = collect(Sse::frames($response->streamedContent()))->pluck('type')->all();

    expect($types)->toContain('entity_created')
        ->and($types)->toContain('capture_complete');
});
