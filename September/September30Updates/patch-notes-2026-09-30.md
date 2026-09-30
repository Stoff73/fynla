# What's new in Fynla — 30 September 2026

Today's work stops your date of birth moving back a day each time you saved it, makes sure a question you type during setup gets an answer instead of being taken as a tick, and cleans both servers so they hold only what the site needs to run.

**This is live on fynla.org** from 30 September 2026 at about 08:18, through release pull request #1012 (updates #1009, #1010 and #1011). It was walked on the test site first, then on fynla.org itself on the desktop and mobile web apps, with a new account registered for the purpose.

## Your date of birth stays put

- **Saving Personal Info no longer moves your date of birth back a day.** On the desktop web app, every save of a summer birthday stored the day before: 1 May became 30 April, then 29 April. The date is now stored exactly as you enter it.
- **Dates read the same wherever you are.** Someone outside UK time could see, and save back, the day before. Dates of birth now show the same calendar day in every time zone.
- **The mobile app shows your date of birth in words,** "1 May 1985", as the desktop does. It used to show 1985-05-01.
- **Employment status shows "Full-Time",** not the internal code.
- **The partner card in the 60% band reads "reclaims all but £20 of their Personal Allowance"** when a whole-pound contribution cannot reclaim all of it (Income Tax Act 2007 s35).

## A question during setup gets an answer

- **Asking a question at "Which of these do you have?" no longer ticks an account.** Typing "What is the ISA allowance this year?" ticked ISA, and the question was never answered. Fyn now answers it ("The ISA allowance for the 2026/27 tax year is £20,000 per person"), asks the same question again, and records nothing until you tap.
- **The same applies to every setup question with buttons.** "Does my partner count if we are not married?" used to be recorded as "No", because "not" contains "no". It is now answered, and the question is asked again.
- **This is one change on the server,** so the desktop web app, the mobile web app and the iPhone app all get it.

## The servers hold only what the site needs

- **Both servers are cleaned.** The test server held the whole project: planning notes, handovers, documentation, tests, the iPhone app's source and tooling folders. fynla.org held leftovers from early uploads. Both now hold only the files the site runs on. The test server went from 3.3 GB to 960 MB, fynla.org from 690 MB to 633 MB.
- **Design mockups are no longer public.** fynla.org was serving old design mockup pages to anyone who knew the address. They are gone from the site and kept with the design documents.
- **One list decides what a server may hold,** and every entry says which part of the app reads it. The test server now checks out only those files, so they cannot come back.

## Decisions taken

- **Servers hold runtime files only** (CSJ, 30 September).
- **A question typed at a setup step is answered and never taken as a choice;** only tapping the button (or its exact label) counts.
- **The fix for dates is at the source,** in how the date is stored, so every screen gets it.

## Still to do

- **Dates of birth that already slipped:** one account shows the pattern in the audit log and can be put back once agreed. Dates entered for the first time on the desktop cannot be traced.
- **The spouse's own setup confirming what their partner gave:** fixed and walked on the test site, and in the next update.
- **Retirement, investment and estate "how to" guides.**

## Behind the scenes

- **The date is stored as a plain date** (year, month, day), and the desktop no longer converts it through a timestamp before sending it.
- **A second check in setup:** a message that looks like a question is sent to Fyn to answer unless it is exactly one of the buttons.
- **`deploy/server-runtime-paths.txt`** is the keep-list. Every release upload must fall inside it.
- **At release:** app code and both web bundles only; no database changes.

## What we checked

- **Live on fynla.org with a new account, desktop web app (full-size window):**
  - At "Do you have a spouse or civil partner?", typing "Does my partner count if we are not married?" was answered, and the question was asked again with nothing recorded.
  - At "Which of these do you have?", typing "What is the ISA allowance this year?" was answered with £20,000, and the question was asked again with nothing ticked.
  - Personal Info: 1 May 1985 entered for the first time, then saved again unchanged. It stayed 1 May 1985, and read so after reloading the page.
- **Live on fynla.org, mobile web app, same account:** the ISA question was answered and the step asked again with nothing ticked; Personal Information read "1 May 1985". The account was deleted afterwards.
- **Live on fynla.org:** the mockup pages no longer load, and the site, mobile app, Pension Check and Save Tax plan pages all load.
- **On the test site first,** on the desktop and mobile web apps: two saves of 1 May 1985 and of 15 July 1990 held the date; the trap card wording; the question at both kinds of setup step, and a real tap still recording the account.
- **Not tested:** the iPhone app.
