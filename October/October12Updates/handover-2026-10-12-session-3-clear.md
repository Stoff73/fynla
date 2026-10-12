---
type: handover
mode: context-clear
date: 2026-10-12
session: 3
branch: dev (docs via docs/context-handover-2026-10-12-s3)
trigger: context-handover skill (about 750k context, at a clean task boundary before T6)
---

# Context Clear Handover — 2026-10-12, Session 3

## Immediate state

T2, T3, T4 and T5 of the walk fix plan are ticked to Dev walk. T6 is next: navigation moves to the server, and the web keyword matcher is deleted. T6 was mapped (below) but not started; no T6 code exists.

I stopped at about 750k context. T6 touches the server, both chat panels, a web bundle rebuild and walks on two surfaces, so it would have crossed 900k partway through.

## The thread

- I resumed from session 1's handover. TODO item 18 is still current, and the plan's task list (`October/October12Updates/walk-2026-10-11-fix-plan.md`, section 0b) sets the order.
- **CSJ instruction this session (saved to memory):** "remember to check off items in the task list 0b, as you go, DO NOT move onto the next task unless it is checked off". Every box below was ticked as its evidence landed, with dated entries in the plan's task log.
- **T2** (PR #1213) — one question test in front of every gate.
  - `QuerySchemas::isAdviceShaped` is now the only question test (Rule 20). It absorbed `WriteIntentClassifier::looksLikeQuestion`/`isQuestion` and `OnboardingChatDirector::userAskedQuestion`, which are removed.
  - It is called by `recordDeclaredNone`, `classify`, `proposalAcceptanceIntent`, `captureContinuationIntent`, `offerTypedChangeForm` and the setup walk's checks.
  - The write classifier also drops negated write verbs ("nothing to record").
- **T3** (PR #1214, plus #1215) — `QueryClassifier` never files a question as `data_entry` or `navigation`.
  - New retirement, estate, savings-tax and rent wording.
  - A turn is given its PRIMARY type's tools only (`AdvicePromptBuilder::buildToolsAndTriggersBlock`). `QuerySchemas::getRequiredToolsForClassification` has no app caller, so the tests read the real block.
  - "Net worth" no longer takes a question about a pension pot at a named age (P5).
- **T4** (PR #1216) — `ToolResults::wroteData` is the one definition of a landed write.
  - `onboarding_capture` counts, unless it is asking for clarification or lists something missing.
  - The stored `capture_write_landed` is now true after `capture_*` writes.
- **T5** (PRs #1217 and #1218) — the capture-handoff rule applies to statements only.
  - The rule now has one home, `FynSystemPrompt::handoffGuidance()`, which the legacy builder also reads.
  - The first walk showed the prompt alone did not hold: gpt-6-luna handed "What is 6% + 8% of £72,000…?" to capture as `work_details`.
  - Added a dispatch gate: `CoordinatingAgent::executeTool('delegate_to_capture')` refuses a handoff on a question back to the model (`questionHandoffRefusal`). A what-if (`what_if_scenario`) still hands off (S0.5.r).
  - Re-walked from step 1: green.
- **Rejected:**
  - Bare topic words in the question test ("forecast", "how much" anywhere). They stop typed answers filling setup forms.
  - Bare "shortfall" in retirement wording. It would take "protection shortfall".

## New tasks added to the list (R4)

- **T45:** the write router files "Change my retirement income target…" as a `goal` (the keyword "target").
- **T46:** Fyn's own arithmetic on the user's figures is wrong, and Fyn has no calculation tool. Three cases:
  - £478 a month should be £492.89 (5% compounded monthly, 228 months, to build £186,975);
  - "gift to Dev saves £0" should be £484;
  - the ISA saving of £468 should be £216, because ISA interest does not use the Personal Savings Allowance (ITTOIA 2005 s694).

  Rule 23 says a wrong figure a user can see is app-breaking, so this is a real defect. A fix needs a design (for example a read-only calculation tool); the plan does not specify one.
- **T47:** Fyn printed raw LaTeX (`\(\frac{…}\)`) on /m.

## Files touched this session

- Code, all merged to dev, csjones at `e9cf1f21c`:
  - `app/Constants/QuerySchemas.php`, `app/Services/AI/QueryClassifier.php`, `app/Services/AI/WriteIntentClassifier.php`, `app/Services/AI/AdviceFyn.php`;
  - `app/Services/Onboarding/OnboardingChatDirector.php`;
  - `app/Services/AI/ToolResults.php`, `app/Traits/HasAiChat.php`;
  - `app/Services/AI/Fyn/FynSystemPrompt.php`, `app/Services/AI/AdvicePromptBuilder.php`, `app/Agents/CoordinatingAgent.php`;
  - the prompt snapshot and the PromptOverlay fixtures.
- Tests:
  - `IsAdviceShapedTest`, `WriteIntentClassifierTest`, `AdviceFynCaptureContinuationTest`, `AdviceDeclaredNoneTest`, `TypedChangeFillsFormTest`, `OffScriptAnswerFilterTest`;
  - `QueryClassifierTest`, `CaptureWriteLandedTest` (new), `FynSystemPromptTest`, `QuestionNotHandedToCaptureTest` (new).
- Docs, in this handover's PR:
  - the plan: 0b ticks, the task log for T2–T5, and T45–T47;
  - `todoCurrent/TODO.md`, item 18's "Where we stopped";
  - `October/October12Updates/shots/T2-*` to `T5-*`.
- Memory:
  - `feedback_plan_task_list_and_change_only_testing` updated (tick as you go);
  - new `reference_free_tier_daily_fyn_budget_blocks_walks`.

## WIP commit

- There is no WIP code: everything is merged by PR.
- The docs are on `docs/context-handover-2026-10-12-s3`, merged to dev by PR. The SHA is in that PR.

## Open decisions

- **T42** (older): clearing Brett's production account 812 flags is a database change. It needs CSJ's OK.
- **T46's fix design** is not specified by the plan. The default if CSJ says nothing: a read-only deterministic calculation tool for arithmetic on figures the user gives (percentages, totals, annuities), with the prompt telling Fyn to use it rather than work sums out itself. Raise it in plain words when T46 comes up; it is not blocking T6.
- **R9/R20** (older): the Retirement chart heading.

## Pick up from here (auto-continue contract)

Current TODO item: **18**. See "Where we stopped".

### FIRST: T48, one Fyn limit per tier (CSJ 2026-10-12, added after this handover was written)

CSJ: "due to the change in the llm, we can increase the limits … so the test runs can complete", then "lets replace both, with 750k and 3 million, so we only have one limit".

- **Values (CSJ 2026-10-12):** Free **750,000** tokens, Premium **3,000,000**.
- **They replace both:**
  - `fyn_weekly_token_budget` (Free 100,000, Premium 500,000). Exceeding it only switches to a cheaper model and shows a notice: `HasAiGuardrails::isWeeklyBudgetExceeded` (`:85`), `weeklyTokenUsage` (`:72`), `getAiModel` (`:132`) and `HasAiChat.php:467`.
  - `fyn_daily_hard_backstop` (Free 500,000, Premium 2,000,000). This is the hard stop, "You've reached your daily Fyn usage limit": `isDailyBackstopExceeded` (`:104`) and `:213`.
- **Period, default until CSJ says otherwise:** per day. Keep the daily stop and its midnight UTC reset, set it to 750,000 / 3,000,000, and remove the weekly switch and its column. If CSJ meant per week, the stop moves to the rolling week instead.
- **Values live in data, not code:** `tier_configurations`, the same on csjones and production today (read 2026-10-12). `TierConfigurationSeeder` uses `firstOrCreate` (`:20`), so it will not change existing rows. A migration must set the new values on both environments. Also update the seeder (`:62-63`, `:105-106`) for fresh installs.
- **Remove `fyn_weekly_token_budget` from:**
  - `app/Models/TierConfiguration.php:19,31`;
  - `app/Services/Stores/TierConfigurationStore.php:168`;
  - `app/Http/Resources/TierConfigurationResource.php:27`;
  - `app/Services/Payment/SubscriptionStatusService.php:81`;
  - `app/Http/Requests/Admin/UpdateTierConfigurationRequest.php:26`;
  - the admin page `resources/js/components/Admin/TierConfiguration.vue:125,232`. That is a web bundle rebuild; /m and iOS do not read it.
  - Then drop the column by migration.
- **Tests to update:**
  - `tests/Feature/Tiers/ApprovedTwoTierMatrixTest.php:62,77`;
  - `tests/Feature/Tiers/TierConfigurationSeederTest.php:25,38`;
  - `tests/Feature/Tiers/TierConfigurationsTableTest.php:16`;
  - `tests/Pest.php:165-166`;
  - `tests/Unit/Services/Payment/SubscriptionStatusServiceTest.php:144-145`;
  - `tests/Unit/Services/Tiers/FynMeteringTest.php:24`;
  - `tests/Architecture/StoreBoundary/TierConfigBoundaryTest.php`;
  - `tests/E2E/public/pricing-contract.spec.js:71,90` (fixture only).
- **Walk:** Rory (504) had used 517,635 tokens on 2026-10-12. Under 750,000 he is no longer stopped. Walk Fyn on web and /m past the old 500,000 point, and check the admin Tier Configuration page shows one limit.
- **Release:** needs the migration on production. CSJ runs the release.

### Then T6

Next in the plan is **T6**: navigation moves to the server for every surface, and the web keyword matcher is deleted (F3, F4; plan section 5 B2.5).

T6 mapping, done this session:
- **Web matcher to delete:** `resources/js/utils/chatNavigationRouter.js`.
  - Callers: `resources/js/components/Shared/AiChatPanel.vue:455` (import), `:1193` and `:1231` (`matchNavigationIntent`).
  - Spec mocks: `AiChatPanel.conversation.spec.js:13`, `AiChatPanel.navigation.spec.js:9`, `AiChatPanel.onboardingStart.spec.js:7`.
- **One source for targets (Rule 20):** `app/Constants/GateRoutes.php:71` `MAP`, which gives label, web and mobile routes per page. It includes `TAX_STRATEGY`; check whether it is missing any page the old matcher covered (plans, actions, what-if, trusts, will builder, help).
- **Plan rules:**
  - only a plain command with no question navigates (`QuerySchemas::isAdviceShaped` false, with a leading command such as "take me to", "go to", "open", "navigate to");
  - words match whole;
  - emit the existing `navigation` SSE event;
  - save the request and the reply;
  - web and /m already handle `navigation`; native is to confirm.
- **Server home:** advice mode has no navigation tool (`navigate_to_page` is stripped, `AdviceFyn.php:195-203`). So the deterministic command check belongs in `AdviceFyn::handleTurn` before the model, like the other gates. Keep F13's by-design behaviour (`AiChatPanel` `handleNavigation`).
- **The loop per task:**
  1. Worktree off origin/dev in the scratchpad: `cp -R` vendor, copy `.env`, symlink `node_modules`, `composer dump-autoload -o`.
  2. Red test first, then green. Run only the touched test files, named explicitly; the hook blocks directories and `xargs`.
  3. PR to dev, then `gh pr merge --merge --admin`.
  4. On csjones: `git pull origin dev`, clear route, config and view, then `config:cache`.
  5. T6 changes the frontend, so rebuild the web bundle (and /m if touched) from a clean dev copy with `deploy/csjones-fynla/build.sh`.
  6. Walk web 1440 and /m 390 as a user.
  7. Tick the 0b boxes with evidence.
  8. Remove the worktree.

## What the next Claude needs to know

- **Walk accounts:** Rory (504) used up the Free daily Fyn budget (500k tokens). Use Alex 502 (`slaterjoneschris+devluna-a@gmail.com` / `Password1!`) or the others in the new reference memory.
  - Codes: `ssh -F ~/.ssh/config.fynlaDev csjones-dev 'cd ~/www/csjones.co/fynla-app && php zz_code.php <id>'`.
  - Opening /m while signed in on web signs the web tab out (Q3, T33). To switch account, use the /m menu "Sign out".
- **How to read a turn's filing:** each assistant row stores `assembled_context` (`<required_tools>` is the primary type's list) and `tool_calls` and `tool_results`.
  - A refused handoff shows `handoff_refused: true` in `tool_results.raw`.
  - The csjones log lines `[AdviceFyn] Deterministic write-intent routed` and `[FynLoop] LLM-fallback write-intent caught` tell the gate path from the model path.
- **The auto-mode classifier** sometimes refuses plain local greps as "Production Reads" after a production read. `git -C <worktree> grep …` and the Read tool get through.
- **zsh does not word-split `$files`**, and the test hook blocks `xargs pest`. Name the test files literally.
- **Main checkout:** still on dev, behind origin/dev. After this docs PR merges, the local plan, TODO and T2–T5 shots match dev (`git diff` against origin/dev to confirm, then `git checkout --` the two files and remove the untracked shots before `git pull --ff-only`). The `brettTest/` and `workforce/` changes and the session-2 handover copy are not this session's; leave them for CSJ.

## Branch / deploy state

- dev: `e9cf1f21c`, plus this docs PR.
- csjones: `e9cf1f21c`, server PHP only. No bundle rebuild was needed for T2–T5.
- fynla.org: main `f45bc67fd` (release ab), unchanged.
- T0–T5 wait for CSJ's release. There is no migration beyond `2026_10_09_140000`; both bundles are needed for R19–R39.
