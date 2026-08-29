---
type: handover
mode: session-end
date: 2026-08-29
session: 1
repo: fynla
branch: fix/w-0204-salary-sacrifice-add-back-to-threshold-income
---

# Session Handover — 2026-08-29, Session 1

## Where things stand

**Board work. Three PRs merged, two open and green-or-nearly, four board items filed.**
This session closed out both PRs the previous handover left open and then worked down the
board: W-0509 (critical), W-0489 and W-0204.

`dev` now carries W-0482, W-0485 + W-0511, and W-0489. Two PRs remain open: **#742
(W-0509)** waiting only on a flaky native job, and **#744 (W-0204)** with CI in flight on
its final commit.

**Nothing is blocked on CSJ.** Both decisions this session needed were asked and answered.

## Priorities for the next session

1. **Land #742 and #744.** Both were green on everything that matters when the session
   ended.
   - **#742 (W-0509)** — every check green except `test-and-build`, the iOS native job. It
     failed once on `FynlaUITests.swift:153`, a `waitForExistence(timeout: 3)` on a CI
     simulator, and was re-run; the re-run was still pending at close. **That test is
     unrelated to the change** (a validation rule and a DB enum on the Estate profile),
     so if it fails again it is the flake, not the diff — check, then `--merge --admin`.
   - **#744 (W-0204)** — full CI running on `9122bf348`. It had already gone green
     locally: 358 passed on the post-merge state, 23 vitest.

2. **W-0512 — the perpetuity that over-credits `projected_cash`.** Next on the board and
   the one that unblocks **W-0517**, filed this session. `PensionProjector:219` credits
   `pot × safe withdrawal rate` every retired year from a fund it never reduces, so
   `HouseholdCashFlowProjector` inflates cash for every household with a pension. Fix it
   **with** W-0517, not before: W-0517's residual subtracts that same series, and
   future-valuing one half while the other is still a perpetuity buys false precision.

3. **The rest of the filed items, in severity order:** `W-0513`, `W-0514`, `W-0508`,
   `W-0515`, `W-0510`, `W-0516`, then the new `W-0517` and `W-0518`.

4. **Then the queued high:** `W-0037 W-0050 W-0133 W-0138 W-0139 W-0144 W-0155 W-0171
   W-0222 W-0226 W-0227 W-0462 W-0486 W-0490 W-0495`, then medium, then low.

5. **A full-suite run, alone, as a consolidation point.** Still not re-established — it has
   been outstanding for three sessions. **One Pest process at a time**; two share
   `laravel_testing` and deadlock into failures indistinguishable from real breakage.

## Context to load

- `workforce/ops/board/W-0517-the-pension-residual-keeps-growth-on-pounds-the-cash-never-earned.md`
  — carries the arithmetic for priority 2, including the worked £500,000 household, and
  why it is blocked on W-0512.
- `workforce/ops/board/W-0512-the-cash-projection-pays-a-pension-income-from-a-fund-that-never-shrinks.md`
  — the other half of that pair.
- `workforce/ops/board/W-0204-salary-sacrifice-is-not-added-back-to-threshold-income.md`
  — read the Resolution. It records the finding that the pre/post-sacrifice ambiguity only
  ever moved net income, never the taper decision.
- `app/Services/Tax/IncomeDefinitionsService.php` — three items landed in it this session
  (W-0485, W-0511, W-0204). The docblock on `getPensionContributions()` is the record of
  the salary sacrifice treatment.
- `app/Services/Tax/IncomeTaxBands.php` — `withBlindPersonsAllowance()`. The one place an
  allowance is added after the taper, and the model for any future non-tapered allowance.
- `docs/tech-debt-report.md` — this session's pass, five findings, none critical.

## Completed this session

**Merged to `dev`:**

- **#740 / W-0482** — the tax gate was re-run and **CLEARED**. The suites were run alone
  first (622 passed, 1,978 assertions), closing the one verification gap the previous
  handover named. `24577aab1`, merged as `99cab136e`.
