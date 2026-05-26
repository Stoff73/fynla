---
type: handover
mode: context-clear
date: 2026-05-26
session: 3
branch: feat/pension-store-pr6
trigger: context-handover skill (tripwire at ~844k/800k tokens)
previous_session: 2026-05-26 session 2 (also context-clear)
---

# Context Clear Handover — 2026-05-26, Session 3

## Immediate state

Mid-implementation of SP1 Pass 3 / PR 6 (derived columns + snapshot tables). All code shipped via WIP commit `f7ba90b` on `feat/pension-store-pr6`; all 25 PR 6 targeted tests pass; local backfill verified populating columns correctly. Final full Pest was running in background (task id `bjqwczudm`, output at `/tmp/pest-pr6.log`) at handover invocation — next session must check that result before pushing PR.

## The thread

- Session 3 picked up from session-2's handover and executed straight through PR 3 → PR 4 → PR 5 → PR 6 of the SP1 Pass 3 pensions plan.
- Shipped + merged **PR #380 (PR 3 follow-up)**, **PR #381 (PR 4 — upload + seeders)**, **PR #382 (PR 5 — read consumers, all 7 sub-clusters + 1 follow-up fix)**. All admin-merged via `gh pr merge --merge --admin`.
- CSJ flagged mid-session that running full Pest after every sub-PR was wasteful. **New cadence agreed and applied for PR 5/PR 6: targeted tests per cluster (~1 min each), full Pest only once at end before pushing**. Saved ~60 min on PR 5 alone.
- PR 6 implementation: 6 migrations (3 derived columns, 3 snapshot tables), 3 snapshot models, 1 calculator, SnapshotPolicies extended with 4 pension policies, PensionStore constructor + recalc methods wired into all 5 write paths (createDc, updateDc via updateOrCreateDc path, createDb, updateDb, upsertState), pension models fillable+casts extended, BackfillPensionDerivedColumns console command, 3 test files.
- Two gotchas hit and fixed:
  - **MySQL long-index names**: `dc_pension_value_snapshots_dc_pension_id_column_name_taken_at_index` > 64 chars. Used custom short names (`dcpvs_id_column_taken_idx`, `dbpvs_…`, `spvs_…`).
  - **Laravel pluralisation**: `DCPensionValueSnapshot` → `d_c_pension_value_snapshots` (treats caps as separate words). Fixed with explicit `protected $table = 'dc_pension_value_snapshots'` on all 3 snapshot models.
- Local DB had a pre-existing `dc_pension_value_snapshots` table (probably from an aborted earlier migration). Made the migration idempotent with `Schema::hasTable()` guard.
- **Cadence point still open**: CSJ's last message before tripwire was "this is taking too long?" — interpreting as a flag that even the current cadence is too slow. Next session may want to (a) parallelise PR 7 + PR 8 prep, (b) skip the final full Pest if all targeted tests are green and CI will re-run it anyway, or (c) just keep pushing through.

## Files touched this session

```
Pass 3 PR 3 (#380 — merged in session 2, finalised this session)
Pass 3 PR 4 (#381 — merged)
  app/Http/Controllers/Api/PreviewController.php
  app/Services/Documents/DocumentProcessor.php
  database/seeders/ChrisUserSeeder.php
  database/seeders/PreviewUserSeeder.php
  tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php
  tests/Feature/Stores/PensionUploadIngestTest.php

Pass 3 PR 5 (#382 — merged, all 7 clusters)
  app/Agents/RetirementAgent.php
  app/Services/Retirement/{Annual,Pension,Retirement…}.php  (8 files)
  app/Services/Plans/RetirementPlanService.php
  app/Services/Coordination/{HouseholdPlanning,CashFlowCoordinator}.php
  app/Services/Goals/LifeEventAllocationService.php
  app/Services/Estate/{IHTCalculation,EstateAssetAggregator,EstateActionDefinition}.php
  app/Services/NetWorth/NetWorthService.php
  app/Services/Tax/Strategies/{SalarySacrificeNi,PensionAACarryForward}Strategy.php
  app/Services/Tax/TaxStrategyMath.php
  app/Services/AI/{AdvicePromptBuilder,DuplicateAcknowledgement}.php
  app/Services/UserProfile/{ProfileCompletenessChecker,UserProfileService}.php
  app/Services/Risk/AutoRiskCalculator.php
  app/Services/Documents/HoldingsImportService.php
  app/Services/Onboarding/AssetCaptureEntityExtractor.php
  app/Services/Eval/EvalHttpDriver.php
  app/Agents/CoordinatingAgent.php (handleListRecords + toggleSalarySacrifice)
  tests/Unit/Services/Retirement/AnnualAllowanceCheckerTest.php (RefreshDatabase + factory user)
```

