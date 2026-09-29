<?php

declare(strict_types=1);

namespace App\Services\AI\Loop;

use App\Models\AiConversation;
use Illuminate\Support\Facades\Cache;

/**
 * Which user turns a conversation has already taken, by the id the client
 * gives each turn (`turn_id`). "Try again" after a dropped connection re-sends
 * the same id; the server has usually finished that turn anyway (the stream
 * runs to the end with ignore_user_abort), so answering it a second time would
 * duplicate the reply and, during onboarding, the record it wrote. One home for
 * that rule on every surface (Rule 20): the send endpoint asks here first.
 */
final class FynTurnLedger
{
    public const STARTED = 'started';

    public const DONE = 'done';

    private const TTL_SECONDS = 86400;

    /** 'started', 'done', or null for a turn this conversation has not seen. */
    public function seen(AiConversation $conversation, ?string $turnId): ?string
    {
        $key = $this->key($conversation, $turnId);

        return $key === null ? null : Cache::get($key);
    }

    public function start(AiConversation $conversation, ?string $turnId): void
    {
        $this->put($conversation, $turnId, self::STARTED);
    }

    public function finish(AiConversation $conversation, ?string $turnId): void
    {
        $this->put($conversation, $turnId, self::DONE);
    }

    private function put(AiConversation $conversation, ?string $turnId, string $state): void
    {
        $key = $this->key($conversation, $turnId);
        if ($key !== null) {
            Cache::put($key, $state, self::TTL_SECONDS);
        }
    }

    private function key(AiConversation $conversation, ?string $turnId): ?string
    {
        return is_string($turnId) && $turnId !== '' ? 'fyn:turn:'.$conversation->id.':'.$turnId : null;
    }
}
