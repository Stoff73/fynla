<?php

declare(strict_types=1);

/**
 * BS-02 — base_spouse direct-write
 *
 * Sprint: 0
 * Invariants: INV-2.2.2 (grouped-extract direct-write)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-02
 * Screenshots: docs/sprint-0-verification/BS-02/
 *
 * Seed: factory user at state base_spouse (onboarding_fyn_step='base_spouse',
 *       onboarding_fyn_path='journey', onboarding_fyn_selection='protection').
 *
 * Script:
 *   1. Login::as($email, $password) — drive Playwright per BS-01 steps 1-4.
 *   2. Chat panel opens at the base_spouse turn.
 *   3. browser_type into chat input:
 *      "Angela, DOB 12 January 1976, email aslater@gmail.com"
 *   4. browser_press_key('Enter')
 *   5. browser_wait_for assistant ack ≈ "Got it, I've added Angela…"
 *   6. Navigate to /profile via UI: click avatar → "Profile" (NOT typed URL).
 *   7. Click the "Family" tab.
 *   8. browser_take_screenshot → docs/sprint-0-verification/BS-02/family-tab.png
 *
 * Assertions:
 *   - Family tab shows Angela with DOB 12/01/1976, email aslater@gmail.com.
 *   - Pest-side: FamilyMember::where(['user_id' => $user->id, 'relationship' => 'spouse'])
 *     ->first() exists with first_name='Angela', email='aslater@gmail.com'.
 *   - Navigate to /settings → "Connected accounts" → spouse account link present.
 *
 * Pass: Angela row visible in family tab, email matches, screenshot captured,
 *       FamilyMember row in DB.
 */
it('BS-02 base_spouse direct-write', function (): void {
    $this->markPendingInteractiveRun('BS-02');
});
