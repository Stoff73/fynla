---
type: handover
mode: session-end
date: 2026-09-01
session: 1
repo: fynla
branch: chore/board-verification-31-august
---

# Session Handover — 2026-09-01, Session 1

## Where things stand

Board loop, run to CSJ's rules: one item at a time, verify-before-fixing, test the
diff. **Board went 55 outstanding → 29.** Twenty-six items closed, gated or deferred;
33 commits, tree clean, nothing pushed.

The most important output is **not** an item that was closed. Working W-0488 surfaced a
production defect that affects every user with a mortgage, and it is deliberately
unfixed because the fix changes user-visible figures across three subsystems. It is
priority 1 below and it needs a board id.

## Priorities for the next session

1. **BUG — emergency runway is overstated for every mortgaged user, by up to 4.7×.
   Not yet a board item; open one first.**

   `users.monthly_expenditure` **excludes mortgage payments by schema** — there is no
   mortgage expenditure column, and payments live only on `mortgages.monthly_payment`.
   The runway divides cash by that figure alone.

   **The application already computes the correct total and the runway does not use
   it.** `UserProfileService::getExpenditureBreakdown():314-323` sums manual
   expenditure **plus** `getFinancialCommitments()`, which at `:994` builds "Property
   Expenses (mortgage + council tax + utilities + maintenance)" and is documented as
   matching the Expenditure tab. The runway path uses
   `ResolvesExpenditure::resolveMonthlyExpenditure()` (`:34`), which returns
   `users.monthly_expenditure` and stops. **Two household-outgoings figures, one wrong
   wherever there is a mortgage** — a Rule 20 shape.

   Measured on all six personas after re-seeding, 2026-09-01:

   | persona | cash | manual | commitments | runway shipped | runway correct |
   |---|---:|---:|---:|---:|---:|
   | young_family | 15,950 | 1,951 | 2,415 | 8.18 | **3.65** |
   | peak_earners | 102,000 | 1,225 | 4,549 | **83.27** | **17.67** |
   | entrepreneur | 169,180 | 4,500 | 10,213 | 37.6 | **11.5** |
   | young_saver | 10,700 | 1,033 | 578 | 10.36 | 6.64 |
   | retired_couple | 103,500 | 1,065 | 1,648 | **97.18** | **38.15** |
   | student | 1,200 | 340 | 55 | 3.53 | 3.04 |

   Commitments exceed manual expenditure for **five of the six**.

   **Why it was not fixed here:** `ResolvesExpenditure` has three consumers —
   `SavingsAgent:104` (the runway headline), `AutoRiskCalculator:470` (risk scoring)
   and `LifeEventAllocationService:587` (life-event affordability). Changing the basis
   moves the headline runway, the risk score **and** life-event allocation for every
   mortgaged user at once. That is not a persona-data change and did not belong inside
   W-0488. The fix itself is small — both figures already exist.

   **Ask CSJ before changing the basis**: it alters figures users have already seen.
   The reproduction and the full measurement are on the W-0488 board file.

2. **Continue the board loop at W-0492.** 29 outstanding in `tasks.md`. The loop is
   `.claude/skills/board-loop/SKILL.md` — CSJ requires each of the nine steps
   **announced by number before executing it**, and no work that is not one of those
   steps. See "Decisions" below; this was the session's main friction.

3. **Pre-existing architecture failure, not from this session.**
   `Tests\Architecture\StoreBoundary` fails on
   `app/Services/UserProfile/UserProfileService.php:8` — `use App\Models\DCPension;`.
   Confirmed present at session-start commit `ba67234c4`. One failure, unrelated to
   any change here.

4. **iOS items are deferred, not done.** W-0090, W-0243, W-0311, W-0416 carry
   `surfaces: [ios]` and are marked `deferred-ios`. CSJ ruled 2026-08-31 that the board
   loop is **web and /m only**. Do not touch `ios-native/`.

## Context to load

- `tasks.md` — the board. 29 outstanding, counts at the top. Regenerate, never
  hand-edit the counts.
- `.claude/skills/board-loop/SKILL.md` — the nine steps. CSJ treats this as gospel and
  stopped the session twice for deviating; read it before doing anything else.
- `app/Traits/ResolvesExpenditure.php` — priority 1's wrong side of the fork.
- `app/Services/UserProfile/UserProfileService.php:314` and `:994` — priority 1's
  correct side. Read both before proposing the fix.
- `app/Services/Savings/EmergencyFundCalculator.php:26` — where the division happens.
- `workforce/ops/board/W-0488-peak-earners-expenditure-looks-understated.md` — the full
  measurement and reproduction for priority 1.

## Completed this session

Closed: W-0054, W-0110, W-0145, W-0152, W-0153, W-0156, W-0196, W-0197, W-0198,
W-0199, W-0208, W-0258, W-0259, W-0275, W-0280, W-0324, W-0334, W-0337, W-0346,
W-0351, W-0392, W-0398, W-0414, W-0442, W-0443, W-0488.

Gated on CSJ (analysis done, decision outstanding, one-line question on each board
file): W-0178, W-0200, W-0426, W-0472, W-0476.

Deferred (iOS): W-0090, W-0243, W-0311, W-0416.

Worth singling out:

- **W-0196 / W-0197** — seven retirement-age constants and two State Pension scalars
  replaced by one resolver each. State Pension age is now a **cohort schedule** in tax
  config, not a scalar; `current_spa`/`future_spa` are retired and the resolver throws
  rather than falling back.
