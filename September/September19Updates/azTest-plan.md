# Azlan and Laura test — fix plan (2026-09-19)

Source: `azTest.md` plus Brett's Fyn edit report. Every cause below was read in the
code, and where it mattered checked against the production transcripts of Laura
(user 741, conversations 897/898), Azlan (742) and Brett (740) on 2026-09-19.

Rules that shape the plan: one Fyn change in one place for all surfaces (Rule 20);
done means web AND `/m` (Rule 19); no scores, no new icons; build to this plan and
stop to ask if it looks wrong (Rule 16).

---

## What actually happened (evidence)

| Who | What they hit | Transcript |
|---|---|---|
| Laura | "I don't have investment" acknowledged as "Recorded — no investments." then "Sorry, I didn't catch that" four times in a row, including after "Yes, that's right" | 897: 2394–2405 |
| Laura | Escaped that loop only by tapping "No, change something", which produced the canned refusal and then advanced | 897: 2406–2408 |
| Laura | "Can I change that answer?" → "Yes, you can change any answer." → the invitation question re-asked verbatim | 897: 2435–2437 |
| Laura | Invited `azlan.raj@…`; no permission row, no link. Azlan registered and is still parked at `path_choice`, `spouse_id` null on both accounts | users 741/742 |
| Laura | "Help me complete my tax strategy details" → Fyn: "your tax strategy recommendations are currently locked" → "I have none of those" → still locked. Actions list carries "Unlock pension info" and "Unlock ISA info" | 898: 2444–2447; `NextActionsService::buildAll(741)` |
| Laura | Byte-identical reply twice (2449 and 2452) after "No" then "Ok"; then a tool-narration leak "Let me retrieve your savings and investment analysis." | 898: 2449–2454 |
| Brett | Verify page for property → "No, change something" → "Mortgage is 300000" → "I wasn't able to apply that change". Then "Continue", "It all correct", "Yes, that's right" ALL got the same failure. He is still parked at `campaign_verify_edit` today | 896: 2358–2369 |

---

## Batch 1 — capture forms and small UI (one PR, no design questions)

Cause before symptom; every item is one schema or one file and lands on both
surfaces because the form state is shared.

| # | Report | Cause | Fix |
|---|---|---|---|
| 1.1 | Employer and trading name don't allow spaces | `resources/mobile/utils/captureFormState.js:101-104` trims on every keystroke; the input is `:value`-bound so Vue writes the trimmed value back over the space the user just typed. Affects every text field on web and `/m` (web imports the same state file) | Trim in `submit()` and `hasText`, never in `setText` |
| 1.2 | No way to say "none" at investments; "then it didn't catch it" | `investment` schema has no `allow_empty`; typed "I don't have investment(s)" misses `isCompletionDeclaration` (`OnboardingChatDirector.php:6960-6961` requires "any/a/an/one" after "don't have"), so the delegated turn acks and the zero-tool guard re-asks | `allow_empty` plus kinds prompt on the investment schema (pattern already on `spouseAssets`); widen the declaration regex to "don't have <noun>" and "yes, that's right" on a form state with nothing to save |
| 1.3 | Spouse income unknown, no "I don't know" | `spouse_annual_income` required money field, `CaptureForms.php:815-840` | `money_or_none` with an "I don't know" label; store the unknown as null, not zero |
| 1.4 | Joint account question and account types unclear | Savings form: four bare kind chips, "Ownership: Individual / Joint" with no hint; joint is fixed 50/50 with the spouse (`CaptureForms.php:410-412`) but the form never says so; on "add another" the lead-in is blanked so the form is wordless | One-line hint per kind; ownership hint "Joint means shared 50/50 with {spouse first name}"; keep a one-line lead-in on re-entry |
| 1.5 | Registration text broken at the bottom on mobile web | `resources/js/views/Register.vue:215` `whitespace-nowrap` on a 76-character legal sentence | Drop the class |
| 1.6 | Left menu can't scroll to the bottom on iPhone mini | Drawer nav pads with `env(safe-area-inset-bottom)`, which is zero inside the `/m` iframe (`dashboard.css:1188-1192`, `mobile-host.blade.php:31`); sign out sits under the gesture bar | Static bottom pad on the nav; verify on a real iPhone, not headless |

