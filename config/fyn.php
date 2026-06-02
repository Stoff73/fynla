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

    /*
     * Planning cycle cap per session mode (CoALA Phase 5 — FR-M6 / FR-S2).
     * Bounds planner→action iterations per turn; FynLoop reads the cap for the
     * turn's session_mode, falling back to `default`.
     */
    'cycle_cap' => [
        'default' => (int) env('FYN_CYCLE_CAP', 8),
        'advice' => (int) env('FYN_CYCLE_CAP_ADVICE', 8),
        'onboarding' => (int) env('FYN_CYCLE_CAP_ONBOARDING', 8),
    ],

    /*
     * CoALA memory stores (markdown). Procedural = CSJ-authored procedures;
     * episodic = Fyn-agent-written episodes governed by the rubric; semantic =
     * Phase 1 (future). See fyn-memory/README.md. The loop's retrieve/learn
     * actions read/write these paths once the adapters are wired.
     */
    'memory' => [
        'root' => base_path('fyn-memory'),
        'procedural_path' => base_path('fyn-memory/procedural'),
        'episodic_rubric' => base_path('fyn-memory/episodic/RUBRIC.md'),
        'episodic_template' => base_path('fyn-memory/episodic/_TEMPLATE.md'),
        'episodic_path' => base_path('fyn-memory/episodic/episodes'),
        'semantic_path' => base_path('fyn-memory/semantic'),
        'semantic_index' => storage_path('app/memory/semantic/index.json'),
        'semantic_top_k' => (int) env('FYN_SEMANTIC_TOP_K', 4),
        'pointers_path' => base_path('fyn-memory/procedural/pointers'),
    ],
];
