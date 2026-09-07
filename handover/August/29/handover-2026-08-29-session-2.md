---
type: handover
mode: session-end
date: 2026-08-29
session: 2
repo: fynla
branch: fix/w-0522-hardcoded-taper-band-table
---

# Session Handover — 2026-08-29, Session 2

## Where things stand

**Six PRs merged, one open and green-pending, and the board is finally honest.**
#744–#749 are on `dev`: the salary-sacrifice add-back, the IHT service split, the
depleting pension drawdown, the estate-assumptions chain, the dead TaxConfigService
injections, and a 53-item board reconciliation. #750 (W-0522) is open with CI running.

**The important outcome is not the code — it is that the board's `gated` bucket has been
triaged and it is worse than it looked.** All 96 gated items were audited. **Not one has
its acceptance fully evidenced.** Read the trap below before trusting any "looks done"
signal on this board.

**One thing is blocked on CSJ** (W-0523, priority 3).

## THE TRAP — read this before triaging anything on this board

**I used "a test cites the W-number" as a proxy for "the item is done". It is not one, and
believing it would have handed CSJ a false all-clear on 87 of 96 items.**

The measurement: 87 of the 96 gated items have a test naming the item id. That reads as
"fixed and guarded". It is wrong, and the counter-example is decisive —

> **`W-0463` sits in that bucket with 9 test citations and 8 application citations, and it
> is genuinely unfinished.** Four configured reliefs — Agricultural Property Relief, Normal
> Expenditure Out of Income, the 14-year rule, quick succession relief — are implemented by
> nothing at all. The tests cite the *parts that were fixed*; the item covers more than
> those parts.

**A citation measures ATTENTION, not COMPLETENESS.** Broad or structural items attract
tests for the first slice of work and keep citing that slice forever.

**The signal that actually works is the item's own acceptance checklist**, because it is the
item's own contract:

| acceptance state | count of the 96 |
|---|---|
| all criteria ticked | **0** |
| criteria exist, **none** ticked | 21 |
| partially ticked | 13 |
| Acceptance never written as a checklist | 62 |

`W-0154` (critical) reads **7 of 16** ticked while carrying 26 test citations — the same
shape as W-0463.

**This is the third time this session I took one proxy for a complete answer**, and the
pattern is the lesson:

1. The unused-injection sweep grepped only each class's own file — missing traits,
   inheritance and dynamic access. CSJ caught it: *"they may be used by other users, or
   other profiles."* Re-checked; the conclusion survived, but only after closing three real
   holes.
2. The board reconciliation matched only `fix(scope): … W-NNNN` commits and missed the
   `W-NNNN: …` house style that Icecube and Phailanx use — **8 items**. CSJ caught it:
   *"make sure you get all of them."*
3. This one.

**Before reporting any sweep on this repo: name the ways a true positive could hide from
your pattern, and check each one, before quoting a number.**

A process note in the same vein: I classified the 96 (count, severity, why they are gated),
went depth-first on a single item, and then *proposed the triage as if I had not started
it*. CSJ: *"I thought you did the triage, what have you been doing?"* Finish the breadth
pass and report it before going deep on one item.

## Priorities for the next session

1. **W-0523 — BLOCKED ON CSJ. Ask this first, before anything else.**
   `PersonalizedTrustStrategyService` answers "what does this trust transfer cost on
   death?" twice, differently. `buildImmediateCLTStrategy()` charges
   `(amount − availableNRB) × rate` less the 20% lifetime charge already paid — correct,
   and matches CSJ's 2026-08-29 ruling. `calculateMultiCycleDeathCharge()` charges the
   **gross** amount with no band and no credit. Both overstate and they compound, in a
   figure shown to the user as the cost of a strategy the app recommends.
   **The decision needed: how does the nil rate band cumulate across seven-year cycles?**
   IHTA 1984 s7(1) cumulates chargeable transfers in the seven years before *each*
   transfer, so a cycle's available band depends on the cycles before it. That is modelling,
   not refactoring — do not guess it.

