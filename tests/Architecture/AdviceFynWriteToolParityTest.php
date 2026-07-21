<?php

declare(strict_types=1);

/**
 * AdviceFyn write-tool parity.
 *
 * The Two-Fyn contract (CLAUDE.md, canonical 00-canonical.md) says
 * AdviceFyn is read-only — it exposes ZERO `create_*` / `update_*` /
 * `delete_*` / `capture_*` / `set_expenditure` tools to the LLM. Every
 * persistent record-creation tool must appear in `AdviceFyn::WRITE_TOOLS`
 * so that the dispatch filter strips it from the catalogue before the
 * advice turn fires.
 *
 * This arch test enforces the contract: it parses the schema array
 * produced by `AiToolDefinitions::all()`, picks every tool whose name
 * starts with a known write-verb prefix, and asserts each one is on
 * `WRITE_TOOLS`. Catches the bug where someone adds a new
 * `create_xxx` tool in `AiToolDefinitions` and forgets to add it to
 * `AdviceFyn::WRITE_TOOLS` — Pest is the only place this gets caught
 * before the next branch drift.
 *
 * Added during the 2026-05-23 tech-debt audit (B35).
 */

use App\Services\AI\AdviceFyn;
use App\Services\AI\AiToolDefinitions;
use Tests\TestCase;

uses(TestCase::class);

it('every write-prefix tool in AiToolDefinitions is on AdviceFyn::WRITE_TOOLS', function (): void {
    // The canonical write-verb prefixes that imply persistent data change.
    // Mirrors the contract language in CLAUDE.md "Fyn AI — Two-Fyn architecture".
    $writePrefixes = ['create_', 'update_', 'delete_', 'capture_', 'set_'];

    // Pull the WRITE_TOOLS allowlist out of AdviceFyn via reflection (it's
    // a `private const` for encapsulation but the arch contract is public).
    $reflection = new ReflectionClass(AdviceFyn::class);
    expect($reflection->hasConstant('WRITE_TOOLS'))
        ->toBeTrue('AdviceFyn::WRITE_TOOLS constant has been renamed or removed — update this arch test too.');

    $writeTools = $reflection->getConstant('WRITE_TOOLS');
    expect($writeTools)->toBeArray()->not->toBeEmpty();

    // Get every tool the LLM might see (non-preview mode includes the full
    // write-capable catalogue). AdviceFyn strips WRITE_TOOLS from this set
    // before invoking the LLM — that's the contract we're auditing.
    $definitions = app(AiToolDefinitions::class);
    $allTools = $definitions->getTools(false);
    expect($allTools)->toBeArray()->not->toBeEmpty();

    $missing = [];

    foreach ($allTools as $tool) {
        // The schema shape from AiToolDefinitions is either ['name' => '...']
        // (Anthropic format) or ['function' => ['name' => '...']] (xAI/OpenAI format).
        $name = $tool['name']
            ?? $tool['function']['name']
            ?? null;

        if (! is_string($name) || $name === '') {
            continue;
        }

        // Skip non-write-prefix tools (search, read, navigate, etc.)
        $hasWritePrefix = false;
        foreach ($writePrefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $hasWritePrefix = true;
                break;
            }
        }

        if (! $hasWritePrefix) {
            continue;
        }

        if (! in_array($name, $writeTools, true)) {
            $missing[] = $name;
        }
    }

    expect($missing)->toBeEmpty(
        'These write-prefix tools are exposed by AiToolDefinitions but missing from '.
        'AdviceFyn::WRITE_TOOLS — advice mode would leak them to the LLM and break '.
        "the read-only contract: \n  - ".implode("\n  - ", $missing)."\n".
        'Either add them to the WRITE_TOOLS constant in app/Services/AI/AdviceFyn.php '.
        'OR justify why they belong on the advice-mode catalogue.'
    );
});
