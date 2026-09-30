---
type: handover
mode: session-end
date: 2026-09-30
session: 4
repo: fynla
branch: dev
---

# Session Handover — 2026-09-30, Session 4

## Where things stand

- **Everything from this session is live on fynla.org and was walked there** (web 1440px and /m 390px, Carter demo household). Production is `main` `736fdd269`, and its tree matches `dev`.
- **Nothing is half-done.**
- **New this session: `todoCurrent/TODO.md` is CSJ's persistent, ordered work list.** Items are crossed off, never deleted; findings go under the item being worked, as "Found:" lines. The prompt hook, session-start, session-end and context-handover all read it now. **Item 1 is done. Item 2 is current.**
- **Patch notes for the evening releases:** `October/October1Updates/patch-notes-2026-10-01.md` and a PDF.

## Priorities for the next session

These come from `todoCurrent/TODO.md`, in its order. Re-read it first: CSJ edits it and answers decisions on it directly.

1. **CSJ to look (not a blocker): the Carter demo household's plan is now £75 a year.** It was "Pay £20,900 more into your pension and save £8,360". Their production figures leave nothing spare: net income £55,557, spending £52,386, goals £1,050 a month. So the new affordability rule is working correctly, but the demo household's story may want different figures. It's recorded under item 1 on the list. Mention it at the start; don't change persona data without CSJ.
2. **Item 2: Marriage Allowance tested against the law, not gov.uk's summary.** Approved ("widen to law").
   - The law: the transferor must not pay tax above the basic rate after the transfer (ITA 2007 s55C(1)(c), (ca)). Gov.uk's summary instead says the lower earner must have income below the Personal Allowance.
   - The gates are in `TaxStrategyMath::marriageAllowance` (`app/Services/Tax/TaxStrategyMath.php:543`).
   - `gh pr view 982` has ice-cube's analysis. The case that differs: £14,000 of savings interest inside the Starting Rate for Savings.
   - Spec with sources first, then build, walk web and /m on csjones, then release.
3. **Item 3: move savings to the partner who pays less tax, for couples who both earn.** Approved ("Yes build it"). This is matrix E2, and E5 for retired couples.
   - Today `AssetShiftingBundleStrategy` (gift savings) returns nothing unless the mode is `single_earner_couple` (`:34`).
   - Size the move with `PensionAffordability`-style household facts and each partner's Personal Savings Allowance (ITA 2007 s12B).
4. **Item 4: pension relief at 40% below £100,000, down to £50,270.** Approved. This is matrix E3.
   - `IncomeBandStrategy` stops at the taper threshold.
   - Any new card must go through the same `pensionFundableGross` cap (CSJ: "cap the user's own card by the same money").
5. **Items 5 onwards: see the list.** Next are the Retirement page for someone already retired, then the retirement, investment and estate module reviews followed by their how-tos.

## Context to load

- `todoCurrent/TODO.md`: the order of work, CSJ's answers, and the "Found:" lines under item 1 (what's left from it).
- `docs/superpowers/specs/2026-09-30-partner-pension-affordability-design.md`: the affordability design and CSJ's answers. Items 3 and 4 must use the same money rule.
- `docs/testing/2026-09-29-savetax-scenario-matrix.md`: the E2, E3 and E5 findings and the worked households (S3, S8, S9) behind items 3 and 4.
- `app/Services/Tax/TaxStrategyMath.php:543`: `marriageAllowance()`, the gates for item 2.
- `app/Services/Tax/PensionAffordability.php`: the one affordability figure every pension suggestion now uses.

## Completed this session

- **Released in #1025 (`d050b0609`):**
  - #1022: the desktop Tax Strategy page gives the rates note;
  - #1023: a linked spouse's own income counts as known;
  - #1024: a linked partner is asked only their income at "Now your spouse." It also added a typed prompt builder and made `isa_coordination` read the partner's own ISAs.
- **Released in #1028 (`e3ac7f944`):**
  - **#1026: `PensionAffordability`**, a year of surplus once spending is recorded, or recorded cash for someone with no income at all. It caps:
    - your own pension cards;
    - the partner top-up, which also waits for spending ("Unlock spending info") and is dropped when the partner has their own shared account.

    When the money can't cover both, they're alternatives (`competes_for_money_with` feeds the composer). Figures are whole pounds, and the tile uses the same figure.
  - **#1027:** the Save Tax spending step asks the monthly total again.
- **Released in #1030 (`736fdd269`):** #1029, a tile that affordability brings to £0 says "£0 of headroom" (web) or "£0 available" (/m), not "Fully used".
- **Process:**
  - `todoCurrent/TODO.md` created;
  - `.claude/hooks/handover-priorities.sh` rewritten to show the current item, the next three and open decisions;
  - session-start, session-end and context-handover updated to read and update the list;
  - `CSJTODO.md` NEXT pruned of shipped entries.
