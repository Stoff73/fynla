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
 * Seed: factory user at state base_dependants_detail with one parked
 *       dependant; last ai_messages.created_at backdated 6+ minutes:
 *         AiMessage::where('conversation_id', $cid)->update(['created_at' => now()->subMinutes(6)]);
 *
 * Script:
 *   1. Login::as($email, $password)
 *   2. Chat panel opens; first visible turn is the welcome-back greeting.
 *   3. browser_snapshot → docs/sprint-0-verification/BS-04/01-resume-greeting.png
 *   4. browser_click "Continue" (id='continue')
 *   5. browser_wait_for the base_dependants_detail prompt.
 *   6. browser_snapshot → docs/sprint-0-verification/BS-04/02-resumed-state.png
 *
 * Assertions:
 *   - First visible chat turn includes the resumeSummary fragment for
 *     base_dependants_detail: "noting your dependants" (per
 *     OnboardingChatDirector::describeStep).
 *   - Two bubbles visible with labels "Continue" and "Start over".
 *   - After clicking "Continue", the visible prompt text matches the
 *     base_dependants_detail prompt (state has not advanced — the
 *     resume action re-emits the same state).
 *   - Pest-side: $user->fresh()->onboarding_fyn_step === 'base_dependants_detail'.
 *
 * Pass: greeting carries the per-state summary; bubbles present;
 *       continue path re-emits the saved state.
 */
it('BS-04 resume after disconnect', function (): void {
    $this->markPendingInteractiveRun('BS-04');
});
