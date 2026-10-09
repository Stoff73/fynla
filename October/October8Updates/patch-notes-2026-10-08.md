# What's new in Fynla — 8 October 2026

Spare cash now leads to the right next step. If you hold a General Investment Account or an investment bond, or your ISA and pension allowances are used, Fynla suggests where the cash above your emergency fund could go, with "How to do it" steps for a General Investment Account, an investment bond and an offset mortgage. And a pension top-up for a partner who does not earn now says what it is: "HMRC adds £720", not "You could save £720".

**This is live on fynla.org** through three releases on the morning of 8 October:

- **About 08:30:** release #1132 (#1130 and #1131: spare cash suggestions and their steps).
- **About 09:15:** release #1135 (#1134: "HMRC adds £720" on the action row and card).
- **About 09:40:** release #1139 (#1138: no savings rate comparison without a stored market rate).
- **About 10:20 and 10:55:** releases #1142 and #1144 (#1141 and #1143: Fyn explains how a plan figure was worked out).

All were walked on the test site first, on the desktop web app (full-size window) and the mobile web app, and then checked on fynla.org.

## Spare cash: General Investment Account and bond suggestions reach the right people, with steps (8 October, about 08:30, release #1132)

**This is live on fynla.org** through release #1132 (#1130 and #1131), UK time.

