# What's new in Fynla — 25 September 2026

One update went live on fynla.org on 25 September. Its main change is to how new people get started: everyone now begins with the Save Tax check, and Fyn asks the opening questions itself when someone hasn't answered them on the Save Tax page. It also carries three fixes to how Fyn answers questions, which had been running on the test site since 24 September.

Before release, the new start was walked through as a real user on the test site, then again on the live site after the release, on the desktop web app and the mobile web app. The iPhone app gets the same questions from the same place, but it has not been walked through yet. This note only covers what has changed since the 22 September update.

## Everyone starts with the Save Tax check

- **Every new account now begins with Save Tax,** wherever the person signed up from. Before this, someone who registered directly, or came in through another campaign or a life-stage page, landed on a set-up wizard or on a choice between "Follow a journey" and "Pick a focus". Those routes are switched off for now, not removed, and can be switched back on in one place.
- **If you skipped the Save Tax page, Fyn asks the questions for you.** Fyn introduces itself ("Hi, I'm Fyn. I'll help you find where you could be saving tax. First, a few quick questions.") and then asks, one tap at a time:
  - your employment situation;
  - whether you have a spouse or civil partner;
  - roughly what they earn, if you do (the income bands come from the current tax figures);
  - which of these you have: bank account, savings account, ISA, pension, investments, property.
- **It asks only what it doesn't know yet.** If you answered some questions on the Save Tax page, Fyn picks up from the first one you missed. If your profile already answers a question, for example if you joined through your partner's invitation, Fyn skips it.
- **We dropped the "roughly what do you earn" question.** Fyn asks for your exact income straight afterwards, so asking twice added nothing.
- **Fyn introduces itself once.** Whichever question comes first gets the greeting, and the income step that follows doesn't greet you a second time.
- **Picking what you hold is quicker.** Once you've tapped one thing, the question shortens to "Anything else?" and the item you picked drops off the list. Tapping "That's everything" moves you on.
- **Typed answers work too.** "I work full time" is understood even without the hyphen in "Full-time". If you type something that isn't an answer at the first question, Fyn stops asking and helps with what you said, instead of repeating "Sorry, I didn't catch that".
- **Anyone already partway through setting up carries on where they were,** including anyone partway through the Pension Check.
- **Life-stage journeys still work for people who have finished setting up.** The Planning Journeys page opens as before.

## Fyn's answers

- **Fyn no longer treats your questions as facts about you.** Asking "where did you get the £21,000 income figure from?" used to make Fyn remember £21,000 as your income and then quote it back to you as recorded. Fyn now only picks up facts while you're setting up your profile.
- **Income protection figures are explained properly.** When Fyn talks about an income protection shortfall, it now says the figure is based on 60% of your earned income before tax, not counting rental income or dividends. Before, the need could be read as your income.
- **Fyn no longer offers to recalculate with a figure you haven't saved.** It offers to update your records instead, so what you see on your pages and what Fyn says stay the same.
- **Asking the same question twice gets a fresh answer.** Fyn used to repeat its previous reply word for word. Now it recognises the repeat, gives a new answer, and on a third try asks what you expected to see.
- **Fyn no longer names internal data labels or the parts of its briefing** in its answers.

## Behind the scenes

- **Test data can no longer leak into the live memory store.** Automated tests used to write practice "learning" notes into the folder that is copied to the live site. That is fixed, and the release process now never copies that folder. Fyn's learning feature stays switched off on the live site.
- **The development tooling was tuned** for the newer Claude models. Users won't notice any difference.

## Checked, not yet changed

As part of this work we traced every result the Save Tax check can produce, for single people, couples, and families with children. The full list is in `savetax-outcomes-by-household-2026-09-25.md` in this folder. The points below will be fixed in the next update:

- **Children don't change the plan yet.** The Save Tax check never asks about children, so families get the same plan as households without them.
- **Some suggestions can appear for the wrong people.** For example, salary sacrifice can be suggested against a personal pension, and Marriage Allowance against someone with no taxable income or an unmarried partner.
- **The "you could save" total can count the same money twice** and includes bonuses that aren't tax savings.

## Decisions taken this week

- **All onboarding goes through Save Tax for now.** The other routes stay in the app, switched off, and can come back without rebuilding anything.
- **The chat does not ask for an income band;** it asks for your exact income.
- **"Spouse or civil partner" is the wording everywhere.** They are treated the same in tax law. The public Save Tax page changes in the next update.
- **The "you could save" total will be as accurate as possible:** a suggestion only appears when you actually have the income or holding it depends on, and only real tax savings count towards the total.
- **Pension tax relief will be suggested for every tax band,** not only for people near the 60% or 45% rates.
- **The threshold strip stays where it is:** on Your actions on web and mobile, and on the iPhone home screen.
