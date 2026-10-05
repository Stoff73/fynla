---
type: handover
mode: session-end
date: 2026-10-05
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-10-05, Session 1

(The session ran 2026-10-03 to 2026-10-04; it is the second session of item 7a's income work.)

## Where things stand

Item 7a's income work is merged to dev in three PRs, #1060, #1063 and #1065 (dev `95e9ba28b` after the list PRs #1064, #1066, #1067), and walked on csjones on web and /m. It is NOT released: fynla.org is still on main `cf0cbbc06`. Every decision raised in the last two sessions is closed. Item 7a stays unchecked on the list until it is released and walked on fynla.org; the next work item is 8.

## Priorities for the next session

1. **Release #1060 + #1063 + #1065 only when CSJ says.** Never recommend it. The release needs: `php artisan db:seed --class=TaxConfigurationSeeder --force` (State Pension age table, #1060), migration `2026_10_04_000001_make_ni_years_required_nullable_on_state_pensions_table`, `php artisan fyn:procedural:validate` (onboarding corpus changed: the date of birth step now asks gender), both bundles (web + /m), patch notes in the running file (`October/October1Updates/patch-notes-2026-10-01.md` + PDF, memory `feedback_patch_notes_one_running_file`). Walk fynla.org web 1440 + /m 390 after, then cross 7a off.
2. **Item 8: Investment module review, then its how-tos** (24 definitions in `database/seeders/InvestmentActionDefinitionSeeder.php`: 18 agent, 3 goal, 3 strategy). Same shape as items 6 and 7: read `docs/superpowers/specs/2026-10-01-retirement-cards-review-design.md` for the review structure (definition_key + figures through the adapter, keys that fire on real households, duplicates with the Tax plan and Savings, text breaking Rules 2/9/23). Known already: Investment keeps its own emergency fund cards (`emergency_fund_critical`, `emergency_fund_grow`) beside Savings' (Rule 20); `use_isa_allowance`/`isa_allowance_remaining`/`surplus_to_isa` overlap the Tax plan's ISA strategies; `surplus_to_pension` overlaps the Tax plan's pension relief with no affordability check; Title Case titles; the "Speak to your adviser" line. The adapter is `app/Services/Coordination/PlanSources/Adapters/InvestmentRecommendationAdapter.php`; the evaluator `app/Services/Investment/InvestmentActionDefinitionService.php`.
3. **Item 8a: smoker and health status** (deferred by CSJ until item 8's cards are done).

## Context to load

- `todoCurrent/TODO.md` — the order of work; 7a's lines from "Progress (2026-10-03, session 2" down record this session, the closed decisions and the remaining Found lines.
- `docs/superpowers/specs/2026-10-01-retirement-cards-review-design.md` — the review shape item 8 copies.
- `database/seeders/InvestmentActionDefinitionSeeder.php` — the 24 Investment definitions to review.
- `docs/tech-debt-report.md` — top section (2026-10-03/04 session 2), 8 items from this batch.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_only_real_decisions_in_plain_words.md` — this session's correction.

## Completed this session

- **#1063** (merged dev `0fdb1a651`):
  - Fyn edits dividend, interest, trust and other income through a form (`CaptureForms::OTHER_INCOME`, written by `update_profile`, which now takes interest and trust). It is offered on every edit door, /m income rows and the /m Income page's "Edit details" (form, or the edit chooser's bubbles).
  - `WriteIntentClassifier` names income, so "change my income details" reaches the forms.
  - Trust income is taxed in the beneficiary's bands before savings and dividends (ITA 2007 s16), with the trust's tax as a credit (ITA 2007 s494; gov.uk trusts-taxes beneficiaries); the rates come from tax config (`UKTaxCalculator.php:619-624`).
  - Fyn's advice context carries the Income tab's own Income Tax, National Insurance and take-home, and employment after salary sacrifice, so the parts it lists add up.
  - `InvestmentAccountStore` keeps `users.annual_dividend_income` in step on every write.
  - IHT figures are worked in pence (`assessTaxPosition`).
  - The first-income award counts recorded income only.
  - Plus the tech-debt items, the CI fixes, and NI years read through `StatePension::ni_years_for_full_pension`.
- **#1065** (merged dev `e0660ece8`, CI green including iOS):
  - `done` carries the stored reply; web (one helper for four handlers), /m and iOS show it, so the screen equals a reload.
  - Gender is asked with the date of birth on the Save Tax step (`campaign_dob`, form + lead-in + corpus) and on the journey's personal form; the read-back names it.
  - Schema-only migration making `state_pensions.ni_years_required` nullable with no default.
- **List PRs** #1064, #1066, #1067; memory `feedback_only_real_decisions_in_plain_words`.

## Verification state

- CI fully green on #1063 (`1c4d90942`) and #1065 (incl. iOS `test-and-build`).
- Walked on csjones from each branch before merge:
  - **Web 1440 and /m 390 as 459:** the other-income form saves; trust £3,000 at 40% = £1,200 against £1,350 paid gives a reclaim of £150. Fyn quotes £65,000 total (£60,000 + £2,500 + £2,500), tax £13,147, National Insurance £3,210.60, take-home £48,642.40.
  - **New account 479 through Save Tax:** date of birth 14/03/1981 and Female saved and read back.
  - **Screen and reload:** the screen shows the stored reply on web and /m.
  - **IHT:** projected IHT reconciles on two demo households (peak earners £5,544,870.70 × 40% = £2,217,948.28).
- **Not verified:**
  - fynla.org (not released);
  - iOS on a device (CI only, by rule);
  - the cap-pass case of `done` replacing the text was not triggered live (tests cover it).

## Decisions and dead ends

- **CSJ 2026-10-04 answers:**
  - **Trust type:** comes from the trust form, so no new question.
  - **Gender:** must be registered, so it is asked in setup, not given a fallback.
  - **Two versions of one message:** never shown, hence `done.content`.
  - **The four #1060 choices:** closed. 1 is the no-browser-maths rule; 2 is only a label; 3 and 4 are the law.
- **The tool-call cap pass keeps `7731abcb1`'s rule:** the stored reply is the final pass only. I first reversed it, which broke `FynRepetitionRegressionTest`, so I restored it. The screen now matches the store through `done.content`.
- **Classifier blocks:** remote writes to csjones and a migration that rewrote rows were blocked by the auto-mode classifier. CSJ ran the deploy scripts; pulls ran after CSJ asked.
- **Marriage Allowance:** can't come off the Income Tax shown because nothing records a claim. This is a Found line, not a decision.

## Things that will bite you

- **`TaxConfigurationFactory` stores rates as whole percentages** (20, not 0.20). Any test on the 2019/20 safety-net row that taxes income above the allowance gets figures far too large, so seed `TaxConfigurationSeeder` in such tests.
- **Test helper functions in Pest files are global:** name them uniquely.
- **The Pest hook blocks directory runs and paths held in variables:** list each file literally.
- **csjones registration codes** are on `PendingRegistration.verification_code` until the account exists; login codes are on `EmailVerificationCode`.
- **Local `public/build` and `public/m-build` are csjones builds** (base `/fynla/`); rebuild before walking locally. The local dev server may still be running.
- **Walk accounts:**
  - local 125 (`walk-7a-2026-10-03@example.com`, AI consent recorded);
  - csjones 459 (now dividends £2,500, trust £2,500), 460, and 479 (`walk-gender-2026-10-04@example.com`, mid-Save Tax at the workplace pension step).
  - All use `Password1!`.

## Tech debt deferred

From `docs/tech-debt-report.md` (2026-10-03/04 session 2):
- Duplicate `pounds()` helpers (`UKTaxCalculator.php:675`, `CaptureForms.php:698`).
- `incomeAndTaxFor` built on every Fyn turn (`AdvicePromptBuilder` income block).
- The income field list written twice in `CoordinatingAgent::handleUpdateProfile`, which also writes employment totals directly.
- Income source keys listed in both `RecordEditForms::OTHER_INCOME_SOURCES` and `CreateContextualConversationRequest.php:374`.
- `TaxBandTracker::getCurrentBandPosition` (`:162`) now unused.
- `UserProfileService::totalGrossAnnualIncome` misnamed.
- The round-separator block repeated four times in `HasAiChat`.
- The income special case in `ContextualConversationService::create`.

## Branch and deploy state

- Branch: `dev`, clean apart from CSJ's own files (two excalidraw diagrams, the 30 September handover edit, workforce logs), left alone.
- Unpushed commits: none before this handover.
- fynla.org: main `cf0cbbc06` (not released).
- csjones: on dev `e0660ece8`+, migration `2026_10_04_000001` run, corpus valid, bundles from `fix/7a-csj-points` (same code as dev).
