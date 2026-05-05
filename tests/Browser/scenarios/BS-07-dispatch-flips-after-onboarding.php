<?php

declare(strict_types=1);

/**
 * BS-07 — dispatch flips to AdviceFyn after onboarding_completed
 *
 * Sprint: 0
 * Invariants: INV-2.1.1 (dispatch path), INV-2.1.3 (dispatch condition)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-07
 * Screenshots: docs/sprint-0-verification/BS-07/
 *
 * Delivered session 88 (2026-04-26) — GREEN end-to-end via canonical Quick
 * start with Fyn flow. Fresh user Cassidy Greenwood (bs07d@example.com,
 * User #360, AiConversation #79). Walk: landing → /register?from=fyn →
 * MFA (code 820842) → /dashboard?openFyn=journey → "Follow a journey" →
 * "Building Foundations" → base_personal ("I was born on 5 May 1985 and
 * I'm single") → base_dependants ("No") → profile_review_family ("Looks
 * correct") → base_employment ("Full-time") → base_work ("I work for
 * Globex Corp as a Product Manager earning £75,000 a year") →
 * base_employment_more ("No, that's everything") → base_expenditure
 * ("I spend about £2,500 a month") → profile_review_expenditure ("Looks
 * correct") → asset_capture ("I want to save £15,000 for an emergency
 * fund by 2028") → add_more → "I'm done".
 *
 * Acceptance verified:
 *   - User #360 onboarding_completed=true, onboarding_fyn_step=null,
 *     onboarding_fyn_path=null after I'm done click.
 *   - "Building Foundations" terminal route is /goals (per session 86
 *     spec amendment: Fyn auto-routes to the journey's terminal module,
 *     not /dashboard). Cassidy landed on /goals with the Emergency
 *     Fund goal card visible and the projection chart marker plotted at
 *     age 43 / year 2028.
 *   - AdviceFyn dispatch confirmed by sending "What's my net worth?" via
 *     the post-onboarding chat input. Response: factual content message
 *     "Your current net worth is £0 — that's total assets (£0) minus
 *     total liabilities (£0)..." (ai_messages.id 192). DOM contains zero
 *     quick_replies bubbles for the post-onboarding turn (no Continue /
 *     Something else / Yes / No / Looks correct buttons rendered). Plain
 *     content layout — no onboarding-specific wide chat.
 *   - Backend dispatch logic at AiChatController::sendMessage:174-182
 *     resolves $inOnboarding=false (onboarding_completed=true ||
 *     onboarding_fyn_step=null), routing to $this->adviceFyn->handle(...)
 *     instead of $this->onboardingDirector->handleUserMessage(...).
 *
 * Bug-fix-in-loop (Sprint 0 plan §S0.16b — failures route through
 * dedicated bug-fix sub-tasks against the relevant Sprint 0 file):
 *   - Symptom uncovered while walking: dashboard "Goals & Life Events"
 *     chart was empty after onboarding completed even though the goal
 *     existed in the goals table and was returned by /api/goals and
 *     /api/goals/dashboard-overview. Root cause was twofold:
 *     (a) Goal::class had NO observer registered in
 *         EventServiceProvider, so GoalsProjectionService's 24-hour
 *         Cache::remember at goals_projection_{userId}_individual was
 *         never invalidated when a goal was created during onboarding.
 *         The chart had already mounted and fetched a stale cache that
 *         excluded the new goal.
 *     (b) The aiChat onboarding_complete SSE handler set pending
 *         navigation to /dashboard (or the journey's terminal module),
 *         but if the user was already on the target route, Vue Router
 *         silently no-ops — no remount fires and Vuex projectionData
 *         stays stale.
 *   - Fix:
 *     1. New app/Observers/GoalCacheObserver.php mirrors NetWorthCache
 *        pattern, calls GoalsProjectionService::clearCache() and
 *        CacheInvalidationService::invalidateForUser() on Goal create,
 *        update, and delete (incl. joint_owner_id for shared goals).
 *     2. Registered in app/Providers/EventServiceProvider.php
 *        $observers under Goal::class.
 *     3. resources/js/store/modules/aiChat.js onboarding_complete
 *        handler now also dispatches goals/fetchProjection,
 *        goals/fetchDashboardOverview, netWorth/refreshNetWorth, and
 *        auth/fetchUser so dashboard widgets reflect onboarding writes
 *        even when no route remount happens.
 *   - Re-verified: fresh walk above produced a populated Goals & Life
 *     Events chart on /dashboard with the Emergency Fund marker visible
 *     at age 43, AND the /goals projection chart with the same marker.
 *
 * Pass: SSE shape matches Advice Fyn expectations; onboarding_completed
 *       persisted; no onboarding events. Dashboard + /goals chart show
 *       the goal correctly post-onboarding.
 *
 * Session 95 redo (2026-04-26, S0.16c #5) — GREEN against the post-
 * `ffc9c3f` shared `AiChatPanelShell` body. The dispatch-flip contract
 * is verified using the session-95 BS-01 user (Laury Greenwood, User
 * #449), who is fully onboarded (onboarding_completed=true,
 * onboarding_fyn_step=null after the BS-01 walk's terminal click).
 *
 * Walk transcript:
 *   - Sign out previous walk user → /login → email bs01-s95@example.com
 *     + password TestPass123! → MFA 890739 (digit-by-digit) → /dashboard.
 *   - Vuex auth.user reflects the post-onboarding state:
 *     name='Laury Greenwood', onboarding_completed=true,
 *     onboarding_fyn_step=null. aiChat.isOnboardingActive=false. Fresh
 *     conv #126 created via the panel's startNewConversation path
 *     (NOT startOnboardingConversation — the isMidOnboarding check
 *     correctly resolved false).
 *   - Chat panel renders the normal "Hi, I'm Fyn / Ask me anything
 *     about your finances" empty state. Suggestions panel header
 *     visible (since isOnboardingActive=false suppresses the
 *     onboarding-specific hide). No /profile push, no wide-chat
 *     dashboard blur.
 *   - Type "What's my net worth?" + Enter via browser_type with
 *     submit:true. browser_click for ALL bubbles (none here, but the
 *     discipline holds throughout the session); NO browser_evaluate for
 *     interaction.
 *   - AdviceFyn answered: "Your current net worth is £0, calculated as
 *     total assets (£0) minus total liabilities (£0)..." (3-paragraph
 *     factual response with the standard "Want to add some assets or
 *     check a specific area..." nudge tail).
 *   - Vuex post-stream: 2 messages — user + assistant. ZERO
 *     quick_replies. ZERO action_bubbles. No onboarding_layout_change
 *     events. No /profile push.
 *   - Backend dispatch (AiChatController::sendMessage) resolved
 *     $inOnboarding=false because (onboarding_completed=true AND
 *     onboarding_fyn_step=null) → routed to $this->adviceFyn->handle(...)
 *     instead of $this->onboardingDirector->handleUserMessage(...). This
 *     is the INV-2.1.1 dispatch-path / INV-2.1.3 dispatch-condition
 *     contract.
 *
 * Cross-walk evidence — the same dispatch-flip behaviour was also
 * verified in the session-95 BS-04 walk: Devon's "Something else"
 * pause nulled onboarding_fyn_step, then a free-text "What's the ISA
 * allowance for 2025/26?" routed to AdviceFyn (which answered with a
 * substantive ISA breakdown). Same dispatch logic, different trigger
 * condition (paused vs. completed).
 *
 * Dashboard observations (Laury, post-onboarding, browser-verified):
 *   - 60% profile complete (Cash 38% / Finances 38% / Family 57% /
 *     Planning 0%).
 *   - Protection card shows "Total Coverage £300,000" — the Aviva life
 *     policy from the BS-01 asset_capture turn.
 *   - Allowances card correctly shows "Tax year 2026/27" (the current
 *     UK tax year as of 2026-04-26).
 *   - Recommended Actions includes "Increase life insurance cover by
 *     £1,157,657" — the protection gap analyser correctly computed a
 *     shortfall against Laury's £75k income / married-with-spouse profile.
 *
 * Screenshots `docs/sprint-0-verification/BS-07/`:
 *   - s95-01-dashboard-after-onboarding.png — full dashboard for a
 *     completed user, chat panel "Hi, I'm Fyn" empty state, no
 *     onboarding chrome.
 *   - s95-02-advice-fyn-net-worth.png — AdviceFyn answer rendered in
 *     the chat panel after the test message.
 *
 * Pest baseline: 529/1968 (no regressions — session-95 BS-04 fixes
 * are additive; INV-2.1.1/2.1.3 codepaths unchanged).
 */
it('BS-07 dispatch flips to AdviceFyn after onboarding_completed', function (): void {
    $this->markPendingInteractiveRun('BS-07');
});
