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
        '<handoff_guidance>', '<billing_guidance>', '<fca_signposting>',
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
