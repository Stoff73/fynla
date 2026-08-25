# R-26 — Cycle 4, batch 7

**Agent:** `peak-earners-c4` (persona-tester) · **Persona:** `peak_earners`
**Surface:** web, local · **Account:** David (16)
**Batch closed:** 2026-08-22 ~19:20 · Continues R-19 … [R-25](R-25-cycle4-batch-6.md)

The **Letter to Loved Ones** — a whole persona section untested until now, and one my
instructions name explicitly ("letter to loved ones and key contacts").

---

## Done — and a lot of it is right

Read `/valuable-info?section=letter` end to end as David.

### GREEN — every contact and wish matches the persona exactly

| Persona | Letter |
|---|---|
| Executor: Sarah Jones & Barclays Wealth, Barclays 0800 779 9779 | ✓ verbatim |
| Solicitor: Henderson & Co, 01483 562 890 / estates@hendersonlaw.co.uk | ✓ verbatim |
| Financial Adviser: James Patterson – HL Wealth Advice, james.patterson@hl.co.uk | ✓ verbatim |
| Accountant: Graham & Associates, 01483 445 678 / david.graham@grahamassoc.co.uk | ✓ verbatim |
| Employer HR 020 7946 0123, death in service 4x salary | ✓ verbatim |
| Funeral: cremation, St Nicolas Church Guildford, reception at the rugby club, no flowers, donations to Cancer Research UK | ✓ verbatim |
| Joint account accessible immediately | ✓ "Nationwide – £4,500.00", correctly the whole balance |

### W-0022 is GREEN
That item was *"Letter tells the spouse 'No outstanding liabilities recorded' while a
£65,000 mortgage exists"*. The letter now carries a **Liabilities & Debts** section listing
all three mortgages by name with balances. The empty-state lie is gone.

### A genuinely good feature worth noting
**"Letter Consistency Checks"** flags what the letter does *not* say against what the data
knows: *"You have 2 vehicles recorded as chattels, but your letter does not mention vehicle
information"* and the same for valuables. That is the app checking its own document for
completeness, and it is right on both counts.

---

## D-24 (HIGH) — The Letter computes six household totals client-side at 100%, and it is a printable document

**A fifth mechanism for "what does this user own", and it survived the W-0238
consolidation.** Every other surface now applies the ownership share; this one does not.

| Section | Letter says | Every other surface | The difference |
|---|---|---|---|
| Bank Accounts & Savings | **£102,000** | £99,750 | the joint account at 100% |
| Investments | **£220,000** | £172,500 | the joint General Investment Account at 100% |
| Properties | **£1,570,000** | £755,500 | all three at full value |
| Liabilities & Debts | **£365,000** | £170,500 | all three mortgages at full balance |
| Life Insurance | £700,000 | £700,000 ✓ | — |

**I ruled out stale cache before raising this.** I cleared `savings_analysis_16`,
`investment_analysis_16`, `estate_analysis_16`, `protection_analysis_16`,
`retirement_analysis_16` and `mobile_dashboard_16`, reloaded, and the letter still showed
£102,000 / £220,000 / £1,570,000. It is a separate mechanism, not a stale copy of a fixed
one.

`resources/js/components/UserProfile/LetterToSpouse.vue:981-986` — six `reduce()` calls,
client-side, none of them share-aware:

```js
this.profileData.totalSavings       = this.profileData.savings.reduce((sum, a) => sum + (parseFloat(a.current_balance) || 0), 0);
this.profileData.totalInvestments   = this.profileData.investments.reduce((sum, a) => sum + (parseFloat(a.current_value) || 0), 0);
this.profileData.totalPropertyValue = this.profileData.properties.reduce((sum, p) => sum + (parseFloat(p.current_value) || 0), 0);
this.profileData.totalLiabilities   = this.profileData.liabilities.reduce((sum, l) => sum + (parseFloat(l.current_balance) || 0), 0);
```