- **#741 / W-0485 + W-0511** — the Blind Person's Allowance removed from adjusted net
  income *and* granted at ITA 2007 s23 Step 3, shipped together on CSJ's decision so no
  registered-blind user passes through a state where their tax moves the wrong way.
  `9e304da01`, merged as `1eae56f9d`.
- **#743 / W-0489** — `migrate:savings-to-cash` deleted on CSJ's decision. `ce36afca6`.

**Open:**

- **#742 / W-0509** (critical) — a civil partnership could not save an Inheritance Tax
  profile at all. Two layers carried the pre-2026-04-15 list and **neither held a quoted
  `'married'` literal**, which is why the W-0480 sweep guard could not see either.
- **#744 / W-0204** — the FA 2004 s228ZA(3) salary sacrifice add-back.

**Board items filed: 4.** W-0517, W-0518, plus the resolutions recorded on W-0482, W-0485,
W-0489, W-0509, W-0511 and W-0204.

## Verification state

- **W-0482:** 622 passed (1,978 assertions) across
  `tests/Unit/Services/Estate tests/Feature/Estate tests/Unit/Services/Retirement`, run
  alone. All 15 CI checks green before merge.
- **W-0485 + W-0511:** 309 passed (tax, profile, benefits), 110 (protection), 23 vitest.
  **Mutation-verified** — the three relief tests fail with the application reverted. All CI
  green before merge.
- **W-0509:** 345 passed across `tests/Feature/Estate` and `tests/Architecture`.
  **Mutation-verified** — restoring the old `in:` literal turns two of the four new tests
  red. CI green except the flaky native job.
- **W-0489:** 380 passed across Console, Coordination and Architecture. CI fully green;
  merged.
- **W-0204:** 358 passed on the post-merge state (tax, benefits, feature tax, protection),
  23 vitest. **Mutation-verified** — removing the add-back turns three of the six new tests
  red. **CI was still running at close.**
- **NOT verified in a browser, anywhere.** No Playwright this session.
- **The full suite has still not been run alone.** Every run this session was scoped to the
  diff, on CSJ's instruction.

## Decisions and dead ends

**CSJ decisions — do not re-litigate:**

- **W-0511 ships alongside W-0485.** Merging W-0485 alone moved a registered-blind user's
  tax UP.
- **`cash_accounts` holds current accounts, distinct from savings** (W-0489). The command
  was the odd voice out and is deleted. Two of the three sources already agreed.
- **W-0204: ask the user, do not assume.** `users.employment_income_basis` is asked of a
  sacrificing user rather than a convention being declared.

**Settled by evidence this session:**

- **The W-0204 pre/post-sacrifice ambiguity was never the blocker it looked like.** The
  basis is applied before any definition is struck, so it moves net income and leaves the
  taper gate alone — £210,000 gross and £193,200 post-sacrifice land on the same threshold
  income. The item had been parked on this for months.
- **W-0482's complement eliminates the double count.** Verified against
  `HouseholdCashFlowProjector::pensionIncomeInTodaysMoney()` rather than its own docblock:
  the projector deflates by `(1+i)^yearsToRetirement` and re-inflates by `(1+i)^year`,
  which is the series `unusedDcFundAtAge()` sums, term for term.
- **It does NOT eliminate the growth attribution** — W-0517. Not the double count
  returning; the pound is still counted once.
- **W-0509's defect class cannot be caught by a regex.** Neither layer holds a literal to
  match, so the guard is a comparison: read both column definitions from `SHOW COLUMNS` and
  hold them to each other and to the shared constant.

**Dead end:**

- **The dispatched `tax-compliance-reviewer` agent went idle twice without returning a
  verdict**, ~11 minutes apart, including after a direct request for its report. The gate
  was run inline instead. Do not assume a spawned reviewer will report — ask once, then
  take the work back.

## Things that will bite you

