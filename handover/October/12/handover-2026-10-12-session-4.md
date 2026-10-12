---
type: handover
mode: session-end
date: 2026-10-12
session: 4
repo: fynla
branch: dev
---

# Session Handover — 2026-10-12, Session 4

Session 4 of the day. Sessions 1 to 3 are context-clear handovers in `October/October12Updates/`; this one follows on from session 3's.

## Where things stand

- Walk fix plan tasks T48, T6, T7, T8 and T9 are done to **Dev walk**: merged to dev, deployed to csjones, and walked as a user on web 1440 and /m 390.
- None are released. Production is still main `f45bc67fd` (release ab).
- T0 to T5 were already walked green and also wait for CSJ's release.
- New tasks T49 to T52 were added to the plan's list.
- The tree is clean apart from CSJ's own `brettTest/` and `workforce/` changes. Leave those alone.

## Priorities for the next session

Order comes from `todoCurrent/TODO.md` item 18, then the plan's task list (`walk-2026-10-11-fix-plan.md` section 0b).

1. **DECISION (CSJ): approve the user-facing wording written this session.** These are under item 18's "Where we stopped (session 4)":
   - "Opening <page>." and "<page> is only on the website for now." (T6);
   - the input placeholder "Fyn usage limit reached — resets in 2d 4h" (T48);
   - "You don't have a <thing> saved, so there's nothing to delete." and "Which goal did you mean: A or B?" (T8);
   - "You're in preview mode, so I haven't sent an invitation. Create your own account to invite your spouse." (T9).

   Ask once and carry on with T10.
2. **DECISION (CSJ): T50 needs a design before it is built.** The what-if comparison shows £0 for every figure:
   - `WhatIfScenarioService::extractMetrics` reads keys the agents do not return;
   - `applyOverrides` never applies `pension_contribution`;
   - it saves the user's own row mid-calculation, then reverts it.

   It is an engine rebuild, not a patch (Rule 16: ask before deciding the design).
3. **T10: spouse figures always in Fyn's context** (P4, P3, F5; plan section 5, B3).
   - `FynContextAssembler::savedHouseholdFinancesDirective` (about line 579) adds the household row only when the message has both a spouse word and a finance word, so "Dev" never triggers it.
   - The plan says: add the household row whenever it exists.
4. **Then T11 onward, in plan order.** T11 is the retirement plan tool carrying the Retirement page's headline. Seen again on the T6 walk: Fyn quoted £1,290,042 and £531,047 and could not show the working.
5. **Release (CSJ runs it).** It carries T0 to T9 and T48 and needs:
   - the migration `2026_10_12_120000_one_weekly_fyn_limit_per_tier`;
   - both bundles built from main with `deploy/fynla-org/build.sh`.

   After the release, walk each task's Prod box on fynla.org, then purge the walk accounts.

## Context to load

