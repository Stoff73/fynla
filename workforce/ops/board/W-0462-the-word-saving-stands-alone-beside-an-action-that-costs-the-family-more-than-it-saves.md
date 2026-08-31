---
id: W-0462
title: "\"Save £74,987\" is attached to an action that leaves the beneficiaries £37,891 worse off — the tax figure is correct and the disclosure is missing"
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [compliance-lead, tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
surfaces: [web]
created: 2026-08-23T06:40:00Z
claimed: null
blocked_by: []
gate: compliance-lead
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0451, W-0452, W-0432-verdict, W-0451-verdict-C2]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
source: tax-compliance-reviewer verdict 2026-08-23 on W-0451/W-0452, condition C2 — "a Consumer Duty question before it is a tax question"
---

## Intent

**Raised by the statutory gate that cleared the figure.** The reviewer confirmed
the saving is the lawful answer to the question the sentence asks, and then said
plainly that the sentence is materially incomplete:

> The word **"saving"** stands alone in a sentence recommending an action that,
> on user 16 today, leaves the beneficiaries **£37,890.72 worse off.**
> **The figure is correct; the disclosure is missing.**

### The arithmetic, verified end to end by the reviewer

```
as the will stands:   £1,728,780 − £20,000 gift  − £343,512.00 tax = £1,365,268.00 to beneficiaries
with the larger gift: £1,728,780 − £132,878 gift − £268,524.72 tax = £1,327,377.28 to beneficiaries
                                                                Δ = −£37,890.72
```

**Both statements are true.** The estate really does pay £74,987 less tax. And
the family really does receive £37,891 less. **Only one of them is on the page.**

### The break-even, which is the point of the item

Writing `r_s`, `r_r` for the two rates, `E` for the chargeable estate and `S` for
the shortfall, the change in what the non-charity beneficiaries receive is

```
Δresidue = (r_s − r_r)·E  −  S·(1 − r_r)          at 40/36:  0.04·E − 0.64·S
```

so the beneficiaries are better off only while

> **S < E·(r_s − r_r)/(1 − r_r)** — at 40/36, **a shortfall under 6.25% of the
> chargeable estate.**

**Below that line the recommendation is genuinely good advice.** That is why this
is a disclosure item and not a "stop recommending it" item: the sentence should be
able to say **which side of the line this household is on**, and today it cannot,
because nothing computes the residue.

### Not introduced by W-0451, and made materially worse by it

**The framing is pre-existing** — the old sentence said "saving £19,580" for the
same action. But W-0451:

- **multiplied the published figure by 3.8×** (£19,580 → £74,987), which makes the
  omission proportionally more consequential; and
- **added** the clause *"and the additional £112,878 leaves the estate as an
  exempt gift"* — a disclosure that the gift leaves the estate, **framed as part
  of the benefit rather than as the cost.**

The second is the sharper point. The sentence now states the mechanism by which
the family loses money, in the middle of a sentence whose headline is a saving.

### What each surface carries today

| Surface | Mitigation |
|---|---|
| `/estate` charitable card | *"A scenario only — nothing above changes until the gift is in your will"* — partial, and about timing rather than cost |
| decision trace (`/actions/estate/*`) | **none** |
| `/plans/estate` panel and printed plan | **none** |

## Acceptance

1. **State the net effect, or state the cost beside the saving.** One of:
   the change in what the beneficiaries receive; or the gift amount and the tax
   saving side by side with the difference named. **Not a disclaimer** — a figure.
2. **Encode the break-even** `S < E·(r_s − r_r)/(1 − r_r)` from `TaxConfigService`
   rates (Rule 2 — the constant is 6.25% only at 40/36 and must not be written as
   a literal), so the sentence can say which side of it the household is on.
3. **One definition, all three surfaces** (Rule 20) — the trace, `/plans/estate`
   and the printed plan, and `/estate`'s card. The saving already comes from one
   home (`charitable_rate_saving`); the residue effect should join it there rather
   than being composed per surface.
4. **`compliance-lead` gate before any wording ships.** This is a Consumer Duty
   question before it is a tax question: a headline benefit figure attached to an
   action with an unstated cost to the customer.
5. Verified on both spouses' sessions on a household **each side** of the
   break-even — the peak_earners household is well above it (shortfall £112,878
   against a chargeable estate of £858,780, i.e. 13.1%, more than double the 6.25%
   line), so it can only demonstrate the losing case.

## Working notes

**2026-08-23 — filed by build-lead (`fix-cycle4-figures`) from F-0033, not fixed
there.** The gate that raised it said explicitly: *"Not a reason to withhold
clearance — the tax figure is correct and is a large improvement on what it
replaced. It is a reason to file the disclosure as its own item and route it to
compliance-lead."*

**Deliberately not folded into W-0451.** It is a disclosure and suitability
question, its gate is `compliance-lead` rather than `tax-compliance-reviewer`, and
W-0451's own reviewer asked for it to travel separately.

**A closing observation the next reader should have:** every check F-0033 built
asks whether the sentence is internally consistent — do the printed figures
subtract to the printed answer. **All of them pass on a sentence that recommends
a value-destroying action, because arithmetic consistency and advice quality are
different properties** and nothing in the batch tested the second.

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev`.** `grep -rn 'W-0462'` across `app/`,
  `resources/` and `tests/` returns **nothing**, so no work has been done on this item.
  The engine half the reviewer asked for **does** exist — `WillAnalysisService:144-149` publishes
  `taxable_estate`, `taxable_estate_if_qualifying`, `tax_at_standard_rate` and
  `tax_at_reduced_rate` beside `potential_saving`, with a docblock saying they are there "so every
  sentence that quotes the saving can print its own working". **No sentence uses them.** The
  disclosure the gate required — that the recommended action leaves the beneficiaries £37,890.72
  worse off on user 16 — is still absent from every surface. The figure remains correct; the
  disclosure is still missing.