Tests: Vitest on `captureFormState` (space survives a keystroke), Pest on the
declaration regex and the investment form empty save, Playwright walk of the
savings and investments steps on `/m`.

---

## Batch 2 — land the parked fixes branch

`docs-bugs-fixed-log` (12 commits, 2026-09-14) merges into today's `dev` with zero
conflict hunks and contains three of Azlan's items:

- **MB-24** — "Something else" at the front door is an answer, not the skip action.
  Exactly Azlan's dead end (`onboardingChat.js:587-588, :616`). A third occurrence
  at `:288-289` (transcript replay) is not covered by MB-24 and gets fixed here.
- **MB-18/19/20** — `/m` login gains the two-factor and restore steps. Today `/m`
  `Login.vue:120-129` has no branch for `requires_mfa` (returned with HTTP 200 and
  no token), so a user asked for a code sees nothing happen. This is the most
  likely reason Laura "couldn't sign in".
- **MB-26, MB-27/47** — server decides who needs onboarding; page refresh after a
  Fyn write. Both help the invitee path in Batch 3.

The other nine commits are all real fixes with tests, none merged: MB-28 (profile
review pause returns to the route it left), MB-33 (wizard step endpoint validates
with the profile rules), MB-35 (wizard copy British, civil partnership option),
MB-48 (household figures for a non-working spouse), MB-50 (`/m` expenditure shows
the entered figure beside the total), MB-56/57/58 (pension capture scope, one
backstop row, pot loop exits), plus the `bugsFixed.md` ledger.

**CSJ 2026-09-19: merge the whole branch.** Nothing is dropped. MB-28 touches the
web profile-review pause that the 2026-09-17 release rerouted for the journey path,
so that one is re-verified on web after the merge.

---

## Batch 3 — spouse invitation to a linked household

Cause: the invitation is sent and forgotten. `inviteUnregisteredSpouse()`
(`SpouseLinkingService.php:425-461`) writes no row because `spouse_permissions.spouse_id`
must reference a user; the email is never stored; `SpouseHoldingTransfer` runs only
from `SpousePermissionController::accept()`. So Azlan registers as a stranger,
gets the ground-zero front door, and none of Laura's figures reach him.

| # | Report | Fix |
|---|---|---|
| 3.1 | Invite lands on the homepage | `RedirectPhoneToMobile.php:26-33` has no `register`/`login` in `EXCLUDED_PREFIXES`, so on a phone `/register` becomes bare `/m` and the host frames the homepage. Add them, preserving `?to=` like the campaign prefixes. Also fixes the `/m` login "Create an account" link |
| 3.2 | Invite lands on the homepage, registration starts from ground zero | New `spouse_invitations` table: inviter id, invited email, first name, opaque token, expiry, accepted_at. `inviteUnregisteredSpouse()` writes it and the email link carries the token. The link opens the **registration page prefilled** with what we hold (first name, email; last name if the spouse card has it), so the invitee types a password and continues. On submit the token is consumed the way `resolveRegistrationHandoff` (`routes/api.php:158`) consumes the funnel handoff |
| 3.3 | Nothing of Laura's on Azlan's profile | Registering from the link **links the accounts automatically**, no accept step: the inviter consented by inviting, the invitee by registering from the link. Registration runs the existing `establishAcceptedLink()`, so `SpouseHoldingTransfer` (date of birth, employment status, income via `EmploymentIncomeService`, savings, ISA, investments, pension) and `SpouseJointRecords` (stamps `joint_owner_id` on the joint records) fire before Fyn opens. Joint properties become visible through `joint_owner_id`; they are not copied, a copy would duplicate them |
| 3.4 | Front door is meaningless for an invitee | No journey/focus question for an invitee. Fyn opens knowing what {inviter} told us and **asks only for the gaps, through the forms, each form showing what we already hold** (the same prefill as Batch 4). Order: personal details form prefilled, work form prefilled with the stated income, then the account forms for anything the inviter did not cover, then the household tax strategy |
| 3.5 | Closed Fyn, lost on the dashboard with an empty actions list | `NextActionsService.php:117-125` suppresses every row while `onboarding_fyn_step` is set, and a new user has no recommendations, so the list is empty while the nudge says "Finish your personalised tax plan". Render one "Continue with Fyn" row in that state instead of nothing. The pop-up he saw is that nudge (`Dashboard.vue:206`), not the milestone card |

