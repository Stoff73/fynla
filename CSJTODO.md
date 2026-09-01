# CSJTODO — Fynla

*Last updated: 2026-09-01 session 1 — board 55 -> 29 outstanding.
Handover: `handover/September/01/handover-2026-09-01-session-1.md`*

## The board position

Computed from the 327 files, not from a register. `tasks.md` in the repo root is the
live checklist and is **generated** — regenerate it, never hand-edit the counts.

| | |
|---|---|
| items | 327 |
| **resolved** | **298** |
| **outstanding** | **29** — 24 medium, 5 low |
| critical | **0** |
| high | **0** |

**77 closed on 31 August, 26 more on 1 September.** The rule is unchanged — **a citation
is not a verification.** Only reading the code and finding the defect gone counts, and it
earned its keep again: **W-0346 was stale**, describing an enum and a gate that W-0347 had
already rebuilt. CSJ stopped the session before working code was rewritten from a board
description.

**The second lesson of the day: items are consistently bigger than filed.** W-0503 was
one Tailwind class and turned out to be 31. W-0453 was 2 sites and was 5. W-0424 was one
broken gate and was three faults across two mechanisms. **The guards found the extras,
not reading.**

## Next session starts here

- [ ] **BUG — emergency runway is overstated for EVERY mortgaged user, by up to 4.7x.
      No board id yet; open one first.** `users.monthly_expenditure` excludes mortgage
      payments by schema, and the runway divides cash by that alone.
      **The correct total already exists and the runway does not use it:**
      `UserProfileService::getExpenditureBreakdown():314` sums manual expenditure plus
      `getFinancialCommitments():994` (mortgage + council tax + utilities +
      maintenance); the runway path uses `ResolvesExpenditure:34`.
      Measured on all six personas — `peak_earners` shows **83.3 months against a real
      17.7**, `retired_couple` **97.2 against 38.2**; commitments exceed manual
      expenditure for five of six. **Ask CSJ before changing the basis:** it moves the
      runway headline, the risk score and life-event allocation together, through
      `SavingsAgent:104`, `AutoRiskCalculator:470` and `LifeEventAllocationService:587`.
      Full measurement on the W-0488 board file.
- [ ] **PR #759 — CSJ'S CALL.** Now ~101 commits with today's 33. Open against `dev`,
      unreviewed.
- [ ] **Tax-compliance review.** W-0367 (s19), W-0514, W-0508, W-0338, W-0470,
      W-0104–W-0109. **W-0462 is still the sharpest.** Add today's:
      **W-0197** (State Pension age is now a cohort schedule, not a scalar — it moves
      every projection) and **W-0392** (the will screen now states a different estate
      from the taxable one).
- [ ] **Browser verification — still nothing checked in a browser, two sessions running.**
      Today's best walks: **W-0351** (a mixed-rate mortgage's split now displays, web and
      `/m`), **W-0442** (holdings show units and prices on `/m`), **W-0259** (the median
      now sits beside the conservative band on three cards).
- [ ] **Continue the loop on the remaining 29** via `.claude/skills/board-loop/SKILL.md`
      and `tasks.md`, resuming at **W-0492**.
- [ ] **Five items are gated on a CSJ decision**, each with a one-line question at the
      end of its board file: **W-0178** (are repairs deductible — split the capture?),
      **W-0200** (first-class second life assured?), **W-0426** (is the Letter premium to
      READ?), **W-0472** (retain the invited address?), **W-0476** (closes with W-0472).

## Settled by CSJ — do not re-raise

- **W-0144** — revocation of former wills is the law; the 28-day survivorship period is
  standard drafting. Defaults unchanged, no prompt needed.
- **W-0155** — consent is a single accept button. There is no withdrawal journey.
  `declineCookies()` is the banner's Decline path, not dead code.
- **W-0524** — agricultural relief is a property-type design decision, deferred.
- **One PR, not split.** **No parallel agents, of any kind, for any purpose.**
- **The board loop is web and `/m` ONLY.** Every iOS item defers, marked `deferred-ios`
  (W-0090, W-0243, W-0311, W-0416). Do not touch `ios-native/` from the loop.
