---
id: W-0205
title: The row labelled "Net Income" deducts the Gift Aid gross-up, which net income does not — for a Gift Aid donor the label names a statutory figure the number beside it is not
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0020-cycle2-auditability-figures-the-user-cannot-check.md
owner: brett-2026-08-25
status: handoff
severity: low
surfaces: [web]
created: 2026-08-22T07:26:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0189]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by `cycle2-audit` while fixing **W-0189**. Adjacent to it, deliberately left
alone: W-0189 was about steps that were displayed and not applied, and this is a step
that IS applied, under a name that does not describe it.

**Surface:** `/valuable-info?section=income` → "Your Income Definitions", the "Net
Income" row.

### Expected

"Net income" is a defined term. ITA 2007 s23 Step 2: total income less the reliefs
listed in s24. **Gift Aid is not one of them** — a Gift Aid donation extends the
basic rate band; it does not reduce net income. The grossed-up donation is deducted
one definition further down, at **adjusted net income** (ITA 2007 s58), alongside the
Blind Person's Allowance.

### Actual

`app/Services/Tax/IncomeDefinitionsService.php:35`:

```php
$netIncome = $totalIncome - $pensionRelief - $giftAidGross;
```

and the panel renders "Less Gift Aid (grossed up)" **above** the Net Income line
(`IncomeDefinitionsPanel.vue`, the deductions-to-net-income block).

So for a Gift Aid donor the figure labelled "Net Income" is net income **less the
grossed-up donation** — which is part of the way to adjusted net income, and is not a
figure with a name.

**The end results are all correct.** Adjusted net income is right, because the Blind
Person's Allowance is the only thing deducted after this point and the donation had
to come off somewhere. Threshold income is right, because it is built from **total**
income (`:47`) and never touches this intermediate — which is the W-0189 finding.
Only the intermediate row is mislabelled.

### Impact

Low, and bounded. No calculated outcome changes: no allowance, taper, charge or
liability is decided from the `net_income` key.

It matters because this panel exists to be checked, and it is checked by exactly the
people who would notice — someone reconciling their own figures against HMRC's
definitions. For them the column now adds up (W-0189) while one of its labels names
the wrong statute. A non-donor never sees it, because `gift_aid_gross` is zero and
the row is not rendered.

### Repro

1. A user with `is_gift_aid = true` and `annual_charitable_donations > 0`.
2. `/valuable-info?section=income` → "Your Income Definitions".
3. "Less Gift Aid (grossed up)" appears **above** "Net Income". Under ITA 2007 it
   belongs below it, with the Blind Person's Allowance.

### Acceptance

1. [x] The Gift Aid gross-up is deducted at adjusted net income, not at net income, and
   the panel renders it in that position.
2. [x] `net_income` in the service payload is net income as ITA 2007 s23 Step 2 defines
   it. **Check every consumer of the key before moving it** — the figure changes for
   Gift Aid donors, and the point of W-0189 is that a figure and its account of
   itself must not part company. — swept; **no calculation reads it.**
3. [x] `adjusted_net_income` and `threshold_income` are unchanged by the fix. If either
   moves, the fix is wrong. — both unchanged, measured on the persona AND pinned by a
   donor-vs-non-donor test.
4. [x] Pinned by a test with a Gift Aid donor asserting all three figures against the
   statutory definitions, not against the previous output. — five new cases, mutation-
   verified red against the old code.
5. [x] **`/m` and native have no counterpart** (verified by grep, 2026-08-22) — but the
   `net_income` key is shared, so confirm no other surface reads it before changing
   what it means. — confirmed; the shared name is a **collision**, not a shared figure.

## Working notes
(append-only)

- 2026-08-22 cycle2-audit (build-lead): raised from `F-0020`, not fixed — scope
  discipline. The displayed chain **does** add up as printed for a Gift Aid donor, so
  W-0189's acceptance 1 is met either way; this is a naming defect, not an
  arithmetic one, and it deserves its own consumer sweep. See `F-0020` §6.

