---
type: handover
mode: session-end
date: 2026-10-08
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-10-08, Session 1

## Where things stand

- **Five releases went to fynla.org today, each walked there on web and /m:**
  - w #1132: item 15, savings excess-cash cards and three how-tos;
  - x #1135: item 13 follow-up, "HMRC adds £720";
  - y #1139: item 17, no invented 4.00% market rate;
  - z #1142: item 18, plan items carry their working, figure questions grounded, failed turns not repeats.
- **Release z2 (#1144, main `63b1d63b5`) is DEPLOYED** (~10:55 BST, checksum matches, no new errors). Walked on fynla.org:
  - /m is correct;
  - web still opens "The £3,700 figure comes directly from the working shown on the card". The card does not show it, so item 18 stays open.
- **A production error was found that is not from today's work** (see priority 2).

## Priorities for the next session

0. **NEW, FIRST (CSJ 2026-10-08): item 17a.** Add OpenAI to the AI choices in the admin panel, so Fyn can connect to GPT 6 Luna, "specifically for now".
   - Starting points and what to look up are under item 17a in `todoCurrent/TODO.md`.
   - Find the admin panel's AI setting first.
   - Look up the model id in OpenAI's docs; do not assume it.
   - Then do items 18 (card wording) and the retirement error, in that order.

1. **Close item 18: Fyn still says the working is "shown on the card" (web, fynla.org, after z2).**
   - Cause: the instruction added in #1143 alone is not reliable. `FynContextAssembler::actionGrounding` frames everything as "the card" ("Explain that action from the card below", "When the card below has working").
   - Fix: present the working as the plan's own. For example, field `how_the_plan_worked_it_out`, and an intro naming "the action and how the plan worked out its figure", with the card fields separate. Keep `tapped:`.
   - Test the grounding text, then walk csjones web + /m several times (it is non-deterministic). Then own PR, release (merge the dev→main PR BEFORE handing CSJ the `!` line), and fynla.org walk with a new Save Tax walk account:
     - full-time, £50,271–£100,000, no spouse, Pension;
     - Aviva workplace 10%, not salary sacrifice; £2,000 spending;
     - ask "How did you work out the £3,700 pension figure?" on web (new conversation) and "Where does the £3,700 in my pension action come from?" on /m.
   - Purge the account. Patch notes go in `October/October8Updates/` (z and z2 are already added), then cross off item 18.
2. **PRODUCTION ERROR: the retirement plan fails** for anyone whose pension holds funds charging above 0.5%.
   - Log line: "Undefined variable $potentialSaving", `app/Services/Retirement/RetirementActionDefinitionService.php:2746` (`evaluateHighPensionFundFees`). Seen twice on fynla.org on 2026-10-08 (10:15, 10:19).
   - Cause: commit `8adf7e213` (1 October, released #1047) removed `$potentialSaving`, which rested on a typed-in 0.25% index-fund assumption. The decision-trace sentence still reads it.
   - Fix: drop the unsourced saving sentence from the trace (Rule 23; do not bring back 0.25%). Add a test with a pension whose holdings carry a weighted fund charge above 0.5%. Own PR, csjones walk, release.
   - It is recorded as a Found line under item 18 in `todoCurrent/TODO.md`.
3. **Item 19:** "Ask Fyn about this" from a card is swallowed while onboarding is paused (csjones user 419).
4. **Item 20:** /m cannot mark an emergency fund account; Fyn cannot set `is_emergency_fund`.

## Context to load

- `todoCurrent/TODO.md`:
  - item 18 has its Built, Found and Where-we-stopped lines, and the production error;
  - items 15 and 17 are crossed off with evidence.
- `app/Services/Retirement/RetirementActionDefinitionService.php:2700` — the failing evaluator (priority 2).
- `app/Services/AI/Fyn/FynContextAssembler.php` — `planGroundingDirective`, `actionGrounding` (now takes `tapped:`) and `repeatedQuestionBlock` (now counts only answered sends). These are the Fyn paths changed today.
- `October/October8Updates/patch-notes-2026-10-08.md` — today's running patch notes; z and z2 are added, with the card wording as still to do.

## Completed this session

- **Item 15** (#1130, #1131 → release w #1132):
  - Bond and GIA cards reach a holder (beside the ISA or pension card), or someone without one whose ISA allowance is used and who has no pension room that would attract relief (CSJ ruling).
  - The pension card needs ISA used, relief room (`AnnualAllowanceChecker::reliefRoomThisYear`, the one home now) and age under 75.
  - The cards use the user's own emergency target.
  - Offset, bond and GIA how-tos are approved.
- **Item 13 follow-up** (#1134 → release x #1135): the row says "HMRC adds £720" and the figure box says "HMRC adds £720 a year".
- **Patch notes now live in today's folder** (CSJ): `October/October8Updates/`. 1 to 7 October stays in `October/October1Updates/`.
- **Item 17** (#1138 → release y #1139): no stored market rate means no comparison; the zero-rate trace quotes the stored rate.
- **Item 18** (#1141 → release z #1142):
  - `PensionTaxReliefStrategy` publishes `working`, which reaches Fyn through the plan and the actions list.
  - New prompt rule in `FynSystemPrompt` (snapshot regenerated).
  - `ActionCardService::forFigureQuestion` grounds "how did you work out £X".
  - `HasAiChat::buildMessageHistory` collapses a question repeated after a failed turn; `repeatedQuestionBlock` counts only answered sends.
- **#1143** (z2, not deployed): the grounding says the working is not on the card.

## Verification state

- **Named test files** pass for each change. Every new test was checked to fail on the old code. No full suite was run (rule).
- **fynla.org:**
  - w, x, y and z were walked on web 1440 and /m 390;
  - no errors in the log from these releases;
  - the `$potentialSaving` error is older, from #1047.
- **Not verified:**
  - z2 on fynla.org (not deployed);
  - z2 on csjones /m (web only);
  - the iPhone app (server changes; same endpoint).
- **Tech-debt pass:** `tech-debt-session` was NOT run this session (CSJ asked to stop for a context clear). Known debt is in the Found lines under items 15, 17 and 18.

## Decisions and dead ends

- **CSJ 2026-10-08:**
  - Bond and GIA rules: show to a holder; otherwise only once allowances are used. A holder with ISA room sees both. Spare cash is still needed.
  - "HMRC adds £720" on the row: yes, for pensions.
  - All three how-tos approved.
  - Patch notes go in today's folder.
- **The offset card's "outside your emergency fund"** counted every unticked account. It now uses cash above the user's target, and its why line was reworded to match the approved GIA wording. CSJ was told and did not object.
- **xAI on csjones ran out of credits mid-walk.** CSJ topped it up. A failed turn exposed the repeat-question bug.

## Things that will bite you

- **Release hand-over order:** CSJ runs the `!` script as the go-ahead without typing /release. Merge the dev→main release PR as soon as csjones is walked, before handing over the line, or the guard stops it. This happened twice today.
- **/m on prod or csjones:** copy `sessionStorage.auth_token` to `localStorage.m_scaffold_token`. Visiting /m swaps the token, so web then needs signing in again (demo: re-enter via "See our demo").
- **Savings analysis is cached:** a test that changes balances mid-test needs `Cache::flush()`.
- **`investment_accounts` has no `institution` column.**
- **The patch-notes converter mangles nested lists:** write flat bullets. The converter is at `.../91ffb94e-…/scratchpad/patch-notes-pdf.py`, copied to the aa7bbf85 scratchpad. Print with headless Chrome.
- **csjones walk account 492** (`walk-18-2026-10-08@example.com` / `Password1!`) is still there for re-walks.

## Tech debt deferred

- The other tax strategies carry no `working` yet. `SalarySacrificeStrategy`: Fyn said the switch comes "with no change to your take-home pay" (wrong).
- `InvestmentActionDefinitionService::evaluateSwitchSavingsRate` reads keys `RateComparator` never sends, so that card never fires.
- The GIA card's own text says "Open a general investment account" even to a holder.

## Branch and deploy state

- **Branch:** `dev` at `9a7e7b166`, synced with origin. CSJ's own uncommitted files were left untouched.
- **fynla.org:** main `63b1d63b5` (release z2) deployed. Walk accounts 802, 804 and 805 were purged.
- **csjones:** on dev `9a7e7b166`.
