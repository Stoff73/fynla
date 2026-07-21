<?php

declare(strict_types=1);

namespace App\Services\AI\Loop;

use App\Services\AI\Actions\SurfaceAllowlist;

/**
 * The mode the shared FynLoop runs a turn in (CoALA Phase 5 item 4 —
 * plan §"Decision loop", line 434/497).
 *
 * Per the canonical Two-Fyn contract Fyn has one prompt and two write states.
 * Under Option B (shared loop with thin shells) the difference between the
 * states is a single `session_mode` field each shell sets before delegating to
 * the loop — not a separate code path. This enum is that field, and it bridges
 * to the two string vocabularies the loop must drive:
 *   - {@see persona()} → the ai_messages / HasAiChat::personaOverride tag
 *     ('advice' | 'data_capture'), and
 *   - the {@see SurfaceAllowlist} gate key (the
 *     read-only mode is the persona 'advice').
 */
enum SessionMode: string
{
    case Advice = 'advice';
    case Onboarding = 'onboarding';

    /**
     * The persona tag this mode writes to ai_messages.persona and sets as
     * HasAiChat::personaOverride. Advice is the read-only state; onboarding is
     * the data-capture (write) state.
     */
    public function persona(): string
    {
        return match ($this) {
            self::Advice => 'advice',
            self::Onboarding => 'data_capture',
        };
    }

    /**
     * True when this mode forbids write surfaces (the advice state). The
     * SurfaceAllowlist enforces the boundary mechanically off the persona.
     */
    public function isReadOnly(): bool
    {
        return $this === self::Advice;
    }
}
