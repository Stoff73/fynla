<?php

declare(strict_types=1);

use App\Services\AI\AdvicePromptBuilder;
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

/*
 * Fix plan T5 (F20, P5, Q5, Q10): the TOP-PRIORITY handoff rule had no
 * exception for a question, so the model handed questions that quote figures
 * to capture ("these figures are not in the stored records and need capturing
 * before analysis", csjones T2 walk #22766) and blank add forms opened.
 */
it('applies the capture handoff to statements only (fix plan T5)', function (): void {
    $guidance = FynSystemPrompt::handoffGuidance();

    expect(FynSystemPrompt::text())->toContain($guidance)
        ->and($guidance)->toContain('A question is never a write')
        ->and($guidance)->toContain('`what_if_scenario`')
        ->and($guidance)->not->toContain('{{');
});

it('gives the legacy prompt the same handoff rule, from one place (Rule 20)', function (): void {
    $method = new ReflectionMethod(AdvicePromptBuilder::class, 'getHandoffGuidance');
    $method->setAccessible(true);

    expect($method->invoke(app(AdvicePromptBuilder::class)))->toBe(FynSystemPrompt::handoffGuidance());
});

it('treats asking which figure is on file as a question, not a correction (fix plan T5, Q10)', function (): void {
    expect(FynSystemPrompt::text())->toContain('Asking which figure is on file is a question');
});

// Walk fix plan T13 (Q13): a figure from earlier in the conversation is
// quoted from what the user wrote, or said to be out of view; never invented.
it('carries the recall rule', function (): void {
    expect(FynSystemPrompt::text())
        ->toContain('call search_conversation_index with the words to look for and quote what they wrote')
        ->toContain('Never supply a figure the user did not give.');
});

// Walk fix plan T14 (M5): Fyn names the source of a <knowledge> fact it uses.
it('tells Fyn to name the source of a knowledge fact', function (): void {
    expect(FynSystemPrompt::text())->toContain('When you use a fact from <knowledge>, name its source in plain words');
});
