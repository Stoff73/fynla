<?php

declare(strict_types=1);

/**
 * Onboarding configuration — feature flags for the Fyn-driven
 * onboarding flow.
 *
 * Plan: April/April15Updates/fynOnboardFix.md §14 (kill switch)
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Fyn-driven onboarding flow
    |--------------------------------------------------------------------------
    |
    | Master switch for the OnboardingChatDirector / state-machine flow
    | that drives new users through base KYC and focus asset capture via
    | Fyn's chat interface. When false:
    |
    |   - POST /api/ai-chat/onboarding/start returns 503
    |   - POST /api/ai-chat/conversations/{id}/messages does NOT delegate
    |     to the director even for users with onboarding_fyn_step set —
    |     falls through to CoordinatingAgent::chat()
    |   - The landing page Quick-Start CTA still routes to /register?from=fyn,
    |     but the onboarding chat never triggers; the user lands on the
    |     dashboard like any other registered user
    |
    | Hard-disable: set ONBOARDING_FYN_FLOW_ENABLED=false in .env and
    | `php artisan config:clear`. Zero file changes needed. Existing users
    | and in-flight sessions revert to the standard Fyn chat.
    |
    */
    'fyn_flow_enabled' => env('ONBOARDING_FYN_FLOW_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Entry-source journey map (INV-2.2.5)
    |--------------------------------------------------------------------------
    |
    | When POST /api/ai-chat/onboarding/start arrives with a `from` value,
    | the controller looks the value up in this map and pre-selects the
    | matching life-stage journey, skipping STATE_PATH_CHOICE +
    | STATE_JOURNEY_SELECTION and landing the user at STATE_BASE_PERSONAL.
    |
    | An unrecognised or missing `from` falls through to STATE_PATH_CHOICE
    | (the default first turn). Adding a new entry source requires only a
    | new key/value pair here — no controller change. Map values must be
    | one of the journey ids declared in
    | OnboardingStateMachine::states()[STATE_JOURNEY_SELECTION].bubbles.
    |
    */
    'journey_map' => [
        'budgeting' => 'budgeting',
        'goals' => 'goals',
        'protection' => 'protection',
        'retirement' => 'retirement',
    ],
];
