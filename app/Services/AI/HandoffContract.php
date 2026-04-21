<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Centralises the two internal handoff tool names used by FynPersonaOrchestrator.
 *
 * Advice Fyn emits `delegate_to_capture` when it cannot answer without data
 * the user hasn't supplied. Data-capture Fyn emits `capture_complete` when
 * the capture sub-conversation is done. Neither tool reaches the user — the
 * invoker strips them from the SSE stream and the orchestrator interprets
 * them as persona-state transitions.
 *
 * Constants (not string literals) so a typo fails at parse time rather than
 * silently mis-routing at runtime. Registry integrity is enforced via the
 * FynPersonaRegistryTest downstream (Task 9).
 */
final class HandoffContract
{
    public const DELEGATE_TO_CAPTURE = 'delegate_to_capture';

    public const CAPTURE_COMPLETE = 'capture_complete';

    /**
     * @return list<string>
     */
    public static function internalToolNames(): array
    {
        return [
            self::DELEGATE_TO_CAPTURE,
            self::CAPTURE_COMPLETE,
        ];
    }

    public static function isInternalTool(string $toolName): bool
    {
        return in_array($toolName, self::internalToolNames(), true);
    }
}
