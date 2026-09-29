# What's new in Fynla — 28 September 2026

Today's work makes every action personal. Each action card now says why it matters to you, how to do it for your own pensions and accounts, and what it will change: your Income Tax before and after, what it really costs you, and what happens to your take-home pay. The actions page is now grouped into the lanes that were agreed in the design, and several tax rules have been corrected along the way.

**This is live on fynla.org** from 28 September 2026 at about 15:28, through release pull request #949 (update #948). It was walked on the test site and then on fynla.org itself, on the desktop web app and the mobile web app, with new accounts registered for the purpose.

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
- **Every step is checked against its source** (GOV.UK, HMRC's manuals or the legislation). All 21 tax actions have been reviewed and approved, Marriage Allowance last.

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

## Found and fixed on the release walk

- **Fyn now shows you the screen it asks about on a narrow browser window.** Below tablet width the chat fills the screen. When Fyn said "Here's your investments page — does it look right?", the page opened behind the chat, where you could not see it. The chat now closes so the page shows, as the mobile app already did. On a full-size screen the chat stays beside the page.
- **"Waiting on you" no longer asks for things you have already given.**
  - A Stocks and Shares ISA now counts as your ISA details. Before, only a cash ISA did.
  - Answering "none" (no investments, no charitable donations) now stays answered after you finish setting up. Before, the answers were thrown away at the end of setup, so the same questions reappeared as waiting items. Accounts set up before this release have already lost those answers and will still see them.
- **"Add it now" for your spouse's income works.** It opened Fyn with a request that mentioned "pension", so Fyn tried to add a pension and then refused. It now opens the form for your spouse's details, starting with their income. Saving it turns the waiting item into "Claim Marriage Allowance".
- **Two headings still said "harvest"** on the investment screens. They now read "Losses to use now" and "Losses you could use against gains".
- **A form introduction read "Here's your Your spouse's details".** Fixed.

## Second release, 28 September at about 16:26 (#951, release #952)

- **Your spouse's pension top-up is limited to what they earn from work.** Tax relief on a pension payment is limited to the person's earnings from work, or £3,600 a year if that is more (Finance Act 2004 sections 189 and 190). Fynla used their total income, so a spouse whose £9,000 came from rent or a pension was shown a £7,200 top-up. The spouse form now asks "Of that, earnings from work"; with no earnings, the top-up is £2,880 (£3,600 with the relief added).
- **The pension card for basic-rate taxpayers says why once.** "Why this matters" now opens with the saving ("Paying in £900 more this year saves £180 of income tax"), and the summary above no longer repeats it. A step that said "claim the rest as below", with nothing below it, is fixed.
- **Fynla only asks for your past pension payments when they could matter.** They are used to carry forward unused allowance from earlier years, which only helps someone who earns more than this year's pension allowance and has the savings to pay more than it. Everyone else no longer sees "Unlock pension info".

## Third release, about 17:00 (#954, release #955): the help page

- **The help page is rewritten to match the app as it is today.** It was describing screens that no longer exist ("Gap Analysis", tabs, Quick Actions) and got several facts wrong. Now:
  - Inheritance Tax on worldwide assets depends on long-term UK residence (at least 10 of the previous 20 tax years) since 6 April 2025, not domicile;
  - gifts into trusts are taxed only on the part above the tax-free allowance;
  - linking a spouse is an invitation, and sharing is one switch either of you can turn off;
  - the emergency fund aims for 6 months of spending if employed, 9 if self-employed and 3 if retired;
  - the made-up support hours and the "demonstration system" wording are gone.
- **New sections** cover the Actions page, Tax Strategy, Settings, Plans, Goals and What If, and using Fynla on your phone.
- **New: paying more in alongside a defined benefit pension**, explaining buying extra pension and additional voluntary contributions (AVCs).
- **Every figure comes from Fynla's tax settings** and every rule links to its source on GOV.UK, HMRC or The Pensions Regulator.

## Fourth release, about 17:52 (#957, release #958)

- **Pension action cards link to the help on paying more in.** Anyone with a defined benefit pension sees "Find out more: Paying more in alongside a defined benefit pension" on the four pension actions, which opens that section of the help page.
- **Three Estate cards that did nothing now open their screens.** Gifting opens the gifting strategy, where gifts can be recorded again; Life Policy opens the life policy options; Trust opens Trusts. Each has a way back to Estate.
- **The small gift allowance on the Gifting card comes from the tax settings.** It was typed in.
- **The gifting and life policy screens spell out Inheritance Tax** and use British spelling.

## Decisions taken

- **How-to steps follow the user's records and use their figures,** with the outcome shown on every action.
- **"Harvest" is banned in anything a user reads.**
- **The salary sacrifice cap date is 6 April 2029.**
- **Marriage Allowance waits for the spouse's actual income,** in the same way other actions wait for missing details.
- **Scottish income tax:** a caveat line on Marriage Allowance for now.

## Still to do

- **Scottish income tax rates** are not yet applied anywhere in Fynla; this is its own piece of work.
- **Fix Fyn's "Ask Fyn about this" explanation** of the pension figure.
- **Make an intermittent iPhone app test reliable.**
- **Write the "how to" guides for the savings, protection, retirement, investment and estate actions.**

## Behind the scenes

- **One source for every card.** The card's "why", "how" and "what changes" all come from one reviewed file per module. The web, mobile web and iPhone app all show what the server sends.
- **No tax figure is typed into the code:** strategies that still had fallback figures (the Capital Gains Tax allowance, ISA allowances, Lifetime ISA ages and bonus) now read the tax settings only.
- **The how-to file is protected:** loading it now fails loudly if a heading doesn't match an action. Before, a renamed heading was silently skipped.
- **At release,** the tax settings, the action definitions and the how-to guides must be reloaded on the server.

## What we checked

- **Live on fynla.org, 28 September, with a new account** (married, earning £45,000, a workplace pension paying 5% with 3% from the employer, a Stocks and Shares ISA, spouse's income not known at first):
  - **Desktop web, full-size window:**
    - Setup through Fyn took the user to each page it asked about ("does it look right?"), with the chat beside the page.
    - The actions page showed "Before 5 April" with 189 days left, "Worth doing soon", and "Waiting on you". The waiting list had no ISA, investment or charitable giving items, because those had been answered.
    - The pension card showed the user's own figures: Income Tax falls from £6,036 to £5,856, £150 a month for the 6 months left, and £720 after tax relief.
    - "Unlock spouse's income info" → "Add it now" opened the spouse's details form. Saving £9,000 went through.
  - **Mobile web:**
    - The rows for these allowances said "Closes 5 April".
    - "Claim Marriage Allowance: you could save £252" appeared after the spouse's income was saved, and its card showed the approved steps (£1,260 transferred, £9,000 below the £12,570 Personal Allowance).
- **On the test site first,** the same checks passed with two new accounts, the chat was checked on a narrow window as well, and "Add it now" was checked on the mobile web app too.
- **The automated checks all passed** on the first release's final update, including the iPhone app's tests. The second release went out at CSJ's call while its automated checks were still running, after the test site walk and every changed test file had passed.
- **Second release, live on fynla.org with a new account:** the spouse step in setup asked "Of that, earnings from work"; with none given the top-up was £2,880 (£3,600 with relief); after saving £9,000 of earnings it was £7,200 (£9,000) and the card said "earns £9,000 from work". The pension card's "why" read "Paying in £4,500 more this year saves £900 of income tax", and "Unlock pension info" was gone.
- **Not tested on screen:**
  - the reworded investment tax pages, which only appear for accounts with holdings; the wording was checked in the code instead;
  - the iPhone app.
- **Third and fourth releases, live on fynla.org:**
  - the help page loaded with every section; the AVC section, the £60,000 annual allowance and the 10 of 20 years residence rule showed from the tax settings; none of the old wording remained;
  - as a demo household, the Gifting and Life Policy cards opened their screens and returned to Estate; a defined benefit member's pension action showed the help link, and it opened the AVC section.
- **Automated checks for the later releases:** the third and fourth releases also went out while their checks were running. Two checks failed after the second release (stored copies of Fyn's tool list, and code formatting); both were fixed in #956, and the fourth release's checks all passed. Nothing users see was affected.
- **Not tested on fynla.org:** the help link on the phone version (tested on the test site), and the Trust card, which only shows for estates over £2 million.
