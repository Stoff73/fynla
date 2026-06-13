<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of an ai_messages row in the concurrent-turn queue
 * (CoALA Phase 5 item 6 — plan §"Decision loop", FR-M7).
 *
 * A turn the user sends while another is still streaming is persisted
 * immediately as `queued`. When the in-flight turn finishes the queue pops the
 * oldest live `queued` row to `processing`; it ends `answered`. The user may
 * `cancel` a `queued` row before it starts, and a `queued` row left past the
 * configured TTL while the user is offline is swept to `expired`.
 *
 * Every pre-queue message (and every assistant reply) is `answered`: it was
 * never queued, so it is in its terminal answered state from creation. The
 * migration backfills existing rows to `answered` for the same reason.
 */
enum AiMessageStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Answered = 'answered';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Terminal states a queue pop must skip (the turn will never run).
     *
     * @return list<self>
     */
    public static function skippable(): array
    {
        return [self::Cancelled, self::Expired];
    }

    public function isLive(): bool
    {
        return $this === self::Queued || $this === self::Processing;
    }
}
