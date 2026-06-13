<?php

declare(strict_types=1);

namespace App\Services\AI\Actions;

use App\Services\AI\AdviceFyn;
use App\Services\AI\Ground\GroundGate;

/**
 * The unified session_mode → allowed-surfaces matrix
 * (CoALA Phase 5 item 3 — plan line 315, FR-M4).
 *
 * Single source for Fyn's read/write boundary. It subsumes two pieces of gating
 * that previously lived apart:
 *   - the AdviceFyn catalogue strip (array_diff against WRITE_TOOLS), and
 *   - the {@see GroundGate} dispatch predicate (item 2),
 *     which now delegates here so the two can never drift.
 *
 * Behaviour is byte-for-byte that of the item-2 gate (a parity test asserts it):
 *   - 'advice'        read-only state: every {@see AdviceFyn::WRITE_TOOLS}
 *                     surface denied; every other surface allowed.
 *   - 'data_capture'  onboarding write state: pass-through. The tight per-focus
 *                     allowlist stays with OnboardingPromptBuilder::toolsForFocus
 *                     and the director wrapper — moving it into this matrix is a
 *                     deliberately separate, later PR (regulated-boundary risk).
 *   - null / other    legacy / unset: pass-through. The canonical contract
 *                     forbids disturbing legitimate onboarding or legacy writes
 *                     at the gate (a paused-onboarding user routes here).
 *
 * "Fail-closed" applies WITHIN the restricted advice mode: a write surface is
 * denied unless it is explicitly a read. Other modes are intentionally
 * pass-through, exactly as the standalone gate was.
 */
final class SurfaceAllowlist
{
    /** The read-only persona — the only mode that restricts surfaces today. */
    public const READ_ONLY_MODE = 'advice';

    public function __construct(
        private readonly ToolActionMapper $mapper,
    ) {}

    /**
     * True when the given surface may execute in the given session mode.
     */
    public function allows(?string $sessionMode, string $surface): bool
    {
        if ($sessionMode !== self::READ_ONLY_MODE) {
            return true;
        }

        return ! $this->mapper->isWriteSurface($surface);
    }
}
