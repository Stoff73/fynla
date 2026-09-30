# Partner pension top-up: affordability, weighed in household context

**Status:** draft for CSJ, 2026-09-30. todoCurrent item 1.
**Approved scope (CSJ 2026-09-30):** "affordability check always" on every partner pension top-up. On whose pension comes first: "a reasoned decision and needs to be taken in context, not just a once off rule… For pensions we need all the info."

## 1. What is wrong today

`NonEarnerSpousePensionStrategy` suggests a partner top-up on two paths, and neither asks whether the household can pay it.

| Path | Fires when | Suggests | File |
|---|---|---|---|
| Non-earner | household mode `single_earner_couple` | the non-earner maximum, £2,880 net (£3,600 gross), less what they already pay | `NonEarnerSpousePensionStrategy.php:61` |
| Modest earner | `dual_earner`, partner income under twice the Personal Allowance | up to the partner's whole relief limit (their earnings from work, or the basic amount), less what they already pay | `NonEarnerSpousePensionStrategy.php:121`, capacity at `:159` |

**Evidence:** Save Tax matrix S9 (`docs/testing/2026-09-29-savetax-scenario-matrix.md`). A partner earning £20,000 was told to pay £15,200 into a pension. At the same time the user had 40% relief headroom that the same money could have used (matrix E4).

**The user's own pension is already capped.** The pension allowance tile is limited to a year of surplus (CSJ 2026-09-25: "what use is knowing a limit you neither care about or can afford?"): 12 × `CompositePlanService::financials()['effective_surplus']`, or recorded cash for a declared non-earner (`TaxStrategyService.php:48-56`; CSJ 2026-09-29). The partner top-up has no such cap. Nor has the user's own "Pay £X more into your pension" card for someone who earns: only the non-earner card is capped (at recorded cash, `PensionTaxReliefStrategy.php:146`). Carry forward has a third rule of its own, half of liquid wealth (`PensionAACarryForwardStrategy.php:139`). Three affordability rules for one question is itself a Rule 20 problem; this spec moves the pension suggestions onto one.

## 2. The rules this rests on

- **Anyone may pay into a member's pension, and the member gets the relief.** Relievable contributions are those "by or on behalf of" the member (Finance Act 2004 s188(2), https://www.legislation.gov.uk/ukpga/2004/12/section/188). Gov.uk: "When someone else (for example your partner) pays into your pension, you automatically get tax relief at 20% if your pension provider claims it for you (relief at source)" (https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief).
- **The limit is the member's, not the payer's.** Relief is limited to 100% of the member's relevant UK earnings, or the basic amount if higher (FA 2004 s189-190). That is £3,600 gross, which gov.uk states as "£2,880 if you have no earnings". The figure comes from `pension.relevant_earnings_minimum` in tax config.
- **No relief after 75** (FA 2004 s188(3)(a); `pension.relief_max_age`).
- **The top-up counts as household tax saved** (CSJ 2026-09-25: "a spouse is a legal contract"). Married couples and civil partners only.

## 3. Design

### 3.1 The money a pension payment can come from

**One figure, the affordability calculator the tile already uses.** For this household it is:

- **the user's part:** 12 × `effective_surplus` (monthly net income less spending, committed contributions and goal commitments); or, for a declared non-earner, their recorded cash (`nonEarnerFundableGross`, the 29 September rule);
- **plus the partner's own part,** worked out the same way on the partner's own account, but only when that account is linked and shares its data (`HouseholdFinancialContext::partnerWithOwnRecords`). Otherwise the partner's money is not known, and is not counted.

### 3.2 Every partner top-up is capped by it

The top-up's net cost (what is actually paid) is capped at the money in 3.1 left over after the household's other pension suggestion (3.3). The gross and the relief follow from the capped net. If less than £100 net is left, there is no top-up card, as with the user's own pension items.

### 3.3 Two pension suggestions, one pot of money: weighed, not ranked by rule

When the user's own pension item and the partner top-up both fire, and the money in 3.1 cannot fund both in full:

- **Both are shown,** each sized to what the money can fund on its own.
- **They are labelled as alternatives for the same money,** through the existing mechanism (`StrategyPlanComposer`: "Alternative to … compare before doing both.").
- **The total counts the one that saves more tax;** the other reads "Not counted in your total."
- **Each card says why you might choose it,** in the household's own figures:
  - the user's card: the relief rate ("40% relief on your own contributions");
  - the partner's card: what it builds for them ("builds a pension in their name; in retirement they can use their own Personal Allowance and tax-free lump sum"). Only lines that rest on a cited rule.

When the money funds both, they are not alternatives, and both count.

This is the "in context" decision: the household's actual relief rates, limits and money decide it. No fixed order. The user chooses between them with the reasons in front of them.

### 3.4 When the information is missing, ask; don't guess

The partner top-up waits, as an unlock card, when any of these is unknown:

- **the household's spending:** the surplus is meaningless without it (`expenditure_composition.has_recorded_expenditure` from `DisposableIncomeAccessor`);
- **the partner's earnings from work,** on the modest-earner path. This is already asked separately (CSJ 2026-09-28);
- **what the partner already pays in** (`spouse_pension_input_annual`), when unanswered.

The partner's age is used when known. A missing age is only a risk near 75, and asking every household for it is out of proportion (open point 4).

### 3.5 Surfaces

This is one change in the strategy layer and the payload, so web `/tax-strategy`, /m Tax Strategy, the action cards and Fyn all get it (Rule 20; Rule 19). No front-end change: the cards already render `conflict_note` and `counted_in_total`.

## 4. Decisions for CSJ (with a recommendation)

1. **Cap the user's own pension item by the same money?** Today only the tile is capped; the plan's own "Pay £X more into your pension" card is not. It has to be, for 3.3 to work, and item 4 (40% relief below £100,000) needs it too. **Recommend yes.**
2. **Count the partner's own surplus when their account is linked and shares data?** **Recommend yes.** Without it, a working partner's own money is ignored.
3. **Wait for spending when none is recorded?** The alternative is the current behaviour: net income treated as all spare. **Recommend wait.**
4. **Partner's age unknown:** keep suggesting, as today, rather than asking. **Recommend keep.**

## 5. Tests and walk

- **Unit tests:**
  - the S9 household (£105k user, £20k partner, £40k savings, spending recorded) gets a partner top-up capped at the money left;
  - the two items are labelled as alternatives when the money is short, and not when it covers both;
  - a non-earner partner's £2,880 capped by a smaller surplus;
  - waits when spending is unrecorded;
  - a linked partner's own surplus counts.

  Every new test must fail without the change.
- **Walk on csjones, web 1440 + /m 390:** a couple where the money is short (both cards, one "Not counted in your total"), and a couple where it isn't. Then release and walk fynla.org.
