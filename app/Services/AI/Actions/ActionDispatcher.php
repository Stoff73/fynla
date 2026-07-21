<?php

declare(strict_types=1);

namespace App\Services\AI\Actions;

use App\Services\AI\Ground\GroundGate;

/**
 * The decision seam for Fyn's tool-use loop (CoALA Phase 5 item 3 —
 * plan §"Action space" / module boundary `app/Services/AI/Actions`).
 *
 * Wraps each emitted provider tool call in a typed {@see Action} and checks the
 * surface against the unified {@see SurfaceAllowlist}. It replaces the bare
 * {@see GroundGate} check at the HasAiChat dispatch
 * point: same write-safety boundary (parity-tested), but every call is now a
 * typed action rather than an untyped tool name.
 *
 * It deliberately does NOT execute the tool. Execution stays inside the
 * HasAiChat tool-use loop until the FynLoop service consumes this dispatcher in
 * item 4 — item 3 is "the existing tool-use loop wraps in typed actions, no LLM
 * planner yet" (PRD §8 internal sequencing, step 3).
 */
final class ActionDispatcher
{
    public function __construct(
        private readonly ToolActionMapper $mapper,
        private readonly SurfaceAllowlist $allowlist,
    ) {}

    /**
     * Type an emitted tool call. Every catalogue tool is an external `ground`
     * surface (writes and engine reads alike); `retrieve` / `reason` / `learn`
     * are planner-emitted and have no tool yet.
     *
     * @param  array<string, mixed>  $args
     */
    public function classify(string $tool, array $args = []): Action
    {
        return $this->mapper->toAction($tool, $args);
    }

    /**
     * True when the surface may execute in the given session mode. The single
     * gate decision the HasAiChat loop consumes.
     */
    public function isSurfaceAllowed(string $surface, ?string $sessionMode): bool
    {
        return $this->allowlist->allows($sessionMode, $surface);
    }
}
