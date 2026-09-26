---
type: handover
mode: session-end
date: 2026-09-26
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-26, Session 1

## Where things stand

**Released:** Plan B (the Save Tax strategy-engine fixes) plus CSJ's accuracy review are **live on fynla.org** as release #942 (`main` `b81d5fcc2`, carrying #941). They were walked on web, `/m` and chat before and after the release.

**In progress: the `IncomeDefinitionsService` fix.** It is code-complete and pushed on branch `fix/income-definitions-pension-contributions` (`196f90abc`).
- It is **not verified**: the full Unit+Feature regression was still running at session end (0 reds seen so far).
- There is no PR yet and it is not deployed.

**Blocked on CSJ:** the Estate long-term-residence rework needs a spec decision.

## Priorities for the next session

1. **Finish the IncomeDefinitionsService fix, then ship it.** CSJ: "needs to be done next with the rest of the outstanding stuff".
   - **Where:** the worktree is at the scratchpad `wt-planb`, which may be gone. Otherwise check out `origin/fix/income-definitions-pension-contributions` in a fresh worktree.
   - **Regression:** read `/tmp/idef-unit.log` and `/tmp/idef-feature.log`. If they're gone, re-run `./vendor/bin/pest tests/Unit` then `tests/Feature` **one at a time**, never alongside another pest process (they share one testing database).
   - **Expected reds:** tests that pin the old wrong behaviour, i.e. onboarding contributions ignored, or `taxableIncomeFor` returning total income instead of net income. Update each one with the cited statute.
   - **Then:** open the PR to `dev`, deploy the branch to csjones (`deploy/csjones-fynla/build.sh`, git pull, no seeders), and walk a user with an onboarding workplace pension and a personal pension on web and `/m`. Check the income tab net income, the Tax Strategy plan and adjusted net income.
   - **Release:** CSJ invokes `/release`. The prod deploy is a script CSJ runs with `!` (see Things that will bite you).
2. **Estate long-term UK residence. BLOCKED ON CSJ SPEC: ask at the start of the session.**
   - `User::isDeemedDomiciled` applies the pre-April-2025 "deemed domiciled after 15 years" rule. It drives the profile Domicile section, `EstateDataReadinessService.php:172` requires `domicile_status`, and Fyn gets `TaxConfigService::getDomicile()` (`CoordinatingAgent.php:2878`).
   - The law since 6 April 2025: resident in at least 10 of the previous 20 tax years (IHTA 1984 s6A, [IHTM47020](https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm47020)).
   - The Inheritance Tax calculation itself does not use domicile. Domicile still governs wills (`WillDocumentService`, leave it).
   - Needed from CSJ: the profile section, onboarding question and Fyn briefing design.
