<?php

declare(strict_types=1);

return [
    /*
     * Fyn prompt architecture.
     *
     * 'legacy'  — the 12-layer AdvicePromptBuilder / 4-layer
     *             OnboardingPromptBuilder (pre-2026-05-16).
     * 'unified' — single static FynSystemPrompt + dynamic
     *             FynContextAssembler.
     *
     * Defaults to legacy until the unified path proves >= legacy on
     * the Fyn eval suite. Fail-safe: any unrecognised value is legacy.
     */
    'prompt_architecture' => env('FYN_PROMPT_ARCH', 'legacy'),
];
