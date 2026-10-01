# Move savings to the partner who pays less tax

**Item:** `todoCurrent/TODO.md` item 4. APPROVED, CSJ 2026-09-30: "Yes build it" (matrix E2; E5 for retired couples).
**Date:** 2026-10-01

## 1. The law

| Rule | Source |
|---|---|
| Savings interest is taxed in the order Personal Allowance, then the starting rate for savings (0% on up to £5,000, reduced £1 for £1 by non-savings income above the allowance), then the Personal Savings Allowance, then the person's own bands | ITA 2007 s16, [s12](https://www.legislation.gov.uk/ukpga/2007/3/section/12) (`income_tax.starting_rate_for_savings.band`), [s35](https://www.legislation.gov.uk/ukpga/2007/3/section/35) |
| Personal Savings Allowance: £1,000 if none of the person's income is higher-rate income, £500 if some is, nil if any is additional-rate income | [ITA 2007 s12B](https://www.legislation.gov.uk/ukpga/2007/3/section/12B) (`TaxConfigService::getPersonalSavingsAllowance`) |
| Interest on savings one spouse or civil partner gives the other outright is the recipient's income for tax, not the giver's | [ITTOIA 2005 s626](https://www.legislation.gov.uk/ukpga/2005/5/section/626): the settlements rule in s624(1) does not apply to an outright gift that carries the right to all the income and is not wholly or substantially a right to income. A gift with conditions, or one where the money can come back to the giver, is not outright (s626(4)) |
| No Inheritance Tax on gifts between spouses or civil partners living in the UK | [IHTA 1984 s18](https://www.legislation.gov.uk/ukpga/1984/51/section/18); [gov.uk](https://www.gov.uk/inheritance-tax/gifts) (already cited in the `savings_to_spouse` how-to) |

## 2. What exists today

| Mechanism | When it fires | Problem |
|---|---|---|
| `AssetShiftingBundleStrategy` → `savings_to_spouse` (tax plan, priced) | `single_earner_couple` only, partner's savings recorded as £0 | No card for couples who both earn (E2). It gives the partner a full Personal Allowance, starting rate and £1,000 PSA whatever their income. A retired partner on £14,000 of pension has used the allowance and most of the starting rate (S8/E5), so the figure is overstated. |
| `JointSavingsStrategy` → `joint_savings_psa_split` (tax plan, priced) | The same gate | Same pricing assumption: the partner's side is "basic rate above a full PA + starting rate + PSA". |
| `SavingsActionDefinitionService::evaluateSpousePSAOptimisation` → `spouse_psa_shift` (savings module, no figure) | A linked partner who shares data, the user near or over their PSA, the partner under 50% of theirs | Says "consider holding savings in their name" with no amount or saving. It duplicates the tax plan card; this is item 21 on the list. |

## 3. The design

### 3.1 One pricing for "move interest from you to your partner"

- **The saving from moving £X of interest** is the fall in the user's tax less the rise in the partner's tax. Both sides are priced by the one tax engine (`TaxStrategyMath::incomeTaxOn`, `UKTaxCalculator`), on each person's full income: pay, pension, dividends, their own interest, net-pay contributions, and Gift Aid / relief at source.
- **The engine applies s12, s12B and s35 on both sides,** so a partner's own pension income using up their allowance, or their own interest using their PSA, is priced as HMRC would.
- **The amount moved is the one that saves the most.** Moving more than that only shifts interest that was already tax-free for the user, or that the partner pays more on. Worked example from the list, through the engine:
  - The user earns £60,000 and has £40,000 at 4.5% (£1,800 of interest).
  - The partner earns £20,000.
  - Moving £1,300 of interest, about £28,900 of savings, saves **£460**.
  - Moving all £1,800 saves £360: the user's own £500 PSA was covering the rest.
- **The search** steps through the interest the user could move, in £10 steps, and keeps the best saving. The tax is piecewise linear, so the best is at a band edge and £10 steps find it to within £10 of interest.
- **Which savings it uses:** sole-name, non-ISA savings only. A joint account is already split 50/50 for tax.
- **Rounding:** the amount is shown as savings, taken from the highest-rate accounts first, rounded down to £100 and then re-priced at that amount, so the card's two figures agree.
  - Sizing at the average rate would be wrong: a 0% account and a 4.7% account averaged gave £3,400, and moving that from the 0% account saves nothing.
  - The how-to's first step says "from the savings that pay you the most interest".
- **It runs in the composed plan's order,** after the pension items: `pensionPaidElsewhere` and `interestSheltered` are used as `savings_to_spouse` uses them today.
- **The same pricing replaces the hand-built partner capacity** in `savings_to_spouse` (every couple) and in `joint_savings_psa_split` (the 50/50 alternative). That fixes the retired-partner overstatement in both (Rule 20: one rule).

### 3.2 When the partner's position is known

Any guess about the partner's income or savings would be a figure without a source (Rule 23; CSJ 2026-09-30, "we need all the info").

| Partner | Income from | Their own savings interest from |
|---|---|---|
| Linked and sharing data (`financiallySharedSpouse`) | Their own records (`incomePartsFor`) | Their own savings records |
| Not linked, does not work (`single_earner_couple`) | `spouse_annual_income` and `spouse_annual_dividends` (required since 2026-09-28) | `spouse_existing_savings_balance` = £0, or the new interest answer (D2) |
| Not linked, works (`dual_earner`) | `spouse_annual_income` and `spouse_annual_dividends` | **Not asked today** (D1) |

When anything is missing, the card waits and asks, as Marriage Allowance and the partner pension top-up do.
- **The data key** is `spouse_savings` (`HouseholdFinancialContext`): "Unlock spouse's savings". The prompt "Update my spouse's details" opens the partner form, which now has Savings.
- **Nobody is asked** when the user pays no tax on the interest they could move (the key is `null`), because no answer could change the plan.
- **"Savings" left unticked** while other options are answered records the partner's savings as £0, as saving with nothing chosen already does: they saw it listed.

### 3.3 Linked partners

The card lives on the account of the partner who holds the savings. It names the other partner as the one who receives them. The other partner's account does not show a matching card: they would be receiving money, not acting.

## 4. Decisions (answered by CSJ, 2026-10-01: the recommended option on all three)

- **D1. Ask a working partner about their savings.** Add a "Savings" option to the working-partner form ("Do they have any of the following?"), with "Savings balance" (required) and "Interest they receive each year" (optional, "Leave blank if you don't know").
  - Without it, a couple who both work and are not linked can never get the card.
  - *Recommendation: yes.*
- **D2. The same interest question for a non-working partner who has savings.** Today, a non-working partner with any savings blocks the card for good, because a balance without a rate gives no interest.
  - *Recommendation: yes,* the same optional field under "Savings balance".
  - If the interest is left blank for a non-zero balance, the card waits for it.
- **D3. The card's wording.** Today: "Gift £X of savings to your spouse for up to £Z of interest tax-free every year". That is true only when the partner pays no tax on it.
  - *Proposed, for every couple:* "Gift £X of savings to your spouse and save £Y in tax a year".
  - The how-to adds a line saying the interest is then taxed on them, at their rates, and gives the figures.

## 5. Not in this item

- `spouse_psa_shift` (the savings module's unpriced card) duplicating this one: item 21.
- Moving dividend-paying investments (`gia_to_spouse`) is unchanged.
- Scottish rates: parked (CSJ).

## 6. Tests (named files only)

- **Worked example:** £60,000 user with £40,000 at 4.5%, £20,000 partner. Saving £460, amount about £28,800 (the interest rounded down, then the savings rounded down to £100).
- **Retired couple (S8):** the user draws £25,000 with £60,000 at 4.5%, and the partner has £14,000 of pension income. Priced by the engine, not by a full allowance stack. The matrix's "about £340" is checked against the engine and corrected if the engine says otherwise.
- **Single-earner partner with no income and no savings:** today's figure is unchanged, a regression guard.
- **Retired partner in single-earner mode:** the figure now nets the partner's pension against their allowance. It fails before the change.
- **Partner savings unknown:** the card is locked, asking for the partner's savings.
- **A user with only PSA-covered interest:** no card, because nothing moved saves tax.
