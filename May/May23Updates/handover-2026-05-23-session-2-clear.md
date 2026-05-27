---
type: handover
mode: context-clear
date: 2026-05-23
session: 2
branch: reviewFix
trigger: context-handover tripwire (~870k tokens / >97.5% of 800k Fynla budget)
---

# Context Clear Handover — 2026-05-23, Session 2

## Immediate state

Just finished a long batch of tech-debt remediation on a new `reviewFix` branch (10 commits, +4194/-2164 across 116 files). Pest just completed (4022 passed / 7 failed / 25 skipped) and the branch is pushed to `origin/reviewFix`. Tripwire fired before I could investigate the 7 failures or write the final summary to CSJ.

## The thread

- `/session-start` ran cleanly (Phase 1 of 2026-05-23 morning) → CLAUDE.md metrics refreshed (Vue 667→671, Stores 36→38), dev server started, handover from May 22 session 3 picked up.
- CSJ chose `/tech-debt-full` over R1.0 audit (browser-blocked). 6 parallel module-scan agents dispatched. **178 issues catalogued** vs 101 in the 18 April baseline. Report written to `docs/tech-debt-report-full.md` (515 lines, 45 KB).
- CSJ said "create a reviewFix branch, and then get to work fixing all the issues in the report, mark them off as you go". I created the branch off `dev` at `99400ce`, set up `reviewFix-progress.md` checklist for all 178 items, and started shipping.
- **10 commits delivered, 33 audit items closed:**
  - Quick wins (Q1, Q3-Q9): Symfony CVEs cleared (composer clean), 5 dead-code deletions, joint-ISA validation guard + test inversion, factory FK fixes, schema dump refreshed (244→384 migrations), `ai_chat_enabled` orphan column dropped.
  - Short-term (S11-S20 except S14/S16/S21): 8 Rule #13 score badges stripped from Investment surfaces, CashOverview.vue wrapped in AppLayout (Rule #14), duplicate Vue filenames renamed, AssetLocationController fail-loud on missing tax config, 210 actingAs calls swept to `actingAs($user, 'sanctum')`, Auditable trait on 5 models, .env.example +5 keys.
  - Medium / backlog (M29, B30-B55 selective): constants `SignificanceThresholds` + `getCLTLifetimeRate()` consolidating 15 magic numbers, AdvicePromptBuilder now uses Resolves{Income,Expenditure} traits (closes a Fyn tax-band regression), `User::markAsAdvisor()` + `UserFactory::preview()/advisor()` states, new arch test `AdviceFynWriteToolParityTest` (6 assertions green), `DCPensionResource`, `PreviewController` sanitised error response, index migration, rogue browser scenario renamed to `BS-03`, 12 verified orphan Vuex mutations stripped, hex/palette cleanup, `console.*` → `logger.*` sweep in 4 frontend services.
- Wrong substitution caught mid-flow: my first Sanctum sweep used `Sanctum::actingAs($user)->fooJson(...)` which breaks because Sanctum::actingAs returns the User. Reverted and used the canonical second-arg form. Worth knowing for any future actingAs work.
- `npm audit fix` was skipped (Q2) — remaining 8 vulns all require breaking-change major upgrades (Capacitor CLI 7→8, Vite 5→8) that need mobile Xcode rebuild on a dedicated branch.
- Final Pest run scheduled in background just before tripwire fired. Result lines just landed: **4022 passed / 7 failed / 25 skipped** in 363s. Did NOT get to investigate the 7 failures.

## Files touched this session

```
116 files changed, 4194 insertions(+), 2164 deletions(-)
```

