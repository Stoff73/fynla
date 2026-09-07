---
type: handover
mode: session-end
date: 2026-08-31
session: 2
repo: fynla
branch: chore/board-verification-31-august
---

# Session Handover — 2026-08-31, Session 2

## Where things stand

**The board went from 132 outstanding to 55, and every CRITICAL and every HIGH is
closed.** 77 items closed today; what remains is 49 medium and 6 low. All 68
commits are on `chore/board-verification-31-august` and pushed; **PR #759 is open
against `dev` and has not been reviewed or merged.**

Roughly half the closures needed no code — they were finished work nobody had
restamped. The other half were real fixes, each with a test, and several left a
**mutation-verified guard** behind. Nothing is half-done: the last item, W-0367,
was fully switched on rather than left at `review`.

**Nothing in this PR is browser-verified and nothing has had a tax-compliance
review.** Several changes move real Inheritance Tax figures. See Priorities 1 and 2.

## Priorities for the next session

1. **PR #759 — BLOCKED ON CSJ: review and merge decision.** 68 commits, 77 board
   closures, six-plus real defect fixes. It has been open since this morning and
   grown all day. CSJ chose "one PR" this morning; that choice predates two thirds
   of the work, so it is worth re-confirming rather than assuming.

2. **Tax-compliance review — BLOCKED ON CSJ (or the `tax-compliance-reviewer`
   agent).** These move real figures and none has been reviewed:
   **W-0367** (s19 annual exemption — moves the bill for every household with a
   recorded gift), **W-0514** (second-death residence band taper), **W-0508**
   (civil partnerships), **W-0338** (two-leg mortgage reader), **W-0470**,
   **W-0104**–**W-0109** (LPA statutory statements). **W-0462 is the sharpest:**
   it is customer-facing copy about a benefit figure with an unstated cost, which
   the item itself calls a Consumer Duty question — I wrote that copy and it
   should not ship unreviewed.

