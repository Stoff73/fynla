---
type: handover
mode: session-end
date: 2026-10-01
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-10-01, Session 2

## Where things stand

- **Everything from this session is live on fynla.org.** Production is `main` `a80399d5a`; its tree matches `dev` apart from docs.
- **Items 5 and 6 of `todoCurrent/TODO.md` are crossed off,** with evidence. Each was walked on csjones before merging and on fynla.org after release, on web 1440 and `/m` 390, and each prod walk account was purged.
- **Nothing is half-done.** **Item 7 is current.**
- **CSJ cleared context to carry straight on.**

## Priorities for the next session

These follow `todoCurrent/TODO.md` in its order; re-read it first, because CSJ edits it directly.

1. **Item 7: Retirement module review, then its how-tos.** There are 25 definitions in `RetirementActionDefinitionService` and no how-tos written yet.
   - **Review first, in the shape protection got (#972, #994):**
     - Do the cards come from the module's definitions?
     - Do they carry `definition_key` and `figures` through `buildRecommendation` → adapter `extra` → aggregator → `ActionCardService`?
     - Which keys fire on real households?
     - Which cards are the same action?
     - Is there a "your position" view? Item 6's drawing view is now part of this for retirees.
   - **Then the how-to batch:** add `database/seeders/data/action-how-to/retirement.md` in the `savings.md` / `protection.md` format (`why`, branches, `outcome`, `learn:`, every claim sourced, Rule 23), with a `SOURCES` entry in `ActionHowToSeeder` (`:22`).
     - **The draft must be on `dev` before CSJ reviews it**, and CSJ approves each entry (memory `feedback_review_files_must_be_on_dev`).
   - **Then:** walk web + `/m` on csjones, release, walk fynla.org.
   - **Item 6 left these under item 7:** the other Retirement tabs (Future Value, Income, Capital Adequacy, Drawdown strategy) and the dashboard's `years_to_retirement` still use the saver framing for someone drawing.
2. **Item 8:** Investment module, the same review and how-tos.
3. **Item 9:** Estate module, the same review and how-tos.

## Context to load

- `todoCurrent/TODO.md`: the order of work, plus the Found lines under items 5 and 6. Several are follow-ups for later items.
- `app/Services/Retirement/RetirementActionDefinitionService.php`: the 25 retirement definitions item 7 reviews.
- `app/Services/Actions/ActionCardService.php` and `database/seeders/ActionHowToSeeder.php` (`SOURCES` at `:22`): how cards get their how-to, and where a new module's file is registered.
- `database/seeders/data/action-how-to/protection.md`: the newest approved how-to batch, the format item 7 copies.
- `docs/superpowers/specs/2026-09-29-protection-cover-position-design.md`: the "your position" design protection got, the model for the retirement review.
- `docs/superpowers/specs/2026-10-01-retirement-drawing-view-design.md`: what item 6 built for retirees, so item 7 doesn't duplicate it.

## Completed this session

- **Item 5: pension relief at 40% below £100,000** (PR #1040, released as #1041, main `9dde090a1`).
  - **The card:** `pa_taper_rescue` carries on below £100,000 down to the higher-rate threshold, only when spending is recorded. `TaxStrategyMath::higherRateSlice` is now shared.
  - **The payroll split:**
    - How-tos put what pay can carry through payroll (salary sacrifice first) and the rest in as a one-off, with its own figures.
    - Built in `ActionHowToFacts::payrollCanCarryPerMonth`; facts `payroll_short`, `payroll_total`, `payroll_rest*`.
    - Covers all three pension how-tos.
  - **Tax review fixes:**
    - the reclaimed allowance never exceeds what was lost;
    - the Blind Person's Allowance is in the plan's pricing and band limits;
    - the 40% sentence shows only where the engine agrees;
    - the taper ratio comes from config.
- **Item 6: the Retirement page for someone drawing their pension** (PR #1042, released as #1043, main `a80399d5a`).
  - **The view:** `RetirementDrawdownPosition` is served as `drawdown_position` in `/api/retirement/projections`, read by `RetirementDrawingView.vue` (web) and `/m` `Retirement.vue`.
  - **State Pension "being paid" is now captured.** Before, nothing wrote `state_pension.already_receiving`, so the State Pension never counted as income anywhere. It is asked in:
    - the web form;
    - Fyn's `capture_state_pension` (both schemas);
    - the new `CaptureForms::STATE_PENSION` edit form, which a Fyn State Pension edit opens on.
  - **Other fixes:**
    - the State Pension rate comes from `taxConfig.js` (it was a typed-in £221.20 / £11,973 in five places);
    - money boxes in Fyn forms take pence;
    - `TaxStrategyMath::incomeTaxLiability` treats Gift Aid and relief at source as band extensions;
    - no National Insurance past State Pension age;
    - `RetirementAgeResolver` reads a past `retirement_date`;
    - `MonteCarloEngine` floors a drawn pot at £0;
    - the tool-schema golden fixtures were recaptured (they were stale on dev).
- **Patch notes:** `October/October1Updates/patch-notes-2026-10-01.md` and its PDF now cover both releases.
- **Memory:**
  - `project_release_2026_10_01_marriage_allowance.md` now covers all six releases;
  - new `feedback_compute_real_numbers_never_either_or.md`.
- **Tech debt:** see `docs/tech-debt-report.md`, session 2026-10-01 (session 2).

## Verification state

- **Named test files only:**
  - **#1040:** 390 tests across 29 files.
  - **#1042:** the new `RetirementDrawdownPositionTest` (17), `StatePensionContextualFormTest` (3) and `CaptureStatePensionTest` additions, plus related retirement, Monte Carlo, contextual Fyn, form, golden-master and architecture files. All green.
  - **Frontend:** 59 vitest tests across the touched specs.
  - **Every new backend test was shown to fail on the old code.**
- **Tax compliance reviews:** one each for #1040 and #1042. The law and figures were confirmed; the findings were fixed before release, except the Found lines listed under items 5 and 6.
- **Walked by clicking:**
  - csjones: web + `/m`, accounts 456/457/458 for item 5 and Pat 439 for item 6;
  - fynla.org: web + `/m`, walk accounts 774 and 775, both purged.
- **Not verified:** the iPhone app (rule). Full suites were not run (rule).

## Decisions and dead ends

- **CSJ's rulings today:**
  - **Item 5:**
    - D2: leave the /savetax funnel as is;
    - D3: the card sentence and how-to step approved as written;
    - payroll: "why is this an either or… give the user a correct number". Built as payroll-first plus a one-off for the rest; saved as memory `feedback_compute_real_numbers_never_either_or`.
  - **Item 6:**
    - the "Income + how long it lasts" view, for anyone drawing from a pension (even while working);
    - the clearer projection wording (Consumer Duty);
    - "Taxable income drawn each year";
    - merge and release.
- **Item 5 is one card, not two.** It follows `additional_rate_avoidance` and `OWN_PENSION_TYPES` ("only one applies for a given income").
- **Item 5 carries on below £100,000 only when spending is recorded.** That follows CSJ's "We ask for expenditure", and keeps the funnel parity test equal.
- **The `already_receiving` column is `NOT NULL DEFAULT 0`,** so "never asked" and "deferred" read the same. Rather than migrate:
  - the card says "not recorded as being paid";
  - the edit form leaves the choice unanswered unless it is a known "yes".
- **Advice Fyn could not save "I'm already being paid my State Pension":** there is no write verb, so the write-intent classifier doesn't route it to capture. The fix is the edit-form pattern, not more classifier verbs.

## Things that will bite you

- **GitHub pushes failed with "Error in the HTTP2 framing layer".** Use `git -c http.version=HTTP/1.1 push`.
- **bash 3.2 cannot parse apostrophes in a `$(cat <<'EOF' …)` PR body.** Use `--body-file`. The release scripts are in this session's scratchpad: `release-prod-2026-10-01-e.sh` (app + seeder + corpus) and `-f.sh` (app + corpus + both bundles).
- **zsh does not split `$K` flag variables.** Write the ssh flags out.
- **Login codes on both servers drop digits typed before the box has focus.** Click digit 1, then press the keys one at a time.
- **`php artisan cache:clear` on csjones signs out walk sessions.**
- **Upload bundles to csjones by tar:** `cp -rn $d/. $d.old/ && rm -rf $d && tar -xf - && cp -rn $d.old/. $d/` keeps old chunks for open tabs.
- **`users.annual_employment_income` and friends are totals.** On test users, `DCPension.monthly_contribution_amount` is the EMPLOYEE contribution, not employee plus employer.

## Tech debt deferred

See `docs/tech-debt-report.md`, session 2026-10-01 (session 2):

- **Two Income Tax routes:** `incomeTaxNow` uses deductions, `incomeTaxLiability` uses band extensions.
- **Slow first load:** `incomeLastingTo` runs about 11 Monte Carlo runs on the first load.
- **State Pension status labels are mapped on each client.**
- **`payrollCanCarryPerMonth` recomputes affordability** that the plan already has.
- **`PensionList.vue` is about 2,070 lines.**

## Branch and deploy state

- **Branch:** `dev`, pushed. No worktrees.
- **Production:** `main` `a80399d5a`. csjones is on `dev` `f386968e6`, plus later docs-only commits.
- **csjones walk accounts to purge** (housekeeping item): 442 and 444–458.
  - Pat 439 is CSJ's own walk account; its State Pension is now marked paid.
- **CSJ's uncommitted files, left alone:**
  - `docs/diagrams/*.excalidraw`;
  - `workforce/ops/log/*`;
  - the 30 September session-1 handover edit;
  - the untracked folders.
