# CSJTODO — Fynla

*Last updated: 2026-08-31 session 2 — every critical and high on the board is closed.
Handover: `handover/August/31/handover-2026-08-31-session-2.md`*

## The board position

Computed from the 327 files, not from a register. `tasks.md` in the repo root is the
live checklist and is **generated** — regenerate it, never hand-edit the counts.

| | |
|---|---|
| items | 327 |
| **resolved** | **272** |
| **outstanding** | **55** — 49 medium, 6 low |
| critical | **0** |
| high | **0** |

**77 items closed on 31 August.** Roughly half needed no code: they were finished work
nobody had restamped. The rule that produced the number is unchanged — **a citation is
not a verification.** Only reading the code and finding the defect gone counts.

**The second lesson of the day: items are consistently bigger than filed.** W-0503 was
one Tailwind class and turned out to be 31. W-0453 was 2 sites and was 5. W-0424 was one
broken gate and was three faults across two mechanisms. **The guards found the extras,
not reading.**

## Next session starts here

- [ ] **PR #759 — CSJ'S CALL.** 68 commits, 77 closures, open against `dev`, unreviewed.
      CSJ chose "one PR" this morning; that predates two thirds of the work.
- [ ] **Tax-compliance review.** W-0367 (s19 — moves the bill for every household with a
      recorded gift), W-0514, W-0508, W-0338, W-0470, W-0104–W-0109. **W-0462 is the
      sharpest** — customer-facing copy about a benefit with an unstated cost, which the
      item calls a Consumer Duty question.
- [ ] **Browser verification — nothing today was checked in a browser.** Best walks:
      W-0050 (decline cookies then register), W-0330 (joint owner sees no Edit/Delete),
      W-0413 (a renter's rent persists and returns).
- [ ] **Continue the loop on the remaining 55** via `.claude/skills/board-loop/SKILL.md`
      and `tasks.md`. Next natural cluster: **W-0100** (LPA umbrella; five of six siblings
      closed today), then **W-0110 / W-0044 / W-0090** (the `/m` and native surface gaps).
- [ ] **W-0054 is `in-progress`** and was not touched. Check whether that claim is stale.

## Settled by CSJ — do not re-raise

- **W-0144** — revocation of former wills is the law; the 28-day survivorship period is
  standard drafting. Defaults unchanged, no prompt needed.
- **W-0155** — consent is a single accept button. There is no withdrawal journey.
  `declineCookies()` is the banner's Decline path, not dead code.
- **W-0524** — agricultural relief is a property-type design decision, deferred.
- **One PR, not split.** **No parallel agents, of any kind, for any purpose.**

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
