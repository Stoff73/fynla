# What's new in Fynla — 22 September 2026

Three updates went live on fynla.org on 22 September. The first two carry a new feature, the threshold position, that had been in the works since the 17 September design session, plus a batch of things we found while building it. The third is everything Brett raised after walking through the app on his phone.

Everything below was walked through as a real user on the test site before it merged, then again on the live site after each release, on both the desktop web app and the mobile web app. This note only covers what has changed since the 19 September update.

## Where you stand against the tax lines

- **A new strip at the top of "Your actions" shows the tax or benefit line that matters most to you.** For example: "You are £11,800 into the 60% band". It tells you what being over that line costs you each year, and the one thing that would move you back under it, such as "Pay £11,800 into your pension".
- **It is worked out for you personally.** Fynla checks which lines apply to your own income, family and estate and shows the nearest one first. If none apply, the strip does not appear. There is no fixed list.
- **The lines it can show:** the £100,000 Personal Allowance taper; the childcare cliff at £100,000 (Tax-Free Childcare and the funded hours for under-fives); the High Income Child Benefit Charge; the higher-rate and additional-rate thresholds; the tapered pension Annual Allowance; the salary-sacrifice National Insurance cap from April 2027; the inheritance tax nil rate band and the £2 million residence band taper; and pensions entering the estate from April 2027.
- **The cost is a real calculation, not a rule of thumb.** Fynla works out your full tax bill where you are and again at the line, and shows the difference broken down by income tax, National Insurance, dividend tax, savings tax and lost benefits. On the web you see the full breakdown behind "See what this costs"; on mobile you see the headline and the total.
- **The suggested pension amount is the same one your actions list recommends,** so the two can never disagree. If the pension money would be locked until 55, the strip says so.
- **Your whole income mix counts:** salary, self-employment, rental profit, pensions in payment, interest, dividends and, new this week, shares that vest this year.

## Share schemes and pensions in drawdown

- **Fynla now sees a share vest coming.** If you hold restricted stock units or unapproved options, the shares vesting this tax year count as income for every tax calculation and for the strip. The strip also warns when a coming vest would push you back over a line.
- **Every share scheme now carries a value** (vested units at the current share price) and the card is labelled properly, so "Restricted Stock Units" rather than a code, on web, mobile and the iPhone app.
- **The share scheme drill-down now shows the unvested units correctly.** It used to read "Fully vested, 0 unvested" on a scheme with 800 units still to vest.
- **You can record how often a scheme vests** ("Months Between Vestings") on the existing form.
- **Pensions in drawdown can be captured.** A defined contribution pension now has fields for the annual income you draw from it and the tax-free lump sum you have taken, on web, mobile and through Fyn. Drawdown income counts as pension income; the lump sum never counts as income.

## Childcare, Gift Aid and families

- **Childcare spend and charitable donations are now free-tier fields.** Before this week, free users could not enter either, so the Tax-Free Childcare line and the Gift Aid reclaim could never reach them. Both now appear on the spending form, in Fyn, and on the profile, whichever plan you are on.
- **Gift Aid relief is only suggested when you have actually ticked Gift Aid.** Fynla used to tell every donor to reclaim higher-rate relief, including donors whose gifts were not made under Gift Aid. The Gift Aid tick box now sits beside the donations figure wherever donations are entered.
- **A child with a disability can be recorded** on the family form, in Fyn's dependants form, and on the mobile list. This unlocks the higher £4,000 Tax-Free Childcare cap and the age-16 limit that apply in that case.
- **The Personal Allowance taper explanation now gives the right upper figure** (£125,140), matching the strip.

## Telling Fyn about your finances (from Brett's phone walk)

- **An odd character (a box with a cross) no longer appears in Fyn's opening message** when you start a profile on mobile.
- **Job title is now optional.** You are asked for an employer and the pay, and can leave the title blank.
- **The "tap Okay when you're ready" step has gone.** When Fyn finishes saving a section it takes you straight to the page to check, with Continue and Edit, instead of asking you to press Okay first. This is a trial; we will keep it unless it causes confusion.
- **The risk profile is not shown while you are setting up.** It becomes an action for later. The wording is now "change any you don't agree with" rather than "any that are wrong".
- **The retirement page no longer shows a projection against a target while you are setting up.** During the Save Tax walk it shows only your pensions and the overview; the projection, target and recommendations return once you have finished.
- **Each mortgaged property on the mobile net worth screen now shows your equity**: your share of the value minus your share of the mortgage. The web already did.
- **Dividends are now asked for when you enter shares.** The investment form asks "Dividends it pays you each year" for a general investment account or other holding, and the spouse form asks "Dividends they receive each year". The hint reads "Leave blank if none or unknown".
- **A non-working spouse's pension contributions are now captured** with a simple yes or no: "They pay the non-earner maximum into it (£2,880 a year)". Fynla fills in the figure from the current tax rules and uses it in the plan, which can then lead with "Top up your spouse's pension by £2,880".
- **The spending step in Save Tax asks only for what changes your tax:** childcare, charitable donations and Gift Aid. It no longer asks for your total monthly outgoings; that belongs in the actions that follow. Fyn's read-back repeats everything you entered, on this form and on the spouse form.
- **The spending form says it is for the household** ("What your household spends each month").
- **"See all your actions" on the tax strategy page now takes you to your actions,** not to the protection promo.
- **The set-up wizard on the web prefills your spending correctly.** A Save Tax user who had entered childcare saw £0 there before.

## Smaller fixes

- **A phone number typed with spaces is accepted** everywhere you can enter one.
- **Error messages point at the field that is wrong** rather than a general "something went wrong".
- **The tapered Annual Allowance now uses the correct definition of adjusted income,** which was previously understated by the employee's own pension contributions.
- **Salary-sacrifice National Insurance savings are priced one way** throughout the app; there were three different calculations before.
- **Every account type has one label** wherever it appears, chosen in one place and read by web, mobile and the iPhone app.
- **Figures no longer arrive with floating-point noise** (for example £700.0000000001) on either server.

## Decisions taken this week

- **Free tier includes childcare and charitable donations.** Anything that feeds the threshold lines is available to every user.
- **The threshold strip is always personal.** No fixed set of lines, no peer benchmarking.
- **The National Insurance cap on salary sacrifice is dated April 2027** throughout, taken from configuration.
- **Minimum pension age is 55, rising to 57 from April 2028,** and the strip says when money would be locked.
- **Save Tax asks only the tax-relevant spending fields.** The full monthly budget moves to the actions after set-up.
- **Older paid plans count as Premium.** The eight customers on legacy plans keep full access.
- **Anything found while building or testing is fixed in the same batch,** not written up for later.

## Behind the scenes

- The automated test suite is fully green for the first time in weeks; several long-failing tests were fixed rather than skipped, and one of them turned out to be a real bug (the legacy plan point above).
- The free-tier spending categories now live in one place, so the profile, the set-up wizard and Fyn cannot drift apart.
- Three production backups were taken, one before each release.

## What we checked

Each release was walked live on fynla.org with a fresh account: registering with a spaced phone number, adding a share scheme and reading its card and drill-down, entering childcare and donations with Gift Aid, reading the strip and its costs on the actions page, recording a disabled child and seeing the higher childcare cap, and, for the third release, the full Save Tax walk on mobile from part-time work through spouse, investments, pensions and spending to the plan and the actions list. Property equity was checked on the test site, as the live walk account had no property. The iPhone app was not rebuilt this week; it reads the same data but I could not test it on screen. Every test account was removed afterwards.
