---
id: W-0273
title: Capacity for loss is computed from a numerator and a denominator describing different sets of records
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0024-cycle4-risk-engine-reach-and-fraction.md
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T21:00:00Z
claimed: 2026-08-22T21:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0238, W-0241, W-0244, F-0022]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Raised as D-10 by `peak-earners-c4` in cycle 4.

### The defect, and a correction to the item as dispatched

The dispatch described this as stored `risk_profiles.factor_breakdown` rows holding
figures that the already-landed W-0238 fix had superseded, and asked whether the
stored rows needed recomputing or expiring.

**Measured before touching anything: the figures were not stale, they were being
recomputed wrong on every page load.** W-0238 fixed the *agents*.
`AutoRiskCalculator:129` ran its own `InvestmentAccount::where('user_id', …)->sum()`,
which W-0238 never touched:

| | Read live | Correct |
|---|---|---|
| David (16) `investments_total` | £220,000 | £172,500 |
| Sarah (17) `investments_total` | £85,000 | £132,500 |

So the user-facing sentence — *"11.5% of your net worth is in investments/pensions,
giving you high capacity to take risk"* — was a factual claim computed from a
numerator over one set of records and a denominator over a different set.

**No migration is needed and none was written.**
`RiskPreferenceService::getRiskProfile():216-227` recalculates the factors live for
display, so the stored row never reaches the screen; it is an audit artefact that
refreshes on the next write. Recomputing it would change nothing a user sees;
expiring it would destroy the record of what was assessed when.

**Had the premise been taken on trust**, the work would have been a recompute
migration that measures green and leaves the live defect in place.

### On `pensions_total 0` for a defined benefit holder

Sarah's £35,000/year NHS scheme reads £0 with no explanation. Per CSJ's settled
ruling on W-0241 the exclusion is **correct** — and correct twice over for this
factor, since the scheme has no capital to place at risk and a guaranteed income
carries no market risk to lose. What was wrong is that it read as *no provision at
all*, silently.

## Acceptance

1. The factor reads the corrected, share-correct investment total on both accounts.
2. Numerator and denominator describe the same set of records.
3. A defined benefit scheme is excluded **and disclosed**, with no valuation and no
   capitalisation multiple introduced anywhere.

## Working notes

**DONE.** `calculateCapacityForLoss` already called `calculateNetWorth()` for its
denominator; both terms now come from that **one response**
(`breakdown.investments`, `breakdown.pensions`, `has_db_pensions`). This **deletes**
two queries and two imports rather than adding anything, and makes the two halves of
the ratio structurally incapable of describing different sets.

Measured after — David **£172,500 / 45.1%**, Sarah **£132,500 / 17.9%**.

**Disclosure: read from `App\Constants\PensionDisclosure`, never re-typed.** I wrote
my own sentence first and deleted it when the canonical constant landed mid-batch. The
factor now returns `PensionDisclosure::DEFINED_BENEFIT_EXCLUDED_SHORT`.

**It is its own `disclosure` field, not appended to `description`, and that was
measured.** Appended, it rendered **clipped** on the summary card —
`FactorBreakdownCard` applies `line-clamp-2` and the two sentences are three lines
(`scrollHeight` **48** against `clientHeight` **32**, read from the live DOM). The user
saw the ratio and lost the reason for the £0. All nine factors now return a
`disclosure` key (null for eight); the card renders it unclamped and
`RiskFactorDetailPage.vue` renders it beneath the formula, where the "£0 pensions"
term prints. That page did not render the factor's description **at all** before, so an
explanation put only there would have existed unreadably.

`components.has_defined_benefit_pension` is **not a second flag** — it is
`calculatePensionBreakdown()['has_db']`, arriving inside the `calculateNetWorth()`
response this method already reads.

A test asserts `pensions_total` is 0 **and specifically not £700,000** — the ×20
capitalisation that another agent is removing from the net worth item list must not
reappear here.

**Not done, and deliberately out of scope:** whether a guaranteed £35,000/year should
*raise* the capacity-for-loss level. It plainly bears on capacity, but the factor has
no lever for income and inventing one is a product decision (Rule 16). Flagged to
team-lead; interacts with W-0244.

**Browser-verified, both accounts — and the disclosure verified by measurement, not by eye.** Sarah's card: disclosure `clientHeight` 32 = `scrollHeight` 32, `webkitLineClamp: none`, `overflow: visible`; four uncut lines at 390 × 844. Exactly one of nine cards carries a disclosure node and none renders an empty one. David's detail page: **£172,500 investments +
£500,000 pensions ÷ £1,489,500 = 45.1%**. Sarah's: **£132,500 + £0 pensions ÷
£739,280 = 17.9%**, with the disclosure sentence rendered **directly beneath the
£0** — *"Your defined benefit pension is not counted here — it pays a guaranteed
income rather than holding a capital sum you could lose."* Screenshot
`W-0273-web-sarah-17-capacity-for-loss-db-disclosed.png`.