Open on `feat/pension-store-pr6` (this branch, WIP commit `f7ba90b`):

```
Pass 3 PR 6 (uncommitted-then-WIP'd this session)
  app/Console/Commands/BackfillPensionDerivedColumns.php                          (new)
  app/Models/{DC,DB,State}PensionValueSnapshot.php                                (new × 3)
  app/Models/{DC,DB,State}Pension.php                                              modified (fillable + casts)
  app/Services/Stores/PensionStore.php                                             modified (+ 3 recalc methods)
  app/Services/Stores/Recalc/PensionDerivedColumnCalculator.php                   (new)
  app/Services/Stores/Snapshots/SnapshotPolicies.php                               modified (+ 4 policies)
  database/migrations/2026_05_26_100000…100005_*.php                              (6 migrations new)
  tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php                    modified (+ BackfillPensionDerivedColumns)
  tests/Feature/Stores/PensionDerivedColumnsBackfillTest.php                      (new — 3 cases)
  tests/Unit/Services/Stores/PensionStoreTest.php                                  modified (+ 4 store recalc cases)
  tests/Unit/Services/Stores/Recalc/PensionDerivedColumnCalculatorTest.php        (new — 5 cases)
```

## WIP commit

- SHA: `f7ba90b8bd59f5d05e9a11ff65b60c0e7df82723`
- Pushed: **yes** (origin/feat/pension-store-pr6 is at `f7ba90b`).

## Open decisions

- **PR 6 final-Pest result**: background task `bjqwczudm` was running when tripwire fired. Output file `/tmp/pest-pr6.log`. Next session: check `tail /tmp/pest-pr6.log` to confirm green before pushing PR. If red, diagnose (likely candidates: any pre-existing test that calls PensionStore creates without a User with `date_of_birth` populated — recalcDcDerived passes user to calculator which does `now()->diffInYears($user->date_of_birth)`; should be null-safe per the calculator). Auto-resume default: **if green, squash WIP into a proper commit and open PR #383**.
- **Squash strategy for the WIP commit**: the `f7ba90b` message already documents everything PR 6 ships. Two options: (a) `git commit --amend` to rewrite the WIP subject to `feat(pensions): derived columns + snapshot tables (SP1 Pass 3 PR 6)` + force-push (`--force-with-lease`); (b) `git reset --soft HEAD~1` and re-commit clean. Auto-resume default: (a) amend.
- **Cadence**: CSJ flagged speed once on PR 5 ("queue up PRs, single full test at end") and again at tripwire ("this is taking too long"). For PR 7 (tier-cap, very small — ~50 lines) + PR 8 (boundary lock-down, even smaller), consider doing both in one session without any intermediate full Pest at all; ship as PR #384.

## Pick up from here (auto-continue contract)

