<?php

declare(strict_types=1);

use App\Services\AI\AiToolDefinitions;
use App\Services\AI\XaiToolDefinitions;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Rule 9 — no acronyms in user-facing text.
 *
 * Rule 9 lived in CLAUDE.md and was restated as an instruction inside
 * FynSystemPrompt, and nothing checked it. On 2026-08-17 a user answered "Sip"
 * and Fyn replied "Recorded — Aviva workplace pension", having first asked for
 * "the scheme type (workplace, SIPP, or personal)" — an abbreviation the system
 * prompt explicitly forbids.
 *
 * The cause was a second, ungoverned vocabulary source: the tool-schema corpus
 * carried 38 banned acronyms across 16 files. The model treats those
 * descriptions as the authoritative domain language, so schema prose beats a
 * prompt rule. Tool descriptions ARE Fyn's vocabulary — an abbreviation here
 * reaches the user.
 *
 * Catalogue-name parity between providers is covered by ToolCatalogueParityTest.
 */

/** Exactly as FynSystemPrompt lists them. "ISA" is the one permitted abbreviation. */
const RULE9_BANNED = [
    'IHT', 'DC', 'DB', 'AA', 'MPAA', 'AEA', 'CGT', 'BPR', 'BADR',
    'NRB', 'RNRB', 'SIPP', 'GIA', 'LPA', 'PET', 'NI',
];

/**
 * Substrings where an apparent acronym is not one. Every entry is a hole in the
 * rule, so justify additions.
 */
const RULE9_NOT_ACRONYMS = [
    'SW1A 1AA',   // UK postcode example in create_property
];

dataset('fyn tool catalogues', [
    'xAI (live provider)' => [XaiToolDefinitions::class],
    'Anthropic' => [AiToolDefinitions::class],
]);

it('exposes no banned acronym in any tool schema text', function (string $catalogue): void {
    $tools = app($catalogue)->getTools(false);

    expect($tools)->not->toBeEmpty(
        'The tool catalogue is empty — the procedural corpus failed to load. '
        .'One malformed frontmatter line in fyn-memory/procedural silently drops every procedure.'
    );

    $violations = [];

    foreach ($tools as $tool) {
        $fn = $tool['function'] ?? $tool;
        $name = (string) ($fn['name'] ?? 'unknown');

        $texts = ['description' => (string) ($fn['description'] ?? '')];
        foreach (($fn['parameters']['properties'] ?? []) as $property => $definition) {
            $texts['param:'.$property] = (string) ($definition['description'] ?? '');
        }

        foreach ($texts as $where => $text) {
            $haystack = str_replace(RULE9_NOT_ACRONYMS, '', $text);

            foreach (RULE9_BANNED as $acronym) {
                if (preg_match('/(?<![A-Za-z])'.preg_quote($acronym, '/').'(?![A-Za-z])/', $haystack) === 1) {
                    $violations[] = sprintf('%s [%s] uses "%s"', $name, $where, $acronym);
                }
            }
        }
    }

    expect($violations)->toBe([], sprintf(
        "Rule 9 violated in %d place(s) — spell these out in full:\n  %s",
        count($violations),
        implode("\n  ", $violations)
    ));
})->with('fyn tool catalogues');

/**
 * SPEC-crud-handler-contract C7 — the same rule, one layer further out.
 *
 * A handler's `message` is printed to the user verbatim (that is what made the
 * C6 model-instruction leak visible), so it is Fyn's vocabulary just as much as
 * a tool description is. Two acronyms reached users this way — "Recorded your
 * {type} LPA." and "No LPA with that ID found" — and the schema sweep above
 * could not see them.
 */
it('exposes no banned acronym in a handler message', function (): void {
    // Not app_path() — this file is read, not executed, so the source is enough.
    $source = file_get_contents(__DIR__.'/../../app/Agents/CoordinatingAgent.php');

    preg_match_all('/\'message\' => (?:\'([^\']*)\'|"([^"]*)")/', (string) $source, $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty('No handler messages found — the regex has drifted from the source.');

    $violations = [];
    foreach ($matches as $match) {
        $text = $match[1] !== '' ? $match[1] : ($match[2] ?? '');
        $haystack = str_replace(RULE9_NOT_ACRONYMS, '', $text);

        foreach (RULE9_BANNED as $acronym) {
            if (preg_match('/(?<![A-Za-z])'.preg_quote($acronym, '/').'(?![A-Za-z])/', $haystack) === 1) {
                $violations[] = sprintf('"%s" uses "%s"', mb_strimwidth($text, 0, 80, '…'), $acronym);
            }
        }
    }

    expect($violations)->toBe([], sprintf(
        "Rule 9 violated in %d handler message(s) — spell these out in full:\n  %s",
        count($violations),
        implode("\n  ", $violations)
    ));
});
