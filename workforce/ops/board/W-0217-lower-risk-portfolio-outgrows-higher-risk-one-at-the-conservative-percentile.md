---
id: W-0217
title: A £85,000 medium-risk portfolio projects higher than a £220,000 portfolio containing an upper-medium account — the lower risk produces the higher return, at the conservative percentile
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0024-cycle4-investment-projection.md
owner: build-lead
status: gated
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T08:10:00Z
claimed: 2026-08-22T20:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0137, F-0018]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found by `cycle2-projection` while reconciling the projected estate from its parts for
W-0137. **Start from the comparison, not from the number.** The magnitude is what makes
it worth taking; the comparison is the finding, and it is what tells you where to look.

### The comparison

Two portfolios, one household, same 36-year horizon, same simulation, same run:

| | Sarah (17) | David (16) |
|---|---|---|
| Accounts | 1 | 3 |
| Capital today | **£85,000** | **£220,000** |
| Risk preference | `medium` | `upper_medium` on the largest, `medium` on two |
| Contributions | **none** | **none** |
| Twentieth percentile at 36 years | **£1,577,731** | **£1,025,964** |
| Implied annual, p20 | **8.44%** | **4.37%** |
| Median at 36 years | £2,310,532 | £1,702,169 |
| Implied annual, median | 9.60% | 5.85% |

**39% of the household's investment capital produces 61% of its projected investment
value.**

### Why this is a question about the model, not about a number being high

**1. The lower risk preference produces the higher return.** Sarah's only account is
`medium`. David's largest is `upper_medium` — a higher risk setting — and his portfolio
compounds at roughly half her rate. That is backwards, or it is being driven by
something other than risk preference.

**2. It is not a percentile artefact.** The gap holds at the median as well as at p20,
so it is the whole distribution, not the tail. Sarah's p20/median ratio is 0.683 against
David's 0.603 — **her distribution is both higher and narrower.** Lower risk should mean
lower return *and* lower dispersion. She has lower risk, higher return and lower
dispersion.

**3. Contributions are not the explanation.** `monthly_contribution_amount` is null on
all four accounts and no contribution override was passed. This is pure growth on
capital, so the entire difference is rate.

**4. 8.44% a year at the twentieth percentile is not a conservative figure.** p20 is
meant to be the pessimistic case a plan is stress-tested against.

### One observation, offered as a lead and not as a diagnosis

**Two of David's three accounts hold zero holdings rows; both accounts that do hold one
belong to the higher-growing side of every comparison.**

| Account | Owner | Value | Risk | Holdings |
|---|---|---:|---|---:|
| ISA 13 | Sarah | £85,000 | `medium` | **1** |
| GIA 14 | David | £95,000 | `upper_medium` | **1** |
| ISA 26 | David | £95,000 | `medium` | **0** |
| VCT 27 | David | £30,000 | `medium` | **0** |

**£125,000 of David's £220,000 sits in accounts with no holdings at all.** If asset
allocation is derived from holdings and a holdings-less account falls back to something
cash-like, that would drag his portfolio's blended rate down without any risk preference
being wrong. **This is a place to look first, not a conclusion** — it was not tested,
because the simulator is a different module.

### Impact

**£1,577,731 of a £2,603,695 projected investment line rests on this, and roughly
£630,000 of projected Inheritance Tax.** The projection is the figure a user acts on
when deciding whether to gift, to insure, or to do nothing.

If the model is right, a household is being told a £85,000 ISA becomes £1.58m in the
pessimistic case. **If it is wrong, every projected estate in the application is wrong
by whatever this is**, because nothing here is specific to these two people.

### Not investigated

`InvestmentProjectionService` and `MonteCarloSimulator` were **not** opened. Different
module, different item, and `cycle2-projection` was scoped to the cash projection.
Everything above is from the two portfolios' inputs and the service's published output.

### Repro

