# R-18 — Persona estate ledger, complete household, contract-grade

**Agent:** `persona-passA3` · **Date:** 2026-08-21 · **Environment:** local
`localhost:8000` only.
**Subjects:** **David Jones `users.id 16`** and **Sarah Jones `users.id 17`** — premium,
married, reciprocal `spouse_id`, `SpousePermission` accepted both ways.
**Contract:** `tests/Persona/peak_earners.md`.
**Read-only.** No persona data was written during this pass; the household was entered
in R-17's second pass and has not moved since.

**This report supersedes R-17 §2.** R-17 remains the history — the one-third-entered
ledger, the entry itself, and the first verification. **Every figure here is
contract-grade**: the household is complete, so the expected values come from the
persona file rather than from "what happens to be in the database".

---

## 1. Expected values, hand-computed from `peak_earners.md`

Tax values read live from `TaxConfigService` on 2026-08-21: nil rate band £325,000,
residence nil rate band £175,000, taper threshold £2,000,000 at £1 per £2, standard rate
40%, charitable reduced rate 36%, charitable threshold 10% of baseline.

**One deviation from the persona file, and it is the file that is wrong:** NS&I Premium
Bonds cannot be held jointly. The application correctly removes the Ownership Type
control for that product, so the £50,000 is David's individual holding, not joint. Both
`peak_earners.md` and `PASS-PLAYBOOK.md` have been annotated. The household total is
unchanged; only the split moves.

**One deviation caused by a defect:** the Manchester mortgage saved as joint 50% instead
of tenants-in-common 40% (**W-0172**). Where that changes an expected value, both are
given — *persona-correct* and *as entered*.

### 1.1 Ownership shares, every record

| Record | Full | Type | **David** | **Sarah** | Third party |
|---|---|---|---|---|---|
| The Willows | 850,000 | joint 50% | 425,000 | 425,000 | — |
| City Centre Flat | 425,000 | joint 50% | 212,500 | 212,500 | — |
| Manchester | 295,000 | tenants in common 40% | **118,000** | **0** | **Mike Barrett 177,000 — must appear nowhere** |
| **Property** | | | **755,500** | **637,500** | |
| HSBC Current | 25,000 | individual D | 25,000 | 0 | |
| Barclays Current | 6,280 | individual S | 0 | 6,280 | |
| Nationwide Current | 4,500 | joint 50% | 2,250 | 2,250 | |
| Cash ISA (David) | 22,500 | individual | 22,500 | 0 | |
| Cash ISA (Sarah) | 22,500 | individual | 0 | 22,500 | |
| Premium Bonds | 50,000 | individual D | 50,000 | 0 | |
| **Cash** | | | **99,750** | **31,030** | household 130,780 |
| Hargreaves Lansdown ISA (David) | 95,000 | individual | 95,000 | 0 | |
| Hargreaves Lansdown ISA (Sarah) | 85,000 | individual | 0 | 85,000 | |
| AJ Bell General Investment Account | 95,000 | joint 50% | 47,500 | 47,500 | |
| Venture Capital Trust | 30,000 | individual D | 30,000 | 0 | |
| **Investments** | | | **172,500** | **132,500** | household 305,000 |
| **Chattels** (6 records) | 193,000 | mixed | **132,250** | **60,750** | |
| Defined Contribution pensions | 500,000 | individual D | 500,000 | 0 | outside the estate until 2027-04-06 |

| Mortgage | Full | Type | David persona-correct | David as entered | Sarah |
|---|---|---|---|---|---|
| HSBC — Willows | 65,000 | joint 50% | 32,500 | 32,500 | 32,500 |
| Barclays — Flat | 180,000 | joint 50% | 90,000 | 90,000 | 90,000 |
| NatWest — Manchester | 120,000 | tenants in common 40% | **48,000** | **60,000** | 0 |
| **Total** | | | **170,500** | **182,500** | **122,500** |

### 1.2 Estate and Inheritance Tax

| Line | Persona-correct | As entered |
|---|---|---|
| David's estate assets (ex-pensions) | 1,160,000 | 1,160,000 |
| Sarah's estate assets | 861,780 | 861,780 |
| **Gross estate** | **2,021,780** | **2,021,780** |
| Liabilities | 293,000 | 305,000 |
| **Net estate** | **1,728,780** | **1,716,780** |
| Nil rate band (650,000 − 150,000 chargeable lifetime transfer) | 500,000 | 500,000 |
| Residence nil rate band (175,000 × 2, no taper) | 350,000 | 350,000 |
| **Allowances** | **850,000** | **850,000** |
| Charitable, household (David 10,000 + Sarah 10,000) | 20,000 | 20,000 |
| Baseline / 10% threshold | 1,228,780 / 122,878 | 1,216,780 / 121,678 |
| Rate (20,000 « threshold) | **40%** | **40%** |
| **Taxable estate** | **858,780** | **846,780** |
| **Inheritance Tax** | **£343,512** | **£338,712** |

