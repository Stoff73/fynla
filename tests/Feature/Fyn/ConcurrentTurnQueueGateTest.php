<?php

declare(strict_types=1);

use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AI\Loop\ConcurrentTurnQueue;
use App\Services\GDPR\ConsentService;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

/**
 * CoALA Phase 5 item 6 (FR-M7) increment 2b — the in-flight gate on
 * POST /messages: a turn sent while another is streaming is queued (202) or
 * rejected past the depth cap (429); an uncontended turn streams and releases
 * the per-conversation lock.
 */
function gateConversation(): array
{
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'is_preview_user' => false,
    ]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Gate',
        'message_count' => 0,
    ]);
    Sanctum::actingAs($user);

    return [$user, $conversation];
}

it('queues a turn (202) when one is already in-flight', function () {
    [, $conversation] = gateConversation();

    // Simulate turn A in-flight by holding the conversation lock.
    Cache::lock('fyn:inflight:'.$conversation->id, 300)->get();

    $response = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", [
        'message' => 'second turn while A streams',
    ]);

    $response->assertStatus(202)
        ->assertJson(['status' => 'queued']);

    expect($conversation->messages()->where('status', AiMessageStatus::Queued->value)->count())->toBe(1);
});

it('rejects with 429 when the queue is at the depth cap', function () {
    [, $conversation] = gateConversation();
    config(['fyn.queue.depth_cap' => 3]);

    Cache::lock('fyn:inflight:'.$conversation->id, 300)->get();
    $queue = app(ConcurrentTurnQueue::class);
    $queue->enqueue($conversation, 'b1');
    $queue->enqueue($conversation, 'b2');
    $queue->enqueue($conversation, 'b3');

    $response = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", [
        'message' => 'b4 — over cap',
    ]);

    $response->assertStatus(429)
        ->assertJson(['error' => 'queue_full']);

    expect($queue->queuedDepth($conversation))->toBe(3);
});

it('streams an uncontended turn and releases the in-flight lock', function () {
    [, $conversation] = gateConversation();

    $response = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", [
        'message' => 'only turn',
    ]);

    $response->assertOk();
    // Consume the stream so the finally{} (lock release) runs.
    $response->streamedContent();

    // The lock is free again — a fresh acquire succeeds.
    expect(Cache::lock('fyn:inflight:'.$conversation->id, 5)->get())->toBeTrue();
});

it('streams a queued turn (2c) without duplicating the user row and marks it answered', function () {
    [, $conversation] = gateConversation();
    $queued = app(ConcurrentTurnQueue::class)->enqueue($conversation, 'what is my net worth');

    $response = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages/{$queued->id}/stream");

    $response->assertOk();
    $response->streamedContent(); // drive the finally{} (completeTurn + release)

    // Same row, now answered (stable id) — and NOT a second user row.
    expect($queued->fresh()->status)->toBe(AiMessageStatus::Answered)
        ->and($conversation->messages()->where('role', 'user')->count())->toBe(1)
        // Lock released so the next queued turn can stream.
        ->and(Cache::lock('fyn:inflight:'.$conversation->id, 5)->get())->toBeTrue();
});

it('returns 409 not_queued for a message that is not queued', function () {
    [, $conversation] = gateConversation();

    $response = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages/99999/stream");

    $response->assertStatus(409)->assertJson(['error' => 'not_queued']);
});

it('returns 409 busy when another turn holds the in-flight lock', function () {
    [, $conversation] = gateConversation();
    $queued = app(ConcurrentTurnQueue::class)->enqueue($conversation, 'q');
    Cache::lock('fyn:inflight:'.$conversation->id, 300)->get();

    $response = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages/{$queued->id}/stream");

    $response->assertStatus(409)->assertJson(['error' => 'busy']);
    // Still queued — not consumed.
    expect($queued->fresh()->status)->toBe(AiMessageStatus::Queued);
});

it('cancels a queued turn (200)', function () {
    [, $conversation] = gateConversation();
    $queued = app(ConcurrentTurnQueue::class)->enqueue($conversation, 'cancel me');

    $response = $this->deleteJson("/api/ai-chat/conversations/{$conversation->id}/messages/{$queued->id}");

    $response->assertOk()->assertJson(['status' => 'cancelled']);
    expect($queued->fresh()->status)->toBe(AiMessageStatus::Cancelled);
});

it('returns 409 not_cancellable for a message that is not queued', function () {
    [, $conversation] = gateConversation();

    $response = $this->deleteJson("/api/ai-chat/conversations/{$conversation->id}/messages/99999");

    $response->assertStatus(409)->assertJson(['error' => 'not_cancellable']);
});
