<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

use App\Models\AiMessage;
use Throwable;

/**
 * Runs a pointer's handler and (optionally) records fetch provenance on the
 * assistant message. A handler failure degrades to null + a logged report —
 * never breaks the turn (Phase-1 resilience posture).
 */
final class FetchDispatcher
{
    public function __construct(private readonly FetchHandlerRegistry $handlers) {}

    public function run(Pointer $pointer, FetchContext $ctx, ?AiMessage $message = null): ?FetchResult
    {
        try {
            $result = $this->handlers->get($pointer->handler)->fetch($ctx);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($message !== null) {
            $this->recordProvenance($message, $result->provenance($pointer->pointerId, $pointer->handler));
        }

        return $result;
    }

    /** @param array<string,string> $entry */
    private function recordProvenance(AiMessage $message, array $entry): void
    {
        $meta = $message->metadata ?? [];
        $meta['fetch_provenance'] = array_merge($meta['fetch_provenance'] ?? [], [$entry]);
        $message->update(['metadata' => $meta]);
    }
}
