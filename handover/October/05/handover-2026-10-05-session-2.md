---
type: handover
mode: session-end
date: 2026-10-05
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-10-05, Session 2

## Where things stand

Item 7a's income work and five follow-up fixes are released and walked on fynla.org: releases j to n (#1071, #1073, #1075, #1077, #1079), main `fd2f7525b`. Item 7a is still open. Its last four steps are now written at the top of it in `todoCurrent/TODO.md`; when they are done, 7a is crossed off, and anything found after that becomes its own list item.

CSJ ended the session angry: "WHY has item 7 and 8a not been completed ... this has been going on for a week". Item 7 is done (2026-10-02). 7a never closed because it had no end point, and every release walk added new lines under it. 8a waits on item 8 by CSJ's own 2026-10-02 note, and item 8 never started because 7a came first. Also, the session-start hook never showed 7a or 8a: it matched only `N. [ ]`. That is fixed this session.

## Priorities for the next session

Work 7a's NEXT steps in order, then 8, then 8a. Do not add new lines under 7a. Anything new becomes its own numbered list item.

1. **DECISION (CSJ), ask first: Save Tax "No thanks".** The question after income asks only "can I ask about your bank and savings accounts?". "No thanks" ends the whole setup (`OnboardingStateMachine::nextFromCampaignIntro` → `STATE_DONE`, `app/Services/Onboarding/OnboardingStateMachine.php:2552-2558`), so date of birth, gender, pension and spending are never asked. Should it skip only savings, or end the setup as now? The closing lines also read "Your savetax dashboard" and "Your savetax module is ready to explore". Ask this in plain words at the start, then carry on with step 2 below.
2. **7a step 1: every typed change opens its form, filled in** (CSJ option A, released in #1079). Still uncovered:
   - **Typed changes Advice Fyn never routes:** anything outside an "Edit details" conversation that doesn't use wording like "change my …". Today these get a typed answer, and the `CertaintyFilter` backstop catches a direct claim but not a list of the new value.
   - **Typed answers on onboarding form steps:** still saved straight from the text (`OnboardingChatDirector.php`, "A form turn answered with typed text", `handleAssetCaptureTurn`).
   - **How:** reuse `TypedFormFill` plus `emitFormMessage`.
3. **7a step 2: unlock prompts and "add" through forms.**
   - **Wrong prompt:** the /m "Date of birth is required" card sends "Help me add my pension details". `RecommendationRouting::unlockPrompt` works per module, while the card's line is the gate's first missing item.
   - **"Add" requests get typed questions:** `runInlineCapture` opens forms for edits only, and a `ContextualConversationService` "add" has no form.
   - **Surfaces:** web, /m and iOS cards (iOS has no forms yet).
4. **7a step 4: the smaller lines.**
   - Demo "Mark as done" is accepted but not kept.
   - `TaxConfigurationFactory` stores whole-percentage rates.
   - Nothing records a Marriage Allowance transfer.
   - The Holistic Plan page has its own ranked list.
   - Then cross 7a off, with evidence.
5. **Item 8: the Investment review, then its how-tos.** 24 definitions in `database/seeders/InvestmentActionDefinitionSeeder.php`. Follow the same shape as item 7 (`docs/superpowers/specs/2026-10-01-retirement-cards-review-design.md`).
6. **Item 8a: smoker and health status.** `users.smoking_status` and `health_status` already exist from the old onboarding, and /m Personal Information has a "Health and lifestyle" form (`resources/mobile/views/PersonalInformation.vue`, `healthForm`). The enhanced annuity card reads `protection_profiles` instead. Find every reader and writer and settle on one home first.

## Context to load

- `todoCurrent/TODO.md` — the order of work. Read 7a's NEXT block (right under the 7a title) and the 8a "Asked" line.
- `docs/tech-debt-report.md` — top section (2026-10-05 session 2), 8 items from this session.
- `app/Services/Onboarding/TypedFormFill.php` — the form-fill engine that steps 1 and 2 reuse.
- `app/Services/Onboarding/OnboardingChatDirector.php` — `offerTypedChangeForm`, `emitEditForm`, `emitFormMessage` (~`:7473-7560`), and `runInlineCapture` (~`:8210`).
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_only_real_decisions_in_plain_words.md` — how to ask the one decision.

## Completed this session

- **#1069 (released j):** /m Expenditure "Edit details" opens the spending form, chosen by how the spending was entered.
  - Fyn's one-box save goes through `HouseholdExpenditureWriter`.
  - One sharing test, `dividesFor`.
  - One category sum, `UserProfileService::categorySpendingTotal`; /m shows the server's rows.
- **#1070 (released j):** no "Ask Fyn about this" or "Get more recommendations" in a web demo.
- **Release j #1071:** #1060, #1063, #1065, #1069, #1070, migration `2026_10_04_000001`, `TaxConfigurationSeeder`, corpus, both bundles.
- **#1072 (release k #1073):** the web Expenditure form read `/properties` as a list (the API answers `data.properties`), so it counted a homeowner's utilities. The view row now shows the server figure.
- **#1074 (release l #1075):** /m Personal Information "Edit details" opens the personal form (date of birth, gender, marital status).
  - /m folds a capture confirmation into the streamed reply.
  - The web record card no longer repeats the reply; both stream paths use one helper, `pushCaptureComplete`.
- **#1076 (release m #1077):** `CertaintyFilter` drops sentences in the read-only advice state that claim a save, and replaces the first with "That has not been saved yet."
- **#1078 (release n #1079):** CSJ option A, a typed change fills in the record's form.
  - `TypedFormFill` reads values through xAI JSON mode and checks them with `CaptureForms::fieldRules`.
  - Entry points: `AdviceFyn::offerTypedChangeForm` for "Edit details", and `emitEditChooser` for "change my …".
- **#1080:** patch notes (`October/October1Updates/patch-notes-2026-10-01.md` + PDF, 5 October section) and list updates.
- **Session-start hook** (`.claude/hooks/handover-priorities.sh`): lettered items (7a, 8a) now count, the current item is capped at 14 lines, and answered decisions are dropped. Committed with this handover.

## Verification state

- **fynla.org (all walked):**
  - **Release j:** the Mitchell demo on web 1440 and /m 390; spending £5,191 on both after release k; Income £156,806 with the tax checked by hand.
  - **Real walk account 795, now purged:**
    - Save Tax sign-up;
    - /m Expenditure one-box £2,600;
    - the personal form saved gender, with the reply shown once;
    - "Actually my date of birth is 15 March 1981" → form at 15/03/1981 in about 2 seconds, nothing saved until Save, then 1981-03-15.
  - No errors in the production log.
- **csjones:** every PR walked before merge on web 1440 and /m 390, using accounts 459 and the Mitchell demo. csjones is on dev `2d00e7fdd`+.
- **Tests:** only the touched files were run (never the full suite). Every PR is listed in its own description. CI was not watched (memory: never gate on it).
- **Not verified:**
  - iOS: no forms, typed path only. CI only.
  - `TypedFormFill` speed: about 15 seconds locally, about 2 seconds on csjones and fynla.org.
  - The "change my …" door with several records (the chooser): the change is not filled in after the choice.

## Decisions and dead ends

- **CSJ 2026-10-05, option A:** "agree with option a". A typed change fills in the record's form and is saved only on Save.
- **The write-claim filter is scoped to advice only.** In capture turns a gap-fill rescue can save after the model's text, so applying it there could print "not saved" next to a real save.
- **Promises ("I'll update that for you now") are left alone.** In advice they can come just before a real `delegate_to_capture` hand-off. Filtering them was tried and reverted.
- **A sentence filter can't catch a list that states the new value as recorded** (fynla.org, after release m). That is why option A exists. Don't keep widening the regex as the fix.
- **The classifier blocked two things this session:**
  - an `--admin` merge combined with remote SSH;
  - even `bash -n` on a production script.
  CSJ runs the release scripts via `! bash <path>`. A docs-only `gh pr merge --admin` on its own was allowed.
- **Committing with `git commit -a` swept CSJ's own files onto a branch once.** It was fixed with a rewritten commit and `--force-with-lease`. Always stage paths explicitly.

## Things that will bite you

- **CSJ's uncommitted files:** two excalidraw diagrams, the 30 September handover edit and two workforce logs. Never stage them.
- **Vitest picks up the three abandoned agent worktrees** in `.claude/worktrees/agent-*`, which hold old code. Failures there are not this repo's.
- **The release scripts live in this session's scratchpad** (`release-prod-2026-10-05-{j,k,l,m,n}.sh`). Copy one for the next release:
  - **j:** migration, seeder and bundles.
  - **k:** web bundle only.
  - **l:** app plus bundles.
  - **m and n:** app only.
- **Local `public/build` holds a csjones build.** `public/m-build` was rebuilt for local. Rebuild /m before walking locally (`npm run build:mobile`).
- **Walk accounts with a manual Premium grant:** local 125 (`walk-7a-2026-10-03@example.com`) and csjones 459, both `Password1!`.
- **Seeded David and Sarah Jones (local 16 and 17) can't write:** a lapsed old payment record overrides their active entitlement (Found line under 7a, billing).

## Tech debt deferred

From `docs/tech-debt-report.md` (2026-10-05 session 2):
- **The spending category list is written in four places:** `UserProfileService::CATEGORY_FIELDS`, `CoordinatingAgent::handleSetExpenditure`, `RecordEditForms::expenditureAnswers`, and the web `ExpenditureForm.vue` arrays.
- **`TypedFormFill::extract` repeats `ProposedFactSynthesiser`'s xAI JSON call.**
- **The web `ExpenditureForm.vue` still adds up section and spouse totals in the browser** (~`:1560-1600`).
- **Naming and coupling:**
  - `CertaintyFilter` now holds two rules; rename it `FynOutputFilter`.
  - `UserProfileService` reads its labels from `CaptureForms` and types "Regular savings" itself.
  - `CoordinatingAgent` resolves `app(UserProfileService::class)` instead of injecting it.
- **The form is built twice per "Edit details" typed change.**
- **The write-claim patterns are phrase matching.**

## Branch and deploy state

- **Branch:** `dev`, clean apart from CSJ's own files.
- **Unpushed commits:** none before this handover commit.
- **fynla.org:** main `fd2f7525b` (release n).
- **csjones:** dev, with the bundles from #1074's build (the same frontend as dev).
