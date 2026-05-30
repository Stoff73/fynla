<?php

declare(strict_types=1);

use App\Jobs\ConversationSummariserJob;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * CoALA Phase 5 item 7 (FR-M10) — inactivity pause: idle conversations are
 * consolidated + marked paused; paused stays in history; sending reopens it.
 */
function idleConversation(User $user, int $minutesIdle, array $extra = []): AiConversation
{
    return AiConversation::create(array_merge([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Conv',
        'message_count' => 1,
        'last_message_at' => now()->subMinutes($minutesIdle),
    ], $extra));
}

it('pauses idle active conversations and dispatches consolidation (FR-M10)', function () {
    Queue::fake();
    $user = User::factory()->create(['onboarding_completed' => true]);
    $idle = idleConversation($user, 5);
    $recent = idleConversation($user, 0);

    Artisan::call('ai:conversations:summarise-stale', ['--idle-minutes' => 3, '--pause' => true]);

    expect($idle->fresh()->status)->toBe('paused')
        ->and($recent->fresh()->status)->toBe('active');
    Queue::assertPushed(ConversationSummariserJob::class);
});

it('does not pause an in-flight onboarding conversation', function () {
    Queue::fake();
    $user = User::factory()->create(['onboarding_completed' => false]);
    $conv = idleConversation($user, 5, ['metadata' => ['source' => 'fyn_onboarding']]);

    Artisan::call('ai:conversations:summarise-stale', ['--idle-minutes' => 3, '--pause' => true]);

    expect($conv->fresh()->status)->toBe('active');
});

it('keeps paused conversations in the history list', function () {
    $user = User::factory()->create(['onboarding_completed' => true]);
    idleConversation($user, 0, ['status' => 'paused', 'title' => 'Paused one']);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/ai-chat/conversations');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('title'))->toContain('Paused one');
});

it('reopens a paused conversation on send', function () {
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    $conv = idleConversation($user, 0, ['status' => 'paused', 'message_count' => 0]);
    Sanctum::actingAs($user);

    $response = $this->postJson("/api/ai-chat/conversations/{$conv->id}/messages", ['message' => 'hello again']);
    $response->assertOk();
    $response->streamedContent();

    expect($conv->fresh()->status)->toBe('active');
});
