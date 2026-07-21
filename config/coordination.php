<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Composed module plans
    |--------------------------------------------------------------------------
    |
    | When enabled, RecommendationsAggregatorService routes the five non-tax
    | modules (retirement, savings, investment, protection, estate) through the
    | generalised ComposedModulePlanService instead of their raw per-agent
    | blocks, attaching catalogue metadata (claim_tier, sequence_position,
    | conflict_note) and resolving cross-strategy sequencing/conflicts the same
    | way tax already does. The raw per-agent path is retained as the rollback
    | branch (flag OFF) until the composed sources are proven on live data.
    |
    */
    'composed_module_plans' => env('COMPOSED_MODULE_PLANS', true),
];