1. `tail -10 /tmp/pest-pr6.log` — confirm PR 6 final full Pest is green (expecting 4071+ passed, 0 failed). If notification arrived during tripwire, exit code is already known.
2. **If green**: amend WIP commit (`git commit --amend -m "feat(pensions): derived columns + snapshot tables (SP1 Pass 3 PR 6)" -m "<full body from f7ba90b>"`), force-push (`git push --force-with-lease`), open PR #383 against `dev` via `gh pr create --base dev --head feat/pension-store-pr6 --title "feat(pensions): derived columns + snapshot tables (SP1 Pass 3 PR 6)" --body "$(cat <<'EOF'...EOF)"`. CSJ admin-merges per established pattern.
3. **If red**: diagnose. Most likely failure mode is a pre-existing test that does `PensionStore::createDc/createDb/upsertState` without seeding TaxConfigurationSeeder (the calculator's `getPensionAllowances()` lookup) or without setting `date_of_birth` on the user. Apply the same null-safe pattern used in `PensionDerivedColumnCalculator` (try/catch around taxConfig, null checks on date_of_birth).
4. **Then PR 7**: Tier-cap enforcement at store level. Plan at `docs/superpowers/plans/2026-05-24-sub-project-1-pass-3-pensions-plan.md` lines 3791+. Very small: extend `StaticTierGate::LIMITS` with `pension_account` + write `tests/Feature/Stores/PensionTierCapTest.php` (4 cases per plan).
   - **NOTE per CSJTODO**: `StaticTierGate` was retired in favour of `TierConfigurationStore` — seed `pension_account` into `tier_configurations` instead. The plan's `StaticTierGate::LIMITS` text is stale. Confirm by checking how SavingsStore enforces tier cap in production.
5. **Then PR 8**: boundary lock-down. Final allowlist hardening. Plan at lines 3870+ish.

## What the next Claude needs to know

- **Pint hook strips unused imports** — this is by now repeatedly confirmed. Always add `use App\Services\Stores\PensionStore;` AFTER referencing `PensionStore::class` in the body. Hit this ~10 times this session.
- **MySQL identifier length limit is 64 chars**. Default Laravel index naming concatenates everything: `<table>_<col1>_<col2>_<col3>_index`. For snapshot tables with 3-col composite indexes, names blow the limit. Always provide an explicit short index name as 2nd arg to `$table->index([...], 'short_name_idx')`.
- **Laravel class-to-table pluralisation breaks on consecutive caps**. `DCPensionValueSnapshot` → `d_c_pension_value_snapshots` (wrong). Always set `protected $table = '…'` on snapshot models. Same pitfall applies to any new pension-related model with `DC`/`DB`/`UK` prefixes.
- **`TaxConfigService::getPensionAllowances()` THROWS RuntimeException if no active TaxConfiguration**. The calculator's `annual_allowance_used_gbp` calculation wraps it in try/catch with `TaxDefaults::PENSION_ANNUAL_ALLOWANCE` fallback. Mirror this pattern in any future calculator that needs tax config during a write path.
- **PensionStore recalc happens INSIDE the DB transaction** in createDc/updateDc/createDb/updateDb/upsertState. If a snapshot write fails, the whole create rolls back. Per Savings precedent — this is intentional. Don't move recalc outside the transaction.
- **Local DB already had `dc_pension_value_snapshots` table** when migration ran — added `Schema::hasTable()` guard to all 3 snapshot table migrations. If CSJ runs the migration on a fresh DB without that pre-existing table, the guard still allows clean creation.
- **CSJTODO `StaticTierGate::LIMITS` is dead** (per memory `project_pr317_gated_on_freemium_refactor.md` and session-2 handover). PR 7's plan needs adapting to use `TierConfigurationStore` + seeding `pension_account` into `tier_configurations` table. **Don't blindly follow the plan text on PR 7** — re-derive the right approach from how Savings does it.
- **Boundary allowlist status**: ~12 entries left (all documented residuals or §14.2 permanents). PR 8 final lock-down should drop the comment "transition" language entirely and convert to "LOCKED" framing (see SavingsStoreBoundaryTest as template).
- **csjones deploy is now 16+ commits behind dev** (was 14 in session 2). No deploy in flight. Mobile + tax-settings + pension store work all stacked.
- **CSJ pace signal**: 2 explicit "this is taking too long" prompts this session. Default to faster cadence — queue up multiple PRs per branch, single full Pest at the end, ship with confidence.

## Branch / deploy state

- Branch: `feat/pension-store-pr6`
- Behind origin: 0
- Ahead of origin: 0 (WIP at `f7ba90b`, pushed)
- Tree: clean (post-WIP)
- PR #380 / #381 / #382: all merged this session
- PR #383: **not yet opened** — opens once next session confirms full Pest green and amends WIP
- Deploy status: nothing deployed this session — `dev` accumulating session 3 commits + the PR 6 WIP; csjones is 16+ commits behind, main hasn't moved since 22 May (~150+ commits behind dev now).

## Session 3 scoreboard (since session-2 clear)

- 3 PRs merged: #380 (Pass 3 PR 3 finalised), #381 (Pass 3 PR 4 upload+seeders), #382 (Pass 3 PR 5 all 7 read-consumer sub-clusters)
- 1 PR ready-to-open: PR 6 (derived cols + snapshots) on WIP commit, awaiting final-Pest confirmation
- Pass 3 progress: PR 1+2+3+4+5 shipped + PR 6 staged → **5/8 PRs merged, 1 ready, PR 7+8 remaining**
- Last full Pest before tripwire: 4071 passed, 26 skipped, 0 failed (382s) on PR 5 final
- Boundary allowlist: shrunk from ~30 entries (start of session 3) to ~12 documented residuals