- **W-0275** — eight consumers routed through `DependantsReach`; two hand-rolled spouse
  traversals deleted, each with real bugs (a Carbon-vs-string comparison, a
  non-reciprocal reach).
- **W-0443** — asset-type vocabulary went from **fourteen** copies to one; the sweep
  found a filter dropdown missing three of the ten values.
- **W-0346** — closed with **no code change**: the item was stale, W-0347 had already
  built spouse revoke. CSJ intervened here; see Decisions.

## Verification state

- **Clean full PHP suite at `9b54719b3`: 3 failed, 30 skipped, 8,304 passed.** Two
  failures were `StoreBoundary` (one pre-existing, one mine, now fixed); the third did
  not recur.
- Architecture suite after the fix: **1 failed** (the pre-existing `DCPension` one),
  150 passed.
- Frontend: **1,275 passed, 131 files** — full suite, green.
- **An earlier full run reported 481 failures. That was my own doing** — I ran targeted
  suites against the same MySQL database while the full suite was running, and
  `RefreshDatabase` truncates. Do not chase it; the clean run above is authoritative.
- **Not verified:** no browser testing at all this session. W-0442 acceptance 4
  (browser verification) and W-0208 acceptance 4 remain explicitly not done, and say so
  on their board files.
- **Not verified:** nothing deployed anywhere.

## Decisions and dead ends

- **CSJ, twice, on process:** the board-loop skill is not guidance. Every deviation this
  session had the same shape — the loop's next step was "take the next item" and I
  substituted something I judged more useful (reading ahead during a build, a
  spouse-linking survey, stopping to write a status report). CSJ now requires each step
  **announced by number before it is executed**. Nothing outside the nine steps.
- **CSJ: no iOS.** The board loop is web and `/m` only. iOS items defer, marked
  `deferred-ios`. Swift changes started against W-0090 were reverted.
- **Board items go stale — step 3 is the whole defence.** W-0346 described an enum with
  no `revoked` value and a gate ignoring the permission row; W-0347 had already rebuilt
  both. I began reasoning from the item text and CSJ stopped me before I rewrote working
  code. **Open the cited code first, every time, even for an item filed yesterday.**
- **W-0280's own first finding was false** and the correction is the useful part: a row
  carries exactly one `user_id`, so two spouses' queries are disjoint and no row is ever
  double-counted. The real defect is reach-and-fraction, and it **cancels at household
  level** — which is why it survived four sweeps. F-0024 corrected in place (W-0337).
- **Measure before concluding.** On W-0476 my first measurement returned 500 on both
  POSTs (the endpoint takes `first_name`/`last_name`, not `name`), and both payloads
  came back identical — which would have supported the **opposite** conclusion.
- **W-0476 cannot close alone.** Unifying the payload would not close the oracle:
  `revoke():455` returns 404 with no permission row, so the Withdraw button
  distinguishes the two addresses anyway. It closes with W-0472's retention decision.
- **Guards must read the source, not service output.** Several of these defects survived
  earlier sweeps because every existing test asserted on what a service returned. The
  guards added this session read the files themselves, and each was mutation-verified.

## Things that will bite you

- **Pint re-adds an import for a `{@see}` docblock reference**, which `StoreBoundary`
  then rejects for services using models. Write the reference as plain text in
  backticks. Cost this session two rounds on `RetirementAgeResolver`.
- **Never run a targeted suite while a full suite is running.** Same MySQL database,
  `RefreshDatabase` truncates, and you get hundreds of phantom failures.
- **`./vendor/bin/pint app/` times out** at 2 minutes. Format only the changed files.
- **`pest --filter=""` matches nothing** and exits 0 — it looks like a pass.
- Holdings are polymorphic: `holdable_id`/`holdable_type`, not `investment_account_id`.
- `wills.spouse_primary_beneficiary` is NOT NULL — the factory needs it passed.
- `MortgageResource` and any `when()` resource need `->resolve()` in tests, not
  `->toArray()`; the latter leaves `MissingValue` under the key and passes on a withheld
  field.
- **The full PHP suite takes ~30 minutes** and buffers all output until it finishes.

## Tech debt deferred

The `tech-debt-session` pass over 178 changed files was **clean**: no missing
`declare(strict_types=1)`, no debug leftovers, no banned colour classes, no hex in
`<style>` blocks, no new acronyms in user-facing text.

Named on board files rather than fixed:

- `resources/mobile/components/CanonicalPortfolio.vue:23` — "OCF" unexpanded on `/m`
  (Rule 9). Pre-existing, flagged on W-0442.
- `resources/js/components/UserProfile/FamilyMembers.vue` — `spouseCreated` and
  `temporaryPassword` are now provably always `false`/`null`, feeding inert
  `SpouseSuccessModal` props. Removing them means editing that component (W-0472).
- `resources/js/components/Estate/WillPlanning.vue` — the three outstanding
  compliance-lead copy reviews (W-0108, W-0152, W-0153) are worth one batched review
  rather than three.
- W-0351 acceptance 3 — the sweep for other `v-if`s gating on fields their Resource
  never returns is **not done**. That is the read-boundary class, and W-0442 turned out
  to be another instance of it.

## Branch and deploy state

- Branch: `chore/board-verification-31-august`
- Unpushed commits: **33**
- Deploy status: nothing deployed. Not deployed to csjones or production.
