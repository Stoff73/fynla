---
type: handover
mode: context-clear
date: 2026-10-08
session: 2
branch: feat/17a-openai-provider
trigger: context-handover skill (CSJ asked for a logical stop)
---

# Context Clear Handover — 2026-10-08, Session 2

## Immediate state

Mid-fix of the savings **edit form read-back** inside the full GPT-6 Luna joint run: `tests/Unit/Services/Onboarding/SavingsEditFormReadBackTest.php` is written and all 3 tests fail; no fix written yet. Jordan (invited partner, user 143) is part-way through onboarding on /m.

## The thread

- **Item 17a built** (PR #1148, `feat/17a-openai-provider`, `fed65fd7f`, not merged):
  - OpenAI GPT `gpt-6-luna` is a third admin AI provider; `AiProvider` is the one home.
  - Every OpenAI call sends `store: false` (CSJ: "NEVER retain data"). The stored completions were deleted (0 left).
  - Local is ON OpenAI and must stay there (CSJ: "keep it on luna, do not revert back to xAI").
- **The Decisions API** (CSJ asked) does not suit Fyn: no streaming, tools, history, system prompt or free text.
- **CSJ asked for a full Luna run.** CSJ answers: web and /m locally now; the iPhone after release, with CSJ signing in on the phone.
  - **Single run (Robin, user 141): green.** Every figure was checked by hand. Fyn answers ran on gpt-6-luna.
  - **Joint run** (Sam 142 inviter, Jordan 143 invitee): in progress; see TODO.
- **Found and fixed: the savings-allowance step in the pension working.** It was introduced today by item 18's `1d9bf05fb`. PR #1149 (`fix/18-psa-working-line`), not merged; walked: Fyn now names the £480 covered.
- **Found and fixing: the joint-owner duplicate** (`fix/joint-owner-duplicate-guard`, WIP `65456c2a5`, pushed).
  - The invited partner could save a second copy of the inviter's joint account.
  - CSJ chose: widen the existing `RecaptureGuard`, which asks "a separate … or the same one?" and never refuses.
  - The widening then caused Jordan's "It's the same one" → `update_record` → "Record not found or unauthorized". Root cause: the guard returned a record the user does not primary-own.
  - Fixed with a no-change `update_record` shortcut and no guard writes to the partner's record. Tests pass.
- **CSJ corrections this session, do not repeat:**
  - Never report a bug without research, citation and proof.
  - Fix issues found in the path; do not list them and move on.
  - Debug with `superpowers:systematic-debugging`.
  - Open invitations the way it has always been done: `/register?invite=<token>` from `SpouseInvitation`.
  - Withdrawn non-bugs:
    - the "/m sign-out leaves the session" finding was my probe sending the session cookie, which the `verify-m` skill (line 10) warns about;
    - "A pension" not ticked by typed fill is by design (`TypedFormFill.php:102-103`);
    - the "years to retirement", "Joint for single" and cache findings were not bugs.

## Files touched this session

- **PR #1148** (17a): `app/Services/AI/AiProvider.php` (new), `XaiClient`, `HasAiChat`, `HasAiGuardrails`, `Planner`, `AdviceFyn`, `AiToolDefinitions`, `OnboardingChatDirector`, `AIExtractionService`, `DocumentProcessor`, `TypedFormFill`, `ConversationSummariser`, `ProposedFactSynthesiser`, `EvalDeltaBuilder`, `AdminController`, `config/services.php`, `config/ai_pricing.php`, `AiSettings.vue`, `tests/Feature/AI/OpenAiProviderTest.php`.
  - All 17a work is in `fed65fd7f`, checked at handover: `git diff HEAD` in the checkout shows only the #1149 and joint-fix copies.
- **PR #1149**: `TaxStrategyMath`, `PensionTaxReliefStrategy`, `PensionTaxReliefStrategyTest`. The same changes are also uncommitted in the main checkout, so the running app has them.
- **WIP `fix/joint-owner-duplicate-guard`**: `RecaptureGuard`, `CoordinatingAgent` (update_record no-change shortcut), `CreateSavingsAccountTest`, `SavingsEditFormReadBackTest`. The same changes are also uncommitted in the main checkout.

## WIP commit

- `65456c2a5` on `fix/joint-owner-duplicate-guard`, pushed.
- The main checkout (`feat/17a-openai-provider`) deliberately keeps the uncommitted copies of #1149 and the joint fix, so the local app runs with everything. CSJ's own uncommitted files (excalidraw, Sept 30 handover, workforce logs, untracked folders) were NOT touched.

## Open decisions

- None waiting on CSJ.
- CSJ still to do:
  - re-authenticate Gmail (`/mcp`);
  - add `OPENAI_API_KEY` to csjones `.env` (then prod `.env` at release).

## Pick up from here (auto-continue contract)

Current item: **17a** (todoCurrent/TODO.md). Read its "Where we stopped" line.

1. **17a is fully committed** (`fed65fd7f`, PR #1148). Nothing to do there until csjones has `OPENAI_API_KEY`.
2. **Edit read-back fix** (on `fix/joint-owner-duplicate-guard`, systematic-debugging):
   - **First** find why the owner's real change fails. `RecordEditForms::update` with `answers.easy_access.current_value = 25000` returns "Validation failed for account update." Trace `recordFields('savings_account')` → `update_record` → `SavingsStore::update` validation.
   - Then make the read-back truthful:
     - no change must not say "Updated" (SPEC-crud-handler-contract C5, `August/August17Updates/SPEC-crud-handler-contract.md`);
     - ownership must come from the stored record, not `CaptureForms::toolInputs`' `individual` default (`CaptureForms.php:494`; the edit form drops ownership fields in `RecordEditForms::editSchema`).
   - Run `./vendor/bin/pest tests/Unit/Services/Onboarding/SavingsEditFormReadBackTest.php tests/Feature/AI/DirectWrite/CreateSavingsAccountTest.php` plus the guard and update_record files (listed in TODO).
3. **Finish Jordan's onboarding on /m** (iPhone browser), as a user:
   - bank accounts check → pension (Nest) → spending → plan;
   - check Jordan's and Sam's figures against the database;
   - confirm the joint account is one row (278).
4. **Then:** squash the WIP, PR the joint fix to dev, and walk it again. #1148 and #1149 await csjones (needs the key), then release, then the iPhone (CSJ).

## What the next Claude needs to know

- **Being the iPhone in Playwright:**
  - run CDP `Network.setUserAgentOverride` (iPhone Safari) + `Network.setCacheDisabled` via `browser_run_code_unsafe`, then open `http://localhost:8000/`;
  - the desktop page was cached without `Vary: User-Agent`, so caching must be off;
  - a cookie consent dialog appears after clearing cookies: Decline → "Continue Without Cookies".
- **/m page details:**
  - /m pages are inside `iframe[title="Fynla"]`, so use `iframe[title="Fynla"] >> internal:control=enter-frame >> …`;
  - the full-screen Fyn input is `input[placeholder="Ask Fyn anything..."] >> visible=true`; the docked one is `#mc-fyn-input`.
- **Verification codes:**
  - registration codes are in `pending_registrations.verification_code` (`getRawOriginal`);
  - sign-in codes are in `email_verification_codes` (order by id desc).
- **Accounts:**
  - walk accounts, all with password `Password1!`: Robin 141 (`slaterjoneschris+luna-a@gmail.com`), Sam 142 (`+luna-b`), Jordan 143 (`+luna-c`);
  - Sarah 13's Marcus walk row was removed.
- **The edit form's savings balance field is `current_value`**, not `current_balance.`
- **Fyn replies on /m can show "…" for about a minute** while the server finished in about 16 seconds. That was not proven to be an app issue (CDP Network domain enabled); do not report it without proof.

## Branch / deploy state

- **Branch:** `feat/17a-openai-provider` (PR #1148).
- **Also pushed:** `fix/18-psa-working-line` (PR #1149) and `fix/joint-owner-duplicate-guard` (WIP).
- **Deploy status:** nothing deployed. csjones and fynla.org are unchanged and still on xAI.
