# R-17 — Persona estate figures, ledger, both accounts, web and `/m`

**Agent:** `persona-passA3` · **Date:** 2026-08-21 · **Environment:** local
`localhost:8000` only. csjones and production untouched. **Read-only — no persona data
was created, modified or deleted.**
**Subjects:** the persona household — **David Jones `users.id 16`** and **Sarah Jones
`users.id 17`**, both premium, married, reciprocal `spouse_id`, `SpousePermission`
accepted in both directions.
**Contract:** `tests/Persona/peak_earners.md`.

> **Numbering.** The coordinator asked for `R-16` and screenshots from 44. Both were
> already taken: `R-16-batch-b-regression.md` was filed and indexed before the re-scope
> arrived, and screenshots 44–69 belong to it. Renumbering would break links in a filed
> report, so this is **R-17** and its screenshots run **70–77**. Flagged rather than
> silently resolved.

---

## 0. Read this before the ledger — the household is only partly entered

Pass A entry was halted, so **the persona file's household is not what is in the
database.** Every "expected" figure below is therefore given twice:

- **Contract** — what `peak_earners.md` specifies for the complete household.
- **As entered** — hand-computed from the rows that actually exist, applying the
  persona's own ownership rules.

**Defect calls are made against "as entered".** The app cannot display a property
nobody typed in. The gap is a coverage note for the coordinator, not a defect.

| Record | Contract | In the database |
|---|---|---|
| Properties | 3 (Willows, City Centre Flat, Manchester) | **1** — Willows only, joint 50%, £850,000 |
| Mortgages | 3 (£65,000 / £180,000 / £120,000) | **1** — HSBC £65,000, joint 50% |
| Savings | 6 accounts, £130,780 | **4** — £76,280. Missing both joint accounts (Nationwide £4,500, NS&I Premium Bonds £50,000) |
| Investments | 4 accounts, £305,000 | **2** — £180,000. Missing David's Stocks & Shares ISA £95,000 and the Venture Capital Trust £30,000 |
| Chattels | 6, £193,000 | **6, £193,000** — complete |
| Defined Contribution pensions | £500,000 | £500,000 — present, correctly outside the estate |
| Defined Benefit pension | Sarah, NHS | present, £0 capital — correct |
| Trust | £185,000 | present |
| Lifetime gift | not stated in the persona file | **`gifts.id 10`** — £150,000 chargeable lifetime transfer, 2020-09-01, note says "Auto-recorded", derived from the trust settlement |

**The £150,000 gift is the single most consequential figure in this report and it is
not in the persona file.** It is a faithful derivation — the persona settles
£150,000 into the Jones Children's Education Trust on 2020-09-01, which is **5 years
11 months** before the run date, so it is a chargeable lifetime transfer within seven
years and it does reduce the nil rate band. **`PASS-PLAYBOOK.md` §2.5 is wrong to omit
it** and I have corrected the playbook accordingly.

---

## 1. The hand-computed household answer

Applying the persona's ownership rules to the rows that exist, on the household
second-death basis the application itself uses (`is_married: true`,
`data_sharing_enabled: true`):

| Line | £ | Working |
|---|---|---|
| David's estate assets | 652,250 | 425,000 property + 47,500 joint General Investment Account + 47,500 cash + 132,250 chattels |
| Sarah's estate assets | 647,030 | 425,000 property + 132,500 investments + 28,780 cash + 60,750 chattels |
| **Total gross** | **1,299,280** | |
| Liabilities | 65,000 | £65,000 mortgage, £32,500 each |
| **Net estate** | **1,234,280** | |
| Nil rate band | 500,000 | (325,000 − 150,000 chargeable lifetime transfer) + 325,000 |
| Residence nil rate band | 350,000 | 175,000 × 2, no taper — 1,234,280 < 2,000,000 |
| **Total allowances** | **850,000** | |
| Charitable legacies | 20,000 | David £10,000 Cancer Research UK **+ Sarah £10,000 British Heart Foundation** |
| Baseline / 10% threshold | 734,280 / 73,428 | net estate − nil rate band |
| Rate | **40%** | £20,000 < £73,428 |
| **Taxable estate** | **364,280** | 1,234,280 − 850,000 − 20,000 |
| **Inheritance Tax** | **£145,712** | 364,280 × 0.40 |

