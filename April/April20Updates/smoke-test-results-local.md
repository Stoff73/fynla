---
tags: [test-results, onboardingFyn, PRD, P0, local]
date: 2026-04-20
session: 3
branch: onboardingFyn
status: 6/7-PASS-1-FAIL-1-FIX-REQUIRED-BEFORE-DEV
---

# Smoke test results — PRD P0 (local, localhost:8000)

**Branch:** `onboardingFyn`
**Baseline commit:** `760939e`
**Run date:** 2026-04-20
**Environment:** localhost:8000 (Laravel dev server) + Vite 5.4.21 on :5174
**DB:** `laravel` (local MySQL), re-seeded with `php artisan db:seed` before run

## Summary

| # | Item | PRD ref | Result |
|---|------|---------|--------|
| 1 | Preview block 403 | FR-M9 | **PASS** |
| 2 | Hybrid base_personal (partial capture) | FR-M10 | **PASS (after local fix)** |
| 3 | Full walkthrough path_choice → done | FR-M11 | **PASS** |
| 4 | Post-onboarding expenditure sync | FR-M12 | **PASS** |
| 5 | Spouse email collision | FR-M13 | **PASS** |
| 6 | Asset-capture off-script suppression | FR-M14 | **FAIL — documented below** |
| 7 | Trust CLT orphan prevention | FR-M15 | **PASS (save + cancel both)** |

Net: **6/7 PASS, 1 FAIL requiring follow-up**. Test 2 required a fix landed on `onboardingFyn` (uncommitted as of this run — see "Code changes landed locally" below). Test 6 is a FR-M14 regression that I'm flagging for a separate PR before the `onboardingFyn → dev` merge-back; it does not block the other 6 items.

---

## Test 1 — FR-M9 preview block

**Setup:** landing `/?demo=true` → persona picker → `Emily & James Carter` (preview_young_family, user_id=113).

**Action:** `POST /api/ai-chat/onboarding/start` with bearer token from sessionStorage, body `{ journey: 'protecting_and_growing' }`.

**Result:**
- HTTP status: `403`
- Body: `{"success":false,"reason":"preview_mode"}`
- DB: `users.id=113` `onboarding_fyn_step` remained `null` (preview data untouched)

**Verdict:** PASS. Middleware aborts before the controller runs.

---

## Test 2 — FR-M10 hybrid base_personal

### Divergence from first attempt

On the first attempt (user `test-fr-m10-dob-1@example.com`) I typed `"12 January 1985"` at `base_personal`. Expected: handler writes DOB, Fyn re-asks marital with "Got it — I have you down as born on 12 January 1985…". Actual: handler wrote `date_of_birth=1985-01-12` AND `marital_status=single`; state advanced straight past `base_spouse` to `base_dependants`; no hybrid pre-confirm message.

**Root cause** (`app/Services/AI/AiToolDefinitions.php:985` before fix):

```php
'required' => ['date_of_birth', 'marital_status'],
```

Both fields were required on the `capture_personal_details` tool schema, so the LLM could not return a single-field call. It filled `marital_status="single"` as a default whenever the user supplied only a DOB, giving the handler a complete payload. FR-M10's hybrid path (which only fires when exactly one of `{DOB, marital}` is set on the user) was therefore unreachable.

### Fix landed on `onboardingFyn` (local only, not yet committed)

Three code files + one test file:

1. **`app/Services/AI/AiToolDefinitions.php`** — `capture_personal_details`:
   - `required: []` (both fields optional)
   - Description tightened: "CRITICAL: only include a field in the arguments when the user has EXPLICITLY stated it in their reply. Do not guess, infer, or default any field. … Omit a field entirely rather than inventing a value — the onboarding flow will re-ask for anything missing."
   - Per-field descriptions now each say "Only include this field if the user explicitly stated …".

2. **`app/Agents/CoordinatingAgent.php`** — `handleCapturePersonalDetails`:
   - No longer rejects when exactly one field is provided.
   - Still rejects with `error=true` when BOTH fields are empty (LLM called the tool with no captures).
   - DOB validation (format + age bounds 18–105) runs only when DOB is provided.
   - Marital enum validation runs only when marital is provided.
   - Saves only the field(s) present in the payload; the other field is untouched.

3. **`app/Services/Onboarding/OnboardingStateMachine.php`** — `nextFromPersonal`:
   - Added an early-return guard: if either `date_of_birth` OR `marital_status` is still empty on `$user`, return `STATE_BASE_PERSONAL`. The director then re-renders the same state; `buildPersonalPrompt` sees the partial state and emits the hybrid pre-confirm variant.
   - Comment added explaining why: "Needed because capture_personal_details now accepts partial payloads; without this guard a DOB-only reply would branch straight to base_dependants with marital_status still null."