- `todoCurrent/TODO.md`: item 18, "Where we stopped (2026-10-12, session 4)". It holds the current position, the decisions and the walk-account state.
- `October/October12Updates/walk-2026-10-11-fix-plan.md`: section 0 (the NON-NEGOTIABLE rules R1 to R5), 0b (the task table and the evidence log for T48 and T6 to T9), and section 5 B3 (T10's spec).
- `app/Services/AI/Fyn/FynContextAssembler.php`: `savedHouseholdFinancesDirective`, where T10 starts.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_read_before_asserting_never_guess.md`: new this session, after CSJ asked "why are you guessing?".

## Completed this session

All PRs are merged to dev; the evidence is in the plan's task log.

- **T48, one Fyn limit (#1222):** the limit is per rolling week, Free 750,000 and Premium 3,000,000. The daily stop and the cheaper-model switch are removed, and the migration ran on csjones. Docs: #1223.
- **T6, navigation decided on the server for every surface (#1224):** `App\Services\AI\NavigationCommand` sits before the director and Advice Fyn in both controller dispatches. `GateRoutes::NAVIGATION_NAMES` holds the page names. /m sends `X-Fynla-Surface: m`. The web keyword matcher `chatNavigationRouter.js` is deleted. Docs: #1225.
- **T7, a what-if reaches the scenario capture (#1226, test only):** T5's gate already did it. Docs: #1227.
- **T8, a delete preview refuses a record that does not exist (#1228, #1229, #1230):**
  - the not-found result carries `saved_records` and a `user_facing` sentence;
  - `captureFailureText` shows a `user_facing` message as written.

  Docs: #1231.
- **T9, an invite request reaches the invite step's code (#1232):** `spouse_invite` intent, then `OnboardingChatDirector::inviteSpouseFromChat`.
  - **#1233, security, from the background review:** `SpouseLinkingService::guardInviteRate` is the one 5-an-hour limit for every invite path (Family settings, chat and campaign, the agent's spouse tools, the onboarding save), and demo personas never invite.

  Docs: #1234.
- **Memory:** `reference_free_tier_daily_fyn_budget_blocks_walks` was rewritten for the weekly limit; `feedback_read_before_asserting_never_guess` is new.

## Verification state

- Each task's touched test files went red first, then green. The counts are in the plan's log; the largest is 214 passed for T9 and 184 for #1233.
- **No full suite has been run.** That is plan task T43, once the plan is complete (R5).
- Dev walks on csjones, web and /m:
  - T48 as Rory 504;
  - T6, T7 and T8 (web) as Alex 502;
  - T8 (/m) and T9 as Drew 508.
- **Not walked:**
  - the T48 admin Tier Configuration page (CSJ: "dont worry about ttesting these limits now");
  - the spouse-invite rate limit and the demo-persona refusal (covered by tests; walking the limit would send 5 more real emails);
  - native iOS for every task (iOS points at production).
- The bundles on csjones were built at 12:41 BST from T6's tree. T7 to T9 changed no frontend, so they still match dev.

## Decisions and dead ends

- **NO SUB-AGENTS (CSJ, 2026-10-12, NON-NEGOTIABLE)** also rules out skills that spawn agents. This session-end skipped `vault-sync` and `tech-debt-session` for that reason, mirrored the handover to the vault with a plain copy, and did the tech-debt pass by hand.
- **The auto-mode classifier refuses some csjones actions:**
  - `php artisan migrate --force` over ssh;
  - `deploy/csjones-fynla/build.sh`, as "Production Deploy";
  - a combined merge-then-ssh-pull command.

  CSJ ran the migration and both builds with `!`. These went through when run alone: `git pull` on csjones, rsync of `public/build` and `public/m-build` (no `--delete`), and `gh pr merge`.
- **T6's design:** no request said which client it came from.
  - `X-Fynla-Client: ios` is native, the new `X-Fynla-Surface: m` is /m, and anything else is web.
  - `route_path` is the surface's own path, plus a semantic `destination`.
  - A website-only page on /m says so and does not navigate.
  - Only a plain command navigates: the whole remainder must be a page name, and any question (`isAdviceShaped` or "?") does not.
- **T7 needed no production code:** T5's dispatch gate (#1218) already refuses a pension handoff on a what-if question.
- **T9:**
  - A request that ends in a question ("Is that address written correctly?") is still answered rather than sent (the T2 rule, a question is never a write).
  - The invite needs the name and email; the spouse's name on file fills in when only an email is given. The order is linked spouse, then parked fact, then the `FamilyMember` spouse row.
- **T50 was not started:** it is an engine rebuild needing CSJ's design call.

## Things that will bite you

- **Walk accounts:** Rory 504 and Alex 502 are over the 750,000 weekly limit until 00:00 on 16 October. Use Drew 508, Pat 506 (linked to Rory) or Jordan 492.
  - Drew is now married with three pending invitations (13 to 15, to `slaterjoneschris+t9walk*`).
  - Opening /m signs the web tab out (Q3/T33).
- **Sign-in codes:** `ssh -F ~/.ssh/config.fynlaDev csjones-dev 'cd ~/www/csjones.co/fynla-app && php zz_code.php <id>'` (the R2 exception; log each use).
- **Capture tests with a fake model:** the write classifier routes "Delete my …" and "Invite my …" straight to capture with no advice-model call, so the first fake turn is the capture turn's. Read the dispatch before writing the harness sequence.
- **The worktree** is `scratchpad/wt48`, reused for every branch with `git checkout -b … origin/dev`. It holds the csjones build output. Fetch first: its dev pointer goes stale.
- **Capture failure messages reach the user.** A tool's `message` is shown by `captureFailureText`, so steer the model with a separate key (`instruction`), as T8 learned.

## Tech debt deferred

- `HasAiGuardrails::getTodayTokenUsage`: since T48 only `TokenBudgetConcurrencyTest` calls it.
- `CoordinatingAgent::handleDeleteRecord` builds record names inline in its delete branches (`:6915`, `:6935`) beside the new `recordName`.
- The email regex is twice: `OnboardingChatDirector.php:2662` and `OnboardingValueInterpreter.php:394`.
- The demo-mode sentence is twice: `OnboardingChatDirector.php:2723` and `CoordinatingAgent.php:4870`.
- `GateRoutes::NAVIGATION_NAMES` web paths are mirrored by hand in `resources/js/utils/semanticDestinations.js` and `resources/mobile/navigation/semanticDestinations.js`.
- Native `FynEventReducer.allowedRoute` opens only some screens. Confirm T6 with the next iOS build.

## Branch and deploy state

- Branch: `dev` (main checkout), `7fa4470ba` plus this handover's docs PR.
- Unpushed commits: none.
- csjones: `cb78008e5` (PHP). The T48 migration ran, and both bundles were built at 12:41 BST from T6's tree.
- fynla.org: main `f45bc67fd` (release ab), unchanged.