3. **Browser verification.** Nothing today was verified in a browser, because
   `public/build/` and `public/m-build/` are BOTH csjones builds and rebuilding
   overwrites what CSJ has staged. The highest-value walks: W-0050 (decline
   cookies, then register), W-0330 (a joint owner sees no Edit/Delete), W-0413
   (a renter's rent persists and comes back).

4. **Continue the board loop on the remaining 55.** Use
   `.claude/skills/board-loop/SKILL.md` and `tasks.md`. On today's evidence
   roughly a third of the remainder is already fixed and needs restamping only.
   Natural next cluster: **W-0100** (the LPA generator umbrella — five of its six
   siblings closed today), then **W-0110**/**W-0044**/**W-0090** (the `/m` and
   native surface gaps, which cluster together).

5. **W-0054 is `in-progress`** and was not touched today. Someone marked it
   claimed; establish whether that is stale before starting it.

## Context to load

- `tasks.md` — the checklist, generated from the board and ticked item by item.
  **Regenerate it, never hand-edit the counts.** This is the fastest way to see
  what is left.
- `.claude/skills/board-loop/SKILL.md` — the loop CSJ specified, verbatim. Nine
  steps, `superpowers:systematic-debugging` on every live bug, no parallel agents,
  no full suite per item. **Follow it exactly; deviating from it cost time today.**
- `tests/Feature/Estate/PeakEarnersPersonaFiguresTest.php` — the persona figures
  are locked here and **they MOVED today**. Read the docblock before trusting any
  remembered number.
- `app/Services/Estate/GiftAnnualExemption.php` — the s19 implementation, and the
  reasoning for the pro-rata same-day rule. The newest tax logic in the estate.
- `docs/tech-debt-report.md` — carried forward, still unfixed.
- `workforce/ops/board/W-0367-gift-values-taken-gross-with-no-lifetime-exemptions-applied.md`
  — the fullest record of a fix from today, including what is deliberately not
  handled (s20, s21, s22).

## Completed this session

**77 board items closed.** Every critical (W-0463) and all eleven highs.

**Fixed with tests, most with a guard:**
- **W-0461 / W-0432** — rate literals reaching configuration, and the guard the
  family never had: a test that moves a rate and asserts on a MOUNTED template.
  Mutation-verified. Three dead components deleted on CSJ's call.
- **W-0367** — the s19 annual exemption, built AND switched on. Same-day gifts
  apportioned pro rata (IHTM14143).
- **W-0514** — the second death's residence band was **never tapered at all**.
- **W-0424** — a percentage-only pension contribution reached nothing; both
  mechanisms were broken, differently, on the same record.
- **W-0503** — one Tailwind class filed, **31 found**, all emitting nothing. Guard added.
- **W-0142**, **W-0127**, **W-0321**, **W-0413**, **W-0176**, **W-0495**,
  **W-0338**/**W-0373**, **W-0330**, **W-0453**, **W-0131**, **W-0104**–**W-0109**,
  **W-0115**, **W-0370**, **W-0371**, **W-0470**, **W-0133**, **W-0037**, **W-0050**.

**Closed on CSJ's rulings, not to be re-raised:** **W-0144** (revocation is the
law; 28-day survivorship is standard drafting) and **W-0155** (consent is one
button; there is no withdrawal journey).

**`board-loop` skill written**, then moved into `.claude/skills/` where the other
twenty Fynla skills live — it was wrongly created at user level.

## Verification state

- Targeted suites green at every commit. Largest single runs: **887** estate/gift/
  IHT/trust (2,882 assertions) at `67fce3e7b`; **821** frontend; **611** investment
  (2,004); **567** profile/expenditure/income (1,729).
- **Mutation-verified** (broke the protection, saw red, restored): W-0461's
  template guard, W-0526, W-0525, W-0527, W-0453.
- Persona locks green at the NEW figures — see below.
- Pint clean on every changed file.
- **NOT verified: anything in a browser. NOT reviewed: anything by
  tax-compliance or compliance-lead.**

## Decisions and dead ends

- **CSJ's rulings, settled — do not re-litigate.** One PR, not split. Delete the
  three dead estate components. Build W-0527's data capture rather than defer it.
  Revocation and the 28-day period stay (W-0144). Consent is a single accept
  button (W-0155). **No parallel agents, of any kind, for any purpose.**
- **`git checkout -- <file>` destroyed my own uncommitted fixes, twice.** It
  reverts to HEAD, not to the pre-mutation state. Copy the file first. This is now
  written into the board-loop skill.
- **A broad `--filter` on Pest costs 4–8 minutes and exceeds the tool timeout.**
  Test the diff, as the skill says. I broke this rule three times and it was the
  single largest waste of the day.
- **W-0367 could not be switched on until the taper suite was re-derived.** The
  first attempt at grossing up the fixtures worked for single-gift cases and broke
  every multi-gift one, because the allowance is SHARED. The answer was pro-rata
  apportionment, which is also the law (IHTM14143).
- **Items are consistently bigger than filed.** W-0503 1→31, W-0453 2→5, W-0109
  3→4, W-0371 partially done, W-0424 one fault→three. **The guards found the
  extras, not reading.**
- **`W-0335`'s "dead" endpoint is not dead** — it takes a scenario the index
  payload cannot answer. Documented at the line so the next sweep does not re-file it.

## Things that will bite you

- **THE PERSONA FIGURES CHANGED. `£343,512` is now `£341,112`.** W-0367 relieves
  £6,000 of the 2020 settlement, so `nrb_gift_deduction` is £144,000, the band
  £506,000, the taxable estate £852,780. The household was previously **over-charged
  £2,400**. The old number appears in earlier handovers and in the vault — treat
  those as stale. Household gross is unchanged at £1,728,780.
- **Both `public/` bundles are csjones builds.** A cold `localhost:8000` is a blank
  page with MIME errors. Rebuilding overwrites CSJ's staged work — **ask first.**
- **Vite: 5173 is taken by `hermes-desktop`; 5174 works, 5176 does NOT** (CSP
  allowlists only 5173/5174).
- **The board directory mixes filename conventions** (`W-0002.md` vs
  `W-0002-slug.md`). Use `ls ${id}-*.md ${id}.md 2>/dev/null | head -1`.
- **Test result keys are `key` and `description`**, not `check`/`detail` —
  `LpaComplianceService::result()`. Cost several failed assertions.
- **Persona passwords are `Password1!`, not `password`.**

## Tech debt deferred

Carried forward from `docs/tech-debt-report.md`, none fixed today:

1. **Two mechanisms answer "what does this user owe"** — `NetWorthService:155` and
   `CrossModuleAssetAggregator:404`. Parity held by a test, not by construction.
2. **The debt protection panel exists twice** — the canonical `protection_gap_v1`
   service and the web `/protection` component.
3. **`InvestmentController`'s write paths disagree on the auto-Cash row** —
   create guards with `&& ! $hasCashHolding` (`:439`), update does not (`:587`).
   Same create/update asymmetry as W-0321, different mechanism.

New, from today:

4. **No UI field for `lpa_attorneys.is_bankrupt` (W-0105) or the professional
   certificate-provider details (W-0106).** Column, validation and compliance
   check all exist; nothing on screen asks.
5. **`GiftAnnualExemption` does not model s20, s21 or s22** — each needs a fact
   the application does not record. s21 is W-0525's remaining half.
6. **52 unused private injections** outside the TaxConfigService cluster;
   `database/schema/mysql-schema.sql` is stale.

## Branch and deploy state

- **Branch:** `chore/board-verification-31-august`, 68 commits ahead of `dev`.
- **Tree:** clean. **Unpushed: none.**
- **PR #759** open against `dev`, unreviewed, unmerged.
- **Nothing deployed.** csjones and production untouched.
- **Migrations added today and NOT applied to either server:**
  `add_iht_paid_on_prior_death_to_life_events`,
  `add_bankruptcy_to_lpa_attorneys`. Plus the two from 29 August still pending.
- `main` unchanged; PR #736 still deliberately unmerged.
