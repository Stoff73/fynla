<?php

declare(strict_types=1);

/*
 * Fyn Rubric-B eval harness configuration.
 *
 * Floors are starting baselines per fyn-rubrics.md §B "Starting baseline".
 * CSJ raises floors via the escalation path documented in §B; lowering
 * requires explicit sign-off in the commit message
 * ("EVAL_FLOOR_LOWER: ..." per the rubric).
 */
return [

    /*
     * Sanctum token TTL for eval-issued tokens. Defence in depth — the
     * routes that mint eval tokens already refuse in production at both
     * the controller and route levels, but a short TTL means an
     * accidentally-leaked dev token cannot outlive a single recording
     * session.
     */
    'token_ttl_minutes' => (int) env('FYN_EVAL_TOKEN_TTL_MINUTES', 15),

    /*
     * Per-focus entity-count recall baseline. Tunable per focus.
     */
    'recall_floor' => [
        'default' => 95,
        'protection' => 95,
        'savings' => 95,
        'retirement' => 95,
        'investment' => 95,
        'mortgage' => 95,
    ],

    /*
     * Per-tool field-level precision baseline. Tunable per (tool, field).
     */
    'precision_floor' => [
        'default' => 95,
    ],

    /*
     * Non-tunable failure modes. These never relax — any drift below the
     * stated value is a hard scenario fail, regardless of recall/precision.
     */
    'hard_fail_floors' => [
        'entity_validity' => 100,
        'value_accuracy' => 100,
        'cross_entity_consistency' => 100,
        'fabrication_rate' => 0,
    ],

    /*
     * When false, EvalScenarioCountTest skips with a "gate inactive" note.
     * Flipped to true at S1.10 verification rollup (once Sprint 1 scenarios
     * are seeded). Per plan/11-sprint-1-plan.md S1.1: "directory counts are
     * 0 vs placeholder 0 — once minima enforced in Task 1.2 onwards, the
     * gate becomes active".
     */
    'enforce_minima' => env('FYN_EVAL_ENFORCE_MINIMA', false),

    /*
     * Minimum scenario count per category for the Mode-1 harness to
     * declare itself coverage-complete. Enforced by
     * tests/Architecture/EvalScenarioCountTest when `enforce_minima` is
     * true. Numbers come from fyn-rubrics.md §B coverage targets table
     * (75 total).
     */
    'scenario_minima' => [
        '01-query-types' => 22,
        '02-preview-personas' => 6,
        '03-multi-entity' => 10,
        '04-handoffs' => 5,
        '05-cancel-timeout' => 3,
        '06-prompt-injection' => 10,
        '07-regulatory' => 5,
        '08-provider-parity' => 4,
        '09-canonical-behaviour' => 10,
    ],

];