- **Cash above your emergency fund now leads to a General Investment Account or an investment bond when that fits.** Before, these two suggestions could never show: the pension suggestion took every case they were meant for. Now, if you hold a General Investment Account or a bond, you see that suggestion, alongside the ISA or pension one if those apply. If you do not, you see it only once your ISA allowance for the year is used and no further pension payment would get tax relief this year. Either way, only with cash above your emergency fund target. (CSJ, 8 October: "if a person has entered a gia or bond account, these cards need to show, if a person has not, then they only show if they have used their allowances up".)
- **The pension suggestion for spare cash checks it can work.** It now shows only when your ISA allowance is used, a pension payment would still get relief this year (within the Annual Allowance and your earnings, [Finance Act 2004 s190](https://www.legislation.gov.uk/ukpga/2004/12/section/190)), and you are under 75 ([s188(3)(a)](https://www.legislation.gov.uk/ukpga/2004/12/section/188)). Its amount is no more than still gets relief.
- **Your spare cash is measured against your own emergency fund target:** 3 months of spending if you are retired, 9 if self-employed, 6 otherwise. These suggestions used 6 months for everyone. The offset mortgage suggestion also counted every account not marked as emergency fund as spare; it now uses the same measure, so it agrees with the others.
- **Three suggestions now have "How to do it" steps,** each from named sources (approved by CSJ, 8 October).
- **An offset mortgage:** ask your lender about one (interest is charged on the mortgage less your savings, [HMRC BIM45695](https://www.gov.uk/hmrc-internal-manuals/business-income-manual/bim45695)); check the early repayment charge on your mortgage statement ([FCA MCOB 7.5.3R and 12.3.1R](https://www.handbook.fca.org.uk/handbook/MCOB/12/3.html)); or overpay from savings above your emergency fund.
- **An investment bond:** what it is ([HMRC IPTM2005](https://www.gov.uk/hmrc-internal-manuals/insurance-policyholder-taxation-manual/iptm2005)), when gains are taxed, the one-twentieth (5%) yearly allowance, basic-rate tax treated as paid on UK bonds only, and top slicing relief ([HMRC helpsheets HS320](https://www.gov.uk/government/publications/gains-on-uk-life-insurance-policies-hs320-self-assessment-helpsheet/hs320-gains-on-uk-life-insurance-policies-2026) and [HS321](https://www.gov.uk/government/publications/gains-on-foreign-life-insurance-policies-hs321-self-assessment-helpsheet/hs321-gains-on-foreign-life-insurance-policies-2026)).
- **A General Investment Account:** add to the one you hold or compare charges before opening one; dividends above the £500 dividend allowance and gains above the £3,000 Capital Gains Tax allowance are taxed, both from the tax configuration ([gov.uk](https://www.gov.uk/tax-on-dividends)); the Financial Services Compensation Scheme covers a firm failing, not a fall in value ([FSCS](https://www.fscs.org.uk/what-we-cover/investments/)).

**What we checked.**
- **On the test site, desktop (full-size window) and mobile web apps:** the Mitchell demo (holds a General Investment Account, ISA allowance left) sees "Consider a General Investment Account" beside "Use Your ISA Allowance for Excess Savings". The Alex Chen demo (holds an AJ Bell General Investment Account, no bond, ISA allowance used, £76,995 paid into pensions against a £60,000 allowance, so no relief room) sees all three suggestions with their steps.
- **Live on fynla.org, as the Alex Chen demo, desktop and mobile:** the offset mortgage ("Your mortgage costs 4.49% a year. Your savings earn 2.21% on average… You hold £36,763 in savings above your emergency fund target"), the bond ("You hold £169,180 in cash, £36,763 of it above your emergency fund target", ending "Before you buy, ask the provider for the bond's charges") and the General Investment Account ("You hold £36,763 in cash above your emergency fund target of £132,417", "You can add to your General Investment Account with AJ Bell", £500 and £3,000). No errors in the log.

**Still to do:**
- **The General Investment Account suggestion's own text** says "Open a general investment account" even to someone who holds one; its steps say "add to".
- **The dashboard action row "You could save £720"** beside the partner top-up will read "HMRC adds £720" (CSJ, 8 October); done in release #1135, below.

**Behind the scenes.** One rule for how much a pension payment can still get relief, used by the Retirement plan and the savings suggestions. No database change. App code and the how-to steps (`ActionHowToSeeder`); no new web or mobile bundle.

## A partner's pension top-up says "HMRC adds £720" on the action row too (8 October, about 09:15, release #1135)

**This is live on fynla.org** through release #1135 (#1134), UK time.

- **The action row for a partner's pension top-up reads "HMRC adds £720",** not "You could save £720", and the card's figure box reads "HMRC adds £720 a year", not "Saves about £720 a year". The £720 is basic-rate relief the pension provider claims from HMRC and adds to the pot ([Finance Act 2004 s192](https://www.legislation.gov.uk/ukpga/2004/12/section/192)), not tax you save. (CSJ, 8 October: "if it is pensions, then yes".)
- **The same for your own pension top-up when you have no earnings** and nothing is claimed back through Self Assessment. Where part is claimed back, the row still says "You could save", because the benefit is more than HMRC adds.
- The row and the figure box are written once on the server, so the desktop and mobile web apps and the iPhone app read the same words.

**What we checked.**
- **On the test site, desktop and mobile web apps:** a walk household whose partner does not earn: the row "HMRC adds £720" beside "You could save £120" on an ISA row, and the card "HMRC adds £720 a year".
- **Live on fynla.org:** a walk account made through Save Tax (earning £45,000, a partner with no income, £2,000 a month spending). Its plan: "Pay £4,500 more into your pension and save £900 in tax" (row "You could save £900") and "Pay £2,880 into your spouse's personal pension and HMRC adds £720" (row "HMRC adds £720", card "HMRC adds £720 a year"), on the desktop and mobile web apps. The account was deleted afterwards. No errors in the log.

**Behind the scenes.** App code only.

## No savings rate comparison without a stored market rate (8 October, about 09:40, release #1139)

**This is live on fynla.org** through release #1139 (#1138), UK time.

- **A savings account is compared only against a stored best-buy rate.** When none was stored, every account was compared against a typed-in 4.00%, and a "Better rate available" suggestion could appear from it. Now, with no stored rate, nothing is compared and no such suggestion appears.
- **"Show how we worked this out" on an account earning no interest** priced the lost interest at "an illustrative 4.0% easy-access rate". It now uses the stored rate with its provider and date, for example "At 5.00% (Cahoot (part of Santander) Sunny Day Saver Easy Access, 1 October 2026), this balance could earn approximately £1,250/year", or gives no figure when none is stored.
- fynla.org holds the best-buy rates taken on 1 October 2026 (MoneySavingExpert's tables), so today's suggestions do not change.

**What we checked.**
- **On the test site and live on fynla.org, as the Mitchell demo, desktop and mobile web apps:** "Better Rate Available for David's Cash ISA" still reads 4.25% against 5.01% from Trading 212 on 1 October 2026, about £171 a year; on the desktop Savings page, "Show how we worked this out" for David's Current Account (£25,000 at 0%) quotes the stored 5.00% rate. No errors in the log.

**Still to do:**
- **The Investment page's "switch to a better savings rate" suggestion can never show:** it looks for a rating the savings comparison does not send.

**Behind the scenes.** App code only. Each account is now compared once per analysis, not twice.

## Fyn explains how a plan figure was worked out, from the plan's own working (8 October, about 10:20 and 10:55, releases #1142 and #1144)

**This is live on fynla.org** through releases #1142 (#1141) and #1144 (#1143), UK time.

- **The pension suggestion carries its working,** and Fyn gives it rather than doing its own sums. Before, asked to talk through a plan, Fyn worked "£60,000 − £50,270 = £9,730 taxed at 40%", while the plan's £3,700 starts from income after the pension paid through pay. Now: "Your income this year is £60,000. £6,000 of it goes into your pension from your pay before tax, which leaves £54,000 taxed as income. The higher rate starts at £50,270. So £3,730 of your income is taxed at 40%. Rounded down to the nearest £100 that is £3,700, and 40% of £3,700 is £1,480 of tax saved."
- **A question about how one of your figures was reached is answered from that action,** for example "How did you work out the £3,700 pension figure?". Before, such a question went unanswered from the plan, and Fyn once gave a made-up meaning.
- **A question asked again after a reply failed** is treated as a first ask. Before, Fyn said "I answered that a moment ago" when it had not.

**What we checked.**
- **On the test site and live on fynla.org, desktop and mobile web apps:** walk accounts made through Save Tax (earning £60,000, 10% into a workplace pension through pay, £2,000 a month spending), plan "Pay £3,700 more into your pension and save £1,480 in tax". Both apps gave the working above. The accounts were deleted afterwards. No new errors in the log.

**Still to do:**
- **On the desktop app Fyn can still say the working is "shown on the card".** It is not shown there. Next.
- **The salary sacrifice suggestion carries no working yet;** Fyn said the switch comes "with no change to your take-home pay", where the National Insurance saving raises it.
- **The retirement plan fails for someone whose pension holds funds charging above 0.5%** ("Undefined variable", from a change released on 2 October). Next after the line above.

**Behind the scenes.** App code only.


## Fyn on GPT-6 Luna, joint records for both owners, and figures that agree (9 October, about 07:15, release #1162)

**This is live on fynla.org** through release #1162, UK time.

- **Fyn now runs on OpenAI's GPT-6 Luna.** It can be chosen in the admin panel beside the existing providers. Every call tells OpenAI not to keep the conversation.
- **Both owners of a joint record can change or remove it.** This covers bank and savings accounts, investments, property, mortgages, liabilities, goals, valuables and business interests, on the desktop and mobile web apps and through Fyn. The record stays the first owner's. A partner who adds an account the other has already added is asked whether it is the same one, rather than getting a second copy.
- **Fyn's edit form saves only what you changed.** Before, saving it unchanged could rename an account (for example "Premium Bonds" to "NS&I easy access savings") or change its type.
- **Your past pension contributions are asked for only when they could matter.** That means when you could fill this year's pension allowance and your whole ISA allowance and still have money left over: more than £80,000 of spare cash at today's allowances. Before, Fyn could tell someone earning £26,000 that "the plan needs your pension contribution history".
- **The Personal Savings Allowance suggestions use your own share of a joint account's interest.** Before, the first owner was told "Your estimated annual savings interest of £960 exceeds your £500 Personal Savings Allowance" for an account whose interest is split £480 each. The partner was counted as having none.
- **The monthly amount left over counts your pension payments once.** Before, payments into a pension from take-home pay, protection premiums and regular saving were taken off twice. One user was shown a shortfall of £266.70 where the right figure is £136.70. Fyn now quotes the plan's figure rather than working it out itself.
- **Clearer wording in the pension working:** it names only what raised the higher-rate limit, for example "raised to £50,870 by your £600 Gift Aid".
- **Clearer wording on a possible duplicate account:** the question no longer starts "I couldn't save".
- **Clearer wording on shared spending:** a shared household's spending is acknowledged as "your half of the £3,800 your household spends".
- **Clearer suggested questions on the mobile web app:** they read "Tell me more about: …" instead of `How do I "You have no will recorded"?`.
- **A partner's earnings now sit inside their income on the partner form.** Before, it could show income of £79,440 with £84,000 of it from work.

**What we checked.**
- **On the test site, desktop and mobile web apps:** a Save Tax walk household (earning £84,000 and £26,000, a joint £24,000 account), with the partner invited, accepted and set up. Each change above was checked against figures worked out by hand.
- **Live on fynla.org, mobile web app, as the John Morgan demo:** the suggested questions read "Tell me more about: You have no will recorded"; "Why is there no pension recommendation for me?" was answered with no mention of past contributions; "What monthly surplus does my plan show for me?" gave £206.76, the plan's own figure.
- **Live on fynla.org, desktop web app:** a walk account made through Save Tax (earning £84,000). "What pension details will you need from me?" asked for no past contributions. The account was deleted afterwards.
- On fynla.org every answer came from GPT-6 Luna. No errors in the log.

**Still to do:**
- **After saving an account that matches one you already have,** answering "It's the same one" now carries on correctly, but Fyn can still try to add it again and ask for an ownership share. Nothing is saved twice.
- **Joint-owner edits of valuables, business interests and investment holdings now refresh both owners' figures.** This could not be seen on the test household, which holds none of those.

**Behind the scenes.** App code, two settings files, one of Fyn's knowledge files, and the desktop and mobile web app files. No database change.