**CSJ 2026-09-19, agreed:** link on click, registration prefilled, password only,
Fyn asks for gaps through forms that show what we have. The order in 3.4 is my
proposal within that; say if you want it different.

Tests: Pest on token issue/consume/expiry and on the link + transfer; Playwright
from Laura's invitation email to Azlan's first dashboard on web and `/m`.

---

## Batch 4 — Fyn edits through the form (Brett, "Can I change that answer?")

This is the design change you described. Today there is no way for a user to edit
a record through Fyn that works end to end:

- Post-onboarding, an edit goes advice → `delegate_to_capture` → an LLM turn whose
  own instructions say "you will not have prior record ids in this turn", so it
  asks a question and never shows a form.
- In the verify loop, `campaign_verify_edit` runs an LLM update turn scoped by
  section. For **property** the scope is empty: `FynVerifyEditTurnInstructions::toolsForSection`
  has no `property` case (returns `[]`) and `verifyEditRecordScope` has no property
  branch. Every edit fails, and nothing but a landed write leaves the state, so
  "Yes, that's right" and "Continue" fail too. That is Brett's transcript exactly.
- Mid-walk, "Can I change that answer?" is classified as a question and answered
  by read-only Advice Fyn ("Yes, you can"), then the current step is re-emitted.

**One pathway, three doors.** A deterministic edit pathway in `OnboardingChatDirector`,
reached from (a) `campaign_verify_edit`, (b) the advice → capture handoff when the
intent is edit/change/correct/delete, and (c) a recommendation action of kind
`edit`. Behaviour:

1. Resolve the entity type from the section or the message; load candidates with
   `user_id = ? OR joint_owner_id = ?`.
2. **One record** → emit the existing `capture_form` for that type with `answers`
   prefilled from the record and a `record: {type, id}` marker.
3. **Several** → emit action bubbles, one per record labelled by name and provider,
   plus "A different one". The pick emits the prefilled form. Bubbles already render
   on web and `/m`; no new component.
4. **None** → say so and offer the blank form.
5. On submit, `handleFormTurn` sees `record` and routes to the existing
   `handleUpdateRecord` allowlisted update (or the profile write for work/spouse/
   expenditure) instead of the create tool. `RecaptureGuard` is not consulted; the
   form is the explicit edit the 2026-08-17 contract asks for.
6. Delete: a "Remove this record" control on a prefilled form, routed to
   `delete_record`, with one confirmation line. Only for record-backed types.
7. Exits: on a verify page, "Yes, that's right" / "Continue" / "Nothing" leave
   `campaign_verify_edit` back to `campaign_verify_navigate` whether or not a write
   landed.
8. "Can I change that answer?" mid-walk: detect correction intent before the
   question classifier and open the pathway on the last-saved section, then return
   to the step that was interrupted.

**"Add another" is an add, nothing else** (CSJ 2026-09-19). It is not an edit,
delete or update, so the August contract, which governs those, does not apply to
it. The loop form carries `intent: new` and the create path does not run the
"same or separate?" collision check for it. Laura's second current account at the
same bank matched the first (identity is name + institution, `RecaptureGuard.php:123-128`,
and the form derives the name from provider + kind), so the form reopened with the
duplicate question pinned. The guard also gains `account_type` in the savings
identity so two products at one bank never collide on the typed path either.

**Prefill needs one change** in the shared `captureFormState.js` (accept initial
answers) and the `capture_form` event gains `answers` and `record`. Both surfaces
read the same state file, so it is one edit.