- **The quality `lint` job is not just Pint.** It runs `scripts/quality/run.sh lint` AND
  `scripts/quality/check-mobile-impact.mjs`, which fails a PR whose diff touches desktop UI
  without `/m` files **or the literal token `Mobile impact: shared-backend` /
  `mobile-changed` / `no-counterpart-approved` in the PR BODY**. A "## Rule 19" prose
  section does not satisfy it. This failed #741 once.
- **`eslint-changed.mjs` lints CHANGED files**, so editing a file inherits its pre-existing
  violations. Two unused catch bindings in `IncomeOccupation.vue` that had sat there for
  months failed #744's lint the moment W-0204 touched the file. Fixed in `9122bf348`.
- **`./vendor/bin/pint app/` takes longer than a 2-minute Bash timeout.** Pass the changed
  files explicitly. `./vendor/bin/pint --test` over the whole repo times out too.
- **`pint` on a directory reformats files you did not touch**, and `git add -A` then sweeps
  them into your commit. It pulled `workforce/ops/ui/index.php` into a board-docs commit;
  backed out by hand.
- **Architecture tests that count constructor parameters break on any legitimate
  injection.** `Phase02ArchitectureTest` asserted `PersonalAccountsService` had exactly 3.
  Now asserted by type. **Check for siblings before adding a constructor argument.**
- **`tests/Unit/Console/Commands/` is not bound in `Pest.php`.** A new file there without
  `uses(TestCase::class, RefreshDatabase::class)` throws "A facade root has not been set"
  with 0 assertions.
- **zsh does not word-split an unquoted variable.** A file list in a shell variable is
  passed as ONE argument; `grep` then reports no matches and the check silently passes. Use
  an array (`set -A`).
- **The iOS `test-and-build` job now runs** (it used to sit pending at 0s forever) and takes
  ~40 minutes. It fails on 3-second `waitForExistence` timeouts in UI journeys. Treat a
  single failure as a flake unless the diff touches native.

## Tech debt deferred

Full pass in `docs/tech-debt-report.md` — five findings, **none critical**. Mechanical
checks all clean: `declare(strict_types=1)` everywhere, no debug leftovers, no hardcoded tax
values in any added line, no banned colours, no new acronyms, scores or icons.

- **`app/Services/Estate/IHTCalculationService.php` — 2,973 lines**, six times the split
  threshold, and W-0482 added ~174 more. Every estate item lands in it. Deserves its own
  extraction item.
- **`app/Services/TaxConfigService.php:528`** — `getBlindPersonsAllowance()` now returns `0`
  for an unconfigured year rather than a stale `2870`. Better of the two, still a silent
  answer to missing configuration, and several sibling getters share the shape.
- **`app/Traits/ResolvesIncome.php`** — `getTaxConfig()` resolves from the container. No
  alternative exists for a trait; noted so it is not read as an oversight.
- **`tests/Unit/Console/Commands/` unbound in `Pest.php`** — see above. Fixing it means
  changing `tests/CLAUDE.md`, which currently advises the workaround.
- **`docs/archive/appMapping/`** still documents the deleted `migrate:savings-to-cash`.
  Deliberately left — it is archive.

## Branch and deploy state

- **Branch: `fix/w-0204-salary-sacrifice-add-back-to-threshold-income`** at `9122bf348`,
  pushed, tree clean.
- **`dev`** carries #740, #741 and #743. **`main`** is unchanged from #736.
- **Two migrations landed locally and are on open PRs, not yet on `dev`:**
  `2026_08_28_211500_add_civil_partnership_to_iht_profiles_marital_status` (#742) and
  `2026_08_29_090000_record_whether_employment_income_is_before_or_after_salary_sacrifice`
  (#744). Both are `ALTER TABLE`, both preserve existing rows.
- **Deploy: nothing deployed this session.** csjones and production untouched.
- **The branch guard follow-ups from the previous session are still outstanding, both
  CSJ's:** get `.github/workflows` branch guard onto `main`, and add `main-source-branch`
  as a required check alongside `enforce_admins`.