4. **`tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php`**:
   - 5 existing `nextFromPersonal` tests updated to supply `date_of_birth => '1985-01-12'` (User factory defaults DOB to null, so without this they would now return `STATE_BASE_PERSONAL` and break).
   - 2 new tests added:
     - `'stays on base_personal when DOB is captured but marital is still null (FR-M10 partial)'`
     - `'stays on base_personal when marital is captured but DOB is still null (FR-M10 partial)'`

Regression run: `./vendor/bin/pest tests/Unit/Services/Onboarding/ tests/Unit/Agents/CoordinatingAgentHandleSetExpenditureTest.php tests/Feature/Onboarding/` → **160 tests pass**, 530 assertions, 0 failures.

### Verification — part A (DOB only)

- Fresh user `test-fr-m10-dob-4@example.com` (id 113 in this run), registered via "Quick start with Fyn" CTA → /register?from=fyn → OTP completion → dashboard with Fyn chat auto-opened.
- `Follow a journey → Protecting and growing → base_personal`.
- Typed: `"12 January 1985"`.
- **Fyn replied:** *"Got it — I have you down as born on 12 January 1985. Are you single, married, in a civil partnership, or have you been through a separation as a widow or widower?"*
- DB after turn: `users.date_of_birth='1985-01-12'`, `users.marital_status=NULL`, `users.onboarding_fyn_step='base_personal'` — state held, exactly as FR-M10 intends.

### Verification — part B (marital only)

- Fresh user `test-fr-m10-mar-1@example.com` (id 114), same journey path.
- Typed: `"I am married"`.
- **Fyn replied:** *"Thanks — I have you noted as married. Could you share your date of birth? Something like 12 January 1985 is fine."*
- DB after turn: `users.date_of_birth=NULL`, `users.marital_status='married'`, `users.onboarding_fyn_step='base_personal'` — state held, hybrid prompt fired.

**Verdict:** PASS.

---

## Test 3 — FR-M11 full walkthrough

Continued with user 114 (TestE, marital=married). Provided DOB to clear base_personal, then walked the rest of the journey.

| State | Input | Captured |
|-------|-------|----------|
| `base_personal` | "12 January 1985" | dob=1985-01-12 |
| `base_spouse` | "Her name is Maria, born 8 March 1987, email maria-fynm11-1@example.com" | spouse linked (family_member id 21, relationship=spouse) |
| `base_dependants` | bubble "No" | no dependants |
| `base_employment` | bubble "Employed" | employment_status=employed |
| `base_work` | "I work at Acme Corp as a Software Engineer earning £75,000 per year" | employer=Acme Corp, occupation=Software Engineer, annual_employment_income=75000 |
| `base_expenditure` | "About £4000 per month" | expenditure_profiles row 6, total_monthly_expenditure=4000 |
| `asset_capture` (selection=family) | "My dad Robert is 68, my mum Susan is 65." | no family_member rows created — UI `"The form for your family member didn't load in time"` toast appeared. Separate pre-existing bug, not in scope for FR-M11. |
| `add_more` | bubble "Savings" | transitioned to asset_capture (selection=savings) |
| `asset_capture` (selection=savings) | "I have a Nationwide instant access savings account with £12,000 in it, and a Cash ISA with Santander at £8,500." | two savings_accounts rows: Nationwide instant_access 12000.00, Santander cash_isa 8500.00 |
| `add_more` | bubble "I'm done" → first click was swallowed; re-typed "I'm done" and pressed Enter | `onboarding_completed=1`, `onboarding_completed_at=2026-04-20 11:48:09`, `onboarding_fyn_step=NULL` |

**Final state:** user landed on `/net-worth/cash` (matches the PRD expectation).

**Verdict:** PASS. One caveat worth documenting but not a FR-M11 regression:

- **Family member form-load race** — when the asset_capture (family) turn fires a `fill_form` action quickly after a prior tool-call-driven navigation, the front-end router occasionally reports "form didn't load in time" and the create_family_member call doesn't produce a row. Observed in Test 3 (mum/dad not persisted) and partially in Test 6 (mum not persisted). Suggest raising as a follow-up; the state machine transitions were unaffected in either case.

---

## Test 4 — FR-M12 post-onboarding expenditure sync

**Setup:** user 114, completed flow, Fyn chat new conversation.

**Action:** typed `"My rent is £1500 and utilities are £300"`.

**Result:**
- Fyn reply included: *"I've updated your expenditure details with rent at £1,500.00 and utilities at £300.00, giving a total monthly spend of £1,800.00."*
- URL navigated to `/valuable-info?section=expenditure`.
- DB:
  - `expenditure_profiles` row 6 (user_id 114): `total_monthly_expenditure=1800.00`, `updated_at=2026-04-20 11:49:10` (updated from 4000.00).
  - `users.id=114`: `monthly_expenditure=1800` (synced).

