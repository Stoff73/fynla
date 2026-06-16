<?php

declare(strict_types=1);

use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;

/**
 * CoALA Phase 5 item 6 (FR-M7) increment 1 — the concurrent-turn queue
 * schema foundation: the ai_messages.status column, its enum cast, and the
 * queue config. The queue mechanics (enqueue / pop / cancel / expire) land in
 * increment 2 against this foundation.
 */
function statusTestConversation(): AiConversation
{
    $user = User::factory()->create();

    return AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Status',
        'message_count' => 0,
    ]);
}

it('defaults a new ai_message to answered', function () {
    $message = AiMessage::create([
        'conversation_id' => statusTestConversation()->id,
        'role' => 'user',
        'content' => 'hi',
    ]);

    expect($message->fresh()->status)->toBe(AiMessageStatus::Answered);
});

it('casts a queued status to the enum', function () {
    $message = AiMessage::create([
        'conversation_id' => statusTestConversation()->id,
        'role' => 'user',
        'content' => 'queued one',
        'status' => AiMessageStatus::Queued,
    ]);

    expect($message->fresh()->status)->toBe(AiMessageStatus::Queued);
});

it('exposes the skippable terminal states and isLive', function () {
    expect(AiMessageStatus::skippable())->toBe([AiMessageStatus::Cancelled, AiMessageStatus::Expired])
        ->and(AiMessageStatus::Queued->isLive())->toBeTrue()
        ->and(AiMessageStatus::Processing->isLive())->toBeTrue()
        ->and(AiMessageStatus::Answered->isLive())->toBeFalse()
        ->and(AiMessageStatus::Cancelled->isLive())->toBeFalse();
});

it('exposes the queue config with FR-M7 defaults', function () {
    expect(config('fyn.queue.depth_cap'))->toBe(3)
        ->and(config('fyn.queue.ttl_minutes'))->toBe(10);
});
