---
type: handover
mode: session-end
date: 2026-10-12
session: 6
repo: fynla
branch: dev
---

# Session Handover — 2026-10-12, Session 6

Follows session 5 (`handover/October/12/handover-2026-10-12-session-5.md`). Everything below is on **dev and csjones only**. Nothing was released to fynla.org; production is still main `f45bc67fd` (release ab).

## Where things stand

The walk fix plan (`October/October12Updates/walk-2026-10-11-fix-plan.md`) moved from T16 to T19 this session. T16 was found to break CSJ's rulings and was **reverted**. CSJ's replacement, T58, disables the chat box while bubbles wait. T17 was narrowed to the one live defect. T18 and T19 are done. Each task is ticked to **Dev walk**, walked as a user on csjones on web 1440 and /m 390 with fresh accounts. csjones runs dev `da633bb29`, and its bundles were built from the T58 tree, whose `resources/` equals dev. No Prod boxes are ticked; CSJ decides the release.

## Priorities for the next session

1. **T20: one form per person named in a message (P8).** The next task in the plan's list (section 0b). Read P8's production evidence first: which step it was typed at. Since T58, bubble steps can't take typed text, so check that the finding is still live before building anything (the lesson of T16 and T17).
2. **Then T21–T57 in plan order** (T21 the self-employed sentence says profit, T22 normal registration start, T23 Save Tax report only for Save Tax arrivals, …).
3. **DECISIONS waiting on CSJ (ask at the start):**
   - From session 5: keep or revert #1257 (estate working assumptions); `App\Support\Money` vs `FormatsCurrency` (Rule 20); #1242 Child Benefit whole-pound rounding vs exact pence; #1248 savings-to-spouse rewrite and the "source required" loader.
   - T50 what-if design; T55 how Fyn receives the Child Benefit charge; T56 search other conversations; T57 pages showing pence.
   - New wording this session: "Pick one of the options above" (chat box placeholder, T58). The limit sentence reused outside onboarding: "You've reached your plan's limit of N … To add more, upgrade your plan." (T18; it is the sentence `CoordinatingAgent` already uses at Save).
   - T17's dropped half: the plan's "park typed facts and pre-fill later forms" was not built. It only mattered for text typed at bubble steps, which T58 locks. Confirm CSJ agrees.
4. **Purge csjones walk accounts:** Maya, Leo (T58); Asha, Ravi (T17); Nadia, Omar, Lena, Theo (T18); Iris (T19); Robin 509, Priya 510, Sanjay 511 (session 5). All `slaterjoneschris+…@gmail.com`, `Password1!`.

## Context to load

