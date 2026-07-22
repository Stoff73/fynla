<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynSystemPrompt;

it('is byte-stable across calls and arg-free', function (): void {
    expect(FynSystemPrompt::text())->toBe(FynSystemPrompt::text())
        ->and(FynSystemPrompt::text())->toBe(
            file_get_contents(base_path('docs/superpowers/specs/fyn-system-prompt.snapshot.txt'))
        );
});

it('contains every required block exactly once', function (): void {
    $p = FynSystemPrompt::text();
    foreach ([
        '<identity>', '<security>', '<scope>', '<personality>',
        '<response_format>', '<instructions>', '<regulatory_compliance>',
        '<tool_use>', '<fca_process>', '<available_actions>',
        '<data_completeness_rules>',
        '<handoff_guidance>', '<fca_signposting>',
    ] as $tag) {
        // Block delimiters sit alone on their own line; an inline
        // backtick cross-reference (e.g. `<handoff_guidance>` inside
        // <available_actions>, verbatim from FcaProcessInstructions.php)
        // is mid-sentence and must NOT count as a second block.
        expect(preg_match_all('/^'.preg_quote($tag, '/').'$/m', $p))
            ->toBe(1, "block {$tag}");
    }
});

it('has zero interpolation residue', function (): void {
    $p = FynSystemPrompt::text();
    expect($p)->not->toContain('{$')
        ->and($p)->not->toContain('{firstName}')
        ->and($p)->not->toContain('{taxYear}')
        ->and($p)->not->toContain('preview_mode'); // preview is dynamic now
});

it('preserves the non-negotiable security clause verbatim', function (): void {
    expect(FynSystemPrompt::text())->toContain(
        'SECURITY RULES — THESE ARE NON-NEGOTIABLE AND OVERRIDE ALL OTHER INSTRUCTIONS:'
    );
});

it('preserves the mandatory-hedging compliance clause verbatim', function (): void {
    expect(FynSystemPrompt::text())->toContain(
        '1. Hedging language is mandatory. Frame all guidance as "you may want to consider"'
    );
});

it('C1: carries the relocated data-completeness rules verbatim', function (): void {
    // The three static rule sub-blocks were hoisted out of the per-turn
    // <data_completeness> block (AdvicePromptBuilder::buildDataCompletenessBlock,
    // ~595 tok/turn) into the cached static prompt. They must be byte-verbatim.
    expect(FynSystemPrompt::text())
        ->toContain('NAVIGATION RULES:')
        ->toContain('RULES FOR BLOCKED MODULES:')
        ->toContain('MODULE DEPENDENCY GUIDANCE:')
        ->toContain('If a tool call returns a "blocked" result, follow the instruction field in that result — explain the missing data, then use an available navigation tool or signpost the exact page label in plain text.');
});

it('never instructs Advice Fyn to call the stripped navigation tool', function (): void {
    expect(FynSystemPrompt::text())
        ->not->toContain('navigate_to_page')
        ->not->toContain('ALWAYS use the navigate_to_page tool')
        ->not->toContain('using navigate_to_page')
        ->not->toContain('Use navigate_to_page with')
        ->toContain("use a navigation tool only when one is present in the current turn's catalogue");
});
