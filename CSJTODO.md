# CSJTODO — Fynla

*Last updated: 2026-08-29 session 2 — six PRs merged (#744–#749), one open (#750). The
board's `gated` bucket was triaged: all 96, and not one is fully accepted. Handover:
handover/August/29/handover-2026-08-29-session-2.md*

## Next session starts here

- [ ] **ASK CSJ FIRST — `W-0523`.** `PersonalizedTrustStrategyService` answers "what does
      this trust transfer cost on death?" twice, differently.
      `buildImmediateCLTStrategy()` charges `(amount − availableNRB) × rate` less the 20%
      lifetime charge already paid — correct, and matches CSJ's ruling that a trust
      transfer is a CLT with an immediate 20% charge over £325,000.
      `calculateMultiCycleDeathCharge()` charges the **gross** amount, no band, no credit.
      Both overstate and they compound, in a figure shown to the user as the cost of a
      strategy the app recommends. **The decision needed: how does the nil rate band
      cumulate across seven-year cycles?** IHTA 1984 s7(1) cumulates over the seven years
      before *each* transfer, so a cycle's band depends on the cycles before it. Modelling,
      not refactoring — do not guess it.

- [ ] **`W-0154` (critical) — the tractable way into the gated 96.** 7 of 16 acceptance
      criteria ticked, so there are **nine written criteria** to check rather than an
      open-ended audit. Best-shaped item on the board for real progress.

- [ ] **`W-0524` — Agricultural Property Relief (high).** CSJ, 2026-08-29: *"the four
      reliefs are work, and need to be done."* An estate holding farmland gets no relief at
      all. **The trap:** `cap_shared_with_bpr: true` means Agricultural and Business
      Property Relief draw on **ONE** £2,500,000 allowance — a parallel copy of the BPR
      allocator gives a household holding both £5,000,000 where the law gives £2,500,000.
      Extend `EstateAssetAggregatorService::applyBusinessPropertyRelief()` to allocate
      across both; do not duplicate it. Then `W-0525`, `W-0526`, `W-0527`.

## THE TRAP — before triaging anything on this board

**A test citing a W-number does NOT mean the item is done.** 87 of the 96 gated items have
one, which reads as "fixed and guarded". `W-0463` sits in that bucket with 9 test citations
and is genuinely unfinished — four configured reliefs implemented by nothing. **Citations
measure attention, not completeness**; broad items attract tests for the first slice of work
and keep citing that slice for ever.

**The signal that works is the item's own acceptance checklist.** Across the 96: **zero**
fully ticked, 21 with none ticked, 13 partial, 62 whose Acceptance was never written as a
checklist. `W-0154` reads 7 of 16 while carrying 26 citations.

Three times in one session a single proxy was mistaken for a complete answer — the
unused-injection sweep that ignored traits and inheritance, the board reconciliation that
matched one commit style and missed 8 items, and this. **Name the ways a true positive could
hide from your pattern, and check each, before quoting a number.**

## Open

- [ ] **#750 — W-0522** (taper ladder read from configuration; CLT type applied). Green on
      everything except Feature and Unit, which were still running at close.

## Next, in order

- [ ] The three **no-trace highs** — `W-0203` (a mortgage counted twice), `W-0255` (the
      "80% Probability" band drawn as a straight line), `W-0344` (a one-sided spouse link
      discloses the other account). No test and no application file cites any of them, so
      they are the likeliest of the 96 to be genuinely untouched.
- [ ] The rest of the gated 96, off
      `workforce/ops/reports/2026-08-29-gated-triage.md`, severity order.
- [ ] **The queued high:** `W-0037 W-0050 W-0133 W-0138 W-0139 W-0144 W-0155 W-0171 W-0222
      W-0226 W-0227 W-0462`, then medium, then low.
- [ ] **One full-suite run, alone, as a consolidation point.** Outstanding **four** sessions.
      **One Pest process at a time** — proved this session: a background run plus a
      foreground run gave 316 phantom failures from `laravel_testing` contention.

## For CSJ

- [ ] **The branch guard is still not biting.** The workflow file has to reach `main`, and
      `main-source-branch` must be added as a required status check alongside
      `enforce_admins`.
- [ ] **`main` is still diverged from `dev`.** PR #736 holds the reconciliation and is
      deliberately unmerged, because merging it equals a release.
- [ ] **W-0512/W-0517 is user-visible and nobody has looked at it.** The year-by-year cash
      flow table now steps DOWN at the fund's depletion age instead of running flat.
      Intended, but worth a browser pass before release.

## Tech debt

- [ ] **52 unused private injections remain** outside the TaxConfigService cluster.
      Clusters: `RetirementAgent` (6), `GoalsAgent` (3),
      `ComprehensiveProtectionPlanService` (3), `EstateActionDefinitionService`'s
      `MortgageStore` + `PropertyStore`. **Each needs judgement** — some are dead, some may
      be a capability silently unwired, as `projectInvestments()` was.
- [ ] **`IHTCalculationService` is still ~2,391 lines.** Next seam is the cache/persistence
      block — `getCachedCalculation`, `isCurrentResultShape`, `charitableBequestFingerprint`,
      `generateHashes`, `saveCalculation`, `invalidateCache`, ~230 lines.
- [ ] **`0.047` as a fallback investment return has three homes** —
      `EstateProjectionService::getFallbackGrowthRate()`, `LifeCoverCalculator`,
      `LifePolicyStrategyService::FALLBACK_INVESTMENT_RETURN_RATE`.
- [ ] **`database/schema/mysql-schema.sql:3864`** still carries the stale
      `enum('pensions','investments')`. The W-0520 migration corrects any database built
      from it, so the dump is wrong rather than harmful.
- [ ] **`tests/Unit/Console/Commands/` is unbound in `Pest.php`.** Fixing it means changing
      `tests/CLAUDE.md`, which currently advises the workaround.
- [ ] **12 open board items still carry no `severity`** — grandfathered, do not add a guard.

## Standing traps

- **There is no PHPStan, Psalm or Larastan in this repo.** `composer lint:php` is a syntax
  check plus Pint, a formatter. Nothing can see an unused injection or an uncalled method.
- **One Pest process at a time.** Two share `laravel_testing` and deadlock into failures
  indistinguishable from real breakage. A dispatched reviewer agent counts as a second.
- **A PR branched before a fix merges will fail CI on that fix.** Merge `dev` in before
  blaming the change.
- **BSD `sed` does not support `\+`.** Use `sed -E` — silent slug damage otherwise, and
  W-0490 exists because of exactly that.
- **`git stash pop` on a clean tree pops someone else's stash.** Two unrelated stashes are
  sitting in the list.
- **Count board files, not `^status:` lines** — `W-0009` has a second `status:` in its body.
- **`workforce/ops/log/` is the append-only JSONL the control centre reads.** Prose reports
  go in `workforce/ops/reports/`.
- **The `lint` CI job is not just Pint.** It also runs `check-mobile-impact.mjs`, which
  fails any PR touching desktop UI without `/m` files **or the literal token
  `Mobile impact: shared-backend` / `mobile-changed` / `no-counterpart-approved` in the PR
  body**. Prose about Rule 19 does not satisfy it.
- **`eslint-changed.mjs` lints CHANGED files**, so editing a file inherits its pre-existing
  violations.
- **`./vendor/bin/pint app/` exceeds a 2-minute timeout** — pass changed files explicitly.
  Running it on a directory also reformats files you did not touch.
- **Architecture tests that count constructor parameters** break on any legitimate
  injection. Check before adding a constructor argument.
- **iOS is VIEW ONLY and is checked separately (CSJ, 2026-08-29).** `test-and-build` does
  not gate a merge on a backend or web change — ~40 minutes, flakes on 3-second
  `waitForExistence` timeouts. **The exception is a diff touching `ios-native/`.**
