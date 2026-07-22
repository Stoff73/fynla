<?php

declare(strict_types=1);

use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Loop\ConcurrentTurnQueue;

/**
 * CoALA Phase 5 item 6 (FR-M7) increment 2 — the concurrent-turn queue service.
 * State transitions and queries; the controller transport wires on top.
 */
function queueConversation(): AiConversation
{
    return AiConversation::create([
        'user_id' => User::factory()->create()->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Queue',
        'message_count' => 0,
    ]);
}

beforeEach(function () {
    $this->queue = app(ConcurrentTurnQueue::class);
    config(['fyn.queue.depth_cap' => 3, 'fyn.queue.ttl_minutes' => 10]);
});

it('detects an in-flight turn only once one is processing', function () {
    $conv = queueConversation();

    expect($this->queue->hasInFlightTurn($conv))->toBeFalse();

    $this->queue->startTurn($conv, 'first');

    expect($this->queue->hasInFlightTurn($conv))->toBeTrue();
});

it('enqueues up to the depth cap then rejects', function () {
    $conv = queueConversation();
    $this->queue->startTurn($conv, 'in-flight');

    expect($this->queue->enqueue($conv, 'b1'))->not->toBeNull()
        ->and($this->queue->enqueue($conv, 'b2'))->not->toBeNull()
        ->and($this->queue->enqueue($conv, 'b3'))->not->toBeNull()
        ->and($this->queue->queuedDepth($conv))->toBe(3)
        ->and($this->queue->isFull($conv))->toBeTrue()
        ->and($this->queue->enqueue($conv, 'b4-rejected'))->toBeNull()
        ->and($this->queue->queuedDepth($conv))->toBe(3);
});

it('completes the in-flight turn to answered', function () {
    $conv = queueConversation();
    $turn = $this->queue->startTurn($conv, 'q');

    $this->queue->completeTurn($turn);

    expect($turn->fresh()->status)->toBe(AiMessageStatus::Answered)
        ->and($this->queue->hasInFlightTurn($conv))->toBeFalse();
});

it('pops the oldest queued turn to processing, FIFO', function () {
    $conv = queueConversation();
    $this->queue->startTurn($conv, 'in-flight');
    $first = $this->queue->enqueue($conv, 'oldest');
    $this->queue->enqueue($conv, 'newer');

    // The in-flight turn finishes; pop the next.
    $popped = $this->queue->popNext($conv);

    expect($popped->id)->toBe($first->id)
        ->and($popped->status)->toBe(AiMessageStatus::Processing)
        ->and($this->queue->queuedDepth($conv))->toBe(1);
});

it('returns null from popNext when nothing is queued', function () {
    expect($this->queue->popNext(queueConversation()))->toBeNull();
});

it('cancels only a still-queued turn', function () {
    $conv = queueConversation();
    $this->queue->startTurn($conv, 'in-flight');
    $queued = $this->queue->enqueue($conv, 'cancel me');

    expect($this->queue->cancel($queued))->toBeTrue()
        ->and($queued->fresh()->status)->toBe(AiMessageStatus::Cancelled);

    // A processing turn cannot be cancelled.
    $processing = $this->queue->startTurn($conv, 'already running');
    expect($this->queue->cancel($processing))->toBeFalse()
        ->and($processing->fresh()->status)->toBe(AiMessageStatus::Processing);
});

it('skips a cancelled turn when popping', function () {
    $conv = queueConversation();
    $this->queue->startTurn($conv, 'in-flight');
    $cancelled = $this->queue->enqueue($conv, 'will cancel');
    $live = $this->queue->enqueue($conv, 'still live');
    $this->queue->cancel($cancelled);

    expect($this->queue->popNext($conv)->id)->toBe($live->id);
});

it('expires queued turns past the TTL', function () {
    $conv = queueConversation();
    $this->queue->startTurn($conv, 'in-flight');
    $stale = $this->queue->enqueue($conv, 'stale');

    $this->travel(11)->minutes();
    $expired = $this->queue->expireStale($conv);

    expect($expired)->toBe(1)
        ->and($stale->fresh()->status)->toBe(AiMessageStatus::Expired)
        ->and($this->queue->queuedDepth($conv))->toBe(0);
});

it('expires a stale queued turn during popNext and pops the next live one', function () {
    $conv = queueConversation();
    $this->queue->startTurn($conv, 'in-flight');
    $stale = $this->queue->enqueue($conv, 'stale');

    $this->travel(11)->minutes();
    $fresh = $this->queue->enqueue($conv, 'fresh');

    $popped = $this->queue->popNext($conv);

    expect($stale->fresh()->status)->toBe(AiMessageStatus::Expired)
        ->and($popped->id)->toBe($fresh->id);
});