**There is one household and therefore one right answer: £145,712.** The application
shows three different numbers and none of them is it.

Defined Contribution pensions (£500,000) are correctly excluded — they enter the estate
from 2027-04-06. That exclusion is right and is the one large adjustment the app gets
right silently.

---

## 2. The ledger — every figure, expected against actual

`I` = `/estate/inheritance-tax`, `P` = `/plans/estate`, `S` = `/estate` summary card.

### 2.1 Composition — GREEN throughout, both accounts

| Figure | Expected | David (I) | Sarah (I) | |
|---|---|---|---|---|
| David's assets | 652,250 | £652,250 | £652,250 | **GREEN** |
| — Willows, joint 50% | 425,000 | £425,000 | £425,000 | **GREEN** |
| — AJ Bell General Investment Account, joint 50% | 47,500 | £47,500 | £47,500 | **GREEN** |
| — cash (2) | 47,500 | £47,500 | — | **GREEN** |
| — chattels (5) | 132,250 | £132,250 | — | **GREEN** |
| Sarah's assets | 647,030 | £647,030 | £647,030 | **GREEN** |
| — investments (2) | 132,500 | £132,500 | £132,500 | **GREEN** |
| — cash (2) | 28,780 | £28,780 | £28,780 | **GREEN** |
| — chattels (4) | 60,750 | £60,750 | £60,750 | **GREEN** |
| Total gross assets | 1,299,280 | £1,299,280 | £1,299,280 | **GREEN** |
| Liabilities | 65,000 | −£65,000 | −£65,000 | **GREEN** |
| **Net estate** | **1,234,280** | **£1,234,280** | **£1,234,280** | **GREEN** |

Every joint record is split 50/50, counted once on each side, and the two subtotals
add to the household total. **Both logins agree on the estate. This half of the module
is right, and it is worth saying so precisely** — Rule 6 ownership is being honoured
end to end, including the chattels totals (£132,250 / £60,750) that the playbook
independently verified on `/m` in an earlier pass.

### 2.2 Allowances — RED on both accounts, differently

| Figure | Expected | David | Sarah | |
|---|---|---|---|---|
| Nil rate band available | 500,000 | **£500,000** (implied) | **£650,000** (implied) | Sarah **RED** |
| Residence nil rate band | 350,000 | £350,000 | £350,000 | GREEN |
| **Stated total allowances** | **850,000** | **−£850,000** | **−£1,000,000** | Sarah **RED** |
| Row: David's Tax-Free Allowance | — | −£325,000 | −£325,000 | |
| Row: Sarah's Tax-Free Allowance | — | −£325,000 | −£325,000 | |
| Row: David's Home Allowance | — | −£175,000 | −£175,000 | |
| Row: Sarah's Home Allowance | — | −£175,000 | −£175,000 | |
| **Rows add to** | | **£1,000,000** | **£1,000,000** | |
| **Total shown beneath them** | | **£850,000** | £1,000,000 | David **RED** |

**On David's screen the four allowance rows sum to £1,000,000 and the subtotal
immediately beneath them says £850,000.** The £150,000 chargeable-transfer reduction is
applied to the total and appears in no line. → **W-0134**.

The footnote makes it worse rather than better: *"Combined Nil Rate Band of £650,000
available (£325,000 each) … Reduced by £150,000 due to gifts made within the last 7
years."* It states £650,000 as the available figure while the table applies £500,000, so
the page offers the user three different nil-rate-band numbers — £325,000 per row,
£650,000 in the prose, £500,000 in the arithmetic.

**Sarah's nil rate band ignores the chargeable transfer entirely.** Same household, same
combined estate, and her allowances are £150,000 larger. → cross-references **W-0154**.

### 2.3 Charitable position — RED, stated four different ways

