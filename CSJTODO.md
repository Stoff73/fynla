# CSJTODO — Fynla

*Last updated: 2026-09-04 session 1 — the four unverified items are browser-verified,
and the five raised on 1 September are fixed rather than raised again.
Handover: `handover/September/04/handover-2026-09-04-session-1.md`*

## The board position

Computed from the 327 files, not from a register. `tasks.md` in the repo root is the
live checklist and is **generated** — regenerate it, never hand-edit the counts.

| | |
|---|---|
| items | 337 |
| **resolved** | **325** |
| **outstanding** | **12** — 6 `deferred-ios`, 1 `deferred`, 5 `queued` |
| critical | **0** |
| high | **0** |
| medium / low in scope | **5** — W-0532 and W-0533 closed today; W-0540 is blocked, the rest queued |

**77 closed on 31 August, 26 on 1 September session 1, 12 in session 2, and on 4
September the four items that had never been driven in a browser were verified — which
found three more defects, two of them created by that session's own wiring.**

**The lesson of 4 September, and it is the same one: verify the instrument.** A second
orphan-component scan reported 72 rather than 79, and it was **wrong** — its seven extra
"references" were substring collisions with unrelated backend identifiers
(`calculateHouseholdNetWorth` and `generateSpousalOptimisations` are service methods;
`AssetLocationOptimizer`, `PerformanceAttribution` and `RebalancingCalculator` are PHP
classes sharing a name with a dead `.vue`). An earlier count of 32 was also wrong — the
file was read while the scan was still writing it.

**And a second, sharper one: a component nothing renders had never had its failure path
looked at either.** The trusts card, once wired in, 403'd twice per page load and told a
user holding a trust that they had none.

## Next session starts here

- [ ] **W-0540 — delete the 79 dead Vue components. BLOCKED ON CSJ: approval for a bulk
      `git rm`.** The guard is written (`tests/Architecture/EveryComponentIsRenderedSomewhereTest.php`,
      1.7s, no allowlist) and the list is verified; the blocked command was
      `xargs git rm -q < dead.txt`. Run the guard to regenerate the list — it prints it on
      failure. **`Public/CalculatorCard.vue` needs handling, not blind deletion**:
      `tests/Feature/Public/FreemiumCopyContractTest.php:25` asserts it EXISTS, so move its
      entry to `'deleted'` and adjust the counts (34 changed -> 33, 3 deleted -> 4). After
      the sweep run the full frontend suite **and a production build** — a deleted
      component that something imports fails at build time, not test time.
- [ ] **The Rule 15 lint vs the grandfather rule — CSJ's call.** The stop hook keys on
      **changed files, not changed lines**, so four pre-existing emoji hits
      (`IHTPlanning.vue:747`, `:1938`; `PropertyForm.vue:470`, `:645` — all blame-verified
      to Jan/Mar 2026 and Nov 2025) stop every session that touches those files. Rule 15
      grandfathers them, so nothing is owed; the noise is the problem. Options: **(a)
      narrow the lint to lines within the diff — recommended**, it makes the lint match the
      rule as written; (b) an explicit allowlist the lint reads, which itself rots; (c)
      clear the four as a one-off, which contradicts "don't rip them out" and so needs CSJ
      to say it directly.
- [ ] **Get `31c2bc4ad` onto `dev`** — the branch is pushed but nothing is on `dev` yet.
      **`960f23308` must NOT reach `dev`** until the W-0540 sweep; the guard is red by
      construction. Merging needs a `public/build/` rebuild and upload to csjones.
- [ ] **W-0535 — CoordinatingAgent, now 6,785 lines** (up 17 today from the two capability
      guards). CSJ asked "is not shown anywhere?" — correct, it has no user-facing surface.
      Close it or leave it plan-first; do not extract opportunistically.
- [ ] **Tax-compliance review.** W-0367 (s19), W-0514, W-0508, W-0338, W-0470, plus
      **W-0518 and W-0498**, and now **W-0533** (leasehold bands) and **W-0534** (the
      published exclusion sentence, which changes who is told what).
- [ ] **Design-lead / quality-lead on W-0497**, chief-of-staff on W-0506. No agents were
      dispatched on 1 or 4 September.
- [ ] **The six `deferred-ios` items** — W-0044, W-0090, W-0243, W-0311, W-0416, W-0496.
      They need a native cycle; the board loop is web and `/m` only.
- [ ] **W-0539 — `/m` has no trusts surface at all.** No route, no nav entry, only a count
      row at `Estate.vue:85`. **Deferred by CSJ on 4 September**; listed so it is not
      rediscovered as a gap.
- [ ] **The sweep findings — decide, do not chase.** Baseline now recorded: **38 findings,
      5 advisories at `34ea12401`**, all orphan references in check [1]. The rule is that a
      *rising* count is the signal, and it now has a number to rise from.

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
- **`./vendor/bin/pint app/` times out** at 2 minutes — format only changed files, and
  remember it is PHP-only so it does nothing for `.vue`/`.js`. A whole-directory run killed
  a compound command mid-chain on 4 September, losing the commit that followed it.
  **`pest --filter=""` matches nothing and exits 0**, which looks like a pass.
- **The design-system lint keys on changed FILES, not changed lines** — see "Next session
  starts here". Four grandfathered emoji hits stop any session touching `IHTPlanning.vue`
  or `PropertyForm.vue`.
- **`ssh-add ~/.ssh/fynlaDev` is needed at the start of a session.** Passphrase-protected
  and not loaded automatically; csjones SSH returns `Permission denied (publickey)` until
  it is.
- **rsync to csjones needs `$HOME`, not `~`, inside the quoted `-e`.** `~` is not expanded
  there and it fails with `Can't open user config file`.
- **The playwright MCP timed out** on 4 September (30s). The Chrome extension tools did
  everything needed, including reading computed styles and network status codes.
- **A hard browser navigation loses the SPA session** — navigate by clicking nav links.
  `/m` and the web hold separate token stores, so each needs its own MFA cycle.

## Deploy state

- **`dev` is at `41771cca0`** — PRs #760 through #765 merged on 4 September, all
  `--merge --admin`.
- **csjones is on `dev` at `41771cca0`**, backend pulled and `public/build/` rebuilt with
  `./deploy/csjones-fynla/build.sh` and uploaded. Caches cleared with `route:clear`,
  `cache:clear`, `view:clear`, then `config:cache` only — neither forbidden caching command
  was run. Verified live in a browser, not just by status code.
- **The branch is pushed; two commits are NOT on `dev`.** `31c2bc4ad`
  (W-0532/0533/0534) is mergeable now. `960f23308` (the W-0540 guard) is **red by
  construction** and must not reach `dev` until the sweep. Merging either needs a
  `public/build/` rebuild and upload to csjones, not just a `git pull`.
- **production untouched.** Nothing from 1 or 4 September is on fynla.org.

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
- **`CoordinatingAgent.php` is 6,785 lines** — up 17 on 4 September from the two capability
  guards, which is exactly the growth path. It is **W-0535** and it is plan-first: a seam
  decided against the `fyn-architecture` contract before anything moves, or Fyn handlers end
  up in two homes and Rule 20 breaks.
- **`tests/Architecture/EveryComponentIsRenderedSomewhereTest.php` is RED by design** until
  the W-0540 sweep. Committed on the branch, must not reach `dev` before the deletion.
- **`IHTCalculationService.php` is 2,723 lines.** Watch; do not split without a decided
  seam — that is how the estate figures diverged before.
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
