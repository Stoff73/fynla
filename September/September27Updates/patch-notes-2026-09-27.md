# What's new in Fynla — 27 September 2026

Two updates went live on fynla.org on 27 September. The morning release carries the work from 26 September: pension contributions now count everywhere they should, the inheritance tax rules follow the April 2025 change to long-term UK residence, and every action now opens its own detail card. The evening release makes the tax figures themselves more accurate, after a line-by-line review of every calculation behind the Save Tax plan.

Both releases were walked through as a real user on the test site before they merged, then again on the live site afterwards, on the desktop web app and the mobile web app. This note covers what has changed since the Save Tax accuracy update of 26 September.

## Every action has its own card

- **Tapping any action opens a detail card** instead of sending you straight to another page. The card says what the action is, why it matters for you, the key figure (for example "Saves about £7,240 a year"), and when it needs doing by.
- **"Fund from" shows where the money could come from.** The card lists your cash and investment accounts and suggests the safest one first. It warns you if using an account would leave you with less than your emergency fund, and if selling investments could mean paying Capital Gains Tax. Joint accounts count at your share.
- **Each card has three ways forward:** "Ask Fyn about this" for an explanation, "Go to it" to open the page where you act, and "Mark as done" (or "Add it now" where Fynla needs a detail from you first).
- **Deadlines are shown where they exist.** Allowances that do not carry over, such as ISA and pension contributions, say "Closes 5 April". Actions that need doing straight away say "Immediate action".
- **One-off savings no longer say "a year".** A one-off pension top-up using unused allowance from earlier years, for example, shows the amount without "a year".
- **The same cards appear on the web, on mobile and in the iPhone app.** On the iPhone app, "See all actions" now opens your list of actions rather than the achievements page.
- **Step-by-step "how to" guides are ready for 21 tax actions** and are waiting for review. None will show until each one is approved.

## Pension contributions count everywhere

- **Pension contributions entered while setting up now reach every tax figure.** Before, a workplace pension entered through Fyn was left out of your income for tax purposes, so your tax bands, the 60% band and the Annual Allowance check were all worked out as if you paid nothing in.
- **Personal pensions and Self-Invested Personal Pensions (SIPPs) are counted correctly.** The pension provider adds basic-rate relief to what you pay, and Fynla now counts the full amount going in, as HMRC does.
- **The Annual Allowance "used" figure is now right.** It counts what you pay, the tax relief added to it and what your employer pays. For our test user it moved from £7,200 to the correct £7,800.
- **The income panel shows the working.** A new line, "Less personal pension contributions (grossed up)", sits between net income and adjusted net income, so you can see how the figure is reached.
- **Pension cards show contributions on the web as well as on mobile,** including the workplace contribution line that was missing.
- **The pensions page projection works again.** "Projected Value (middle outcome)" was showing £0 because of a mismatch between the page and the server; it now shows the projected figure.
- **The Save Tax page no longer tells pension holders they could save "up to £0".**

## Inheritance tax and where you have lived

- **Fynla follows the April 2025 rules on long-term UK residence.** From 6 April 2025, whether your worldwide estate is liable to UK inheritance tax depends on how many of the last 20 years you have lived in the UK, not on the old "deemed domicile" rule. The rule now comes from Fynla's tax settings, like every other tax rule, and years before April 2025 keep the old rule.
- **The profile section is now called "Where you have lived"** on the web, mobile and the iPhone app.
- **An unanswered country of birth is no longer assumed to be the UK,** and a saved country now shows correctly after you reload the page.

## More accurate tax figures

We checked every calculation behind the Save Tax plan against the legislation. These are the corrections that went live.

- **Savings interest is taxed the way HMRC taxes it.** Fynla now applies:
  - the £5,000 starting rate for savings;
  - the right Personal Savings Allowance for your total income;
  - any unused Personal Allowance against interest and dividends.

  Two examples: someone with £20,000 of interest and no other income was shown £2,800 of tax; the correct figure is £286. Someone living on £20,000 of dividends was shown £2,096; the correct figure is £745.