3. **Help pages: full accuracy audit.** `public/pages/help.php` and `resources/js/views/Help.vue` still describe screens that no longer exist ("Current Situation Tab", "Policy Details tab") and say domicile drives Inheritance Tax. Verify every statement against the live UI and a cited source (CLAUDE.md Rule 23).
4. **Plan C: how-to pages.** See CSJTODO "NEXT". It includes designing the overlap note (`conflict_note` / `counted_in_total`) into the new headed-action layout (CSJ ruling: don't bolt it on).
5. **Scottish income tax: a separate programme** (CSJ). Nothing exists yet: no taxpayer field, no bands.

## Context to load

- `CSJTODO.md` section "NEXT — accuracy follow-ups, then Plan C": the ranked outstanding list with every ruling.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_figure_errors_are_never_minor.md`: the **golden rule** (CLAUDE.md Rule 23). Cite a source for every claim, fix and figure. A figure or eligibility error is never "minor".
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/project_release_2026_09_26.md`: what shipped, the deploy facts, the permission lessons and what's next.
- `September/September25Updates/patch-notes-2026-09-25-savetax-accuracy.md`: the user-facing account of what's live, with sources.
- `tests/Persona/savetax-strategy-fixes/reports/2026-09-25.md`: walk evidence and expected figures for the four test households.

## Completed this session

- **Plan B** (`docs/superpowers/plans/2026-09-25-savetax-strategy-engine-fixes.md`) run inline: B1, B4–B9, B12, B13 fixed. B11 isn't a bug (step-children are stored as `child`). B2, B3 and B14 need new capture.
- **Review fixes** (tax-compliance reviewer plus a fresh code reviewer):
  - pension relief sized after existing contributions;
  - Marriage Allowance transferor cost netted off;
  - one workplace rule (`PensionContributionRule::isWorkplace`);
  - `counted_in_total` on composed items;
  - Fyn section and advice paths include pension relief.
- **Accuracy batch** after CSJ's review, every rule cited:
  - **Marriage Allowance:** per ITA 2007 Part 3 Chapter 3A, both directions; the saving is capped at Step 5 tax via `UKTaxCalculator`.
  - **Funnel estimate:** no longer double counts the transferred allowance.
  - **Pension relief:** re-priced without interest the ISA wrap already shelters; the age limit comes from config (`pension.relief_max_age`, FA 2004 s188(3)(a)); one `pension_tax_relief` type.
  - **Spouse pension top-up:** married couples and civil partners only.
  - **Wording:** "5 April"; "spouse or civil partner" everywhere.
  - **ISA rule:** corrected on all Help pages and guides (SI 2024/350, including the Junior ISA exception).
  - **Help Inheritance Tax and spouse-linking answers:** sourced, and the garbled "â†’" removed.
  - **Web Tax Strategy:** "Current-year use not confirmed" instead of "Fully used"; no false "well-utilised" or "Nothing to act on"; spouse-transfer copy from TCGA 1992 s58 and IHTM47030; the household heading is no longer repeated.
  - **Pension tile:** limited by earnings relief (FA 2004 s190) and capped by the affordability calculator (`CompositePlanService::financials`), with a note; the "Annual Allowance used" milestone is guarded by `limit_basis`.
- **Shipped:** #941 merged into `dev` (`be6f42dd2`), release #942 into `main` (`b81d5fcc2`), deployed to fynla.org and walked live (account 752, purged). Patch notes marked released (`f5f039b6a`).
- **Rule 23 (golden rule)** added to `CLAUDE.md`, and saved to memory.
- **The IncomeDefinitionsService fix,** on its branch (`481fac0be`, plus the tech-debt report in `196f90abc`).

## Verification state

- **#941 CI at `a3c16c036`:** all green except iOS `test-and-build`, the known `AuthenticationCoordinatorTests.refreshAuthenticatedUserReplacesTheCachedUserWithoutTouchingTheSession` (red since 2026-09-22; not a gate).
- **Local clean regression at `a3c16c036`:** 5,888 passed, 3 skipped. Full vitest 1,452 passed.
- **Walked on fynla.org after release**, on web, `/m` and chat: £3,828 plan (pension £8,700 / £3,480), pension tile £45,357 with the budget note, `/help` Junior ISA wording with 0 mojibake, `/savetax` spouse-or-civil-partner wording.
- **Walked on csjones:**
  - Households (i) and (iv) through the full chat, on web and `/m`: £3,828 and £252.
  - Lena's £8,000 relief-limit tile and Paula's budget-limited tile.
  - Household (ii) is £2,822 by server recompute.
- **Not verified:**
  - The `IncomeDefinitionsService` branch's full regression (still running at session end);
  - iOS native for everything this session (the change is server-side);
  - the affordability cap when a user has recorded no spending: the calculator then treats all net income as affordable. That is by design of the calculator, but no user has been walked with spending recorded through the UI.

## Decisions and dead ends

**CSJ rulings:**
- The spouse pension top-up counts as tax saved ("a spouse is a legal contract").
- Basic-rate pension relief is 10% of earnings less what is already paid in.
- `IncomeDefinitionsService` goes next, together with the rest of the outstanding items.
- Scottish is a separate programme.
- Dated insight articles keep their historic figures.
- The pension tile must use the affordability calculator ("what use is knowing a limit you neither care about or can afford?").
- The overlap note goes into Plan C, not bolted on now.

**My rulings (in the Plan B ledger):**
- `IsaAllowanceAllocator` ranks by annual value, using the Lifetime ISA bonus when there's no tax saving, so the Lifetime ISA isn't starved.
- Allocator and conflict notes name items by title, never by internal id.
- The Marriage Allowance transferor test stays strictly "less than" the Personal Allowance: ITA 2007 s55C(2) says "less than". A reviewer's "£12,570 or less" was wrong.

**Dead ends:**
- The first regression after the tile change showed 197 false reds. That was a second pest run on the same testing database, and re-running the failures alone passed.
- `/savetax` redirecting to `/login` in Playwright was a stale web session cookie; clear the context cookies.

## Things that will bite you

- **Auto mode blocks production deploys and backups** ("Production Deploy"). Write the full deploy as a scratchpad script (template: `release-prod-2026-09-26.sh` in this session's scratchpad) and have CSJ run it with `! bash <script>`. Read-only prod tinker and the walk-account purge worked.
- **Merges:** `gh pr merge --admin` only passed after CSJ invoked `/release` in the turn. The `release` skill can only be started by CSJ.
- **Never run two pest processes at once.** One testing database, and the result is mass false reds.
- **csjones builds need `deploy/csjones-fynla/build.sh`.** The base path is `/fynla/build/`; the local worktree build uses `/build/`. Upload with the preserve-old-chunks pattern (`build.old` plus `cp -rn`).
- **The cookie banner blocks Playwright clicks** on csjones and fynla.org after clearing cookies. Click "Accept Cookies" first.
- **Local `/m` and web login codes:** `email_verification_codes`. Registration codes: `pending_registrations`.

## Tech debt deferred

From `docs/tech-debt-report.md` on the branch `fix/income-definitions-pension-contributions`:
1. **`TaxStrategyService::recalculate`** now calls `CompositePlanService::financials()` (a full profile build) on every slider move. Memoise it, or cap only on the dashboard load.
2. **`TaxStrategyMath`** is 762 lines. Extract a `MarriageAllowanceCalculator`.
3. **`TaxStrategyMath::marriageAllowance`** calls `UKTaxCalculator` up to 5 times, from 4 call sites per `calculate()`. Memoise per user.
4. **The budget-note rule and copy are duplicated** in `AllowanceCard.vue:28` and `resources/mobile/views/TaxStrategy.vue:225`. The server should set `budget_limited`.
5. **29 hardcoded tax fallbacks** (`?? 12570` and the like) in `app/Services/Tax/`. They pre-date this session.
6. **`TaxDefaults::NON_EARNER_*`** is still read by `SpouseOptimisationService.php:457` and `RetirementActionDefinitionService.php:658`.
7. **`public/pages/help.php` duplicates the content of `Help.vue`.**
8. **`IncomeDefinitionsService` `getPensionContributions` docblock** still says "no relief-at-source flag".

## Branch and deploy state

- **Main checkout:** branch `dev` at `f5f039b6a` (pulled). `CLAUDE.md` Rule 23 is committed with this handover. The other modified and untracked files (excalidraw, `workforce/*`, `brettTest/`, `chrisMapping/`, `September22Updates/`, briefs, the 15th handover) are **not from these sessions**; they were left untouched.
- **Feature branch:** `fix/income-definitions-pension-contributions` @ `196f90abc`, pushed, no PR.
- **Production:** fynla.org on `main` `b81d5fcc2` (#942). Backup `~/release-backups/2026-09-26/`.
- **csjones:** on `dev` `be6f42dd2`, with its bundles from the #941 branch build (code-identical).
