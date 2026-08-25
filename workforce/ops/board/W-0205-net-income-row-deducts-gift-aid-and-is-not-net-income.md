---
id: W-0205
title: The row labelled "Net Income" deducts the Gift Aid gross-up, which net income does not — for a Gift Aid donor the label names a statutory figure the number beside it is not
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0020-cycle2-auditability-figures-the-user-cannot-check.md
owner: null
status: queued
severity: low
surfaces: [web]
created: 2026-08-22T07:26:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
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

1. The Gift Aid gross-up is deducted at adjusted net income, not at net income, and
   the panel renders it in that position.
2. `net_income` in the service payload is net income as ITA 2007 s23 Step 2 defines
   it. **Check every consumer of the key before moving it** — the figure changes for
   Gift Aid donors, and the point of W-0189 is that a figure and its account of
   itself must not part company.
3. `adjusted_net_income` and `threshold_income` are unchanged by the fix. If either
   moves, the fix is wrong.
4. Pinned by a test with a Gift Aid donor asserting all three figures against the
   statutory definitions, not against the previous output.
5. **`/m` and native have no counterpart** (verified by grep, 2026-08-22) — but the
   `net_income` key is shared, so confirm no other surface reads it before changing
   what it means.

## Working notes
(append-only)

- 2026-08-22 cycle2-audit (build-lead): raised from `F-0020`, not fixed — scope
  discipline. The displayed chain **does** add up as printed for a Gift Aid donor, so
  W-0189's acceptance 1 is met either way; this is a naming defect, not an
  arithmetic one, and it deserves its own consumer sweep. See `F-0020` §6.
