<?php

declare(strict_types=1);

return [
    /*
     * Fyn prompt architecture.
     *
     * 'unified' — single static FynSystemPrompt + dynamic
     *             FynContextAssembler. This is THE Fyn prompt.
     * 'legacy'  — the pre-2026-05-16 12-layer AdvicePromptBuilder /
     *             4-layer OnboardingPromptBuilder, retained only as an
     *             emergency rollback path (set FYN_PROMPT_ARCH=legacy).
     *
     * Defaults to unified. Fail-safe: any unrecognised value is legacy.
     */
    'prompt_architecture' => env('FYN_PROMPT_ARCH', 'unified'),
];