Highlights by area:
- **Backend services:** `app/Services/Estate/IHTStrategyGeneratorService.php` (deleted), `LifeStage/LifeStageService.php`, `TaxConfigService.php` (+getCLTLifetimeRate), `Coordination/{PriorityRanker,HolisticPlanner}.php`, `Dashboard/DashboardAggregator.php`, `Estate/{PersonalizedTrustStrategyService,GiftingStrategyOptimizer}.php`, `AI/AdvicePromptBuilder.php`
- **Models:** `app/Models/User.php` (+ Auditable + markAsAdvisor + auditExcludeFields), Household, NotificationPreference, SavingsGoal, SubscriptionPlan
- **Constants:** new `app/Constants/SignificanceThresholds.php`
- **Controllers:** `AssetLocationController.php`, `InvestmentController.php`, `PreviewController.php` (+SanitizedErrorResponse), `RetirementController.php` (+DCPensionResource)
- **Resources:** new `app/Http/Resources/DCPensionResource.php`
- **FormRequests:** `Savings/{Store,Update}SavingsAccountRequest.php` (+joint-ISA guard)
- **Migrations:** new `2026_05_23_080000_drop_ai_chat_enabled_from_users_table.php` + `2026_05_23_080001_add_funding_source_index_to_plan_action_funding_selections.php`
- **Database:** `database/schema/mysql-schema.sql` (refreshed), `database/seeders/{ChrisUserSeeder,AdvisorClientSeeder}.php`, **all 6 factories** (UserFactory, PropertyFactory, MortgageFactory, ChattelFactory, BusinessInterestFactory, CashAccountFactory, SavingsAccountFactory)
- **Vue components:** 8 score-badge files in `Investment/`, `views/NetWorth/CashOverview.vue` (AppLayout wrap), `components/Estate/LetterEstateWarnings.vue` (404 fix), `views/Advisor/{ClientDetail,ClientList,Dashboard}.vue` (CHART_COLORS), `views/Public/learn/LearnHubPage.vue` (palette drift), `Shared/ModuleStatusBar.vue`, rename of `Protection/CurrentSituation.vue` → `ProtectionModuleOverview.vue` + `Savings/CurrentSituation.vue` → `SavingsModuleOverview.vue`, delete of `Investment/GoalCard.vue`
- **Vuex stores:** Deleted `store/modules/{dashboard,household}.js` + 4 refs in `store/index.js`. Stripped 12 orphan mutations across 10 store files.
- **Frontend services:** `dashboardService.js`, `authService.js`, `investmentService.js`, `occupationService.js` (console→logger)
- **Tests:** 31 Feature files swept actingAs→sanctum (210 calls), `tests/Feature/Savings/SavingsApiTest.php` joint-ISA test inverted, new `tests/Helpers/TaxConfigFixture.php`, new `tests/Architecture/AdviceFynWriteToolParityTest.php`, renamed `tests/Browser/scenarios/document-articles-end-to-end.php` → `BS-03-...`
- **Docs:** `docs/tech-debt-report-full.md` (full rewrite), `reviewFix-progress.md` (new tracker), `CLAUDE.md` (metrics)
- **Deps:** `composer.lock`, `package-lock.json`, `.env.example` (+5 keys)

## WIP commit

- **No WIP needed** — working tree is clean. Last commit on `reviewFix`: `77470bf docs(audit): summary header on reviewFix progress checklist`.
- **Pushed:** Yes. `origin/reviewFix` exists. GitHub PR URL ready: `https://github.com/Stoff73/fynla/pull/new/reviewFix`.

## Open decisions

1. **Q10 (BLOCKED on CSJ)** — Soft-delete fate for 11 financial models that have `deleted_at` columns but don't `use SoftDeletes`. Two options:
   - **Add SoftDeletes everywhere** (Mortgage, Property, Goal, SavingsAccount, CashAccount, Investment/Holding, Subscription, Estate/Will, LifeInsurancePolicy, DBPension, DCPension) — preserves delete history, may surface downstream "where" clause issues that assume hard-delete semantics.
   - **Drop the unused `deleted_at` columns** — simpler, but loses the audit-trail intent the original Feb 2026 migration was added for.
   - Default-of-travel if not answered: surface to CSJ, do nothing destructive without explicit instruction.

2. **What to do with the 7 Pest failures.** Likely includes the pre-existing PaymentWebhookRaceTest noted in May 22's handover ("Unidentified 4th Pest failure"). Could be 6 new regressions or 6 pre-existing — needs investigation. Auto-resume should diff against `dev`'s last Pest run baseline to identify which are new.

