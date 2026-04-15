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
];
