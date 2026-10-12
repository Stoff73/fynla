<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConversation;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The messages the model sees of a conversation: the newest MESSAGES user and
 * assistant rows. One home for the window, read by the history the model is
 * given (HasAiChat::buildMessageHistory), the note that earlier messages are
 * out of view (FynContextAssembler) and the search that reads them
 * (search_conversation_index), so the three cannot disagree on what is in
 * view (walk fix plan T13).
 */
final class ConversationWindow
{
    public const MESSAGES = 20;

    /** The conversation's user and assistant rows, newest first. */
    public static function newestFirst(AiConversation $conversation): HasMany
    {
        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** How many user and assistant rows are older than the window. */
    public static function earlierCount(AiConversation $conversation): int
    {
        return max(0, self::newestFirst($conversation)->count() - self::MESSAGES);
    }

    /**
     * The user's own messages older than the window that contain any of the
     * words, oldest first, exactly as written.
     *
     * @param  list<string>  $words
     * @return list<array{said: string, said_at: ?string}>
     */
    public static function earlierUserMessagesWith(AiConversation $conversation, array $words, int $limit = 10): array
    {
        $words = array_values(array_filter(array_map('trim', $words), static fn (string $w): bool => $w !== ''));
        if ($words === []) {
            return [];
        }

        return self::newestFirst($conversation)
            ->skip(self::MESSAGES)
            ->take(PHP_INT_MAX)
            ->get(['id', 'role', 'content', 'created_at'])
            ->where('role', 'user')
            ->filter(static function ($message) use ($words): bool {
                foreach ($words as $word) {
                    if (mb_stripos((string) $message->content, $word) !== false) {
                        return true;
                    }
                }

                return false;
            })
            ->reverse()
            ->take($limit)
            ->map(static fn ($message): array => [
                'said' => (string) $message->content,
                'said_at' => $message->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