**Scope:** the types that have a form today: property with mortgage, ISA, savings,
investment, pension, protection, personal, spouse, dependants, work, expenditure.
Goals, estate items and life events keep the LLM update path but get the record
chooser so the id is never guessed.

**CSJ 2026-09-19, agreed:** bubbles for the chooser. Brett's account: once the
exit in step 7 lands he can answer "Yes, that's right" and continue. No database
edit.

Tests: Pest on the pathway for one, many and none candidates and for the property
section; on the verify-edit exits; on `intent: new` bypassing the guard. Playwright:
Brett's exact sequence on web and `/m`, and a post-onboarding "change my Halifax
balance" from the dashboard on both.

---

## Batch 5 — tax strategy: locked, asks for records the user has declared, what next

| # | Report | Cause | Fix |
|---|---|---|---|
| 5.1 | Asked for non-ISA savings, GIAs, pension history she doesn't have; still locked after "I have none" | `HouseholdFinancialContext::availability()` (`:39-58`) is record-counting only. Laura's row: `gia_holdings`, `pension_contributions`, `pension_input_history`, `isa_subscriptions_ytd` all false. The declaration is recognised but never stored | Persist declared-none keys to `onboarding_fyn_context.declared_none[]` when the matcher fires (typed or the empty-form save from 1.2); `availability()` honours them. Backend only, all surfaces |
| 5.2 | "Tax strategy is locked" from the actions list | `strategy_unlock:*` rows (`NextActionsService.php:530-566`) render a violet "Unlock" pill labelled Tax Strategy; Fyn's own reply also says "currently locked" because the composed plan carries `locked` for the same missing keys | Falls out of 5.1. Also split `moduleCards` (`:206-245`) so "gate open, nothing to recommend" is not shown as locked |
| 5.3 | "Landed on tax strategy – didn't know what to do next" | At the end of onboarding Fyn says "We've created your personal tax strategy, {name}." and sends the user to the tax strategy page. The page lists the strategies; nothing on it or from Fyn says what to do with them, and the only prompt is the footer button "See all your actions" | Fyn's closing line tells her what she is about to see and what to do with it, e.g. "Here are the strategies that apply to you. Open one to see the steps, or ask me about any of them." The page shows the same one-line lead-in on a first visit. Proposed wording, amend as you like |
| 5.4 | Same message twice about savings and investments | Two byte-identical replies to "No" then "Ok" (2449/2452), then a tool-narration leak. Not a retry row, so the history filter is not the cause | Reproduce with that transcript against the model before fixing; strip "Let me retrieve…" narration at the streaming boundary regardless |

---

## Batch 6 — tone, and the Save Tax landing page

| # | Report | Cause | Fix |
|---|---|---|---|
| 6.1 | Sulky, hard to understand recommendations | Deterministic: `OnboardingChatDirector.php:1405-1411` prefixes "You may want to consider:" then recites each strategy's caveat-heavy description (e.g. `BedAndIsaStrategy.php:136-142`). Prompt side: the tone rules exist twice, `FynSystemPrompt.php:71-90` and a dormant drifted copy in `CoreIdentity.php:46-68` | Each strategy class gets an action sentence and a separate caveat; the director renders action first, caveat as a second line; drop the hedge prefix. Delete the `CoreIdentity` copy (Rule 20) |
| 6.2 | Landing page allowances overwhelming | `public/pages/savetax-plan.php` renders 6 cards (single) or 14 (married) in two columns, each with amount, state, explanation and a savings line. The headline "Total of allowances marked available" (`:170-171`) adds Personal Allowance, ISA, pension annual allowance, PSA, dividend and CGT allowances together, which is meaningless; the in-app page already says they are not additive | Replace the total with a count and the "not additive" sentence; collapse explanations behind the existing `<details>`; show the top three, "see all" for the rest |
| 6.3 | Partner missing from the landing page | "partner" appears nowhere; the hero and "Your allowances" heading read as single-person even when 8 of 14 cards are the spouse's | When the funnel answered spouse = yes: hero subhead "because of you and your partner", grid grouped "Yours" and "Your partner's" |