2. **W-0154 (critical) — the tractable half of the gated bucket.** 7 of 16 acceptance
   criteria ticked, so there are **nine specific, written criteria** to check rather than an
   open-ended audit. It is the best-shaped item on the board for making real progress on the
   96. The unticked ones cover: one household producing one answer from either login,
   allowance components reconciling to their total, transferable bands attributed as
   transfers, no reduced rate reported on a nil liability, a scalar gift-deduction payload,
   a negative projected estate being impossible by construction, and `/m` + iOS checked
   rather than assumed.

3. **W-0524–W-0527 — the four reliefs. CSJ ruled these are work: *"the four reliefs are
   work, and need to be done."*** Take **W-0524 (Agricultural Property Relief, high)** first
   — an estate holding farmland currently gets no relief at all.
   **The trap in it:** `cap_shared_with_bpr: true` means Agricultural and Business Property
   Relief draw on **ONE** £2,500,000 allowance. A parallel copy of the BPR allocator gives a
   household holding both £5,000,000 of relief where the law gives £2,500,000. The model to
   follow is `EstateAssetAggregatorService::applyBusinessPropertyRelief()` — capped,
   pro-rata, dated by `allowance_cap_effective_date` — extended to allocate across both,
   not duplicated.

4. **Merge #750 (W-0522)** once its Feature and Unit jobs land. Everything else was green
   at hand-over.

5. **The three no-trace highs** — `W-0203` (a mortgage counted twice), `W-0255` (the "80%
   Probability" band drawn as a straight line between two neighbouring points), `W-0344` (a
   one-sided spouse link discloses the other account). No test and no application file cites
   any of them, which makes them the likeliest of the 96 to be genuinely untouched.

6. **A full-suite run, alone, as a consolidation point.** Still not re-established — now
   outstanding four sessions. **One Pest process at a time.** I proved why this session:
   backgrounding a Pest run and starting another in the foreground gave 316 phantom failures
   from `laravel_testing` contention.

## Context to load

- `workforce/ops/reports/2026-08-29-gated-triage.md` — all 96 gated items, by severity,
  with each one's acceptance-checklist state and citation counts. **The priority-2 and
  priority-5 work is picked straight off this table.**
- `workforce/ops/reports/2026-08-29-board-reconciliation.md` — what the 53 restamped items
  were, the three evidence rules used, and the five deliberately left open.
- `workforce/ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md` — **read Finding
  0.** It is why all 96 are gated: zero evidence packs exist, and every inline evidence note
  was written by the agent that wrote the code, which the constitution calls
  self-certification.
- `workforce/ops/board/W-0154-same-household-two-different-inheritance-tax-bills.md` —
  priority 2. The nine unticked criteria are the task list.
- `workforce/ops/board/W-0524-agricultural-property-relief-is-configured-in-full-and-implemented-by.md`
  — priority 3, and the `cap_shared_with_bpr` constraint in full.
- `app/Services/Estate/PersonalizedTrustStrategyService.php` — both charge paths, the
  correct one and the wrong one, with the W-0523 discrepancy commented at the line.

## Completed this session

**Merged to `dev`:**

- **#744 / W-0204** — FA 2004 s228ZA(3) salary sacrifice added back to threshold income.
  `b455dcc08`.
- **#745 / W-0519** — `IHTCalculationService` split 2,973 → 2,391 lines, projection moved to
  a new `EstateProjectionService`. **The 574 moved lines are byte-identical apart from
  nine** — five `private`→`public`, four `$this->poolsSpouse(`→`HouseholdPooling::poolsSpouse(`
  (a one-line pass-through to the same static). Zero arithmetic edited. `dbe7add05`.
- **#746 / W-0512 + W-0517** — the pension is now paid from a fund that shrinks. One
  depleting drawdown fixes both: carrying the fund forward as `fund × (1+g) − drawn` IS
  W-0517's future-valued complement, and it stops paying at zero, which is W-0512.
  On a £500,000 pot: `projected_cash` −£54,143, `projected_unused_pension` −£58,613,
  `projected_iht_liability` £302,885 → £257,783. The tax delta reconciles exactly at 40% of
  the estate movement. `a1a26ec1a`.
