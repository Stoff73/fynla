# R-20 — Cycle 4, batch 2

**Agent:** `peak-earners-c4` (persona-tester) · **Persona:** `peak_earners`
**Surface:** web, local · **Account driven:** Sarah (user 17), with David (16) cross-checked from the same mechanisms
**Batch closed:** 2026-08-22 ~18:21 · Continues [R-19](R-19-cycle4-batch-1.md)

Batch 2 covers the risk engine and the investment projection — surfaces the batch-1 fixes
(mortgage share, dashboard aggregator, liabilities total) do not touch.

---

## Done

Drove Sarah's `/net-worth/investments`, `/risk-profile` and `/net-worth/cash` in the
browser, opened the savings account form, and cross-read `risk_profiles.factor_breakdown`
for both users. All figures below are live — the six per-user caches were cleared before
these readings (see R-19 D-05).

### Verified GREEN
- Sarah's Investments page: £85,000 ISA + joint GIA "Full Value £95,000 / Your Share
  (50.00%) £47,500 / Held with David Jones" → **Current Portfolio £132,500**. Correct.
- Sarah's Bank Accounts: Current Accounts £8,530 (£6,280 + £2,250 with "Total: £4,500"),
  Cash ISAs £22,500 → £31,030. Correct.
- The savings account form **does** offer "This forms part of my emergency fund" — so
  `is_emergency_fund` is settable. That matters for D-07's framing.

### A finding I withdrew before raising it
"Add Account does nothing" — reproduced twice, then failed to reproduce on a freshly
loaded page. The button exists in the DOM before its handler is live; my click landed in
the gap. **Not a defect.** Noting it because it is a convincing false positive and the
next tester will hit it.

### A number I checked and did not raise
David's dashboard "41.6 / 6 months" emergency runway. Live, the SavingsAgent returns
**83.27** months for him (£102,000 ÷ £1,225). The 41.6 was £102,000 ÷ £2,450 — the
pre-W-0190 denominator, frozen in the stale cache. That is R-19 D-05 evidence, not a
separate defect.

---

## Defects found — 4 (D-07 … D-10)

### D-07 (HIGH) — Two mechanisms answer "how many months of emergency fund do you have?" and contradict each other absolutely

Same user, same session, both live:

| Mechanism | Says |
|---|---|
| `SavingsAgent` → dashboard + `/net-worth/cash` | David **83.27 months**, Sarah **23.49 months**, category **"Excellent"**, *"Your emergency fund is well-funded. Excellent!"* |
| `AutoRiskCalculator` → `/risk-profile` | Both **"0 months"**, level **Lower-Med**, *"Less than 3 months emergency fund suggests keeping investments more conservative."* |

`app/Services/Risk/AutoRiskCalculator.php:368-370`

```php
$emergencyFundTotal = SavingsAccount::where('user_id', $user->id)
    ->where('is_emergency_fund', true)
    ->sum('current_balance');
```

It counts only accounts flagged `is_emergency_fund`. Every one of this household's six
accounts has the flag at 0, so the risk engine sees £0 against £130,780 of actual cash —
including a £50,000 NS&I holding and two Cash ISAs.

The flag **is** settable (the savings form has the checkbox), so this is not "no way to
record it". It is two definitions of one concept, and the disagreement is not marginal —
one says "well-funded, excellent", the other says "under 3 months, invest more
conservatively", and the second one **feeds the risk level that drives every projection.**

Same line carries the household-reach failure from R-19 D-04: `where('user_id', …)` with
no `joint_owner_id` and no share, so a flagged joint emergency account would be invisible
to one spouse and counted whole for the other.

Screenshot: `126-web-sarah-risk-profile-zero-dependants-zero-emergency-fund.png`

### D-08 (HIGH) — A linked spouse's risk profile is assessed as childless

Sarah's `/risk-profile` shows:

> **Dependants · 0 · Upper-Med** — *"No dependants means you can afford to take more
> investment risk."*

She is the mother of the household's two dependent children. `family_members` 21
(William, 2007-09-15) and 22 (Charlotte, 2010-02-28) are both `is_dependent = 1` and both
`user_id = 16` — recorded on David. David's own profile correctly reads
**Dependants · 2 · Lower-Med** — *"Multiple dependants means financial stability is a
priority."*

`app/Services/Risk/AutoRiskCalculator.php:277-279`

```php
$dependants = FamilyMember::where('user_id', $user->id)
    ->where('is_dependent', true)
    ->get(['first_name', 'relationship', 'stated_relationship']);
```

`user_id`-only, in a household whose `spouse_id` is reciprocal and whose
`SpousePermission` is accepted in both directions. The two parents get opposite
risk guidance from the same two children, and the factor pushes Sarah's risk level **up**
on a false premise. Same reach disease as R-19 D-04, third mechanism.

Screenshot: `126-web-sarah-risk-profile-zero-dependants-zero-emergency-fund.png`

### D-09 (HIGH) — The projection card contradicts the rate printed on it

Sarah's `/net-worth/investments`, horizon **10 Years** (the selected option):

- Current Portfolio **£132,500**
- Projected Value (80%) **£316,777**
- Caption: *"Using Medium risk profile (**5.00% expected return**)"*