- `October/October12Updates/walk-2026-10-11-fix-plan.md` — the plan and task list (section 0b), the rules R1–R5 (section 0), and each task's evidence log. T20 is next.
- `todoCurrent/TODO.md` (item 18, "Where we stopped (2026-10-12, session 6)") — the list position and the Found lines.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_bubble_steps_are_taps_never_typed_matching.md` — why T16 was reverted; never widen bubble matching.
- `app/Services/Onboarding/OnboardingStateMachine.php` (`freeLimitAt`, `freeLimitForForm`, `sectionEntryForLoop`, the `skip*` section rules) — the one home for the Free limit from T18.

## Completed this session

- **Re-check of T16, then its revert.** CSJ: "why the fuck is the user typing?". #1252 had matched typed words to bubbles, against CSJ's rulings of 2026-09-30 ("only a tap is a pick") and 2026-10-01 ("forms only, no matching"). It also misread "nothing to change" at the verify step as "No, change something". Reverted by #1262 (`4ba517595`).
- **T58, the chat box locks while answer bubbles wait (#1263, `1688f9eb8`):**
  - web: `AiChatPanel::awaitingBubbleAnswer`;
  - /m: `onboardingChat.js` methods, used by `Dashboard.vue` and `MobileChrome.vue`;
  - stays open for a navigation, Compare plans, Skip or Try again bubble alone, and for forms;
  - the /m Send arrow is styled as disabled.
- **T17, a typed answer at a form step always goes to the form (#1265, `2bb5b9bf0`).** Brett's #3836 said "nothing else in his name", which `isCompletionDeclaration` read as "nothing to add".
- **T18, Free limit (#1267, `f86a1f96e`):**
  - the check runs at section start;
  - the limit is said in words before a blank add form and before the save offer;
  - the section lead-in shows when the form was skipped;
  - the investments steps say "ISAs and investment accounts".
- **T19, typed "no mortgage" ticks the No mortgage box (#1269, `da633bb29`).** `TypedFormFill` now follows a `none_label` rule; spouse-income "I don't know" is guarded.
- **Docs PRs with each task's evidence:** #1264, #1266, #1268, #1270.

## Verification state

- **Tests:** only the files each change touches were run, all green: T58 web panel tests 26 + 3, /m 331; T17 133; T18 509; T19 70. No full suite; R5 puts it once at T43.
- **csjones walks:** web 1440 and /m 390 for T58, T17, T18 and T19, with screenshots `October/October12Updates/shots/T58-*`, `T17-*`, `T18-*` and `T19-*`.
- **Not verified:** native iOS (deferred), and production (nothing released).

## Decisions and dead ends

- **Never match typed words to bubbles** (CSJ, angry). The fix is to lock the box (T58), not to widen the matcher. Before fixing any finding, read where the user typed it in production. T17's F2, F11 and F21 were all typed at bubble steps, so they are closed by T58.
- **/m bubble state cannot be a cached computed.** The stream pushes `cursor.reply` and fills its bubbles in place, outside reactivity (`onboardingChat.js:219`, `:704`). A computed stayed false on csjones. Methods read at render work.
- **T18 lead-in signal:** "Fyn's previous message wasn't the section form" was wrong, because saves leave other messages. The signal used is the user's last message carrying that section's `form`.
- **T18 save offer:** the classifier's `entity_type` plus `matched_entity_keyword` ("cash isa") picks the form (`formForWriteIntent`), so a cash ISA counts with the ISAs.

## Things that will bite you

- **The auto-mode classifier blocks:**
  - SSH commands that change csjones state (`git checkout` of a branch, a combined merge and pull);
  - rsync of bundles ("Production Deploy").
- **What gets through:** plain `git pull`, `git checkout dev && git pull`, and tinker reads. CSJ runs the blocked lines with `!`. When a `!` line arrives as a message instead of running, check the server and run it yourself (`feedback_csj_bang_lines_arrive_as_messages`).
- **The main checkout must stay on dev.** I switched branches there by mistake once and switched straight back. Use worktrees under the scratchpad (`wt-t58`, `wt-t17`, `wt-t18`, `wt-t19`, `wt-revert1252`, `wt-docs*`). Each has its own copied `vendor`.
- **zsh does not split `$files`.** List test files explicitly when running Pest.
- **/m on csjones loads in an iframe.** Playwright role selectors need refs from a snapshot. Opening /m also signs the web tab out (T33), so expect the sign-in page when switching surfaces.

## Tech debt deferred

- The limit sentence is written twice in the director (`OnboardingChatDirector.php:2438`, `:7820`) and once per entity in `CoordinatingAgent` (`:3136`–`:4112`). It needs one home (a `freeLimitSentence()` beside `freeLimitAt`).
- The bubble-lock rule exists twice, per surface: web `AiChatPanel.vue:620` excludes only `route` bubbles; /m `onboardingChat.js:92` excludes route, action, retry and `subscription_options`. Web has no Compare plans or Skip bubbles, so the outcome matches today, but the two can drift.
- `OnboardingChatDirector.php` is 9,590 lines.

## Branch and deploy state

- Branch: `dev` at `c7c24efef`. No unpushed commits.
- csjones: dev `da633bb29`, caches cleared; bundles from the T58 tree.
- fynla.org: unchanged (`f45bc67fd`).
- Leftover branches merged: `revert/1252-typed-bubble-matching`, `fix/t58-lock-chat-input-at-bubbles`, `fix/t17-typed-answer-at-spouse-form`, `fix/t18-free-cap-at-section-start`, `fix/t19-no-mortgage-ticks-box`, and the `docs/*` evidence branches.
- Your main checkout still has CSJ's own uncommitted changes (`brettTest/` deletions, `workforce/ops` logs, briefs). They are not mine; left alone.
