# What's new in Fynla — 1 October 2026

Your pension suggestions now start from what you can actually afford. A suggestion to pay into your own pension, or to top up your partner's, is sized to the money left after your spending and goals, and when there is not enough for both, the two are shown as alternatives rather than added together. The Save Tax setup asks for your monthly spending again so this works from the start, and a pension allowance you cannot afford to use no longer says "Fully used".

**This is live on fynla.org** through two releases on the evening of 30 September:

- **About 20:27:** release #1028 (#1026 and #1027: pension suggestions sized to what you can afford; Save Tax asks your spending).
- **About 20:37:** release #1030 (#1029: the pension allowance tile).

Both were walked on the test site first, on the desktop web app (full-size window) and the mobile web app, and then checked on fynla.org (see "What we checked").

## Marriage Allowance follows the law (1 October, about 07:57, release #1032)

- **More couples qualify.** The partner giving Marriage Allowance used to need income below the Personal Allowance, which is GOV.UK's summary. The law asks something different: once their Personal Allowance is reduced by the £1,260 they give, none of their income is taxed above the basic rate, with dividends counted in full ([Income Tax Act 2007 s55C(1)(c), (ca)](https://www.legislation.gov.uk/ukpga/2007/3/section/55C)). In the law, the "income below the Personal Allowance" test applies only to people who are not UK resident (s55C(2), through s55C(1)(d)).
- **What it changes,** for a partner on a £35,000 salary receiving it:
  - A partner giving it with £14,000 of savings interest now saves the household £252 a year. The income their smaller allowance no longer covers stays inside the 0% starting rate for savings ([s12](https://www.legislation.gov.uk/ukpga/2007/3/section/12)).
  - A partner giving it with £14,000 of dividends saves the household about £116 a year: £252 less the £135 of dividend tax they then pay.
  - For a partner whose income is wages, nothing changes. Above the Personal Allowance, the extra tax they pay equals what the other partner saves.
- **The saving takes off any tax the giver then pays.** The steps now say so: "With a smaller allowance, Sam pays about £138 more Income Tax a year. The saving below already takes that off." A giver earning £12,000 was never told this before. Where it costs them nothing, the steps say that instead.
- **Gift Aid and pension payments that extend your basic-rate band are counted** when the giver's extra tax is worked out ([ITA 2007 s414](https://www.legislation.gov.uk/ukpga/2007/3/section/414); [Finance Act 2004 s192(4)](https://www.legislation.gov.uk/ukpga/2004/12/section/192)).
- **One rule everywhere.** Before, four places decided Marriage Allowance, and three of them were wrong:
  - **The older tax-optimisation figures Fyn reads** always claimed the full £252, and they added an invented "£200" estimate for couples in different tax bands. Both are gone.
  - **The household optimisations endpoint** returned an error for every couple who qualified.
  - **A copy in the investment engine** was never shown, and has been removed.
- **Fyn no longer treats a working partner as not eligible.** A partner earning £8,000 qualifies.
- **Wording (approved by CSJ, 1 October).** The card no longer says "unused" Personal Allowance. The steps explain how a partner whose income uses all of their allowance can still give part of it. Fyn's background knowledge on Marriage Allowance says the same.

**What we checked.**
- **On the test site, desktop (full-size window) and mobile web apps:** a couple where Alex earns £35,000 and Sam has £14,000 of savings interest. Both apps showed "Claim Marriage Allowance, £252/yr" and these steps:
  - "Sam's income of £14,000 uses all of their £12,570 Personal Allowance. They can still give part of it…"
  - "Sam pays no more tax…"

  The older tax-optimisation figures and the household endpoint gave the same £252.
- **Live on fynla.org, desktop (full-size window) and mobile web apps:** a walk couple made for the check (Alex on £35,000, Sam with £14,000 of savings interest) saw "Claim Marriage Allowance, £252/yr" and the same steps as on the test site. The walk accounts were deleted afterwards.

## Pension suggestions sized to what you can afford

- **One figure decides what a pension payment can be:** the money you have left in a year after your spending, the payments you already make and your goals. Someone with no income at all is sized to their savings instead. A retiree drawing a pension has income, so they are sized to what is left of it like anyone else.
- **Your own "Pay £X more into your pension" card is never more than that.** It is sized to what your money buys once the pension provider adds basic-rate relief at source (Finance Act 2004 s192; [gov.uk: pension tax relief](https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief)). The Pension Annual Allowance tile shows the same figure.
- **A top-up to your partner's pension is never more than that either.** On the test site, a partner earning £20,000 had been told to pay £15,200 into a pension. Anyone, you included, can pay into your partner's pension and they still get the tax relief, up to their own limit ([Finance Act 2004 s188(2)](https://www.legislation.gov.uk/ukpga/2004/12/section/188); gov.uk: "When someone else (for example your partner) pays into your pension, you automatically get tax relief at 20%"). Figures are in whole pounds, rounded down.
- **When there is not enough for both, they are alternatives.** Your own card and your partner's top-up are both shown. Only the larger saving counts in your total, and the other reads "Alternative to … compare before doing both. Not counted in your total." No fixed order decides it: your own figures do.
- **If your partner has their own linked account and shares their data, their pension suggestion is on their account,** sized to their own money. Your plan no longer shows a second one for the same pension.
- **If your spending is not recorded, the partner top-up waits and asks for it:** "Unlock spending info". "Add it now" opens Fyn, who asks for the figure and records it; the top-up then appears.

## Save Tax asks your monthly spending again

- **The spending step asks "What your household spends each month"**, with childcare, charitable donations and Gift Aid under it as "Of that, …". From 22 September it had asked only those three on the desktop and mobile web apps; the iPhone app always asked the total.
- **Fyn repeats what you gave:** "Thanks — I've noted monthly spending of £3,500, childcare of £200 a month and charitable donations of £50 a month under Gift Aid."

## The pension allowance tile

- **An allowance you cannot afford to use says £0, not "Fully used".** On fynla.org the Carter demo household had £7,500 paid in of £60,000 and nothing left to afford, and the tile read "Fully used". It now reads "£0 of headroom" on the desktop and "£0 available" on mobile, with "Limited to what you can afford this year" beneath. An allowance that really is used still says "Fully used".

## Decisions taken

- **Affordability is checked on every partner pension top-up** (CSJ, 30 September: "affordability check always").
- **Whose pension comes first is decided in context, not by a fixed rule** (CSJ: "a reasoned decision and needs to be taken in context… For pensions we need all the info").
- **Your own pension card is capped by the same money** (CSJ: "Agree").
- **A linked partner gets their own card on their own account** (CSJ).
- **With no spending recorded, we ask for it** (CSJ: "We ask for expenditure"; "lets ask for spending").
- **A partner's unknown age does not stop the suggestion** (relief stops at 75; CSJ: "agree").

## What we checked

- **On the test site, desktop (full-size window) and mobile web apps:**
  - A household with about £2,500 a year spare: "Pay £3,100 more into your pension and save £1,240 in tax", and "Top up your spouse's pension by £2,499 — instant £624" marked as the alternative, not counted. The tile read "£3,125 available — Limited to what you can afford this year".
  - The same household after lowering its spending on the Expenditure page: both at full size (£9,700 saving £3,880; £2,880 with £720 added), no alternative.
  - Households with no spending recorded: "Unlock spending info", then "Add it now", Fyn recorded the spending, and the top-up appeared.
  - The Save Tax spending step asked the monthly total on both apps, and the plan that followed used it.
  - A household with nothing spare: "£0 of headroom" (desktop) and "£0 available" (mobile).
- **Live on fynla.org, as the Carter demo household:** the pension tile reads "£0 of headroom, £7,500 used — Limited to what you can afford this year" on the desktop and "£0 available" on mobile. The production figures behind it: net income £55,557 a year, spending £52,386, goals £1,050 a month, so nothing is spare. Their earlier "Pay £20,900 more into your pension and save £8,360" card no longer shows, because they could not afford it; their plan is now £75 a year.
- **Not walked on fynla.org:** the Save Tax spending step itself and the partner top-up cards (walked on the test site only).

## Still to do

- The public Save Tax estimate still promises a partner top-up without checking affordability.
- A pension for a child (£2,880 a year) is not yet sized to what you can afford.
- The pension tile label is worked out separately on the desktop and mobile; it should come from the server, once.

## Behind the scenes

- **`PensionAffordability`** is the one rule for the money a pension payment can come from, used by every pension card and the tile. Before, there were three different rules.
- **At release:** app code and one seeder (the partner top-up now needs spending recorded), then both web bundles. No database changes.

## Not tested: the iPhone app

These are server changes, so the iPhone app gets the same figures, and its typed setup question already asked for spending. Its own tile wording was not changed or tested.
