---
type: handover
mode: session-end
date: 2026-10-05
session: 4
repo: fynla
branch: dev
---

# Session Handover — 2026-10-05, Session 4

## Where things stand

Item 7a's steps 1, 2, 2b and 3 are done and merged to dev (#1082, #1084, #1085; dev `fb185bd8d`). Every one was walked on csjones, web 1440 and /m 390. NONE IS RELEASED: fynla.org is still main `fd2f7525b` (release n).

Only 7a step 4 is left (four smaller lines). When it is done, 7a is crossed off, and anything found after that becomes its own list item.

CSJ was frustrated mid-session that #1082 had built new entry points beside code that already did the job. The audit and cleanup (#1084) followed. CSJ also asked that `todoCurrent/TODO.md` is loaded at the start of every job and updated as each step lands (memory `feedback_todo_current_persistent_list`, updated).

## Priorities for the next session

1. **7a step 4: the smaller lines, in list order** (`todoCurrent/TODO.md`, item 7a, step 4):
   - **Demo "Mark as done"** is accepted but not kept ("Changes are session-only"). It needs demo session state.
   - **`TaxConfigurationFactory`** stores band, Capital Gains Tax and dividend rates as whole percentages (20, 18, 8.75) where the seeded years store fractions. Converting it touches every test built on it, so name those files when running them.
   - **Nothing records that a Marriage Allowance transfer has been made**, so the Income page cannot take the £252 off. CSJ 2026-10-04 restated that it must, so build it. First check the existing forms and records (memory `feedback_fyn_entry_uses_existing_forms`) and the law (Income Tax Act 2007 s55B).
   - **The Holistic Plan page has its own ranked list** (`HolisticPlanningController`, `orchestrateAnalysis`), not the actions list.
   - Then cross 7a off with evidence.
