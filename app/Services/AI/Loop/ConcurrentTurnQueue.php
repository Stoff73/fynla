<?php

declare(strict_types=1);

namespace App\Services\AI\Loop;

use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Carbon;

/**
 * The concurrent-turn queue (CoALA Phase 5 item 6 — plan §"Decision loop", FR-M7).
 *
 * A "turn" is a user-message row. The row's {@see AiMessageStatus} tracks its
 * place in the queue: a turn sent while another is `processing` is `queued`; the
 * in-flight turn is `processing` and ends `answered`. A `queued` turn can be
 * `cancelled` by the user before it starts, or swept to `expired` once it has
 * waited past the TTL.
 *
 * This service owns the state transitions and the queries the controller and
 * {@see FynLoop} need; the cross-request transport (how a queued turn is later
 * streamed, the `turn_queued` SSE event) is wired at the controller. The
 * single-conversation, one-streaming-worker contention this models is bounded
 * (depth cap 3, p95 depth ≤ 1).
 */
final class ConcurrentTurnQueue
{
    /** True when a turn is already streaming for this conversation. */
    public function hasInFlightTurn(AiConversation $conversation): bool
    {
        return $conversation->messages()
            ->where('status', AiMessageStatus::Processing->value)
            ->exists();
    }

    /** How many live turns are waiting behind the in-flight one. */
    public function queuedDepth(AiConversation $conversation): int
    {
        return $conversation->messages()
            ->where('status', AiMessageStatus::Queued->value)
            ->count();
    }

    public function depthCap(): int
    {
        return (int) config('fyn.queue.depth_cap', 3);
    }

    public function isFull(AiConversation $conversation): bool
    {
        return $this->queuedDepth($conversation) >= $this->depthCap();
    }

    /**
     * Persist a new user turn as the in-flight (`processing`) turn. Used when no
     * turn is already streaming for the conversation.
     */
    public function startTurn(AiConversation $conversation, string $content): AiMessage
    {
        return $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
            'status' => AiMessageStatus::Processing,
        ]);
    }

    /**
     * Persist a new user turn as `queued` behind an in-flight one. Returns null
     * when the queue is already at its depth cap (the caller surfaces a "too many
     * pending" rejection).
     */
    public function enqueue(AiConversation $conversation, string $content): ?AiMessage
    {
        if ($this->isFull($conversation)) {
            return null;
        }

        return $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
            'status' => AiMessageStatus::Queued,
        ]);
    }

    /** Mark the in-flight turn answered once its stream has completed. */
    public function completeTurn(AiMessage $turn): void
    {
        $turn->update(['status' => AiMessageStatus::Answered]);
    }

    /**
     * Pop the oldest live queued turn to `processing` and return it, sweeping any
     * TTL-expired turns first. Returns null when nothing live is waiting.
     */
    public function popNext(AiConversation $conversation): ?AiMessage
    {
        $this->expireStale($conversation);

        $next = $conversation->messages()
            ->where('status', AiMessageStatus::Queued->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($next === null) {
            return null;
        }

        $next->update(['status' => AiMessageStatus::Processing]);

        return $next;
    }

    /**
     * Cancel a queued turn. Only a still-`queued` turn can be cancelled — a turn
     * already `processing` (or terminal) is left untouched. Returns true when a
     * cancellation happened.
     */
    public function cancel(AiMessage $turn): bool
    {
        if ($turn->status !== AiMessageStatus::Queued) {
            return false;
        }

        $turn->update(['status' => AiMessageStatus::Cancelled]);

        return true;
    }

    /**
     * Sweep queued turns that have waited past the TTL to `expired`. Returns how
     * many were expired.
     */
    public function expireStale(AiConversation $conversation): int
    {
        $cutoff = Carbon::now()->subMinutes((int) config('fyn.queue.ttl_minutes', 10));

        return $conversation->messages()
            ->where('status', AiMessageStatus::Queued->value)
            ->where('created_at', '<', $cutoff)
            ->update(['status' => AiMessageStatus::Expired->value]);
    }
}
