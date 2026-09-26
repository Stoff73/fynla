# What's new in Fynla: Save Tax accuracy (live on fynla.org, 26 September 2026)

This update makes the Save Tax plan accurate. A suggestion now appears only when you qualify for it, the "you could save" total counts only real tax savings and counts each one once, and every rule comes from its official source.

**Status:**
- Released to fynla.org on 26 September 2026 (release #942, `main` `b81d5fcc2`).
- Before release it was walked through as real users on the desktop web app, the mobile web app and Fyn's chat, on a local copy and on the test site. After release it was walked again on fynla.org, on web and mobile web: the same plan and the same £3,828 total for the same answers.
- The iPhone app gets the same plan from the same place, but it has not been walked through.

## Your Save Tax plan

- **Pension tax relief is suggested for every tax band.** Before, only people near the 60% or 45% rates saw a pension suggestion.
  - **Basic-rate taxpayers** are shown paying in a tenth of their earnings, less what they already pay.
  - **Higher-rate taxpayers** are shown the slice of income taxed at 40%.
  - **What the suggestion accounts for:** it counts what you already pay in, and it stops short of any savings interest another suggestion already moves out of tax.
  - **The upper limit:** it is never rounded up past the tax you pay.
  - **The age limit:** it stops at 75, when pension tax relief ends ([Finance Act 2004 s188](https://www.legislation.gov.uk/ukpga/2004/12/section/188)).
- **Salary sacrifice is only suggested against a workplace pension.** It can't apply to a personal pension or SIPP. It is now priced from the percentages you give us, so it appears for people who set up their pension in the chat.
- **Marriage Allowance follows the law exactly** ([Income Tax Act 2007, Part 3 Chapter 3A](https://www.legislation.gov.uk/ukpga/2007/3/part/3/chapter/3A)):
  - it is only offered to married couples and civil partners;
  - the partner giving up the allowance must earn less than the Personal Allowance;
  - the saving is never more than the tax you actually pay;
  - it takes off any extra tax the other partner pays by giving up part of their allowance;
  - it now works when you are the lower earner, transferring to your partner;
  - it now works when your partner earns a little, not only when they earn nothing.
- **Your partner's pension top-up takes off what they already pay in,** and is only suggested to married couples and civil partners.
- **Advice about moving money or investments to your partner** only appears for married couples and civil partners. Some of it also needs to know your partner's income: for example, moving investments into their name depends on their tax band.
- **"You could save" counts only real tax savings, and each saving once.**
  - A Lifetime ISA bonus, a pension top-up for a child, unused Dividend Allowance and the tapered pension allowance warning still appear. They show no savings figure and aren't added to the total.
  - Wrapping savings in an ISA, gifting savings to your partner and sharing savings equally all shelter the same interest. Only the largest counts, and the others say which suggestion they are an alternative to, by its title.
  - Marriage Allowance takes its £1,260 out of your partner's allowance before any gift of savings uses the rest.

## The Tax Strategy page

- **Allowances we can't confirm are no longer shown as "Fully used".** They now read "Current-year use not confirmed", as the mobile app already did. This affects, for example, your partner's ISA or your Capital Gains Tax allowance when we don't know what has been used this year.
- **The page no longer says "Nothing to act on" or "well-utilised" when that isn't true.** Before, it could say both while showing a Marriage Allowance action, or while you still had unused allowances.
- **The pension allowance shows what you can actually use and afford.**
  - **If you earn less than the Annual Allowance,** it shows your earnings-based limit ([Finance Act 2004 s190](https://www.legislation.gov.uk/ukpga/2004/12/section/190)). Someone earning £8,000 sees £8,000, not £60,000.
  - **The headroom is limited to what the app's affordability check says you can fund this year,** meaning what's left after spending, regular commitments and your goals. When that limit applies, the page says so.
- **Statements about moving assets to your partner are now correct.** Transfers between married couples or civil partners who live together have no immediate Capital Gains Tax charge ([TCGA 1992 s58](https://www.legislation.gov.uk/ukpga/1992/12/section/58)), and the Inheritance Tax spouse exemption has conditions ([HMRC IHTM47030](https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm47030)). The old wording referred to "UK-domiciled spouses", which has not been the test since April 2025. The heading is no longer repeated in the first sentence.
- **Dates are written the British way:** "before 5 April".

## The Save Tax and Pension Check pages

- **Both pages ask "Do you have a spouse or civil partner?"** Fyn's summary of your answers says the same.
- **The Save Tax estimate no longer counts your partner's allowance twice** when Marriage Allowance is included.

## Help and guides

- **The ISA rule is corrected.** The Help page and two ISA guides said you could only pay into one ISA of each type a year. Since 6 April 2024 you can pay into more than one of the same type, except a Lifetime ISA or a Junior ISA ([The Individual Savings Account (Amendment) Regulations 2024](https://www.legislation.gov.uk/uksi/2024/350/made); [gov.uk: Who can invest in an ISA](https://www.gov.uk/guidance/who-can-invest-in-an-isa-if-youre-an-isa-manager)).
- **The Inheritance Tax answer is corrected and takes its figures from the current tax settings:**
  - the rate and the tax-free threshold;
  - the residence allowance for a home left to children or grandchildren, and how it reduces for estates over £2 million;
  - passing unused allowances to a surviving spouse or civil partner.
- **Linking your partner's account is described correctly.** We send them an invitation, and nothing is shared or linked until they accept.
- **Garbled characters on the Help page are gone.** The Help page showed "Protection module â†’ Policy Details"; it now uses plain words.

## Checked, still to do

These are the next pieces of work:

1. **Pension contributions entered in the chat are not yet counted in your "adjusted net income" everywhere in the app.** The Save Tax plan already allows for them. The next update fixes it at the source, which affects the Personal Allowance taper, and other modules.
2. **The Help pages need a full review.** Some sections describe screens that have since changed, and some still describe Inheritance Tax in terms of domicile rather than long-term UK residence. We are also checking whether the Estate module uses the current long-term residence rule.
3. **Scottish income tax is not supported yet.** Scottish taxpayers' figures use UK rates. This is a separate piece of work.

## Decisions taken

- **Your partner's pension top-up counts as a tax saving** (a spouse is a legal contract). A child's pension top-up and a Lifetime ISA bonus do not.
- **The basic-rate pension suggestion is sized at a tenth of earnings,** matching what the Save Tax page promises.
- **Dated insight articles keep the figures for their date.**
- **The pension allowance tile uses the app's affordability check,** so it shows what you can actually use.
