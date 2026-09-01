---
id: W-0452
title: One page shows a charitable percentage that cannot be derived from the estate figure printed above it — 4.2% against a Net Estate row of £1,728,780, where /estate shows 0.8%
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0033-cycle4-the-charitable-saving-and-the-percentage-denominator.md
owner: build-lead
reviewers: [tax-compliance-reviewer, quality-lead]
status: done
claimed_by: fix-cycle4-figures
severity: high
surfaces: [web]
created: 2026-08-23T04:35:00Z
claimed: 2026-08-23T04:08:43Z
blocked_by: []
gate: tax-compliance-reviewer-cleared-2026-08-23
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0433, W-0399]
prior_art_outcome: extend
source: tax-compliance-reviewer verdict 2026-08-23, condition C6 — W-0433's acceptance criterion was ticked and not met
---

## Intent

### MEASURED IN A BROWSER 2026-08-23 — and my original filing had it backwards

**`/plans/estate` shows "Current Charitable Rate: 4.2%" on a page whose own Net
Estate row reads £1,728,780. `/estate` shows 0.8% for the same household, in the
same session, as the same user.**

**That is not two surfaces disagreeing. It is one surface disagreeing with
itself** — a user reading `/plans/estate` sees a percentage that cannot be
derived from the figure printed above it by any arithmetic.

| Surface | Numerator | Denominator | Result |
|---|---|---|---|
| `/estate` | £10,000 | household net estate £1,728,780 − £500,000 = **£1,228,780** | **0.8%** |
| `/plans/estate` | £10,000 | Sarah's **own** net estate £739,280 − £500,000 = **£239,280** | **4.2%** |

**I filed this as "denominators match, numerators differ". Measured, it is the
opposite: the numerator is identical at £10,000 and the denominators differ by
5×**, because one baseline is built on the household estate and the other on the
individual's. Severity raised medium → high on the measurement, not on the
framing.

Evidence: `tests/Persona/20-08-2026_run/pass-a-web/160-web-sarah-plans-estate-4.2pct-vs-estate-0.8pct-W-0452.png`

### Original filing, retained

**W-0433's acceptance said "one definition of charitable giving as a percentage,
against the baseline, read by both surfaces". I ticked it. It is not met, and the
reviewer was right to catch it.**

W-0433 fixed the **denominator** on both surfaces — both now divide by the
Schedule 1A baseline. **It did not touch the numerators, and they differ:**

| Surface | Numerator |
|---|---|
| `/estate` (`IHTCalculationService`) | the **survivor's** will |
| `/plans/estate` (`EstatePlanService` → `WillAnalysisService`) | the **logged-in user's own** will |

Per the 2026-08-21 statutory ruling, Schedule 1A tests the estate of one deceased
person and this service models to the second death — so **the survivor's will is
the correct numerator.** `/plans/estate` uses whoever happens to be signed in.

### Why nothing caught it, including me

**The two numerators coincide on this persona and on every single-person
household.** David and Sarah each leave £10,000, so survivor-or-self gives the
same figure; a single person is trivially their own survivor.

**A fixture can only discriminate where the two partners' charitable legacies
differ AND the logged-in user is not the survivor.** No fixture in the codebase
has both properties. **I ticked a criterion whose failure mode my own asymmetric
fixture could not express** — the fixture was asymmetric in the legacies (£30,000
and £5,000) but always read from the survivor's session.

## Why it is not fixed here

Fixing it means routing `EstatePlanService`'s charitable analysis through the
household's **survivor** rather than the request's user. That resolution lives in
`IHTCalculationService::survivingMember()`, which is private, and the change
alters **which person a published figure describes** — a larger change than a
Rule 2 routing and one that should be reviewed on its own rather than folded into
a batch cleared for something else.

## Acceptance

- [ ] One numerator: the survivor's will, on both surfaces.
- [ ] `survivingMember()` (or an equivalent) exposed once and read by both, not
      re-implemented.
- [ ] **A fixture where the logged-in user is NOT the survivor and the two
      partners' legacies differ** — without both properties the test cannot
      fail, and that is precisely how this criterion came to be ticked while
      unmet.
- [ ] Verified from BOTH spouses' sessions, asserting the figure does not move.
- [ ] `tax-compliance-reviewer` to confirm the survivor is the right numerator on
      the plans surface as well as the Inheritance Tax surface.

## Working notes

**2026-08-23 — build-lead (`fix-cycle4-figures`), F-0033. Claimed.**

**This item has TWO axes, not one, and the filing and the reviewer each found a
different one.** Both had to move or the page still contradicts itself:

- **numerator** — the survivor's will versus the logged-in user's (reviewer's C6).
  Coincides on this persona and on every single-person household.
- **denominator** — the household net estate versus the individual's (the browser
  measurement). `EstateAgent.php:211` passed `$assetSummary['net_estate']` (the
  individual's) with `$ihtCalculation['nrb_available']` (the **household's** band)
  — a mongrel baseline: one person's assets, two people's allowance.

**Both close by the same routing.** `WillAnalysisService::analyzeCharitableBequests()`
no longer computes a baseline, a threshold or a donated amount from anything; it is
handed the position `IHTCalculationService` settled and expresses it.
`EstatePlanService::charitablePercentage()` is **deleted** — **there is now exactly
one division producing this percentage in the application**, and both surfaces read
its answer.

**Acceptance 2 satisfied by the `(or an equivalent)` clause, deliberately, and the
reviewer should judge the substitution.** `survivingMember()` is NOT exposed.
Exposing it would have made `EstateAgent` re-derive the pooling condition
(`isMarried && spouse !== null && dataSharingEnabled`) in order to call it — a
second copy of the decision that selects the survivor, which is the same defect one
level up. Instead the resolution stays in one place and **everything derived from
it is published**, so no second caller ever needs to know who the survivor is. That
includes the unvalued-gift question, which was also being asked of whoever was
logged in.

**Acceptance 3 met by the Bennett fixture** — Harold is not the survivor, the
legacies differ (£31,750 / £4,930), and the estates differ the right way round
(£903,000 / £412,000, survivor holding LESS), so a denominator taken from the
logged-in user is wrong by a different amount from each account and no reading
lands on the household figure by accident.

**Acceptance 4 asserted in tests from both sessions**; the browser reading is owed
and requested by handshake.

**What the browser CANNOT settle on this persona:** both spouses leave £10,000, so
survivor and self give the same figure. The page can confirm the percentage no
longer moves between sessions; **it cannot confirm which will it came from.** That
is the axis, and only the fixture expresses it. Stated rather than implied.

Branch doc: `workforce/branches/fixes/F-0033-cycle4-the-charitable-saving-and-the-percentage-denominator.md`

- 2026-08-31 build-lead: **CLOSED — verified against `dev`.** `EstateAgent:752-756` records that
  this third site used to divide the charitable total by the baseline to recompute a percentage
  the household calculation had already published; it now reads
  `$charitableAnalysis['charitable_percent']` — the one answer, settled in
  `IHTCalculationService:548`. So `/estate` and `/plans/estate` quote the same figure, and the
  percentage on `/plans/estate` is derivable from the estate figure printed above it.
  **This also unblocks W-0139's criterion 4**, which was waiting on this item.
