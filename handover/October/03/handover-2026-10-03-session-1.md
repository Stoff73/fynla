---
type: handover
mode: session-end
date: 2026-10-03
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-10-03, Session 1

## Where things stand

Item 7a's take-home work (the START HERE line) is merged to dev in #1060 (`e10f79853`) and #1061 (`c8e1a9930`) and walked on csjones, web 1440 and /m 390. It is NOT released; fynla.org is still on main `cf0cbbc06`. CSJ ended the session angry: I made design choices without agreement, asked questions the rules already answer, wrote a duplicate memory file, and made a Fyn claim without checking the code. CSJ was asked which of those to revert and has not answered. Nothing is reverted.

## Priorities for the next session

1. **DECISION (CSJ), ask first: revert or keep.** These are the choices made in #1060 without CSJ's agreement (Rule 16), all on dev:
   - no total on the web Income edit form (`IncomeOccupation.vue`);
   - the "worked out from the savings accounts" wording (`UserProfileService::incomeSources`, `IncomeDefinitionsPanel.vue`);
   - the Blind Person's Allowance lifting the higher-rate limit (`TaxBandTracker` constructor);
   - part-year Class 1 counted by paydays, else days (`UserProfileService::class1Share`).
   Also: should the memory file `reference_fyn_capture_paths_by_surface.md` be deleted? It restates CSJTODO and the fyn-architecture skill. Do what CSJ says; do not decide for him.
