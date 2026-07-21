---
type: audit
title: SP1 Pass 3 — pensions pre-pass code-state audit
date: 2026-05-25
spec_section: §3.1 #6 and §15.3 (Pass 3) of docs/superpowers/specs/2026-05-14-module-canonical-store-design.md
plan: docs/superpowers/plans/2026-05-24-sub-project-1-pass-3-pensions-plan.md
plan_dated: 2026-05-24
audit_dated: 2026-05-25
audit_pr: PR 0 of SP1 Pass 3
---

# SP1 Pass 3 — Pensions Pre-Pass Audit

Plan written 2026-05-24. Audit run 2026-05-25 (one day later) on `dev` at HEAD `72e6e5e` (post Pass 2 wrap-up: PRs #369–#374 all merged today).

## Mutation sites confirmed today

The plan's Step 0.1 grep (regex `(DCPension|DBPension|StatePension|PensionInputHistory)::(create|update|updateOrCreate)`) returned **15 hits** — but this regex misses instance-method calls (`$pension->update()`, `$pension->delete()`). A broader grep for `\$pension->(save|update|delete|forceDelete)` catches another **5 hits**.

**Total: 20 mutation sites across 8 files** (vs. plan's "17 mutation sites identified at planning time" — a wider-than-regex search recovers the 5 instance-method sites the plan's narrative descriptions already anticipated but its mechanical grep missed).

### Static-method mutations (15 sites, 7 files)

| File | Lines |
|---|---|
| `app/Agents/CoordinatingAgent.php` | `2431` (`DBPension::create`), `2465` (`DCPension::create`), `4089` (`PensionInputHistory::updateOrCreate`) |
| `app/Http/Controllers/Api/RetirementController.php` | `324` (`DCPension::create`), `477` (`DBPension::create`), `536` (`StatePension::updateOrCreate`) |
| `app/Http/Controllers/Api/PreviewController.php` | `534` (`DCPension::create`), `546` (`DBPension::create`), `561` (`StatePension::create`) |
| `app/Services/Documents/DocumentProcessor.php` | `389` (`DCPension::create`) |
| `database/seeders/PreviewUserSeeder.php` | `948` (`DCPension::create`), `1074` (`DBPension::create`), `1094` (`StatePension::create`), `1106` (`StatePension::create`) |
| `database/seeders/ChrisUserSeeder.php` | `211` (`DCPension::updateOrCreate`) |

### Instance-method mutations (5 sites, 2 files)

| File | Lines |
|---|---|
| `app/Agents/CoordinatingAgent.php` | `3975` (`$pension->update($payload)`) |
| `app/Http/Controllers/Api/RetirementController.php` | `392` (`$pension->update($data)`), `457` (`$pension->delete()`), `497` (`$pension->update(...)`), `517` (`$pension->delete()`) |

## Read consumers confirmed today

The plan's Step 0.2 grep returned **32 files** that reference `DCPension::`, `DBPension::`, `StatePension::`, or `PensionInputHistory::` in `app/Services/`, `app/Agents/`, `app/Jobs/` — vs. plan's "28 read consumers identified at planning time." Four-file delta is within noise; all 32 are already listed in the plan's "Modified files" table (lines 63–122).

Files matched:

```
app/Agents/CoordinatingAgent.php
app/Agents/RetirementAgent.php
app/Jobs/RecalculateRiskProfileJob.php
app/Services/AI/AdvicePromptBuilder.php
app/Services/AI/DuplicateAcknowledgement.php
app/Services/Coordination/CashFlowCoordinator.php
app/Services/Coordination/HouseholdPlanningService.php
app/Services/Documents/DocumentProcessor.php
app/Services/Documents/DocumentTypeDetector.php
app/Services/Documents/FieldMappers/DBPensionMapper.php
app/Services/Documents/FieldMappers/DCPensionMapper.php
app/Services/Documents/HoldingsImportService.php
app/Services/Estate/EstateActionDefinitionService.php
app/Services/Estate/EstateAssetAggregatorService.php
app/Services/Estate/IHTCalculationService.php
app/Services/Eval/EvalHttpDriver.php
app/Services/Goals/LifeEventAllocationService.php
app/Services/NetWorth/NetWorthService.php
app/Services/Onboarding/AssetCaptureEntityExtractor.php
app/Services/Plans/RetirementPlanService.php
app/Services/Retirement/AnnualAllowanceChecker.php
app/Services/Retirement/PensionContributionOptimizer.php
app/Services/Retirement/PensionPortfolioAnalyzer.php
app/Services/Retirement/PensionProjector.php
app/Services/Retirement/RetirementActionDefinitionService.php
app/Services/Retirement/RetirementIncomeService.php
app/Services/Risk/AutoRiskCalculator.php
app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php
app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php
app/Services/Tax/TaxStrategyMath.php
app/Services/UserProfile/ProfileCompletenessChecker.php
app/Services/UserProfile/UserProfileService.php
```

All 32 are covered by the plan's PR 5 "Modified files" table (with `NonEarnerSpousePensionStrategy.php`, `TaperedAnnualAllowanceStrategy.php`, `RetirementStrategyService.php`, `DecumulationPlanner.php`, `SalarySacrificeAnalyzer.php`, `RetirementDataReadinessService.php`, `RequiredCapitalCalculator.php`, `RetirementProjectionService.php` listed as additional PR-5 consumers that didn't appear in this grep's output — they likely import the models via `use` only, no `::` static reference. PR 5 should re-grep them with a different pattern to confirm scope.).

## Pass-1 / Pass-2 dependencies

| Dependency | Plan's check | Reality on `dev` HEAD `72e6e5e` |
|---|---|---|
| `App\Services\Stores\IngestSource` | OK | ✅ class exists |
| `App\Services\Stores\TierGate` | `class_exists` returns false | ⚠️ **`TierGate` is an interface, not a class** — `class_exists()` returns false; `interface_exists()` returns true. Interface exists at `app/Services/Stores/TierGate.php` and is bound in `AppServiceProvider` to `App\Services\Tiers\DbTierGate`. **The plan's audit grep is wrong; the dependency is live.** |
| `App\Services\Stores\Snapshots\SnapshotPolicy` | OK | ✅ class exists |
| `App\Services\Stores\TaxConfigStore` | OK | ✅ class exists (Pass 2 R1 shipped) |
| `App\Services\Stores\ActuarialLifeTableStore` | OK | ✅ class exists (Pass 2 R3 shipped) |

**Verdict on dependencies: ALL LIVE.** Pass 3 has the foundations it needs to execute.

## Plan adjustments needed

Two real and one cosmetic.

### Adjustment 1 (real) — `StaticTierGate::LIMITS` no longer exists

Plan §117 (PR 7) says:

> `app/Services/Stores/StaticTierGate.php` — PR 7 — add `pension_account` to the `LIMITS` constant: `['free' => 5, 'tier1' => null, 'tier2' => null, 'tier3' => null]` per spec §13.

`StaticTierGate` was **retired** before the plan was written. Confirmed by:

- `tests/Architecture/StoreBoundary/TierConfigBoundaryTest.php` asserts: `"it StaticTierGate class file does not exist in app/"` (passes today, 109/1 skipped).
- `find app -name "StaticTierGate.php"` returns nothing.
- `AppServiceProvider.php:62-63` binds `TierGate::class → DbTierGate::class`.

The current cap mechanism is `App\Services\Tiers\DbTierGate` reading from the `tier_configurations` database table. PR 7 of Pass 3 should be rewritten to:

- Seed a `pension_account` row into `tier_configurations` (via `TierConfigurationSeeder` or a follow-up data migration) with the `free => 5, tier1/tier2/tier3 => null` shape.
- Confirm `DbTierGate::canCreate($user, 'pension_account', $count)` resolves it.
- Update PR 7's commit message and test expectations accordingly.

No source-code class file at `app/Services/Stores/StaticTierGate.php` will be created.

### Adjustment 2 (real) — `PensionInputHistory::class` was added 2026-05-05

Pre-existing migration `2026_05_05_000001_create_pension_input_history_table.php` confirmed in the May-05 migration set. Plan correctly includes this as one of the 4 in-scope models (DC + DB + State + PensionInputHistory). No change needed — flagging for visibility because this is the youngest of the four models and the plan's TDD test fixtures must respect its schema.

### Adjustment 3 (cosmetic) — Pre-pass dependency-check grep uses wrong predicate

Plan Step 0.3 uses `class_exists(\App\Services\Stores\TierGate::class)`. Since `TierGate` is an interface, this always returns false even when the dependency is live. Replace with:

```php
echo (interface_exists(\App\Services\Stores\TierGate::class) || class_exists(\App\Services\Stores\TierGate::class)) ? 'TierGate OK' : 'TierGate MISSING';
```

This is a one-line plan edit; not blocking.

## Plan structural shape — confirmed

8 PRs + this PR 0 = 9 total. PR sequence:

| PR | Scope |
|---|---|
| PR 0 (this) | Audit — verify the plan against current code |
| PR 1 | `PensionStore` facade + `PensionNormaliser` + events + arch test |
| PR 2 | HTTP form-request mutations route through `PensionStore` |
| PR 3 | Fyn AI tool calls route through `PensionStore` |
| PR 4 | Upload extraction + seeders route through `PensionStore` |
| PR 5 | Read-consumer migration (5 sub-clusters per plan §lines 2528–2540) |
| PR 6 | Derived columns + snapshot policy |
| PR 7 | Tier-cap (`pension_account` entity-key into `DbTierGate` per Adjustment 1) |
| PR 8 | Boundary lock-down |

## Verdict

**READY to start Pass 3 PR 1**, with the following understandings carried forward:

1. PR 1 baseline mutation count is **20 sites across 8 files** (not the plan's stated 17). The 5 additional `$pension->update/delete()` instance-method sites are concentrated in `RetirementController.php` (4) + `CoordinatingAgent.php` (1) — both already in the plan's "Modified files" table, so no new files appear; the per-PR step counts in PR 2 + PR 3 need to be bumped to reflect the additional instance-method work.
2. PR 7 will route `pension_account` cap through `DbTierGate` + `tier_configurations` table seeding, NOT through a non-existent `StaticTierGate::LIMITS` constant.
3. The plan's grep predicates have two narrow gaps (instance-method mutations, interface-vs-class check). Recommend amending the live plan with the wider greps + `interface_exists` for any future re-audits.
4. All Pass 1 and Pass 2 dependencies are live and verified on `dev` HEAD `72e6e5e`.

No blocking issues. No reason to delay PR 1.
