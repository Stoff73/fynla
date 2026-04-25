<?php

declare(strict_types=1);

/**
 * BS-06 — parked facts flush
 *
 * Sprint: 0
 * Invariants: INV-2.2.6 (parked facts flushed at commit)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-06
 * Pest sibling: tests/Feature/Onboarding/ParkedFactsFlushTest.php (S0.15.3)
 * Screenshots: docs/sprint-0-verification/BS-06/
 *
 * Seed: factory user at state base_personal with first_name='Seeded' and
 *       AiConversation->onboarding_parked_facts seeded with
 *       ['personal' => ['first_name' => 'Seeded']].
 *
 * Script:
 *   1. Login::as($email, $password)
 *   2. Chat panel opens at base_personal.
 *   3. browser_type "My date of birth is 1 April 1980, I'm married"
 *   4. browser_press_key('Enter')
 *   5. browser_wait_for assistant ack and next-state transition.
 *   6. Navigate to /profile via UI menu.
 *   7. browser_snapshot → docs/sprint-0-verification/BS-06/profile-page.png
 *
 * Assertions:
 *   - Profile page shows first name "Seeded".
 *   - Pest-side: User::find($id)->first_name === 'Seeded'.
 *   - Pest-side: AiConversation::find($cid)->onboarding_parked_facts
 *     does NOT contain a 'personal' bucket (flushed by
 *     OnboardingChatDirector::flushParkedFactsForState).
 *   - Pest-side: $user->date_of_birth and $user->marital_status are
 *     populated from the message just submitted.
 *
 * Pass: DB and UI consistent; parked keys cleared.
 */
it('BS-06 parked facts flush', function (): void {
    $this->markPendingInteractiveRun('BS-06');
});