**Verdict:** PASS — both `ExpenditureProfile.total_monthly_expenditure` and `users.monthly_expenditure` wrote correctly, matching FR-M12's `updateOrCreate` intent.

---

## Test 5 — FR-M13 spouse email collision

**Setup:**
- User A = user 114 (TestE) with spouse `maria-fynm11-1@example.com` linked from Test 3.
- User B = `test-fr-m13-collide@example.com` (id 116), fresh via `?from=fyn`.

**Action:**
- User B at `base_spouse`, typed: `"My partner is Jamie, born 14 April 1988, email maria-fynm11-1@example.com"`.

**Result:**
- **Fyn emitted exactly:** *"That email's already registered with another Fynla household. Want to use a different address for your partner, or ask them to link their own account?"*
- `users.id=116.onboarding_fyn_step` stayed at `base_spouse`. State was preserved, not advanced.
- Next reply with a different email — `"Jamie, born 14 April 1988, email jamie-fynm13@example.com"` — succeeded:
  - New user row created: `users.id=117`, first_name=Jamie, linked as family_member with relationship=spouse.
  - State advanced to `base_dependants`.

**Verdict:** PASS. Terminal error copy matches PRD verbatim, state preservation works, retry path works.

---

## Test 6 — FR-M14 asset-capture off-script suppression: **FAIL**

### Setup
User 116 (TestF) walked to `asset_capture` with `onboarding_fyn_selection='family'`.