2. **Release #1082, #1084 and #1085 when CSJ says.** Never recommend deploying.
   - The release carries app code plus the web bundle (#1084 changed `resources/js/store/modules/aiChat.js`). No migration, seeder or /m change.
   - Walk fynla.org afterwards and purge the walk account.
3. **Item 8: the Investment review, then its how-tos.** Then item 8a (smoker and health status).

## Context to load

- `todoCurrent/TODO.md` — the order of work. 7a step 4 is the current line; the 2b "Found" line (investment walk copy) sits under it.
- `docs/tech-debt-report.md` — top section (2026-10-05 session 4): 4 open items.
- `app/Services/Onboarding/OnboardingChatDirector.php` — `offerTypedForm` is the one door from typed words to a form. Read it before touching Fyn capture, so it isn't duplicated again.
- `handover/October/05/handover-2026-10-05-session-3.md` — context on #1082. Its tech-debt section is superseded by session 4.

## Completed this session (session 3 work is in its own handover)

- **The audit (CSJ: "do a full audit… you are doing redundant actions").** #1082 had added four typed-change entry points beside two that existed, plus a second unlock wording table, a second form emitter, a repeated empty-form block and a second record-type map. The real fixes in #1082 (the hand-off tool pool, the demo guard, adds through forms) were checked and kept.
- **#1084, merged dev `92e2b9302`.** Same behaviour, about 40 fewer lines:
  - `767778790`: `offerTypedForm` is the one door. A choice between records carries the answers already read (`typed_fills`). `emitFormMessage` draws blank forms. `emitFormProblem` is the one error reply. `RecordEditForms::createFormsFor` maps sections to blank forms. Unlock prompts reuse `strategyUnlockPrompt`.
  - `2b9deda8a`: "I want to change my savings details" now matches a record type; `WriteIntentClassifier` gained section words.
  - `e711c147b`: on web, tapping a record under "Which one needs changing?" did nothing. `aiChat.js postAction` had its own five-action list; it now uses the server's pattern. This bug predates #1082.
- **#1085, merged dev `fb185bd8d`, 7a step 3 (CSJ: "choice a is good").**
  - On Save Tax, "No thanks" now declines only the accounts and pensions (`declined_asset_questions`, read by `funnelHasAnyAsset`). Fyn still asks date of birth and gender, the spouse, and spending.
  - The closing lines say "Save Tax" (`OnboardingStateMachine::selectionLabel`).
- **TODO.md:** 2b and 3 struck through with evidence; CSJ's answer recorded.

## Verification state

- **Tests (named files only):**
  - #1084: Pest about 380 passed; vitest `aiChatEditActions.test.js` plus the web chat specs passed.
  - #1085: Pest `CampaignStateMachineBranchTest` 29 plus 172 onboarding tests passed.
  - New tests failed on the old code before each fix.
  - CI was not watched; the full suite was not run.
- **#1084 on csjones, branch then dev:**
  - **Web 1440 (account 480):** Santander £6,800 saved via its form; Chase added via the filled blank form; the savings choice opened the Chase form.
  - **/m 390 (account 480):** "Both my easy access accounts pay 4.2% now" gave a choice, and Santander opened with 4.2% filled and saved; the "Edit details" date of birth was saved; Add investment account saved Vanguard £12,000.
- **#1085:**
  - **Local web (new account 128):** "No thanks", then date of birth, then spending, then the plan.
  - **csjones /m 390 (new account 481):** the same path; date of birth 1979-09-02, male and £3,100 saved; no "savetax" in the conversation.
  - **Not walked:** csjones web for #1085. The local web walk covers it; the server path is the same.
- **Not verified:** iOS (no forms, CI only). A demo persona's form refusal in a browser (tests only).

## Decisions and dead ends

- **CSJ 2026-10-05, "choice a is good":** "No thanks" skips only the accounts and pensions questions.
- **CSJ 2026-10-05, cleanup:** "clean it up now", then "make sure you are always loading the main todo list and updating it".
- **Don't add entry points beside existing ones.** Typed text to a form goes through `OnboardingChatDirector::offerTypedForm` only. A new caller passes the records it may be about (`candidates`) and any blank forms (`createForms`).
- **The spouse section is still asked after "No thanks".** The question named accounts and pensions, not the spouse's income.
- **Rule 6 rewording was tried and reverted (session 3).** It made no difference to the model's refusals; the deterministic pre-check is the fix.

## Things that will bite you

- **Vitest also runs the abandoned worktrees** (`.claude/worktrees/agent-*`). Their `AiChatPanel` failures are old code, not this repo's.
- **csjones `public/build.old` holds the previous web bundle.** It was kept so open sessions survive; remove it after about 24 hours.
- **The local `public/build` holds a csjones build,** from `./deploy/csjones-fynla/build.sh`.
- **Walk accounts:** local 126, 127 and 128; csjones 480 and 481. All use `Password1!walk`. Purge the csjones ones with item 37.
- **Local walk account 125 still has employment income £70,000** (was £30,000) from a live probe in session 3. Reset it through the web Income form before relying on its figures.
- **CSJ's uncommitted files:** two excalidraw diagrams, the 30 September handover and two workforce logs. Never stage them.

## Tech debt deferred

From `docs/tech-debt-report.md` (2026-10-05 session 4):
- A model call runs before every non-question advice message (`AdviceFyn::offerTypedChangeForm`).
- The `<security>` block is written twice, and rule 6 has drifted (`FynSystemPrompt`, `CoreIdentity`).
- The investment form's "save with none chosen" copy shows on an Add button's blank form (also a 2b "Found" line).
- `OnboardingChatDirector.php` is about 9,350 lines.

## Branch and deploy state

- **Branch:** `dev` at `fb185bd8d`, clean apart from CSJ's own files.
- **Unpushed commits:** none before this handover commit.
- **fynla.org:** main `fd2f7525b`. #1082, #1084 and #1085 are not released.
- **csjones:** dev `fb185bd8d`, with the web bundle from #1084's build. The /m bundle is unchanged.
