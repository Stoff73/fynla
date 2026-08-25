# Browser test — multi-entity capture across all Fyn tools

**Date:** 22 April 2026, session 2
**Branch:** `feature/fyn-persona-split`
**Scope:** Phase D1 of `plan-multi-entity-capture.md` — 14-row browser test matrix.
**Environment:** localhost:8000, xAI provider (`AI_PROVIDER=xai` in `.env`), full seed fresh.

---

## Ground rules for this log

- **Every Fyn reply is captured verbatim.** Training-grade log — no paraphrasing.
- **Every tool call + DB verification is recorded.** If Fyn emits 2 tool_use blocks, both are noted; if only 1, bug is flagged.
- **Issues + fixes are captured inline.** If any test fails mid-run and we fix it, the fix goes in the "issues + fixes" table at the bottom AND the test is re-run with the new result appended.
- **No paraphrasing. No summary claims of success.** If it isn't pasted here verbatim, it didn't happen.

## Pre-flight

- Database seeded. Users: 14 total, 9 preview, 5 real (chris + john + jane + sarah + 1 other).
- Dev server running: `artisan serve --port=8000` (PID 37626). Vite on `:5174`.
- `FYN_PERSONA_SPLIT` flag — checked below per test (onboarding runs regardless of flag; post-onboarding needs flag=true for orchestrator path).
- Test user reset: `john@example.com` / `password`, id=129, `onboarding_completed=0`, clean state.

## Tests

Each test row below follows the structure:
```
### Row N — <module> — <path>
**Bug scope:** (within-tool | cross-tool | regression guard)
**Path used:** (onboarding asset_capture | post-onboarding data_capture | onboarding set_expenditure | onboarding multi-job loop)
**User input:** "..."
**Fyn response (verbatim):** "..."
**Tool calls emitted:** (list)
**DB verification:** (query + result)
**Result:** PASS | FAIL (+ detail)
```

---

## Walkthrough — Fresh registration via `/register?from=fyn` (per PRD §6 flow diagram)

**User:** Testuser Alpha (`testalpha@example.com`, Password1!), fresh registration, user_id auto-assigned.
**Verification code:** 751729 (fetched from `pending_registrations` table).
**Layout:** wide-chat (712px, double 356px) right-docked, dashboard blurred with `filter: blur(4px) pointer-events-none`, `isOnboardingActive=true`.

### Turn 1 — path_choice (state: path_choice)

**Entry:** `/register?from=fyn` → verification code accepted → `/dashboard` with Fyn onboarding stream running.

**Fyn (verbatim, 1 message streamed):**
> "Hi Testuser, I'm Fyn — welcome to Fynla. I'll help you set up your financial plan. To start, do you want to follow a life-stage journey or pick a single module focus?"

**Quick reply bubbles rendered:** `Follow a journey` | `Pick a focus`.

**Screenshot:** `April/April22Updates/step1-after-register.png`.

**Status:** PASS — welcome message + both bubbles rendered in wide-chat (712px) with dashboard blurred per FR-M21.

### Turn 2 — journey_choice (state: path_choice → journey sub-state)

**User action:** Clicked `Follow a journey` bubble.

**Fyn (verbatim):**
> "Which journey fits your situation best?"

**Quick reply bubbles rendered:** `Starting out` | `Building foundations` | `Protecting and growing` | `Planning your future` | `Enjoying your wealth`.

**Status:** PASS — 5 journey bubbles rendered as spec'd (PRD flow diagram line 233 path_choice).

### Turn 3 — base_personal (state transitioned after journey selection)

**User action:** Clicked `Protecting and growing` bubble.

**Fyn (verbatim):**
> "Let me grab a few basics first, Testuser. What's your date of birth, and are you single, married, in a civil partnership, divorced, or widowed?"

**No bubbles.** Free-text input expected.

**Status:** PASS — advanced to base_personal asking for DOB + marital_status per PRD Scenario 2 step 2 (*"Fyn asks: 'Could you tell me about yourself — date of birth, marital status?'"*).

### Turn 4 — base_personal first user reply

**User (verbatim):** "My date of birth is 12 March 1985 and I'm married."

**Fyn (verbatim):**
> "Thanks — I have you noted as married. Could you share your date of birth? Something like 12 January 1985 is fine."

**DB state after this turn (users id=143):**
- `marital_status = married` ✅
- `date_of_birth = NULL` ❌ (the DOB part of the answer was not captured)
- `onboarding_fyn_step = base_personal` (still on step — Fyn is asking again)
- `onboarding_fyn_selection = protection` ✅

**Issue flagged (not the multi-entity bug, but related — single-message partial capture):** The user volunteered BOTH DOB and marital_status in one message. Fyn captured only marital_status and dropped the DOB, then asked again. This matches the CSJTODO open item "LLM drops fields when multi-field answer is given" and is adjacent to the multi-entity fix. Noting but continuing — the multi-entity scope is *records*, not *fields within a single record*.

**Status:** PARTIAL — marital_status captured, DOB dropped. Continuing with DOB supplied separately.

### Turn 5 — base_personal DOB retry

**User (verbatim):** "12 March 1985"

**Fyn (verbatim):**
> "Great — let's add your spouse's details. Can you share their first name, date of birth, and email address? I'll create an account and link the two of you so you can plan together."

**DB state:** `date_of_birth = 1985-03-12`, `onboarding_fyn_step = base_spouse`, advanced correctly.

**Status:** PASS — DOB captured when supplied alone, state advanced to base_spouse per PRD flow.

### Turn 6 — base_spouse

**Skip link rendered:** `Skip this for now` (raspberry, inline, per FR-M16). ✅

**User (verbatim):** "Her name is Angela, date of birth 15 June 1987, email angela@example.com"

**Fyn (verbatim):**
> "Any children or dependants to add?"

**Quick reply bubbles:** `Yes` | `No`.

**DB state after this turn:**
- New user id=144, `first_name=Angela`, `email=angela@example.com`, `date_of_birth=1987-06-15` ✅
- `family_members` row id=21 linking Testuser (user_id=143) → Angela (linked_user_id=144) with `relationship=spouse` ✅
- `onboarding_fyn_step = base_dependants` ✅

**Status:** PASS — spouse captured with all 3 fields from a single multi-field message (no drop this time), spouse user auto-created, family_member link inserted, state advanced. This is the multi-FIELD single-record case working correctly.