Net worth including pensions: David **£1,489,500**, Sarah **£739,280**, household
**£2,228,780** — matching `PASS-PLAYBOOK.md` §2.2 exactly.

---

## 2. The ledger

`I` = `/estate/inheritance-tax` · `P` = `/plans/estate` · `PR` = `/net-worth/property`
· `M` = `/m/app/estate`

### 2.1 Ownership shares — GREEN except one figure

**David, `PR`** (`92-web-david-property-three-properties-shares.png`):

| Card | Shown | Expected | |
|---|---|---|---|
| Willows — "Joint (50.00%) · Joint with Sarah Jones" | value £850,000 · share **£425,000** · mortgage £32,500 · equity £392,500 | same | **GREEN** |
| Flat — "Joint (50.00%) · Joint with David/Sarah" | value £425,000 · share **£212,500** · mortgage £90,000 · equity £122,500 | same | **GREEN** |
| Manchester — **"Tenants in Common (40.00%) · Tenants in common with Mike Barrett"** | value £295,000 · share **£118,000** | share 118,000 | **GREEN** |
| Manchester — mortgage / equity | mortgage **£60,000** · equity **£58,000** | **£48,000 · £70,000** | **RED — W-0172** |

**`£177,000` appears nowhere on either account.** Checked by regex across both property
pages and both `/m` estate screens: no "Manchester", "Victoria Mill", "295,000" or
"177,000" on Sarah's surfaces at all. **The run's single most important isolation
requirement passes.**

**Sarah, `PR`** (`93-web-sarah-property-two-properties-no-manchester.png`): two cards
only, Willows and Flat, each £425,000 / £212,500 shares with £32,500 / £90,000 mortgages
and correct equity, both captioned "Joint with David Jones". **GREEN.**

**W-0172 now has a second surface.** The wrong mortgage share propagates into the
**equity** figure on the property card — £58,000 where £70,000 is due — so the defect is
visible on the module page as well as in the estate table.

### 2.2 Estate composition — GREEN, both accounts

From `I`, expanded, both logins:

| Figure | Expected | David | Sarah | |
|---|---|---|---|---|
| David's assets | 1,160,000 | £1,160,000 | £1,160,000 | **GREEN** |
| Sarah's assets | 861,780 | £861,780 | £861,780 | **GREEN** |
| **Total gross** | **2,021,780** | **£2,021,780** | **£2,021,780** | **GREEN** |
| David's liabilities | 170,500 / *182,500 entered* | £182,500 | £182,500 | RED via W-0172 |
| Sarah's liabilities | 122,500 | £122,500 | £122,500 | **GREEN** |
| **Net estate** | 1,728,780 / *1,716,780 entered* | **£1,716,780** | **£1,716,780** | consistent; £12,000 low via W-0172 |

Defined Contribution pensions (£500,000) correctly outside the estate on both accounts.

### 2.3 Allowances — RED, unchanged, and the gap has grown

| | Shown, both accounts | Sums to |
|---|---|---|
| David's Tax-Free Allowance | −£325,000 | |
| Sarah's Tax-Free Allowance | −£325,000 | |
| David's Home Allowance | −£175,000 | |
| Sarah's Home Allowance | −£175,000 | **£1,000,000** |
| **Subtotal printed beneath them** | **−£850,000** | **£150,000 adrift** |

And the column still does not carry the charitable deduction:
£1,716,780 − £850,000 = £866,780 against **£846,780** shown — **now £20,000 out, not
£10,000**, because the deduction correctly doubled and the table still has no row for it.

**W-0134 unchanged.** The audit's explanation is the right one and should go in the fix:
the £175,000 nobody can trace is £325,000 of modelled second-death transfer netted
against the £150,000 gift — one of those rows is modelling a future event and does not
say so.

### 2.4 Charitable — calculation GREEN, presentation RED

