# CSJTODO — Fynla

*Last updated: 2026-09-01 session 2 — board 29 -> 6 outstanding, all iOS.
Handover: `handover/September/01/handover-2026-09-01-session-2.md`*

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

- [ ] **BROWSER-TEST ON csjones.co/fynla — four items with unverified acceptance.**
      Nothing has been driven in a browser for three sessions. All of it is now live on
      csjones, so this is unblocked. Full instructions, per item, in the session-2
      handover:
      **W-0504** `/m` dashboard rings (net worth must say "Equity" and the arc must match
      the number; use `peak_earners`, 11% investments — a persona near 72% proves
      nothing); **W-0500** the `/m` spouse question on a shared property with an
      unlinked co-owner, confirmed by reading `properties.joint_owner_is_spouse`, not by
      screenshot; **W-0034** `/m` Health and lifestyle read AND write;
      **W-0045** the four Trusts palette screens.
- [ ] **Raise board items for four findings.** `family_module` and `benefits_child` have
      zero consumers and are **named in the pricing comparison** — sold and ungated, the
      same defect as W-0499, and the sharpest of the four. Also `tenure_types` /
      `leasehold_reform` (configured, read by nothing), `IHTPlanning.vue:620-630` (a true
      sentence the engine does not publish, so the teaser cannot say it), and
      `CoordinatingAgent.php` at 6,768 lines.
- [ ] **Tax-compliance review.** W-0367 (s19), W-0514, W-0508, W-0338, W-0470 remain, plus
      **W-0518 and W-0498** from session 2 — both carry the reviewer in their front matter
      and neither was run, because no agents were dispatched.
- [ ] **Design-lead / quality-lead on W-0497**, chief-of-staff on W-0506. Same reason.
- [ ] **The six remaining board items are all `deferred-ios`** — W-0044, W-0090, W-0243,
      W-0311, W-0416, W-0496. They need a native cycle with a Mac running Xcode and the
      `Fynla-Staging` scheme; the board loop is web and `/m` only.
- [ ] **The 34 remaining sweep findings — decide, do not chase.** Largely real deletions
      cited in historical reports, plus build hashes that can never resolve. Rewriting
      history to satisfy a checker is the failure W-0506 was about.

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

- **`dev` is at `c52b51db2`** — PR #759 merged (`--merge --admin`), carrying #750–#758
  plus both 1 September sessions.
- **csjones is deployed and verified.** Pulled to `c52b51db2`; **11 migrations applied**,
  which cleared the four that had been local-only plus session 2's two
  (`add_second_life_assured_to_life_insurance_policies`,
  `add_declared_liability_percentage_to_mortgages`). Both bundles rebuilt with
  `./deploy/csjones-fynla/build.sh` and uploaded. Confirmed live: homepage 200, `/m` 200,
  and the new `/m` bundle greps positive for this session's work — which is what proves
  the deploy did not land a stale build.
- **production untouched.** Nothing from either session is on fynla.org.

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
