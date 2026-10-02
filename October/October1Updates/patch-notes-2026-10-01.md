# What's new in Fynla — 1 and 2 October 2026

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

## The "You're ahead of" band is hidden (1 October, about 08:30, release #1034)

- **The pink "LEVEL UP / You're ahead of X% of people" band below the level card no longer shows.** This applies to the dashboard on the desktop web app, the web app at phone width and the mobile web app. The band is commented out in the iPhone app's code too, so it will go from the next iPhone build.
- **Everything else on the dashboard stays:** the level card, and the focus areas, recommendations and actions below it.
- **It can come back.** The band is commented out, not deleted, and the percentage is still worked out on the server (CSJ, 1 October: "comment out, so do not remove in case we need to reverse this").

**What we checked.**
- **On the test site:**
  - Mobile web app: the level card now sits directly above "Top actions", and the carousel still moves between focus areas.
  - Desktop web app: the level header shows only the level and its progress bar, and the focus tabs still switch.
- **Live on fynla.org, as the Carter demo household:** the same on the desktop web app, the web app at phone width and the mobile web app.
- **Not tested: the iPhone app.** Its change ships with the next build.

## Moving savings to the partner who pays less tax (1 October, about 09:59 and 10:11, releases #1037 and #1039)

- **Every married couple and civil partnership can now be shown "Gift £X of savings to your spouse and save £Y in tax a year".** Before, only couples where one partner had no earnings saw it.
- **The law behind it:** interest on savings you give your spouse outright is theirs for tax ([ITTOIA 2005 s626](https://www.legislation.gov.uk/ukpga/2005/5/section/626)). Each of you has your own Personal Allowance, starting rate for savings ([ITA 2007 s12](https://www.legislation.gov.uk/ukpga/2007/3/section/12)) and Personal Savings Allowance ([s12B](https://www.legislation.gov.uk/ukpga/2007/3/section/12B): £1,000 at the basic rate, £500 at the higher rate).
- **How the saving is worked out:**
  - The saving is what you stop paying, less what your partner starts paying, worked out on each of your whole incomes.
  - It moves only the amount that saves the most. That amount comes from your highest-interest savings first and is rounded down to £100.
  - Example: you earn £60,000 with £40,000 at 4.5%, and your partner earns £20,000. Moving £28,800 saves about £459 a year. Moving all £40,000 would save less, because your own £500 allowance already covers some of the interest.
- **A retired partner's pension now counts.** Before, a partner was treated as having no income at all. A retired couple (you draw £25,000, your partner has a £14,000 pension, £60,000 at 4.5%) was told to move all £60,000. Now it is £37,700, saving about £339.
- **We now ask about your partner's savings** (CSJ, 1 October):
  - "Savings" is an option on the form for a partner who works.
  - Both partner forms ask for "Interest they receive each year".
  - Until we know their savings, the card waits with "Unlock spouse's savings info", and "Add it now" opens the form through Fyn.
  - We don't ask when moving your interest could not save you anything.
- **The steps say what your partner then pays,** for example "they pay about £105 a year on it, and you pay about £210 less". Release #1039, 12 minutes after #1037, made those figures add up to the saving shown (it had read £106 against a £105 saving).
- **The 50/50 joint-account alternative is now worked out the same way.**

**What we checked.**
- **On the test site, desktop (full-size window) and mobile web apps,** starting from a partner whose savings we didn't know:
  - "Unlock spouse's savings info", then "Add it now", then Fyn's partner form with the new Savings option.
  - After saving, the card: "Gift £17,700 of savings to your spouse and save £365 in tax a year", and its steps.
  - On the mobile web app, savings given without the interest: the card keeps waiting, and the form reopens with the savings already filled in.
- **Live on fynla.org, desktop and mobile web apps,** with walk accounts deleted afterwards:
  - The same journey, giving the same £17,700 / £365 card.
  - A partner who already earns £1,000 of interest: "Gift £11,700 … and save £105", with "they pay about £105 a year on it, and you pay about £210 less".
- **A tax-compliance review** confirmed the law, the working and the example figures. Its wording and citation findings were fixed before release.

## Pension relief at 40% below £100,000 (1 October, about 11:44, release #1041)

- **The "Reclaim your Personal Allowance" card no longer stops at £100,000.** Once a pension payment has brought your income back to £100,000, every further £1,000 you pay in still saves £400, until your income reaches £50,270, where the higher rate starts ([ITA 2007 s35](https://www.legislation.gov.uk/ukpga/2007/3/section/35), [s58](https://www.legislation.gov.uk/ukpga/2007/3/section/58); [Finance Act 2004 s192(4)](https://www.legislation.gov.uk/ukpga/2004/12/section/192); [GOV.UK, pension tax relief](https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief)). The card now goes all the way down, within the Annual Allowance, your earnings and what you can afford.
- **Only when we know what you can afford.** It carries on below £100,000 only once your spending is recorded (CSJ: "We ask for expenditure"). Without recorded spending, the card stays as it was. When money is short, it goes to the 60% slice first.
- **Example:** you earn £110,000, pay 5% into a workplace pension, have £10,000 in savings and spend £3,000 a month. Before, the card said "a £4,900 pension contribution … £2,940 back". Now it says "a £35,800 pension contribution … Together that's £15,310 back this year. Income between £100,000 and £125,140 is taxed at 60%. Below £100,000, each £1,000 you pay in still saves £400, down to £50,270." Your Income Tax falls from £30,222 to £14,912.
- **How to pay it in now uses your real figures** (CSJ: salary sacrifice where offered, then a personal payment for the rest, never either/or). The steps put through payroll what your pay can carry after your spending, for the months left in the tax year. The rest goes into a personal pension as a one-off, with its own figures. For the example: "£4,172 a month is as much as your pay can carry after your spending: £25,032 by 5 April 2027. Open a personal pension … and pay the other £10,768 into it as a one-off: you pay £8,615 and the provider adds £2,153 of basic-rate relief", then "Claim the other £2,155 … through your Self Assessment tax return". The parts add up to the £15,310. This applies to all three pension cards.
- **The public Save Tax estimate is unchanged** (CSJ): it cannot know what someone can afford.
- **Also fixed, from the tax review:**
  - The card never claims back more Personal Allowance than was lost, for a Gift Aid donor above £125,140 ([s35(2)](https://www.legislation.gov.uk/ukpga/2007/3/section/35)).
  - The Blind Person's Allowance is now counted in every tax-plan figure and in where each rate starts ([ITA 2007 s38](https://www.legislation.gov.uk/ukpga/2007/3/section/38)). The tax calculator already counted it; the plan did not.
  - "Each £1,000 still saves £400" appears only where it is true. Taxed savings interest at the top of your income can put part of the payment at another rate.
  - The taper ratio is read from the tax settings, not typed into the code.
- **Wording approved by CSJ** (1 October): the new card sentence, the new step "Below £100,000, each pound you pay in still gets relief at 40%, down to £50,270, where the higher rate starts", and the payroll and one-off steps.

**What we checked.**
- **On the test site, desktop (full-size window) and mobile web apps:** the £110,000 household above. Both apps showed the £35,800 / £15,310 card, the new step, and the payroll and one-off steps with the figures above. A copy of the household with no spending recorded still showed £4,900 / £2,940.
- **Live on fynla.org, desktop (full-size window) and mobile web apps:** a walk account made for the check showed the same card and steps. It was deleted afterwards.
- **A tax-compliance review** confirmed the law, the sizing and the example figures. Its findings were fixed before release, apart from two noted under "Still to do".

## The Retirement page for someone drawing their pension (1 October, about 14:20, release #1043)

- **Someone retired, or drawing from a pension, now sees their retirement as it is,** not a saver's page. Before, a retiree of 68 drawing £30,000 a year from £200,000 was shown "Retirement Age 67, Years to Go 1", a retirement target, "Am I saving enough?" with a required capital of £478,723, and a pot projection that ignored the £30,000 drawn. Now, on the desktop and mobile web apps (CSJ: "Income + how long it lasts", for "anyone drawing from a pension", even while still working):
  - **"Retired since January 2020, at 61"**, from the retirement date.
  - **Your income this year:** each pension being drawn, the State Pension once it is being paid, any final salary pension in payment, earnings, and Income Tax. National Insurance shows on earnings only, and none past State Pension age ([Social Security Contributions and Benefits Act 1992 s6(3)](https://www.legislation.gov.uk/ukpga/1992/4/section/6)). Take-home is shown last. Pension income is taxable ([GOV.UK, tax on pension income](https://www.gov.uk/tax-on-pension)).
  - **How long your pension lasts:** for the example, the middle outcome "runs out by about age 76" and the lower outcome (4 in 5 do better) by about 75. Life expectancy is "84 on average (Office for National Statistics)", and the income that would last to it in 4 out of 5 outcomes is about £14,300 a year. The card says: "These are projections, not guarantees. They assume the same £30,000 each year at your Lower-Medium risk level's returns, with no charges or inflation. Many people live longer than the average." (CSJ approved this wording, for Consumer Duty, [FCA PRIN 2A.5](https://www.handbook.fca.org.uk/handbook/PRIN/2A/5.html).)
  - **The pot chart is drawn down from today.**
- **Whether the State Pension is being paid is now asked.** Before, nothing recorded it, so a State Pension never counted as income anywhere in the app. It can be put off ([GOV.UK, deferring your State Pension](https://www.gov.uk/deferring-state-pension)), so it is asked, never assumed:
  - "I am already being paid my State Pension" on the web form;
  - the same question when Fyn records it;
  - a new State Pension form that a Fyn edit opens on.

  Past State Pension age, the income card says "State Pension: add it", "not recorded as being paid" or "amount not recorded" until it is known.
- **The full new State Pension rate is right everywhere.** It was typed in as £221.20 a week (£11,502) under a 2026/27 label on both pension forms, the life-stage panel and the glossary, and as £11,973 on the pensions campaign page. 2026/27 is £241.30 a week, £12,547.60 a year ([GOV.UK](https://www.gov.uk/new-state-pension/what-youll-get)). All of them now read the tax settings.
- **The pension forms ask "Taxable income drawn each year"** (CSJ). It is taxed in full, so any tax-free part, such as the tax-free quarter of a lump sum, is left out.
- **Fyn's edit forms take pence.** A prefilled amount such as £11,502.40 could not be saved before, because the box took whole pounds only.
- **Also from the tax review:**
  - Gift Aid and relief-at-source pension payments extend the tax bands in the Income Tax shown ([ITA 2007 s414](https://www.legislation.gov.uk/ukpga/2007/3/section/414)).
  - The income lines add up to the income that is taxed.

**What we checked.**
- **On the test site, desktop (full-size window) and mobile web apps,** as a retiree born in 1958, retired in 2020, with £200,000 drawing £30,000:
  - the new view, at £26,514 take-home;
  - the State Pension through the web form's new box, and on mobile through Update and Fyn's State Pension form: £11,502 counted, £5,786 Income Tax, £35,716 take-home;
  - the "Taxable income drawn each year" label.
- **Live on fynla.org, desktop and mobile web apps,** with a walk account deleted afterwards:
  - the same view, and "not recorded as being paid" with Update;
  - the form showing "£241.30/week (£12,548/year)";
  - after ticking "being paid": State Pension £12,548, Income Tax £5,996, take-home £36,552 on both apps.
- **A tax-compliance review** confirmed the law and figures. Its findings were fixed before release, apart from three listed under "Still to do".

## Retirement suggestions, and the same figure on every screen (2 October, about 15:55, release #1047)

- **Retirement suggestions come from their own rules,** as savings and protection already did, so each one has its own steps.
  - **One card for where your retirement income stands.** It replaces three cards that each offered a different fix: pay in more, retire later, or start paying in. The amount it suggests closes the gap the Retirement page shows, limited to what you can afford and to the tax relief limit ([Finance Act 2004 s189-190](https://www.legislation.gov.uk/ukpga/2004/12/section/190)). Before, one household was offered £4,375 a month, the whole remaining allowance.
  - **No saver cards for someone retired and drawing a pension.** Someone still working while drawing sees the Money Purchase Annual Allowance limit instead ([s227ZA](https://www.legislation.gov.uk/ukpga/2004/12/section/227ZA)).
  - **No duplicates of the Tax plan.** Pension tax relief and salary sacrifice are suggested once, by the Tax plan, with its affordability check. The warning that the Annual Allowance is already exceeded stays on Retirement, because no Tax plan card gives it.
  - **One charges card per pension.**
  - **Card text without unsourced figures or provider names.** The auto-enrolment minimum now comes from the tax settings.
- **Steps for the retirement cards** (approved by CSJ, 1 and 2 October), each with its sources, including "Plan how you will take your pension". That card covers the tax-free part, flexi-access drawdown, uncrystallised funds pension lump sums (UFPLS), annuities, mixing them, and the Money Purchase Annual Allowance ([GOV.UK, how you can take your pension](https://www.gov.uk/personal-pensions-your-rights/how-you-can-take-pension); [GOV.UK, tax-free part](https://www.gov.uk/tax-on-pension/tax-free); [s227G](https://www.legislation.gov.uk/ukpga/2004/12/section/227G)). No step sends you to a named service.
- **The care costs card is gone** (CSJ: "take it out").
- **The dashboard card for someone drawing a pension** shows this year's income and "runs out by about age X", the Retirement page's own figures.
- **Every screen now shows the figure the server works out,** on the desktop and mobile web apps. Before, each screen did some of its own arithmetic, so the same household could see two different numbers. This covers:
  - the retirement projection (card, Retirement page, Fyn);
  - net worth, debts, equity, shares and property;
  - savings, the one emergency fund target, interest and maturity;
  - investment value, charges, returns and monthly contributions;
  - protection premiums and cover;
  - the estate value and the Inheritance Tax table;
  - the tax allowance tiles;
  - goals.
- **Removed:** the desktop Emergency Fund "Adjust Target" slider, which worked out a second target in the browser. The desktop Tax Strategy page now counts the allowances with headroom instead of adding together allowances of different kinds.

**What we checked.**
- **On the test site, desktop (full-size window) and mobile web apps:**
  - a saver and a retiree drawing a pension: the card and the Retirement page show the same figures;
  - the Mitchell demo household: dashboard £1,464,500 net worth, the same as the Net Worth page; savings £74,750; investments £172,500;
  - "Plan how you will take your pension" for a household at its retirement age with £160,000 not yet drawn: seven steps, about £40,000 tax-free, the £268,275 cap and the £10,000 Money Purchase Annual Allowance.
- **Live on fynla.org, as the Mitchell demo household:** desktop and mobile both show net worth £1,464,500, protection £700,000, savings £74,750 (14 of 6 months), retirement £500,000 (85% of target) and investments £172,500. On the Retirement page, projected income of £63,595 against the £75,000 target is the card's 85%.
- **Not walked on fynla.org:** "Plan how you will take your pension". No live account qualifies; the approved text is on the server, checked file by file.

**Found on fynla.org, fixed, not yet released (#1048):**
- An investment card told the Mitchells their emergency fund was "critically low at 0 months", beside the 14 months on the same dashboard. The investment rules never received the savings figures and read the gap as 0.
- The ISA card wrote "10,000" with no pound sign.

Both fixes are on the test site with the one rule for ISA allowance used and the Personal Allowance and Annual Allowance tapers.

**Still to do:**
- The steps for "Think about combining your pensions" and "Ask for enhanced annuity quotes" went back to draft when their MoneyHelper and Pension Wise steps came out. They need approval again.
- The salary sacrifice warning still uses £10,000, not the National Minimum Wage for your age and hours.
- Investment, Protection and Savings still ask a retiree for "gross annual income".

**Not tested: the iPhone app.** Its changes ship with the next build and CI checks them.

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

- Life expectancy uses the men's tables when no gender is recorded, which understates it for a woman.
- Marriage Allowance received is not yet taken off the Income Tax shown on the Retirement page.
- Fyn once said "Your State Pension record has been updated" when nothing was saved. Fyn is told not to, but nothing checks it.
- With taxed savings interest, the £100,000 card can stop up to £500 short of a stretch that would save 60%. The figure shown is never overstated.
- Whether earnings are recorded before or after salary sacrifice affects the relief limit when earnings are the limit; not yet checked.
- The public Save Tax estimate still promises a partner top-up without checking affordability.
- A pension for a child (£2,880 a year) is not yet sized to what you can afford.
- The pension tile label is worked out separately on the desktop and mobile; it should come from the server, once.

## Behind the scenes

- **`PensionAffordability`** is the one rule for the money a pension payment can come from, used by every pension card and the tile. Before, there were three different rules.
- **At release:** app code and one seeder (the partner top-up now needs spending recorded), then both web bundles. No database changes.

## Not tested: the iPhone app

These are server changes, so the iPhone app gets the same figures, and its typed setup question already asked for spending. Its own tile wording was not changed or tested.
