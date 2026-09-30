# What's new in Fynla — 29 September 2026

Today's work fills in the "how to" guides for savings and protection, rebuilds the protection cards so they come from one reviewed list and say plainly how much cover you are short, and fixes a long list of things found when real people used the Save Tax journey on their phones. Fyn now answers "Ask Fyn about this" from the card you asked about, and never tells you how sure it is.

**This is live on fynla.org** from 29 September 2026, through five releases:

- about 10:41, release #966 (savings guides and Fyn), with a fix at about 10:51 (#968);
- about 14:46, release #994 (protection), with its last three guides at about 14:55 (#996);
- about 20:25, release #1008 (Save Tax, spouse income, onboarding and Fyn fixes).

Each was walked on the test site first, then on fynla.org itself.

## Savings actions explain how to do them

- **Every savings action now has approved "how to" steps:** 26 guides covering 41 actions, each checked and approved before release. The steps use the figures on your own card.
- **A £0 Personal Savings Allowance is no longer called "nearly used"** when you earn no interest.
- **Rates you are compared against name their source:** the provider and date from MoneySavingExpert.
- **A child's Junior ISA is no longer read as your Cash ISA.** On the live site, a family's steps said "your Cash ISA with Vanguard" when it was the child's Junior ISA. Fixed the same morning.
- **The actions list no longer shows internal labels** such as "Lifecycle" or "Warning" as a topic.

## Protection shows the cover you are short of

- **One card per type of cover:** life insurance, critical illness and income protection. Each card says how much cover you have, how much you need and the gap. The separate "gap" and "reliance" cards are folded into these as reasons.
- **A new "Your cover" section on the Protection page,** on the desktop and mobile web apps, shows the same three figures.
- **The need is worked out properly.** It no longer uses a fixed three times income or 70% of take-home pay, and your life cover gap no longer subtracts critical illness cover, which pays out on diagnosis, not death.
- **You can record your employer's benefits** (death in service, income protection, critical illness cover or private medical insurance) on the Protection page, on mobile, or by telling Fyn. "None" now counts as an answer, so you are not asked again.
- **Statutory Sick Pay is worked out for each person** from the tax settings: the weekly rate or 80% of normal weekly earnings, whichever is lower, with no lower earnings limit from the 2026/27 tax year (gov.uk/statutory-sick-pay/what-youll-get).
- **29 protection guides are approved and live,** including the three for the new cover cards.
- **The desktop Protection page now shows the same gaps as the mobile app.** It used to work them out in the browser with its own figures.

## Save Tax does what it promises

- **The funnel promises only what the plan delivers.** For a partner who doesn't earn, it now says "pay £2,880 into a personal pension and HMRC adds £720", and the plan says the same (gov.uk/tax-on-your-private-pension/pension-tax-relief).
- **Someone with no income** is greeted properly, and the pension suggestion is capped at what they have in savings, because that is where the payment would come from.
- **A partner in the 60% band is priced by whether they earn,** with a new question about the partner's work on the Save Tax page.
- **Scottish taxpayers are told the funnel uses the rest-of-UK rates,** and a Scottish recipient of Marriage Allowance is warned.
- **Pension first:** the plan prices a pension contribution before the savings alternatives, and labels the alternatives as alternatives on every screen.
- **Invented reviews and member counts are removed** from the Pension Check and Save Tax plan pages.

## A spouse's details are used, not asked twice

- **An invited spouse's own job replaces the income their partner entered.** Before, the two were added together (one live account showed £64,000 instead of £32,000; it was repaired the same evening).
- **A linked partner's own records answer the partner questions,** so an invited spouse isn't asked their partner's earnings band again.
- **A partner's earnings are carried across as pay, and the rest as other income,** so a partner living on a pension isn't treated as an employee.

## Fyn

- **"Ask Fyn about this" is answered from the card itself,** with the card's own figures.
- **Fyn never states how certain it is.** A filter removes any sentence claiming certainty from Fyn's replies, as they stream and as they are stored.
- **If a reply is cut off, Fyn says so and offers "Try again".** Trying again never takes the same turn twice.
- **On mobile, the conversation is kept while you are signed in,** and the dashboard no longer reopens Fyn by itself.
- **On the "Which of these do you have?" step, you tap each account, then one button sends them all.**
- **The desktop Fyn panel starts collapsed on every visit.**

## Other fixes

- **Signing out of the mobile app also signs out the desktop app shown inside it.**
- **/m/savetax opens the campaign inside the mobile app** instead of a missing page.
- **People born in England, Scotland, Wales or Northern Ireland can save Personal Info again.**
- **A refused save keeps your form,** and residence for Inheritance Tax is described in plain words.
- **The mobile screen behind Fyn refreshes after Fyn saves a record.**

## Decisions taken

- **Fyn never states certainty.** This is enforced on the output, not only in Fyn's instructions.
- **The pension contribution is priced first,** and the sheltered-interest pricing it made pointless is removed.
- **The £720 relief for a non-earner is counted and worded,** and a no-income arrival's pension is capped at their savings.
- **The details a partner gives are transferred and stored** (ruling 50, restated).

## Still to do

- **The spouse's own onboarding still asks again for the savings, ISA, investments and pension their partner already gave.** It is fixed in the next update.
- **The date of birth moved back a day on each desktop Personal Info save.** It was found on the evening walk and is fixed in the next update.
- **Retirement, investment and estate "how to" guides.**

## Behind the scenes

- **Protection cards come from one reviewed list of actions,** as tax and savings do. The old seven-rule engine no longer feeds the cards.
- **Protection cover is one calculation,** read by the plan, the cards and both apps.
- **Try again uses the same safeguard the iPhone app already had** (an idempotency key), so a repeated request is recognised.
- **At release:** one database change (whether an employee's income is an estimate), the protection and how-to lists reloaded, and a repair of the one spouse income that had been added twice.

## What we checked

- **Live on fynla.org, morning release,** as the Carter demo household on the desktop web app: the emergency fund card; "Consider a Cash ISA" with the ISA-left, spouse and children steps, and no Junior ISA read as the parent's; the actions list with no internal labels.
- **Live on fynla.org, protection release,** as the Carter household: "Your cover" showed life cover £131,953 short, critical illness £225,000 short and income protection £3,750 a month short, and the cards matched the page.
- **Live on fynla.org, mobile, with a new account:** life cover £872,551 short, then £632,551 after four times salary death-in-service was saved through Fyn; critical illness £180,000 short; income protection £3,000 a month short. The account was deleted afterwards.
- **Live on fynla.org, evening release,** on the desktop (full-size window) and mobile web apps with a new account: no invented reviews on the plan pages; "Your cover" agreeing with the coverage gaps; Fyn opening on sign-up, staying open between pages, and starting collapsed on a new visit; the partner-work screen showing "3 of 4"; Personal Info saving with England and Scotland as country of birth; "Try again" replacing a cut-off reply.
- **On the test site first,** every change was walked on its own branch on the desktop and mobile web apps before it was merged.
- **Not tested on fynla.org:** the "HMRC adds £720" wording for a partner who doesn't earn (tested on the test site), and the iPhone app.