| | Expected | Result |
|---|---|---|
| `charitable_deduction`, both accounts | 20,000 | **£20,000** — **GREEN, fixed** |
| `P` David — "Current Charitable Rate" | one household figure | **1%** |
| `P` Sarah — "Current Charitable Rate" | same figure | **4.2%** |
| `P` David — "Shortfall to Qualify" | one figure | £87,750 |
| `P` Sarah — "Shortfall to Qualify" | same figure | £13,928 |

**W-0139's calculation half is verified fixed** — the pooled estate now deducts the
household's £20,000, where it previously deducted £10,000 whichever spouse was logged in.
**Its presentation half is still red**: the same household, at the same instant, is
described as giving 1% or 4.2% depending on who is signed in, with thresholds £73,822
apart. Sarah is at least no longer told she gives 0%.

### 2.5 Liability — GREEN against what was entered

| | Expected (as entered) | David | Sarah | |
|---|---|---|---|---|
| Taxable estate | 846,780 | **£846,780** | **£846,780** | **GREEN** |
| **Inheritance Tax** | **£338,712** | **£338,712** | **£338,712** | **GREEN** |
| Rate | 40% | 40% | 40% | **GREEN** |

**W-0154 verified fixed on both surfaces.** One household, one answer, and it matches an
independent hand-computation to the pound. Against the *persona-correct* liabilities the
answer should be **£343,512**; the £4,800 shortfall is W-0172 and nothing else.

### 2.6 Projection to age 84 — RED, three ways

| Figure | David `I` | David `P` | Sarah `I` | Expected |
|---|---|---|---|---|
| Net estate | £4,368,401 | £4,368,401 | £4,471,607 | one household figure |
| Allowances | −£850,000 | −£850,000 | −£850,000 | **−£500,000** |
| Taxable estate | £3,467,510 | £3,493,680 | £3,569,514 | £3,848,401 |
| **Inheritance Tax** | **£1,387,004** | **£1,397,472** | **£1,427,806** | **£1,539,360** |

1. **W-0136 — the taper is never applied, and it is now the largest error on the page.**
   Excess over the £2,000,000 threshold is £2,368,401; the taper at £1 per £2 is
   **£1,184,200**, which exceeds the entire £350,000 residence nil rate band. **It should
   be extinguished.** The screen shows it in full, beneath the sentence *"Your combined
   estate is below the £2,000,000 taper threshold."* **Understated by £152,356.**

   **The taper logic exists and is correct** — `IHTCalculationService:1348-1368` has both
   a `tapered` and a fully-tapered-away branch with correct arithmetic. It is applied to
   the current estate and the projected column reuses the result. **This is a scoping
   fix, not new tax logic**, which should make it much smaller than it looks.
2. **W-0135 — two screens, two answers**, still: David's drill-down and plan differ, and
   the drill-down's figure reconciles to nothing on its own page.
3. **The two logins project different households** — £4,368,401 against £4,471,607,
   **£103,206 apart**, up from £88,257 when the household was one-third entered. **The
   discrepancy scales with the estate**, which makes it a proportional defect rather than
   a fixed offset — worth knowing before anyone hunts for a constant.

### 2.7 Income and expenditure — RED

| | Expected | David `P` | Sarah `P` | |
|---|---|---|---|---|
| Gross income | 162,280 / 130,800 | **£162,280** | **£120,000** | Sarah **RED — W-0173** |
| Annual expenditure | 29,400 / none recorded | **£52,394** | **£14,820** | **RED — W-0140** |

**David's gross income is exactly right** — £145,000 salary plus 50% of the Flat's
£21,600 and 40% of Manchester's £16,200 = £17,280. The ownership split is applied
correctly on his side. **Sarah's £10,800 share of a property she half-owns reaches
nobody** (W-0173) — the same failure as W-0172 in the income module.

**Sarah's "Annual Expenditure" is exactly her share of the property commitments**
(£1,235 × 12 = £14,820) and she has **no recorded expenditure at all**. David's £52,394
is his recorded £29,400 plus his £20,474 property share plus £2,520 unreconciled. One
label, two derivation rules, neither returning the recorded figure (W-0140).

**A contradiction inside the household:** the cost side treats Sarah as an owner and the
income side does not.

### 2.8 `/m` — RED, and now precisely quantified

