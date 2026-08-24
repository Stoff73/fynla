---
id: W-0433
title: The charitable percentage and the threshold it is compared against are percentages of different things — 0.6% against 10%, where the statutory figure is 0.81%
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0032-cycle4-rate-literals-and-the-charitable-denominator.md
owner: build-lead
reviewers: [tax-compliance-reviewer, quality-lead]
status: gated
claimed_by: build-lead
severity: medium
surfaces: [web]
created: 2026-08-23T03:25:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0399, W-0154, W-0431]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer verdict 2026-08-23, condition C2. Full text at workforce/ops/handoffs/W-0399/tax-compliance-reviewer-verdict-2026-08-23.md
---

## Intent

**Raised by the tax-compliance gate on W-0399, and deliberately NOT folded into
it** — it moves a published figure, so it gets its own item and its own review.

The sentence W-0399 left in place reads:

> Your charitable giving of **0.6%** (£10,000) is below the **10%** threshold of
> £122,878.

**Those two percentages are percentages of different things**, so the comparison
the sentence invites is not a comparison at all.

| Term | Measured against | Value |
|---|---|---|
| `charitable_percent` | the **net estate**, £1,728,780 | 10,000 ÷ 1,728,780 = **0.58%** → "0.6%" |
| the 10% threshold | the **baseline**, £1,228,780 | Schedule 1A's base |

**The statutory figure is £10,000 ÷ £1,228,780 = 0.81%.** The user is shown 0.6%
and invited to compare it with 10%, when the number that belongs beside 10% is
0.81%.

`IHTCalculationService.php:1330` computes it:

```php
$charitablePercent = $netEstate > 0 ? ($survivorBequestTotal / $netEstate) * 100 : 0;
```

### The amounts are all correct, which is why this is medium

£10,000, £122,878 and the £112,878 shortfall are all right, and all computed
against the baseline. **Nobody is told a wrong amount to give.** The defect is a
percentage that cannot be compared with the percentage printed next to it.

### The application already knows the right answer

`EstatePlanService::charitablePercentage()` (`:587`) computes it against the
baseline, **with a docblock saying exactly that** — *"Charitable giving as a
percentage of the baseline amount"* — and its own scar comment recording that
reading a key the analysis never emits had pinned it to `0.0`.

**So two web surfaces compute this percentage two different ways for one
household**, and the one that is right is not the one on `/estate`. That is a
Rule 20 consolidation, not a new calculation: **route the Inheritance Tax card
onto the definition that already exists and is already documented as correct.**

## Fix — 2026-08-23

`IHTCalculationService:1338` now divides by the **baseline**, which is Schedule
1A's own denominator and is already computed three lines above. Routed onto the
definition `EstatePlanService::charitablePercentage()` has documented all along;
no third definition introduced.

Measured on the household that raised it: **0.5784% → 0.8138%.**

## Acceptance

- [~] **PARTIALLY MET, and I ticked it wrongly.** Both surfaces now use the
      Schedule 1A **baseline** as the denominator — that half is done. **The
      numerators still differ:** `/estate` uses the survivor's will,
      `/plans/estate` uses the logged-in user's own. They coincide on this
      persona and on every single-person household, so no fixture discriminates.
      Caught by the tax-compliance gate (C6), filed as **W-0452**.
- [x] `/estate` computes **0.81%** for this household, not 0.6%.
- [ ] The numerator and the denominator named in the same sentence, or neither —
      a bare percentage beside a threshold implies they share a base.
- [x] Verified on both spouses' accounts by the existing pair test — the figure
      is a property of the household's second death and does not differ by
      session.
- [x] **Asserted as a RECONCILIATION rather than a literal:**
      `percent ÷ 100 × baseline == rate_test_amount`. On many households the
      baseline is a round fraction of the net estate, so a wrong denominator
      lands on a plausible number; a reconciliation holds whatever the
      household. A companion case asserts the two denominators **give different
      answers in this fixture**, so it cannot silently stop discriminating.
- [x] Mutation-tested: restoring the net-estate denominator turns exactly those
      two cases red.
- [ ] **Rendered page unverified** — browser handshake pending.
- [ ] `tax-compliance-reviewer` to confirm the baseline is the correct
      denominator for a figure presented to a user as "your charitable giving",
      given Schedule 1A defines the 10% test against it.

## Working notes

- 2026-08-23 build-lead: filed from condition C2 of the W-0399 gate verdict.
  **Not fixed in F-0031** — that batch's clearance covers publishing a discarded
  figure and removing rate literals, and changing a published percentage is
  neither. Folding it in would have put an unreviewed figure change inside a
  cleared batch.

## Working note — 2026-08-23, W-0451 verdict condition C5

**W-0432's condition C2 — the profile-percentage branch — is CLOSED, and it was
F-0033 that closed it.**

C2 recorded that `charitable_giving_percent` had two definitions depending on
which branch ran: a percentage of the **baseline** when a will recorded a legacy,
and the user's typed Inheritance Tax profile figure — a percentage of the **net
estate** — when it did not. One published key, two meanings, decided by whether
the user happened to have recorded a bequest.

**Verified by the tax-compliance gate at `IHTCalculationService:1466`:** the
percentage is computed **once, after both branches**, from `$rateTestAmount ÷
$baseline`. The profile percentage remains what it always was — an input for
deriving the intended amount — and is no longer mistaken for the output.

**One consequence, advisory (verdict C7, no item required):** a user who types 5%
on their Inheritance Tax profile is now shown `5 × netEstate ÷ baseline` back —
7.0% on the peak_earners household. **That is statutorily correct**, because
Schedule 1A measures against the baseline and not the net estate, and it is the
whole point of closing C2. But the input and the output now carry the same words
and different values. **Label the output so it is not read as an echo of the
input.** No fixture reaches it: `iht_profiles` holds 0 rows with
`charitable_giving_percent > 0` (of 2). Any user can reach it.