2. **Fyn income edit by form, not phrase matching.** CSJ's 2026-10-01 ruling ("all Fyn capture through forms") already answers this; do not ask again. Today /m Income → "Edit details" reaches `update_profile` only when the message contains words from `verifyEditProfileFields` (`OnboardingChatDirector.php:5768-5778`). Trust income and a typed interest figure have no Fyn path. Before proposing anything, read the deterministic flow and grep the columns' existing writers (memories `feedback_read_the_deterministic_flow_first`, `feedback_fyn_entry_uses_existing_forms`). iOS draws no Fyn forms (no `X-Fynla-Forms` header, no `capture_form` case in `FynEvent.swift`); iOS is deferred (TODO 35).
3. **Release #1060 when CSJ says.** Never recommend it. The release must run `php artisan db:seed --class=TaxConfigurationSeeder --force`: the State Pension age table lives in the tax configuration.
4. **Item 8: Investment module review, then its how-tos** (24 definitions). Same shape as items 6 and 7.
5. **Item 8a: smoker and health status** (deferred by CSJ until item 8's cards are done).
6. **DECISION (CSJ), still open from 2 October:** what a retired person who has not started drawing sees on the Retirement page (account 405).

## Context to load

- `todoCurrent/TODO.md`: the order of work. Read 7a's lines from "Progress (2026-10-03" down: the decision, where we stopped, and the corrected Fyn capture finding.
- `CLAUDE.md`: Rules 3, 16, 20 and 23. Re-read them before every decision; that is what failed today.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_fix_adjacent_defects_in_path.md`: today's entry. Fix in the branch, never list.
- `docs/tech-debt-report.md`: the 2026-10-03 section, 7 items for this batch.
- `app/Services/Retirement/StatePensionAgeResolver.php`: the new month-precise API (`dateForUser`, `labelForUser`, `fractionPaidAtAge`, `isBeforeStatePensionAge`) that all callers now use.

## Completed this session

- #1060 commit `8ca7105e6`: one take-home figure.
  - The Income tab (`UserProfileService::incomeAndTaxFor`) is the one home for tax, National Insurance and take-home, worked out on `IncomeDefinitionsService` parts. Other income and share vests are taxed. Gift Aid and relief-at-source payments extend the bands.
  - Interest is the recorded figure, else the non-ISA accounts' balance × rate at the user's share (ITTOIA 2005 s369, s694; ITA 2007 s836). The Tax plan's own adjustment was removed.
  - The Retirement box reads the Income tab. The web Income tab renders the server's rows and total.
- #1060 commit `2097a38d2`, after CSJ's correction:
  - State Pension age to the month from Pensions Act 1995 Sch 4: every table, men and women before 6 Dec 1953, table 3 months, table 4 dates. Labels on web, /m and iOS. Projections pay State Pension from the month it starts.
  - Class 1 is charged only on pay before State Pension age (SSCBA 1992 s6(3)).
  - One gross pay figure, with salary sacrifice as its own deduction before tax and Class 1 (FA 2004 s228ZA(3)). It is no longer counted again as spending.
  - The web form keeps the before/after-sacrifice answer (it was wiped on every save). Other income input added. Income Tax £0 shown when no band is taxed.
  - Typed-in figures removed: 67 (dashboard alert, chart), 65 (final salary commencement), 13.8% employer National Insurance.
- #1061: list update.
- Memory: today's entry appended to `feedback_fix_adjacent_defects_in_path`; `reference_fyn_capture_paths_by_surface` written (possibly to be deleted, priority 1).

## Verification state

- Pest on touched files: 1,007 + 555 + 281 green; new `IncomeTabTakeHomeTest` (11) and `StatePensionAgeResolverTest` (14). Vitest specs for touched components green; the only reds are the three abandoned agent worktrees under `.claude/worktrees/`. CI on #1060 green.
- Walked on csjones, web 1440 and /m 390:

| Account | Result |
|---|---|
| 460 (retiree) | Income tab = Retirement box: £9,000, tax £0. Born 1 Feb 1960, so State Pension age 66, reached |
| 459 (salary sacrifice, before) | Switched on through the pension form: £58,200, tax £10,712, National Insurance £3,174.60, take-home £44,313 |
| 459 (salary sacrifice, after) | Gross £61,800, take-home £45,357; the answer survives saving |
| Bennett demo | £26,514 take-home, unchanged; State Pension age 66 |
| Mitchell demo | Gift Aid now extends the bands; net £101,879 |

- Locally, walk account 125: State Pension age "66 years and 4 months"; Class 1 £769.60 = 8 of 12 paydays.
- Not verified: iOS (CI only, by rule); fynla.org (not released); the Fyn edit paths for income (not exercised).

## Decisions and dead ends

- Interest (the handover told me to decide on evidence): the Tax plan's estimate is right, because Fyn's setup never asks for a yearly interest figure. It is now the Income page's rule.
- State Pension age must never be rounded (CSJ). The schedule is row for row from the statute.
- Salary sacrifice (CSJ): one gross figure everywhere, then each deduction at its legal point.
- Take-home stays "total income less tax and National Insurance". Workplace net-pay contributions are not deducted from it (the W-0422 convention, unchanged).
- My claim "/m and Fyn have no form for other income" was wrong; see the corrected TODO line.

## Things that will bite you

- **csjones is checked out on `fix/7a-one-take-home`.** Same code as dev. Switching back is a remote write the auto-mode classifier blocks; CSJ runs `git checkout dev && git pull origin dev` in `~/www/csjones.co/fynla-app`.
- **Reading csjones codes over SSH tinker works; any remote write is refused.** Prepare a script and ask CSJ to run it with `!`.
- **Local `public/build` and `public/m-build` are csjones builds** (base `/fynla/`). Rebuild before walking locally: `npm run build:mobile`, plus Vite through `./dev.sh`. The dev servers were stopped at their time limit.
- Walk accounts: local 125 `walk-7a-2026-10-03@example.com`; csjones 459 (now salary sacrifice, basis `post_sacrifice`) and 460. All use `Password1!`.
- Test helper names in Pest files are global functions: give them unique names. `sacrificer()` collided with another file today.

## Tech debt deferred

See `docs/tech-debt-report.md`, 2026-10-03 section:
- the four unagreed design choices;
- `HouseholdCashFlowProjector::statePensionAgeFor` duplicates the resolver and ignores gender (`:613`);
- the phrase-gated Fyn income edit;
- iOS has no capture forms;
- stale interest comments (`TaxStrategyMath.php:25`, `:36`);
- a repeated user lookup (`RetirementActionDefinitionService.php:2239`);
- read-only edit rows reading the form copy (`IncomeOccupation.vue` ~277, ~328).

## Branch and deploy state

- Branch: `dev` (this handover on `docs/session-end-2026-10-03`, merged by PR).
- Unpushed commits: none.
- fynla.org: main `cf0cbbc06`. csjones: `fix/7a-one-take-home` (= dev code) with the State Pension age table reseeded.
- CSJ's own uncommitted files left alone: two excalidraw diagrams, the 30 September handover edit, workforce logs. Untracked `scratch-walk-7a/` holds today's walk screenshots.