- **Pension relief no longer counts tax-free interest as taxed income.** For our test user (£72,000 salary, £30,000 in savings) the plan said "£18,600 of your income is taxed at 40%" and a saving of £7,440. £500 of that was savings interest already covered by the Personal Savings Allowance. It now says £18,100 and £7,240.
- **Every saving is now worked out on your full tax picture,** not with a single flat rate. This applies to moving cash into an ISA, sharing savings with a spouse, Gift Aid, and pension contributions at higher incomes. So a saving that spans two tax bands, or runs into an allowance, is priced correctly.
- **Using unused pension allowance from earlier years is no longer double-counted.** This year's allowance is used first, and only the extra beyond it is shown as a separate saving. For an £80,000 earner in our tests, the figure drops from £32,000 to the correct £1,486.
- **Pension relief is capped where the law caps it.** Tax relief is only given on contributions up to your earnings, or £3,600 a year if that is higher. Fynla now applies that limit to the higher-income suggestions and to a spouse's pension.
- **Someone earning exactly £50,270 is treated as a basic-rate taxpayer,** as the law says.
- **Figures now round down, never up.** No suggestion promises more than the calculation supports. The tapered pension allowance is now shown to the pound; rounding it up to the nearest £1,000 could have invited a contribution that triggers a tax charge.
- **Joint investment accounts count at your share** when Fynla works out gains you could move into an ISA.
- **The emergency fund warning works the right way round.** It now warns only when using an account would leave the rest of your cash below your emergency fund.
- **The dividend allowance explanation is corrected.** It used to describe a tax saving as a "payout".
- **No tax figure is hard-wired into the code any more.** Every rate and threshold comes from the tax settings for the current year.

## The Save Tax page

- **The ISA line now shows the tax on the interest, not the money moved.** It used to value putting £10,000 in an ISA as a £4,000 saving. An ISA saves only the tax on the interest, which is often nothing once the Personal Savings Allowance covers it, and the line now only appears when there is a real saving.
- **Allowances you already get automatically are no longer counted as savings.** The Personal Savings Allowance, the dividend allowance and the Capital Gains Tax allowance apply whether or not you do anything, so they no longer add to "you could save". They still appear in the allowances list.

## Telling Fyn about your finances

- **Fyn's opening question lists your savings, pensions and so on in the order it will ask about them.** Before, it could promise pensions first and then ask about bank accounts.
- **A missing comma is back** in that question ("…and pensions, is that okay?").
- **SIPP is spelled out.** The pension form now says "A SIPP is a Self-Invested Personal Pension." above the choice of pension type.
- **After you save your pensions, Fyn first reads back what it saved** and only then mentions any limit on your plan. Before, the limit line alone made it look as if nothing had been saved.

## Decisions taken

- **Every tax rule comes from the tax settings,** including the long-term residence rule for inheritance tax.
- **"Fund from" is part of the first version of the action cards,** not a later addition.
- **Anything found while walking through a change is fixed in the same batch,** not reported for later.
- **Automatic allowances are not savings.** The Save Tax page counts only savings you can act on.
- **In-app purchase on the iPhone app is set up alongside web subscriptions.** Monthly £6.99 and annual £59.99, matching the web prices, for the UK store. This is parked until the app is submitted for App Store review; TestFlight testing is unaffected.

## Still to do

- **Review and approve the 21 tax "how to" guides.** Seven are marked as not yet checked against their source.
- **Rewrite the help pages.** An audit found that most sections describe screens that have since changed, and some state facts that are now wrong.
- **Fyn's "Ask Fyn about this" explanation** of the pension figure left out a personal pension and used an internal term. It is next on the list.
- **Scottish income tax rates** are not yet applied; this is its own piece of work.

## Behind the scenes

- The iPhone app's automated tests are fully green again. One login test had been failing for five days because its test setup was wrong, not the app; that is fixed, along with three screen tests that still expected the old way actions opened.
- Fynla's tax calculator had two versions of the savings-interest step, and they disagreed. Both now follow the same rules, and a new test checks they give the same answer.
- Two production backups were taken, one before each release.
- iPhone app test builds 11 and 12 were uploaded to TestFlight. The action cards appear there now that the live site has the matching update.

## What we checked

- **Morning release:** walked live on fynla.org with a fresh account. We registered, entered a £72,000 salary, a £30,000 easy-access savings account and a workplace pension (5% from you, 3% from your employer) through Fyn, and checked:
  - the pension card's £480 monthly contribution;
  - the action card and its "Fund from" account;
  - "Go to it", on both the web and mobile.
- **Evening release:** walked the same way on the test site and again on fynla.org. The Tax Strategy page showed:
  - "Pay £18,100 more into your pension and save £7,240 in tax";
  - the £280 ISA saving;
  - the £7,592 total;
  - Fyn's corrected opening question and the SIPP wording.
- **iPhone app:** it reads the same data but was not tested on screen.
- **Clean-up:** every test account was removed afterwards.