| Where | Shown | Expected |
|---|---|---|
| Server `charitable_deduction`, both accounts | £10,000 | **£20,000** (household) |
| `S` David — "Leave **£73,428**+ to charity…" | 73,428 | 73,428 ✓ threshold right, but asked as if nothing were given |
| `S` Sarah — "Leave **£58,428**+ to charity…" | 58,428 | 73,428 — computed off her un-reduced nil rate band |
| `P` David — "Current Charitable Rate **1.6%**" | 1.6% | 1.62% of *his own* net estate; the server says 0.81% of the household's |
| `P` Sarah — "Current Charitable Rate **0%**" | **0%** | She has a live £10,000 British Heart Foundation bequest, and the same page deducts £10,000 from her taxable estate |
| `P` David — "Shortfall to Qualify £51,975 · Potential Saving £24,790" | | 10% and 4% of **David's individual** net estate, not the statutory baseline |

**Sarah is told she gives nothing to charity on a page that simultaneously deducts her
£10,000 legacy.** And the household's £20,000 is never recognised anywhere, so the 10%
test runs against half the true charitable total on both accounts. → **W-0139**.

No line labelled "charitable" appears in the estate table on either account — the
£10,000 is deducted invisibly, which is why the visible column does not subtract to its
own next row even on Sarah's screen (1,234,280 − 1,000,000 = 1,234,280 − 1,000,000 =
**234,280**, against **£224,280** shown). Folded into **W-0134**.

### 2.4 Liability — RED, three answers for one household

| | Expected (household) | David | Sarah |
|---|---|---|---|
| Taxable estate | **364,280** | £374,280 | £224,280 |
| **Inheritance Tax** | **£145,712** | **£149,712** | **£89,712** |
| Rate label | 40% | 40% ✓ | 40% ✓ |
| Life cover "Cover Needed" | one figure | £149,712 | £89,712 |

**£60,000 apart, on the same estate, depending on which spouse logs in.** Each figure is
internally consistent with its own screen's arithmetic (374,280 × 0.40 = 149,712;
224,280 × 0.40 = 89,712) — the inputs differ, not the multiplication. The rate label
happens to be correct on both accounts here because the standard rate genuinely applies;
that is luck, not correctness (see W-0132 from R-16, where the same label mechanism was
wrong).

### 2.5 Projection to age 84 — RED, and partly nonsense

| Figure | David `I` | David `P` | Sarah `I` | Sarah `P` |
|---|---|---|---|---|
| Net estate | £2,343,680 | £2,343,680 | £2,431,937 | £2,431,937 |
| Allowances | −£850,000 | −£850,000 | −£1,000,000 | −£1,000,000 |
| **Taxable estate** | **£1,474,691** | **£1,493,680** | **£1,412,234** | **£1,431,937** |
| **Inheritance Tax** | **£589,877** | **£597,472** | **£564,893** | **£572,775** |

**Two Fynla screens, same user, same instant, £7,595 apart for David and £7,882 apart
for Sarah.** `/plans/estate` reconciles to what it shows (2,343,680 − 850,000 =
1,493,680, × 0.40 = 597,472). `/estate/inheritance-tax` does not: its £1,474,691 cannot
be derived from any combination of the figures on its own page. → **W-0135**.

**The residence nil rate band taper is never applied.** Projected estates of £2,343,680
and £2,431,937 both exceed the £2,000,000 threshold; the expected tapered residence nil
rate band for David is £350,000 − (2,343,680 − 2,000,000)/2 = **£178,160**, giving
allowances of £678,160. The screen shows a flat **−£850,000 / −£1,000,000** in the age
84 column, and the footnote beneath asserts *"Your combined estate is below the
£2,000,000 taper threshold"* directly under a column showing £2.34m. → **W-0136**.

**Projected cash is deeply negative and the projection is not credible.** From David's
expanded table:

```
Cash/Savings (2)              £47,500      -£1,803,267
  HSBC - Current_account      £25,000        -£949,088
  Nationwide - Cash_isa       £22,500        -£854,179
David's Assets              £652,250         £242,377
```

A Cash ISA cannot hold minus £854,179, and David's total assets are projected to **fall
from £652,250 to £242,377** while his property alone triples to £1,231,768. Sarah's cash
projects to −£1,092,590 on the same basis. → **W-0137**.