£132,500 compounded at 5.00% for 10 years is **£215,828**. £316,777 implies **9.11% a
year**. There are no contributions to close the gap — `monthly_contribution_amount` and
`planned_lump_sum_amount` are both `NULL` on all four of the household's investment
accounts (ids 13, 14, 26, 27), so this is pure growth on capital.

Worse, £316,777 is the **80% probability** band — a conservative percentile that should
sit *below* the mean, not 47% above it.

David's card, same page, same "Medium Risk" label: £172,500 → £217,451, captioned
**5.41% expected return**. That is 2.34% a year — *below* its stated rate, while Sarah's
is far above hers. Two users, one label, rates that disagree with themselves in opposite
directions.

**Route to W-0217, do not raise separately.** This is the same phenomenon that item
describes, but it adds something W-0217 could not have: W-0217's table used capital of
£85,000 (Sarah) and £220,000 (David) — the *broken* agent totals from R-19 D-04. This
reading uses the **correct** £132,500 and £172,500 from the module page and the anomaly
persists. So "the capital inputs were wrong" is now ruled out as the explanation.

Screenshot: `127-web-sarah-projection-132500-to-316777-on-stated-5pct.png`

### D-10 (MEDIUM) — R-19 D-04's wrong totals propagate into the risk profile, which drives every projection

`risk_profiles.factor_breakdown`, Capacity for Loss, written 2026-08-22 08:04:14:

| | components as stored | should be |
|---|---|---|
| David | `net_worth 1477500, pensions_total 500000, investments_total 220000` → **48.7%** | investments £172,500 → 45.5% |
| Sarah | `net_worth 739280, pensions_total 0, investments_total 85000` → **11.5%** | investments £132,500 → ≥17.9% |

Two problems, both visible on `/risk-profile`:

1. `investments_total` is the un-shared agent figure (R-19 D-04), not the £172,500 /
   £132,500 the Investments and Wealth Summary pages both agree on.
2. Sarah's `pensions_total` is **0** while she holds an NHS Defined Benefit pension paying
   £35,000 a year with a £105,000 tax-free lump sum. Her own retirement page states
   "Guaranteed Retirement Income · Total Annual Income £35,000/year".

The user-facing sentence built from these is *"11.5% of your net worth is in
investments/pensions, giving you high capacity to take risk"* — a factual claim about her
finances, computed from two wrong inputs, that then contributes to the risk level feeding
D-09's projection.

**Impact note for R-19 D-04, not a separate fix.** It raises D-04 from "a dashboard card
is wrong" to "a wrong figure reaches the risk assessment and therefore every projection".

### Also observed, low severity, not raised as items
- `/risk-profile` states the rule *"The most common risk level across all factors becomes
  your overall risk level"*, then shows Sarah's tally as **Medium: 3 · Upper-Med: 3** — a
  tie — and returns Medium. The tie-break is real, silent, and always downward. Worth a
  sentence of disclosure rather than a work item.
- Sarah's `/net-worth/cash` renders an empty "NS&I" section with an Add Account button.
  Harmless; David's £50,000 NS&I holding is individual and correctly not shown to her.

---

## Not done, and why

Still queued for batch 3, none of it blocked:
- The **10 missing holdings** — the live test of W-0039 and W-0009 (both `handoff`).
- **State pension** absent for both users; the retirement page already flags it OUTSTANDING.
- The **four missing children's bequests**; both charities stored `beneficiary_type=individual`.
- **"Charlotte's Gap Year Fund"** goal missing entirely (5 of 6 goals present).
- **Adviser fee 0.75%** absent on all four investment accounts; platform fee `NULL` on
  David's Hargreaves Lansdown ISA.
- **David's risk preference** — persona Upper Medium, stored `medium` on the ISA and VCT.
- **Sarah's will** naming "Sarah Jones, Barclays Wealth" as her own executor (the W-0024
  shape; W-0024 is `handoff`).
- `dc_pensions.annual_allowance_used_gbp = 38.67` — a percentage in a `_gbp` column.
- `/m` parity for everything raised in R-19 and R-20.

## Assumptions

- The `/risk-profile` factor rows are live: the stored row was recomputed today at
  08:04:14, well after every record it reads existed, and it is what the page renders.
- I read the 80% band as "80% probability of at least this value", per the chart legend
  (90/85/80/75% Probability). If it means the opposite, D-09 is worse, not better.

## Needs

- Board IDs for D-07 and D-08. D-09 and D-10 should be **appended to existing items**
  (W-0217 and R-19 D-04 respectively) rather than raised fresh.
- D-07, D-08 and R-19 D-04 are the same disease — a household figure derived with
  `where('user_id', …)` and no share — in four mechanisms now. Worth one agent and one
  sweep of `AutoRiskCalculator` rather than three point fixes.

## Noticed

- The reach failure now has four confirmed sites: `InvestmentAgent:72`,
  `User::savingsAccounts()` (`User.php:732-735`), `AutoRiskCalculator:277-279`,
  `AutoRiskCalculator:368-370`. F-0019 named the vocabulary (reach / fraction); this is the
  same vocabulary, still spreading. A grep for `where('user_id', $user` across
  `app/Services` and `app/Agents` would likely find more.