| | David expected | `M` David | Sarah expected | `M` Sarah |
|---|---|---|---|---|
| Property | 755,500 | **£755,500** ✓ | 637,500 | **£637,500** ✓ |
| Investments | 172,500 | **£172,500** ✓ | 132,500 | **£132,500** ✓ |
| Cash & savings | 99,750 | **£99,750** ✓ | 31,030 | **£31,030** ✓ |
| **Chattels** | **132,250** | **absent** | **60,750** | **absent** |
| Liabilities | 182,500 entered | £182,500 ✓ | 122,500 | £122,500 ✓ |
| Net estate | **977,500** | **£845,250** | 739,280 | **£678,530** |
| Inheritance Tax | 338,712 | **not shown** | 338,712 | **not shown** |

> **Correction, cycle 2.** This row read **977,750** for David. It is **£977,500** —
> 755,500 property + 172,500 investments + 99,750 cash + 132,250 chattels = 1,160,000,
> less 182,500 of liabilities. A £250 arithmetic slip in my own expected value, caught by
> the coordinator. The understatement it measures is unchanged at exactly £132,250, since
> both sides of that subtraction move together. Sarah's £739,280 is correct as written.

**Every ownership split on `/m` is exactly right**, including the Manchester 40% inside
David's £755,500 and its complete absence from Sarah's. **The defect is precisely and
only the missing chattels class** — David understated by **£132,250**, Sarah by
**£60,750** — plus the individual-versus-household basis and the absent tax figure.
That is a much narrower W-0138 than the one-third household suggested, and worth saying
so: `/m`'s aggregation is sound; it is missing an asset class.

---

## 3. Verdict

| # | Check | Verdict |
|---|---|---|
| 1 | Estate composition, both accounts | **GREEN** |
| 2 | Ownership shares, every record, both sides | **GREEN** except the Manchester mortgage (W-0172) |
| 3 | £177,000 third-party share never appears | **GREEN** |
| 4 | Spouse isolation — Sarah sees no Manchester anything | **GREEN** |
| 5 | Allowances itemised, components reconcile to total | **RED — W-0134** |
| 6 | Charitable total, household | **GREEN (fixed)**; presentation **RED — W-0139** |
| 7 | Liability, both accounts agree | **GREEN — W-0154 verified fixed** |
| 8 | Residence nil rate band taper on the projection | **RED — W-0136, £152,356** |
| 9 | Projection consistent across screens and accounts | **RED — W-0135, £103,206 between logins** |
| 10 | Income splits by ownership on both sides | **RED — W-0173** |
| 11 | Expenditure is the recorded expenditure | **RED — W-0140** |
| 12 | `/m` parity | **RED — W-0138**, now narrowed to the chattels class |
| 13 | Is any of it explained | **RED — W-0171** |
| 14 | iOS | **I COULD NOT TEST THIS** — csjones database, local-only task |

**Defects raised by this run and still open:** W-0131, W-0132, W-0133, W-0134, W-0135,
W-0136, W-0137, W-0138, W-0139, W-0140, W-0171, W-0172, W-0173.
**Verified fixed by this run:** W-0154, W-0139 (calculation half), W-0052
(non-reproduction).

## 4. Not done

- **iOS.** Not attempted; the native app reads the csjones database.
- **W-0132 and W-0133 not re-verified** — no fix has landed, on the coordinator's
  instruction.
- **W-0137's negative projected cash** not re-examined line by line this pass. Its
  fingerprint — two logins projecting different household estates — is present and has
  grown, which is recorded in §2.6.
- **Holdings** (units, price, allocation, ongoing charges figure) were not entered. They
  do not affect any figure in this ledger; the account values do.
- **`/net-worth` is not a route** — it redirects to `/dashboard`. The per-module pages
  are the surfaces, and the property page carries the ownership ledger.

## 5. Noticed

- **The dashboard's pension-relief recommendation now reads £16,560**, where
  `PASS-PLAYBOOK.md` §2.6 hand-computes **£19,101** on a £36,800 contribution. That is
  **not a regression** — the playbook's figure assumed £145,000 of income, and David's
  gross is now £162,280 with rental included, so almost the whole contribution falls in
  the 45% band (£36,800 × 45% = £16,560). **Whether rental income should widen the
  pension-relief calculation at all is a question for `tax-compliance-reviewer`** —
  rental income is not relevant UK earnings for contribution purposes, even though it
  does affect the bands. Flagged, not adjudicated.
- **The Add Property wizard is 4 steps for a Buy-to-Let**, not 3 as `PASS-PLAYBOOK.md`
  §1.1 states: Basic Info → Mortgage → Costs → Buy-to-Let Details, with **Save Property**
  on step 4.
- **A "LEVEL UP" celebration modal** fires on the dashboard after bulk entry and must be
  dismissed before navigation, or route changes are swallowed.
