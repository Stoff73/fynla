---
type: handover
mode: session-end
date: 2026-10-01
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-10-01, Session 1

## Where things stand

- **Everything from this session is live on fynla.org.** Each change was walked on csjones before it merged, and on fynla.org after release, on web and `/m`.
- **Production** is `main` `86ac5c5de`. Its tree matches `dev`, apart from docs.
- **Nothing is half-done.** `todoCurrent/TODO.md` items 2, 3 and 4 are crossed off, with evidence. **Item 5 is current.**
- **CSJ cleared context to carry straight on with item 5.**

## Priorities for the next session

These follow `todoCurrent/TODO.md`, in its order. Re-read it first: CSJ edits it directly.

1. **Item 5: pension relief at 40% below £100,000, down to £50,270** (APPROVED, CSJ 2026-09-30: "Yes build it"; matrix E3).
   - **The gap:** `IncomeBandStrategy` stops once the Personal Allowance is back at £100,000. Every further £1,000 paid in still saves £400 down to £50,270, within the Annual Allowance and what is affordable.
   - **Rules:**
     - Any new card goes through the same `PensionAffordability` / `pensionFundableGross` cap. CSJ: "cap the user's own card by the same money".
     - Weigh the household in context, with all the info (`feedback_pension_advice_in_context_all_info`).
   - **Process, as items 2 and 4:**
     - Write a spec with sources first. Put any open design choices to CSJ with AskUserQuestion, recommendation first.
     - Then build in a worktree, test the named files and get a tax-compliance review.
     - Walk csjones web 1440 + `/m` 390, merge, then ask before releasing.
     - On release, walk fynla.org with walk accounts, purge them afterwards, and add a section to the running patch notes.
   - **Check the plan composition:** pension items are priced first and the savings items re-priced after them (`TaxStrategyCalculator::repriceSavingsAfterPension`). A larger pension card changes the savings figures.
2. **Item 6:** the Retirement page for someone already retired. Then items 7 to 9 are the module reviews and how-tos. See the list.

## Context to load

- `todoCurrent/TODO.md`: the order of work, CSJ's answers, and the Found lines under items 2 to 4. Some are follow-ups for later items.
- `docs/testing/2026-09-29-savetax-scenario-matrix.md`: E3 and the worked households S3 (£110k) and S9 behind item 5.
- `app/Services/Tax/Strategies/IncomeBandStrategy.php`: where the plan stops at the taper threshold (`generate` at :26).
- `app/Services/Tax/PensionAffordability.php` and `docs/superpowers/specs/2026-09-30-partner-pension-affordability-design.md`: the one money rule every pension card uses, and CSJ's rulings on it.
- `docs/superpowers/specs/2026-10-01-savings-to-lower-tax-partner-design.md`: the spec format that worked today (law table, what exists, design, decisions, tests).

## Completed this session