### 2.6 `/m` — RED, a fourth answer

| | Web (household) | `/m` David | `/m` Sarah |
|---|---|---|---|
| "Estimated estate value" | £1,234,280 | **£487,500** | **£553,780** |
| Property | | £425,000 | £425,000 |
| Investments | | £47,500 | £132,500 |
| Cash & savings | | £47,500 | £28,780 |
| **Chattels** | £132,250 / £60,750 | **absent** | **absent** |
| Liabilities | | £32,500 | £32,500 |
| Inheritance Tax liability | £149,712 / £89,712 | **not shown at all** | **not shown at all** |

`/m`'s own arithmetic is internally right (520,000 − 32,500 = 487,500), but it omits
chattels entirely — £132,250 of David's assets and £60,750 of Sarah's, every one of
which the web table itemises — and presents an individual estate where web presents a
household one, under a heading that reads "Inheritance tax exposure and planning" while
showing no tax figure. → **W-0138**.

---

## 3. Is any of it explained? — Largely no, and this is a finding

CSJ's second point, answered plainly from what is on screen.

**What the estate drill-down explains:** two sentences. *"Combined Nil Rate Band of
£650,000 available (£325,000 each). Transfers between spouses are exempt from IHT on
first death. Reduced by £150,000 due to gifts made within the last 7 years."* and
*"Full Residence Nil Rate Band of £350,000 available (£175,000 each). Your combined
estate is below the £2,000,000 taper threshold."*

**What it does not explain, on a page of otherwise bare numbers:**

- **The £500,000 of Defined Contribution pensions that are silently outside the estate.**
  Searched the rendered page: the word **"pension" does not appear once**, and neither
  does **"2027"**. The single largest adjustment to this household's estate — and the one
  about to reverse on 2027-04-06 — is invisible and unmentioned.
- **The £10,000 charitable deduction.** The word **"charitable" does not appear** on the
  drill-down at all, yet £10,000 is deducted from the taxable estate.
- **The £150,000 gift as a line.** It is named in a footnote and appears in no row, so
  the arithmetic the user can see does not work.
- **What any allowance actually is.** `/plans/estate` offers *"Nil Rate Band: Individual
  Nil Rate Band. On second death, up to double may be available."* — a definition that
  restates its own name, gives no figure, and does not mention that £150,000 of it has
  been consumed.

A person planning their estate is shown eleven pounds-and-pence figures and told the
provenance of none of the three adjustments that move them most. **I am recording this
as a product finding for `product-lead` rather than a board defect**, because the fix is
an editorial and design decision, not a bug — but it is not a small one, and it is the
reason the arithmetic errors above went unnoticed: nothing on the page invites the user
to check.

The one figure that *is* fabricated rather than merely unexplained is raised:
`/plans/estate` states **"Annual Expenditure: £39,420"** for David whose recorded
`annual_expenditure` is **£29,400**, and **"£7,500"** for Sarah whose expenditure
columns are **NULL**. Both feed the "Disposable Income" the plan's advice is built on.
→ **W-0140**.

---

## 4. Defects raised

| Item | Sev | Summary |
|---|---|---|
| **W-0134** | high | The estate column does not add up: four allowance rows sum to £1,000,000 beneath a £850,000 total, and the charitable deduction has no row at all |
| **W-0135** | high | `/estate/inheritance-tax` and `/plans/estate` give different projected tax for the same user at the same moment; the drill-down's figure reconciles to nothing on its page |
| **W-0136** | high | The residence nil rate band taper is never applied to the projected estate, and the footnote asserts the estate is below the threshold beneath a column showing £2.34m |
| **W-0137** | high | Projected cash goes to minus £1.8m; a Cash ISA projects to −£854,179 and total assets fall below today's |
| **W-0138** | high | `/m` omits chattels from the estate entirely, shows an individual estate against web's household one, and shows no Inheritance Tax figure under an Inheritance Tax heading |
| **W-0139** | high | The charitable position is stated four different ways; Sarah is told she gives 0% on a page that deducts her £10,000; the household's £20,000 is never recognised |
| **W-0140** | medium | `/plans/estate` states an Annual Expenditure neither user entered, and it drives Disposable Income |

