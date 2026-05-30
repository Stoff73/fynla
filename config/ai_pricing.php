<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AI model price table (CoALA Phase 5 PR 1 — FR-M13)
|--------------------------------------------------------------------------
|
| Per-model rates in GBP per 1,000 tokens. `AiCostCalculator` reads this
| table to compute the `gbp_cost` persisted on every LLM turn alongside
| the prompt-cache hit/miss token counts (FR-M11/M12).
|
|   gbp_cost = tokens × rate_per_1k / 1000
|
| Components:
|   input        — regular (uncached) prompt tokens
|   output       — completion tokens
|   cache_write  — prompt-cache creation tokens (Anthropic write-through)
|   cache_read   — prompt-cache hit tokens (served from cache)
|
| PROVENANCE: rates below are derived from each provider's published USD
| list price (per 1M tokens) at the stated FX, then expressed as GBP per
| 1k. They are config-driven precisely so they can be corrected without a
| code change — VERIFY against the live provider pricing page before
| relying on these figures for FCA / board cost reporting. Anthropic's
| standard cache multipliers (write = 1.25× input, read = 0.10× input)
| are applied where a published cache rate is not separately quoted.
|
| FX used to derive the seeded GBP figures: 1 USD ≈ 0.79 GBP.
|
*/

return [

    // Marks the unit so downstream readers never guess.
    'currency' => 'GBP',
    'per_tokens' => 1000,

    // FX assumption used to derive the seeded rates from USD list prices.
    // Operators updating rates may keep working in GBP/1k directly.
    'usd_to_gbp' => env('AI_PRICING_USD_TO_GBP', 0.79),

    'models' => [

        // ── Anthropic ──────────────────────────────────────────────────
        // Claude Haiku 4.5 — list ≈ $1 in / $5 out per 1M.
        'claude-haiku-4-5-20251001' => [
            'input' => 0.00079,
            'output' => 0.00395,
            'cache_write' => 0.0009875,
            'cache_read' => 0.000079,
        ],
        // Claude Sonnet 4.6 — list ≈ $3 in / $15 out per 1M.
        'claude-sonnet-4-6-20260320' => [
            'input' => 0.00237,
            'output' => 0.01185,
            'cache_write' => 0.0029625,
            'cache_read' => 0.000237,
        ],
        // Claude Opus 4.x — list ≈ $15 in / $75 out per 1M.
        'claude-opus-4' => [
            'input' => 0.01185,
            'output' => 0.05925,
            'cache_write' => 0.0148125,
            'cache_read' => 0.001185,
        ],

        // ── xAI ────────────────────────────────────────────────────────
        // Grok 4.x — placeholder list ≈ $3 in / $15 out per 1M. xAI does
        // not bill a separate cache-creation tier; only cached-read hits
        // are discounted, so cache_write mirrors input and cache_read is
        // the discounted hit rate. VERIFY against xAI's pricing page.
        'grok-4.3' => [
            'input' => 0.00237,
            'output' => 0.01185,
            'cache_write' => 0.00237,
            'cache_read' => 0.000593,
        ],
    ],

    // Fallback when a model has no explicit entry. The calculator flags
    // any turn priced via this fallback (`priced => false`) so unknown
    // models surface rather than silently costing £0.
    'default' => [
        'input' => 0.003,
        'output' => 0.006,
        'cache_write' => 0.00375,
        'cache_read' => 0.0003,
    ],
];