- **The board-loop skill is gospel, not guidance.** Each of the nine steps is announced
  by number before it is executed, and nothing outside them is done.

## Known issues

- **`public/build/` and `public/m-build/` are BOTH the csjones build** (base `/fynla/`).
  Local `localhost:8000` is a blank page until Vite runs; `/m/app/*` double-prefixes and
  404s, so **`/m` cannot be browser-verified locally.** Rebuilding overwrites what is staged.
- **Vite cannot use 5173** — `hermes-desktop` owns it and Fynla is `strictPort`. **5174
  works, 5176 does not**: the CSP allowlists only 5173/5174.
- **THE PERSONA BILL MOVED: `£343,512` → `£341,112`** (W-0367). `nrb_gift_deduction`
  £144,000, band £506,000, taxable estate £852,780. Household gross unchanged at
  £1,728,780. **Earlier handovers and vault notes carry the old figure.**
- **Persona passwords are `Password1!`**, not `password`. A 401 is probably not a bug.
- The iOS `test-and-build` CI job is **flaky, not a regression**.
- **`main` and `dev` are still diverged.** PR #736 holds the reconciliation and is
  deliberately unmerged, because merging it equals a release.
- **Never `git checkout -- <file>` to undo a mutation test.** It reverts to HEAD and
  destroys uncommitted fixes. Copy the file first.
- **Never run a targeted suite while the full suite is running** — same MySQL database,
  `RefreshDatabase` truncates, and you get hundreds of phantom failures. A run reported
  481 this session; a clean run was **3 failed, 8,304 passed**.
- **`Tests\Architecture\StoreBoundary` fails on `UserProfileService.php:8`**
  (`use App\Models\DCPension;`). Pre-existing at `ba67234c4`, not from this session.
- **Pint re-adds an import for a `{@see}` docblock class reference**, which
  `StoreBoundary` then rejects. Write the reference as plain text in backticks.
- **`./vendor/bin/pint app/` times out** at 2 minutes — format only changed files.
  **`pest --filter=""` matches nothing and exits 0**, which looks like a pass.

## Deploy state

- `dev` carries #750–#758. **Nothing deployed**; csjones and production untouched.
- **Four migrations not applied anywhere but locally:**
  `2026_08_29_160000_add_trust_id_to_gifts_table`,
  `2026_08_29_110000_allow_estate_planning_in_user_assumptions_type`,
  `2026_08_31_140000_add_iht_paid_on_prior_death_to_life_events`,
  `2026_08_31_170000_add_bankruptcy_to_lpa_attorneys`.

## Tech debt deferred

Full report: `docs/tech-debt-report.md`.

- **Two mechanisms answer "what does this user owe"** — `NetWorthService:155` and
  `CrossModuleAssetAggregator:404`. Parity held by a test, not by construction.
- **The debt protection panel exists twice** — the canonical service `/m` consumes, and
  the web `/protection` page's own component.
- **`InvestmentController`'s write paths disagree** — create guards the auto-Cash row with
  `&& ! $hasCashHolding` (`:439`), update does not (`:587`). Same asymmetry as W-0321.
- **No UI field for `lpa_attorneys.is_bankrupt` (W-0105) or the professional
  certificate-provider details (W-0106).** Column, validation and check exist; nothing asks.
- **`GiftAnnualExemption` does not model s20, s21 or s22** — each needs a fact the app does
  not record. s21 is W-0525's remaining half.
- 52 unused private injections outside the TaxConfigService cluster.
- `database/schema/mysql-schema.sql` is stale. Wrong, not harmful.
- The gifting UI still offers edit/delete on a trust-owned gift; they fail with a clear
  422, but the control should not be there. Needs `trust_id` on `GiftResource`.
- Spouse WRITES require reciprocity but not consent — deliberate, open to challenge.
- **W-0351 acceptance 3 NOT done** — the sweep for other `v-if`s gating on fields their
  Resource never returns. W-0442 turned out to be a second instance of that class.
- **Three compliance-lead copy reviews outstanding** (W-0108, W-0152, W-0153) — worth one
  batched review rather than three.
- `CanonicalPortfolio.vue:23` prints "OCF" unexpanded on `/m` (Rule 9). Pre-existing.