- **Corrections made:**
  - The session-3 handover recorded two of CSJ's questions as approvals. CSJTODO was corrected, and CSJ then approved both ("Yes build it").
  - Saved as memory: `feedback_read_the_deterministic_flow_first`, `feedback_pension_advice_in_context_all_info`, `feedback_todo_current_persistent_list`.

## Verification state

- **Named test files only, all green at each PR head:**
  - #1023: 56 + 165;
  - #1024: 31 + 177 + 308;
  - #1026: 9 new, plus 131 + 137 + 266 related;
  - #1027: 37 + 79;
  - #1029: 28 frontend.

  Every new test was shown to fail without its change.
- **Walked on csjones, web and /m, by clicking:** every PR. Walk accounts 442 and 444 to 451.
- **Walked on fynla.org:**
  - #1022: the rates note (Carter household);
  - #1023: no "Unlock spouse's income info" for the Carter household;
  - #1029 / #1026: the tile and the plan (Carter household);
  - #1024: prod state table checked (the builder is live).
- **NOT walked on fynla.org:**
  - the Save Tax spending step;
  - the partner top-up cards;
  - #1024's partner step.
- **Not verified:** the iPhone app, for everything. Full suites were not run.

## Decisions and dead ends

- **CSJ's rulings today:**
  - Pension suggestions are "a reasoned decision… in context… we need all the info". No fixed "higher rate first" rule.
  - Affordability is checked always, and your own card is capped by the same money.
  - A linked partner's card lives on their own account.
  - "We ask for expenditure" / "lets ask for spending".
  - A partner's unknown age: keep suggesting.
  - CSJ tests the phone.
  - Item 14 (sources for three savings how-tos) is Claude's work, not a decision.
- **The cash-only affordability rule applies only to someone with no income at all.** It first caught a retiree drawing a £125,140 pension and emptied their card.
- **With no spending recorded, your own card keeps today's (uncapped) behaviour.** Save Tax now asks spending, so this case is only accounts created before tonight.
- **The waiting partner top-up lists `expenditure` before `pension_contributions` in its required data,** because the unlock card names the first missing item.
- **Dead end:** `gh pr merge --admin` and database edits on csjones can be blocked by the auto-mode classifier. When CSJ says "merge" or "allow the edit", they work.

## Things that will bite you

- **Save Tax's spending step behaved differently by date and client.**
  - Before 22 September 19:17 (`f00dbad01`) it asked the total.
  - From then until #1027 it asked only childcare, donations and Gift Aid on web and /m.
  - The iPhone app, which gets typed prompts, always asked the total.
  - Read the state order, corpus, form variant, `git log -S` and production `ai_messages` before stating what onboarding asks.
- **The users table defaults `expenditure_entry_mode` to `category`.** A fixture using `monthly_expenditure` needs `'expenditure_entry_mode' => 'simple'`, or the spending counts as unrecorded.
- **Seeded walk accounts need AI chat consent** (`ConsentService::recordConsent`). Without it, Fyn returns a 403 that shows as "Connection lost".
- **The first verification code after a sign-out on csjones is usually rejected.** Sign in again and the next one works.
- **A walk account mid-onboarding can't open Tax Strategy from the menu.** Use an account with onboarding completed.
- **fynla.org /m runs inside an iframe.** Find refs through the page snapshot, not role selectors.

## Tech debt deferred

See `docs/tech-debt-report.md`, session 4. No critical issues. Warnings:

- the affordability figure is worked out twice per dashboard request (`TaxStrategyCalculator.php:68`, `TaxStrategyService.php:57-61`);
- `availability()` resolves `PensionAffordability` twice (`HouseholdFinancialContext.php:66-67`);
- the cap block is duplicated (`PensionTaxReliefStrategy.php:60`, `IncomeBandStrategy.php:54`);
- the tile label is computed per client (`AllowanceCard.vue:72-84`, `TaxStrategy.vue:232-239`);
- new `app()` service locators.

## Branch and deploy state

- **Branch:** `dev`, pushed, with nothing unpushed and no worktrees.
- **Production:** `main` `736fdd269`, the same tree as `dev` apart from docs.
- **csjones:** on `dev`.
- **csjones walk accounts to purge** (item 36): 442, 444 (linked to each other), and 445 to 451.
- **Local uncommitted files are CSJ's own, left alone:** `docs/diagrams/*.excalidraw`, `workforce/ops/log/*`, the session-1 handover edit, and untracked folders.
