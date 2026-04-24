<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Centralises the two internal handoff tool names used by the Fyn onboarding
 * inline-capture flow.
 *
 * During onboarding, the LLM may emit `delegate_to_capture` when the user
 * volunteers data that belongs in create_/update_ tools, and
 * `capture_complete` when the mini-capture turn is done. Neither tool name
 * reaches the user — HasAiChat emits a synthetic `handoff` SSE event that
 * OnboardingChatDirector::handleInlineCapture consumes internally.
 *
 * Constants (not string literals) so a typo fails at parse time rather than
 * silently mis-routing at runtime.
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
