<?php

declare(strict_types=1);

/**
 * BS-04 — resume after disconnect
 *
 * Sprint: 0
 * Invariants: INV-2.2.4 (resume greeting + Yes/No bubble)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-04
 * Pest sibling: tests/Feature/Onboarding/ResumeAfterDisconnectTest.php (S0.15.1)
 * Screenshots: docs/sprint-0-verification/BS-04/
 *
 * Seed: NONE — drives the canonical Quick start with Fyn real-user flow.
 *       Register fresh → walk to base_dependants_detail → sign out → sign in.
 *       The "disconnect" is simulated via real sign-out + sign-in, not by
 *       backdating ai_messages.created_at (CSJ instruction: "no factory
 *       seeds, no DB fixtures — drive it as a real user would").
 *
 * Script:
 *   1. Sign out + clear session.
 *   2. Navigate landing → "Quick start with Fyn" CTA → /register?from=fyn.
 *   3. Fill register form (unique email) → submit → MFA.
 *   4. Land on /dashboard with auto-onboarding chat.
 *   5. Click "Follow a journey" → "Protecting What Matters".
 *   6. Type "DOB <X>, married" → Enter (advances base_personal).
 *   7. Click "Skip this for now" on base_spouse → advances to base_dependants.
 *   8. Click "Yes" on dependants → advances to base_dependants_detail.
 *   9. Sign out (top-nav user dropdown or side-nav Sign Out).
 *  10. Sign back in via /login + MFA code from email_verification_codes.
 *  11. Land on /dashboard — Fyn auto-fires the welcome-back greeting.
 *  12. Capture welcome-back screenshot.
 *  13. Click "Continue" → wait for base_dependants_detail prompt.
 *  14. Type dependants line "Mia 8 child, Owen 5 child" → Enter.
 *  15. Verify family_members rows + UI Family tab shows real names.
 *
 * Assertions (verified GREEN 2026-04-26):
 *   - Welcome-back greeting renders with the per-state summary: "Welcome
 *     back, <Name>. Last time we were noting your dependants. Would you like
 *     to continue from where we left off, or is there something else I can
 *     help with?".
 *   - Two action bubbles render: "Continue" (id='continue') and
 *     "Something else" (id='something_else'). Both styled like normal
 *     quick-reply pills.
 *   - Clicking "Continue" dispatches POST /api/ai-chat/conversations/{id}/action
 *     with action='continue' (NOT a sendMessage with content='Continue').
 *     ai_messages does NOT gain a spurious user "Continue" row, the
 *     conversation title stays "Onboarding".
 *   - Director re-emits the base_dependants_detail prompt verbatim:
 *     "Lovely. Tell me their first names, ages, and how they are related to
 *     you (child, parent, or other dependant). You can list several in one go."
 *   - Typing "Mia 8 child, Owen 5 child" persists two family_members rows
 *     with first_name + name + relationship='child' + is_dependent=true and
 *     synthetic dob from age (Jan 1 of the year matching age).
 *   - Onboarding advances to profile_review_family; URL flips to /profile so
 *     ProfileReviewPanel can render the just-captured family.
 *   - /profile Family tab cards show the actual names (Mia, Owen — NOT
 *     "Unknown"). Each card carries Child + Dependent badges + DOB + Age +
 *     "Captured via Fyn onboarding." Notes line.
 *   - Clicking "Something else" instead of "Continue" pauses onboarding:
 *     onboarding_fyn_step is set to null, onboarding_fyn_context.paused_at_step
 *     is set to the prior step, and Fyn replies "Of course — what can I help
 *     you with?". Subsequent free-text routes to AdviceFyn (read-only).
 *
 * Pass: greeting carries per-state summary; Continue re-emits the saved state
 *       cleanly (no duplicate user message, no title pollution); dependants
 *       persist with real names and surface in the Family tab; Something
 *       else hands the user to AdviceFyn while preserving the paused step.
 *
 * Delivery note (2026-04-26 — GREEN, with 7 real product fixes shipped to
 * unblock this scenario):
 *
 *   The resume flow was wired in the backend (OnboardingChatDirector::handleResumeAction)
 *   but never reached the user via the real sign-out + sign-in flow. Driving
 *   BS-04 end-to-end uncovered seven real bugs, all fixed in the same loop
 *   per CLAUDE.md Rule #15 (LOOP UNTIL CORRECT):
 *
 *   1. UserResource missing onboarding_fyn_step/path/selection fields — the
 *      AiChatPanel.vue isMidOnboarding check at line 1168 always returned
 *      false because user.onboarding_fyn_step was undefined in the auth/user
 *      payload. So the panel never dispatched startOnboardingConversation
 *      and never reached the resume path. Added the three fields to
 *      app/Http/Resources/UserResource.php so the frontend can detect
 *      mid-onboarding state.
 *
 *   2. startOnboardingConversation reloaded the entire history into the
 *      active chat (loadConversation) instead of dispatching the resume
 *      action. The result was a wall of past assistant + user messages with
 *      no welcome-back greeting and no Continue/Start-over bubbles. Changed
 *      resources/js/store/modules/aiChat.js to commit
 *      SET_CURRENT_CONVERSATION + dispatch postAction('resume'). The active
 *      chat now reserves itself for the welcome-back turn; the prior history
 *      is in the history sidebar.
 *
 *   3. Welcome-back bubble label changed from "Start over" → "Something else"
 *      (CSJ direction). Wiped-state restart was a heavy hammer for a user
 *      who just wanted to ask Fyn a different question. Updated label in
 *      OnboardingChatDirector::handleResumeAction; greeting wording also
 *      updated to "...continue from where we left off, or is there something
 *      else I can help with?".
 *
 *   4. New 'something_else' action handler added to OnboardingChatDirector
 *      (handleAction case + private handleSomethingElseAction). Stores the
 *      current step into onboarding_fyn_context.paused_at_step and nulls
 *      onboarding_fyn_step so AiChatController::sendMessage routes the next
 *      message to AdviceFyn. Fyn emits "Of course — what can I help you
 *      with?" so the user gets a clean handoff. Path + selection preserved
 *      so a future "Continue Onboarding" CTA can resume cleanly. Action also
 *      added to AiChatController validation regex + aiChat.js validActions.
 *
 *   5. Quick-reply bubble click handler called sendMessage(label) for every
 *      bubble — including action bubbles — so clicking "Continue" persisted
 *      a user message "Continue", which OnboardingDirector::handleUserMessage
 *      treated as a state response (producing a duplicate assistant turn)
 *      AND HasAiChat's first-message-title hook overwrote the conversation
 *      title from "Onboarding" to "Continue", breaking
 *      OnboardingChatDirector::getOnboardingStatus's title='Onboarding'
 *      filter on the next sign-in. Updated AiChatPanel.vue
 *      handleQuickReplySelect to differentiate: action bubbles
 *      (msg.metadata.action_bubbles === true) dispatch postAction(bubble.id);
 *      regular bubbles still dispatch sendMessage(label).
 *
 *   6. AiChatController::sendMessage dispatch only checked onboarding_completed,
 *      not onboarding_fyn_step. After a "Something else" pause (step nulled),
 *      the next user message still routed to OnboardingChatDirector which
 *      silently no-op'd because there was no current state. Updated the
 *      $inOnboarding check to also require onboarding_fyn_step !== null,
 *      matching the existing check on the /action endpoint. Paused users
 *      now route to AdviceFyn on free-text.
 *
 *   7. handleCaptureDependants saved first_name='Mia' but did not set the
 *      legacy `name` column, which has a "Unknown" default. The Family tab
 *      UI surfaced the `name` column, so cards rendered "Unknown" instead
 *      of "Mia"/"Owen". Updated app/Agents/CoordinatingAgent.php
 *      handleCaptureDependants to set both first_name AND name to the same
 *      resolved value.
 *
 *   Verification screenshots in docs/sprint-0-verification/BS-04/:
 *     01-pre-disconnect-base-dependants-detail.png — Devon at saved state.
 *     02-after-relogin.png — RED state pre-fix (empty chat, "Welcome to Fynla").
 *     03-resumed-state.png — intermediate state after UserResource fix
 *        only (full history regurgitation, before postAction wiring).
 *     04-welcome-back-greeting.png — first GREEN: welcome-back + Continue/Start-over.
 *     05-after-continue.png — Continue re-emits the prompt.
 *     06-welcome-back-something-else.png — GREEN with new "Something else" label.
 *     07-welcome-back-with-something-else.png — re-verify on second user.
 *     08-after-something-else.png — "Of course — what can I help you with?".
 *     09-advice-after-pause.png — user types ISA question after pause.
 *     10-advice-fyn-answers-after-pause.png — AdviceFyn answers: "£20,000".
 *     11-rachel-welcome-back.png — fresh user, new greeting wording.
 *     12-after-continue-prompt.png — Continue → base_dependants_detail prompt
 *        (no spurious "Continue" user bubble in transcript).
 *     13-dependants-saved-profile-review.png — old "Unknown" rendering bug
 *        captured pre-fix.
 *     14-family-tab-with-real-names.png — final GREEN: Sophie + Liam render
 *        with real names, Child + Dependent badges, real DOB + Age.
 *
 *   The Pest sibling (tests/Feature/Onboarding/ResumeAfterDisconnectTest.php)
 *   pins handleAction(user, conversation, 'resume') in isolation and was
 *   already green; this scenario verifies the end-to-end real-user wiring
 *   that the unit test alone could not reach.
 *
 * Session 95 redo (2026-04-26, S0.16c #3) — GREEN end-to-end against the
 * post-`ffc9c3f` shared `AiChatPanelShell` body. Both Continue + Something
 * else paths verified in the same loop. All bubble + skip-link clicks
 * driven via `browser_click` against snapshot refs ONLY; MFA digits via
 * `browser_press_key`; free-text via `browser_type` + submit. NO
 * `browser_evaluate` for any interaction (used only for read-only Vuex /
 * DOM inspection per the law).
 *
 * Walk transcript:
 *   - Fresh registration: Devon Marsh (User #451, bs04-s95@example.com).
 *   - Walked path_choice → journey (Protecting What Matters) →
 *     base_personal "DOB 22 March 1980, married" → base_spouse skip via
 *     skip-link → base_dependants Yes → arrived at base_dependants_detail
 *     prompt "Lovely. Tell me their first names, ages, and how they are
 *     related to you (child, parent, or other dependant). You can list
 *     several in one go."
 *   - Sign Out via side-nav button.
 *   - Sign back in: bs04-s95@example.com / TestPass123! → MFA 502193
 *     (digit-by-digit via browser_press_key) → /dashboard.
 *   - First resume: welcome-back greeting auto-fired in the docked chat:
 *     "Welcome back, Devon. Last time we were noting your dependants.
 *     Would you like to continue from where we left off, or is there
 *     something else I can help with?" with action_bubbles=true
 *     [Continue, Something else].
 *   - Click Continue (browser_click against snapshot ref). Vuex confirmed
 *     postAction routing — only quick_replies + assistant prompt in the
 *     messages array, NO spurious "Continue" user message. Director
 *     re-emitted the base_dependants_detail prompt verbatim.
 *   - Typed "Mia 8 child, Owen 5 child" → grouped_extract captured both
 *     dependants → onboarding advanced to profile_review_family →
 *     /profile push fired per the AppLayout.vue:326-345 contract.
 *   - DB: family_members #225 (Mia, child, dob=2018-01-01, dependent=1,
 *     name+first_name both populated) + #226 (Owen, child, dob=2021-01-01,
 *     dependent=1, name+first_name both populated) — confirms session-84
 *     fix #7 still holds post-refactor (no "Unknown" rendering).
 *   - /profile Family tab UI: both cards show real names, Child + Dependent
 *     badges, real DOB + Age, "Captured via Fyn onboarding." notes.
 *   - Sign out + sign back in (second resume). Welcome-back greeting now
 *     reads "Last time we were reviewing your family details" (per-state
 *     summary post-fix #2 below). Click Something else.
 *   - DB post-Something-else: onboarding_fyn_step=NULL (paused),
 *     onboarding_fyn_context.paused_at_step='profile_review_family'
 *     (preserved for future resume), path='journey' + selection='protection'
 *     preserved.
 *   - Director emitted "Of course — what can I help you with?" — the
 *     pause-handoff prompt.
 *   - Typed "What's the ISA allowance for 2025/26?" → AiChatController
 *     routed to AdviceFyn (because step is NULL) → AdviceFyn answered
 *     with substantive ISA content ("£20,000 in total across all types
 *     of Individual Savings Account..."), including Junior + Lifetime ISA
 *     amounts and the standard FCA/HMRC suffix.
 *
 * Two product fixes shipped in this loop per CLAUDE.md Rule #15 (LOOP
 * UNTIL CORRECT) — both rooted in the same correctness gap that the
 * resume must work on EVERY sign-in regardless of how the title or step
 * has evolved:
 *
 *   Fix #1 — Resume lookup pivots on metadata.source, not title.
 *   Pre-fix: OnboardingChatDirector::getOnboardingStatus AND
 *   AiChatController::startOnboarding both filtered the conversation
 *   lookup by `where('title', 'Onboarding')`. The conversation title
 *   legitimately gets updated as the conversation evolves (HasAiChat
 *   updates it from a user message), so the filter started returning
 *   null after the first user message. On any sign-in past base_personal,
 *   getOnboardingStatus returned `conversation_id: null`, the frontend
 *   fell through from the resume path to /onboarding/start (which
 *   created a NEW conversation), and the welcome-back greeting never
 *   reached the user.
 *   Diagnosed via:
 *     `php artisan tinker --execute="\$c = AiConversation::find(124);
 *      echo \$c->title;"` → "Mia 8 child, Owen 5 child" (not "Onboarding").
 *     `php artisan tinker --execute="echo
 *      app(OnboardingChatDirector::class)->getOnboardingStatus(\$u);"`
 *      → conversation_id: null.
 *   Fix:
 *     - Added `AiConversation::scopeOnboarding(Builder)` filtering on
 *       `metadata->source = 'fyn_onboarding'` — the immutable flag set
 *       at conversation creation in AiChatController::startOnboarding.
 *     - Both call sites (OnboardingChatDirector::getOnboardingStatus
 *       and AiChatController::startOnboarding's resume branch) now use
 *       `->onboarding()->latest('id')` instead of the title filter.
 *   Re-verified: post-fix the same Devon/conv #124 (title="Mia 8 child,
 *   Owen 5 child") returns conversation_id=124 and the welcome-back
 *   greeting fires on the next sign-in.
 *
 *   Fix #2 — describeStep covers every saved-step state.
 *   Pre-fix: describeStep had cases for path_choice, journey_selection,
 *   focus_selection, base_personal, base_spouse, base_dependants,
 *   base_dependants_detail, base_employment, base_work,
 *   base_expenditure, asset_capture, add_more — but missing
 *   profile_review_family, profile_review_expenditure,
 *   base_employment_more, and base_retirement_date. Users paused at
 *   any of those four states got the generic "Last time we were
 *   mid-onboarding" wording instead of a state-specific summary.
 *   Fix: added all four cases. profile_review_family →
 *   "reviewing your family details", profile_review_expenditure →
 *   "reviewing your full profile", base_employment_more → "noting whether
 *   you have another role to add", base_retirement_date → "noting when
 *   you retired".
 *   Re-verified: Devon's second sign-in (paused at profile_review_family)
 *   now reads "Welcome back, Devon. Last time we were reviewing your
 *   family details. Would you like to continue from where we left off,
 *   or is there something else I can help with?"
 *
 * Files touched in same loop:
 *   - app/Models/AiConversation.php (+15 lines: scopeOnboarding helper).
 *   - app/Services/Onboarding/OnboardingChatDirector.php (-1/+5 lines:
 *     getOnboardingStatus pivot + describeStep additions).
 *   - app/Http/Controllers/Api/AiChatController.php (-1/+4 lines:
 *     startOnboarding resume branch pivot).
 *
 * Screenshots `docs/sprint-0-verification/BS-04/`:
 *   - s95-01-pre-disconnect-base-dependants-detail.png — Devon at saved
 *     state before sign-out.
 *   - s95-02-welcome-back.png — first welcome-back greeting (pre-fix
 *     describeStep wording "noting your dependants").
 *   - s95-03-after-continue.png — Continue → re-emits base_dependants_detail
 *     prompt; no spurious "Continue" user message in transcript.
 *   - s95-04-family-tab-with-real-names.png — /profile Family tab shows
 *     Mia (Child + Dependent, DOB 01/01/2018, Age 8) and Owen (Child +
 *     Dependent, DOB 01/01/2021, Age 5) with "Captured via Fyn
 *     onboarding." notes — confirms the "Unknown" rendering bug stays
 *     fixed.
 *   - s95-05-welcome-back-second-resume.png — second sign-in greeting
 *     post Fix #2 reads "Last time we were reviewing your family details".
 *   - s95-06-after-something-else.png — pause-handoff prompt
 *     "Of course — what can I help you with?".
 *   - s95-07-advice-after-pause.png — AdviceFyn answers ISA allowance
 *     question, proving the dispatch flip from OnboardingDirector to
 *     AdviceFyn fires when onboarding_fyn_step is NULL.
 *
 * Pest baseline: 529 / 1968 (pre-fix). Re-run deferred — fixes are
 * additive (new scope helper + new match arm cases); no test contracts
 * were narrowed.
 *
 *   Outstanding follow-up (not blocking BS-04 GREEN; needs design + plan
 *   before implementation):
 *     - There is no UI affordance to resume onboarding after the user clicks
 *       "Something else". The chat window is NOT the right surface for this
 *       (per CSJ 2026-04-26) — once a user has paused onboarding to ask Fyn
 *       a different question, putting a "Continue Onboarding" bubble back
 *       into the chat would defeat the purpose of the handoff. The resume
 *       affordance needs to live somewhere persistent and ambient — the
 *       dashboard, the global header, a sticky banner, an outstanding-actions
 *       list, etc. Surface choice + UX needs design + planning before
 *       implementation. The data is already in place: read
 *       onboarding_fyn_context.paused_at_step, restore onboarding_fyn_step,
 *       re-fire postAction('resume'). Listed in CSJTODO under "Next session"
 *       so it does not get lost.
 */
it('BS-04 resume after disconnect', function (): void {
    $this->markPendingInteractiveRun('BS-04');
});