- 2026-08-25 (Brett, working alone per CSJ's 2026-08-24 standing instruction):
  **FIXED and verified live on the persona.** The Gift Aid gross-up now comes off at
  ITA 2007 s58, beside the Blind Person's Allowance, and "Net Income" is s23 Step 2.

  **Acceptance 2 — the consumer sweep, and what it found.** There are **two unrelated
  `net_income` keys in this codebase and they mean different things.** `UKTaxCalculator`
  publishes `net_income` meaning **take-home pay** (gross less tax and National
  Insurance); this service publishes `net_income` meaning **the statutory s23 figure**.
  Most of a repo-wide grep is the former and is untouched — including
  `ResolvesIncome:106`, which reads `UKTaxCalculator::calculateNetIncome`, not this
  service. The shared name is a collision, not a shared figure.

  Of the six real consumers of `IncomeDefinitionsService::calculate()`, **none reads
  `net_income`:**

  | Consumer | Reads |
  |---|---|
  | `AnnualAllowanceChecker:115-116` | `threshold_income`, `adjusted_income` |
  | `TaxStrategyMath:175,407,422` | `adjusted_net_income`, `threshold_income`, `adjusted_income` |
  | `ChildBenefitService:218` | `adjusted_net_income` |
  | `UserProfileService:420` | `adjusted_net_income` |
  | `IncomeDefinitionsController` | the panel — display |
  | `CoordinatingAgent:2696` | passes the whole array to Fyn — display |

  So the item's "no calculated outcome changes" is confirmed by reading, not assumed.
  (`UserProfileService:585` reads `$detailedTax['summary']['net_income']` — that is the
  take-home figure from the other service.)

  **Acceptance 3 — measured on David (user 104, `peak_earners`, £2,400 of donations,
  the very persona this was raised from):**

  | Figure | Before | After |
  |---|---|---|
  | Net Income | £144,689.60 | **£147,689.60** |
  | Adjusted Net Income | £144,689.60 | £144,689.60 — unchanged |
  | Threshold Income | £147,689.60 | £147,689.60 — unchanged |
  | Adjusted Income | £170,889.60 | £170,889.60 — unchanged |

  **An independent corroboration nobody asked for.** The tax breakdown higher up the
  SAME page taxes **"Taxable Income £147,690"**. Before this fix the panel said net
  income was £144,690 while the table four inches above taxed £147,690 — two figures
  for one statutory concept on one screen, in the panel whose whole purpose is to be
  checked. They now agree.

  **Consequence, recorded in the code rather than left to be rediscovered:** net income
  and threshold income are now **the same number** for a net-pay contributor, because
  the Gift Aid gross-up was the only thing separating them. That coincidence is
  correct — for someone with no salary sacrifice and no relief-at-source contributions
  the two definitions genuinely land on the same figure. It also broke an existing
  W-0189 assertion (`net_income !== threshold_income`) whose stated rationale was that
  Gift Aid separates them. That assertion was **moved to `adjusted_net_income !==
  threshold_income`**, which is the pair Gift Aid legitimately separates — the test's
  purpose is preserved, its differentiator corrected.

  **Tests — mutation-verified, per `tests/CLAUDE.md` §4.** Five new cases in a
  `W-0205` describe block, asserting against the statutory definitions: a donor and an
  identical non-donor share a net income and differ only at s58; the gross-up is
  deducted exactly once between the two; Gift Aid and the Blind Person's Allowance are
  deducted at the same step; threshold and adjusted income are untouched by a donation;
  and the Personal Allowance taper reads s58 — the one place the distinction reaches a
  figure a user is charged on. **Reverted the fix and confirmed 5 tests go red**, so
  none of them is a Collision that passes either way. Two frontend cases pin the row's
  POSITION (Gift Aid between Net Income and Adjusted Net Income), also mutation-verified
  against the old layout.

  Green: Tax + Benefits + AnnualAllowanceChecker 229/637; Tax + Api + UserProfile
  727/5,283; UserProfileService + Income + AA 27/80; panel vitest 10/10. Pint clean.

  **Adjacent, REPORTED NOT FIXED — worth a look, not fixed here because it is not this
  item.** `getPensionContributions()` publishes `arrangement: 'salary_sacrifice'` when a
  DC pension has `salary_sacrifice` set, but threshold income deducts the employee
  contribution identically either way. Under FA 2004 s228ZA a **post-8-July-2015 salary
  sacrifice is added BACK** to threshold income precisely to stop it being used to dodge
  the taper. If that is right, a sacrificing high earner's threshold income is
  understated and the Annual Allowance taper can be missed. The service already knows
  which arrangement applies, so the information is there — it is the arithmetic that
  does not branch. **I have not verified this against a fixture and am not claiming it
  as a defect; it needs a tax-compliance eye.**

  **Not done:** no `tax-compliance-reviewer` gate was run — CSJ's standing instruction
  bans agents this session. The change is a definitional relabel with no figure moving
  except the one the item asked to move, but a statutory reviewer may still want it.