- **Item 2: Marriage Allowance tested against the law** (ITA 2007 s55C(1)(c), (ca), not GOV.UK's "below the Personal Allowance").
  - #1031: one rule across four engines (`TaxOptimisationService`, `HouseholdPlanningService`, and a deleted dead copy).
  - Gift Aid / relief at source priced as a band extension.
  - Released in #1032 (`b25943e73`).
- **Item 3 (new, from CSJ): the "LEVEL UP / You're ahead of X%" band** is commented out, not deleted, on web compact and desktop, `/m` and iOS (#1033). Released in #1034 (`74e3d0c8f`).
- **Item 4: move savings to the partner who pays less tax, for every couple** (#1036, #1038).
  - `TaxStrategyMath::savingsMoveToPartner` / `partnerTaxPosition` / `soleNonIsaSavings`.
  - New column `spouse_annual_savings_interest`.
  - A "Savings" option on the working-partner form, and "Interest they receive each year" on both partner forms.
  - The `spouse_savings` unlock.
  - Title "Gift £X of savings to your spouse and save £Y in tax a year".
  - Released in #1037 (`9f748a136`, with the migration and seeders) and #1039 (`86ac5c5de`, figures add up).
- **Patch notes:** one running file, `October/October1Updates/patch-notes-2026-10-01.md` plus PDF. It now has today's four releases plus last night's, and CSJ wants it appended to until they say otherwise (memory `feedback_patch_notes_one_running_file`).
- **Memory:** `project_release_2026_10_01_marriage_allowance.md` (all four releases, procedures) and `feedback_patch_notes_one_running_file.md`.
- **Tech debt:** `docs/tech-debt-report.md`, session 2026-10-01: 0 critical, 4 warnings.

## Verification state

- **Named test files only, green at each PR head:**
  - #1031: 177.
  - #1036: 12 new plus 64 related files.
  - #1038: 129.
  - Every new test was shown to fail on the old code (stash and run).
- **Tax compliance reviews:** #1031 and #1036 both confirmed the law and the worked figures. Their findings were fixed before release.
- **Walked by clicking:**
  - csjones web + `/m` for every PR.
  - fynla.org web + `/m` for #1032 (couple 770/771), #1034 (Carter demo, desktop, compact web, framed `/m`) and #1037/#1039 (772/773).
  - All prod walk accounts purged.
- **Not verified:** the iPhone app, for everything (the band change ships with the next build). Full suites were not run (by rule).

## Decisions and dead ends

- **CSJ's rulings today:**
  - Marriage Allowance "widen to law" was carried out as approved.
  - Item 3: "comment out, so do not remove in case we need to reverse this".
  - Item 4: D1 (ask a working partner's savings: balance and interest), D2 (the same interest field for a non-working partner) and D3 (the new title), all the recommended option.
  - "yes" to the item 4 release.
  - Patch notes: one running file.
- **"Savings" left unticked beside other answers records £0** (the form's existing "nothing chosen = none" logic, extended). The edit form pre-fills savings so an edit never wipes them.
- **The savings gift moves only the interest that saves most,** from the highest-rate accounts first.
  - Sizing at the average rate was wrong: a 0% account plus a 4.7% account gave £3,400, which saves nothing if taken from the 0% account.
  - Assuming a smooth curve is wrong too: the s12B allowance jumps when the user drops out of the higher-rate band. So every £10 step is priced.
- **The unlock prompt for `spouse_savings` is "Update my spouse's details"** rather than "…savings", which could open the user's own savings form. It was walked and opens the partner form.
- **GOV.UK still says "live in the UK permanently"** for the Inheritance Tax spouse exemption. The how-to cites it, so the wording stays, even though the tax reviewer mentioned FA 2025.
- **The reviewer's "s626(4)" doubt is settled:** the fetched text has "not outright" in s626(4).

## Things that will bite you

- **Auto mode blocks remote writes until CSJ says the word:** csjones branch checkouts and seeding, and production tinker writes such as walk accounts. After "deploy to csjones" or "allow the prod walk account" they run. Ask in the same turn as the plan.
- **zsh does not split an `$S="ssh …"` variable.** Write the ssh flags out.
- **Pest hook:** `./vendor/bin/pest $(cat list)` is blocked as a "full suite". Write the file names out.
- **New test directories** (for example `tests/Unit/TmpDebug/`) are not bound in `Pest.php`: `$this->seed()` is undefined. Put tests in existing directories.
- **The formatter drops a `use` import** added before its usage exists. Re-add it after.
- **Release scripts** are in this session's scratchpad: `release-prod-2026-10-01{,-b,-c,-d}.sh` covering app plus corpus, bundles only, migration plus seeders, and app only. `-c` and `-d` are the cleanest templates.
- **fynla.org demo sessions:** a cold reload of `/m` loses the demo session. Re-enter through "See our demo" → persona, then go to `/m`.
- **No demo persona qualifies for Marriage Allowance or the savings gift.** Walking those needs a walk account on production (with CSJ's OK).

## Tech debt deferred

See `docs/tech-debt-report.md`, session 2026-10-01:
- **Duplicated band-extension sum:** `TaxStrategyMath.php:105` and `:1089`.
- **Two pricing paths:** `incomeTaxWithBandExtension` against `pricingPartsFor` + `incomeTaxOn`, which clips Gift Aid and relief at source at pay.
- **`savingsMoveToPartner` runs up to five times per dashboard request.**
- **`spouseSavingsKnown`** adds queries to every `availability()` call.
- **Smaller items:** unused `$higherEarner` / `$lowerEarner` in `TaxOptimisationService.php:425-426`, and the inert `users.marriage_allowance_eligible`.

## Branch and deploy state

- **Branch:** `dev`, pushed, with nothing unpushed and no worktrees.
- **Production:** `main` `86ac5c5de`. csjones is on `dev`.
- **Walk accounts on csjones to purge (TODO housekeeping item):** 442, 444 to 455.
- **Local uncommitted files are CSJ's own, left alone:** `docs/diagrams/*.excalidraw`, `workforce/ops/log/*`, the 30 September session-1 handover edit, and untracked folders.
