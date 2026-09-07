# CSJTODO — Fynla

*Last updated: 2026-09-07 session 1 — dev RELEASED to fynla.org (main a7cb3a211); TestFlight build 8 on production.
Handover: `handover/September/07/handover-2026-09-07-session-1.md`*

## The board position

Computed from the 327 files, not from a register. `tasks.md` in the repo root is the
live checklist and is **generated** — regenerate it, never hand-edit the counts.

| | |
|---|---|
| items | 328 |
| **resolved** | **322** |
| **outstanding** | **6** — all `deferred-ios` |
| critical | **0** |
| high | **0** |
| medium / low in scope | **0** |

**77 closed on 31 August, 26 on 1 September session 1, 12 more in session 2.** Every
non-iOS item is closed. The rule is unchanged — **a citation is not a verification** —
and it earned its keep again twice: **W-0498's three offered classifications were all
wrong** (it was a Rule 20 duplicate, not dead config), and **W-0506's own proposed fix
was measured and rejected** (25 of the 41 references its rule would keep were the
citations it wanted excluded).

**The lesson of session 2: verify the instrument before trusting the measurement.** Four
separate scans reported defects that did not exist — a regex for PHP literals broken by an
apostrophe in a docblock, a method-name tracker fooled by anonymous closures, a guard that
matched its own explanatory comment, and a `grep -rl` that counted the file the real check
deliberately excludes.

## Next session starts here

- [ ] **BLOCKED ON CSJ — merge #773 after the device check** (login on build 8 is
      confirmed; still to see: "Upgrade on the web" / "Manage billing on the web" under
      Settings → Plan and billing, and a Fyn turn). Native-only, no csjones step. Then pull
      csjones to the dev tip.
- [ ] **BLOCKED ON CSJ — W-0540 dead-components clusters and the Rule 15 lint scope**
      (both carried from 5 September). `GiftingStrategy.vue`, `TrustPlanningStrategy.vue`
      and `IHTPlanning.vue` are in the unreachable set.
- [ ] **Fix the adjacent findings from the 7 September browser pass**: free-tier 403s on
      `/api/estate/calculate-iht` and `/api/estate/will-builder` from pages that do not
      need them; unformatted amounts in the `/m` "Today's insight" line; "Exit Demo" on
      csjones lands on the csjones.co root (`preview.js:376`, fallback referrer `/`).
- [ ] **Close W-0532/W-0533/W-0534 on the board and in `tasks.md`** — on dev and prod
      via #768. Decide the unstyled homepage pension-check block parked in PR #770.
- [ ] **BROWSER-TEST ON csjones — still unverified from 1 September:** W-0500 (`/m`
      spouse question, read `properties.joint_owner_is_spouse`), W-0034 (`/m` Health and
      lifestyle read AND write), W-0045 (four Trusts palette screens). W-0504 rings were
      seen working on `/m` today (Level wheel, "2 of 4 actions").
- [ ] **Tax-compliance review** — W-0367, W-0514, W-0508, W-0338, W-0470, W-0518, W-0498;
      design-lead / quality-lead on W-0497; chief-of-staff on W-0506.
- [ ] **The six `deferred-ios` items** — W-0044, W-0090, W-0243, W-0311, W-0416, W-0496.
      Production now serves the native routes, so a native cycle can run against fynla.org.
- [ ] **The 34 remaining sweep findings — decide, do not chase.**

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
- The iOS `test-and-build` CI job is **not a release gate and must never be re-run
  unasked** (CSJ 2026-09-07); its recurring red is the simulator "not hittable" trap.
- **prod == main == dev since 2026-09-07 17:37 BST.** Before any release, check what
  prod actually runs (bundle hash, `migrate:status`, vendor mtime) — it had run the 22
  June code for ten weeks while main moved.
- **A restored dump cannot drop tables it never held** — after a rollback, diff
  `SHOW TABLES` against the dump before migrating again (three leftover pipeline tables
  bit the second attempt).
- **fynla.org has 8 paying customers on legacy plans** (pro/standard/family/student);
  they confer Premium (#771). "No premium subscribers" was only ever true of csjones.
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

- **fynla.org = main `a7cb3a211`** (tree identical to dev at `06a671b8b`), live since
  2026-09-07 17:37 BST after a rollback of the first attempt (`34b6faeaa`) at the
  tier-collapse preflight. 73 migrations, seed, Fyn validators green, routes uncached,
  config cached. Backups: server `~/release-backups/2026-09-07*/`, local
  `~/Desktop/fynla-release-backups/`. Deploy notes: memory
  `project_release_2026_09_07_rolled_back` and PR #772's comments.
- **dev = `3cd23bb63`** (#767–#771, #774 today). **csjones = dev `06a671b8b`**, bundles
  built from it; pull to the tip after #773 (only ops logs since).
- **TestFlight "Fynla" 1.0 (8)** on the `org.fynla.app.dev` record, Production
  configuration (fynla.org), login confirmed by CSJ. The `org.fynla.app` record is
  "Fynla (legacy)" with its build expired — never upload there unasked.

## Tech debt deferred

Full report: `docs/tech-debt-report.md`.

- **(2026-09-07)** `SubscriptionManagementView.swift` writes the web-handoff button + error
  block twice; `TierCollapsePreflight.php` re-derives "plans that confer premium" instead
  of one `TierConfigurationStore::plansConferringPremium()`; `WillFactory.php` repeats the
  unnamed column default `100.00`.
- **(2026-09-07)** A homepage pension-check block salvaged from August has no CSS; the hunk
  is in PR #770's description.

- **Two mechanisms answer "what does this user owe"** — `NetWorthService:155` and
  `CrossModuleAssetAggregator:404`. Parity held by a test, not by construction.
- **The debt protection panel exists twice** — the canonical service `/m` consumes, and
  the web `/protection` page's own component.
- **`InvestmentController`'s write paths disagree** — create guards the auto-Cash row with
  `&& ! $hasCashHolding` (`:439`), update does not (`:587`). Same asymmetry as W-0321.
- **No UI field for `lpa_attorneys.is_bankrupt` (W-0105) or the professional
  certificate-provider details (W-0106).** Column, validation and check exist; nothing asks.
- **`CoordinatingAgent.php` is 6,768 lines** — every Fyn capture handler lives there and it
  grows with each tool. Wants its own board item, not an opportunistic extraction.
- **`TaxConfigService::hasSurvivorshipRights()` and `allowsWillOverride()` have zero callers
  BY DESIGN** (`:828-846`, W-0498) — a first-death question the second-death estate must not
  ask, recorded with a guard. Listed so a dead-code sweep does not delete them.
- **`RetirementProjectionService` now takes nine constructor arguments** (W-0516 added the
  ninth). Correct, but two test files construct it by hand; watch if a tenth appears.
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
