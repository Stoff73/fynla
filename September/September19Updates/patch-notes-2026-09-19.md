# What's new in Fynla — 19 September 2026

Two updates went live on fynla.org today. Most of the changes come straight from watching Azlan, Laura and Brett use the app, so if you spotted something that was awkward last week, there is a good chance it is on this list.

Everything below was tested by walking through it as a real user on the test site and then again on the live site, on both the desktop web app and the mobile web app.

## Signing up and getting started

- **Typing in a company or provider name now works properly.** Spaces were being stripped as you typed, so "Acme Widgets Ltd" came out as "AcmeWidgetsLtd". Fixed on web and mobile.
- **Registering on a phone no longer squashes the legal text at the bottom of the page.**
- **Choosing "Something else" on the first screen is no longer a dead end.**
- **Signing in on the mobile web app:** there is now a "Forgotten your password?" link, and if your account is temporarily locked after too many attempts it tells you that, rather than saying your password is wrong.
- **The mobile sign-in code step** and the "restore my account" step now work the same way as on the web.

## Telling Fyn about your finances

- **You can say "I don't know".** When Fyn asks what your spouse earns, there is now an "I don't know" tick box. Before, you had to type a number or get stuck. Fyn also reads it back correctly ("I don't know what my spouse earns…") instead of pretending you said your spouse has nothing.
- **"None" is an answer.** If you have no investments, you can save the form with nothing chosen, or type "I don't have any", and Fyn moves on. Later on, the tax strategy page no longer nags you to add the things you have already said you do not have.
- **Saving an empty form no longer fails silently.** Where a form allows "nothing to add", saving it now counts as your answer.
- **The savings form explains the account types** (what a current account is versus easy access, fixed rate and notice accounts) and reminds you that a joint account is shared 50/50.
- **On mobile, the "Continue" and "Edit" buttons only appear when you are checking a page,** not while you are still filling in a form. Tapping Continue now shows your answer straight away instead of a blank box while it thinks.
- **You can correct something you have already told Fyn.** Ask "Can I change that answer?" mid-walk and Fyn offers the sections you have saved so far, opens the one you choose with your answers filled in, then carries on where you left off. On a "check this page" screen, "No, change something" opens the record ready to edit. After you have finished setting up, "change my salary to £65,000" or "remove my …" in the chat opens the same form.
- **When you edit a property or a policy from the chat, the page behind updates immediately.** You no longer need to reload to see the new figure.
- **Fyn no longer describes what it is doing behind the scenes** ("I'll now save that…"). It just does it.

## Inviting your spouse or partner

- **The invitation email now takes your partner to a registration page,** not the homepage. Their first name and email are already filled in, and the page says who invited them.
- **Registering from the link links your accounts automatically.** There is no separate "accept" step: sending the invitation was your say-so, and registering from the link is theirs.
- **Your partner starts where you left off.** They land in Fyn's Save Tax walk with the household details you already gave carried over, rather than being asked to pick a journey from scratch.
- **Joint accounts now show up for both of you.** A joint savings account you added while setting up now appears on your partner's side once they register from your invitation. Before today it stayed invisible to them.
- **Opening the link on a phone** no longer loses the invitation on the way to the mobile app.

## Your dashboard

- **The Savings card and your net worth now agree.** If you had added a joint account but not yet given your date of birth or monthly spending, the Savings card said £0 while net worth said £6,000. The card now shows what your cash is worth from the moment you add it, on web and mobile.
- **While you are still setting up, the mobile dashboard says so.** The empty actions list now reads "Finish setting up with Fyn to unlock your actions" and opens Fyn, instead of showing nothing.

## Your tax strategy

- **The Save Tax page before you sign up** now counts the allowances that could apply to you (for example "6 of 14") instead of adding up unrelated amounts, and when you have a partner it shows "Yours" and "Your partner's" side by side with a "Why?" behind each one.
- **The strategy page tells you what to do next.** Both Fyn's closing message and the page itself now say "open any strategy to see the steps, or ask Fyn about it" rather than leaving you on a page with no instructions.
- **Strategies are only shown as locked when they really are.** A section that is open but has nothing to recommend yet no longer wears a padlock.
- **Fyn's tone when recommending something is warmer and clearer.** It now gives the strategy's name and the one fact that matters, and leaves the small print on the page. No more "You may want to consider:".
- **If you sign up through the Save Tax page or a partner's invitation,** Fyn opens straight into the Save Tax walk on the web too, rather than the general welcome screen.

## Chatting with Fyn after you have finished

- **Typing into Fyn after your setup is complete no longer freezes the chat.** A question such as "Is it worth paying more into my pension this year?" now gets a proper answer.
- **Coming back to a paused setup** picks up where you were.

## Behind the scenes

- The desktop and mobile apps now describe every section with the same words, chosen in one place.
- A handful of older, half-finished fixes from 14 September were brought in and completed at the same time.

## What we checked

Every item above was tried by hand: signing up from the Save Tax page, filling in each form, inviting a partner, registering from their link, signing in on the mobile web app with the emailed code, and reading the dashboard and tax strategy afterwards. The figures shown on screen were checked against what was stored. Test accounts were removed afterwards.
