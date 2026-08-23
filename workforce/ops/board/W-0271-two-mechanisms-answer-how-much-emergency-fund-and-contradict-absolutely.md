---
id: W-0271
title: Two mechanisms answer "how many months of emergency fund do you have" and contradict absolutely — 81 months and 0 months of the same cash, in one session
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0024-cycle4-risk-engine-reach-and-fraction.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T21:00:00Z
claimed: 2026-08-22T21:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0238, W-0190, F-0019, F-0022]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Raised as D-07 by `peak-earners-c4` in cycle 4. Measured live before any edit.

### The defect

Same user, same session, both live:

| Mechanism | Says |
|---|---|
| `SavingsAgent` → dashboard, `/net-worth/cash`, `/m` | David **79.8 months**, Sarah **25.3**, *"Your emergency fund is well-funded. Excellent!"* |
| `AutoRiskCalculator` → `/risk-profile` | Both **"0 months"**, Lower-Med, *"Less than 3 months emergency fund suggests keeping investments more conservative."* |

`app/Services/Risk/AutoRiskCalculator.php:368-370` counted only savings accounts
flagged `is_emergency_fund`. **All six of this household's accounts hold that flag at
0**, including a £50,000 National Savings holding and two Cash ISAs, so the risk
engine saw £0 against £130,780 of real cash.

**The numerator was not the whole defect** — though I first overstated the second
half and am correcting it here. The same method read `users.monthly_expenditure`
directly while the savings module resolves a chain preferring an
`expenditure_profiles` row. **Measured, the two agree per user today** (David
£1,250/£1,250; Sarah £1,225 with no profile row), so routing the denominator moved no
number on this persona. The £1,250/£1,225 gap is **between the spouses** — that is
**D-26**, not this. Routing it is still right structurally: the two sources can
diverge, and did historically, which is how a stale 41.6-month reading survived beside
a live 83.3.

The same line also carried the household reach failure — `where('user_id', …)` with
no `joint_owner_id` and no share — so a flagged joint account would have been
invisible to one spouse and counted whole against the other.

**This is not a display bug.** The losing definition feeds the risk level that drives
every projection in the application.

## Acceptance

1. `/risk-profile` emergency-fund months agree with the dashboard and `/net-worth/cash`
   **to the month**, on both accounts.
2. A joint account contributes each owner's stored share, not 100% and not 0%.
3. What `is_emergency_fund` now means is stated explicitly, and it is no longer the
   sole determinant of whether the user has any runway at all.

## Working notes

**DONE.** Routed to three homes that already existed; no arithmetic added:

- cash — `CrossModuleAssetAggregator::calculateCashTotal()` (reach-complete, at the
  user's share; what `/net-worth` and `SavingsAgent` already read)
- expenditure — `ResolvesExpenditure`, the same chain `SavingsAgent` resolves
- runway — `EmergencyFundCalculator::calculateRunway()`

Measured after: David **79.8 months** (£99,750 ÷ £1,250), Sarah **25.3** (£31,030 ÷
£1,225) — the savings module's own numerator and denominator, so the surfaces agree
by construction rather than by coincidence. **Sarah's denominator is the one D-26 will
correct to £1,250**, taking her to ≈24.8 months on all three surfaces at once. Expected,
not a regression here.

**Decision on the flag (dispatch asked for it explicitly): `is_emergency_fund` is a
designation, not a definition.** It keeps the account badge, the "designate an
emergency fund" action, and the draw-down preference in `LifeEventAllocationService`.
It stops deciding whether the user has any runway. Money in an instant-access account
is available in an emergency whether or not somebody ticked a box about it.

**Known cost, recorded not buried:** the runway now counts cash the user cannot reach
quickly — a five-year bond counts like a current account. Not a regression (the
savings module always counted it that way; this makes the risk page agree), but wrong
in a way `LiquidityAnalyzer` could fix. Raised as **W-0276**.

**Behaviour change, deliberate:** with no expenditure recorded the old code printed an
invented **"12 months"**. `calculateRunway` would print 0.0 → *"Less than 3 months"*,
the opposite lie. It now reads **"Not calculated"** at level `medium`, following the
rule this same class already applies to an unknown age.

Frontend strings the fix made false were corrected in the same change
(`RiskFactorDetailPage.vue`: "Source: Savings accounts marked as emergency fund" →
the cash rule; numerator label "emergency fund" → "cash savings").

Tests: 6 new feature tests on real records at a 75/25 split, including one that drives
the real `SavingsAgent` and asserts the two runways are **identical**. The three
existing unit tests were flipped from a flagged fixture to an unflagged one — flagged,
the right answer and the wrong answer are the same number and none of them could fail.

**Browser-verified, both accounts, `localhost:8000`.** David: `/risk-profile`
**79.8 months Upper-Med**, detail page **£99,750 ÷ £1,250**, dashboard **79.8 / 6
months**, `/net-worth/cash` accounts summing to **£99,750**. Sarah (through the MFA
gate, code fetched from the database): `/risk-profile` **25.3 months**, dashboard
**25.3 / 6 months £31,030**, **`/m` dashboard 25.3 / 6 months £31,030**. Agreement is
exact on both accounts and both surfaces. Screenshots in F-0024 §7.

**The browser pass found what the tests could not: `/savings` → Emergency Fund still
reads "0.0 months / £0"** on both accounts — a client-side fourth implementation still
filtering on the flag. Raised as **W-0274** at HIGH, not fixed (savings frontend, out
of scope).
