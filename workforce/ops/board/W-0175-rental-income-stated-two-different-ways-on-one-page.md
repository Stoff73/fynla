---
id: W-0175
title: Rental income is stated two different ways on the same page — the tax computation uses a figure net of some property costs, the allowance panel uses the gross, and the two totals differ by £1,920
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0017-cycle1-tax-income-and-allowances.md
owner: build-lead
status: done
severity: high
surfaces: [web]
created: 2026-08-21T23:45:00Z
claimed: 2026-08-22T19:50:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22T19:50:00Z
prior_art_found: ["app/Services/Property/PropertyService::calculateTaxPosition() — the existing per-property allowable-expense computation", "app/Services/UserProfile/UserProfileService::calculateAnnualRentalIncome() — aggregated it correctly", "app/Services/Tax/IncomeDefinitionsService::calculateRentalIncome() — re-derived gross with its own ownership arithmetic", "W-0173", "W-0174", "W-0140"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

> ## ⚠ CORRECTION — read before the sections below
>
> **Recorded 2026-08-22 by build-lead (`cycle1-tax`); confirmed by team-lead.**
>
> The "Actual" and "Acceptance" sections below contain a factual error that will send you
> after the wrong number if you act on them as written.
>
> **They say the £8,880 is "net of exactly two of the four cost fields" and therefore
> arbitrary. The count is right; the conclusion is wrong.**
>
> `PropertyService::calculateTaxPosition()` (`app/Services/Property/PropertyService.php:85-97`)
> deducts **eight** fields — gas, electricity, water, buildings insurance, contents
> insurance, service charge, ground rent, managing agent fee. That is the HMRC
> allowable-letting-expenses set. This persona populated four cost fields, and the split
> falls exactly on that line:
>
> | Populated | In the allowable list? |
> |---|---|
> | `monthly_building_insurance` £35 · `monthly_service_charge` £285 | **yes — deducted** |
> | `monthly_maintenance_reserve` £100 · `other_monthly_costs` £150 | **no — excluded, deliberately** |
>
> So "two of four" was an accurate observation of an eight-field list meeting a
> four-field fixture. It is not an arbitrary subset — it is the allowable/not-allowable
> boundary.
>
> **So £8,880 is a property-business profit and it is the defensible figure. The GROSS
> side is the one that was wrong**, not the net side.
>
> Consequently the fix is **not** the one Acceptance 1 proposes ("keep both and label
> them"). The two figures are collapsed to one — the profit — because property income
> enters total income as the profits of the property business (ITA 2007 s23 Step 1 over
> ITTOIA 2005 Part 3), which is what adjusted net income (s58) and threshold income
> (FA 2004 s228ZA) both build on. **Gross rent is not the correct base for the taper
> threshold**, which was the assumption this item and its dispatch were written on.
>
> The one genuine deductibility question this exposed — whether the maintenance reserve
> and "other" monthly costs belong in the allowable list — is **W-0178**, raised
> separately so it is decided rather than settled quietly inside a fix.
>
> Everything else in the item stands, including the £1,920 discrepancy and its cause.

## Intent

Found by: persona run `peak_earners`, local, journey re-walk, **both** persona accounts.

**Surface:** `/valuable-info?section=income`. The contradiction is between the top of the
page and the bottom of the same page, visible without scrolling away.

### Expected

One definition of "Rental Income" per page, or two clearly different labels. Sarah owns
50% of a City Centre Flat letting for £1,800/month, so her gross share is **£10,800/yr**.

### Actual

**Sarah's page, verbatim, top and bottom of one screen**
(`96-web-sarah-income-breakdown.png`):

```
Total Annual              £128,880
  Employment Income:      £120,000
  Rental Income:            £8,880      ← headline
  Total Annual Income:    £128,880
...
Your Income Definitions
  Total Income            £130,800
  Employment £120,000 · Rental £10,800   ← same page, £1,920 higher
  Adjusted Net Income     £130,800
  Threshold Income        £130,800
```

**The same page states her rental income as both £8,880 and £10,800, and her total as
both £128,880 and £130,800.**

**The £8,880 is net of two property costs, and only two.** Working it back:

| | Gross rent | Building insurance + service charge | Net | Owner's share |
|---|---|---|---|---|
| City Centre Flat | £21,600 | £35 + £285 = £320/mo = £3,840 | £17,760 | **£8,880** each at 50% |
| Manchester | £16,200 | £28 + £195 = £223/mo = £2,676 | £13,524 | **£5,410** at 40% |

Both match the screen exactly (David's page shows Flat £8,880 and Manchester £5,410,
total £14,290). **Maintenance reserve and other monthly costs are not deducted**, so it is
not a full net-of-expenses figure either — it is net of exactly two of the four cost
fields.

> **CORRECTED — see the block at the top of this file.** The deduction list is eight
> fields, the HMRC allowable-letting-expenses set. This persona populated four cost
> fields, two of which are on that list. £8,880 is a property-business profit, and the
> maintenance reserve and `other_monthly_costs` are excluded on purpose. Whether that
> exclusion is right is **W-0178**.

**The consequence is that the two halves of the page compute different things:**

- **The income tax computation uses the net figure.** David's "Taxable Income £147,690"
  is £145,000 + £14,290 − £11,600.
- **The allowance computation uses the gross figure.** Sarah's "Adjusted Net Income
  £130,800" and "Threshold Income £130,800" both use £10,800.

So a single page taxes one income and tests the allowances against another.

**And a third surface disagrees with both.** `/plans/estate` reads Sarah's gross income
as **£120,000** — no rental at all (**W-0173**). Three figures for one person's income:
£120,000, £128,880, £130,800.

### Impact

Adjusted net income and threshold income are the figures that decide the Personal
Allowance taper and the pension annual allowance taper. Computing them from a different
rental figure than the one being taxed means the two can disagree at a threshold — a user
sitting near £100,000 or £200,000 could be given an allowance the tax computation on the
same page contradicts.

For this household the outcome does not change (both are far above the £100,000 taper and
far below £200,000), so the harm today is that **the page visibly disagrees with itself**
and neither figure can be reconciled to the property records without knowing which two of
the four cost fields are being netted off. That is the same auditability problem as
**W-0171**, in the income module.

### Repro

1. `sarah.jones@example.com` → `/valuable-info?section=income`, wait ~14s.
2. Read the top card: "Rental Income: **£8,880**", "Total Annual Income: **£128,880**".
3. Scroll to "Your Income Definitions" on the same page: "Total Income **£130,800** —
   Employment £120,000 · Rental **£10,800**".
4. Compare with `/plans/estate` → Financial Overview → "Gross Income: **£120,000**".
5. `php artisan tinker` — one property row, `ownership_percentage 50.00`,
   `monthly_rental_income 1800.00`; costs `building_insurance 35`, `service_charge 285`,
   `maintenance 100`, `other 150`.

### Acceptance

1. ~~If a net-of-expenses figure is wanted for the tax computation, it is **labelled** as
   net and the gross is labelled as gross~~ — **SUPERSEDED, see the correction at the top.**
   There is **one** rental figure per page, from one source (Rule 20): the
   property-business profit. Two labelled figures was the wrong remedy, because the gross
   figure had no correct use on this page. The single figure is still labelled "Rental
   Profit" rather than "Rental Income", and the expenses deducted are named.
2. The deduction is complete and stated. ~~netting building insurance and service charge
   but not maintenance or other costs is not a definition anyone can reconcile~~ — the
   list is the eight HMRC allowable letting expenses and it IS reconcilable; the
   maintenance-reserve question is **W-0178**.
3. Adjusted net income, threshold income and the taxable income on the same page are
   computed from the same rental figure.
4. `/plans/estate` stops disagreeing with both (W-0173 — same fix family).
5. Verified in a browser on both persona accounts, and the composition shown so the user
   can trace it to their property records.

### Notes

- The **£5,410 Manchester figure is on David's page only**, correctly — Sarah is not an
  owner. The 40% share is applied correctly to whatever base is used, so the ownership
  arithmetic is sound in both cases; only the base differs.
- **Do not "fix" this by making everything gross without checking the tax path** — a
  net-of-allowable-expenses figure is the correct base for a rental tax computation, so
  the likely right answer is to keep both and label them, not to collapse them.
  > **Half right.** The warning against making everything gross was correct and is why
  > this was worth checking. The conclusion — keep both — was not: the gross figure had no
  > correct use on this page, so collapsing to the profit was the fix. See the correction
  > at the top.

## Working notes

**2026-08-22 — build-lead (`cycle1-tax`). Fixed. One correction to the diagnosis first.**

### The "two of four cost fields" is not arbitrary

`PropertyService::calculateTaxPosition()` (`app/Services/Property/PropertyService.php:85-97`)
deducts gas, electricity, water, buildings insurance, contents insurance, service charge,
ground rent and managing agent fee — **the HMRC allowable-letting-expenses set**, eight
fields, not two. The persona had populated only two of them, which is why it read as
"two of four".

So £8,880 is a **property-business profit** and it is the defensible figure. The
inconsistency ran the other way from how the item reads.

### Which figure is right for the taper — settled, not guessed

`IncomeDefinitionsService::calculateRentalIncome()` used **gross rent** for total income,
adjusted net income and threshold income, and re-implemented the ownership-share
arithmetic to get it. Under ITA 2007 s23 Step 1, property income enters total income as
the **profits of the property business** (ITTOIA 2005 Part 3); adjusted net income (s58)
and threshold income (FA 2004 s228ZA) both build on total income. The profit is therefore
the correct base on both halves of the page, and the gross figure was the wrong one.

This departs from the working assumption in the dispatch that gross is right for the
taper threshold; flagged to team-lead before building rather than after.

### One home

`PropertyService::annualRentalTaxPosition()` (`app/Services/Property/PropertyService.php:24-88`)
— the user's rental profit, Section 24 credit and per-property composition.
`UserProfileService::calculateAnnualRentalIncome()` (`:235-242`) and
`IncomeDefinitionsService::calculateRentalIncome()` (`app/Services/Tax/IncomeDefinitionsService.php:99-114`)
both compose from it. The duplicate gross implementation and its copy of the ownership
arithmetic are deleted; `IncomeDefinitionsService` now takes `PropertyService` in place of
`PropertyStore`.

### Measured

Sarah: total income £128,880, rental £8,880, adjusted net income £128,880, threshold
income £128,880 — headline and allowance panel now the same number, the £1,920 gap gone.
David: £159,289.60 / £14,289.60.

### Acceptance 1 and 2 — labelling

The component is **"Rental Profit"**, not "Rental Income", in both the tax card
(`app/Services/UKTaxCalculator.php:117-125`) and the definitions panel
(`resources/js/components/UserProfile/IncomeDefinitionsPanel.vue:146`), and the card's
note names the expenses deducted
(`resources/js/components/UserProfile/IncomeOccupation.vue:404-408`).

While relabelling: each income component now carries a stable `key`, and the client's
per-property drill-down matches on `component.key === 'rental'`
(`resources/js/components/UserProfile/TaxIncomeCard.vue:38`) rather than on the literal
string `'Rental Income'`. The label is no longer load-bearing.

### Tests

`tests/Unit/Services/Tax/RentalIncomeOneDefinitionTest.php` — 7 passing. A joint
buy-to-let with allowable expenses and a non-allowable maintenance reserve; every
consumer held to the same figure, and four cases that **move the property record** (rent,
service charge, maintenance reserve, ownership split) and require every consumer to move
together.

### Left open, deliberately

1. **Whether the maintenance reserve and "other" monthly costs should join the allowable
   list.** A genuine deductibility question — a *reserve* is not a paid expense and
   capital improvements are never allowable, so excluding them is defensible, but it is
   not a call to make unilaterally. Flagged to team-lead. The figure is now traceable
   either way.
2. **Acceptance 4 (`/plans/estate`) is W-0173 and is not touched here.** Its cause is
   adjacent and worth recording: `users.annual_rental_income` is written only by
   `updateIncomeOccupation`, so every surface reading that column directly —
   `PersonalAccountsService.php:68` and `:196`, `ResolvesIncome`,
   `CashFlowProjector.php:116` — can disagree with the computed figure. Sarah's column
   holds `0.00`; David's holds `17280.00`, the **gross** share. Routing those onto
   `annualRentalTaxPosition()` is the W-0173 fix and belongs with that item.

### Surfaces

Server-side in shared services. Two web components relabelled. `/m` shows
`income_occupation.total_annual_income` and inherits the corrected figure;
`ios-native/Fynla/` likewise. Neither renders the definitions panel or the per-property
drill-down.

Not done: browser verification, by instruction.

- 2026-08-31 build-lead: **CLOSED — verified against `dev`.** `PropertyService::annualRentalTaxPosition()`
  is the one home for the figure, cited as such at `SyncOwnerRentalIncome:19`,
  `PropertyController:491`, `PropertyService:38` and `Tax\IncomeDefinitionsService:201`. The two
  paths that each wrote their own version are gone, so the headline and the tax computation on
  `/valuable-info?section=income` read the same number.