Per-item values are un-shared too — `account.current_balance` at `:338`,
`liability.current_balance` at `:486`.

### Why this one is worse than the same arithmetic elsewhere

1. **It is addressed to the bereaved spouse**, under the heading *"Your current financial
   position"*, and the app describes it as *"crucial information for your spouse to manage
   financial affairs after your death"*.
2. **It hands a third party's money to the estate.** The Manchester property is tenants in
   common, David 40% / **Mike Barrett 60%**. The letter tells Sarah the estate includes a
   £295,000 Manchester property. £177,000 of it is not the family's. The same £72,000 of
   Mike Barrett's mortgage debt is charged to the household.
3. **It leaves the application.** There is a "Print / Save PDF" control, and
   `buildFinancialHtml()` (`:1503-1506`) renders these same totals into the printed
   document. Wrong figures in a PDF outlive any fix.

Screenshot: `145-web-david-letter-unshared-totals-102000-220000-1570000.png`

---

## D-25 (LOW) — `annual_allowance_used_gbp` holds a percentage

`app/Services/Stores/Recalc/PensionDerivedColumnCalculator.php:68-79` writes

```php
// Annual allowance used — % of AA consumed by this year's contribution.
$aaUsed = round($annualContribution / $aa * 100, 2);
```

into `dc_pensions.annual_allowance_used_gbp` (cast `decimal:2`, with a matching
`annual_allowance_used_gbp_calculated_at`). David's workplace pension holds **38.67**,
which is 23,200 ÷ 60,000 × 100 — a percentage in a column whose name promises pounds.

**The code is right and correctly commented; the column name is the lie.** Nothing in
`resources/` or `app/Http/` reads it today, so **this is latent, not user-visible** — a
future consumer would silently take percentages for pounds. Raising it low because that is
exactly the "Class B" shape the run-state document warns about: a value living somewhere
other than the thing you would check. Same family as W-0221 (write-only
`charitable_bequest`).

---

## Not done, and why

- **Persona's specific immediate-actions wording is not enterable.** The persona's checklist
  is bespoke prose ("Contact Sarah Jones immediately – she is executor and knows
  everything", "File life insurance claim with Vitality – policy VIT-LT-456789", "Register
  the death at Guildford Register Office", "Obtain at least 12 death certificates"). The app
  generates a templated equivalent (its version says "at least 10 copies") and offers custom
  boxes via Edit for additional topics. **Not raised** — the substance is present, the
  facts that matter are correct, and templating a checklist is a legitimate design choice
  rather than a defect against the persona.
- The persona's employer benefits detail — death in service **£580,000** and group life
  **£200,000** — appears only as "death in service benefit of 4x salary". Partial. Noted,
  not raised, for the same reason.
- Part 3 (password manager, estate documents location, vehicles, valuables, crypto,
  recurring bills) is entirely "Not specified" — correctly, since the persona supplies none
  of it, and the consistency checker already prompts for two of them.

## Assumptions

- I read the Letter's totals as claims about *this user's* position, because that is how the
  page labels them ("Your current financial position") and who it is addressed to. If the
  intended reading is "an inventory of everything you can see", the per-item full values are
  defensible — but the **totals** and the third-party property still are not.

## Needs

- Board IDs for **D-24** and **D-25**.
- **D-24 should join the W-0238 workstream**, not stand alone. That fix deleted
  `PortfolioAnalyzer::calculateTotalValue()` and routed its caller to
  `CrossModuleAssetAggregator` — the right move. This is the same job, one layer up, in a
  Vue component that never went through the backend at all. Whoever did W-0238 will
  recognise it in a minute; anyone else will rediscover the whole problem.

## Noticed

- The consistency checker is the most quietly valuable thing I have seen this run: it
  compares the document against the data and names what is missing. If anything, it deserves
  extending — it does not currently notice that the letter's own totals disagree with the
  net worth page.
