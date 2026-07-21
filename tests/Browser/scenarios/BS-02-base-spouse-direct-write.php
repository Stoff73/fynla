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
 * Seed: NONE — drives the canonical Quick start with Fyn real-user flow per
 *       MEMORY.md feedback_loop_until_correct + CSJTODO Batch 3 pattern.
 *       Register fresh via /register?from=fyn → MFA → /dashboard auto-onboarding
 *       → path_choice → journey_choice (Protecting What Matters) → base_personal
 *       (DOB + married) → base_spouse turn lands.
 *
 * Script:
 *   1. Sign out + clear session storage.
 *   2. Navigate landing → "Quick start with Fyn" CTA → /register?from=fyn.
 *   3. Fill register form (unique email) → submit.
 *   4. Pull verification_code from pending_registrations → fill 6-digit MFA.
 *   5. Land on /dashboard with auto-opened onboarding chat.
 *   6. Click "Follow a journey" then "Protecting What Matters".
 *   7. Type "DOB 22 March 1980, married" → Enter (advances base_personal).
 *   8. Type "Angela, DOB 12 January 1976, email aslater@gmail.com" → Enter.
 *   9. Wait for assistant ack: "Got it — I've added Angela and linked the two of you."
 *  10. Navigate to /profile (avatar dropdown route — see amendment 1 below).
 *  11. Click the "Family" tab.
 *  12. Capture docs/sprint-0-verification/BS-02/08-family-tab.png.
 *
 * Assertions (verified GREEN 2026-04-26 via real-user flow):
 *   - users row updated: marital_status='married', spouse_id=<spouse user id>,
 *     date_of_birth set per typed DOB.
 *   - users spouse row created: first_name='Angela', email='aslater@gmail.com',
 *     date_of_birth='1976-01-12', spouse_id=<original user id> (bidirectional).
 *   - family_members rows: TWO rows, one per direction (user→spouse + spouse→user)
 *     each with relationship='spouse' and linked_user_id pointing at the partner.
 *   - ai_audit_events: capture_spouse_details rows with status=dispatched + persisted,
 *     operation='write'.
 *   - /profile Family tab UI: Angela card with name, "Spouse" badge, "Account Linked"
 *     badge, DOB rendered as 12/01/1976, Age 50, "Linked account — can only be edited
 *     or deleted by logging into the spouse's account" caption.
 *   - Chat ack matches "Got it — I've added Angela and linked the two of you."
 *
 * Pass: Angela visible in /profile Family tab with both badges, both family_members
 *       rows in DB, both users rows linked bidirectionally, audit chain shows the
 *       capture_spouse_details persist row.
 *
 * Delivery note (2026-04-26 — GREEN):
 *   Driven via canonical Quick start with Fyn flow. User #356 (Marcus Holloway,
 *   bs02-real@example.com) → User #357 (Angela, aslater@gmail.com) bidirectionally
 *   linked, family_members #31/#32 created, audit chain rows #341/#342 confirm the
 *   capture_spouse_details direct-write. 9 screenshots in docs/sprint-0-verification/BS-02/
 *   (01-landing, 02-register-form, 03-path-choice, 04-journey-choice, 05-base-personal,
 *   06-base-spouse-prompt, 07-spouse-ack, 08-family-tab, 09-settings-no-connected-accounts).
 *
 *   Three real stub-script amendments uncovered (none block GREEN; all carry forward
 *   to S0.17 spec-amendment list):
 *     1. The Marcus-Holloway avatar button at the top-nav does not open a Profile
 *        dropdown via single click in the live SPA. The /profile route is reachable
 *        via direct URL (used here) and via a Profile sub-link the side-nav Account
 *        item routes to /settings — not /profile. Stub script step "click avatar →
 *        'Profile'" should be relaxed to "navigate to /profile" (any UI route is
 *        fine; Profile is not on the side-nav, only via /profile direct).
 *     2. The Family tab card UI does NOT surface the spouse's email — it shows name,
 *        Spouse badge, Account Linked badge, DOB, and Age. Email lives only on the
 *        linked users row. Stub assertion "Family tab shows Angela with email
 *        aslater@gmail.com" is wrong — verify email via the linked-user record.
 *     3. /settings has no "Connected accounts" tab. Live tabs are General / Security
 *        / Privacy / Assumptions. Spouse linkage is visualised via the "Account
 *        Linked" badge on the /profile Family card — that IS the canonical UI for
 *        verifying linkage. Stub assertion "/settings → Connected accounts → spouse
 *        link present" should be replaced with "/profile Family tab → Angela card
 *        shows the Account Linked badge".
 *
 *   Pest-side note: family_members has no email column (verified via
 *   information_schema). Replace the stub's
 *   `FamilyMember::where(['user_id' => $user->id, 'relationship' => 'spouse'])->first()
 *   exists with first_name='Angela', email='aslater@gmail.com'`
 *   with: `family_members.first_name='Angela' AND
 *   users WHERE id = family_members.linked_user_id has email='aslater@gmail.com'`.
 *
 * Session 94 attempted re-walk (2026-04-26, S0.16c #2) — TAINTED, MUST
 * BE REDONE. The session 94 walk relied on the BS-01 walk in the same
 * session for the spouse-capture interaction, and that BS-01 walk was
 * itself driven via `browser_evaluate(...)` JS-clicks instead of
 * `browser_click` against snapshot refs — banned by
 * `critical_browser_testing_law.md` and the S0.16c pre-flight block.
 * The /profile Family tab snapshot was the only Playwright-real
 * verification, but it does not by itself prove the chat-panel
 * spouse-capture contract held against the new shared body. NO claim
 * of GREEN can rest on this walk.
 *
 * Session 95 redo (2026-04-26, S0.16c #2) — GREEN against the post-
 * `ffc9c3f` shared `AiChatPanelShell` body. The base_spouse capture
 * was driven as part of the same session-95 BS-01 walk (User #449
 * Laury Greenwood); this BS-02 evidence rolls up the spouse-specific
 * acceptance against that walk plus a fresh /profile Family tab visit.
 * All chat interactions in the upstream walk used `browser_click` against
 * snapshot refs, no `browser_evaluate` shortcuts.
 *
 * Walk transcript (relevant slice from session-95 BS-01 walk):
 *   - base_spouse grouped_extract prompt: "Great — let's add your spouse's
 *     details. Can you share their first name, date of birth, and email
 *     address? I'll create an account and link the two of you so you can
 *     plan together."
 *   - User typed via browser_type: "Angela, 12 January 1985,
 *     angela-bs01s95@example.com" + Enter.
 *   - LLM (capture_spouse_details tool) extracted all three fields,
 *     CoordinatingAgent::handleCaptureSpouseDetails dispatched the
 *     direct-write, HouseholdProvisioner.ensureFor created household
 *     #6, two FamilyMember rows + one new User row inserted, both
 *     User rows updated with spouse_id pointing at each other.
 *   - Director ack rendered: "Got it — I've added Angela and linked
 *     the two of you." (verified live in chat)
 *
 * DB acceptance (queried from primary `laravel` DB post-walk):
 *   - users #449 (Laury): spouse_id=450, household_id=6,
 *     marital_status='married', date_of_birth='1985-01-12'.
 *   - users #450 (Angela, freshly created): spouse_id=449,
 *     household_id=6, email='angela-bs01s95@example.com',
 *     first_name='Angela', date_of_birth='1985-01-12'.
 *   - family_members #223: user_id=449, linked_user_id=450,
 *     relationship='spouse', first_name='Angela'.
 *   - family_members #224: user_id=450, linked_user_id=449,
 *     relationship='spouse', first_name='Laury' (bidirectional).
 *   - ai_audit_events #432 (capture_spouse_details, op=write,
 *     status=dispatched) and #433 (op=write, status=persisted) — the
 *     INV-2.10.x dispatched+persisted audit pair.
 *
 * UI acceptance (fresh /profile Family tab snapshot in this session,
 * after BS-01 closed):
 *   - Tab order: Personal Info / Health / Family / Subscription;
 *     Family active.
 *   - Family Members card heading.
 *   - Angela row card (only family member): heading "Angela",
 *     "spouse" relationship badge, "Account Linked" status badge,
 *     "Date of Birth" field 12/01/1985, "Age: 41".
 *   - Caption "Linked account — can only be edited or deleted by
 *     logging into the spouse's account" — confirms the linked-User
 *     bidirectional state.
 *   - Email NOT shown on the card (per session-84 amendment 2:
 *     email lives only on the linked users row, never on the
 *     family_members card UI).
 *
 * Screenshots `docs/sprint-0-verification/BS-02/`:
 *   - s95-01-landing through s95-07-spouse-ack are reused from the
 *     session-95 BS-01 walk (the captures are bytewise identical for
 *     turns 1-7 because both scenarios drive the same flow up to
 *     base_spouse).
 *   - s95-08-family-tab.png — fresh post-refactor capture of /profile
 *     Family tab showing Angela's card with both badges.
 *
 * Pest baseline: 529 passed / 1968 assertions / 0 failures (97.00s).
 *
 * The three stub-script amendments below (session 84) still apply
 * post-refactor — they are spec-text issues, not refactor regressions,
 * and remain on the carry-forward list for S0.17.
 */
it('BS-02 base_spouse direct-write', function (): void {
    $this->markPendingInteractiveRun('BS-02');
});
