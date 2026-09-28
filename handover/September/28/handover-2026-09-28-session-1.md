---
type: handover
mode: session-end
date: 2026-09-28
session: 1
repo: fynla
branch: dev (work on feat/personalised-how-to, PR #948)
---

# Session Handover — 2026-09-28, Session 1

## Where things stand

All of today's work is in **PR #948** (`feat/personalised-how-to` → `dev`, 9 commits, tip `160cf472b`, pushed). It is **not merged and not released**.

It makes every tax action card personal: "why this matters", "how to do it" and "what this changes", all from the user's own records and figures. It builds the approved deadline lanes on `/actions` and fixes the tax rules and wording CSJ raised.

- **Tested:** the touched test files, plus local browser walks on web and `/m`.
- **Not yet done:** the csjones walk, and the full CI Quality Gate. It was still running at hand-over (runs cancel on each push).
- **Approval:** CSJ approved 20 of the 21 tax how-tos. Marriage Allowance is still `edited` (draft).

**CSJ's instruction for the next session:** release this work, and update the patch notes to say it is released.

## Priorities for the next session

1. **Release PR #948 to fynla.org, then mark the patch notes as released.** Follow the `release` skill (`.claude/skills/release/SKILL.md`). The order below is non-negotiable; memory `feedback_deploy_gate_csjones_before_admin_merge` explains why.

   1. **CI.** `gh pr checks 948`: the Quality Gate and iOS Native must be green. Fix any red in the branch; never "known, not a gate".
   2. **Deploy the feature branch to csjones:**
      - On the server (ssh `-p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co`, app dir `~/www/csjones.co/fynla-app/`): `git fetch origin && git checkout feat/personalised-how-to && git pull`.
      - Build locally with `./deploy/csjones-fynla/build.sh`, then upload `public/build` and `public/m-build` using the preserve-old-chunks pattern (memory `feedback_warn_before_spa_rebuild`).
      - No migration: the how-to columns came with #944.
   3. **Seeders on csjones, all four, in this order:**
      - `php artisan db:seed --class=TaxConfigurationSeeder --force`: cap date 2029, Lifetime ISA and Junior ISA ages, automatic enrolment minimum age, Married Couple's Allowance date, Scottish Marriage Allowance limit.
      - `--class=TaxActionDefinitionSeeder --force`: Marriage Allowance now requires `spouse_income_amount`.
      - `--class=InvestmentActionDefinitionSeeder --force`: "harvest" title removed.
      - `--class=ActionHowToSeeder --force`: it throws if any heading doesn't match a strategy, which is intended.
   4. **Walk csjones, web and `/m`, with a fresh account.**
      - `/actions` shows three lanes: "Before 5 April" with the days count, "Worth doing soon", and "Waiting on you" with each missing detail once.
      - A tax card (pension or salary sacrifice) shows "Why this matters for you", personal steps and "What this changes" with Income Tax before and after.
      - A single-earner married user sees Marriage Allowance waiting as "Unlock spouse's income info". Its card shows "Add it now". Try the Fyn capture of the spouse's income; it was not tested yesterday.
      - The Investment tax tabs show no "harvest" anywhere.
      - `/m` rows show "· Closes 5 April".
   5. **Admin-merge #948 into `dev`** (memory `feedback_admin_merge_pattern_for_solo_reviewer_prs`). Then put csjones back on dev: `git checkout dev && git pull origin dev`.
   6. **Open the `dev` → `main` release PR.** Production is `main` `569957ef8`.
      - **Prod deploy:** build with `./deploy/fynla-org/build.sh`. Auto mode blocks prod writes, so write a script for CSJ to run with `!`, and tell him plainly when it is waiting on him (lesson in memory `project_release_2026_09_27`).
      - **The script:** backup to `~/release-backups/2026-09-2X/`, `git pull`, upload both bundles, the same four seeders, `php artisan config:cache`. Never `route:cache` or `optimize`. Never use `route:list` as a check, because it crashes on the Apple bridge; probe routes with curl.
   7. **Walk live on fynla.org, web and `/m`,** with the same checks as step 4. Then purge the test account (memory `feedback_clear_cjones_prod_account_after_every_fix`).
   8. **Update the patch notes to released.** In `September/September28Updates/patch-notes-2026-09-28.md` (on the branch, so on `dev` after merge):
      - Replace "This is ready but **not yet live**…" with the live date and time, and the release PR number.
      - Replace "What we checked" with the live walk results.
      - Remove anything from "Not tested on screen" that the walk covered.
      - Regenerate the PDF. Headless Chrome worked: render the Markdown with python `markdown` and the `sane_lists` extension, indent nested bullets to 4 spaces, then run `"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --no-pdf-header-footer --print-to-pdf=...`.
      - Commit through a small PR to `dev`, and save a `project_release_2026_09_2X` memory.

2. **Marriage Allowance how-to approval.** BLOCKED ON CSJ: change `status: edited` to `approved` in `database/seeders/data/action-how-to/tax.md` once he has reviewed it. This doesn't block the release; it just won't show steps until then.

3. **Help pages rewrite.** This was priority 2 on the previous handover and is unchanged. The input is `docs/help-audit-2026-09-26.md`:
   - make `public/pages/help.php` the one source;
   - delete `resources/js/views/Help.vue` and its route (`resources/js/router/index.js:1411-1418`);
   - order: the 12 wrong-fact sections, then the 36 stale-UI sections, then the 24 screens with no help;
   - every figure from tax config, every rule sourced.

   New input from today: the help should explain additional voluntary contributions (AVCs) for defined benefit members. CSJ wants the how-to to be able to link to it.

4. **Fyn "Ask Fyn about this" narration** (Rule 20, one place; load `fyn-architecture`). It leaves the personal pension out of the £7,800 pension-input explanation and says "mechanical-tier strategy".

5. **Intermittent iOS UI test** `testPR7ParityClosureJourney` ("not hittable … Keyboard Focused" on `net-worth.forecast.rate.property`). Apply the hardware-keyboard trap fix from the `ios-simulator` skill.

6. **Other modules' how-to batches,** using the same branch/why/outcome format as tax: savings (54), protection (32), retirement (26), investment (17), estate (12). Add `SOURCES` entries to `ActionHowToSeeder`.

## Context to load

- `.claude/skills/release/SKILL.md`: the release procedure for priority 1.
- `September/September28Updates/patch-notes-2026-09-28.md` (on branch `feat/personalised-how-to`; read with `git show origin/feat/personalised-how-to:September/September28Updates/patch-notes-2026-09-28.md` until merged): the notes to flip to "released".
- `database/seeders/data/action-how-to/tax.md` (on the branch): the 21 entries. The top of the file documents the grammar (`why:`, `when …:`, `always:`, `outcome:`, `{placeholders}`).
- Memory `project_release_2026_09_27.md`: the last release's script lessons (route:list crash, tell CSJ when the script waits).
- Memory `feedback_fix_adjacent_defects_in_path.md`: updated today. CSJTODO is not a parking lot for in-path defects.

## Completed this session (all on PR #948)

- **Personal how-tos** (`74a0d5c` onwards, ending `160cf472b`):
  - `app/Services/Actions/ActionHowTo.php` holds the grammar.
  - `ActionHowToFacts.php` builds each user's facts: pension types by name, accounts, spouse, children, automatic enrolment, figures, and tax config.
  - `ActionCardService` renders `why`, `how_to` and `what_this_changes` (outcome) server-side, so no client changes were needed.
  - All 21 tax entries were rewritten with sources. CSJ approved 20.
- **Deadline lanes** (`3f97b86f2`):
  - `ActionLanes` puts each action in "Before 5 April", "Worth doing soon" or "Waiting on you", and the endpoint returns the lanes.
  - The web `/actions` page renders them; `/m` shows "Closes 5 April".
  - The full list is uncapped, with one waiting card per missing detail.
- **Tax rule fixes:**
  - Salary sacrifice National Insurance cap: the date is now 6 April 2029, where the live seeded config says 2027. Employer NI above the cap is corrected, and a missing cap in config means no cap.
  - The affordability cost model uses the tax engine, plus NI and the cap. It was "£0 cost" before.
  - Marriage Allowance eligibility: a linked spouse's income is used, `spouse_income_amount` must be known, and there are a Married Couple's Allowance note and a Scotland caveat.
  - Automatic enrolment: Pensions Act 2008 s3, with the minimum age in config.
  - Bed and ISA: the 30-day rule is explained.
  - Gift Aid is shown to donors who don't use it yet.
  - Carry forward uses this year's headroom first and is blocked under the money purchase annual allowance.
  - Lifetime ISA and Junior ISA rules.
  - Joint savings now cite s836/s837.
  - Singles no longer see spouse strategies.
  - Hardcoded tax fallbacks removed.
  - `pa_taper_rescue` publishes its displayed contribution; the additional-rate title rates come from config.
- **Plain English:** "harvest" is removed from all user-facing text and banned in CLAUDE.md Rule 9. The "harvest and immediately repurchase" advice is corrected.
- **Tech-debt pass:** one `UKTaxCalculator::employeeClass1Ni` replaces four National Insurance lookups, and the employed statuses come from `OnboardingStateMachine::WORKPLACE_PENSION_STATUSES`.
- **Housekeeping:**
  - Patch notes (`ce6a3c01e`).
  - Memories: salary sacrifice cap date fixed to 2029; in-path defects are fixed, not listed.
  - Merged worktrees removed.

## Verification state

- **PHP tests (Pest), by file, all green at `160cf472b`:**
  - Actions: `ActionHowTo*`, `ActionCard*`, `ActionLanes`, `UnifiedActions`, `ActionCardEndpoint`.
  - Tax and retirement: `MarriageAllowanceStrategy`, `TaxStrategyCalculator`, `PlanB*`, `SalarySacrifice*`, `Thresholds/Lines`, `IncomeBandStrategy`.
  - Investment: `InvestmentActionDefinition*`, `TaxAwareRebalancer*`, `TaxOptimisationService`.
  - Plan and actions plumbing: `HouseholdFinancialContext`, `ComposedModulePlanService`, `StrategyUnlockCards`, `NextActionsService`.
- **CI on PR #948:** GitGuardian, logic-guard and Snyk pass. The Quality Gate and iOS Native were still running at hand-over, so check them first.
- **Local browser (worktree on :8001):**
  - Web: salary sacrifice card with "What this changes", lanes for Chris and the Plan B married couple, and the "Unlock spouse's income info" card.
  - `/m`: the junior pension and Lifetime ISA cards, and the actions list with "Closes 5 April".
- **Not verified:**
  - the reworded Investment tax screens on screen;
  - the Fyn "Add it now" capture of the spouse's income;
  - the iOS app;
  - csjones and production.

## Decisions and dead ends

- **CSJ rulings today:**
  - How-tos follow the user's records and use their figures, with outcome lines ("what this changes": tax before and after, real cost, take-home).
  - "Harvest" is banned in user-facing text.
  - The salary sacrifice NI cap is from **6 April 2029** (CSJ's own note of 2027 was a typo).
  - Marriage Allowance is shown only to couples who qualify. When the spouse's income is unknown, the action waits for it, like the other unlock items.
  - Scotland gets a caveat line only; full Scottish rates are a separate job.
  - The lanes follow design B from 17 September (the canvas artboard "Lanes").
  - The CSJTODO list is not for in-path defects.
  - Gift Aid for non-declarers is a line of text on the existing action, not a new strategy.
- **Dead end:** HMRC manual page CG42562, which some sites cite for Bed and ISA, returns a 404. The 30-day text rests on s106A(3)/(5), CG51560 and s151 instead.
- **Waiting items are capped at two on the 4-slot dashboard only.** That limit was built for the carousel in June; the full actions page shows every missing detail once.

## Things that will bite you

- **CSJ reviews `tax.md` in the main checkout.** The how-to file lives on the branch, and I put copies into the main checkout for CSJ's review. After merge, main is the only copy.
  - While a review is in progress, merge his edits three-way (`git merge-file`); never overwrite his notes.
  - CSJ marks entries `edited`. The parser treats that as draft.
- **A worktree needs its own copy of `vendor`** (`cp -R`, then `composer dump-autoload`), never a symlink. It also needs its own built `public/build` (`VITE_BASE_PATH=/build/ npx vite build`) with `public/hot` removed. Otherwise the web front end comes from the main checkout's Vite. The `/m` bundle needs `npx vite build --config vite.mobile.config.js`.
- **The test hook blocks any command that lists test files through `$(...)`,** and it blocks the entire command, so none of it runs. Name the test files literally.
- **MySQL JSON columns reorder object keys.** Compare stored how-to steps with `toEqual`, not `toBe`.
- **The local dev database has had changes this session:**
  - it ran migration `2026_09_27_000001` (how-to columns);
  - it was reseeded with the new `TaxConfigurationSeeder`, `TaxActionDefinitionSeeder` and `ActionHowToSeeder` from the branch.

  The main checkout on `dev` will read that data until the merge.
- **`September/September27Updates/patch-notes-2026-09-27.md` has an uncommitted one-line edit** in the main checkout. It isn't mine ("Savings interest is taxed the way HMRC taxes it" became "Savings interest."). Ask CSJ before committing or reverting it.

## Tech debt deferred

- `ActionCardFigures` now only holds constants (`ANNUAL_ALLOWANCE_TYPES`, `ONE_OFF_TYPES`, `FUNDED_TYPES`). It could fold into `ActionLanes`.
- `RetirementStrategyService::getMarginalTaxRate` uses raw thresholds (`bandRateFromIncome`), not the Gift Aid- and relief-at-source-extended ones. It is pre-existing and now takes its rates from config.
- Some large files predate today: `RetirementActionDefinitionService.php` (2,845 lines) and `RetirementStrategyService.php` (2,138).

## Branch and deploy state

- **Main checkout:** on `dev` `a2dbc66dc`. It is clean apart from files that were already untracked or modified before this session (`workforce/*`, excalidraw, the 27 September patch-notes line above).
- **Work branch:** `feat/personalised-how-to`, pushed, tip `160cf472b`. Worktree at `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/08b86b11-68ac-4eb3-899b-27f0b7af7eea/scratchpad/wt-howto`; remove it after merge.
- **Deploys:**
  - Production: `main` `569957ef8`, unchanged today.
  - csjones: `dev` `a21778c18`, unchanged today.
