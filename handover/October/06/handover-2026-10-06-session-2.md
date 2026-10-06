---
type: handover
mode: session-end
date: 2026-10-06
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-10-06, Session 2

## Where things stand

- **Item 8a is done and live on fynla.org** (release r, PR #1110, main `6de140eae`).
  - Smoking and health have one home on `users`.
  - No made-up premiums remain anywhere.
  - A retiree drawing their pension gets the right dashboard line.
- **Item 8b (protection needs) is merged to dev but NOT released** (PR #1112, dev `07ffaa3bc`). It was walked on csjones on web and /m.
  - Every figure behind "You need £X" is now in the tax config under `protection.needs_calculation`, with its source, and editable in admin.
  - The maths was corrected to CSJ's decisions D1 to D7.
- **CSJ's instruction at session end:** "release for next session".

## Priorities for the next session

1. **Release item 8b to fynla.org.** CSJ asked for this. It's the NEXT line under 8b in `todoCurrent/TODO.md`.
   - **Start:** ask CSJ to type `/release`. The model can't start that skill (disable-model-invocation), and must not copy its steps by hand.
   - **Release PR:** open dev → main, then admin-merge it.
   - **Build:** `./deploy/fynla-org/build.sh`. This release ships both bundles, because `resources/mobile/views/modules/Protection.vue` changed.
   - **Prod script:** goes to CSJ via `!`, because the auto-mode classifier refuses prod deploys. Copy last session's `release-prod-2026-10-06-r.sh` (path below) and change:
     - no migration;
     - upload `public/m-build/` as well as `public/build/`;
     - seeders: `TaxConfigurationSeeder`, `ProtectionActionDefinitionSeeder`, `ActionHowToSeeder`;
     - the scope guard now allows `resources/mobile`;
     - the backup tables: `tax_configurations`, `protection_action_definitions`, `action_how_tos` or its equivalent (check the real table names).
   - **Walk fynla.org:**
     - web 1440 as a walk account: Protection page "Your cover" rows, the "Income replacement capital" working, and the plan page;
     - admin Tax Settings > Module Config > "Protection needs calculations" (view only);
     - /m 390 Protection.
   - **Finish:** purge the walk account (`RetentionPurgeService::purgeUser` + force delete), add the release to `October/October1Updates/patch-notes-2026-10-01.md` and regenerate the PDF (method below), then cross 8b off.
2. **Item 9: Estate module review, then its how-tos** (12 definitions). Same shape as items 7 and 8: a review spec with evidence, CSJ decisions, fixes, how-tos, walk, release.
   - It has typed-in figures already logged under 8a/8b "Found":
     - `estate.onboarding_estimates` (`EstateOnboardingFlow.php:259`);
     - the £200,000 equity release default (`EstateAgent.php:1802`);
     - the `85 - age` years-to-death fallback (`ComprehensiveEstatePlanService.php:221`).
   - Remove made-up figures on sight; never ask whether to.
3. **Items 10, 11 and onwards,** in list order.

## Context to load

- `todoCurrent/TODO.md` — the order of work.
  - Items 8a and 8b carry today's Found lines, CSJ's answers, and the NEXT release line.
  - 8a is crossed off; 8b is open until it's released.
- `docs/superpowers/specs/2026-10-06-protection-needs-config-design.md` — 8b's research (sources), the maths defects, the method, the config layout, and CSJ's D1 to D7 answers.
- `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/589eb7b6-48a6-4faf-b32a-f7d7bc269a8e/scratchpad/release-prod-2026-10-06-r.sh` — the release-r prod script to copy for the 8b release. It's in /tmp: if it's gone after a reboot, rebuild it from the structure described above.
- `October/October1Updates/patch-notes-2026-10-01.md` — the running patch notes; release r is the newest 6 October section (line ~194). Add 8b's release above it.
- `docs/tech-debt-report.md` — top section: today's session 2 findings.

## Completed this session

- **Item 8a** (#1108, `a7cefcf3b`, `13f1b51e1`, `d2b6bf5da`), released as r (#1110):
  - **One home on `users`:** `ProfileEnums::isSmoker` / `hasHealthHistory` are the one place each question is answered. Smoker means smoked in the last 12 months, per L&G; any health history counts for the enhanced annuity card.
  - **Migration `2026_10_06_120000`:** both columns can now be empty with no default; 72 prod users on both defaults were reset to not answered; the `protection_profiles` pair was dropped.
  - **Fyn's personal form asks both questions.**
  - **The enhanced annuity card:**
    - reaches retirees who have no retirement profile;
    - has a shorter description;
    - no longer carries invented 20%, 15% or 5% figures.
  - **Made-up premiums removed everywhere:**
    - protection plan and cards, `RecommendationEngine`, `ScenarioBuilder`;
    - the Estate life policy page, which now shows the cover and "Premium: From insurers' quotes";
    - the Estate plan;
    - the premium config;
    - dead components that carried invented figures.
  - **Dashboard:** focus-card lines come from the server (`NextActionsService::AREA_INFO`).
  - **Bond how-tos approved:** `bond_position`, `bond_paid_in_missing`.
  - Patch notes and PDF updated.
- **Item 8b** (#1112, `abd332bd3`, `3341880a5`, `342d94237`), merged, not released:
  - **Config:** `protection.needs_calculation` (life_cover.income_replacement and final_expenses, critical_illness, income_protection, employer_cover), each figure with its source. The seeder and the test factory share `TaxConfigurationSeeder::protectionNeedsCalculation()`. Read only through `TaxConfigService::getProtectionNeeds()`, which throws if the block is missing; there are no code fallbacks.
  - **Life cover maths:** household living costs (recorded spending plus a home's running costs, without mortgage, loan, pension, saving and premium payments), less income that continues, paid until State Pension age, as a lump sum at the Personal Injury Discount Rate of 0.5%. If no spending is recorded, the income part is left out and the page says so.
  - **Figures:** final expenses £9,797 (SunLife); education removed; income protection is L&G's 60% to £60,000 then 50%; critical illness stays at 3 × income, shown everywhere as a rule of thumb.
  - **One engine:** `generateOptimizedStrategy` reads the one set of needs; the plan page and print view print the server's working.
  - **Admin:** a "Protection needs calculations" section with headings, sources and validation.

## Verification state

- **Tests:** named files only, all green at `342d94237`.
  - Backend: protection unit, feature and integration; mobile aggregator; insights; retirement checks.
  - Vitest: CoverageGapsSection, plan print, /m FinancialDataParity.
  - CI not watched; full suite not run (a hook blocks it).
- **8a on fynla.org:** walked web 1440 as the Mitchell demo (life policy page, protection plan), plus walk account 796 on web and /m (Retirement line, Health settings, Fyn "Edit details" form, enhanced annuity card). 796 was purged.
- **8b on csjones:** walked web 1440 and /m 390 as walk account 483: life £605,543 (£595,746 + £9,797), critical illness £225,000 with the rule-of-thumb line, income protection £3,625 a month. Admin section viewed as chris@fynla.org.
- **Admin edit and save:** walked locally only (£9,797 → £10,000 moved the need; put back). On csjones I only viewed it, so the shared server's config wasn't changed.
- **Not verified:** iOS (CI only); 8b on fynla.org (not released).

## Decisions and dead ends

- **CSJ 2026-10-06, 8a:**
  - "agree, resett all users" — every real user still on both defaults was reset.
  - The bond how-tos are approved.
  - "we do not make up monthly premiums? we do not make up figures? … FIX IT" — remove invented figures on sight; never offer it as a choice. This is in memory `feedback_figure_errors_are_never_minor`.
- **CSJ 2026-10-06, 8b:**
  - "agree with your recommendations".
  - For D3: keep 3 × income for critical illness, "making sure that the user is aware this is an arbituary figure".
- **Protection readiness:** CSJ asked why it shows health and not policies. It already checks policies and employer benefits at a higher level; only the smoking and health links moved, to `/settings/health`.
- **Spending:** the protection need reads spending from `UserProfileService` (`getExpenditureBreakdown` `monthly_manual` + the property commitments' non-mortgage lines). "Spending recorded" uses the `PensionAffordability::spendingRecorded` test.
- **D2 term:** "later of State Pension age or youngest child 18/21, capped at State Pension age" always works out to State Pension age, so the code uses State Pension age only.

## Things that will bite you

- **`/release`** can only be started by CSJ typing it.
- **Prod and csjones scripts:** the auto-mode classifier refuses them, so CSJ runs them via `!`. Once, after a prod read, the classifier also wrongly blocked local `grep`s as "Production Reads"; CSJ never removed any permission.
- **Local /m** is a built bundle: run `VITE_ROUTER_BASE=/ npm run build:mobile` after editing `resources/mobile/`. The prod release script rebuilds it back to local paths at the end.
- **Protection analyses are cached:** run `php artisan cache:clear` locally after changing the needs maths, or the old text shows. An admin Tax Settings save flushes the cache itself.
- **csjones web session goes stale after a /m sign-in:** sign in at /login as a walk account, then Sign Out, then the homepage and demo work again.
- **Verification codes:** read the newest code by `orderByDesc('id')`. `latest()` returned a stale code once.
- **csjones walk accounts to purge** (item 37): 482 (retired, smoker answers), 483 (8b walk).
- **CSJ's uncommitted files:** two excalidraw diagrams, the 30 September handover, two workforce logs. Never stage them.

## Tech debt deferred

From `docs/tech-debt-report.md` (session 2):

- **Source names typed into the text:** "SunLife" and "Legal & General" sit in sentences beside figures that are editable in admin (`ProtectionGapPresentationService.php:152`, `:178`; `ComprehensiveProtectionPlanService.php:387`).
- **Spending worked out twice:** `CoverageGapAnalyzer::monthlyLivingCosts` calls `getFinancialCommitments` twice per person.
- **Unused dependency:** `TaxConfigService` is still injected into `RecommendationEngine` and `AdequacyScorer` but no longer read.
- **Long method:** `CoverageGapAnalyzer::calculateProtectionNeeds` is about 190 lines.
- **Suggestions:**
  - a repeated `rtrim(number_format)` expression;
  - an unused `$user` parameter in `LifePolicyStrategyService::calculateStrategy`;
  - smoking and health labels held in both PHP and JS with no parity test;
  - `TaxSettings.vue` is 3,157 lines.

## Branch and deploy state

- **Branch:** `dev` at `170cdbfa3`, clean apart from CSJ's files. Unpushed commits: none.
- **fynla.org:** main `6de140eae` (release r). 8b is not there.
- **csjones:** dev `07ffaa3bc` (8b code, seeders run, bundles `app-f3SXec8l.js` / `main-C2GqIGjx.js`).