```php
app(\App\Services\Investment\InvestmentProjectionService::class)
    ->getPortfolioProjections(User::find(17), [36])['portfolio']['projections'][36]['percentiles'];
// p20 => 1577731 from 85,000 of capital, no contributions

app(\App\Services\Investment\InvestmentProjectionService::class)
    ->getPortfolioProjections(User::find(16), [36])['portfolio']['projections'][36]['percentiles'];
// p20 => 1025964 from 220,000 of capital, no contributions
```

### Acceptance

1. An explanation of why the smaller, lower-risk portfolio outgrows the larger one —
   either the model is corrected, or the behaviour is shown to be intended and why.
2. A higher risk preference produces a higher projected return than a lower one, all
   else equal, at every percentile reported.
3. An account with no holdings has a stated, deliberate treatment rather than an
   emergent one.
4. The twentieth percentile is defensible as a pessimistic case over long horizons.
5. Whatever changes, `projected_investments` remains **symmetric across the two spouse
   logins** — it currently is, by construction, and W-0188 must not reopen.

### Note for whoever takes it

`MonteCarloSimulator` **caches**, so these figures are stable between runs today.
Clearing that cache may move them, and the projected estate and tax with them. The
per-login *agreement* does not depend on the cache — both members' portfolios are summed
regardless of who is signed in — but the *magnitude* can move, so pin figures with the
cache state recorded.


---

## Resolution — build-lead, 2026-08-22 (F-0024)

**Two causes, both now fixed, and neither was the Monte Carlo.**

1. **David's side was deflated by a stale cache** (W-0251). His 36-year figure was
   projected from a cached simulation of **£47,500 at 6.5%**, not his £172,500 at 7.07%.
2. **Sarah's side was inflated by contributions she never entered** (W-0254). The estimator
   assumed the full ISA allowance, £1,667 a month for 36 years — **£720,144** of invented
   savings on an account with `monthly_contribution_amount = null`.

### The comparison, re-measured

| | Sarah (17) | David (16) |
|---|---:|---:|
| Capital | £132,500 (was read as £85,000 — W-0256) | £172,500 |
| 36-year p20 **before** | **£1,577,731** | £1,025,964 |
| 36-year p20 **after** | **£261,740** | **£1,148,134** |

**39% of the capital no longer produces 61% of the value.** The larger, higher-risk
portfolio out-projects the smaller one.

### Against the five acceptance criteria

1. **Explained and corrected** — see above. The model was not at fault; the store and the
   contribution assumption were.
2. **A higher risk preference produces a higher return at every percentile — NOT MET, and
   should not be.** The median and upside rise monotonically with risk; the 20th percentile
   is hump-shaped, because volatility widens the downside faster than expected return lifts
   it. Building monotonicity would mean breaking the model. **Raised as W-0259 with the
   measured table and a product decision for CSJ.**
3. **An account with no holdings has a deliberate treatment** — the lead did not hold.
   Holdings play no part in the projection at all: rate and volatility come from the
   account's `risk_preference` via `RiskPreferenceService`, never from asset allocation. A
   holdings-less account is not treated as cash and never was. David's ISA 26 (0 holdings)
   and GIA 14 (1 holding) are projected by the same rule.
4. **The twentieth percentile is defensible** — it is now a measured percentile rather than
   a linear interpolation between the 10th and 25th (W-0255).
5. **Symmetry across spouse logins holds** — the joint account contributes £55,257 at ten
   years to **both** logins, by construction rather than coincidence. W-0188 does not reopen.

### The cache note in the original item

*"`MonteCarloSimulator` caches, so these figures are stable between runs today. Clearing
that cache may move them."* — correct, and it was the whole story. Figures above were taken
with both personas' cache rows cleared; they are now reproducible because the simulation is
seeded from its inputs (F-0024 §3.3).

**`projected_investments` in the estate calculation moves as a consequence.** F-0018's
pinned £2,603,695 is no longer a valid baseline — see F-0024 §10.1.
