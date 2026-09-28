# What's new in Fynla — 28 September 2026

Today's work makes every action personal. Each action card now says why it matters to you, how to do it for your own pensions and accounts, and what it will change: your Income Tax before and after, what it really costs you, and what happens to your take-home pay. The actions page is now grouped into the lanes that were agreed in the design, and several tax rules have been corrected along the way.

This is ready but **not yet live**. It sits in one update (pull request #948) waiting to go to the test site and then fynla.org. It was checked on a local copy of the app, on the desktop web app and the mobile web app, with real test accounts.

## Every action speaks to you

- **"How to do it" follows your own situation.** The steps depend on what you have told Fynla, so two people see different steps for the same action. For a pension top-up, for example:
  - with salary sacrifice, you are told to ask payroll to increase it;
  - with a workplace pension, you are asked first whether your employer offers salary sacrifice;
  - with a personal pension or SIPP, you see what to pay in and what the provider adds;
  - with only a defined benefit pension, you are told about buying extra pension or additional voluntary contributions (AVCs);
  - with no pension, you are told how to open one.
- **Your own figures and names appear in the steps.** "Pay £19,760 into Vanguard SIPP. The provider claims £4,940 of basic-rate relief and adds it, so £24,700 goes in." Monthly amounts are spread over the months left in the tax year, and dates come from the current tax year.
- **"Why this matters for you" uses your figures for all 21 tax actions.** Before, only 2 of them did. For example: "£24,700 of your income is taxed at 40%", or "At 34 you can still open a Lifetime ISA. On £4,000 paid in, the government adds £1,000."
- **"What this changes" shows the outcome.** For example:
  - "Doing this alone, your Income Tax for the year falls from £17,432 to £7,552: £9,880 less. £24,700 goes into your pension, and after the tax relief it costs you £14,820."
  - "Your take-home pay rises by £132 a year, about £11 a month, and the same £1,650 still goes into Scottish Widows Workplace Pension."
- **A step with a figure Fynla doesn't have is left out,** so nobody sees a blank or a placeholder.
- **Every step is checked against its source** (GOV.UK, HMRC's manuals or the legislation). Twenty of the 21 tax actions have been reviewed and approved. Marriage Allowance is waiting for a final review.

## The actions page is grouped

- **The actions page now uses the layout chosen on 17 September.** Actions sit in three groups:
  - **"Before 5 April":** allowances that do not carry over, such as ISAs, Junior ISAs and pension relief, with the number of days left.
  - **"Worth doing soon":** things with no deadline, where waiting still costs.
  - **"Waiting on you":** details Fynla needs before the figures can be right.
- **Every missing detail is now shown, once each.** Before, the page showed at most two, and the same request could appear several times ("Unlock investment info" three times, once for each action it would unlock). The four-slot dashboard still shows its top items.
- **On mobile, rows for these allowances now say "Closes 5 April",** as the mobile design shows.

## Tax rules corrected

- **The salary sacrifice National Insurance cap starts on 6 April 2029, not 2027.** From that date only the first £2,000 a year of salary sacrifice is free of National Insurance, for you and for your employer. The live site currently says 2027 in the threshold strip and the salary sacrifice warnings; this update fixes both.
- **The cost of paying more into a workplace pension is worked out properly.** The "can you afford it" check for the employer-match action treated every extra contribution as costing nothing before 2029. It now counts the contribution, less the Income Tax it saves, less the National Insurance it saves under salary sacrifice (within the cap from 2029).
- **Marriage Allowance is only shown to couples who qualify.** The person receiving it must pay no more than the basic rate, with dividends counted in full. The person giving it must have income below the Personal Allowance.
  - A spouse who "doesn't work" is no longer assumed to have no income. A pension or rent can use the whole allowance, so Fynla now waits for the spouse's actual income ("Add your spouse's income") before suggesting it.
  - A linked spouse's own records are used when they exist.
  - Anyone born before 6 April 1935 is told that Married Couple's Allowance may pay more.
  - Someone living in Scotland earning more than £43,662 is told it does not apply there.
- **Workplace pensions follow the law on automatic enrolment.** An employee aged 22 to State Pension age earning £10,000 or more is told their employer must enrol them and pay in at least 3% of qualifying earnings. Someone earning less is told they can ask to join. Members of a defined benefit scheme are no longer told they have no pension.
- **Bed and ISA explains the 30-day rule.** Selling shares and buying them back inside your ISA straight away is not caught by the 30-day rule, so there is no wait.
- **Gift Aid is shown to donors who don't use it yet.** They see what a Gift Aid declaration adds, for the charity and for them, as long as they have paid enough tax to cover it.
- **Unused pension allowance from earlier years** now uses this year's allowance first. It is no longer suggested to anyone limited to the lower allowance that applies after taking money flexibly from a pension.
- **Lifetime ISA and Junior ISA rules are complete.** The £450,000 first-home price limit, the 12-month wait, the age limits and the Child Trust Fund transfer are all included. The figures come from Fynla's tax settings.
- **Joint savings rest on the right law:** interest is split half each only while you live together, and a different split needs a joint declaration to HMRC.
- **People with no spouse no longer see any spouse actions,** as suggestions or as waiting items.

## Plain English

- **"Harvest" is gone from every screen.** "Tax loss harvesting" now reads "Losses you could use against gains", across the investment pages, the dashboard alert and the action list.
- **One piece of advice was wrong and is corrected.** A suggestion to sell and "immediately repurchase" is defeated by the 30-day rule. It now says that buying back within 30 days does not release the loss.

## Decisions taken

- **How-to steps follow the user's records and use their figures,** with the outcome shown on every action.
- **"Harvest" is banned in anything a user reads.**
- **The salary sacrifice cap date is 6 April 2029.**
- **Marriage Allowance waits for the spouse's actual income,** in the same way other actions wait for missing details.
- **Scottish income tax:** a caveat line on Marriage Allowance for now.

## Still to do

- **Approve the Marriage Allowance guide.**
- **Scottish income tax rates** are not yet applied anywhere in Fynla; this is its own piece of work.
- **Rewrite the help pages** from the audit.
- **Fix Fyn's "Ask Fyn about this" explanation** of the pension figure.
- **Make an intermittent iPhone app test reliable.**
- **Write the "how to" guides for the savings, protection, retirement, investment and estate actions.**

## Behind the scenes

- **One source for every card.** The card's "why", "how" and "what changes" all come from one reviewed file per module. The web, mobile web and iPhone app all show what the server sends.
- **No tax figure is typed into the code:** strategies that still had fallback figures (the Capital Gains Tax allowance, ISA allowances, Lifetime ISA ages and bonus) now read the tax settings only.
- **The how-to file is protected:** loading it now fails loudly if a heading doesn't match an action. Before, a renamed heading was silently skipped.
- **At release,** the tax settings, the action definitions and the how-to guides must be reloaded on the server.

## What we checked

- **Desktop web:** signed in as test users.
  - The salary sacrifice card showed Chris's own pension name, £1,650, the £132 saving and the 6 April 2029 cap.
  - The actions page showed the three groups for a married test couple.
  - "Unlock spouse's income info" opened its card with "Add it now".
- **Mobile web:** the junior pension and Lifetime ISA cards showed the personal steps and outcome. The actions list showed every waiting item once, and "Closes 5 April".
- **Every "how to", "why" and outcome line** was printed for the local test users and read through.
- **The automated tests** for every changed file pass. The full suite runs when the update is merged.
- **Not tested on screen:**
  - the reworded investment pages;
  - Fyn capturing a spouse's income through "Add it now";
  - the iPhone app.
