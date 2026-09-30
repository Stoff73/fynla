# Partner pension top-up: affordability, weighed in household context

**Status:** CSJ answered section 4 on 2026-09-30; one question open (4.5). todoCurrent item 1.
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
- **a partner with their own linked account funds their own pension, on their own account** (CSJ 2026-09-30: "the partner will get their own card in their own account"). Their plan already gives them their own "pay into your pension" card from their own earnings (`PensionTaxReliefStrategy`), and with 4.1 that card is capped by their own money. The user's plan does **not** also suggest a top-up to that partner's pension. Today it does (`NonEarnerSpousePensionStrategy` has no linked-account check), so a linked couple sees two cards for the same headroom.
- **so the partner top-up on the user's plan is only for a partner without their own shared account,** and it is paid from the user's money.

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

The partner top-up waits, as an unlock card asking for it (CSJ 2026-09-30: "We ask for expenditure"), when any of these is unknown:

- **the household's spending:** the surplus is meaningless without it (`expenditure_composition.has_recorded_expenditure` from `DisposableIncomeAccessor`);
- **the partner's earnings from work,** on the modest-earner path. This is already asked separately (CSJ 2026-09-28);
- **what the partner already pays in** (`spouse_pension_input_annual`), when unanswered.

The partner's age is used when known. A missing age is only a risk near 75, and asking every household for it is out of proportion (open point 4).

### 3.5 Surfaces

This is one change in the strategy layer and the payload, so web `/tax-strategy`, /m Tax Strategy, the action cards and Fyn all get it (Rule 20; Rule 19). No front-end change: the cards already render `conflict_note` and `counted_in_total`.

## 4. Decisions (CSJ answered 2026-09-30)

1. **Cap the user's own pension card by the same money:** "Agree".
2. **The partner's own money when linked:** "Agreed, but the partner will get their own card in their own account?" Yes: see 3.1. The linked partner's own card, on their own account, is sized by their own money, and the user's plan carries no second top-up for them.
3. **No spending recorded:** "We ask for expenditure". The top-up waits, and the card asks for spending.
4. **Partner's age unknown:** "agree". Keep suggesting.
5. **OPEN: the user's own card when no spending is recorded.** Save Tax setup asks only childcare and donations, never total spending (`CaptureForms.php:1292-1304`), so most Save Tax plans have no spending on record. Applying 1 and 3 to the user's own card would turn every such plan's headline pension card into "Enter your spending" until they give it. Options: (a) the card waits and asks for spending, like the partner top-up; (b) the card shows as today, uncapped, with a line saying it is not yet checked against your spending, plus the ask; (c) Save Tax setup asks for monthly spending. **Recommend (b) now, and (c) as a separate item:** the plan keeps its main card, says honestly what it has not checked, and asks.

## 5. Tests and walk

- **Unit tests:**
  - the S9 household (£105k user, £20k partner, £40k savings, spending recorded) gets a partner top-up capped at the money left;
  - the two items are labelled as alternatives when the money is short, and not when it covers both;
  - a non-earner partner's £2,880 capped by a smaller surplus;
  - waits when spending is unrecorded;
  - a linked partner's own surplus counts.

  Every new test must fail without the change.
- **Walk on csjones, web 1440 + /m 390:** a couple where the money is short (both cards, one "Not counted in your total"), and a couple where it isn't. Then release and walk fynla.org.
