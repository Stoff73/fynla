<?php

declare(strict_types=1);

namespace App\Services\AI\Actions;

use App\Services\AI\AdviceFyn;
use App\Services\AI\AiToolDefinitions;

/**
 * Maps an emitted provider tool call into a typed {@see Action}
 * (CoALA Phase 5 item 3 — plan §"Action space").
 *
 * In the CoALA framework `retrieve` means recall from the agent's OWN memory
 * (episodic / semantic). Every tool in {@see AiToolDefinitions} either mutates
 * the user's records or queries the app's engines for external facts — both are
 * external action, so they all map to a `ground` surface. `retrieve` / `reason`
 * / `learn` have no catalogue tool yet; they are planner-emitted (item 5).
 *
 * The write-vs-read SAFETY decision is NOT made here — it lives in
 * {@see SurfaceAllowlist}, sourced from {@see AdviceFyn::WRITE_TOOLS} so there is
 * a single denylist. This mapper only types the call and answers two questions
 * the allowlist needs: is this a real catalogue surface, and is it a write?
 */
final class ToolActionMapper
{
    /** @var list<string>|null Lazily-resolved catalogue surface names. */
    private ?array $knownSurfaces = null;

    public function __construct(
        private readonly AiToolDefinitions $toolDefinitions,
    ) {}

    /**
     * Wrap an emitted tool call in a typed ground action.
     *
     * @param  array<string, mixed>  $args
     */
    public function toAction(string $tool, array $args = []): Action
    {
        return Action::ground($tool, $args);
    }

    /**
     * True when the surface mutates persistent records — the WRITE_TOOLS
     * denylist is the single source (shared with the catalogue strip and the
     * GroundGate).
     */
    public function isWriteSurface(string $tool): bool
    {
        return in_array($tool, AdviceFyn::WRITE_TOOLS, true);
    }

    /**
     * True when the surface is a real tool the LLM can emit. Derived at runtime
     * from every {@see AiToolDefinitions} surface the model is ever offered —
     * the main catalogue plus the onboarding grouped-extract tools and the
     * advice→capture handoff tools — so it can never drift from the real code.
     */
    public function isKnownSurface(string $tool): bool
    {
        return in_array($tool, $this->knownSurfaces(), true);
    }

    /**
     * @return list<string>
     */
    private function knownSurfaces(): array
    {
        if ($this->knownSurfaces === null) {
            $names = array_merge(
                $this->toolDefinitions->getTools(isPreviewMode: false),
                $this->toolDefinitions->onboardingExtractionTools(),
                $this->toolDefinitions->handoffTools(),
            );

            $this->knownSurfaces = array_values(array_unique(array_map(
                static fn (array $tool): string => $tool['name'],
                $names,
            )));
        }

        return $this->knownSurfaces;
    }
}