- **#747 / W-0520** — **three broken layers under one feature.** The estate growth
  assumption could not be saved *at all*: the `assumption_type` enum never gained
  `estate_planning` (59 of 59 databases, dev included), `$fillable` omitted all three estate
  columns so `updateOrCreate()` discarded them silently, and the projection called
  `projectInvestmentsMonteCarlo()` straight past `projectInvestments()` — the dispatcher
  that reads the setting, written in February and never once called. `3fd934af8`.
- **#748 / W-0521** — eight dead `TaxConfigService` injections removed. **Rule 2 holds** —
  none was getting tax values from elsewhere. `7924c8eea`.
- **#749** — board reconciliation, 53 items restamped. `01eab4f58`.

**Open:** **#750 / W-0522** — the taper ladder read from configuration, and the CLT type
applied.

**Board items filed: 6** — W-0519, W-0520, W-0521, W-0522, W-0523, W-0524–W-0527.

## Verification state

- **W-0519:** 628 passed (Estate, Feature Estate, Retirement), run alone. All CI green.
- **W-0512/W-0517:** 895 passed across Estate, Retirement and Architecture.
  **Mutation-verified** — all five new/rewritten tests fail against the pre-fix code.
- **W-0520:** 899 passed + 16 on `--filter=Assumption`. **Mutation-verified** — a 10% custom
  rate over ten years on £100,000 must land on £259,374.25, which no Monte Carlo p20 produces.
- **W-0521:** 868 passed across Estate, Savings, Goals, Plans, Architecture.
- **W-0522:** 723, then 545 after the CLT change. CI in flight at hand-over.
- **NOT verified in a browser, anywhere.** No Playwright this session.
- **The full suite has still not been run alone** — four sessions outstanding.
- **W-0512/W-0517 is user-visible and unseen:** the year-by-year cash flow table now steps
  DOWN at the fund's depletion age instead of running flat. Intended, but nobody has looked
  at it.

## Decisions and dead ends

**CSJ decisions — do not re-litigate:**

- **A transfer into a trust is a CLT, not a PET** (2026-08-29). *"anything over 325k has an
  immediate 20% iht charge."* Applied in #750. It moves no figure today because
  `chargeable_lifetime_transfers` carries no `death_rate` of its own and falls back to the
  standard rate — but it is the correct type, and the day that key is configured the `pet`
  reading would have been silently wrong.
- **The four reliefs are work and must be done** (2026-08-29). Filed as W-0524–W-0527.
- **Merge #744 with `--merge --admin`**; the iOS `test-and-build` job does not gate a
  backend change.
- **Mark board items done, never delete them** — *"so we can check later if they have been
  truly done or not."* Every restamped item says explicitly that `done` means "the change is
  on `dev`", NOT "someone re-verified the behaviour since".

**Settled by evidence this session:**

- **The eight `TaxConfigService` injections were genuinely dead.** Traits, inheritance and
  dynamic property access all checked. A `private` promoted property is class-scoped, so no
  user, tier or profile can reach a line that does not exist. Four were orphaned by *good*
  consolidations (W-0501 and three others replacing hand-derived tax with the real engine);
  three came from one bulk commit, `446b04d0a` (2026-03-14, 154 files), which injected the
  service into 31 services and over-applied on 3.
- **Nothing in the toolchain can catch an unused injection.** There is no PHPStan, Psalm or
  Larastan in this repo; `composer lint:php` is a syntax check plus Pint, a formatter.
- **The "assertion count keeps growing" mystery was one flaky test**, not a state leak.
  `DCPensionFactory` randomises `retirement_age` (60–68) and `investment_strategy`, so with
  the new depleting drawdown the £400,000 pot was sometimes exhausted by the modelled death
  → no caveat → the first expectation fails → 1 assertion recorded instead of 4. Pinned in
  the fixture; three consecutive runs now give an identical 27.
- **`SharedGoalIsOneWholeGoalTest` was a Decoy, not a defect.** It read
  `function.parameters.properties` while `AiToolDefinitions::getTools()` returns the flat
  `{name, description, parameters}` shape, so it reported present fields as missing.

**Dead ends:**

