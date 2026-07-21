<?php

declare(strict_types=1);

namespace App\Services\AI\Loop;

use App\Models\AiConversation;
use Illuminate\Support\Carbon;

/**
 * The resumption surface (CoALA Phase 5 item 7 — FR-M9).
 *
 * A turn that ends without finishing {@see flag}s the conversation; the user's
 * next session reads {@see latestForUser} and the chat surfaces "last time we
 * didn't finish — continue, or start fresh?". Either choice {@see clear}s it.
 */
final class ResumptionService
{
    /**
     * Record that this conversation has unfinished business. The latest reason
     * wins — the most recent unfinished state is the most relevant to surface.
     */
    public function flag(AiConversation $conversation, string $reason, string $message): void
    {
        $conversation->update([
            'pending_resumption' => [
                'reason' => $reason,
                'message' => $message,
                'flagged_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pending(AiConversation $conversation): ?array
    {
        return ! empty($conversation->pending_resumption) ? $conversation->pending_resumption : null;
    }

    public function clear(AiConversation $conversation): void
    {
        if (empty($conversation->pending_resumption)) {
            return;
        }

        $conversation->update(['pending_resumption' => null]);
    }

    /**
     * The user's most recent conversation carrying a pending resumption, if any.
     */
    public function latestForUser(int $userId): ?AiConversation
    {
        return AiConversation::forUser($userId)
            ->whereNotNull('pending_resumption')
            ->orderByDesc('last_message_at')
            ->first();
    }
}
