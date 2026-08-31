# CSJTODO — Fynla

*Last updated: 2026-08-31 session 1 — every high and critical item on the board is now
verified. Handover: handover/August/31/handover-2026-08-31-session-1.md*

## The verified board position

Computed from the 327 files, not from a register. The `done` column reconciles exactly:
120 (30 Aug audit TSV) + 3 (closed 30 Aug after it was written) + 65 (31 Aug) = **188 done**,
plus 3 `closed_invalid`, 2 `closed_duplicate`, 2 `deferred` = **195 resolved**.

| | |
|---|---|
| items | 327 |
| **resolved** | **195** |
| **outstanding** | **132** |
| — opened and verified, still live or partial | **16** (14 high, 2 critical) |
| — **never opened** | **116** — 95 medium, 11 low. **Zero high, zero critical.** |

**The rule that produced it: a citation is not a verification.** Only reading the code and
finding the defect gone counts. W-0432, W-0461, W-0463 and W-0515 are all heavily cited in
code and all still live.

**The board's real problem was never unfinished work — it was finished work nobody
restamped.** Twelve items carried "FIXED" in their own notes from 21 August and sat at
`gated`; six more had merged in the previous session's own PRs.

## Next session starts here

- [ ] **Push `chore/board-verification-31-august` and decide the PR shape. CSJ'S CALL.**
      12 commits, **no upstream** — all of 31 August is local only. Five code fixes plus
      ~70 board restamps; reasonably splittable.

- [ ] **The rate-literal set — `W-0432` + `W-0461`, converged on ONE remaining set.**
      Do them together, not as separate items.
      - `IHTPlanning.vue:621` — "£175,000" Home Allowance. Named by two verdicts, survived
        four batches, three lines below a sentence W-0432 already fixed.
      - `TaxSettingsController:330` — `?? 0.36` and a hardcoded `10%+`, inside the admin
        screen that displays the tax settings. The last non-comment `?? 0.36` in `app/`.
      - **`W-0461`'s criterion 1 IS the item and is not met:** a guard that moves a
        configured rate and asserts on a **rendered Vue template**. Every existing guard
        drives PHP and asserts on service output, so re-hardcoding any of the nine leaves
        the suite green. The nine fixes are the easy half.
      - Instance 6 has grown: `EstateOverviewCard.vue` carries `* 0.40` at **:158 and :169**.

- [ ] **`W-0508` — civil partnership, and it is the half-fixed shape.** `WillController:80`,
      `GiftingController:81`/`:272`, `TrustController:187` still read `['married']` alone.
      **`TrustController` is sharp:** `:207` uses `hasSpousalStatus()` and `:187`, twenty
      lines above in the same method, does not — so a civil partner gets the corrected
      calculation and a single-person default profile in one request.

- [ ] **Pension-in-estate — `W-0513`, `W-0514`, `W-0515`.** One subsystem, three items.
      `W-0515` is sharpest: `IHTCalculationService:2382` still says pensions pass outside
      the estate and `:2386` publishes **today's pot**, the figure W-0482 exists to reject.

- [ ] **`W-0495`** — `EmergencyFundCalculator:14-16` returns `0.0` for both "no runway" and
      "cannot be calculated". Small, and wrong in the alarming direction.

- [ ] **`W-0463` — work SEVEN, not twenty.** 12 accessors have zero callers; only 7 are
      genuinely unwired. Three are dead *accessors* with the capability wired elsewhere
      (blind person's allowance via `blindPersonsAllowanceFor()`; the 14-year rule is
      implemented from the CLT block — `W-0526`, a consolidation). **Agricultural relief is
      CSJ's deferral (W-0524) and agricultural land is a property type — do not re-open it.**

- [ ] **`W-0483` — CSJ's amended W-0228 ruling.** *"W-0228 can allow mortgage share that is
      not the same as ownership share."* Relax the throw in
      `CalculatesOwnershipShare::refuseRecordWhoseShareFollowsAnother()`; give the user a way
      to SAY a co-owner borrowed alone; web AND `/m`.
      **Trap:** do not make `mortgages.ownership_percentage` authoritative for existing rows
      — the persona carries joint 50% on a tenants-in-common 40% property, and believing it
      moves that household's liabilities £293,000 → £305,000.

- [ ] **The 116 unopened mediums and lows.** Same method: open it, find the defect, record
      FIXED with evidence or LIVE with file:line.

## Blocked, parked, or not ours

- **`W-0050`** — registration still requires accepting cookies. Live, but **parked by CSJ
  on 21 August** until the functional board is clear. Do not re-raise as a gate.
- **`W-0154`** — F1/F2/F3 fixed and pinned on web. The residual is **iOS**, never built
  against it, plus `/m`'s allowance breakdown which is `W-0464` and must not be counted twice.
- **`W-0133`, `W-0144`, `W-0155`** — each needs a mechanism that does not exist yet: a route
  back to `syncBequests` after completion; a field behind the revocation clause; an interface
  for a withdrawal capability the server already has.

## Known issues

- **`public/build/` and `public/m-build/` are BOTH the csjones build** (base `/fynla/`).
  Local `localhost:8000` is a blank page until Vite runs; `/m/app/*` double-prefixes and
  404s, so **`/m` cannot be browser-verified locally.** Rebuilding overwrites what is staged.
- **Vite cannot use 5173** — `hermes-desktop` owns it and Fynla is `strictPort`. **5174 works,
  5176 does not**: the CSP allowlists only 5173/5174, and any other port is silently blocked.
- **Persona passwords are `Password1!`**, not `password`. A 401 is probably not a bug.
- The iOS `test-and-build` CI job is **flaky, not a regression**.
- **`main` and `dev` are still diverged.** PR #736 holds the reconciliation and is
  deliberately unmerged, because merging it equals a release.

## Deploy state

- `dev` carries #750–#758. Nothing deployed; csjones and production untouched.
- **Two migrations on `dev` not applied anywhere else:**
  `2026_08_29_160000_add_trust_id_to_gifts_table` and
  `2026_08_29_110000_allow_estate_planning_in_user_assumptions_type`.

## Tech debt deferred

Full report: `docs/tech-debt-report.md`.

- **Two mechanisms answer "what does this user owe"** — `NetWorthService:155` and
  `CrossModuleAssetAggregator:404`. W-0226 made them agree; parity is held by a test, not
  by construction.
- **The debt protection panel exists twice** — the canonical service `/m` consumes, and the
  web `/protection` page's own component. That is why W-0227 was live on one surface only.
- **`InvestmentController`'s write paths disagree** — create guards the auto-Cash row with
  `&& ! $hasCashHolding` (`:439`), update does not (`:587`), so 70% equities + 20% Cash
  through update yields two Cash rows.
- 52 unused private injections outside the TaxConfigService cluster.
- `database/schema/mysql-schema.sql` is stale against two migrations. Wrong, not harmful.
- The gifting UI still offers edit/delete on a trust-owned gift (web and `/m`); they fail
  with a clear 422, but the control should not be there. Needs `trust_id` on `GiftResource`.
- Spouse WRITES require reciprocity but not consent — deliberate, so a pending couple's
  household expenditure still splits. Open to challenge.