**Cross-referenced, not re-raised:** **W-0154** (the service computing two different
liabilities for one household) — §2.2 and §2.4 are its surface half. **W-0132** (the
rate label reading a user toggle) — the same mechanism hides the charitable row here.

**Superseded from R-16:** nothing. W-0131 (the dead Inheritance Tax cache) stands
unchanged and is confirmed again here — `iht_calculations` is still empty after a full
two-account browser sweep. W-0132 and W-0133 stand; they were found on throwaway
accounts but neither depends on the account, and W-0133 is reconfirmed on persona data
(`will_documents.5` and `.6` are both `complete`, so neither David nor Sarah can
re-finalise either).

---

## 5. Not done, and why

- **iOS — I COULD NOT TEST THIS.** The native app reads the csjones database; this task
  is local-only.
- **No writes.** Read-only throughout, as instructed: nothing on users 16 or 17 was
  created, edited or deleted, and `fix-batch-G` can carry on. Every figure above was
  read, never set.
- **The missing two-thirds of the household was not entered.** Entering it would have
  changed the very reference data `fix-batch-G` is working against. Coordinator's call,
  and it is the single thing that would most improve this ledger: with three properties
  and the full £305,000 of investments, the household estate reaches ~£1.73m and the
  residence nil rate band taper, the buy-to-let treatment and the third-party
  tenants-in-common share would all come into range.
- **`/m` beyond `/estate`.** Only the estate screen was checked on each account.
- **The £8,989 and £9,703 residuals** in the drill-down's projected taxable estates are
  recorded as unreconcilable rather than diagnosed — locating them is service work and
  belongs with W-0154's audit.

## 6. Assumptions

