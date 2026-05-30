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

    /*
     * Concurrent-turn queue (CoALA Phase 5 item 6 — FR-M7 / FR-S3).
     *
     * When a user sends a turn while another is still streaming, it is queued.
     * `depth_cap` bounds how many turns may wait (further sends are rejected);
     * `ttl_minutes` is how long a queued turn lives before it is swept to
     * `expired` (e.g. the user closed the tab mid-queue).
     */
    'queue' => [
        'depth_cap' => (int) env('FYN_QUEUE_DEPTH_CAP', 3),
        'ttl_minutes' => (int) env('FYN_QUEUE_TTL_MINUTES', 10),
    ],
];