### Action
Typed exactly: `"My mum is 72"` (the PRD's stock input).

### What Fyn actually produced

```
Got it — recording those now.
Thanks, TestF — I've added your mum (aged 72) to your family profile
for protection and estate planning.

With your £50,000 gross income and £3,500 monthly outgoings, it looks
like you have capacity to build protection cover or start growing
savings.

Do you own your home or have a mortgage If so, what's the property
address, ownership share (e.g. 50% with spouse), and rough value
```

### PRD expectation (deploy-PRD-P0.md step 6)

> "Fyn records the family member via `create_family_member`. Output does NOT contain property/mortgage/rent/income questions. No `?` in Fyn's acknowledgment (it should be a statement or nothing — director may even suppress all content this turn)."

### Specific failures

1. **Property/mortgage questions appeared** — "Do you own your home or have a mortgage", "what's the property address", "ownership share", "rough value". These are exactly the follow-ups FR-M14 is meant to suppress when the current selection is `family`.
2. **Income follow-up appeared** — "With your £50,000 gross income and £3,500 monthly outgoings, it looks like you have capacity to build protection cover or start growing savings." This also mixes contexts.
3. **The `?`-strip filter was bypassed** — no literal `?` characters remain in the output. The LLM phrased questions without trailing `?` (e.g. "Do you own your home or have a mortgage If so, what's the property address …"). The director's content filter in `OnboardingChatDirector::handleAssetCaptureTurn` matches the character, not the semantic question, so the sentences passed through the queue-and-flush.
4. **Not a zero-tool-call turn** — the `create_family_member` tool call did fire, so the post-tool content was preserved by the filter's "preserve post-tool confirmations" branch.

### Failure mode locus

- LLM-side: the `assetCaptureInstructions` guardrail in `OnboardingPromptBuilder` is too soft for this bypass. The LLM is reading it as "don't ask questions with `?`" and rewriting the same questions without the punctuation.
- Director-side: `OnboardingChatDirector::handleAssetCaptureTurn` only filters on literal `?` plus the zero-tool-call branch. A tool-call-positive turn whose post-tool content contains off-script sentences passes through untouched.

### DB side-effect
No `family_member` row created for "mum" (similar to the Test 3 form-load race — not a FR-M14 issue). Only pre-existing `spouse` row (Jamie, id 24) remains for user 116.

### Suggested follow-up fix (not landed)

1. **Prompt tighten** — `OnboardingPromptBuilder::assetCaptureInstructions`: add a hard constraint along the lines of
   *"Your acknowledgment MUST be a single sentence of ≤15 words. Do not ask any question, with or without a question mark. Do not mention property, mortgage, rent, income, home, address, value, or any topic outside the current selection (`{selection}`). If you have nothing to say, return empty content."*

2. **Director-side sentence filter** — `OnboardingChatDirector::handleAssetCaptureTurn`: when `selection !== 'protection'` AND `selection !== 'estate'`, strip any full sentence (split on `.|!|\n`) whose lowercased text matches `/property|mortgage|rent|income|home|address|ownership|valuation/`. Run on post-tool content as well as zero-tool-call content — i.e. always.

3. **Add tests** — one unit test for `handleAssetCaptureTurn` that feeds a mocked LLM stream with `"Got it — recording those now. Do you own your home or have a mortgage"` and asserts only the first sentence survives.

### Impact assessment

- Happy path still works (family member recording, state transitions, add_more flow).
- UX leak: users see off-script follow-ups mid-family-capture. Low but real risk of confusion / data entry in the wrong context if the user answers them.
- Not a data-integrity issue — no bad writes happen from the stray content.

### Recommendation

Land the FR-M14 tightening as a small follow-up PR (separate from the current FR-M9..FR-M15 deploy). The fix is narrow and well-scoped. It should not block the `onboardingFyn → dev` merge-back for items 1–5 and 7; those are deployable as-is once FR-M6 fix (Test 2) is committed.

**Verdict:** FAIL. Documented for follow-up.

---

## Test 7 — FR-M15 Trust CLT orphan prevention

### Save case (step 5-6 of PRD Test 7)

**Setup:** signed in as `john@example.com` (user 108), fresh login via OTP. Before the test: `trusts.user_id=108` count = 0, `gifts.user_id=108 AND gift_type='clt'` count = 0.

**Action:** Fyn chat, typed `"Add a discretionary trust called Test Trust, initial value £100,000"`.

**Result:**
- Fyn saved the trust directly via `create_trust` (without opening a `fill_form` form — the LLM chose the direct path rather than the form-fill path, which is within the tool definitions' spec).
- URL navigated to `/trusts`.
- DB:
  - `trusts.id=3`: trust_name=`Test Trust`, trust_type=`discretionary`, initial_value=`100000.00`, created_at=`2026-04-20 12:05:24`.
  - `gifts WHERE user_id=108 AND gift_type='clt'`: **1 row**, `gift_value=100000.00` — created by `TrustObserver::created` as per FR-M15's moved ownership of CLT creation.

**Verdict:** PASS — observer-driven CLT creation works, gift_value matches.

### Cancel case (step 1-4 of PRD Test 7)

**Setup:** on `/trusts`, clicked the "Add Trust" button (the UI-driven path to the trust form). The existing `Test Trust` + 1 CLT remain from the save case as baseline.

**Action:** trust form opened (1 form visible, 10 fields, Cancel button present). Without filling or saving, clicked "Cancel".

**Result:**
- Form closed.
- DB:
  - `trusts.user_id=108` count = **1** (unchanged).
  - `gifts.user_id=108 AND gift_type='clt'` count = **1** (unchanged), total `gift_value=100000.00` (unchanged).

**Verdict:** PASS — cancelling the trust form produced no orphan CLT, because `TrustObserver::created` only fires on actual `Trust::created`, which didn't happen.

### Overall
Both branches pass. FR-M15 is working as designed.

---

## Code changes landed locally (NOT yet committed)

```
 M app/Agents/CoordinatingAgent.php                     (FR-M10 handler partial-capture)
 M app/Services/AI/AiToolDefinitions.php                (FR-M10 tool schema + description)
 M app/Services/Onboarding/OnboardingStateMachine.php   (FR-M10 nextFromPersonal guard)
 M tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php  (5 updated, 2 new)
```

These are the FR-M10 fix only. Pest regression: 160 onboarding tests, 530 assertions, 0 failures.

## Outstanding
- Commit the FR-M10 fix.
- Open a follow-up issue / PR for FR-M14 tightening (see Test 6 section).
- Address the "family member form didn't load in time" UI race (observed in Tests 3 and 6, unrelated to PRD P0 scope). Defer to a separate pass.
- After local commits, deploy to csjones.co/fynla per `April20Updates/deploy-PRD-P0.md` and re-run smoke tests on the dev server before the `onboardingFyn → dev` merge-back PR.

## Test artefact map

| User | Purpose | Test |
|------|---------|------|
| 113 (preview_young_family) | Preview block | 1 |
| 126 (test-fr-m10-dob-1) | First (divergent) DOB-only attempt; pre-fix | 2 — invalidated |
| 96 (test-fr-m10-dob-2) | Attempt during subscription_plans wipe | — invalidated |
| 112 (test-fr-m10-dob-3) | Attempt with stale session cookie | — invalidated |
| 113 (test-fr-m10-dob-4) | DOB-only, post-fix | 2 (part A) PASS |
| 114 (test-fr-m10-mar-1) | Marital-only, post-fix → full walkthrough | 2 (part B) PASS, 3 PASS, 4 PASS |
| 116 (test-fr-m13-collide) | Collision user B | 5 PASS, 6 FAIL |
| 117 (Jamie — spouse of 116) | Collision retry link | 5 |
| 108 (john@example.com) | Seeded completed user | 7 PASS |
