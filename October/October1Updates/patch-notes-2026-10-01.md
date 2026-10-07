# What's new in Fynla — 1 to 7 October 2026

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

## One income figure everywhere, and Fyn reads what the screens show (2 October, about 20:54 and 21:02, releases #1053 and #1055)

- **Your income is the same figure everywhere.** You give the parts (salary, self-employment, rent, dividends, interest, other income, trust income) and your pensions, and the Income page adds them up: rental profit rather than gross rent, any pension being paid, and share-scheme vests. Every part of the app now uses that figure. Before, many places added up their own version, and most left out a pension being paid.
  - **Someone living on a pension is no longer told "Gross annual income is required".** That message blocked Investment, Protection and Savings advice for a retiree with £9,000 of pension. Protection advice now opens; Savings and Investment ask only for monthly spending.
  - **Take-home pay is the Income page's figure everywhere,** including the goals and cash-flow plans, which ignored a pension being paid.
  - **One tax band rule.** Capital Gains Tax on possessions and property, the investment bond suggestion and the retirement plan's tax rate now use the band the Tax plan uses, with rates from the tax settings. Typed-in rates were removed, including 10% and 20% for Capital Gains Tax, the rates before 30 October 2024 ([GOV.UK, Capital Gains Tax rates](https://www.gov.uk/capital-gains-tax/rates)).
  - **Gifts out of income** can now be suggested to someone whose income is a pension ([Inheritance Tax Act 1984 s21](https://www.legislation.gov.uk/ukpga/1984/51/section/21)).
  - **The letter to your spouse** said "Current Household Income" beside your earnings alone; it now shows your annual income.
- **Fyn's income and tax band are the Income page's and the Tax plan's.** Fyn summed the raw income fields, so it had no income at all for a retiree on a pension.
- **Fyn's recommendations are your actions list:** the same items in the same order as your Actions page and dashboard, without anything you have marked done. Before, Fyn read each module's own list and could say "your list is empty" beside ten open actions.
- **"ISA allowance used this year" is one rule** (it differed between the Tax plan and the ISA tracker), and the Annual Allowance and Personal Allowance tapers each have one home ([Finance Act 2004 s228ZA](https://www.legislation.gov.uk/ukpga/2004/12/section/228ZA); [Income Tax Act 2007 s35](https://www.legislation.gov.uk/ukpga/2007/3/section/35)).
- **An investment card no longer says your emergency fund is "critically low at 0 months"** when the savings figures were simply not passed to it. The Mitchell demo showed it beside 14 months of cover.
- **Investment card amounts show the pound sign** ("£10,000", not "10,000").
- **Steps approved (CSJ, 2 October):** "Think about combining your pensions" and "Ask for enhanced annuity quotes".
- **Fixed after release (#1055):** rebuilding the demo households left them without a protection profile, so the Mitchell demo showed "£0, Add your cover" for about nine minutes. The demo setup now creates it.

**What we checked.**
- **On the test site, desktop and mobile web apps:** a retiree with £9,000 of pension: Income page and Income tab both £9,000, take-home £9,000; Protection unlocked; Fyn: "£9,000, all from pension income … below the £12,570 Personal Allowance". A walk account that marked an action done: Fyn listed the remaining actions in the page's order. Every demo household: the Income tab total equals the Income page total.
- **Live on fynla.org:** the Mitchell demo on desktop and mobile: net worth £1,464,500, protection £700,000, savings £74,750, retirement £500,000, investments £172,500; Investment shows 3 actions, without the "0 months" card; ISA card "£10,000 … (£47,500)". The Bennett demo: Income tab £30,000 for Patricia, £26,514 take-home, the same as the Income definitions panel.
- **Not walked on fynla.org:** Fyn. The demo households cannot open Fyn, and its "Ask Fyn about this" button does nothing there (found, not changed).

**Still to do:**
- "Ask for enhanced annuity quotes" needs a smoker or health status that nothing in the app records yet.
- The Income tab does not yet tax share-scheme vests, though its total includes them.
- Someone retired who has not started drawing sees "the same £0 each year" on the Retirement page.

## One income, tax and spending figure, and Fyn's changes go through forms (5 October, releases #1071 to #1079)

**This is live on fynla.org** through five releases on the morning of 5 October: #1071 (about 09:33), #1073 (09:54), #1075 (10:34), #1077 (11:00) and #1079 (11:28), UK time.

- **Income Tax, National Insurance and take-home pay are worked out once, on the Income tab,** and the Retirement page's "Your income this year" reads them from there.
  - **Other income and share-scheme vests are now taxed** there. Gift Aid and pension payments that get relief at source widen your tax bands rather than coming off your income ([Income Tax Act 2007 s414](https://www.legislation.gov.uk/ukpga/2007/3/section/414); [Finance Act 2004 s192(4)](https://www.legislation.gov.uk/ukpga/2004/12/section/192)).
  - **No National Insurance on pay after State Pension age,** and no Class 4 from the 6 April after it ([Social Security Contributions and Benefits Act 1992 s6(3)](https://www.legislation.gov.uk/ukpga/1992/4/section/6)).
  - **State Pension age is worked out to the month,** not rounded to whole years ([Pensions Act 1995 Schedule 4](https://www.legislation.gov.uk/ukpga/1995/26/schedule/4)).
  - **Salary sacrifice is one deduction,** taken off before tax and National Insurance, and no longer counted again as spending.
- **Fyn can change your dividends, interest, trust and other income through a form.** Trust income is taxed in your own bands, with the tax the trust paid as a credit ([Income Tax Act 2007 s16](https://www.legislation.gov.uk/ukpga/2007/3/section/16), [s494](https://www.legislation.gov.uk/ukpga/2007/3/section/494)).
- **Gender is asked with your date of birth** when you set up, and on the personal details form. Life expectancy depends on it.
- **Your monthly spending is one figure on the desktop and mobile web apps.**
  - **The total adds up every category,** including charitable donations. Rent and utilities count for someone without a home of their own; a homeowner's housing costs come from the property. Before, the mobile app showed £955 where the desktop app showed £2,095 for the same spending.
  - **The desktop spending table now matches.** It counted a homeowner's utilities twice: the Mitchell demo showed £1,385 against £1,225 on mobile.
  - **A couple's one-figure total is halved across both accounts** when Fyn records it, as the desktop form already did.
- **"Edit details" in the mobile app opens a form** for your spending and your personal details (date of birth, gender, marital status). Before, it opened a chat that asked typed questions and never asked gender.
- **A change you type to Fyn fills in the form for you** (CSJ, 5 October). Say "my date of birth is 15 March 1981", and Fyn opens your details with that date filled in. Nothing is saved until you press Save.
- **Fyn no longer says it saved something it did not.** Before, after "Actually my date of birth is 2 August 1960", Fyn replied "I have updated your date of birth" and changed nothing.
- **A Fyn reply shows once.** The mobile app showed some replies twice, and the desktop app repeated them as the heading of the record card.
- **Demo households no longer show "Ask Fyn about this" or "Get more recommendations".** Fyn is not available in a demo, so those buttons did nothing.

**What we checked.**
- **On the test site, desktop (full-size window) and mobile web apps,** before each change was merged:
  - Expenditure: the same total on both apps after editing on each (£1,760, then £1,810);
  - "Edit details" forms for spending and personal details;
  - a typed date of birth filled into the form, and saved only on Save;
  - the Mitchell demo with no Fyn buttons.
- **Live on fynla.org:**
  - **The Mitchell demo on both apps:** spending £1,225 entered plus £3,966 of commitments, £5,191 a month. Income £156,806, with the tax worked through by hand: bands widened by £3,000 of Gift Aid, no Personal Allowance at £142,206 adjusted net income, £50,016 Income Tax after the £780 mortgage credit, £101,879 net.
  - **A walk account on the mobile app, deleted afterwards:**
    - signed up through Save Tax;
    - spending of £2,600 saved through "Edit details";
    - the personal details form saved gender, and the reply showed once;
    - "Actually my date of birth is 15 March 1981" opened the form with 15/03/1981 filled in: the date was unchanged until Save, then saved.

**Still to do:**
- **A change typed to Fyn without "Edit details" or wording like "change my"** can still be answered in words, not with a form. Fyn now says "That has not been saved yet" instead of claiming a save, but it can still list the new value as if it were recorded.
- **Typed answers on the setup steps** are still saved straight from the text, not through the form.
- **On Save Tax,** answering "No thanks" to the question about bank and savings accounts ends the whole setup, so date of birth, pension and spending are not asked (CSJ to decide).
- **In a demo, "Mark as done"** is accepted but not kept.
- **The mobile app's "Date of birth is required" prompt** asks Fyn for pension details.

## Estate actions you can follow, with figures that add up (7 October, about 12:50, release #1122)

**This is live on fynla.org** through release #1122 (#1120, with #1118, #1119 and #1121), UK time.

- **One Inheritance Tax card instead of a figure with no steps.** It states the tax, the estate and the allowances it is worked on, and each step that reduces or pays it, with its own figure: leaving more to charity (the rate falls to 36% once charity gifts reach 10% of the estate the test measures, [Inheritance Tax Act 1984 Schedule 1A](https://www.legislation.gov.uk/ukpga/1984/51/schedule/1A)), the £3,000 yearly exemption ([s19](https://www.legislation.gov.uk/ukpga/1984/51/section/19)), gifts out of income ([s21](https://www.legislation.gov.uk/ukpga/1984/51/section/21)), larger gifts, life cover in trust and gifts into a trust. For a couple it says "If you both died today".
- **A married person whose partner is not linked** is told the figure is "based on your own records alone" and "does not allow for anything passing to your partner", because what passes to a husband, wife or civil partner is free of the tax ([s18](https://www.legislation.gov.uk/ukpga/1984/51/section/18)). The card also carries the same notes the Estate page does, such as pension pots being left out until 6 April 2027.
- **One Lasting Power of Attorney card** that counts only a registered one ([Mental Capacity Act 2005 s9](https://www.legislation.gov.uk/ukpga/2005/9/section/9)) and names which kind is missing. A link opens Fyn's form to record it.
- **A gifts card** that never counts exempt gifts, uses the band a gift actually takes after the yearly exemptions, and gives the date the first gift leaves the seven years. A link opens Fyn's form to record each gift.
- **A pension card only where no beneficiary is recorded.** Fyn's pension form now asks "Who you want it to go to if you die", as the web form does, on every surface.
- **A trust card two years before the trust's ten-year anniversary** ([s64](https://www.legislation.gov.uk/ukpga/1984/51/section/64)), instead of a yearly review with no source.
- **Protection and Estate no longer both list the same life policy** that is not in trust.
- **The Estate plan page's sums are corrected.** The trust step no longer uses the remaining tax as the size of the gift; the gift steps are no longer multiplied by your life expectancy; there is no 50% cash test, no "age 50 or under" rule for life cover and no invented 85 or 50 ages. Turning steps on and off now uses the server's figure for each combination: for the Mitchell demo, charity and larger gifts together save £137,575, not the £151,331 the two added up to.
- **The card updates as soon as Fyn saves** a gift or a Lasting Power of Attorney behind it, on the desktop and mobile web apps. Before, it needed a reload.
- **Jointly owned assets now count** towards opening the Estate module for the joint owner.
- **Wording (approved by CSJ, 7 October):** all six estate how-tos, including recording each gift through Fyn.

**What we checked.**
- **On the test site, desktop (full-size window) and mobile web apps:** the Mitchell demo's plan page (charity alone £78,931, larger gifts alone £72,400, both £137,575) and Inheritance Tax card ("If you both died today, £349,112"); a married account with no linked partner (£105,200 = (£588,000 − £325,000) × 40%, with the "own records" wording); the Lasting Power of Attorney link through Fyn's form, the card updating without a reload.
- **Live on fynla.org:** the Mitchell demo on desktop (the card, £349,112 with the pension note, and the plan page toggles, the same figures as the test site); on the mobile app a walk account with £500,000 of savings (tax £70,000 = (£500,000 − £325,000) × 40%), recording a health and welfare Lasting Power of Attorney through the card's link, the card then naming only property and financial affairs with no reload. The walk account was deleted afterwards. No errors in the log.

**Behind the scenes.** No database change. The tax configuration, estate definitions and how-to seeders, app code, both the desktop and mobile web bundles, and one unused class removed.

## How much protection cover you need, worked out from sourced figures (6 October, about 17:20, release #1115)

**This is live on fynla.org** through release #1115 (#1112, #1113 and #1114), UK time.

- **Every figure behind "You need £X" on the Protection page now has a source** and lives in one place, the tax configuration, under "Protection needs calculations". The administrator can see and update each figure, with its source, from Tax Settings. Nothing is typed into the code any more.
- **Life cover is worked out from your household's spending.** Your household's living costs, less the income that would continue, are paid until your State Pension age and turned into a lump sum at the Personal Injury Discount Rate of 0.5%, the rate the law uses to turn a future income into a lump sum ([GOV.UK: Personal Injury Discount Rate](https://www.gov.uk/guidance/personal-injury-discount-rate); Damages Act 1996 as amended). Before, it was your income divided by a typed-in 4.7%, paid for ever, and a couple who earned the same were told they lost nothing. If your spending is not recorded, the page says so and covers your debts and final expenses only, rather than guessing.
- **Final expenses are £9,797**, the cost of dying in the [SunLife Cost of Dying Report 2025](https://sunlife.co.uk/siteassets/documents/cost-of-dying/sunlife-cost-of-dying-report-2025.pdf) (the funeral, professional fees and send-off), instead of a typed-in £7,500.
- **The £9,000 a year education figure is gone.** It charged every year from now to age 21, so a newborn added £189,000.
- **Income protection follows what insurers pay out:** 60% of your gross income up to £60,000 and 50% above it ([Legal & General Low Start Income Protection policy summary, QGI16002 04/25](https://www.legalandgeneral.com/asset/4a18ed/globalassets/adviser/files/protection/policy-summary/qgi16002.pdf/)), instead of a flat 60% in one place and 70% of net income in another.
- **Critical illness cover stays at three times your gross earned income, and says it is a rule of thumb**, not a set amount: cover is usually set by what you can afford.
- **The protection plan page and its PDF print the working** the Protection page shows, from the same one calculation.

**What we checked.**
- **On the test site, desktop and mobile**, as a walk account born 1985 earning £75,000 and spending £2,000 a month: life cover £605,543 (£24,000 a year for 26.6 years to State Pension age at 0.5%, £595,746, plus £9,797), critical illness £225,000 with the rule-of-thumb line, income protection £3,625 a month; the plan page; the administrator's "Protection needs calculations" section.
- **Live on fynla.org** as the Carter demo, on desktop (full-size window) and mobile: life cover short by £49,308 (£39,511 of income replacement plus £9,797 of final expenses; living costs of £46,824 less £38,080 of continuing income, £8,744 a year for 31.6 years, £255,011), critical illness £225,000 with the rule-of-thumb line, income protection £3,625 a month (60% of £60,000 plus 50% of £15,000); the plan page shows the same working and no 70%, 4.7% or education figure. The server reads every figure from the configuration. No errors in the log. The administrator's section was not opened on fynla.org.

**Behind the scenes.** No database change. The tax configuration, protection definitions and how-to seeders, app code, and both the desktop and mobile web bundles.

**Follow-up the same evening (about 18:10, release #1117, #1116): no ratings on the protection plan.** The plan page showed a rating beside each kind of cover, "Excellent" on life cover even when it was £49,308 short and "Critical" on the others. The ratings are gone from the plan page and its printed version, along with "Excellent:" and "Good coverage:" in the scenario notes and an unsourced "at least 50-60% of income". Each kind of cover still shows what you need, what you have and the gap in pounds. Walked on the test site and on fynla.org as the Carter demo (desktop, the plan page and its printed version). The mobile app has no plan page. No database change; app code and the desktop web bundle.

## Smoking and health in one place, no made-up premiums, and the right Retirement line for a retiree (6 October, about 15:40, release #1110)

**This is live on fynla.org** through release #1110 (#1108 and #1109), UK time.

- **Your smoking and health answers live in one place**: the Health settings page (desktop), Personal Information (mobile) and Fyn's "Your details" form all save to it, and every part of the app reads it. Before, the enhanced annuity card and the protection pages read a second copy that nothing ever filled in, so they always assumed a non-smoker in good health.
- **Fyn's "Your details" form asks "Do you smoke?" and "Are you in good health?"**, with the same answers as the Health settings page. "Edit details" on mobile Personal Information opens it filled with what you have already told us.
- **"Not answered" is now possible.** Everyone who still had the old defaults ("Never smoked", "Yes, good health") without having chosen them is shown as not answered, so the app asks rather than assumes; the people who gave a real answer keep it.
- **"Ask for enhanced annuity quotes" reaches the people it is for.** It shows for anyone who has smoked in the last 12 months, or has a health condition now or in the past ([Legal & General: smokers](https://www.legalandgeneral.com/insurance/life-insurance/health/life-insurance-for-smokers/); [Legal & General: enhanced annuities](https://www.legalandgeneral.com/retirement/pension-annuity/guides/enhanced-annuities/)), including a retiree who has no retirement target recorded. Its typed-in 20% and 15% uplifts and 5% annuity rate are gone; the annuity or drawdown comparison shows the standard rate.
- **No made-up premiums anywhere.** Every monthly or annual premium the app estimated came from typed-in rates with no source. They are gone from the protection plan and its suggestions, the Estate life cover page and the Estate plan. The life cover page now shows the cover your Inheritance Tax calls for and "Premium: From insurers' quotes", with the steps to arrange it and no insurer named.
- **A retiree's Retirement line fits.** Someone drawing their pension reads "See your income this year and how long your pension lasts" under Retirement on the dashboard, instead of "Close your projected income gap". Each area's line now comes from the server.
- **The two investment bond guides are approved** and live with their cards.

**What we checked.**
- **On the test site, desktop and mobile**, as a retired walk account with a £200,000 personal pension: the Retirement line, no annuity card while smoking and health were unanswered, the Health settings saved, the card and its guide on both apps, and Fyn's mobile form filled with the desktop answers, changed and saved. As the Mitchell demo: the life cover page (£349,112 of cover, "From insurers' quotes") and the protection plan's "Never smoked" and "Yes, good health".
- **Live on fynla.org:** the same walk on desktop and mobile with a walk account (removed afterwards), and the Mitchell demo's life cover page and protection plan. On production, 72 people moved to "not answered" and the 6 with real answers kept them. No errors in the log.

**Still to do.** How much cover the Protection page says you need still rests on typed-in figures (4.7% for life cover, three times income for critical illness, £7,500 for final expenses, 70% of net income for income protection). They need a sourced method; that is the next piece of work.

**Behind the scenes.** One database change (the smoking and health columns can be empty, the defaults were reset, and the unused protection copies were removed), the tax configuration, retirement definitions and how-to seeders, app code and the desktop web bundle. No mobile bundle change.

## Investment bonds, clearer investment pages, and edits through forms (6 October, about 12:20, release #1105)

**This is live on fynla.org** through release #1105 (#1104), UK time.

- **Investment bonds.** An onshore or offshore bond now shows the gain building up inside it and how much of the 5% a year you can still take without tax at the time ([ITTOIA 2005 s491, s507](https://www.legislation.gov.uk/ukpga/2005/5/section/507)). If what you paid in is not recorded, a card asks for it. Add a bond's "What you paid in", start date and 5% withdrawals on the desktop form or through Fyn (desktop and mobile). The guides for these two cards are waiting for your approval.
- **"Edit details" on an account, pension, property or policy opens its form**, and a change you type there is filled into that form. Before, Fyn answered in words and saved nothing.
- **The diversification panel only states gaps your holdings prove**, for example "Alternatives are at least 21% of this account against a 5% target". Funds whose mix is not recorded are no longer counted as missing shares, and three global funds are no longer called "high concentration".
- **No drift score on screen.** The rebalancing panel shows the largest gap from the target in percentage points.
- **A joint account's projection shows the value it projects:** "Your share today £47,500", growing to a middle outcome of £73,406, beside the account's full £95,000.
- **Capital Gains Tax is split between the rates:** the lower rate only within the basic rate band your income leaves unused ([TCGA 1992 s1H](https://www.legislation.gov.uk/ukpga/1992/12/section/1H)), in Bed & ISA and the losses figures. With no gains recorded, nothing suggests selling at a loss.
- **"Add" forms no longer say "save with none chosen"**, which only applies during setup.

**What we checked.**
- **On the test site, mobile:** an offshore bond added through the form (£30,000 gain building up, £15,000 of the 5% left), its "Edit details" form filled in, a typed change filled into it, and the save stored.
- **Live on fynla.org, desktop, as the Mitchell demo:** the joint account's diversification lines, "Outside its rebalancing threshold", "Your share today £47,500" and £73,406, and the charges, position and losses cards with their own short descriptions.

**Behind the scenes.** App code, the investment definitions and how-to seeders, and the desktop web bundle. No database change.

## Investment actions you can follow, with figures that match the page (6 October, about 11:10, release #1102)

**This is live on fynla.org** through release #1102 (#1098 to #1101), UK time.

- **One card for moving investments into an ISA.** When your General Investment Account holds gains, the Tax plan's "Bed & ISA" is the card; "Use your ISA allowance" no longer sits beside it (your decision, D2).
- **One card per account that has drifted from its risk level** (D3, per account, as the account page shows it), for example "Joint General Investment Account holds at least 21% in alternatives against 5% for its risk level". The card, the account page, the dashboard and the investment overview now use one rule. Funds whose mix of shares and bonds is not recorded are no longer counted as drift: two-thirds of the Mitchell demo's portfolio had been.
- **One charges card per account, in pounds a year** (D4): "Joint General Investment Account costs £1,104 a year in charges, 1.16% of its value: adviser £713, platform £238, fund charges £154." It shows the same figure as the account page, and it now reaches your actions list.
- **The losses card no longer says "Potential tax saving: £0".** It states the losses in your General Investment Account at your share, that a loss is set against the same year's gains before the tax-free allowance, and that it carries forward if reported to HM Revenue and Customs (HMRC) within four years ([HMRC CG21500](https://www.gov.uk/hmrc-internal-manuals/capital-gains-manual/cg21500); [GOV.UK](https://www.gov.uk/capital-gains-tax/losses)).
- **Only a General Investment Account counts for gains and losses**, in Bed & ISA too: EIS, trust, private company and employee share scheme accounts had been counted.
- **The emergency fund, savings rate and spare-cash suggestions are no longer repeated under Investment** (D1); Savings carries them.
- **Seven new "How to do it" guides,** approved by you: the position card, charges, losses, the two ISA cards, investment bonds and adding your holdings. The bond guide covers onshore and offshore bonds, top-slicing relief ([Income Tax Act 2007 s535](https://www.legislation.gov.uk/ukpga/2007/3/section/535)) and the 5% a year that builds up if unused ([ITTOIA 2005 s507](https://www.legislation.gov.uk/ukpga/2005/5/section/507)).
- **On the desktop app, "Go to it" on an investment account card opens that account,** not the investment overview.

**What we checked.**
- **On the test site, desktop (full-size window) and mobile web apps, as the Mitchell demo:** the actions list, the position card through to the account page ("Rebalancing Recommended", bonds 26.3% against 20%), the charges card and the account page both at £1,104 and 1.16%, the losses card and its guide.
- **Live on fynla.org, as the Mitchell demo:** the desktop actions list with the new cards and Bed & ISA alone; the joint account's charges card and its guide; on mobile, the position card and its guide.

**Still to do:**
- On the charges, losses and position cards, the first "Why this matters for you" line repeats the description.
- The account page's diversification panel still says "Equities allocation is underweight by 75%", counting funds with no recorded mix as missing.
- The joint account's 10-year projection shows a middle outcome below today's value.
- The rebalancing panel shows its drift score as a percentage.
- Someone who already holds an investment bond gets no card about its deferred tax position.

**Behind the scenes.** App code, the investment definitions and how-to seeders, and the desktop web bundle. No database change and no mobile bundle change.

## Fyn's changes always go through a form; demo "Mark as done" is kept; the Holistic Plan follows your actions (6 October, about 09:09, release #1094)

**This is live on fynla.org** through release #1094 (#1082, #1084, #1085, #1087, #1090), UK time.

- **Every change you type to Fyn opens its form, filled in.** This covers changes said in any words, and answers typed on the setup steps. Nothing is saved until you press Save. When more than one record could be meant, Fyn asks "Which one needs changing?" and opens the one you tap.
- **Adding something through Fyn opens the blank form for it,** filled in from what you said. The "Add" buttons open the same form.
- **The "Date of birth is required" prompt asks for your date of birth.** Before, it asked Fyn for your pension details.
- **On Save Tax, "No thanks" to the question about your bank and savings accounts skips only those questions** (CSJ, 5 October). Fyn still asks your date of birth, your gender and your spending, so the plan is sized to what you can afford. The closing lines say "Save Tax".
- **In a demo, "Mark as done" is kept for your visit,** on both apps and after a reload. Another visitor to the same demo household does not see it.
- **The Holistic Plan lists the same actions as your actions list, in the same order,** and an action marked done leaves it. Before, it ranked by pounds saved, so smaller tax items sat above higher-priority actions.
- **On the desktop app, tapping a record under "Which one needs changing?" opens it.** Before, it did nothing.

**What we checked.**
- **On the test site, desktop (full-size window) and mobile web apps,** before each change was merged: a typed change and an "Add" through their forms; "No thanks" then date of birth, gender and spending; the Mitchell demo marking actions done from the list and from an action's page, kept after reload, not seen by a second visitor; the Holistic Plan's 31 items in the actions list's order on both apps.
- **Live on fynla.org, as the Mitchell demo:**
  - Desktop: "Bed & ISA" marked done, kept after reload (34 open, 1 done).
  - The Holistic Plan's 29 items matched the actions list's order, with "Bed & ISA" gone.
  - Mobile: the same visit showed "Bed & ISA" done; "Consider a joint life policy" marked on mobile was kept after reload (33 open, 2 done).
  - Both completions belong to that visit only, and the demo household itself gained no points.

**Still to do:**
- **Demo households other than the Bennetts open the desktop dashboard blurred,** because they are set up as not having finished setup, and the blur stays until the chat is touched.
- **A blank investment form opened by "Add" says "If you have no investments, save with none chosen",** which is setup wording; outside setup an empty save is refused.
- **Switching from one demo household to another signs out every other visitor to the one you left.**
- **The Holistic Plan's "Plans Included" section** still lists module suggestions that the actions list does not have.

**Behind the scenes.** App code, one database change (a demo completion records the visit it belongs to) and the desktop web bundle. No mobile bundle or seeder change.

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