3. **PR target.** This branch is a cleanup branch off `dev`. Default-of-travel: open a `reviewFix → dev` PR after the 7 Pest failures are triaged. **Don't merge to main** — that's the very last step after R1.0 / R1.5 land (per May 22 handover).

## Pick up from here (auto-continue contract)

1. **Investigate the 7 Pest failures** at `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e38033ea-b1f5-4351-88e9-00092987173d/tasks/bh1bt45kt.output` (line "Tests: 7 failed, 25 skipped, 4022 passed (15908 assertions) Duration: 363.49s"). Re-run without `--compact` to get full file list: `./vendor/bin/pest tests/Feature/ 2>&1 | grep -E "FAIL|⨯"`. Triage: pre-existing vs new-from-this-branch. If new, fix before opening the PR.
2. **After Pest is green** (or each remaining failure is documented as pre-existing): open `gh pr create --base dev --head reviewFix --title "chore(audit): full tech-debt remediation batch (33 of 178 items)" --body "..."`. Body should mention `docs/tech-debt-report-full.md` + `reviewFix-progress.md` + the deferred items list.
3. **Surface Q10 to CSJ** — get the soft-delete answer before either of the model-touching follow-up PRs (the 11 financial models all eventually need this resolved).
4. **csjones deploy** — not done yet for this branch. Wait for CSJ to confirm PR merged to `dev` first, then standard deploy via `git pull origin dev` + `./deploy/csjones-fynla/build.sh` + `scp public/build`.
5. **Do NOT merge to main** — release PR is the very last step, after R1.0 + R1.5 land (per May 22 session-3 handover).

## What the next Claude needs to know

- **Branch is on `reviewFix`, NOT `dev`.** Last 10 commits are all audit work; `git checkout dev` to compare against the canonical baseline.
- **`docs/tech-debt-report-full.md`** is the audit report (515 lines). **`reviewFix-progress.md`** is the per-item checklist with `[x]`/`[~]`/`[ ]`/`[!]` status. Both are committed.
- **33 items shipped, 14 partial (helper-in-place / deferred), 4 not started (large refactors), 1 blocked (Q10)**. Status summary table at top of `reviewFix-progress.md`.
- **Formatter quirk:** the PHP formatter aggressively strips orphan `use` imports. Adding an import alone doesn't survive the next save — you must add the import AND the usage in the same edit. Burned me 3 times before I learned to combine them (User, PreviewController, RetirementController patterns).
- **Sanctum sweep gotcha:** `Sanctum::actingAs($user)->fooJson(...)` does NOT work — Sanctum::actingAs returns the User instance, not the TestCase. Use `$this->actingAs($user, 'sanctum')->fooJson(...)` instead. This is what the existing 175 callers do; my sweep brought the bare-actingAs callers in line.
- **Joint-ISA illegality**: now enforced at the FormRequest layer in both Store + Update savings requests. Factory safeguards added too. Memory file `feedback_joint_isa_illegal.md` is the canonical reference.
- **Vite is on :5173 (canonical)**. Dev server was started this session — likely still running.
- **CLAUDE.md metrics** were bumped to Vue 671 / Stores 38 at session start. With S13's renames (no count change) and Q6/S13 deletes (-3 Vue, -2 stores after the dashboard/household/Investment-GoalCard deletes), the canonical counts may now be slightly off. Re-count if doing a vault-sync.
- **Pre-existing failure carry-over**: PaymentWebhookRaceTest was failing at session start (per May 22 handover, "unidentified 4th Pest failure"). It's almost certainly one of the 7 remaining failures, NOT a regression from this branch.

## Branch / deploy state

- Branch: `reviewFix`
- Pushed: yes, `origin/reviewFix` exists
- PR URL ready: `https://github.com/Stoff73/fynla/pull/new/reviewFix`
- Commits ahead of `dev`: 10
- Behind origin: 0 (just pushed)
- Deploy status: **NOT deployed** to csjones or production. csjones still on the `dev` HEAD `d3e1cf6` (per May 22 handover).
