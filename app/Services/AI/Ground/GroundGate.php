<?php

declare(strict_types=1);

namespace App\Services\AI\Ground;

use App\Services\AI\AdviceFyn;

/**
 * CoALA `ground` surface gate (Phase 5 PR 2 — standalone, pre-planner).
 *
 * The mechanical write-safety boundary for the read-only advice state. Today
 * advice-mode safety rests on AdviceFyn stripping WRITE_TOOLS from the tool
 * catalogue (array_diff) so the LLM never sees them — prevention, but a single
 * point of failure (a hallucinated tool id, a future strip-logic regression, a
 * delegate_to_capture edge case). This gate adds enforcement at the dispatch
 * point: in the 'advice' persona, any WRITE_TOOLS surface is rejected before it
 * can execute. Defence-in-depth on a regulatory boundary — "mechanical, not
 * promised" (PRD §canonical, plan Option B / belt-and-braces).
 *
 * Scope (this PR): protects ONLY the read-only advice state. Other personas
 * (data_capture, and null legacy non-advice paths) are intentionally untouched
 * so legitimate onboarding writes are never disturbed. The full
 * session_mode → allowed-surfaces allowlist (fail-closed for every mode) lands
 * with the typed Action enum, when session_mode becomes a first-class working-
 * memory field.
 *
 * Single source of truth for the write surfaces is AdviceFyn::WRITE_TOOLS — the
 * same denylist the catalogue strip uses, so the two enforcement points cannot
 * drift (the GroundGate test suite asserts this).
 */
class GroundGate
{
    /**
     * The read-only persona. AdviceFyn sets personaOverride to this value;
     * OnboardingChatDirector sets 'data_capture'.
     */
    public const READ_ONLY_PERSONA = 'advice';

    /**
     * True when the given surface (tool name) must be mechanically rejected
     * for the given persona. Only the read-only advice state denies writes.
     */
    public function blocksWriteSurface(string $surface, ?string $persona): bool
    {
        if ($persona !== self::READ_ONLY_PERSONA) {
            return false;
        }

        return in_array($surface, AdviceFyn::WRITE_TOOLS, true);
    }
}
