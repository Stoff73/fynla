# What's new in Fynla — 5 and 6 October 2026

This covers the last three working sessions: two on 5 October and one on the morning of 6 October. Together they finish item 7a, "one figure, every surface", which was about making every screen and Fyn read the same thing. Every change you type to Fyn now goes through a form. In the Save Tax setup, "No thanks" no longer ends everything. In a demo, "Mark as done" now sticks. And the Holistic Plan lists the same actions as your actions list.

**All of it is live on fynla.org** through release #1094, at about 09:09 UK time on 6 October. It was walked on the test site first, on the desktop web app (full-size window) and the mobile web app, and then checked on fynla.org (see "What we checked").

## Changes you type to Fyn go through one form

*5 October, session 4 (#1084), released in #1094 together with session 3's #1082.*

- **Every change you type to Fyn opens its form with your change filled in.** Nothing is saved until you press Save. This covers:
  - a change said in any words;
  - an answer typed on a setup step;
  - a request to add something, which opens the blank form for it, filled in from what you said. The "Add" buttons open the same form.
- **When a change could mean more than one record,** Fyn asks "Which one needs changing?". The record you tap opens with your change already filled in.
- **On the desktop app, tapping a record under "Which one needs changing?" now opens it.** Before, nothing happened. The desktop app kept its own list of tappable actions, and that list left these out. It now uses the server's.
- **"I want to change my savings details" now opens your savings.** Before, Fyn did not recognise it as a change to a record.
- **The "Date of birth is required" prompt now asks for your date of birth.** Before, it asked Fyn for your pension details.
- **Behind the scenes:** session 3 had added four separate routes from typed words to a form, beside two that already existed. There is now one route (`OnboardingChatDirector::offerTypedForm`). There is also one way to draw a form, one error reply and one list of which form belongs to which record. Fyn now reads each message once, where some were read twice before.

## Save Tax: "No thanks" skips only the accounts and pensions

*5 October, session 4 (#1085), released in #1094. Your decision: "choice a is good".*

- **Before:** on Save Tax, answering "No thanks" to "Can I ask about your bank and savings accounts and pensions?" ended the whole setup. That had three effects:
  - your date of birth, gender and spending were never asked;
  - every section then sat behind "Date of birth is required";
  - the plan could suggest a pension top-up without knowing what you could afford.
- **Now** "No thanks" skips only the accounts and pensions the question named. Fyn carries on with your date of birth and gender, your partner where there is one, and your monthly spending, then gives the plan.
- **The closing lines say "Save Tax".** Before, they used the internal name: "Your savetax dashboard" and "Your savetax module is ready to explore".

## Demo households: "Mark as done" is kept for your visit

*5 October, session 5 (#1087), released in #1094.*

- **Before:** in a demo, "Mark as done" seemed to work, but the action came back on the next page load.
- **Now** an action you mark done stays done for the rest of your visit, on the desktop and mobile apps and after a reload. Another visitor to the same demo household starts with every action open. The demo household itself earns no points or levels from visitors.
- When the mobile app swaps your sign-in on start-up, your done actions come with it.

## The Holistic Plan follows your actions list

*5 October, session 5 (#1090), released in #1094.*

- **Before:** the Holistic Plan ranked its items by the pounds each one saves. Three medium-priority tax items then sat above every high-priority action, and actions you had marked done stayed on it.
- **Now** it lists the actions still open on your actions list, in the same order. Goals and items waiting on more information keep their own sections on the page. The desktop app, the mobile app and Fyn's cross-module plan all read this one list.

## Marriage Allowance was not reworked

*5 October, session 5.*

A list line said that nothing records a Marriage Allowance transfer. A session started rebuilding it (#1089). You confirmed that Marriage Allowance was done, tested, approved and released on 1 October (#1031, release #1032). #1089 was closed unmerged and the line struck from the list. Nothing about Marriage Allowance changed in these sessions.

## Tests only

*5 and 6 October (#1088, #1092).*

- **#1088:** the test tax settings stored rates as whole numbers (20, 18, 8.75), while the real tax settings store fractions (0.20, 0.18, 0.0875). The test settings now store fractions too. Nothing users see changed.
- **#1092:** one automatic check had failed on every change since 5 October. It still expected an old way of saving a typed spending total, which had been deliberately replaced by the shared one the desktop form uses. The check now tests the shared way. Nothing users see changed.

## What we checked

- **On the test site,** before each change was merged:
  - Typed changes and adds through their forms, on the desktop app (full-size window) and the mobile app. A savings balance was changed through its form, a bank account and an investment account were added, and "Which one needs changing?" opened the record tapped.
  - "No thanks" on Save Tax, on both apps. Fyn then asked "Next, your date of birth and your gender.", then spending, then gave the plan. "savetax" appeared nowhere in the conversation.
  - "Mark as done" in the Mitchell demo:
    - desktop: one action marked from the list and one from the action's own page, both kept after a reload;
    - a second visitor saw all 35 actions open;
    - mobile: an action marked by a new visitor was kept after a reload.
  - The Holistic Plan: the same 31 items in the actions list's order on both apps.
- **Live on fynla.org, as the Mitchell demo:**
  - Desktop: "Bed & ISA" marked done and kept after a reload (34 open, 1 done).
  - The Holistic Plan: 29 items in the actions list's order, with "Bed & ISA" gone.
  - Mobile, same visit: "Bed & ISA" shown as done. "Consider a joint life policy" marked on mobile was kept after a reload (33 open, 2 done).
  - Both completions belong to that visit only, and the demo household gained no points. The server logged no errors.
- **Not repeated on fynla.org:** the second-visitor check, which was done on the test site.

## Still to do

These are now their own items on the list (38 to 41):

- **Demo households other than the Bennetts open the desktop dashboard blurred.** They are set up as not having finished setup, so the dashboard tries to start Fyn's setup, which a demo cannot do. The blur stays until the chat is touched.
- **A blank investment form opened by "Add" shows setup wording:** "If you have no investments, save with none chosen". Outside setup, an empty save is refused.
- **Switching from one demo household to another signs out every other visitor to the one you left,** and their done actions go with it.
- **The Holistic Plan's "Plans Included" section** still lists module suggestions that the actions list does not have.

## Behind the scenes

- **At release:** app code, one database change (a demo completion records the visit it belongs to) and the desktop web bundle. There was no mobile bundle, seeder or Fyn knowledge change.

## Not tested: the iPhone app

These are server changes, so the iPhone app gets the same lists. The iPhone app does not draw forms yet, so a change typed to Fyn there still gets typed questions (list item 35).