Web and `/m` share these files (the `/m` host frames the same page). Adjacent, not
fixed: `SaveTaxCampaignPage.vue` is a dead second copy of the layout; the page
metadata says "5 quick questions" while the counter says 4.

---

## Batch 7 — `/m` login recovery

No forgotten-password link anywhere in `resources/mobile/`; web has `ForgotPasswordModal.vue`
and the six reset endpoints exist (`routes/api.php:174-181`). Add the link to `/m`
`Login.vue`, opening the reset flow in the `/m` chrome. With MB-18/19/20 from Batch 2
this closes Laura's sign-in dead end. Lockout copy: after three failures the user is
told to wait one minute rather than silently refused.

---

## Batch 8 — delay after Continue on the verify page (`/m` only)

`MobileChrome.vue:385-404` runs three sequential round-trips (open Fyn and reload the
user, resume the onboarding stream and fetch the whole transcript, then post the
confirm) before anything renders. Push the user's confirmation bubble into the
transcript immediately and skip the resume and transcript fetch when the
conversation id is already known.

---

## Order and gating

1. Batch 1, then Batch 2 (both same day, one Playwright pass).
2. Batch 4 next; it unblocks Brett and is the biggest behaviour change.
3. Batch 3 (needs your two decisions), then 5, 6, 7, 8.

Each batch: focused Pest and Vitest, then a Playwright walk on csjones web and `/m`
from registration, then PR to `dev`. Before the release: one full walk as Laura, one
as Azlan from the invitation email, one as Brett through a property correction, on
both surfaces. Purge the test accounts afterwards.

**Not in scope, reported only:** the Free plan cap of two accounts and two
properties stopped Laura mid-walk (897: 2387, 2418); four copies of the
`capture_form` event switch in `aiChat.js`; the dead `SaveTaxCampaignPage.vue`.

---

## Status — 2026-09-19, end of build

All eight batches are built, tested and pushed as a **stacked** set of PRs into `dev`.
Merge in this order (each contains the ones before it until they land):

| Order | PR | Branch | Verified live locally |
|---|---|---|---|
| 1 | #907 | `merge/docs-bugs-fixed-log` | /m two-factor login step (Batch 2) |
| 2 | #908 | `fix/az-batch1-forms` | spaces in text fields, "none" at investments, empty save, verify pills, registration wrap — on /m |
| 3 | #909 | `fix/az-batch4-fyn-edit-forms` | Brett's sequence on /m AND web: prefilled property form, mortgage £400,000 → £300,000, read-back, page refreshed |
| 4 | #910 | `fix/az-batch3-spouse-invitation` | web: invitation link prefills registration, registering links both accounts, £230,000 handed over, campaign copied. **Carries a migration.** |
| 5 | #911 | `fix/az-batch5-tax-strategy` | tests only (declared none, locked semantics, copy) |
| 6 | #912 | `fix/az-batch6-tone-landing` | web: landing page single and with a partner (14 cards, Yours / Your partner's, "10 of 14") |
| 7 | #913 | `fix/az-batch7-8-m-login-verify` | /m login shows "Forgotten your password?" |

**Blocked:** the csjones deploy and browser pass, and therefore every admin-merge,
wait on the `fynlaDev` SSH key being unlocked in the session (`! ssh-add ~/.ssh/fynlaDev`).

**Not done, on evidence:** 5.4 (the byte-identical repeated reply) needs a model run
against Laura's transcript before a fix; only the tool-narration leak was addressed.

**Found on the way, fixed inside the batches:** the HTTP layer refused an empty form
with 422 (the director-level test was green over it); the property and protection
pages on both surfaces did not refetch after an edit on the same screen; the advice
side held a different director instance so the forms flag never reached it; two tests
had been red on `dev` since 2026-09-17 asserting the old family-review routing.

**Reported, not in scope:** the Free plan cap of two accounts and two properties
stopped Laura mid-walk; four copies of the `capture_form` switch in `aiChat.js`;
the dead `SaveTaxCampaignPage.vue`; `/m` Playwright clicks inside the host iframe
do not reach Vue handlers in this session (DOM `.click()` does), a tooling quirk.