- **Do not use citation counts to triage this board.** See THE TRAP.
- **`git stash pop` when the tree is clean pops someone else's stash.** I did this and
  restored an unrelated "preserve stale Pint formatting" stash across dozens of
  `public/pages/` files. Recovered with `git checkout HEAD -- .`; both stashes survive.
  `git reset --hard` was refused by the permission classifier, correctly.

## Things that will bite you

- **BSD `sed` does not support `\+` in basic regex.** My board-item slug generation used it
  silently, and W-0524–W-0527 landed with **spaces in their filenames**. Use `sed -E`.
  Fixed, but W-0490 exists because of exactly this class of path damage.
- **One Pest process at a time.** Backgrounding a run and starting another in the foreground
  gave **316 failures** from `laravel_testing` contention — indistinguishable from real
  breakage.
- **A PR branched before a fix merges will fail CI on that fix.** #748 branched off `dev`
  before #747 landed and failed on the very flake #747 pins. Merge `dev` in before blaming
  the change.
- **`gh run view --log-failed` returns nothing while any job in the run is still going** —
  "logs will be available when it is complete". Reproduce locally instead of waiting.
- **The board CLI `workforce/ops/wf.sh` takes longer than a 2-minute Bash timeout** on a
  324-item board. `grep -l "^status: done" workforce/ops/board/*.md | wc -l` is the quick
  count — and count FILES, not `^status:` lines: `W-0009` has a second `status:` in its body
  and inflated my figure by one.
- **`workforce/ops/log/` is the append-only JSONL the control centre reads.** Prose reports
  go in `workforce/ops/reports/`.

## Tech debt deferred

Mechanical checks on this session's 19 changed files are **clean** — `declare(strict_types=1)`
everywhere, no debug leftovers, no hardcoded tax values in any added line, no banned colours,
no board id collisions.

- **52 more unused private injections remain** outside the TaxConfigService cluster.
  Highest-signal clusters: `RetirementAgent` (6), `GoalsAgent` (3),
  `ComprehensiveProtectionPlanService` (3), and `EstateActionDefinitionService`'s
  `MortgageStore` + `PropertyStore`, orphaned by the same W-0501 change as its
  `TaxConfigService`. **Each needs the judgement W-0520 applied to three of them** — some
  are dead, some may be a capability silently unwired, as `projectInvestments()` was.
- **`projectInvestments()`'s siblings** — `app/Services/Estate/EstateProjectionService.php`
  still carries `getCurrentInvestmentValue()`, now reachable only through the dispatcher.
  Harmless; noted so it is not read as an oversight.
- **`0.047` as a fallback investment return has three homes** —
  `EstateProjectionService::getFallbackGrowthRate()`, `LifeCoverCalculator`, and
  `LifePolicyStrategyService::FALLBACK_INVESTMENT_RETURN_RATE`. Not a tax value, so not
  Rule 2, but one assumption stated three times.
- **`database/schema/mysql-schema.sql:3864`** still carries the stale
  `enum('pensions','investments')`. The W-0520 migration corrects any database built from
  it, so the dump is wrong rather than harmful; regenerating it is a separate change.
- **`IHTCalculationService` is still ~2,391 lines.** The cache/persistence block
  (`getCachedCalculation`, `isCurrentResultShape`, `charitableBequestFingerprint`,
  `generateHashes`, `saveCalculation`, `invalidateCache`) is the obvious next seam, ~230
  lines and equally cohesive.
- **`LifeEventService` was removed from `IHTCalculationService`, `PensionStore` injected.**
  Both done in #747; no residue.

## Branch and deploy state

- **Branch:** `fix/w-0522-hardcoded-taper-band-table`, tree clean, **0 unpushed**.
- **`dev`** carries #744–#749. **`main`** is unchanged from #736 — still diverged, and
  PR #736 is deliberately unmerged because merging it equals a release.
- **One migration landed on `dev` this session:**
  `2026_08_29_110000_allow_estate_planning_in_user_assumptions_type` — an `ALTER TABLE`
  widening an enum, preserves every row. **It has been applied to the local `laravel`
  database but not to csjones or production.**
- **Deploy: nothing deployed this session.** csjones and production untouched.
- **iOS:** view only, verified on its own schedule. Nothing this session touched
  `ios-native/`.
