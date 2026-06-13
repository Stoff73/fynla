<?php

declare(strict_types=1);

use App\Services\AI\AdviceFyn;
use App\Services\AI\AiToolDefinitions;
use App\Services\AI\Ground\GroundGate;
use App\Services\AI\HandoffContract;

/**
 * The COMPLETE set of dispatchable tool names. getTools() is the main advice
 * catalogue (43 tools); the onboarding capture tools and the handoff tools are
 * exposed via separate methods. The gate protects against any of these reaching
 * advice dispatch, so the classification tests must see the full universe.
 *
 * @return list<string>
 */
function fullToolCatalogue(): array
{
    $defs = new AiToolDefinitions;

    return collect([
        ...$defs->getTools(),
        ...$defs->onboardingExtractionTools(),
        ...$defs->handoffTools(),
    ])
        ->map(fn ($t) => $t['name'] ?? ($t['function']['name'] ?? null))
        ->filter()
        ->unique()
        ->values()
        ->all();
}

/**
 * CoALA Phase 5 PR 2 — the `ground` surface gate (standalone, pre-planner).
 *
 * The mechanical write-safety boundary: in the read-only 'advice' state, no
 * write surface may execute. This is the second line behind AdviceFyn's
 * catalogue strip (array_diff) — even if a WRITE_TOOLS name reaches dispatch
 * in advice mode (hallucinated tool id, strip-logic regression, handoff edge
 * case), the gate rejects it. "Mechanical, not promised."
 *
 * The matrix below IS the plan's mandated "paired test per tool": adding a
 * write-shaped tool to the catalogue without classifying it into WRITE_TOOLS
 * fails the enforcement test until the omission is deliberately resolved.
 */
describe('GroundGate (FR — ground surface gate)', function () {
    it('blocks every WRITE_TOOLS surface in advice (read-only) mode', function () {
        $gate = new GroundGate;

        foreach (AdviceFyn::WRITE_TOOLS as $writeTool) {
            expect($gate->blocksWriteSurface($writeTool, 'advice'))
                ->toBeTrue("write surface '{$writeTool}' must be blocked in advice mode");
        }
    });

    it('allows every write surface in data_capture (onboarding) mode', function () {
        $gate = new GroundGate;

        foreach (AdviceFyn::WRITE_TOOLS as $writeTool) {
            expect($gate->blocksWriteSurface($writeTool, 'data_capture'))
                ->toBeFalse("write surface '{$writeTool}' must be allowed in data_capture mode");
        }
    });

    it('does not block writes when persona is null (legacy non-advice paths)', function () {
        // This additive PR protects ONLY the read-only advice state; it must
        // not disturb the direct OnboardingChatDirector executeTool paths that
        // carry no persona. Broader fail-closed allowlisting lands with the
        // session_mode field in the Action-enum PR.
        $gate = new GroundGate;

        foreach (AdviceFyn::WRITE_TOOLS as $writeTool) {
            expect($gate->blocksWriteSurface($writeTool, null))->toBeFalse();
        }
    });

    it('never blocks the advice→capture handoff tools', function () {
        $gate = new GroundGate;

        // Advice mode KEEPS these (they survive the array_diff strip) — the
        // write itself happens later via OnboardingChatDirector, not here.
        expect($gate->blocksWriteSurface(HandoffContract::DELEGATE_TO_CAPTURE, 'advice'))->toBeFalse()
            ->and($gate->blocksWriteSurface(HandoffContract::CAPTURE_COMPLETE, 'advice'))->toBeFalse();
    });

    it('never blocks a read tool in advice mode', function () {
        $gate = new GroundGate;

        foreach (['list_records', 'list_goals', 'get_module_analysis', 'get_recommendations', 'get_tax_information', 'generate_financial_plan', 'search_conversation_index'] as $readTool) {
            expect($gate->blocksWriteSurface($readTool, 'advice'))
                ->toBeFalse("read tool '{$readTool}' must never be blocked");
        }
    });

    it('classifies every catalogue tool — advice-blocked IFF in WRITE_TOOLS', function () {
        $gate = new GroundGate;
        $catalogue = fullToolCatalogue();

        foreach ($catalogue as $name) {
            $expectedBlocked = in_array($name, AdviceFyn::WRITE_TOOLS, true);
            expect($gate->blocksWriteSurface($name, 'advice'))
                ->toBe($expectedBlocked, "advice-mode gating mismatch for '{$name}'");
        }
    });

    it('has no orphan entries in WRITE_TOOLS — every denied name exists in the catalogue', function () {
        // Catches a tool renamed in AiToolDefinitions but not in WRITE_TOOLS:
        // the denylist would silently stop protecting the renamed write tool.
        $catalogue = fullToolCatalogue();

        $orphans = array_values(array_diff(AdviceFyn::WRITE_TOOLS, $catalogue));

        expect($orphans)->toBe([], 'WRITE_TOOLS references tools absent from the catalogue: '.implode(', ', $orphans));
    });

    it('enforces that every write-shaped catalogue tool is in WRITE_TOOLS (paired-test guard)', function () {
        // The core regression guard: add create_*/update_*/delete_*/capture_*/
        // set_* to the catalogue and forget WRITE_TOOLS → this fails until the
        // new tool is deliberately classified, closing the unguarded-write hole.
        $catalogue = fullToolCatalogue();

        $handoffAllowed = [HandoffContract::DELEGATE_TO_CAPTURE, HandoffContract::CAPTURE_COMPLETE];

        $writeShaped = array_filter($catalogue, function (string $n) use ($handoffAllowed) {
            if (in_array($n, $handoffAllowed, true)) {
                return false; // handoff orchestration, not a direct write
            }

            return str_starts_with($n, 'create_')
                || str_starts_with($n, 'update_')
                || str_starts_with($n, 'delete_')
                || str_starts_with($n, 'capture_')
                || str_starts_with($n, 'set_');
        });

        $unguarded = array_values(array_diff($writeShaped, AdviceFyn::WRITE_TOOLS));

        expect($unguarded)->toBe([], 'write-shaped tools missing from WRITE_TOOLS: '.implode(', ', $unguarded));
    });
});
