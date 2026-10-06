---
type: handover
mode: session-end
date: 2026-10-05
session: 3
repo: fynla
branch: dev
---

# Session Handover — 2026-10-05, Session 3

## Where things stand

7a steps 1 and 2 are built, merged to dev (#1082, dev `38e05f938`) and walked on local and csjones (web 1440 and /m 390). They are NOT released: fynla.org is still main `fd2f7525b`. 7a step 3 is CSJ's open question (asked at the start of this session, no answer yet), and step 4 (four smaller lines) has not started. When 3 and 4 are done, 7a is crossed off. Anything found after that becomes its own list item, never another line under 7a.

## Priorities for the next session

1. **DECISION (CSJ), ask first if still unanswered: Save Tax "No thanks".** After income, Fyn asks "can I ask about your bank and savings accounts?". "No thanks" ends the whole setup (`OnboardingStateMachine::nextFromCampaignIntro` → `STATE_DONE`), so date of birth, gender, pension and spending are never asked.
   - Recommendation given to CSJ: skip only the subjects asked about, and carry on with the rest of the setup. This follows CSJ's rules that spending is always asked (2026-09-30) and gender must be recorded (2026-10-04).
   - New evidence (local 127, csjones 480): after "No thanks" the plan offers "Pay £7,700 more into your pension and save £3,080" with no spending asked.
   - Whatever the answer, fix the closing lines "Your savetax dashboard" / "Your savetax module is ready to explore" (`fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md:569`, `{selection}` prints the slug).
2. **7a step 4: the smaller lines, in list order.**
   - Demo "Mark as done" is accepted but not kept; it needs demo session state.
   - `TaxConfigurationFactory` stores whole-percentage rates (20, 18, 8.75) where the seeded years store fractions. Converting it touches every test built on it, so name those files.
   - Nothing records that a Marriage Allowance transfer has been made, so the Income page cannot take the £252 off. CSJ 2026-10-04: "does not make sense", restated, so build it. Check the existing forms first (memory `feedback_fyn_entry_uses_existing_forms`).
   - The Holistic Plan page has its own ranked list.
   - Then cross 7a off with evidence.
3. **Release #1082 when CSJ says.** App code only: no migration, no seeder, no bundle (no frontend files changed). Walk fynla.org afterwards, then purge the walk account (memory `feedback_clear_cjones_prod_account_after_every_fix`). Never recommend deploying; CSJ decides.
4. **Item 8: the Investment review, then its how-tos.** Then item 8a (smoker and health status).

## Context to load

- `todoCurrent/TODO.md` — order of work. 7a's NEXT block: steps 1 and 2 are struck through with evidence; step 3 now carries the walk evidence.
- `docs/tech-debt-report.md` — top section (2026-10-05 session 3), 8 items from #1082.
- `app/Services/Onboarding/OnboardingChatDirector.php:7513-7800` — the typed-form entry points built this session (`createFormOffered`, `chooserTypedChange`, `offerTypedChangeAnywhere`, `offerTypedChangeIn`, `emitCreateForm`, `handleCreateFormTurn`, `formSchemaFor`, `walkForm`, `offerTypedAnswerForm`), plus `writeFormRecords` at `:4181`.
- `app/Services/Onboarding/TypedFormFill.php` — reads several forms in one xAI call; its prompt's rules decide record versus blank form.

## Completed this session

- **#1082 merged dev `38e05f938`** (`c8381bf80`, `944cce311`, `8ebed5b38`):
  - **Step 1, setup forms:** a typed answer at a setup form step fills that step's form (choosing the kind: easy access, workplace pension) instead of saving from the text.
  - **Step 1, plain-chat changes:** a change stated in chat is read against every saved record in one call (`offerTypedChangeAnywhere`, run in `AdviceFyn` before the model) and opens that record's form. Several matches give a choice, and the change is filled in on the one chosen; this also closed the "change my …" chooser gap.
  - **Step 1, root cause:** Advice Fyn was never offered `delegate_to_capture`. `HasAiChat`'s allowed-tools pool left out `handoffTools()`; the fix is at `HasAiChat.php:~497`. The test is `AdviceOffersHandoffToolTest`, which uses the new `ScriptedAnthropicMessages::$calls` record.
  - **Step 2, adds:** an add opens the blank form for that record (`CaptureForms::createFormFor`), filled in from the message. A form saved outside the walk is created through `writeFormRecords`, which was pulled out of `handleFormTurn`. `AiChatController` now routes every posted form to the director.
  - **Step 2, Add buttons:** Add buttons open the blank form (`ContextualConversationService`, `CaptureForms::ADD_PROMPT`).
  - **Step 2, unlock cards:** they ask for the gate's first missing item. `PrerequisiteGateService` passes the readiness `key`, and `RecommendationRouting::unlockPrompt($module, $missingKey)` maps date of birth, marital status, income and expenditure.
  - **Fixed in the path, demo personas:** six `capture_*` tools and `RecordEditForms::update/delete` refuse a demo persona (`RecordEditForms::DEMO_MESSAGE`). Before, a /m demo's "Save changes" overwrote the shared persona.
- **TODO.md:** 7a steps 1 and 2 recorded; the step 3 evidence and an item 35 note added. **Tech-debt report:** top section.

## Verification state

- **Tests (named files only):**
  - New: `TypedFormFillTest` 6, `TypedAnswerFillsFormTest` 6, `TypedChangeFillsFormTest` 4, `AdviceOffersHandoffToolTest` 1, `PreviewCaptureToolsBlockedTest` 7, plus unlock and Add-button cases.
  - Each new test failed on the old code; this was checked by restoring the old file.
  - Related named sets: 237, 401 and 175 passed. The full suite was not run (the hook blocks it; CI runs it, not watched).
- **Local walks (web 1440 and /m 390):**
  - New Save Tax account 126: the work, bank, date of birth and pension typed answers each filled the form and saved only on Save.
  - Account 125: "My Walk Bank balance is £21,500 now" (web) and "My date of birth is actually 4 August 1960" (/m) opened the forms; Chase was added via a filled form.
  - New account 127: the /m unlock card opened the personal form; /m Add bank account saved Barclays.
- **csjones walks (web 1440 and /m 390):**
  - Account 459: Nationwide £1,750 was saved via its form; Marcus £5,000 at 4.4% was added via a filled form.
  - New account 480: the setup work form filled from typed text; the /m unlock card opened the personal form and saved the date of birth; /m Add bank account saved Santander.
  - No errors in the csjones log tail. csjones is back on dev `38e05f938`.
- **Not verified:**
  - iOS (no forms, CI only).
  - A demo persona's form refusal in a browser (tests only).
  - fynla.org (not released).

## Decisions and dead ends

- **Rule 6 rewording was tried and reverted.** Two rewordings of rule 6 in `FynSystemPrompt` (an attack is a message changing Fyn's instructions; a message about the user's own money never is) made no difference in live probes: "Walk Bank" statements were still refused 4 of 4. The file says security wording must stay stable. The deterministic pre-check is the fix instead. The refusal seems to come from statements about an account the model cannot see in its context.
- **`TypedFormFill` needed an explicit rule** that a new record goes on the blank form. Without it, grok-4.3 returned `{"forms": {}}` for "I have a Chase easy access savings account with £2,000" when a saved account's form sat beside the blank one.
- **Consolidating the security block** (`FynSystemPrompt::SECURITY` for `CoreIdentity`) was reverted to keep #1082 focused. It is in the tech-debt report.
- **Advice text before a hand-off still shows** (for example "Could you confirm those details?" before the form) on the model's `delegate_to_capture` path. The deterministic pre-check avoids it for changes to saved records.

## Things that will bite you

- **Local walk account 125 was changed by a live probe.** Its employment income is now £70,000 (was £30,000) after a probe's capture turn wrote it before the forms-only add path existed. Reset it through the web Income form before relying on its figures. It also now has Chase (id 272), Walk Bank at £21,500 and a date of birth of 1960-08-04.
- **New walk accounts:**
  - local 126 (`walk-7a-step1-2026-10-05@example.com`, mid-setup at `campaign_pension_more`);
  - local 127 (`walk-7a-step2-2026-10-05@example.com`);
  - csjones 480 (`walk-7a-forms-2026-10-05@example.com`).
  - All use `Password1!walk`. Purge csjones 480 with the housekeeping item 37.
- **The local xAI call is slow** (up to minutes in tinker), while csjones takes about 1 to 2 seconds. Probe scripts are in this session's scratchpad (`probe.php`, `fill.php`).
- **The local `/m` bundle was rebuilt** this session (`npm run build:mobile`). The local `public/build` still holds a csjones build.
- **CSJ's uncommitted files** (two excalidraw diagrams, the 30 September handover, two workforce logs): never stage them.

## Tech debt deferred

From `docs/tech-debt-report.md` (2026-10-05 session 3):
- The filled-form line is written twice (`OnboardingChatDirector.php:1229`, `:7631`).
- The empty-form error block is repeated in `handleFormTurn` and `handleCreateFormTurn`.
- `offerTypedChangeAnywhere` makes one xAI call before every non-question advice message.
- The chosen record's form is read twice after a choice.
- The `<security>` block is written twice, and rule 6 has drifted in `CoreIdentity`.
- `offerTypedChangeIn` takes seven parameters.
- "Add my monthly spending" with spending on file opens the blank form.
- The director file is now 9,402 lines.

## Branch and deploy state

- **Branch:** `dev` at `38e05f938`, clean apart from CSJ's own files.
- **Unpushed commits:** none before this handover commit.
- **fynla.org:** main `fd2f7525b` (release n). #1082 is not released.
- **csjones:** dev `38e05f938` (backend; bundles unchanged since #1074's build).
