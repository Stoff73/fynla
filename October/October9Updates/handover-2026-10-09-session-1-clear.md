---
type: handover
mode: context-clear
date: 2026-10-09
session: 1
branch: dev
trigger: context-handover skill (CSJ: "find a logical place to stop so we can clear the context")
---

# Context Clear Handover — 2026-10-09, Session 1

## Immediate state

I'm in the middle of CSJ's regression walk on fynla.org (release aa). Walk A, Save Tax for a single person, is done through A8. The fixes for defects R4–R6 are in PR #1166, not merged.

## The thread

- **Earlier today:** release aa shipped (#1162, main `33477b8a0`). It put Fyn on GPT-6 Luna and brought #1148–#1161. It was walked on fynla.org, item 17a was crossed off, and its leftovers became items 42–49.
- **CSJ (2026-10-09):** "before we do item 18, do full walks checking everything is good and there are no regressions". That means:
  - Save Tax registration for a single person and for a couple;
  - the partner joining by invite;
  - normal registration ("follows the same route, Fyn operates as expected");
  - additions, edits, actions and how-to pages;
  - walked as a user in the browser, not headless.
- **The task list** is `October/October9Updates/regression-walk-2026-10-09.md`, sections A–H, with Defects and the stop point. Screenshots are in `October/October9Updates/shots/`.
- **CSJ rulings during the walk:**
  - Walk A finishes onboarding on web. The web-to-/m switch moved to walk B (CSJ asked why I had left web part-way).
  - Every anomaly (wrong flow, wrong page, Fyn stuck, anything not in the rules or the design) is noted on the list, fixed and retested, old or new. Saved as memory `feedback_walk_anomalies_noted_fixed_retested`.
  - "As we are not connecting to open banking at the moment, we can remove the open banking screen, take user to the correct screen."
- **R1, the onboarding check step opened a page with no accounts.** It was `/savings`, where real users saw only "Connect to Open Banking". Fixed in #1164, dev `0761a4bef`:
  - Bank Accounts on web is now `/net-worth/cash`.
  - The check-step navigation names its screen (`GateRoutes::destinationForPath`), and web resolves it.
  - The Open Banking screen and the Premium cards are removed.
  - Retested on csjones as Rory Retest (user 504).
- **R2, the dashboard Savings card said "0 / 6 months" with no spending recorded.** Fixed in #1165, dev `a7c2f84af`: the null runway is passed through. Retested on csjones on web.
- **R3, the holistic plan reads an unknown emergency fund as 0 months.** `CoordinatingAgent.php:886`, plus `HolisticPlanner` typed-in 6s. Logged; check during walk F.
- **R4–R6, PR #1166 (`fix/r4-r6-walk-batch`), not merged:**
  - R4: web content ran under the collapsed Fyn rail. `AppLayout` now adds `lg:mr-10`.
  - R5: the salary sacrifice card said "no change to your take-home pay". It now says take-home pay rises.
  - R6: the Tax Strategy links were two client maps. On web, "Open a pension" and "Open investments" fell through to the Dashboard, and "See income & tax" opened Personal details. Now there is one server map, `App\Support\StrategyNextStep`, giving each composed plan item a `next_step`, which web and /m resolve.

## Files touched this session (since release aa)

- #1164: `app/Constants/GateRoutes.php`, `app/Services/Onboarding/OnboardingChatDirector.php`, `resources/js/store/modules/aiChat.js`, `resources/js/utils/semanticDestinations.js`, `resources/js/components/Savings/SavingsModuleOverview.vue`, `resources/js/views/NetWorth/CashOverview.vue`, `resources/js/components/NetWorth/InvestmentList.vue`, plus tests.
- #1165: `app/Services/Mobile/DashboardCards.php`, `app/Services/Mobile/MobileDashboardAggregator.php`, plus a test.
- #1166 (open): `app/Support/StrategyNextStep.php` (new), `app/Services/Coordination/StrategyPlanComposer.php`, `app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php`, `resources/js/components/TaxStrategy/StrategyRecommendationList.vue`, `resources/mobile/views/TaxStrategy.vue`, `resources/js/layouts/AppLayout.vue`, plus tests.
- Docs: the walk list, screenshots, this handover and `todoCurrent/TODO.md`. These go to dev through a docs PR.

## WIP commit

- None. Every code change is committed on its PR branch. The docs (walk list, shots, handover, TODO) go to dev through the docs PR made with this handover.
- CSJ's own uncommitted files (diagrams, workforce logs, scratch folders) are left untouched.

## Open decisions

- None waiting on CSJ.

## Pick up from here (auto-continue contract)

The current item on `todoCurrent/TODO.md` is 18, but CSJ put the regression walk before it. See the "Before 18 (CSJ 2026-10-09)" line under item 18.

1. **Finish PR #1166:**
   - Run `npx vitest run --exclude '.claude/**'` on any spec covering `StrategyRecommendationList` (`tests/frontend/components/TaxStrategy/*.test.js`) and `/m TaxStrategy`. Fix what fails.
   - `gh pr merge 1166 --merge --admin`, then `git checkout dev && git pull`.
   - csjones: `git pull` only (never `cache:clear`, which resets the AI provider). Build both bundles from a clean dev worktree with build.sh's exports sourced (`grep "^export " deploy/csjones-fynla/build.sh > env.sh`; `set -a; source env.sh; npm run build && npm run build:mobile`). Then rsync `public/build/` and `public/m-build/` to `~/www/csjones.co/fynla-app/public/`, without `--delete`.
2. **Retest R4–R6 on csjones** as Rory Retest (user 504; finish his onboarding to reach a plan, or use a new Save Tax account):
   - every Tax Strategy link on web 1440 and /m 390 lands on the right page;
   - the salary sacrifice wording;
   - the right edge no longer runs under the collapsed rail.
   - Also retest R2 on /m.
   - Record each result in the walk file.
3. **Carry on walk A on fynla.org as Ellis Walker** (user 807, `slaterjoneschris+walk-a1@gmail.com`, `Password1!`):
   - A9: Fyn "How did you work out the £17,600 pension figure?" on web and /m.
   - A10: /m after onboarding.
4. **Then walks B–H** per the list, logging, fixing and retesting each anomaly.
5. **When the walk ends:** one release (#1164, #1165, #1166 and later fixes; web and /m bundles). Retest every fixed flow on fynla.org, update the patch notes (running file `October/October8Updates/patch-notes-2026-10-08.md`), and purge the walk accounts (prod 807; csjones 504).

## What the next Claude needs to know

- **Walk as a user:** Playwright `browser_click`, `browser_type` and screenshots; navigate through menus. Tinker over ssh is for setup only (codes, invite links, a single user's cache).
- **Codes:**
  - Prod registration: `PendingRegistration::where('email',…)->latest('id')->first()->getRawOriginal('verification_code')` through the `ssh-fynla` MCP.
  - Prod sign-in: `EmailVerificationCode::where('user_id',…)->orderByDesc('id')`.
  - csjones: same queries in a small PHP script scp'd to the app dir. `php artisan tinker <file>` hangs; use `php file.php` with the bootstrap.
- **Typing codes:** type the code into the first box with `slowly: true`; it auto-advances.
- **The /m phone walk** needs the iPhone user agent via CDP (`Network.setUserAgentOverride`) on that tab. The fynla.org home menu "Sign in" then goes to `/m?to=/login`.
- **csjones caches the mobile dashboard payload** (`mobile_dashboard_{id}`). After a PHP deploy, clear only that user's cache (`CacheInvalidationService::invalidateForUser`), never `cache:clear`.
- **Web sessions expire after about 25 minutes idle.** Sign in again.
- **vitest path filters also match `.claude/worktrees/` copies.** Always pass `--exclude '.claude/**'`.
- **Prod still has R1/R2/R4–R6 until the release.** Ellis's pages show the Open Banking panel and "0 / 6 months"; that's expected, not new.

## Branch / deploy state

- **Branch:** `dev` at `a7c2f84af`; local matches origin.
- **csjones:** dev `a7c2f84af`, provider OpenAI. The web bundle is built from `0761a4bef` (R1); the /m bundle is from #1159.
- **fynla.org:** main `33477b8a0` (release aa). Nothing from today's walk fixes is live there.