- The household second-death basis is the intended model; every expected figure uses it.
- A chargeable lifetime transfer within seven years reduces the transferor's nil rate
  band, so on a combined household basis £500,000 (David's view) is right and £650,000
  (Sarah's view) is wrong — not the other way round.
- `gifts.id 10` is a legitimate derivation of the persona's trust settlement, not stray
  data. Its note says "Auto-recorded", its date and value match the persona's trust
  exactly, and `fix-batch-B`'s document does not claim to have created it.

## 7. Noticed — routed, not fixed

- **Sarah is her own executor.** Both `/estate` and `wills.id 12` show *"Executor: Sarah
  Jones, Barclays Wealth"* on Sarah's own will — live W-0024 residue on persona data.
  Expected under the open gate that existing documents are not rewritten (W-0019
  acceptance 6, still CSJ's), but it is visible to the user today.
- **A stray draft will exists on David.** `will_documents.id 7`, `status draft`,
  `mirror_document_id` NULL, alongside his complete `.5`. `F-0003` §7 records deleting a
  stray id 7; it is present. Since `getWillBuilderDraft()` orders by `updated_at`, a
  newer draft can shadow the complete document on the will surface.
- **A life event's narrative contradicts its own sign.** "Kitchen & Extension · likely ·
  1 Apr 2027 · *May increase the value of your property within the estate* · **−£85,000**
  · Inheritance Tax **−£34,000**." The prose says increase, the figures say decrease.
- **"Cover in Trust £500,000 · Total Policies 0"** on Sarah's `/plans/estate` — half a
  million pounds of cover from zero policies.
- **Liquidity is computed on a different asset basis than the estate.** David's
  Asset Breakdown sums to £1,152,250 (pensions included); his estate is £652,250
  (pensions excluded). Sarah's sums to £647,030 (she has none). So the two accounts'
  liquidity analyses are not comparable, and the advice built on them
  ("David's liquid assets of £47,500 may not cover the Inheritance Tax liability of
  £149,712") uses a household liability against an individual's cash.
- **All nine life events belong to David; Sarah has none**, so the "Life Events Impact
  on Estate" panel is absent from her account entirely. Their impacts are all valued at
  a flat 40%.

## 8. Evidence

Screenshots 70–77 in `tests/Persona/20-08-2026_run/pass-a-web/`:

| | |
|---|---|
| 70 | David `/estate/inheritance-tax`, fully expanded — the allowance rows against the £850,000 total |
| 71 | David `/estate` summary |
| 72 | David `/plans/estate` |
| 73 | Sarah `/estate/inheritance-tax`, fully expanded |
| 74 | Sarah `/estate` summary |
| 75 | Sarah `/plans/estate` |
| 76 | `/m` David estate — chattels absent |
| 77 | `/m` Sarah estate — chattels absent |

Server figures for both accounts were read through the same path the controller uses
(`IHTCalculationService::calculate($user, $spouse, true)`) and are quoted in §1 and in
each board item.

---

# SECOND PASS — the full household, entered and re-verified

**Added 2026-08-21 ~21:30, on the coordinator's instruction to enter the missing
two-thirds of the household.** Everything above this line describes the one-third-entered
database and stands as written; this section supersedes its figures.

## What was entered, and how

Through the module user interface as **David Jones**, real pointer clicks, every write
confirmed against the MySQL row:

| Record | Result |
|---|---|
| **City Centre Flat** — Buy-to-Let, joint 50%, £425,000, Barclays interest-only £180,000 @ 5.19% tracker, rent £1,800 | `properties.19` + `mortgages.15` — **correct** |
| **Manchester** — Buy-to-Let, tenants in common **40%**, co-owner **Mike Barrett**, £295,000, NatWest repayment £120,000 @ 5.49% fixed, rent £1,350 | `properties.20` **correct**; `mortgages.16` **WRONG** → **W-0172** |
| **Nationwide joint current £4,500** | `savings_accounts.53`, joint 50%, Sarah — correct |
| **NS&I Premium Bonds £50,000** | `savings_accounts.54`, **individual** — see note below |
| **Hargreaves Lansdown Stocks & Shares ISA £95,000** (David) | `investment_accounts.26` — correct |
| **Venture Capital Trust £30,000** | `investment_accounts.27` — correct |

The wizard is **4 steps for a Buy-to-Let**, not 3 as `PASS-PLAYBOOK.md` §1.1 states:
Basic Info → Mortgage → Costs → Buy-to-Let Details, with **Save Property** on step 4.

**Premium Bonds cannot be recorded as jointly held.** Selecting "Premium Bonds" collapses
the form to Institution / Product Type / Current Balance — **the Ownership Type control
disappears entirely**, with no explanation (`85-web-david-premium-bonds-no-ownership-control.png`).
That is almost certainly **correct**: NS&I Premium Bonds cannot be held jointly, exactly
like the joint-ISA case the run has already cleared. So the persona file's "Joint Owner →
Sarah" on Premium Bonds is a persona-file error of the same class, **not an application
defect**, and it is entered as David's individual holding. It does not change the
household total, only the split (David +£25,000, Sarah −£25,000 against the playbook).
**Recorded for `product-lead`; the silent disappearance of the control is a small
usability point, not raised.**

**W-0052 does not reproduce.** `POST /api/investment/accounts → 201` on both new accounts;
the `advisor_fee_percent` 500 is gone.

## The household now, hand-computed

| Line | £ | Working |
|---|---|---|
| David's estate assets | 1,160,000 | 425,000 + 212,500 + 118,000 property · 25,000 + 22,500 + 2,250 + 50,000 cash · 95,000 + 47,500 + 30,000 investments · 132,250 chattels |
| Sarah's estate assets | 861,780 | 425,000 + 212,500 property · 6,280 + 22,500 + 2,250 cash · 85,000 + 47,500 investments · 60,750 chattels |
| **Total gross** | **2,021,780** | |
| Liabilities **as entered** | 305,000 | 65,000 + 180,000 + **60,000** (should be 48,000 — W-0172) |
| **Net estate as entered** | **1,716,780** | |
| *Net estate, persona-correct* | *1,728,780* | *matches `PASS-PLAYBOOK.md` §2.5 exactly* |
| Nil rate band | 500,000 | 650,000 − 150,000 chargeable lifetime transfer |
| Residence nil rate band | 350,000 | 175,000 × 2, no taper (1,716,780 < 2,000,000) |
| **Total allowances** | **850,000** | |
| Charitable, household | 20,000 | David £10,000 + Sarah £10,000 |
| Baseline / threshold | 1,216,780 / 121,678 | rate stays **40%** |
| **Taxable estate** | **846,780** | |
| **Inheritance Tax** | **£338,712** | |

**Manchester's third-party share is honoured on the value side.** David £118,000, Sarah
£0, and **£177,000 appears nowhere** — the requirement the persona exists to test. The
debt side is the failure (W-0172).

## Re-verified — what changed under me

**A fix landed in `IHTCalculationService` at 20:14 while I was entering data.** The
service now agrees with my hand-computed answer to the pound, on both accounts.

| Item | Verdict now |
|---|---|
| **W-0154** — two tax bills for one household | **VERIFIED FIXED, both surfaces.** David and Sarah both show taxable **£846,780** and Inheritance Tax **£338,712**. Allowances £850,000 on both. Screenshots `88`, `89` |
| **W-0139** — household charitable total | **VERIFIED FIXED at the service.** `charitable_deduction` is now **£20,000**, the household figure, on both accounts. The per-screen wording defects in §2.3 have not been re-checked |
| **W-0134** — components don't sum | **NOT FIXED.** Rows still 325,000 + 325,000 + 175,000 + 175,000 = **£1,000,000** beneath a **−£850,000** subtotal. Still no charitable row, so £1,716,780 − £850,000 = £866,780 against **£846,780** shown — now a £20,000 gap rather than £10,000 |
| **W-0136** — taper never applied | **NOT FIXED, and now severe.** See below |
| **W-0135** — surfaces disagree on the projection | **NOT FIXED.** David projects £4,368,401 / tax £1,387,004; Sarah projects £4,471,607 / tax £1,427,806 — **£40,802 apart** for one household |
| **W-0137** — negative projected cash | Not re-checked in detail; the projection asymmetry above is its fingerprint |

### W-0136 is now the largest single error on the page

With the full household entered, the projected estate clears the taper threshold by more
than double:

```
Net Estate                       £1,716,780      £4,368,401
Less: Tax-Free Allowances         -£850,000       -£850,000      ← unchanged
Taxable Estate                     £846,780      £3,467,510
Inheritance Tax Liability (40%)    £338,712      £1,387,004
```

Beneath it, unchanged: *"Full Residence Nil Rate Band of £350,000 available (£175,000
each). **Your combined estate is below the £2,000,000 taper threshold.**"*

Hand-computed: excess £2,368,401, taper at £1 per £2 = **£1,184,200**, which exceeds the
whole £350,000 residence nil rate band. **The correct projected allowance is £500,000 —
the residence nil rate band is extinguished entirely**, giving a taxable estate of
£3,848,401 and tax of **£1,539,360**.

**The projected Inheritance Tax is understated by £152,356**, and the sentence directly
beneath the £4,368,401 tells the user the threshold does not reach them.

## Environment note — recorded, deliberately NOT raised

At **20:14:31** a tinker run against `IHTCalculationService` emitted
`Undefined variable $isMarried` three times (lines 1285, 1308, 1330) and returned a
halved residence nil rate band (£175,000, `rnrb_status: "full"`). Re-reading the file
minutes later showed those lines already reading `$poolsSpouse`. **This was a
half-applied edit caught mid-write, not a defect** — the `tests/CLAUDE.md` "contention
between parallel batches — discard, do not diagnose" pattern. Recorded with its
timestamp so nobody re-diagnoses it, and so the coordinator knows the exact minute the
W-0154 fix landed.

## Defects added by this pass

| Item | Sev | Summary |
|---|---|---|
| **W-0172** | high | A tenants-in-common property saves its mortgage as joint 50% — £60,000 charged to David where £48,000 is due, and the other £60,000 attributed to nobody |
| **W-0171** | high | The estate calculation cannot be audited by the person whose money it is (promoted from §3 on the coordinator's direction) |

**ID block note:** W-0131–W-0140 is exhausted. **W-0171–W-0175 claimed by
`persona-passA3`** and announced to the coordinator; highest allocated elsewhere at the
time was W-0161.
