# CSJTODO — Fynla

*Last updated: 2026-08-29 session 1 — board work. Three PRs merged (#740, #741, #743), two
open (#742, #744), four board items filed. Handover:
handover/August/29/handover-2026-08-29-session-1.md*

## Open, and what each needs

- [ ] **#742 — W-0509** (a civil partnership could not save an Inheritance Tax profile at
      all — critical). **Every check green except `test-and-build`**, the iOS native job,
      which failed once on a 3-second `waitForExistence` in a UI journey and was re-run.
      That test is unrelated to the change (a validation rule and a DB enum). Check the
      re-run, then merge.
- [ ] **#744 — W-0204** (salary sacrifice not added back to threshold income). Full CI was
      running on `9122bf348` at session close. Already green locally: 358 passed, 23 vitest.

## Next, in order

- [ ] **`W-0512` + `W-0517` together.** `PensionProjector:219` credits `pot × safe
      withdrawal rate` every retired year from a fund it never reduces, inflating
      `projected_cash` for every household with a pension (W-0512). W-0517 is the other
      half — the estate residual keeps growth on pounds that were withdrawn and sat in cash
      earning nothing, roughly doubling it over a 20-year retirement. **Fix them together:**
      W-0517 subtracts W-0512's series, so future-valuing one half while the other is still
      a perpetuity buys false precision rather than accuracy.
- [ ] **The rest of the filed items, in severity order:** `W-0513`, `W-0514`, `W-0508`,
      `W-0515`, `W-0510`, `W-0516`, `W-0518`.
- [ ] **Then the queued high:** `W-0037 W-0050 W-0133 W-0138 W-0139 W-0144 W-0155 W-0171
      W-0222 W-0226 W-0227 W-0462 W-0486 W-0490 W-0495`, then medium, then low.
- [ ] **One full-suite run, alone, as a consolidation point.** Outstanding for three
      sessions. **One Pest process at a time** — two share `laravel_testing` and deadlock
      into failures indistinguishable from real breakage. A dispatched reviewer agent counts
      as a second process.

## For CSJ

- [ ] **The branch guard is still not biting.** Two steps remain: the workflow file has to
      reach `main`, and `main-source-branch` must be added as a required status check
      alongside `enforce_admins`.
- [ ] **Two migrations are on open PRs, not yet on `dev`** — the `iht_profiles` marital
      status enum (#742) and `users.employment_income_basis` (#744). Both `ALTER TABLE`,
      both preserve existing rows.

## Tech debt

Full pass in `docs/tech-debt-report.md` — five findings, none critical.

- [ ] **`IHTCalculationService.php` is 2,973 lines**, six times the split threshold, and
      every estate item lands in it. Deserves its own extraction item.
- [ ] **`tests/Unit/Console/Commands/` is unbound in `Pest.php`**, so every file there
      declares its own base case. Fixing it means changing `tests/CLAUDE.md`, which
      currently advises the workaround.
- [ ] **12 open board items still carry no `severity`** — grandfathered, do not add a guard.

## Standing traps

- **The `lint` CI job is not just Pint.** It also runs `check-mobile-impact.mjs`, which
  fails any PR touching desktop UI without `/m` files **or the literal token
  `Mobile impact: shared-backend` / `mobile-changed` / `no-counterpart-approved` in the PR
  body**. Prose about Rule 19 does not satisfy it.
- **`eslint-changed.mjs` lints CHANGED files**, so editing a file inherits its pre-existing
  violations.
- **`./vendor/bin/pint app/` exceeds a 2-minute timeout** — pass changed files explicitly.
  Running it on a directory also reformats files you did not touch, which `git add -A` then
  sweeps into your commit.
- **Architecture tests that count constructor parameters** break on any legitimate
  injection. Check for them before adding a constructor argument.
- **The iOS `test-and-build` job runs now** (~40 min) and flakes on 3-second
  `waitForExistence` timeouts. One failure is a flake unless the diff touches native.
