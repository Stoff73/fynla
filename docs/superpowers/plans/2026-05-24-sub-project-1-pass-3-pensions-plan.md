---
title: Sub-Project 1, Pass 3 — Pensions Canonical Store Implementation Plan
date: 2026-05-24
spec: docs/superpowers/specs/2026-05-14-module-canonical-store-design.md
sub_project: 1 of 6 (Fynla major-overhaul series)
pass: 3 of 14 (Pensions — DC + DB + State + Pension Input History)
branch: dev (work on short-lived feature branches per PR; flow feature → dev → main per CLAUDE.md)
status: ready for execution
---

# Pensions Canonical Store Implementation Plan (SP1 Pass 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **Do not start implementation in the same session that produced this plan** — split across sessions for cache hygiene and to let CSJ review.

**Goal:** Build the `PensionStore` service facade so every read and write of `DCPension`, `DBPension`, `StatePension`, and `PensionInputHistory` goes through a single canonical API. Lock the boundary with a Pest architecture test that hard-fails CI on any direct model mutation outside the store. Materialise canonical derived columns (`current_fund_value_gbp`, `projected_value_at_retirement_gbp`, `years_to_drawdown`, `annual_allowance_used_gbp`, etc.) with snapshot tables. Wire the tier-cap hook for `pension_account`.

**Architecture:** Approach A (service facade over Eloquent) per spec §4. **One `PensionStore`** treats Pensions as one entity per spec §3.1 #6, but type-dispatches internally to four model writers. Why one store, not four: the spec dictates one store per entity (§4.1), and the canonical "list all pensions for user" use case is the dominant read pattern (every retirement / IHT / household-planning consumer wants the unified view). Per-type write methods (`createDc`, `createDb`, `upsertState`, `captureInputHistory`) keep the surface specific. A single `PensionNormaliser` with per-source methods (`fromForm`, `fromFyn`, `fromUpload`) handles the heterogeneity. Derived columns are materialised per pension table with `*_calculated_at` timestamps. Three snapshot tables (`dc_pension_value_snapshots`, `db_pension_value_snapshots`, `state_pension_value_snapshots`) preserve history per per-column `SnapshotPolicy`. The arch test ships hard-fail from PR 1 with an explicit allowlist of pre-existing direct-write sites that subsequent PRs progressively remove.

**Tech Stack:** Laravel 10 · PHP 8.2 · Pest 2.36 · Eloquent · Mockery · MySQL 8 · Vue 3 + Vuex · existing `Auditable` / `SoftDeletes` traits · existing `IngestSource` enum and `TierGate` interface shipped by SP1 Pass 1 · existing `SnapshotPolicy` / `SnapshotPolicies` value objects shipped by SP1 Pass 1 · existing `TaxConfigStore` shipped by SP1 Pass 2 R1.

---

## File Structure

### New files (created during this pass)

| Path | Responsibility |
|------|----------------|
| `app/Services/Stores/PensionStore.php` | The store facade. Owns all `DCPension`, `DBPension`, `StatePension`, `PensionInputHistory` mutation. Public read API exposes `find($id, $type, $user)`, `forUser($user)`, `forUserByType($user, $type)`, `statePension($user)`, `pensionInputHistory($user, $taxYear)`. Public write API exposes typed methods: `createDc`, `updateDc`, `deleteDc`, `createDb`, `updateDb`, `deleteDb`, `upsertState`, `captureInputHistory`. Internal `recalculateDerived` runs per-type after every write. |
| `app/Services/Stores/Normalisers/PensionNormaliser.php` | Maps form / fyn / upload arrays to the canonical input shape for each pension type. Methods: `fromFormDc`, `fromFormDb`, `fromFormState`, `fromFynPension` (the Fyn `create_pension` tool is type-switched internally), `fromUploadDc`, `fromUploadDb`, `fromFynInputHistory`. Each returns a canonical-shape array with explicit type discriminator. |
| `app/Events/Pension/DCPensionCreated.php` | Emitted by store after create. Carries the `DCPension` instance, `User`, and `IngestSource`. |
| `app/Events/Pension/DCPensionUpdated.php` | Emitted by store after update. Carries the `DCPension` instance, the `$changes` diff, `User`, and `IngestSource`. |
| `app/Events/Pension/DCPensionDeleted.php` | Emitted by store after delete. Carries the entity id, `User`, and reason string. |
| `app/Events/Pension/DCPensionRestored.php` | Emitted by store after restore. |
| `app/Events/Pension/DBPensionCreated.php` | Emitted by store after create (DB pension). |
| `app/Events/Pension/DBPensionUpdated.php` | Emitted by store after update (DB pension). |
| `app/Events/Pension/DBPensionDeleted.php` | Emitted by store after delete (DB pension). |
| `app/Events/Pension/DBPensionRestored.php` | Emitted by store after restore (DB pension). |
| `app/Events/Pension/StatePensionUpserted.php` | Emitted by store after `upsertState`. Single event because state pension is one-per-user, no separate created/updated semantics. Carries `wasRecentlyCreated` boolean for listeners that care. |
| `app/Events/Pension/PensionInputHistoryCaptured.php` | Emitted by store after `captureInputHistory` writes. Carries `User`, the tax-year-keyed map of `$written` amounts, and `IngestSource`. |
| `app/Services/Stores/Recalc/PensionDerivedColumnCalculator.php` | Computes derived columns for each pension type. Methods: `calculateDc($pension)`, `calculateDb($pension)`, `calculateState($pension, $user)`. Uses `TaxConfigStore` for AA / MPAA / state pension age / triple-lock estimation. |
| `app/Models/DCPensionValueSnapshot.php` | Eloquent model for the DC pension snapshot table. |
| `app/Models/DBPensionValueSnapshot.php` | Eloquent model for the DB pension snapshot table. |
| `app/Models/StatePensionValueSnapshot.php` | Eloquent model for the state pension snapshot table. |
| `database/migrations/2026_05_24_100000_add_derived_columns_to_dc_pensions.php` | Adds `current_fund_value_gbp`, `projected_value_at_retirement_gbp`, `annual_contribution_gbp`, `years_to_drawdown`, plus `_calculated_at` siblings, plus `annual_allowance_used_gbp` and `_calculated_at`. |
| `database/migrations/2026_05_24_100001_add_derived_columns_to_db_pensions.php` | Adds `projected_annual_pension_at_nra_gbp`, `spouse_pension_projected_gbp`, plus `_calculated_at` siblings. |
| `database/migrations/2026_05_24_100002_add_derived_columns_to_state_pensions.php` | Adds `state_pension_forecast_annual_gbp`, `ni_completion_pct`, `years_to_state_pension_age`, plus `_calculated_at` siblings. |
| `database/migrations/2026_05_24_100003_create_dc_pension_value_snapshots_table.php` | Snapshot table for DC pension derived columns. |
| `database/migrations/2026_05_24_100004_create_db_pension_value_snapshots_table.php` | Snapshot table for DB pension derived columns. |
| `database/migrations/2026_05_24_100005_create_state_pension_value_snapshots_table.php` | Snapshot table for state pension derived columns. |
| `app/Console/Commands/BackfillPensionDerivedColumns.php` | Artisan command `pensions:backfill-derived`. One-off backfill for existing rows across all three pension tables. |
| `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php` | Pest `arch()` tests for all four pension models. Hard-fails CI on direct mutation outside the store. Per-model assertion blocks; shared allowlist constants where they overlap. |
| `tests/Unit/Services/Stores/PensionStoreTest.php` | Unit tests for the store — every public method, validation paths, ownership checks, ingest-source plumbing. |
| `tests/Unit/Services/Stores/Normalisers/PensionNormaliserTest.php` | Unit tests for the normaliser — every `from*` method with realistic payloads. |
| `tests/Unit/Services/Stores/PensionStoreEventsTest.php` | Unit tests asserting every write emits the right event with the right payload. |
| `tests/Unit/Services/Stores/Recalc/PensionDerivedColumnCalculatorTest.php` | Unit tests for the derived-column calculator — DC, DB, State, with TaxConfigStore mocks. |
| `tests/Feature/Stores/PensionThreeIngestParityTest.php` | Three-ingest parity test per spec §16.1 #2 — form, fyn, upload produce byte-identical rows for DC pension (the only pension type with all three ingest paths). DB pension parity covers form+fyn only (no upload path today). State pension parity covers form+fyn only. |
| `tests/Feature/Stores/PensionTierCapTest.php` | Tier-cap enforcement — refuses a 6th DC+DB combined pension for free-tier users. |
| `tests/Feature/Stores/PensionAuditIngestSourceTest.php` | Asserts audit rows captured with `ingest_source` for every pension type. |
| `tests/Feature/Stores/PensionUploadIngestTest.php` | Feature test for the upload ingest path (DocumentProcessor → store with `IngestSource::UPLOAD`). |
| `tests/Feature/Stores/PensionDerivedColumnsBackfillTest.php` | Feature test for the backfill artisan command. |

### Modified files (touched during this pass)

| Path | Why |
|------|-----|
| `app/Http/Controllers/Api/RetirementController.php` | PR 2 — `storeDCPension` (line 305), `updateDCPension` (line 380), `destroyDCPension` (line 452), `storeDBPension` (line 471), `updateDBPension` (line 492), `destroyDBPension` (line 512), `updateStatePension` (line 531) all route through `PensionStore`. Direct `DCPension::create / update / find+update / find+delete`, `DBPension::create / update / find+update / find+delete`, `StatePension::updateOrCreate` calls removed. Holdings creation inside `storeDCPension` and `updateDCPension` stays in the controller (holdings are a separate entity not in scope for this pass — see "Out of scope" below). |
| `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php` | PR 2 — read calls to `DCPension::where(...)` for ownership verification migrate to `app(PensionStore::class)->find($id, 'dc', $user)`. Holdings CRUD stays as-is (`Holding::create / update / delete` is out of scope; that's the Investments pass). |
| `app/Agents/CoordinatingAgent.php` | PR 3 — `handleCreatePension` (line 2370–2480) shrinks to: validate tool input, dispatch to `PensionStore::createDc` or `PensionStore::createDb` based on `pension_category`, return existing AI response envelope. `handleCapturePensionHistory` (line 4067) routes through `PensionStore::captureInputHistory`. The generic `handleUpdateRecord` path for `dc_pension` / `db_pension` (line 4137+) routes through `PensionStore::updateDc` / `updateDb`. Direct `DBPension::create`, `DCPension::create`, `PensionInputHistory::updateOrCreate` calls removed. Read calls in CoordinatingAgent for duplicate check (lines 2389, 2393) migrate to `app(PensionStore::class)->forUserByType($user, 'dc')->where('scheme_name', $name)` — handled in PR 5h. |
| `app/Services/Documents/DocumentProcessor.php` | PR 4 — line 389 `DCPension::create($accountData)` routes through `PensionStore::createDc` with `IngestSource::UPLOAD`. DB pension upload path doesn't exist today (no `DBPensionMapper::create` site); leave it to a future PR if/when DB upload extraction lands. |
| `app/Http/Controllers/Api/PreviewController.php` | PR 4 — lines 534 (`DCPension::create`), 546 (`DBPension::create`), 561 (`StatePension::create`) all route through `PensionStore` with `IngestSource::SEEDER`. |
| `database/seeders/PreviewUserSeeder.php` | PR 4 — lines 948 (`DCPension::create`), 1074 (`DBPension::create`), 1094 / 1106 (`StatePension::create`) route through `PensionStore` with `IngestSource::SEEDER`. |
| `database/seeders/ChrisUserSeeder.php` | PR 4 — line 211 `DCPension::updateOrCreate` routes through a new `PensionStore::updateOrCreateDc(match, data, user, source)` method (mirrors the SavingsStore `updateOrCreate` shape added in Pass 1 PR 4). |
| `app/Models/User.php` | PR 5 — `dcPensions()` / `dbPensions()` / `statePension()` relationship methods stay (relationships are read-only). Audit any consumer reads through `$user->dcPensions` to confirm they don't mutate. |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | PR 5 — read consumer migration. Reads via `app(PensionStore::class)->forUser($user)` / `forUserByType($user, 'dc')`. |
| `app/Services/Retirement/AnnualAllowanceChecker.php` | PR 5 — read consumer. Also reads `PensionInputHistory` — adds via `PensionStore::pensionInputHistory($user)` or a new `pensionInputHistoryForRange($user, $taxYears)` helper if needed. |
| `app/Services/Retirement/PensionProjector.php` | PR 5 — read consumer for DC + DB + State. |
| `app/Services/Retirement/PensionContributionOptimizer.php` | PR 5 — read consumer for DC. |
| `app/Services/Retirement/RetirementIncomeService.php` | PR 5 — read consumer for all three pension types. |
| `app/Services/Retirement/PensionPortfolioAnalyzer.php` | PR 5 — read consumer for DC. |
| `app/Services/Retirement/RetirementStrategyService.php` | PR 5 — read consumer. |
| `app/Services/Retirement/DecumulationPlanner.php` | PR 5 — read consumer. |
| `app/Services/Retirement/SalarySacrificeAnalyzer.php` | PR 5 — read consumer (salary_sacrifice + employer_ni_rebate_pct fields on DC). |
| `app/Services/Retirement/RetirementDataReadinessService.php` | PR 5 — read consumer. |
| `app/Services/Retirement/RequiredCapitalCalculator.php` | PR 5 — read consumer. |
| `app/Services/Retirement/RetirementProjectionService.php` | PR 5 — read consumer. |
| `app/Services/Estate/IHTCalculationService.php` | PR 5 — DC pension fund values feed estate value (spec rule: pensions outside the estate by default, but flexibly-accessed funds count). |
| `app/Services/Estate/EstateAssetAggregatorService.php` | PR 5 — read consumer. |
| `app/Services/Estate/EstateActionDefinitionService.php` | PR 5 — read consumer. |
| `app/Services/Coordination/HouseholdPlanningService.php` | PR 5 — reads spouse pensions for household-level planning. |
| `app/Services/Coordination/CashFlowCoordinator.php` | PR 5 — pension contributions feed cash-flow projection. |
| `app/Services/Goals/LifeEventAllocationService.php` | PR 5 — pension drawdown feeds life-event allocation. |
| `app/Services/Plans/RetirementPlanService.php` | PR 5 — read consumer. |
| `app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php` | PR 5 — read consumer. |
| `app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php` | PR 5 — reads `DCPension` + `PensionInputHistory`. |
| `app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php` | PR 5 — read consumer. |
| `app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php` | PR 5 — read consumer. |
| `app/Services/Tax/TaxStrategyMath.php` | PR 5 — read consumer for DC + PensionInputHistory. |
| `app/Services/AI/DuplicateAcknowledgement.php` | PR 5 — Fyn duplicate-check consumer. |
| `app/Services/AI/AdvicePromptBuilder.php` | PR 5 — Fyn read consumer for advice prompt assembly. |
| `app/Services/UserProfile/ProfileCompletenessChecker.php` | PR 5 — profile consumer. |
| `app/Services/UserProfile/UserProfileService.php` | PR 5 — profile consumer. |
| `app/Services/Documents/HoldingsImportService.php` | PR 5 — read consumer (uses `DCPension` for holdings linkage). |
| `app/Services/Documents/DocumentTypeDetector.php` | PR 5 — read consumer (uses `DCPension::class` / `DBPension::class` as type constants). Constant references are not mutations — the arch test allows them; only direct `::create/save/update/delete/find+update/find+delete` patterns are forbidden. Re-grep to confirm. |
| `app/Services/Documents/FieldMappers/DBPensionMapper.php` | PR 5 — type constants only (no mutations); confirm. |
| `app/Services/Documents/FieldMappers/DCPensionMapper.php` | PR 5 — type constants only (no mutations); confirm. |
| `app/Services/Eval/EvalHttpDriver.php` | PR 5 — read consumer for eval scenario seeding. |
| `app/Services/NetWorth/NetWorthService.php` | PR 5 — DC pension fund values feed net-worth (PoR + spec §10.2). |
| `app/Services/Risk/AutoRiskCalculator.php` | PR 5 — read consumer (DC fund value feeds capacity-for-loss). |
| `app/Services/Onboarding/AssetCaptureEntityExtractor.php` | PR 5 — read calls at lines 246, 257 (`DCPension::query()`, `DBPension::query()`) for duplicate checks migrate to store reads. |
| `app/Agents/RetirementAgent.php` | PR 5 — `getDCPensions` (line 555), `formatDCPensions`, `formatDBPensions`, `formatStatePension`, `analyzeDCPensionPortfolio` (line 632) reads migrate to store. |
| `app/Agents/CoordinatingAgent.php` | PR 5 — read references at lines 1505, 1509, 2389, 2393, 3960, 4314, 4315 migrate to store reads (separate from PR 3's write migration of `handleCreatePension`). |
| `app/Jobs/RecalculateRiskProfileJob.php` | PR 5 — read consumer. |
| `app/Console/Commands/EncryptExistingData.php` | PR 5 — uses `DCPension::class` as a type list entry (no mutations); confirm. If it has direct `update` / `save` calls for backfill encryption, those stay on the allowlist (console commands are permanently allowed per spec §14.2). |
| `app/Console/Commands/ResetPreviewData.php` | PR 5 — type list entry only; confirm. |
| `app/Providers/EventServiceProvider.php` | PR 5 — register listeners for the new `DCPension*` / `DBPension*` / `StatePension*` / `PensionInputHistory*` events alongside existing observer registrations (existing observers stay during transition; sub-project decision later on whether to migrate observers to event listeners). |
| `app/Providers/AppServiceProvider.php` | PR 1 — no binding change needed (`TierGate` is already bound to `PermissiveTierGate` after Pass 1 PR 1, or `StaticTierGate` if Pass 1 PR 7 shipped; this pass extends `StaticTierGate::LIMITS` to add the `pension_account` entry — see PR 7). |
| `database/factories/DCPensionFactory.php` | PR 1 — ensure factory remains valid for tests (no behaviour change; the factory continues to call `DCPension` directly because factories are on the permanent allowlist per spec §14.2). |
| `database/factories/DBPensionFactory.php` | PR 1 — same. |
| `database/factories/StatePensionFactory.php` | PR 1 — same. |
| `app/Services/Stores/StaticTierGate.php` | PR 7 — add `pension_account` to the `LIMITS` constant: `['free' => 5, 'tier1' => null, 'tier2' => null, 'tier3' => null]` per spec §13. |

### Untouched (deliberately)

- `app/Models/DCPension.php`, `app/Models/DBPension.php`, `app/Models/StatePension.php`, `app/Models/PensionInputHistory.php` — PR 1 makes no model changes. Fillable, casts, relationships, soft-deletes, `Auditable` trait stay as-is. PR 6 adds `$casts` entries for the new decimal columns and adds the new column names to `$fillable`; no other model changes.
- `app/Models/Investment/Holding.php` — polymorphic relationship to DC pensions stays as-is. Holdings ARE NOT in scope for this pass — they're a separate entity that gets its own store in Pass 6 (Investments) per spec §15.3. Holdings continue to be written directly by `DCPensionHoldingsController` and `RetirementController::storeDCPension`. The Pension store's arch test specifically excludes `Holding::class` mutations from its scope; the Investment store's arch test will pick up holdings when Pass 6 ships.
- `app/Observers/DCPensionRiskObserver.php` — stays. Observers are on the spec §14.2 permanent allowlist. PR 1's arch test explicitly allows it.
- `app/Http/Resources/DCPensionResource.php` — output shape preserved exactly per spec §2.2 (Vue/HTTP API contract unchanged).
- `app/Http/Requests/Retirement/StoreDCPensionRequest.php`, `StoreDBPensionRequest.php`, `UpdateStatePensionRequest.php` — FormRequest validation stays where it is. Controllers shrink, but validation logic is the outer-layer ingest validation per spec §7.1.
- `resources/js/**` — zero frontend changes in pass 3. Vue stores keep reading `/api/retirement/*` responses; the backend swap is invisible to them.

---

## TDD discipline

Every task below follows the TDD micro-cycle:

1. Write the failing test.
2. Run the test and confirm it fails for the *right reason*.
3. Write the minimal implementation.
4. Run the test and confirm it passes.
5. Run the broader suite (`./vendor/bin/pest` for the affected module) and confirm no regressions.
6. Commit (one focused commit per micro-step, or one combined commit per `- [ ]` block — engineer's call as long as the failing-test step is in history).

**Run commands from the repo root** `/Users/CSJ/Desktop/fynla` — `vendor/` lives here.

**Browser testing law (CLAUDE.md Rule §15):** every PR ships with Playwright verification on csjones — click + fill + submit + verify DB + observe UI. No "verified by code review" claims.

**Branch flow per PR:** branch off `dev` → push → open PR `feature/xxx → dev` → admin-merge per `feedback_admin_merge_pattern_for_solo_reviewer_prs.md` → deploy to csjones → smoke → (later) backmerge to main.

**Pass-1 dependencies that MUST be in place before PR 1 starts:**
- `App\Services\Stores\IngestSource` enum (Pass 1 PR 1)
- `App\Services\Stores\TierGate` interface + `PermissiveTierGate` (Pass 1 PR 1; `StaticTierGate` from Pass 1 PR 7 if shipped)
- `App\Services\Stores\Exceptions\StoreValidationException` (Pass 1 PR 1)
- `App\Services\Stores\Exceptions\TierLimitExceededException` (Pass 1 PR 1)
- `App\Services\Stores\Snapshots\SnapshotPolicy` (Pass 1 PR 6)
- `App\Services\Stores\Snapshots\SnapshotPolicies` (Pass 1 PR 6 — extend in this pass with pension policies)

**Pass-2 dependencies that MUST be in place before PR 6 starts:**
- `App\Services\Stores\TaxConfigStore` (Pass 2 PR R1.1) — pension derived-column calculator reads tax config via the store, not via `TaxConfigService` legacy path.
- `App\Services\Stores\ActuarialLifeTableStore` (Pass 2 PR R3.1) — state-pension age / decumulation horizon uses actuarial life expectancy.

---

## Pre-pass verification (PR 0)

> **DO THIS FIRST.** Pensions is the most complex single entity in the SP1 series per spec §15.3 ("Most complex single entity (DB + DC + contributions + tax). If pattern survives this, it survives anything."). The 17 mutation sites and 28 read consumers identified at planning time may have shifted since 2026-05-24. This PR audits the current real state and adjusts the plan if reality has drifted.

**No code change. Just verification + a memory entry.**

### Step 0.1: Re-survey mutation sites

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && grep -rn "DCPension::create\|DCPension::update\|DCPension::updateOrCreate\|DBPension::create\|DBPension::update\|DBPension::updateOrCreate\|StatePension::create\|StatePension::update\|StatePension::updateOrCreate\|PensionInputHistory::create\|PensionInputHistory::update\|PensionInputHistory::updateOrCreate" app/ database/ 2>/dev/null
```

Compare the output against the "Modified files" table above. If new mutation sites have appeared since 2026-05-24, add them to the PR 2/3/4 file lists. If sites have been removed (e.g. by an unrelated refactor), drop them.

### Step 0.2: Re-survey read consumers

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && grep -rln "DCPension::\|DBPension::\|StatePension::\|PensionInputHistory::" app/Services/ app/Agents/ app/Jobs/ 2>/dev/null
```

Compare against the "Modified files" table. Adjust PR 5 cluster groupings if new consumers have landed.

### Step 0.3: Confirm Pass-1 + Pass-2 dependencies are live

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && php artisan tinker --execute="\
echo class_exists(\App\Services\Stores\IngestSource::class) ? 'IngestSource OK' : 'MISSING';\
echo PHP_EOL;\
echo class_exists(\App\Services\Stores\TierGate::class) ? 'TierGate OK' : 'MISSING';\
echo PHP_EOL;\
echo class_exists(\App\Services\Stores\Snapshots\SnapshotPolicy::class) ? 'SnapshotPolicy OK' : 'MISSING';\
echo PHP_EOL;\
echo class_exists(\App\Services\Stores\TaxConfigStore::class) ? 'TaxConfigStore OK' : 'MISSING';\
echo PHP_EOL;\
echo class_exists(\App\Services\Stores\ActuarialLifeTableStore::class) ? 'ActuarialLifeTableStore OK' : 'MISSING';\
"
```

Expected: all five "OK". If any are MISSING, the relevant Pass-1 or Pass-2 PR has not landed yet — **STOP**, surface to CSJ, do not proceed with Pass 3 PRs until the dependency is in place.

### Step 0.4: Write the pre-pass memo

- [ ] **Create `May/May24Updates/sp1-pass-3-pre-pass-audit-2026-05-24.md`:**

```markdown
---
type: audit
title: SP1 Pass 3 — pensions pre-pass code-state audit
date: 2026-05-24
spec_section: 3.1 #6 and 15.3 (Pass 3) of docs/superpowers/specs/2026-05-14-module-canonical-store-design.md
plan: docs/superpowers/plans/2026-05-24-sub-project-1-pass-3-pensions-plan.md
---

# SP1 Pass 3 — Pensions Pre-Pass Audit

## Mutation sites confirmed today
- [list every `grep` hit with file:line]

## Read consumers confirmed today
- [list every file]

## Pass-1 / Pass-2 dependencies
- IngestSource: [OK / MISSING]
- TierGate: [OK / MISSING]
- SnapshotPolicy: [OK / MISSING]
- TaxConfigStore: [OK / MISSING]
- ActuarialLifeTableStore: [OK / MISSING]

## Plan adjustments needed
- [none / list]

## Verdict
- [READY / BLOCKED on Pass X.Y dependency]
```

- [ ] **Commit:**

```bash
git add May/May24Updates/sp1-pass-3-pre-pass-audit-2026-05-24.md
git commit -m "docs(audit): SP1 pass-3 pensions pre-pass code-state audit"
```

This is the only output of PR 0. The rest of the plan continues regardless of audit verdict; the audit just informs adjustments to subsequent PRs.

---

## Task 1 — PR 1: Introduce `PensionStore` facade, `PensionNormaliser`, events, arch test

**PR title:** `feat(pensions): introduce PensionStore facade + validation + audit + boundary arch test`

**Files:**
- Create: `app/Services/Stores/PensionStore.php`
- Create: `app/Services/Stores/Normalisers/PensionNormaliser.php`
- Create: `app/Events/Pension/DCPensionCreated.php`
- Create: `app/Events/Pension/DCPensionUpdated.php`
- Create: `app/Events/Pension/DCPensionDeleted.php`
- Create: `app/Events/Pension/DCPensionRestored.php`
- Create: `app/Events/Pension/DBPensionCreated.php`
- Create: `app/Events/Pension/DBPensionUpdated.php`
- Create: `app/Events/Pension/DBPensionDeleted.php`
- Create: `app/Events/Pension/DBPensionRestored.php`
- Create: `app/Events/Pension/StatePensionUpserted.php`
- Create: `app/Events/Pension/PensionInputHistoryCaptured.php`
- Create: `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php`
- Create: `tests/Unit/Services/Stores/PensionStoreTest.php`
- Create: `tests/Unit/Services/Stores/Normalisers/PensionNormaliserTest.php`
- Create: `tests/Unit/Services/Stores/PensionStoreEventsTest.php`

### Step 1.1: Write the failing PensionNormaliser tests

- [ ] **Create `tests/Unit/Services/Stores/Normalisers/PensionNormaliserTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Services\Stores\Normalisers\PensionNormaliser;

describe('PensionNormaliser::fromFormDc', function () {
    it('produces canonical DC-pension shape from HTTP form payload', function () {
        $canonical = (new PensionNormaliser)->fromFormDc([
            'scheme_name' => 'Aviva Workplace',
            'pension_type' => 'occupational',
            'provider' => 'Aviva',
            'current_fund_value' => 45000,
            'annual_salary' => 60000,
            'employee_contribution_percent' => 5,
            'employer_contribution_percent' => 5,
            'retirement_age' => 65,
            'salary_sacrifice' => true,
        ]);

        expect($canonical['type'])->toBe('dc');
        expect($canonical['scheme_name'])->toBe('Aviva Workplace');
        expect($canonical['pension_type'])->toBe('occupational');
        expect((float) $canonical['current_fund_value'])->toBe(45000.00);
        expect($canonical['salary_sacrifice'])->toBeTrue();
    });

    it('defaults pension_type to occupational when not supplied', function () {
        $canonical = (new PensionNormaliser)->fromFormDc([
            'scheme_name' => 'X',
            'current_fund_value' => 1000,
        ]);

        expect($canonical['pension_type'])->toBe('occupational');
    });

    it('defaults provider to scheme_name when not supplied', function () {
        $canonical = (new PensionNormaliser)->fromFormDc([
            'scheme_name' => 'NEST',
            'current_fund_value' => 1000,
        ]);

        expect($canonical['provider'])->toBe('NEST');
    });
});

describe('PensionNormaliser::fromFormDb', function () {
    it('produces canonical DB-pension shape from HTTP form payload', function () {
        $canonical = (new PensionNormaliser)->fromFormDb([
            'scheme_name' => 'NHS 2015',
            'scheme_type' => 'career_average',
            'accrued_annual_pension' => 12000,
            'pensionable_service_years' => 15,
            'pensionable_salary' => 45000,
            'normal_retirement_age' => 67,
            'spouse_pension_percent' => 37.5,
            'lump_sum_entitlement' => 0,
            'inflation_protection' => 'cpi',
        ]);

        expect($canonical['type'])->toBe('db');
        expect($canonical['scheme_name'])->toBe('NHS 2015');
        expect($canonical['scheme_type'])->toBe('career_average');
        expect((float) $canonical['accrued_annual_pension'])->toBe(12000.00);
    });

    it('defaults scheme_type to final_salary when not in the allowlist', function () {
        $canonical = (new PensionNormaliser)->fromFormDb([
            'scheme_name' => 'X',
            'scheme_type' => 'made_up_type',
            'accrued_annual_pension' => 1,
        ]);

        expect($canonical['scheme_type'])->toBe('final_salary');
    });
});

describe('PensionNormaliser::fromFormState', function () {
    it('produces canonical state-pension shape from HTTP form payload', function () {
        $canonical = (new PensionNormaliser)->fromFormState([
            'ni_years_completed' => 28,
            'ni_years_required' => 35,
            'state_pension_forecast_annual' => 9000,
            'state_pension_age' => 67,
            'already_receiving' => false,
        ]);

        expect($canonical['type'])->toBe('state');
        expect((int) $canonical['ni_years_completed'])->toBe(28);
        expect((int) $canonical['ni_years_required'])->toBe(35);
        expect($canonical['already_receiving'])->toBeFalse();
    });
});

describe('PensionNormaliser::fromFynPension', function () {
    it('maps Fyn create_pension DC params to canonical', function () {
        $canonical = (new PensionNormaliser)->fromFynPension([
            'pension_category' => 'dc',
            'scheme_name' => 'Aviva SIPP',
            'scheme_type' => 'sipp',          // Fyn AI vocabulary
            'current_fund_value' => 25000,
            'monthly_contribution_amount' => 250,
            'employer_contribution_percent' => 3,
            'retirement_age' => 65,
        ]);

        expect($canonical['type'])->toBe('dc');
        expect($canonical['pension_type'])->toBe('sipp');   // mapped from scheme_type
        expect($canonical['provider'])->toBe('Aviva SIPP'); // defaulted to scheme_name
    });

    it('maps Fyn create_pension DB params to canonical', function () {
        $canonical = (new PensionNormaliser)->fromFynPension([
            'pension_category' => 'db',
            'scheme_name' => 'BT Final Salary',
            'scheme_type' => 'final_salary',
            'accrued_annual_pension' => 8000,
            'pensionable_service_years' => 10,
            'normal_retirement_age' => 60,
        ]);

        expect($canonical['type'])->toBe('db');
        expect($canonical['scheme_type'])->toBe('final_salary');
    });

    it('coerces Fyn scheme_type workplace -> occupational for DC', function () {
        $canonical = (new PensionNormaliser)->fromFynPension([
            'pension_category' => 'dc',
            'scheme_name' => 'NEST',
            'scheme_type' => 'workplace',
            'current_fund_value' => 1000,
        ]);

        expect($canonical['pension_type'])->toBe('occupational');
    });
});

describe('PensionNormaliser::fromFynInputHistory', function () {
    it('maps Fyn capture_pension_history entries to canonical per-year shape', function () {
        $canonical = (new PensionNormaliser)->fromFynInputHistory([
            ['tax_year' => '2024-25', 'pension_input_amount' => 9000],
            ['tax_year' => '2025-26', 'pension_input_amount' => 12000],
            ['tax_year' => '2025-26', 'pension_input_amount' => -50], // invalid: negative
            ['tax_year' => '', 'pension_input_amount' => 1000],        // invalid: empty year
        ]);

        // Only the two valid entries survive
        expect($canonical['entries'])->toHaveCount(2);
        expect($canonical['entries'][0]['tax_year'])->toBe('2024-25');
        expect((float) $canonical['entries'][0]['pension_input_amount'])->toBe(9000.00);
    });
});

describe('PensionNormaliser::fromUploadDc', function () {
    it('maps a DC pension document-extraction shape to canonical', function () {
        $canonical = (new PensionNormaliser)->fromUploadDc([
            'scheme_name' => 'Standard Life',
            'provider' => 'Standard Life',
            'current_fund_value' => 32500,
            'pension_type' => 'personal',
            'source_document_id' => 99, // upload-only metadata, dropped
        ]);

        expect($canonical['type'])->toBe('dc');
        expect($canonical['scheme_name'])->toBe('Standard Life');
        expect((float) $canonical['current_fund_value'])->toBe(32500.00);
        expect($canonical)->not->toHaveKey('source_document_id');
    });
});
```

- [ ] **Run and confirm fails:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/Normalisers/PensionNormaliserTest.php
```

Expected: FAIL — `class App\Services\Stores\Normalisers\PensionNormaliser not found`.

### Step 1.2: Implement PensionNormaliser

- [ ] **Create `app/Services/Stores/Normalisers/PensionNormaliser.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

class PensionNormaliser
{
    private const ALLOWED_DB_SCHEME_TYPES = ['final_salary', 'career_average', 'public_sector'];

    private const ALLOWED_DC_PENSION_TYPES = ['occupational', 'sipp', 'personal', 'stakeholder'];

    /**
     * Map HTTP form-validated DC pension input to canonical shape.
     * Replicates the defaulting + sanity logic that previously lived in
     * RetirementController::storeDCPension.
     */
    public function fromFormDc(array $request): array
    {
        $data = $request;
        $data['type'] = 'dc';
        $data['pension_type'] = $data['pension_type'] ?? 'occupational';
        $data['provider'] = $data['provider'] ?? ($data['scheme_name'] ?? null);

        return $data;
    }

    /**
     * Map HTTP form-validated DB pension input to canonical shape.
     * Replicates the scheme_type sanitisation that previously lived in
     * RetirementController::storeDBPension and CoordinatingAgent::handleCreatePension.
     */
    public function fromFormDb(array $request): array
    {
        $data = $request;
        $data['type'] = 'db';
        $rawSchemeType = $data['scheme_type'] ?? 'final_salary';
        $data['scheme_type'] = in_array($rawSchemeType, self::ALLOWED_DB_SCHEME_TYPES, true)
            ? $rawSchemeType
            : 'final_salary';

        return $data;
    }

    /**
     * Map HTTP form-validated State pension input to canonical shape.
     */
    public function fromFormState(array $request): array
    {
        $data = $request;
        $data['type'] = 'state';

        return $data;
    }

    /**
     * Map Fyn AI create_pension tool params to canonical shape.
     * The tool dispatches by pension_category (dc|db) and the normaliser
     * routes to the typed branch internally.
     */
    public function fromFynPension(array $toolParams): array
    {
        $category = $toolParams['pension_category'] ?? 'dc';

        if ($category === 'db') {
            $rawSchemeType = $toolParams['scheme_type'] ?? 'final_salary';
            $schemeType = in_array($rawSchemeType, self::ALLOWED_DB_SCHEME_TYPES, true)
                ? $rawSchemeType
                : 'final_salary';

            $canonical = [
                'type' => 'db',
                'scheme_name' => $toolParams['scheme_name'],
                'scheme_type' => $schemeType,
            ];

            foreach (['accrued_annual_pension', 'pensionable_service_years', 'pensionable_salary', 'spouse_pension_percent', 'lump_sum_entitlement'] as $f) {
                if (isset($toolParams[$f]) && is_numeric($toolParams[$f])) {
                    $canonical[$f] = (float) $toolParams[$f];
                }
            }
            if (isset($toolParams['normal_retirement_age']) && is_numeric($toolParams['normal_retirement_age'])) {
                $canonical['normal_retirement_age'] = (int) $toolParams['normal_retirement_age'];
            }
            foreach (['revaluation_method', 'inflation_protection'] as $f) {
                if (isset($toolParams[$f]) && $toolParams[$f] !== '') {
                    $canonical[$f] = $toolParams[$f];
                }
            }

            return $canonical;
        }

        // DC default
        $pensionType = match ($toolParams['scheme_type'] ?? 'workplace') {
            'workplace', 'occupational' => 'occupational',
            'sipp', 'self_invested' => 'sipp',
            'personal', 'personal_pension' => 'personal',
            'stakeholder' => 'stakeholder',
            default => 'occupational',
        };

        $canonical = [
            'type' => 'dc',
            'scheme_name' => $toolParams['scheme_name'],
            'pension_type' => $pensionType,
            'provider' => ! empty($toolParams['provider']) ? $toolParams['provider'] : $toolParams['scheme_name'],
        ];

        foreach (['current_fund_value', 'annual_salary', 'employee_contribution_percent', 'employer_contribution_percent', 'employer_matching_limit', 'monthly_contribution_amount', 'lump_sum_contribution', 'expected_return_percent', 'platform_fee_percent', 'advisor_fee_percent'] as $f) {
            if (isset($toolParams[$f]) && is_numeric($toolParams[$f])) {
                $canonical[$f] = (float) $toolParams[$f];
            }
        }
        if (isset($toolParams['retirement_age']) && is_numeric($toolParams['retirement_age'])) {
            $canonical['retirement_age'] = (int) $toolParams['retirement_age'];
        }
        foreach (['member_number', 'investment_strategy'] as $f) {
            if (isset($toolParams[$f]) && $toolParams[$f] !== '') {
                $canonical[$f] = $toolParams[$f];
            }
        }

        return $canonical;
    }

    /**
     * Map Fyn capture_pension_history tool params to canonical shape.
     * Drops malformed / negative / blank-year entries; returns an envelope
     * with the filtered entries so the store can iterate cleanly.
     */
    public function fromFynInputHistory(array $toolParams): array
    {
        $history = $toolParams['history'] ?? $toolParams;
        if (! is_array($history)) {
            return ['type' => 'pension_input_history', 'entries' => []];
        }

        $entries = [];
        foreach ($history as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $taxYear = isset($entry['tax_year']) ? (string) $entry['tax_year'] : null;
            $amount = isset($entry['pension_input_amount']) ? (float) $entry['pension_input_amount'] : null;
            if ($taxYear === null || $taxYear === '' || $amount === null || $amount < 0) {
                continue;
            }
            $entries[] = [
                'tax_year' => $taxYear,
                'pension_input_amount' => $amount,
            ];
        }

        return [
            'type' => 'pension_input_history',
            'entries' => $entries,
        ];
    }

    /**
     * Map a DC pension document-extraction shape to canonical.
     * Document extraction can't produce holdings or salary-sacrifice info,
     * so those default; the user edits afterwards.
     */
    public function fromUploadDc(array $extraction): array
    {
        $canonical = [
            'type' => 'dc',
            'scheme_name' => $extraction['scheme_name'] ?? $extraction['provider'] ?? 'Imported pension',
            'pension_type' => $extraction['pension_type'] ?? 'occupational',
            'provider' => $extraction['provider'] ?? ($extraction['scheme_name'] ?? null),
            'current_fund_value' => (float) ($extraction['current_fund_value'] ?? 0),
        ];

        foreach (['annual_salary', 'employee_contribution_percent', 'employer_contribution_percent', 'monthly_contribution_amount', 'retirement_age', 'member_number', 'investment_strategy', 'platform_fee_percent'] as $optional) {
            if (array_key_exists($optional, $extraction)) {
                $canonical[$optional] = $extraction[$optional];
            }
        }

        return $canonical;
    }

    /**
     * Map a DB pension document-extraction shape to canonical.
     */
    public function fromUploadDb(array $extraction): array
    {
        $rawSchemeType = $extraction['scheme_type'] ?? 'final_salary';
        $schemeType = in_array($rawSchemeType, self::ALLOWED_DB_SCHEME_TYPES, true)
            ? $rawSchemeType
            : 'final_salary';

        $canonical = [
            'type' => 'db',
            'scheme_name' => $extraction['scheme_name'] ?? 'Imported DB pension',
            'scheme_type' => $schemeType,
        ];

        foreach (['accrued_annual_pension', 'pensionable_service_years', 'pensionable_salary', 'normal_retirement_age', 'spouse_pension_percent', 'lump_sum_entitlement', 'inflation_protection'] as $optional) {
            if (array_key_exists($optional, $extraction)) {
                $canonical[$optional] = $extraction[$optional];
            }
        }

        return $canonical;
    }
}
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/Normalisers/PensionNormaliserTest.php
```

Expected: PASS, all 12 cases green.

### Step 1.3: Write the failing PensionStore unit tests

- [ ] **Create `tests/Unit/Services/Stores/PensionStoreTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\PensionInputHistory;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PensionStore;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('PensionStore::createDc persists a DC pension through the canonical write path', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDc([
        'scheme_name' => 'Aviva Workplace',
        'pension_type' => 'occupational',
        'provider' => 'Aviva',
        'current_fund_value' => 45000,
        'retirement_age' => 65,
    ], $user, IngestSource::FORM);

    expect($pension)->toBeInstanceOf(DCPension::class);
    expect($pension->user_id)->toBe($user->id);
    expect($pension->scheme_name)->toBe('Aviva Workplace');
    expect((float) $pension->current_fund_value)->toBe(45000.00);
    expect(DCPension::count())->toBe(1);
});

it('PensionStore::createDc rejects writes with missing required fields', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    expect(fn () => $store->createDc(['pension_type' => 'occupational'], $user, IngestSource::FORM))
        ->toThrow(StoreValidationException::class);

    expect(DCPension::count())->toBe(0);
});

it('PensionStore::updateDc mutates a DC pension through the canonical write path', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 10000,
    ]);

    $updated = $store->updateDc($pension->id, ['current_fund_value' => 12500], $user, IngestSource::FORM);

    expect((float) $updated->current_fund_value)->toBe(12500.00);
});

it('PensionStore::deleteDc soft-deletes a DC pension', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = DCPension::factory()->create(['user_id' => $user->id]);

    $store->deleteDc($pension->id, $user, 'user_requested');

    expect(DCPension::find($pension->id))->toBeNull();
    expect(DCPension::withTrashed()->find($pension->id))->not->toBeNull();
});

it('PensionStore::createDb persists a DB pension', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDb([
        'scheme_name' => 'NHS 2015',
        'scheme_type' => 'career_average',
        'accrued_annual_pension' => 12000,
        'normal_retirement_age' => 67,
    ], $user, IngestSource::FORM);

    expect($pension)->toBeInstanceOf(DBPension::class);
    expect($pension->user_id)->toBe($user->id);
    expect($pension->scheme_type)->toBe('career_average');
    expect(DBPension::count())->toBe(1);
});

it('PensionStore::upsertState inserts when no row exists', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $state = $store->upsertState([
        'ni_years_completed' => 28,
        'ni_years_required' => 35,
        'state_pension_forecast_annual' => 9000,
        'state_pension_age' => 67,
    ], $user, IngestSource::FORM);

    expect($state)->toBeInstanceOf(StatePension::class);
    expect(StatePension::where('user_id', $user->id)->count())->toBe(1);
});

it('PensionStore::upsertState updates when row exists (one-per-user invariant)', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $store->upsertState(['ni_years_completed' => 20, 'ni_years_required' => 35], $user, IngestSource::FORM);
    $store->upsertState(['ni_years_completed' => 25], $user, IngestSource::FORM);

    expect(StatePension::where('user_id', $user->id)->count())->toBe(1);
    expect((int) StatePension::where('user_id', $user->id)->first()->ni_years_completed)->toBe(25);
});

it('PensionStore::captureInputHistory writes one row per tax_year', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $written = $store->captureInputHistory([
        ['tax_year' => '2024-25', 'pension_input_amount' => 9000],
        ['tax_year' => '2025-26', 'pension_input_amount' => 12000],
    ], $user, IngestSource::FYN_AI);

    expect($written)->toBe(['2024-25' => 9000.0, '2025-26' => 12000.0]);
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(2);
});

it('PensionStore::captureInputHistory updates an existing tax_year row idempotently', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $store->captureInputHistory([['tax_year' => '2024-25', 'pension_input_amount' => 5000]], $user, IngestSource::FYN_AI);
    $store->captureInputHistory([['tax_year' => '2024-25', 'pension_input_amount' => 9000]], $user, IngestSource::FYN_AI);

    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(1);
    $row = PensionInputHistory::where('user_id', $user->id)->first();
    expect((float) $row->pension_input_amount)->toBe(9000.00);
});

it('PensionStore::find returns the right pension by type', function () {
    $user = User::factory()->create();
    $dc = DCPension::factory()->create(['user_id' => $user->id]);
    $db = DBPension::factory()->create(['user_id' => $user->id]);

    $store = app(PensionStore::class);

    expect($store->find($dc->id, 'dc', $user)->id)->toBe($dc->id);
    expect($store->find($db->id, 'db', $user)->id)->toBe($db->id);
    expect($store->find(999, 'dc', $user))->toBeNull();
});

it('PensionStore::forUser returns all pensions across all types', function () {
    $user = User::factory()->create();
    DCPension::factory(2)->create(['user_id' => $user->id]);
    DBPension::factory(1)->create(['user_id' => $user->id]);
    StatePension::factory()->create(['user_id' => $user->id]);

    $all = app(PensionStore::class)->forUser($user);

    expect($all['dc'])->toHaveCount(2);
    expect($all['db'])->toHaveCount(1);
    expect($all['state'])->not->toBeNull();
});

it('PensionStore::statePension returns null for a user without one', function () {
    $user = User::factory()->create();
    expect(app(PensionStore::class)->statePension($user))->toBeNull();
});

it('PensionStore::updateDc refuses to mutate a pension owned by another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $owner->id]);

    expect(fn () => app(PensionStore::class)->updateDc($pension->id, ['current_fund_value' => 1], $intruder, IngestSource::FORM))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
```

- [ ] **Run and confirm fails:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/PensionStoreTest.php
```

Expected: FAIL — `class App\Services\Stores\PensionStore not found`.

### Step 1.4: Implement the minimal PensionStore

- [ ] **Create `app/Services/Stores/PensionStore.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\PensionInputHistory;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\Normalisers\PensionNormaliser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PensionStore
{
    public const ENTITY_KEY = 'pension_account';

    public function __construct(
        private readonly PensionNormaliser $normaliser,
        private readonly TierGate $tierGate,
    ) {}

    // ---------- Reads ----------

    public function find(int $id, string $type, User $user): DCPension|DBPension|StatePension|null
    {
        $model = $this->modelClassForType($type);

        return $model::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Return every pension the user owns, grouped by type.
     *
     * @return array{dc: Collection, db: Collection, state: ?StatePension, input_history: Collection}
     */
    public function forUser(User $user): array
    {
        return [
            'dc' => DCPension::where('user_id', $user->id)->with('holdings')->get(),
            'db' => DBPension::where('user_id', $user->id)->get(),
            'state' => StatePension::where('user_id', $user->id)->first(),
            'input_history' => PensionInputHistory::where('user_id', $user->id)->orderBy('tax_year')->get(),
        ];
    }

    public function forUserByType(User $user, string $type): Collection
    {
        $model = $this->modelClassForType($type);

        return $model::query()->where('user_id', $user->id)->get();
    }

    public function statePension(User $user): ?StatePension
    {
        return StatePension::where('user_id', $user->id)->first();
    }

    public function pensionInputHistory(User $user, ?string $taxYear = null): Collection|PensionInputHistory|null
    {
        $query = PensionInputHistory::where('user_id', $user->id);
        if ($taxYear !== null) {
            return $query->where('tax_year', $taxYear)->first();
        }

        return $query->orderBy('tax_year')->get();
    }

    // ---------- Writes (DC pension) ----------

    public function createDc(array $data, User $user, IngestSource $source): DCPension
    {
        $this->validateDcCanonical($data);
        $this->enforceTierCap($user);

        $payload = array_merge($data, ['user_id' => $user->id]);
        unset($payload['type']);

        $pension = DB::transaction(fn () => DCPension::create($payload));

        event(new \App\Events\Pension\DCPensionCreated($pension, $user, $source));

        return $pension;
    }

    public function updateDc(int $id, array $data, User $user, IngestSource $source): DCPension
    {
        $pension = DCPension::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateDcCanonical($data, partial: true);

        $payload = $data;
        unset($payload['type']);

        $dirty = [];
        $updated = DB::transaction(function () use ($pension, $payload, &$dirty) {
            $pension->fill($payload);
            $dirty = $pension->getDirty();
            $pension->save();

            return $pension->fresh();
        });

        event(new \App\Events\Pension\DCPensionUpdated($updated, $dirty, $user, $source));

        return $updated;
    }

    public function updateOrCreateDc(array $match, array $data, User $user, IngestSource $source): DCPension
    {
        $existing = DCPension::where('user_id', $user->id)->where($match)->first();

        if ($existing) {
            return $this->updateDc($existing->id, $data, $user, $source);
        }

        return $this->createDc(array_merge($match, $data), $user, $source);
    }

    public function deleteDc(int $id, User $user, string $reason): void
    {
        $pension = DCPension::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $pension->delete();

        event(new \App\Events\Pension\DCPensionDeleted($id, $user, $reason));
    }

    public function restoreDc(int $id, User $user): DCPension
    {
        $pension = DCPension::withTrashed()->where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $pension->restore();

        event(new \App\Events\Pension\DCPensionRestored($pension, $user));

        return $pension;
    }

    // ---------- Writes (DB pension) ----------

    public function createDb(array $data, User $user, IngestSource $source): DBPension
    {
        $this->validateDbCanonical($data);
        $this->enforceTierCap($user);

        $payload = array_merge($data, ['user_id' => $user->id]);
        unset($payload['type']);

        $pension = DB::transaction(fn () => DBPension::create($payload));

        event(new \App\Events\Pension\DBPensionCreated($pension, $user, $source));

        return $pension;
    }

    public function updateDb(int $id, array $data, User $user, IngestSource $source): DBPension
    {
        $pension = DBPension::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateDbCanonical($data, partial: true);

        $payload = $data;
        unset($payload['type']);

        $dirty = [];
        $updated = DB::transaction(function () use ($pension, $payload, &$dirty) {
            $pension->fill($payload);
            $dirty = $pension->getDirty();
            $pension->save();

            return $pension->fresh();
        });

        event(new \App\Events\Pension\DBPensionUpdated($updated, $dirty, $user, $source));

        return $updated;
    }

    public function deleteDb(int $id, User $user, string $reason): void
    {
        $pension = DBPension::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $pension->delete();

        event(new \App\Events\Pension\DBPensionDeleted($id, $user, $reason));
    }

    public function restoreDb(int $id, User $user): DBPension
    {
        $pension = DBPension::withTrashed()->where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $pension->restore();

        event(new \App\Events\Pension\DBPensionRestored($pension, $user));

        return $pension;
    }

    // ---------- Writes (State pension — one per user) ----------

    public function upsertState(array $data, User $user, IngestSource $source): StatePension
    {
        $this->validateStateCanonical($data);

        $payload = $data;
        unset($payload['type']);

        $state = DB::transaction(fn () => StatePension::updateOrCreate(
            ['user_id' => $user->id],
            $payload
        ));

        event(new \App\Events\Pension\StatePensionUpserted($state, $user, $source, wasRecentlyCreated: $state->wasRecentlyCreated));

        return $state;
    }

    // ---------- Writes (Pension Input History — one per user per tax year) ----------

    /**
     * @return array<string, float>  tax_year => pension_input_amount of successfully written rows
     */
    public function captureInputHistory(array $entries, User $user, IngestSource $source): array
    {
        // Entries may already be a normaliser output envelope; unwrap if so.
        if (isset($entries['entries']) && is_array($entries['entries'])) {
            $entries = $entries['entries'];
        }

        $written = [];
        DB::transaction(function () use ($entries, $user, &$written) {
            foreach ($entries as $entry) {
                if (! isset($entry['tax_year'], $entry['pension_input_amount'])) {
                    continue;
                }
                if ((float) $entry['pension_input_amount'] < 0 || (string) $entry['tax_year'] === '') {
                    continue;
                }

                PensionInputHistory::updateOrCreate(
                    ['user_id' => $user->id, 'tax_year' => (string) $entry['tax_year']],
                    ['pension_input_amount' => (float) $entry['pension_input_amount']]
                );
                $written[(string) $entry['tax_year']] = (float) $entry['pension_input_amount'];
            }
        });

        if ($written === []) {
            throw new StoreValidationException(['history' => ['No valid history entries provided.']]);
        }

        event(new \App\Events\Pension\PensionInputHistoryCaptured($user, $written, $source));

        return $written;
    }

    // ---------- Internal ----------

    private function modelClassForType(string $type): string
    {
        return match ($type) {
            'dc' => DCPension::class,
            'db' => DBPension::class,
            'state' => StatePension::class,
            default => throw new \InvalidArgumentException("Unknown pension type '{$type}'."),
        };
    }

    private function enforceTierCap(User $user): void
    {
        $count = DCPension::where('user_id', $user->id)->count()
            + DBPension::where('user_id', $user->id)->count();

        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $count)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $count,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY)
            );
        }
    }

    private function validateDcCanonical(array $data, bool $partial = false): void
    {
        $rules = [
            'scheme_name' => ($partial ? 'sometimes|' : 'required|').'string|max:255',
            'pension_type' => 'sometimes|in:occupational,sipp,personal,stakeholder',
            'provider' => 'sometimes|nullable|string|max:255',
            'current_fund_value' => 'sometimes|numeric|min:0|max:999999999.99',
            'annual_salary' => 'sometimes|nullable|numeric|min:0|max:999999999.99',
            'employee_contribution_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'employer_contribution_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'employer_matching_limit' => 'sometimes|nullable|numeric|min:0|max:100',
            'monthly_contribution_amount' => 'sometimes|nullable|numeric|min:0',
            'lump_sum_contribution' => 'sometimes|nullable|numeric|min:0',
            'platform_fee_percent' => 'sometimes|nullable|numeric|min:0|max:10',
            'platform_fee_amount' => 'sometimes|nullable|numeric|min:0',
            'advisor_fee_percent' => 'sometimes|nullable|numeric|min:0|max:10',
            'retirement_age' => 'sometimes|nullable|integer|min:50|max:75',
            'expected_return_percent' => 'sometimes|nullable|numeric|min:0|max:20',
            'has_flexibly_accessed' => 'sometimes|boolean',
            'flexible_access_date' => 'sometimes|nullable|date|before_or_equal:today',
            'salary_sacrifice' => 'sometimes|boolean',
            'employer_ni_rebate_pct' => 'sometimes|nullable|numeric|min:0|max:1',
            'beneficiary_id' => 'sometimes|nullable|integer|exists:users,id',
            'beneficiary_name' => 'sometimes|nullable|string|max:255',
            'investment_strategy' => 'sometimes|nullable|string|max:255',
            'member_number' => 'sometimes|nullable|string|max:255',
            'risk_preference' => 'sometimes|nullable|string|max:64',
            'has_custom_risk' => 'sometimes|boolean',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }

    private function validateDbCanonical(array $data, bool $partial = false): void
    {
        $rules = [
            'scheme_name' => ($partial ? 'sometimes|' : 'required|').'string|max:255',
            'scheme_type' => ($partial ? 'sometimes|' : 'required|').'in:final_salary,career_average,public_sector',
            'accrued_annual_pension' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'pensionable_service_years' => 'sometimes|nullable|numeric|min:0|max:99',
            'pensionable_salary' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'normal_retirement_age' => 'sometimes|nullable|integer|min:50|max:75',
            'revaluation_method' => 'sometimes|nullable|string|max:64',
            'spouse_pension_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'lump_sum_entitlement' => 'sometimes|nullable|numeric|min:0',
            'inflation_protection' => 'sometimes|nullable|string|max:64',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }

    private function validateStateCanonical(array $data): void
    {
        $rules = [
            'ni_years_completed' => 'sometimes|nullable|integer|min:0|max:50',
            'ni_years_required' => 'sometimes|nullable|integer|min:0|max:50',
            'state_pension_forecast_annual' => 'sometimes|nullable|numeric|min:0|max:99999.99',
            'state_pension_age' => 'sometimes|nullable|integer|min:55|max:75',
            'already_receiving' => 'sometimes|boolean',
            'ni_gaps' => 'sometimes|nullable|array',
            'gap_fill_cost' => 'sometimes|nullable|numeric|min:0',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
```

- [ ] **Create the 12 event classes** (each is a 10-line value object — pattern below for DCPensionCreated; repeat for all 12 with the obvious shape mirrors per the File Structure table):

```php
// app/Events/Pension/DCPensionCreated.php
<?php declare(strict_types=1);
namespace App\Events\Pension;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\IngestSource;

class DCPensionCreated
{
    public function __construct(
        public readonly DCPension $entity,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
```

```php
// app/Events/Pension/DCPensionUpdated.php
<?php declare(strict_types=1);
namespace App\Events\Pension;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\IngestSource;

class DCPensionUpdated
{
    public function __construct(
        public readonly DCPension $entity,
        public readonly array $changes,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
```

```php
// app/Events/Pension/DCPensionDeleted.php
<?php declare(strict_types=1);
namespace App\Events\Pension;
use App\Models\User;

class DCPensionDeleted
{
    public function __construct(
        public readonly int $entityId,
        public readonly User $user,
        public readonly string $reason,
    ) {}
}
```

```php
// app/Events/Pension/DCPensionRestored.php
<?php declare(strict_types=1);
namespace App\Events\Pension;
use App\Models\DCPension;
use App\Models\User;

class DCPensionRestored
{
    public function __construct(
        public readonly DCPension $entity,
        public readonly User $user,
    ) {}
}
```

```php
// app/Events/Pension/DBPensionCreated.php
<?php declare(strict_types=1);
namespace App\Events\Pension;
use App\Models\DBPension;
use App\Models\User;
use App\Services\Stores\IngestSource;

class DBPensionCreated
{
    public function __construct(
        public readonly DBPension $entity,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
```

```php
// app/Events/Pension/DBPensionUpdated.php
<?php declare(strict_types=1);
namespace App\Events\Pension;
use App\Models\DBPension;
use App\Models\User;
use App\Services\Stores\IngestSource;

class DBPensionUpdated
{
    public function __construct(
        public readonly DBPension $entity,
        public readonly array $changes,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
```

```php
// app/Events/Pension/DBPensionDeleted.php
<?php declare(strict_types=1);
namespace App\Events\Pension;
use App\Models\User;

class DBPensionDeleted
{
    public function __construct(
        public readonly int $entityId,
        public readonly User $user,
        public readonly string $reason,
    ) {}
}
```

```php
// app/Events/Pension/DBPensionRestored.php
<?php declare(strict_types=1);
namespace App\Events\Pension;
use App\Models\DBPension;
use App\Models\User;

class DBPensionRestored
{
    public function __construct(
        public readonly DBPension $entity,
        public readonly User $user,
    ) {}
}
```

```php
// app/Events/Pension/StatePensionUpserted.php
<?php declare(strict_types=1);
namespace App\Events\Pension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Stores\IngestSource;

class StatePensionUpserted
{
    public function __construct(
        public readonly StatePension $entity,
        public readonly User $user,
        public readonly IngestSource $source,
        public readonly bool $wasRecentlyCreated,
    ) {}
}
```

```php
// app/Events/Pension/PensionInputHistoryCaptured.php
<?php declare(strict_types=1);
namespace App\Events\Pension;
use App\Models\User;
use App\Services\Stores\IngestSource;

class PensionInputHistoryCaptured
{
    public function __construct(
        public readonly User $user,
        /** @var array<string, float> tax_year => pension_input_amount */
        public readonly array $written,
        public readonly IngestSource $source,
    ) {}
}
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/PensionStoreTest.php
```

Expected: PASS, all 13 cases green.

### Step 1.5: Write the failing event tests

- [ ] **Create `tests/Unit/Services/Stores/PensionStoreEventsTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Events\Pension\DBPensionCreated;
use App\Events\Pension\DCPensionCreated;
use App\Events\Pension\DCPensionDeleted;
use App\Events\Pension\DCPensionUpdated;
use App\Events\Pension\PensionInputHistoryCaptured;
use App\Events\Pension\StatePensionUpserted;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PensionStore;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('createDc emits DCPensionCreated with source', function () {
    Event::fake();
    $user = User::factory()->create();

    app(PensionStore::class)->createDc(
        ['scheme_name' => 'Aviva', 'current_fund_value' => 1000],
        $user,
        IngestSource::FORM
    );

    Event::assertDispatched(DCPensionCreated::class, function ($e) use ($user) {
        return $e->user->id === $user->id && $e->source === IngestSource::FORM;
    });
});

it('updateDc emits DCPensionUpdated with changes diff', function () {
    Event::fake();
    $user = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 1000]);

    app(PensionStore::class)->updateDc($pension->id, ['current_fund_value' => 2500], $user, IngestSource::FORM);

    Event::assertDispatched(DCPensionUpdated::class, function ($e) {
        return array_key_exists('current_fund_value', $e->changes);
    });
});

it('deleteDc emits DCPensionDeleted with reason', function () {
    Event::fake();
    $user = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $user->id]);

    app(PensionStore::class)->deleteDc($pension->id, $user, 'user_requested');

    Event::assertDispatched(DCPensionDeleted::class, function ($e) {
        return $e->reason === 'user_requested';
    });
});

it('createDb emits DBPensionCreated', function () {
    Event::fake();
    $user = User::factory()->create();

    app(PensionStore::class)->createDb(
        ['scheme_name' => 'NHS', 'scheme_type' => 'career_average', 'accrued_annual_pension' => 5000],
        $user,
        IngestSource::FYN_AI
    );

    Event::assertDispatched(DBPensionCreated::class);
});

it('upsertState emits StatePensionUpserted with wasRecentlyCreated boolean', function () {
    Event::fake();
    $user = User::factory()->create();

    app(PensionStore::class)->upsertState(['ni_years_completed' => 10], $user, IngestSource::FORM);

    Event::assertDispatched(StatePensionUpserted::class, function ($e) {
        return $e->wasRecentlyCreated === true;
    });
});

it('captureInputHistory emits PensionInputHistoryCaptured with the written map', function () {
    Event::fake();
    $user = User::factory()->create();

    app(PensionStore::class)->captureInputHistory(
        [['tax_year' => '2024-25', 'pension_input_amount' => 7500]],
        $user,
        IngestSource::FYN_AI
    );

    Event::assertDispatched(PensionInputHistoryCaptured::class, function ($e) {
        return $e->written === ['2024-25' => 7500.0] && $e->source === IngestSource::FYN_AI;
    });
});
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/PensionStoreEventsTest.php
```

Expected: PASS, 6 cases.

### Step 1.6: Write the failing arch test with full transition allowlist

- [ ] **Create `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php`:**

```php
<?php

declare(strict_types=1);

/**
 * Sub-Project 1, Pass 3 — Pensions store boundary enforcement.
 *
 * Hard-fails CI on any direct mutation of App\Models\DCPension,
 * App\Models\DBPension, App\Models\StatePension, or
 * App\Models\PensionInputHistory outside the canonical write path
 * (App\Services\Stores\PensionStore).
 *
 * Allowlist (§14.2 of the spec): observers, migrations, seeders, console
 * commands, the store itself, and pre-existing direct-mutation sites
 * that subsequent PRs in this pass will migrate. Each entry below has a
 * comment naming the PR that removes it.
 */

$pensionConsumers = [
    // Permanent allowlist (per spec §14.2)
    'App\Services\Stores\PensionStore',
    'App\Services\Stores\Normalisers\PensionNormaliser',
    'App\Services\Stores\Recalc\PensionDerivedColumnCalculator', // lands in PR 6
    'App\Observers\DCPensionRiskObserver',
    'Database\Factories\DCPensionFactory',
    'Database\Factories\DBPensionFactory',
    'Database\Factories\StatePensionFactory',
    // Snapshot models, lands in PR 6
    'App\Models\DCPensionValueSnapshot',
    'App\Models\DBPensionValueSnapshot',
    'App\Models\StatePensionValueSnapshot',
    'App\Models\\',  // self-references in relationships

    // Transition allowlist — removed by subsequent PRs in pass 3.
    // PR 2 removes: RetirementController, DCPensionHoldingsController
    'App\Http\Controllers\Api\RetirementController',
    'App\Http\Controllers\Api\Retirement\DCPensionHoldingsController',
    // PR 3 removes: CoordinatingAgent (Fyn AI tool path)
    'App\Agents\CoordinatingAgent',
    // PR 4 removes: DocumentProcessor (upload path), PreviewController, seeders
    'App\Services\Documents\DocumentProcessor',
    'App\Http\Controllers\Api\PreviewController',
    'Database\Seeders\PreviewUserSeeder',
    'Database\Seeders\ChrisUserSeeder',
    // PR 5 removes: read consumers
    'App\Agents\RetirementAgent',
    'App\Services\Retirement\RetirementActionDefinitionService',
    'App\Services\Retirement\AnnualAllowanceChecker',
    'App\Services\Retirement\PensionProjector',
    'App\Services\Retirement\PensionContributionOptimizer',
    'App\Services\Retirement\RetirementIncomeService',
    'App\Services\Retirement\PensionPortfolioAnalyzer',
    'App\Services\Retirement\RetirementStrategyService',
    'App\Services\Retirement\DecumulationPlanner',
    'App\Services\Retirement\SalarySacrificeAnalyzer',
    'App\Services\Retirement\RetirementDataReadinessService',
    'App\Services\Retirement\RequiredCapitalCalculator',
    'App\Services\Retirement\RetirementProjectionService',
    'App\Services\Estate\IHTCalculationService',
    'App\Services\Estate\EstateAssetAggregatorService',
    'App\Services\Estate\EstateActionDefinitionService',
    'App\Services\Coordination\HouseholdPlanningService',
    'App\Services\Coordination\CashFlowCoordinator',
    'App\Services\Goals\LifeEventAllocationService',
    'App\Services\Plans\RetirementPlanService',
    'App\Services\Tax\Strategies\SalarySacrificeNiStrategy',
    'App\Services\Tax\Strategies\PensionAACarryForwardStrategy',
    'App\Services\Tax\Strategies\NonEarnerSpousePensionStrategy',
    'App\Services\Tax\Strategies\TaperedAnnualAllowanceStrategy',
    'App\Services\Tax\TaxStrategyMath',
    'App\Services\AI\DuplicateAcknowledgement',
    'App\Services\AI\AdvicePromptBuilder',
    'App\Services\UserProfile\ProfileCompletenessChecker',
    'App\Services\UserProfile\UserProfileService',
    'App\Services\Documents\HoldingsImportService',
    'App\Services\Documents\DocumentTypeDetector',
    'App\Services\Documents\FieldMappers\DBPensionMapper',
    'App\Services\Documents\FieldMappers\DCPensionMapper',
    'App\Services\Eval\EvalHttpDriver',
    'App\Services\NetWorth\NetWorthService',
    'App\Services\Risk\AutoRiskCalculator',
    'App\Services\Onboarding\AssetCaptureEntityExtractor',
    'App\Jobs\RecalculateRiskProfileJob',
    'App\Console\Commands\EncryptExistingData',
    'App\Console\Commands\ResetPreviewData',
    'App\Models\User', // relationships only — confirmed read-only at planning time
];

arch('DCPension is only used inside the pensions canonical set (plus transition allowlist)')
    ->expect('App\Models\DCPension')
    ->toOnlyBeUsedIn($pensionConsumers);

arch('DBPension is only used inside the pensions canonical set (plus transition allowlist)')
    ->expect('App\Models\DBPension')
    ->toOnlyBeUsedIn($pensionConsumers);

arch('StatePension is only used inside the pensions canonical set (plus transition allowlist)')
    ->expect('App\Models\StatePension')
    ->toOnlyBeUsedIn($pensionConsumers);

arch('PensionInputHistory is only used inside the pensions canonical set (plus transition allowlist)')
    ->expect('App\Models\PensionInputHistory')
    ->toOnlyBeUsedIn($pensionConsumers);

arch('App\Services\Stores classes use strict types')
    ->expect('App\Services\Stores')
    ->toUseStrictTypes();
```

- [ ] **Run the architecture suite to confirm green:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest --testsuite=Architecture
```

Expected: PASS. Note for the engineer: this test starts hard but with a wide allowlist. Subsequent PRs shrink the allowlist; PR 8 confirms it's down to the permanent entries only.

### Step 1.7: Run the full suite

- [ ] **Sanity check — no regressions anywhere:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: all suites green. Total ~4028+ cases.

### Step 1.8: Commit PR 1

- [ ] **Stage and commit:**

```bash
cd /Users/CSJ/Desktop/fynla && git add \
  app/Services/Stores/PensionStore.php \
  app/Services/Stores/Normalisers/PensionNormaliser.php \
  app/Events/Pension/ \
  tests/Unit/Services/Stores/PensionStoreTest.php \
  tests/Unit/Services/Stores/PensionStoreEventsTest.php \
  tests/Unit/Services/Stores/Normalisers/PensionNormaliserTest.php \
  tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php

cd /Users/CSJ/Desktop/fynla && git commit -m "$(cat <<'EOF'
feat(pensions): introduce PensionStore facade + arch boundary

Sub-project 1 / pass 3 / PR 1. Lays the foundation:

- App\Services\Stores\PensionStore (canonical write path for all four
  pension models: DC, DB, State, PensionInputHistory).
- Per-type write methods: createDc, updateDc, deleteDc, restoreDc,
  createDb, updateDb, deleteDb, restoreDb, upsertState,
  captureInputHistory, updateOrCreateDc.
- Per-type read methods: find($id, $type, $user), forUser($user),
  forUserByType($user, $type), statePension($user),
  pensionInputHistory($user, ?$taxYear).
- App\Services\Stores\Normalisers\PensionNormaliser with fromFormDc,
  fromFormDb, fromFormState, fromFynPension, fromFynInputHistory,
  fromUploadDc, fromUploadDb.
- 12 storage event classes under App\Events\Pension\* — separate
  Created/Updated/Deleted/Restored for DC and DB; single Upserted for
  State (one-per-user); Captured for PensionInputHistory.
- tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php hard-
  failing in CI from this PR with explicit transition allowlist.

No consumers wired yet — that lands across PR 2 (HTTP form), PR 3
(Fyn AI), PR 4 (upload + seeders), PR 5 (read consumers). The
allowlist names each file each subsequent PR removes.

Spec: docs/superpowers/specs/2026-05-14-module-canonical-store-design.md
Plan: docs/superpowers/plans/2026-05-24-sub-project-1-pass-3-pensions-plan.md

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Push and open PR `feature/pension-store-pr1` → `dev`:**

```bash
cd /Users/CSJ/Desktop/fynla && git checkout -b feature/pension-store-pr1
cd /Users/CSJ/Desktop/fynla && git push -u origin feature/pension-store-pr1
gh pr create --base dev --title "feat(pensions): introduce PensionStore facade + boundary arch test" --body "$(cat <<'EOF'
## Summary
- New \`App\Services\Stores\PensionStore\` facade — type-dispatched write methods covering DC, DB, State, and PensionInputHistory.
- \`App\Services\Stores\Normalisers\PensionNormaliser\` — per-source maps (form / fyn / upload).
- 12 storage event classes under \`App\Events\Pension\*\`.
- Pest arch test hard-fails CI on direct mutations outside the store, with an explicit transition allowlist that subsequent PRs remove.

## Test plan
- [x] \`./vendor/bin/pest tests/Unit/Services/Stores/PensionStoreTest.php\` passes (13 cases)
- [x] \`./vendor/bin/pest tests/Unit/Services/Stores/Normalisers/PensionNormaliserTest.php\` passes (12 cases)
- [x] \`./vendor/bin/pest tests/Unit/Services/Stores/PensionStoreEventsTest.php\` passes (6 cases)
- [x] \`./vendor/bin/pest --testsuite=Architecture\` passes
- [x] \`./vendor/bin/pest\` (full suite) passes — no regressions
- [ ] csjones smoke: navigate \`/retirement\` for a seeded test user, confirm existing flows still work (this PR is pure addition, no behavioural change yet)

## Browser-test plan
1. Login chris@fynla.org → MFA → dashboard
2. Open \`/retirement\` → confirm DC + DB + State pension cards load
3. Verify zero JS errors, zero new entries in laravel.log

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

### Step 1.9: csjones browser smoke

- [ ] **Deploy `feature/pension-store-pr1` to csjones per CLAUDE.md "Deploying to dev" + `feedback_deploy_gate_csjones_before_admin_merge.md`. Then drive the browser test plan above via Playwright.**

(Use the Playwright MCP tools — `mcp__playwright__browser_navigate`, `browser_fill_form`, `browser_click`, `browser_snapshot`. No "verified by code review" claims.)

- [ ] **Only after csjones smoke is green: admin-merge per `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`:**

```bash
gh pr merge <PR#> --merge --admin
```

---

## Task 2 — PR 2: Point HTTP form requests at `PensionStore`

**PR title:** `refactor(pensions): point HTTP form requests at PensionStore`

**Files:**
- Modify: `app/Http/Controllers/Api/RetirementController.php` (`storeDCPension`, `updateDCPension`, `destroyDCPension`, `storeDBPension`, `updateDBPension`, `destroyDBPension`, `updateStatePension`)
- Modify: `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php` (ownership-check reads via store; holdings mutation stays out of scope)
- Modify: `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php` (remove `RetirementController`, `DCPensionHoldingsController` from allowlist)
- Add: `tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php` (asserts canonical-shape contract through HTTP)

### Step 2.1: Pre-flight — confirm existing feature tests still pass

- [ ] **Run baseline:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/AI/DirectWrite/CreatePensionTest.php tests/Feature/AI/DirectWrite/CapturePensionHistoryTest.php
```

Capture pass counts. These MUST stay green after PR 2 and PR 3 land.

### Step 2.2: Add the failing integration test

- [ ] **Create `tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('HTTP POST /api/retirement/dc-pensions persists via PensionStore', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/retirement/dc-pensions', [
        'scheme_name' => 'Aviva Workplace',
        'pension_type' => 'occupational',
        'provider' => 'Aviva',
        'current_fund_value' => 45000,
        'employee_contribution_percent' => 5,
        'employer_contribution_percent' => 5,
        'retirement_age' => 65,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('dc_pensions', [
        'user_id' => $user->id,
        'scheme_name' => 'Aviva Workplace',
        'pension_type' => 'occupational',
        'current_fund_value' => 45000,
    ]);
});

it('HTTP PUT /api/retirement/dc-pensions/{id} mutates via PensionStore', function () {
    $user = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 1000]);
    Sanctum::actingAs($user);

    $this->putJson("/api/retirement/dc-pensions/{$pension->id}", [
        'current_fund_value' => 3000,
    ])->assertOk();

    expect((float) $pension->fresh()->current_fund_value)->toBe(3000.00);
});

it('HTTP POST /api/retirement/db-pensions persists via PensionStore', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/retirement/db-pensions', [
        'scheme_name' => 'NHS 2015',
        'scheme_type' => 'career_average',
        'accrued_annual_pension' => 12000,
        'normal_retirement_age' => 67,
    ])->assertCreated();

    $this->assertDatabaseHas('db_pensions', [
        'user_id' => $user->id,
        'scheme_name' => 'NHS 2015',
        'scheme_type' => 'career_average',
    ]);
});

it('HTTP PUT /api/retirement/state-pension is idempotent (one row per user)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/retirement/state-pension', ['ni_years_completed' => 20, 'ni_years_required' => 35])->assertOk();
    $this->putJson('/api/retirement/state-pension', ['ni_years_completed' => 25])->assertOk();

    expect(StatePension::where('user_id', $user->id)->count())->toBe(1);
    expect((int) StatePension::where('user_id', $user->id)->first()->ni_years_completed)->toBe(25);
});

it('HTTP DELETE /api/retirement/dc-pensions/{id} soft-deletes via PensionStore', function () {
    $user = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->deleteJson("/api/retirement/dc-pensions/{$pension->id}")->assertOk();

    expect(DCPension::find($pension->id))->toBeNull();
    expect(DCPension::withTrashed()->find($pension->id))->not->toBeNull();
});

it('HTTP DELETE /api/retirement/db-pensions/{id} returns 404 when foreign user attempts delete', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $pension = DBPension::factory()->create(['user_id' => $owner->id]);
    Sanctum::actingAs($intruder);

    $this->deleteJson("/api/retirement/db-pensions/{$pension->id}")->assertNotFound();

    expect(DBPension::find($pension->id))->not->toBeNull();
});
```

- [ ] **Run baseline (test SHOULD pass even pre-refactor — the routes already exist):**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php
```

Expected: PASS. These cases lock the canonical-shape contract; they MUST stay PASS through the refactor.

### Step 2.3: Refactor `RetirementController::storeDCPension`

- [ ] **Replace the body of `storeDCPension` (currently lines 305–375) with:**

```php
public function storeDCPension(StoreDCPensionRequest $request): JsonResponse
{
    $user = $request->user();
    $data = $request->validated();

    // Auto-assign main risk level if the user has a risk profile
    $riskProfile = RiskProfile::where('user_id', $user->id)->first();
    if ($riskProfile && $riskProfile->risk_level) {
        $data['risk_preference'] = $riskProfile->risk_level;
    }

    // Extract holdings before creating pension (holdings are out of scope for
    // the PensionStore — they get their own store in Pass 6).
    $holdings = $data['holdings'] ?? [];
    unset($data['holdings']);

    $pension = null;

    try {
        DB::transaction(function () use ($data, $holdings, $user, &$pension) {
            $canonical = $this->pensionNormaliser->fromFormDc($data);
            $pension = $this->pensionStore->createDc($canonical, $user, IngestSource::FORM);

            $this->seedHoldingsForDcPension($pension, $holdings);
        });
    } catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
        return $this->validationErrorResponse('Validation failed', $e->errors);
    } catch (\App\Services\Stores\Exceptions\TierLimitExceededException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Pension limit reached for your tier',
            'error_type' => 'tier_limit_exceeded',
        ], 403);
    }

    $this->invalidateRetirementCache($user->id);
    $pension->load('holdings');

    return response()->json([
        'success' => true,
        'message' => 'DC pension added successfully',
        'data' => new DCPensionResource($pension),
    ], 201);
}

/**
 * Extracted from the original storeDCPension/updateDCPension bodies — the
 * holdings-creation block is unchanged behaviour, just lifted into a
 * private method so the controller can call it from both entry points.
 * Holdings creation will move into HoldingsStore in Pass 6.
 */
private function seedHoldingsForDcPension(DCPension $pension, array $holdings): void
{
    if (empty($holdings)) {
        return;
    }

    $hasCashHolding = false;

    foreach ($holdings as $holdingData) {
        $currentValue = ($pension->current_fund_value * $holdingData['allocation_percent']) / 100;

        if (($holdingData['asset_type'] ?? '') === 'cash') {
            $hasCashHolding = true;
        }

        $pension->holdings()->create([
            'holdable_type' => DCPension::class,
            'holdable_id' => $pension->id,
            'security_name' => $holdingData['security_name'],
            'asset_type' => $holdingData['asset_type'] ?? 'fund',
            'allocation_percent' => $holdingData['allocation_percent'],
            'current_value' => $currentValue,
            'ocf_percent' => $holdingData['ocf_percent'] ?? 0,
            'cost_basis' => $holdingData['cost_basis'] ?? null,
        ]);
    }

    $totalAllocated = collect($holdings)->sum('allocation_percent');
    if ($totalAllocated < 100 && ! $hasCashHolding) {
        $remainderPercent = 100 - $totalAllocated;
        $pension->holdings()->create([
            'holdable_type' => DCPension::class,
            'holdable_id' => $pension->id,
            'security_name' => 'Cash',
            'asset_type' => 'cash',
            'allocation_percent' => $remainderPercent,
            'current_value' => ($pension->current_fund_value * $remainderPercent) / 100,
        ]);
    }
}
```

- [ ] **Add to the constructor and imports at the top of the class:**

```php
use App\Services\Stores\IngestSource;
use App\Services\Stores\Normalisers\PensionNormaliser;
use App\Services\Stores\PensionStore;

// In the existing __construct() — add these two private readonly params:
private readonly PensionStore $pensionStore,
private readonly PensionNormaliser $pensionNormaliser,
```

### Step 2.4: Refactor `RetirementController::updateDCPension`

- [ ] **Replace the body of `updateDCPension` (currently lines 380–447) with:**

```php
public function updateDCPension(StoreDCPensionRequest $request, int $id): JsonResponse
{
    $user = $request->user();
    $data = $request->validated();

    $holdings = $data['holdings'] ?? null;
    unset($data['holdings']);

    try {
        $pension = DB::transaction(function () use ($id, $data, $holdings, $user) {
            $canonical = $this->pensionNormaliser->fromFormDc($data);
            $pension = $this->pensionStore->updateDc($id, $canonical, $user, IngestSource::FORM);

            if ($holdings !== null) {
                $pension->holdings()->delete();
                $this->seedHoldingsForDcPension($pension, $holdings);
            }

            return $pension;
        });
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['success' => false, 'message' => 'DC pension not found or unauthorized'], 404);
    } catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
        return $this->validationErrorResponse('Validation failed', $e->errors);
    }

    $this->invalidateRetirementCache($user->id);
    $pension->load('holdings');

    return response()->json([
        'success' => true,
        'message' => 'DC pension updated successfully',
        'data' => $pension,
    ]);
}
```

### Step 2.5: Refactor `RetirementController::destroyDCPension`

- [ ] **Replace the body (lines 452–466) with:**

```php
public function destroyDCPension(Request $request, int $id): JsonResponse
{
    $user = $request->user();

    try {
        $this->pensionStore->deleteDc($id, $user, 'user_requested');
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['success' => false, 'message' => 'DC pension not found or unauthorized'], 404);
    }

    $this->invalidateRetirementCache($user->id);

    return response()->json([
        'success' => true,
        'message' => 'DC pension deleted successfully',
    ]);
}
```

### Step 2.6: Refactor `RetirementController::storeDBPension`

- [ ] **Replace the body (lines 471–487) with:**

```php
public function storeDBPension(StoreDBPensionRequest $request): JsonResponse
{
    $user = $request->user();

    try {
        $canonical = $this->pensionNormaliser->fromFormDb($request->validated());
        $pension = $this->pensionStore->createDb($canonical, $user, IngestSource::FORM);
    } catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
        return $this->validationErrorResponse('Validation failed', $e->errors);
    } catch (\App\Services\Stores\Exceptions\TierLimitExceededException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Pension limit reached for your tier',
            'error_type' => 'tier_limit_exceeded',
        ], 403);
    }

    $this->invalidateRetirementCache($user->id);

    return response()->json([
        'success' => true,
        'message' => 'DB pension added successfully',
        'data' => $pension,
    ], 201);
}
```

### Step 2.7: Refactor `updateDBPension` and `destroyDBPension`

- [ ] **Replace `updateDBPension` body (lines 492–507) with:**

```php
public function updateDBPension(StoreDBPensionRequest $request, int $id): JsonResponse
{
    $user = $request->user();

    try {
        $canonical = $this->pensionNormaliser->fromFormDb($request->validated());
        $pension = $this->pensionStore->updateDb($id, $canonical, $user, IngestSource::FORM);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['success' => false, 'message' => 'DB pension not found or unauthorized'], 404);
    } catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
        return $this->validationErrorResponse('Validation failed', $e->errors);
    }

    $this->invalidateRetirementCache($user->id);

    return response()->json([
        'success' => true,
        'message' => 'DB pension updated successfully',
        'data' => $pension,
    ]);
}
```

- [ ] **Replace `destroyDBPension` body (lines 512–526) with:**

```php
public function destroyDBPension(Request $request, int $id): JsonResponse
{
    $user = $request->user();

    try {
        $this->pensionStore->deleteDb($id, $user, 'user_requested');
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['success' => false, 'message' => 'DB pension not found or unauthorized'], 404);
    }

    $this->invalidateRetirementCache($user->id);

    return response()->json([
        'success' => true,
        'message' => 'DB pension deleted successfully',
    ]);
}
```

### Step 2.8: Refactor `updateStatePension`

- [ ] **Replace the body (lines 531–549) with:**

```php
public function updateStatePension(UpdateStatePensionRequest $request): JsonResponse
{
    $user = $request->user();

    try {
        $canonical = $this->pensionNormaliser->fromFormState($request->validated());
        $statePension = $this->pensionStore->upsertState($canonical, $user, IngestSource::FORM);
    } catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
        return $this->validationErrorResponse('Validation failed', $e->errors);
    }

    $this->invalidateRetirementCache($user->id);

    return response()->json([
        'success' => true,
        'message' => 'State Pension information updated successfully',
        'data' => $statePension,
    ]);
}
```

### Step 2.9: Refactor `analyzeDCPensionPortfolio` ownership check

- [ ] **Replace the `DCPension::where(...)->findOrFail($dcPensionId)` line at ~line 567 with a store read:**

```php
public function analyzeDCPensionPortfolio(Request $request, ?int $dcPensionId = null): JsonResponse
{
    $user = $request->user();

    if ($dcPensionId) {
        $pension = $this->pensionStore->find($dcPensionId, 'dc', $user);
        if (! $pension) {
            return response()->json(['success' => false, 'message' => 'DC pension not found'], 404);
        }
    }

    $analysis = $this->agent->analyzeDCPensionPortfolio($user->id, $dcPensionId);

    return response()->json([
        'success' => true,
        'message' => 'DC pension portfolio analysis completed',
        'data' => $analysis,
    ]);
}
```

### Step 2.10: Refactor remaining read sites in `RetirementController`

- [ ] **Audit lines 96–98, 567, 728, 786 in `RetirementController`. These are read-only `DCPension::where(...)` calls. Migrate to `$this->pensionStore->forUserByType($user, 'dc')` or `$this->pensionStore->find($id, 'dc', $user)`. Each call site should be wrapped in a try/catch only if the read can fail (e.g. missing pension by id) — pure listings don't need it.**

### Step 2.11: Refactor `DCPensionHoldingsController` ownership reads

- [ ] **In `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php`, replace every `DCPension::where('user_id', $user->id)->where('id', $dcPensionId)->firstOrFail()` with:**

```php
// In each method (index, store, update, destroy, bulkUpdate):
$pension = app(\App\Services\Stores\PensionStore::class)->find($dcPensionId, 'dc', $user);
if (! $pension) {
    return response()->json(['success' => false, 'message' => 'DC pension not found'], 404);
}
```

(Inject `PensionStore` via the constructor instead of `app(...)` for cleanliness — controllers should resolve dependencies through the container.)

Holdings creation / update / delete logic remains untouched — `Holding::create / update / delete` is out of scope for this pass (Pass 6).

### Step 2.12: Update arch test allowlist — remove `RetirementController` + `DCPensionHoldingsController`

- [ ] **Edit `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php`. Delete from the `$pensionConsumers` array:**

```
'App\Http\Controllers\Api\RetirementController',
'App\Http\Controllers\Api\Retirement\DCPensionHoldingsController',
```

### Step 2.13: Run full suite

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

### Step 2.14: Commit + PR + csjones smoke

- [ ] **Commit:**

```bash
cd /Users/CSJ/Desktop/fynla && git add -A
cd /Users/CSJ/Desktop/fynla && git commit -m "$(cat <<'EOF'
refactor(pensions): point HTTP form requests at PensionStore

Pass 3 / PR 2. RetirementController shrinks to controller-shaped work
(validate, normalise, call store, return resource). Direct
DCPension::create/update/find+delete, DBPension::create/update/find+delete,
StatePension::updateOrCreate calls all removed from the controller body.
Holdings creation extracted into a private seedHoldingsForDcPension()
helper — holdings remain out of scope (Pass 6).

DCPensionHoldingsController ownership-check reads now use
PensionStore::find('dc') instead of a direct DCPension query.
Holdings CRUD stays as-is (out of scope).

Boundary allowlist shrinks: RetirementController +
DCPensionHoldingsController removed.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Push branch `feature/pension-store-pr2`, open PR → `dev`, run the same browser smoke as PR 1 plus extra coverage for /api/retirement/* CRUD via Playwright (create DC, edit DC, delete DC, create DB, update State). Admin-merge after csjones smoke is green.**

---

## Task 3 — PR 3: Point Fyn AI write tools at `PensionStore`

**PR title:** `refactor(pensions): point Fyn AI write tools at PensionStore`

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php` (`handleCreatePension` ~line 2370-2480, `handleCapturePensionHistory` ~line 4067)
- Modify: `app/Services/Stores/PensionStore.php` (no change — already exposes the right methods from PR 1)
- Modify: `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php` (do NOT remove `CoordinatingAgent` yet — it still has read references at lines 1505, 1509, 2389, 2393, 3960, 4314, 4315 that PR 5h migrates)
- Modify: `tests/Feature/AI/DirectWrite/CreatePensionTest.php`, `tests/Feature/AI/DirectWrite/CapturePensionHistoryTest.php` (existing — assert envelope unchanged)

### Step 3.1: Re-run existing AI direct-write tests as baseline

- [ ] **Run before refactor:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/AI/DirectWrite/CreatePensionTest.php tests/Feature/AI/DirectWrite/CapturePensionHistoryTest.php
```

Capture pass counts. These cases MUST stay green after the refactor; the envelope shape (`success`, `created`, `entity_type`, `entity_id`, `name`, `persisted_fields`) is byte-identical.

### Step 3.2: Refactor `CoordinatingAgent::handleCreatePension`

- [ ] **Replace the body (lines 2370–2480) with:**

```php
private function handleCreatePension(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('pension');
    }

    $validationError = $this->validateToolInput($input, [
        'pension_category' => ['required', Rule::in(['dc', 'db'])],
        'scheme_name' => 'required|string|max:255',
        'current_fund_value' => 'nullable|numeric|min:0|max:999999999.99',
        'employee_contribution_percent' => 'nullable|numeric|min:0|max:100',
        'employer_contribution_percent' => 'nullable|numeric|min:0|max:100',
        'accrued_annual_pension' => 'nullable|numeric|min:0|max:999999.99',
        'normal_retirement_age' => 'nullable|integer|min:50|max:75',
    ]);
    if ($validationError) {
        return $validationError;
    }

    // Duplicate check (kept here — read pattern is allowed, not mutation)
    $dcDuplicate = $this->checkForDuplicate(\App\Models\DCPension::class, $user->id, 'scheme_name', $input['scheme_name']);
    if ($dcDuplicate) {
        return $dcDuplicate;
    }
    $dbDuplicate = $this->checkForDuplicate(\App\Models\DBPension::class, $user->id, 'scheme_name', $input['scheme_name']);
    if ($dbDuplicate) {
        return $dbDuplicate;
    }

    $canonical = app(\App\Services\Stores\Normalisers\PensionNormaliser::class)->fromFynPension($input);
    $entityType = $canonical['type'] === 'db' ? 'db_pension' : 'dc_pension';

    try {
        $pension = $canonical['type'] === 'db'
            ? app(\App\Services\Stores\PensionStore::class)->createDb($canonical, $user, \App\Services\Stores\IngestSource::FYN_AI)
            : app(\App\Services\Stores\PensionStore::class)->createDc($canonical, $user, \App\Services\Stores\IngestSource::FYN_AI);
    } catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
        return [
            'error' => true,
            'error_type' => 'validation_failed',
            'errors' => $e->errors,
            'message' => 'Validation failed for pension.',
        ];
    } catch (\App\Services\Stores\Exceptions\TierLimitExceededException $e) {
        return [
            'error' => true,
            'error_type' => 'tier_limit_exceeded',
            'message' => "You've reached your tier's pension limit. Upgrade to add more.",
        ];
    }

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => $entityType,
        'entity_id' => $pension->id,
        'name' => $pension->scheme_name,
        'persisted_fields' => array_keys(array_diff_key($canonical, ['type' => null])),
        'message' => "I've added your \"{$pension->scheme_name}\" pension.",
    ];
}
```

### Step 3.3: Refactor `CoordinatingAgent::handleCapturePensionHistory`

- [ ] **Replace the body (lines 4067–4106) with:**

```php
private function handleCapturePensionHistory(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('pension');
    }

    $history = $input['history'] ?? null;
    if (! is_array($history) || $history === []) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'history must be a non-empty array.'];
    }

    $canonical = app(\App\Services\Stores\Normalisers\PensionNormaliser::class)->fromFynInputHistory(['history' => $history]);

    try {
        $written = app(\App\Services\Stores\PensionStore::class)->captureInputHistory(
            $canonical['entries'],
            $user,
            \App\Services\Stores\IngestSource::FYN_AI
        );
    } catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No valid history entries provided.'];
    }

    return [
        'onboarding_capture' => true,
        'field_group' => 'campaign_pension_history',
        'summary' => sprintf('Captured %d year(s) of pension history.', count($written)),
        'details' => $written,
    ];
}
```

### Step 3.4: Audit the generic `handleUpdateRecord` path

- [ ] **Open `app/Agents/CoordinatingAgent.php` around line 4137 (`handleUpdateRecord`). It dispatches by `entity_type` and currently handles `dc_pension` / `db_pension` aliases at lines 4157, 4164. The actual mutation likely happens via a generic `Model::update` further down. Confirm by reading the method body, then route `dc_pension` and `db_pension` update cases through `PensionStore::updateDc` / `updateDb`:**

```php
// Inside handleUpdateRecord's dispatch, when $entityType === 'dc_pension':
$pension = app(\App\Services\Stores\PensionStore::class)->updateDc(
    $entityId,
    $aliasedFields,
    $user,
    \App\Services\Stores\IngestSource::FYN_AI
);
return $this->updateRecordSuccessEnvelope($pension, $entityType, $aliasedFields);

// And for 'db_pension':
$pension = app(\App\Services\Stores\PensionStore::class)->updateDb(
    $entityId,
    $aliasedFields,
    $user,
    \App\Services\Stores\IngestSource::FYN_AI
);
return $this->updateRecordSuccessEnvelope($pension, $entityType, $aliasedFields);
```

(Adapt `updateRecordSuccessEnvelope` to whatever the existing helper is named — read the method body before editing.)

### Step 3.5: Audit the generic delete-record path

- [ ] **Similarly audit `handleDeleteRecord` (search for the method in `CoordinatingAgent`). If it deletes by entity type, route `dc_pension` / `db_pension` cases through `PensionStore::deleteDc` / `deleteDb`.**

### Step 3.6: Run AI direct-write tests

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/AI/DirectWrite/CreatePensionTest.php tests/Feature/AI/DirectWrite/CapturePensionHistoryTest.php
```

Expected: all cases PASS — the envelope shape is identical (`success`, `created`, `entity_type`, `entity_id`, `name`, `persisted_fields`).

### Step 3.7: Update arch test allowlist

- [ ] **Do NOT remove `App\Agents\CoordinatingAgent` from the allowlist in this PR.** It still has read references at lines 1505, 1509, 2389, 2393, 3960, 4314, 4315. PR 5h removes those. The only PR 3 allowlist change is implicit: write mutations from the file are gone; read references remain on the allowlist until PR 5h.

### Step 3.8: Run full suite

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

### Step 3.9: Commit + PR + csjones smoke

- [ ] **Commit:**

```bash
cd /Users/CSJ/Desktop/fynla && git add -A
cd /Users/CSJ/Desktop/fynla && git commit -m "$(cat <<'EOF'
refactor(pensions): point Fyn AI write tools at PensionStore

Pass 3 / PR 3. CoordinatingAgent::handleCreatePension dispatches
through PensionNormaliser::fromFynPension + PensionStore::createDc /
createDb. The DC/DB type-switch logic and pension_type/scheme_type
sanitisation that previously lived in the handler moves into the
normaliser. Existing AI direct-write feature tests (CreatePensionTest +
CapturePensionHistoryTest) all pass unchanged; envelope shape is
byte-identical.

CoordinatingAgent::handleCapturePensionHistory routes through
PensionStore::captureInputHistory with IngestSource::FYN_AI.

CoordinatingAgent::handleUpdateRecord / handleDeleteRecord dispatch
for dc_pension and db_pension now route through PensionStore::updateDc/
updateDb and PensionStore::deleteDc/deleteDb.

CoordinatingAgent stays on the allowlist for read references (lines
1505, 1509, 2389, 2393, 3960, 4314, 4315) which PR 5h migrates.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Push, open PR `feature/pension-store-pr3` → `dev`, deploy csjones, Playwright-test:**
  1. Login as a test user
  2. Open Fyn chat → "Add a Aviva SIPP with £25,000, monthly contribution £250"
  3. Wait for the `entity_created` SSE event in the network panel
  4. Open `/retirement` → assert the new pension shows with correct fields
  5. Open Fyn chat → "I contributed £8,500 to my pension in 2024-25"
  6. Verify a `pension_input_history` row was written: `php artisan tinker --execute="echo \App\Models\PensionInputHistory::latest()->first()->pension_input_amount;"`
  7. Open Fyn chat → "Update my Aviva SIPP value to £30,000"
  8. Verify the DC pension's `current_fund_value` updated to 30000

- [ ] **Admin-merge only after green.**

---

## Task 4 — PR 4: Point upload extraction + seeders at `PensionStore`

**PR title:** `refactor(pensions): point upload extraction + seeders at PensionStore`

**Files:**
- Modify: `app/Services/Documents/DocumentProcessor.php` (line 389)
- Modify: `app/Http/Controllers/Api/PreviewController.php` (lines 534, 546, 561)
- Modify: `database/seeders/PreviewUserSeeder.php` (lines 948, 1074, 1094, 1106)
- Modify: `database/seeders/ChrisUserSeeder.php` (line 211 — uses `updateOrCreate` semantics)
- Modify: `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php` (remove `DocumentProcessor`, `PreviewController`, both seeders)
- Add: `tests/Feature/Stores/PensionUploadIngestTest.php`

### Step 4.1: Write the failing upload-ingest feature test

- [ ] **Create `tests/Feature/Stores/PensionUploadIngestTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('DocumentProcessor persists DC pension extraction via PensionStore with IngestSource::UPLOAD', function () {
    $user = User::factory()->create();

    // Drive the processor through the direct mapper API. Adjust to the
    // actual public entry-point shape (read DocumentProcessor first).
    $extraction = [
        'scheme_name' => 'Standard Life',
        'provider' => 'Standard Life',
        'pension_type' => 'personal',
        'current_fund_value' => 32500,
    ];

    $canonical = app(\App\Services\Stores\Normalisers\PensionNormaliser::class)->fromUploadDc($extraction);
    $pension = app(\App\Services\Stores\PensionStore::class)->createDc(
        $canonical,
        $user,
        \App\Services\Stores\IngestSource::UPLOAD
    );

    expect($pension)->toBeInstanceOf(DCPension::class);
    expect($pension->user_id)->toBe($user->id);
    expect($pension->scheme_name)->toBe('Standard Life');
    expect((float) $pension->current_fund_value)->toBe(32500.00);
});
```

- [ ] **Run, confirm PASS (the normaliser and store are already in place from PR 1):**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/Stores/PensionUploadIngestTest.php
```

Expected: PASS.

### Step 4.2: Refactor `DocumentProcessor.php` line 389

- [ ] **Read the surrounding context (lines 370–410) to understand the loop. Then replace the `$account = DCPension::create($accountData);` line with:**

```php
$canonical = app(\App\Services\Stores\Normalisers\PensionNormaliser::class)->fromUploadDc($accountData);
$account = app(\App\Services\Stores\PensionStore::class)->createDc(
    $canonical,
    $user,
    \App\Services\Stores\IngestSource::UPLOAD
);
```

(`$user` should be in scope — verify by reading the method. If not, pass it through from the caller.)

### Step 4.3: Refactor `PreviewController.php` lines 534, 546, 561

- [ ] **Read lines 525–575 to understand the persona-seeding loop. The current pattern is:**

```php
// Line 534 (DC):
DCPension::create(array_merge($pension, ['user_id' => $previewUser->id]));

// Line 546 (DB):
DBPension::create(array_merge($pension, ['user_id' => $previewUser->id]));

// Line 561 (State):
StatePension::create(array_merge($pension, ['user_id' => $previewUser->id]));
```

Replace with:

```php
// DC:
app(\App\Services\Stores\PensionStore::class)->createDc(
    app(\App\Services\Stores\Normalisers\PensionNormaliser::class)->fromFormDc($pension),
    $previewUser,
    \App\Services\Stores\IngestSource::SEEDER
);

// DB:
app(\App\Services\Stores\PensionStore::class)->createDb(
    app(\App\Services\Stores\Normalisers\PensionNormaliser::class)->fromFormDb($pension),
    $previewUser,
    \App\Services\Stores\IngestSource::SEEDER
);

// State (note: persona JSON may have multiple State entries; the store
// will upsert them onto the one-per-user row. If the persona expects
// multiple State pension records this is a persona-data bug — flag to
// CSJ and use upsertState anyway):
app(\App\Services\Stores\PensionStore::class)->upsertState(
    app(\App\Services\Stores\Normalisers\PensionNormaliser::class)->fromFormState($pension),
    $previewUser,
    \App\Services\Stores\IngestSource::SEEDER
);
```

### Step 4.4: Refactor `database/seeders/PreviewUserSeeder.php` lines 948, 1074, 1094, 1106

- [ ] **Same pattern as PreviewController. Read each line's surrounding context first to confirm the variable names — the persona user variable may be named differently (`$user`, `$personaUser`, `$entity`). Then apply the store/normaliser swap.**

### Step 4.5: Refactor `database/seeders/ChrisUserSeeder.php` line 211 (updateOrCreate)

- [ ] **The existing call is `DCPension::updateOrCreate([match], [values])`. Replace with:**

```php
app(\App\Services\Stores\PensionStore::class)->updateOrCreateDc(
    match: [/* the existing match array, e.g. ['scheme_name' => 'NHS DC AVCs'] */],
    data: [/* the existing values array */],
    user: $chris,
    source: \App\Services\Stores\IngestSource::SEEDER,
);
```

### Step 4.6: Run seeders end-to-end to confirm parity

- [ ] **Test the seeders directly:**

```bash
cd /Users/CSJ/Desktop/fynla && php artisan db:seed --class=ChrisUserSeeder --force
cd /Users/CSJ/Desktop/fynla && php artisan tinker --execute="\$chris = \App\Models\User::where('email','chris@fynla.org')->first(); echo 'DC: '.\App\Models\DCPension::where('user_id', \$chris->id)->count().PHP_EOL.'DB: '.\App\Models\DBPension::where('user_id', \$chris->id)->count().PHP_EOL.'State: '.(\App\Models\StatePension::where('user_id', \$chris->id)->first() ? 'yes' : 'no').PHP_EOL;"
```

Expected: same DC + DB + State counts as before the refactor (capture baseline counts BEFORE the refactor and compare).

```bash
cd /Users/CSJ/Desktop/fynla && php artisan db:seed --class=PreviewUserSeeder --force
cd /Users/CSJ/Desktop/fynla && php artisan tinker --execute="echo 'DC: '.\App\Models\DCPension::whereHas('user', fn(\$q) => \$q->where('is_preview_user', true))->count().PHP_EOL.'DB: '.\App\Models\DBPension::whereHas('user', fn(\$q) => \$q->where('is_preview_user', true))->count().PHP_EOL;"
```

Expected: same counts as baseline.

### Step 4.7: Update arch test allowlist

- [ ] **Edit `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php`. Delete from `$pensionConsumers`:**

```
'App\Services\Documents\DocumentProcessor',
'App\Http\Controllers\Api\PreviewController',
'Database\Seeders\PreviewUserSeeder',
'Database\Seeders\ChrisUserSeeder',
```

### Step 4.8: Run full suite + commit + PR + csjones smoke

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

- [ ] **Commit:**

```bash
cd /Users/CSJ/Desktop/fynla && git add -A
cd /Users/CSJ/Desktop/fynla && git commit -m "$(cat <<'EOF'
refactor(pensions): point upload + seeders at PensionStore

Pass 3 / PR 4. The remaining direct-write sites for the three pension
models go through the store:

- DocumentProcessor (upload OCR path) → IngestSource::UPLOAD
- PreviewController (persona seeding) → IngestSource::SEEDER
- PreviewUserSeeder + ChrisUserSeeder → IngestSource::SEEDER

PensionStore::updateOrCreateDc preserves the ChrisUserSeeder
updateOrCreate semantics. State pension persona entries route through
upsertState (one-per-user invariant).

Boundary allowlist shrinks to read-only consumers + observers — PR 5
removes the remaining read sites.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Push, open PR `feature/pension-store-pr4` → `dev`. csjones smoke:**
  1. Login as a test user (not preview)
  2. Open Statement Upload → upload a pension-statement PDF
  3. Wait for OCR completion → assert a new DCPension appears in `/retirement`
  4. Switch to a preview persona (e.g. `peak_earners`) → confirm preview-user pensions still load correctly
  5. Admin-merge after green.

---

## Task 5 — PR 5: Point read consumers at `PensionStore`

**PR title:** `refactor(pensions): point read consumers at PensionStore`

**Auto-split rule (spec §15.1):** If the cumulative diff exceeds ~500 lines (sum of additions across all files), the engineer **must** split into sub-PRs along the cluster lines below. Each sub-PR carries its own arch-test allowlist edit. **No consult needed for the split.**

**Cluster groupings (in suggested merge order):**

| Sub-PR | Cluster | Files |
|--------|---------|-------|
| PR 5a | Retirement domain (read-heavy) | `RetirementAgent`, `RetirementActionDefinitionService`, `PensionProjector`, `PensionContributionOptimizer`, `RetirementIncomeService`, `PensionPortfolioAnalyzer`, `RetirementStrategyService`, `DecumulationPlanner`, `RequiredCapitalCalculator`, `RetirementProjectionService`, `RetirementDataReadinessService`, `SalarySacrificeAnalyzer`, `AnnualAllowanceChecker` |
| PR 5b | Plans + coordination | `Plans\RetirementPlanService`, `Coordination\HouseholdPlanningService`, `Coordination\CashFlowCoordinator`, `Goals\LifeEventAllocationService` |
| PR 5c | Estate / IHT | `Estate\IHTCalculationService`, `Estate\EstateAssetAggregatorService`, `Estate\EstateActionDefinitionService`, `NetWorth\NetWorthService` |
| PR 5d | Tax strategies | `Tax\Strategies\SalarySacrificeNiStrategy`, `Tax\Strategies\PensionAACarryForwardStrategy`, `Tax\Strategies\NonEarnerSpousePensionStrategy`, `Tax\Strategies\TaperedAnnualAllowanceStrategy`, `Tax\TaxStrategyMath` |
| PR 5e | AI + profile | `AI\AdvicePromptBuilder`, `AI\DuplicateAcknowledgement`, `UserProfile\ProfileCompletenessChecker`, `UserProfile\UserProfileService`, `Risk\AutoRiskCalculator` |
| PR 5f | Documents + onboarding + jobs | `Documents\HoldingsImportService`, `Documents\DocumentTypeDetector` (type-constant-only — confirm), `Documents\FieldMappers\DCPensionMapper` / `DBPensionMapper` (type-constant-only — confirm), `Onboarding\AssetCaptureEntityExtractor`, `Jobs\RecalculateRiskProfileJob`, `Eval\EvalHttpDriver`, `Console\Commands\EncryptExistingData` (console-allowed; confirm no mutation), `Console\Commands\ResetPreviewData` (console-allowed; confirm no mutation) |
| PR 5g | CoordinatingAgent residual reads | `CoordinatingAgent` lines 1505, 1509, 2389, 2393, 3960, 4314, 4315 (duplicate-check reads + type-constant references in `handleUpdateRecord`/`handleDeleteRecord` dispatch tables) |

### Step 5.1 — per-file migration recipe (apply to every file in the cluster)

**Before** (typical patterns found in the survey):
```php
DCPension::where('user_id', $userId)->get();
DCPension::where('user_id', $userId)->with('holdings')->get();
DBPension::where('user_id', $userId)->get();
StatePension::where('user_id', $userId)->first();
PensionInputHistory::where('user_id', $userId)->orderBy('tax_year')->get();
DCPension::where('user_id', $userId)->where('id', $id)->firstOrFail();
```

**After:**
```php
// All-pensions read (most common):
$all = app(\App\Services\Stores\PensionStore::class)->forUser($user);
// $all['dc'], $all['db'], $all['state'], $all['input_history']

// Type-filtered read:
$dcs = app(\App\Services\Stores\PensionStore::class)->forUserByType($user, 'dc');

// Single record by id:
$pension = app(\App\Services\Stores\PensionStore::class)->find($id, 'dc', $user);
if (! $pension) {
    // handle not-found
}

// State pension only:
$state = app(\App\Services\Stores\PensionStore::class)->statePension($user);

// Input history (all or single year):
$history = app(\App\Services\Stores\PensionStore::class)->pensionInputHistory($user);
$thisYear = app(\App\Services\Stores\PensionStore::class)->pensionInputHistory($user, '2025-26');
```

If a consumer takes a `$userId` (int) rather than a `User` model, refactor the signature to take `User $user` OR add a thin overload in the consumer that resolves the user via `User::findOrFail($userId)` once at the entry point.

Inject `PensionStore` via the constructor for cleanliness — consumers should declare a dependency:

```php
public function __construct(
    private readonly \App\Services\Stores\PensionStore $pensionStore,
    // ... existing deps
) {}
```

`app(...)` is acceptable inside generated/legacy code where the constructor is complex; document the choice when used.

### Step 5.2 — TDD for each cluster

For each cluster, write a feature test that asserts the consumer returns the same data after the refactor as before. Snapshot or numerical comparison — depending on the consumer.

Example for the Retirement cluster (PR 5a):

- [ ] **Add to `tests/Feature/Stores/PensionReadConsumerParityTest.php` (create the file if missing):**

```php
<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Retirement\PensionProjector;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('PensionProjector projects total fund value across DC pensions identically before and after store migration', function () {
    $user = User::factory()->create();
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 25000, 'expected_return_percent' => 5, 'retirement_age' => 65]);
    DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 15000, 'expected_return_percent' => 4, 'retirement_age' => 65]);

    $projection = app(PensionProjector::class)->projectForUser($user);

    // Adapt to the actual return shape — read PensionProjector::projectForUser
    // before writing the assertion.
    expect($projection['total_current_value'])->toBe(40000.00);
});
```

- [ ] **Run, refactor the consumer to use `PensionStore`, re-run, confirm green.**

- [ ] **Remove the consumer file from the arch-test allowlist.**

### Step 5.3 — Per-cluster commit

Commit each cluster as its own commit (or its own sub-PR if the split rule fires):

```bash
git commit -m "refactor(pensions): point <cluster> at PensionStore reads (PR 5x)"
```

### Step 5.4 — End-of-PR-5 full suite + csjones

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

- [ ] **csjones browser smoke (mandatory for PR 5 / each sub-PR):**
  1. Login → `/retirement` → confirm DC + DB + State pension cards load with correct totals
  2. Navigate to `/estate` → confirm DC pension values feed net-worth correctly (flexibly-accessed funds count toward estate, fund value otherwise excluded — per existing IHT rules)
  3. Open a retirement plan → run a projection → confirm output matches pre-refactor baseline
  4. Run a what-if scenario that mutates a pension → confirm results match pre-refactor baseline
  5. Open Fyn chat → "What's in my Aviva SIPP?" → confirm `AdvicePromptBuilder` reads via store and Fyn answers correctly
  6. Open Fyn chat → "What was my pension input for 2024-25?" → confirm `PensionInputHistory` reads work
  7. Check `/admin/data-readiness` → confirm pension-related readiness flags reflect actual data

### Step 5.5 — Notes for the engineer

- **Observers stay on the allowlist** — `DCPensionRiskObserver` is permanently exempt per spec §14.2. Confirm it doesn't mutate other pension models (it only dispatches recalculation jobs).
- **User relationships** (`$user->dcPensions`, `$user->dbPensions`, `$user->statePension`) are read-only; Pest `arch()` allows relationship method calls. Only direct `::query/where/find+update/save/delete` patterns in `User` count as mutations — re-grep to confirm none exist.
- **Type-constant references in `DocumentTypeDetector` / `DCPensionMapper` / `DBPensionMapper` / `Console\Commands\EncryptExistingData` / `Console\Commands\ResetPreviewData`** are NOT mutations. Pest `arch()` `toOnlyBeUsedIn()` semantics catch class references regardless of context — confirm with a `grep -n 'DCPension::class\|DBPension::class\|StatePension::class'` on each file and document the use as a constant-only reference in a comment. Console commands are on the spec §14.2 permanent allowlist anyway.
- **`AssetCaptureEntityExtractor`** reads `DCPension::query()` / `DBPension::query()` at lines 246/257 for duplicate checks. Migrate the read to `app(PensionStore::class)->forUserByType($user, 'dc')` filtered for scheme-name match. The duplicate-check stays in the caller (consistent with `CoordinatingAgent::checkForDuplicate` — duplicate prevention is a UX concern owned by the ingest path, not a uniqueness constraint owned by the store). The store does NOT add a uniqueness validator on `scheme_name`; the form path may legitimately allow two pensions with similar names if the user intends it.
- **`CoordinatingAgent` residual reads** (PR 5g): the duplicate-check calls at lines 2389/2393 use `$this->checkForDuplicate(\App\Models\DCPension::class, ...)`. `checkForDuplicate` is a generic helper that runs `Model::query()->where(...)`. Either: (a) keep `checkForDuplicate` as-is and accept that the model class reference is a constant (not a mutation) and update the arch-test allowlist comment to permit this pattern, OR (b) thread a typed reader through `PensionStore::existsForUserByName($user, $type, $name)`. Pick (b) if it doesn't blow up the diff; (a) is acceptable if `checkForDuplicate` is heavily used across the agent.

---

## Task 6 — PR 6: Materialise canonical derived columns + snapshot tables

**PR title:** `feat(pensions): materialise canonical derived columns + snapshot tables`

**Files:**
- Create: `database/migrations/2026_05_24_100000_add_derived_columns_to_dc_pensions.php`
- Create: `database/migrations/2026_05_24_100001_add_derived_columns_to_db_pensions.php`
- Create: `database/migrations/2026_05_24_100002_add_derived_columns_to_state_pensions.php`
- Create: `database/migrations/2026_05_24_100003_create_dc_pension_value_snapshots_table.php`
- Create: `database/migrations/2026_05_24_100004_create_db_pension_value_snapshots_table.php`
- Create: `database/migrations/2026_05_24_100005_create_state_pension_value_snapshots_table.php`
- Create: `app/Models/DCPensionValueSnapshot.php`
- Create: `app/Models/DBPensionValueSnapshot.php`
- Create: `app/Models/StatePensionValueSnapshot.php`
- Create: `app/Services/Stores/Recalc/PensionDerivedColumnCalculator.php`
- Modify: `app/Services/Stores/Snapshots/SnapshotPolicies.php` (add pension policies)
- Modify: `app/Services/Stores/PensionStore.php` (inject calculator, call `recalculateDerived` after each write)
- Modify: `app/Models/DCPension.php`, `DBPension.php`, `StatePension.php` (extend `$casts` + `$fillable` only)
- Create: `app/Console/Commands/BackfillPensionDerivedColumns.php`
- Create: `tests/Unit/Services/Stores/Recalc/PensionDerivedColumnCalculatorTest.php`
- Modify: `tests/Unit/Services/Stores/PensionStoreTest.php` (add derived-column assertions)
- Create: `tests/Feature/Stores/PensionDerivedColumnsBackfillTest.php`

### Step 6.1: Write the failing calculator test

- [ ] **Create `tests/Unit/Services/Stores/Recalc/PensionDerivedColumnCalculatorTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Stores\Recalc\PensionDerivedColumnCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('calculateDc materialises current_fund_value_gbp + projected_value + annual_contribution', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(45)]);
    $pension = DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 50000,
        'annual_salary' => 60000,
        'employee_contribution_percent' => 5,
        'employer_contribution_percent' => 5,
        'monthly_contribution_amount' => null,
        'expected_return_percent' => 5,
        'retirement_age' => 65,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateDc($pension, $user);

    expect($derived['current_fund_value_gbp'])->toBe(50000.00);
    // 10% of 60k = £6,000/year contributions
    expect($derived['annual_contribution_gbp'])->toBe(6000.00);
    // 65 - 45 = 20 years
    expect($derived['years_to_drawdown'])->toBe(20);
});

it('calculateDc projected_value reflects compounded growth', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(45)]);
    $pension = DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 100000,
        'expected_return_percent' => 5,
        'retirement_age' => 65,
        'monthly_contribution_amount' => 0,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateDc($pension, $user);

    // 100k * 1.05^20 = ~265,329.77
    expect($derived['projected_value_at_retirement_gbp'])->toBeGreaterThan(265000);
    expect($derived['projected_value_at_retirement_gbp'])->toBeLessThan(266000);
});

it('calculateDb materialises projected_annual_pension_at_nra_gbp', function () {
    $user = User::factory()->create();
    $pension = DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => 12000,
        'spouse_pension_percent' => 50,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateDb($pension, $user);

    expect($derived['projected_annual_pension_at_nra_gbp'])->toBe(12000.00);
    expect($derived['spouse_pension_projected_gbp'])->toBe(6000.00);
});

it('calculateState materialises state_pension_forecast_annual_gbp + ni_completion_pct', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(50)]);
    $state = StatePension::factory()->create([
        'user_id' => $user->id,
        'ni_years_completed' => 28,
        'ni_years_required' => 35,
        'state_pension_forecast_annual' => 9000,
        'state_pension_age' => 67,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateState($state, $user);

    expect($derived['state_pension_forecast_annual_gbp'])->toBe(9000.00);
    expect($derived['ni_completion_pct'])->toBe(80.00);
    expect($derived['years_to_state_pension_age'])->toBe(17);
});
```

- [ ] **Run and confirm fails:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/Recalc/PensionDerivedColumnCalculatorTest.php
```

Expected: FAIL — class not found.

### Step 6.2: Implement the derived-column calculator

- [ ] **Create `app/Services/Stores/Recalc/PensionDerivedColumnCalculator.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Recalc;

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Stores\TaxConfigStore;

class PensionDerivedColumnCalculator
{
    public function __construct(
        private readonly TaxConfigStore $taxConfigStore,
    ) {}

    /**
     * @return array{
     *     current_fund_value_gbp: float,
     *     projected_value_at_retirement_gbp: ?float,
     *     annual_contribution_gbp: ?float,
     *     years_to_drawdown: ?int,
     *     annual_allowance_used_gbp: ?float
     * }
     */
    public function calculateDc(DCPension $pension, User $user): array
    {
        // For pass 3, all DC fund values stored in GBP — currency conversion
        // for pensions lands in the broader multi-currency rollout (per spec
        // §9, _gbp columns on user-data entities are added per-entity).
        $currentGbp = (float) $pension->current_fund_value;

        // Annual contribution: prefer monthly_contribution_amount × 12;
        // fall back to employee% + employer% × annual_salary.
        $annualContribution = null;
        if ($pension->monthly_contribution_amount !== null && $pension->monthly_contribution_amount > 0) {
            $annualContribution = round((float) $pension->monthly_contribution_amount * 12, 2);
        } elseif ($pension->annual_salary !== null && $pension->annual_salary > 0) {
            $pct = ((float) ($pension->employee_contribution_percent ?? 0))
                 + ((float) ($pension->employer_contribution_percent ?? 0));
            $annualContribution = round((float) $pension->annual_salary * $pct / 100, 2);
        }

        // Years to drawdown — based on retirement_age vs user DOB
        $yearsToDrawdown = null;
        if ($pension->retirement_age !== null && $user->date_of_birth !== null) {
            $age = (int) now()->diffInYears($user->date_of_birth);
            $yearsToDrawdown = max(0, (int) $pension->retirement_age - $age);
        }

        // Projected value at retirement — compounded growth on current + future contributions
        $projected = null;
        if ($yearsToDrawdown !== null && $pension->expected_return_percent !== null) {
            $r = (float) $pension->expected_return_percent / 100;
            $futureCurrent = $currentGbp * (1 + $r) ** $yearsToDrawdown;

            $contrib = $annualContribution ?? 0.0;
            $futureContribs = $r > 0
                ? $contrib * (((1 + $r) ** $yearsToDrawdown - 1) / $r)
                : $contrib * $yearsToDrawdown;

            $projected = round($futureCurrent + $futureContribs, 2);
        }

        // Annual allowance used — % of AA used by this year's contribution
        $aaUsed = null;
        if ($annualContribution !== null) {
            $aa = (float) ($this->taxConfigStore->activeConfig()->config_data['pension']['annual_allowance'] ?? 60000);
            if ($aa > 0) {
                $aaUsed = round($annualContribution / $aa * 100, 2);
            }
        }

        return [
            'current_fund_value_gbp' => $currentGbp,
            'projected_value_at_retirement_gbp' => $projected,
            'annual_contribution_gbp' => $annualContribution,
            'years_to_drawdown' => $yearsToDrawdown,
            'annual_allowance_used_gbp' => $aaUsed,
        ];
    }

    /**
     * @return array{
     *     projected_annual_pension_at_nra_gbp: ?float,
     *     spouse_pension_projected_gbp: ?float
     * }
     */
    public function calculateDb(DBPension $pension, User $user): array
    {
        $annual = $pension->accrued_annual_pension !== null
            ? round((float) $pension->accrued_annual_pension, 2)
            : null;

        $spouse = null;
        if ($annual !== null && $pension->spouse_pension_percent !== null) {
            $spouse = round($annual * (float) $pension->spouse_pension_percent / 100, 2);
        }

        return [
            'projected_annual_pension_at_nra_gbp' => $annual,
            'spouse_pension_projected_gbp' => $spouse,
        ];
    }

    /**
     * @return array{
     *     state_pension_forecast_annual_gbp: ?float,
     *     ni_completion_pct: ?float,
     *     years_to_state_pension_age: ?int
     * }
     */
    public function calculateState(StatePension $state, User $user): array
    {
        $forecast = $state->state_pension_forecast_annual !== null
            ? round((float) $state->state_pension_forecast_annual, 2)
            : null;

        $completion = null;
        if ($state->ni_years_required !== null && $state->ni_years_required > 0 && $state->ni_years_completed !== null) {
            $completion = round((float) $state->ni_years_completed / (float) $state->ni_years_required * 100, 2);
        }

        $years = null;
        if ($state->state_pension_age !== null && $user->date_of_birth !== null) {
            $age = (int) now()->diffInYears($user->date_of_birth);
            $years = max(0, (int) $state->state_pension_age - $age);
        }

        return [
            'state_pension_forecast_annual_gbp' => $forecast,
            'ni_completion_pct' => $completion,
            'years_to_state_pension_age' => $years,
        ];
    }
}
```

- [ ] **Run, confirm PASS:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/Recalc/PensionDerivedColumnCalculatorTest.php
```

Expected: PASS.

### Step 6.3: Extend SnapshotPolicies with pension policies

- [ ] **Add to `app/Services/Stores/Snapshots/SnapshotPolicies.php`:**

```php
public static function dcPensionFundValue(): SnapshotPolicy
{
    return new SnapshotPolicy(
        triggerPredicate: fn ($old, $new) => $old !== null && (abs($new - $old) > 500 || ($old > 0 && abs($new - $old) / $old > 0.02)),
        retentionDays: self::RETENTION_DAYS,
        surfacingWindowDays: self::TIER_WINDOW,
        maxRowsHardCap: 5000,
        recalcCadence: 'on_change',
    );
}

public static function dcPensionProjectedValue(): SnapshotPolicy
{
    return new SnapshotPolicy(
        triggerPredicate: fn ($old, $new) => $old !== null && (abs($new - $old) > 1000 || ($old > 0 && abs($new - $old) / $old > 0.05)),
        retentionDays: self::RETENTION_DAYS,
        surfacingWindowDays: self::TIER_WINDOW,
        maxRowsHardCap: 5000,
        recalcCadence: 'on_change',
    );
}

public static function dbPensionAnnualValue(): SnapshotPolicy
{
    return new SnapshotPolicy(
        triggerPredicate: fn ($old, $new) => $old !== null && (abs($new - $old) > 100 || ($old > 0 && abs($new - $old) / $old > 0.02)),
        retentionDays: self::RETENTION_DAYS,
        surfacingWindowDays: self::TIER_WINDOW,
        maxRowsHardCap: 5000,
        recalcCadence: 'on_change',
    );
}

public static function statePensionForecast(): SnapshotPolicy
{
    return new SnapshotPolicy(
        triggerPredicate: fn ($old, $new) => $old !== null && abs($new - $old) > 50,
        retentionDays: self::RETENTION_DAYS,
        surfacingWindowDays: self::TIER_WINDOW,
        maxRowsHardCap: 5000,
        recalcCadence: 'on_change',
    );
}
```

### Step 6.4: Create the derived-column migrations

- [ ] **Create `database/migrations/2026_05_24_100000_add_derived_columns_to_dc_pensions.php`:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->decimal('current_fund_value_gbp', 14, 2)->nullable()->after('current_fund_value');
            $table->timestamp('current_fund_value_gbp_calculated_at')->nullable()->after('current_fund_value_gbp');

            $table->decimal('projected_value_at_retirement_gbp', 14, 2)->nullable()->after('projected_value_at_retirement');
            $table->timestamp('projected_value_at_retirement_gbp_calculated_at')->nullable()->after('projected_value_at_retirement_gbp');

            $table->decimal('annual_contribution_gbp', 14, 2)->nullable()->after('monthly_contribution_amount');
            $table->timestamp('annual_contribution_gbp_calculated_at')->nullable()->after('annual_contribution_gbp');

            $table->integer('years_to_drawdown')->nullable()->after('retirement_age');
            $table->timestamp('years_to_drawdown_calculated_at')->nullable()->after('years_to_drawdown');

            $table->decimal('annual_allowance_used_gbp', 8, 2)->nullable()->after('annual_contribution_gbp_calculated_at');
            $table->timestamp('annual_allowance_used_gbp_calculated_at')->nullable()->after('annual_allowance_used_gbp');
        });
    }

    public function down(): void
    {
        Schema::table('dc_pensions', function (Blueprint $table) {
            $table->dropColumn([
                'current_fund_value_gbp', 'current_fund_value_gbp_calculated_at',
                'projected_value_at_retirement_gbp', 'projected_value_at_retirement_gbp_calculated_at',
                'annual_contribution_gbp', 'annual_contribution_gbp_calculated_at',
                'years_to_drawdown', 'years_to_drawdown_calculated_at',
                'annual_allowance_used_gbp', 'annual_allowance_used_gbp_calculated_at',
            ]);
        });
    }
};
```

- [ ] **Create `database/migrations/2026_05_24_100001_add_derived_columns_to_db_pensions.php`:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('db_pensions', function (Blueprint $table) {
            $table->decimal('projected_annual_pension_at_nra_gbp', 14, 2)->nullable()->after('accrued_annual_pension');
            $table->timestamp('projected_annual_pension_at_nra_gbp_calculated_at')->nullable()->after('projected_annual_pension_at_nra_gbp');

            $table->decimal('spouse_pension_projected_gbp', 14, 2)->nullable()->after('spouse_pension_percent');
            $table->timestamp('spouse_pension_projected_gbp_calculated_at')->nullable()->after('spouse_pension_projected_gbp');
        });
    }

    public function down(): void
    {
        Schema::table('db_pensions', function (Blueprint $table) {
            $table->dropColumn([
                'projected_annual_pension_at_nra_gbp', 'projected_annual_pension_at_nra_gbp_calculated_at',
                'spouse_pension_projected_gbp', 'spouse_pension_projected_gbp_calculated_at',
            ]);
        });
    }
};
```

- [ ] **Create `database/migrations/2026_05_24_100002_add_derived_columns_to_state_pensions.php`:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('state_pensions', function (Blueprint $table) {
            $table->decimal('state_pension_forecast_annual_gbp', 12, 2)->nullable()->after('state_pension_forecast_annual');
            $table->timestamp('state_pension_forecast_annual_gbp_calculated_at')->nullable()->after('state_pension_forecast_annual_gbp');

            $table->decimal('ni_completion_pct', 5, 2)->nullable()->after('ni_years_required');
            $table->timestamp('ni_completion_pct_calculated_at')->nullable()->after('ni_completion_pct');

            $table->integer('years_to_state_pension_age')->nullable()->after('state_pension_age');
            $table->timestamp('years_to_state_pension_age_calculated_at')->nullable()->after('years_to_state_pension_age');
        });
    }

    public function down(): void
    {
        Schema::table('state_pensions', function (Blueprint $table) {
            $table->dropColumn([
                'state_pension_forecast_annual_gbp', 'state_pension_forecast_annual_gbp_calculated_at',
                'ni_completion_pct', 'ni_completion_pct_calculated_at',
                'years_to_state_pension_age', 'years_to_state_pension_age_calculated_at',
            ]);
        });
    }
};
```

### Step 6.5: Create the snapshot table migrations

- [ ] **Create `database/migrations/2026_05_24_100003_create_dc_pension_value_snapshots_table.php`:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dc_pension_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dc_pension_id')->constrained('dc_pensions')->cascadeOnDelete();
            $table->string('column_name', 64);
            $table->decimal('value', 16, 2);
            $table->char('currency', 3)->default('GBP');
            $table->decimal('value_gbp', 16, 2)->nullable();
            $table->timestamp('taken_at');
            $table->string('trigger_reason', 64);
            $table->string('ingest_source', 16);
            $table->timestamps();
            $table->index(['dc_pension_id', 'column_name', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_pension_value_snapshots');
    }
};
```

- [ ] **Create `database/migrations/2026_05_24_100004_create_db_pension_value_snapshots_table.php` — identical shape, just swap `dc_pension_id`/`dc_pensions` for `db_pension_id`/`db_pensions`. Same for state in `2026_05_24_100005_create_state_pension_value_snapshots_table.php` with `state_pension_id`/`state_pensions`.**

### Step 6.6: Create snapshot models

- [ ] **Create `app/Models/DCPensionValueSnapshot.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DCPensionValueSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'dc_pension_id', 'column_name', 'value', 'currency', 'value_gbp',
        'taken_at', 'trigger_reason', 'ingest_source',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'value_gbp' => 'decimal:2',
        'taken_at' => 'datetime',
    ];

    public function dcPension(): BelongsTo
    {
        return $this->belongsTo(DCPension::class);
    }
}
```

- [ ] **Create `app/Models/DBPensionValueSnapshot.php` and `app/Models/StatePensionValueSnapshot.php` with the same pattern, swapping the FK and relationship method.**

### Step 6.7: Add `$casts` and `$fillable` entries to the pension models

- [ ] **Edit `app/Models/DCPension.php`. Add to `$fillable`:**

```php
'current_fund_value_gbp', 'current_fund_value_gbp_calculated_at',
'projected_value_at_retirement_gbp', 'projected_value_at_retirement_gbp_calculated_at',
'annual_contribution_gbp', 'annual_contribution_gbp_calculated_at',
'years_to_drawdown', 'years_to_drawdown_calculated_at',
'annual_allowance_used_gbp', 'annual_allowance_used_gbp_calculated_at',
```

And to `$casts`:

```php
'current_fund_value_gbp' => 'decimal:2',
'current_fund_value_gbp_calculated_at' => 'datetime',
'projected_value_at_retirement_gbp' => 'decimal:2',
'projected_value_at_retirement_gbp_calculated_at' => 'datetime',
'annual_contribution_gbp' => 'decimal:2',
'annual_contribution_gbp_calculated_at' => 'datetime',
'years_to_drawdown' => 'integer',
'years_to_drawdown_calculated_at' => 'datetime',
'annual_allowance_used_gbp' => 'decimal:2',
'annual_allowance_used_gbp_calculated_at' => 'datetime',
```

- [ ] **Edit `app/Models/DBPension.php`. Add to `$fillable`:**

```php
'projected_annual_pension_at_nra_gbp', 'projected_annual_pension_at_nra_gbp_calculated_at',
'spouse_pension_projected_gbp', 'spouse_pension_projected_gbp_calculated_at',
```

And to `$casts`:

```php
'projected_annual_pension_at_nra_gbp' => 'decimal:2',
'projected_annual_pension_at_nra_gbp_calculated_at' => 'datetime',
'spouse_pension_projected_gbp' => 'decimal:2',
'spouse_pension_projected_gbp_calculated_at' => 'datetime',
```

- [ ] **Edit `app/Models/StatePension.php`. Add to `$fillable`:**

```php
'state_pension_forecast_annual_gbp', 'state_pension_forecast_annual_gbp_calculated_at',
'ni_completion_pct', 'ni_completion_pct_calculated_at',
'years_to_state_pension_age', 'years_to_state_pension_age_calculated_at',
```

And to `$casts`:

```php
'state_pension_forecast_annual_gbp' => 'decimal:2',
'state_pension_forecast_annual_gbp_calculated_at' => 'datetime',
'ni_completion_pct' => 'decimal:2',
'ni_completion_pct_calculated_at' => 'datetime',
'years_to_state_pension_age' => 'integer',
'years_to_state_pension_age_calculated_at' => 'datetime',
```

### Step 6.8: Wire `recalculateDerived` into PensionStore

- [ ] **Edit `app/Services/Stores/PensionStore.php` — inject the calculator + wire recalc into all three write paths:**

```php
public function __construct(
    private readonly PensionNormaliser $normaliser,
    private readonly TierGate $tierGate,
    private readonly \App\Services\Stores\Recalc\PensionDerivedColumnCalculator $derivedCalc,
) {}

// At the end of createDc(), updateDc(), updateOrCreateDc() — before returning:
$this->recalculateDcDerived($pension, $user, $source, reason: 'create' /* or 'update' */);

// At the end of createDb(), updateDb() — before returning:
$this->recalculateDbDerived($pension, $user, $source, reason: 'create' /* or 'update' */);

// At the end of upsertState() — before returning:
$this->recalculateStateDerived($state, $user, $source, reason: $state->wasRecentlyCreated ? 'create' : 'update');
```

- [ ] **Add the three recalc methods to `PensionStore`:**

```php
private function recalculateDcDerived(\App\Models\DCPension $pension, User $user, IngestSource $source, string $reason): void
{
    $derived = $this->derivedCalc->calculateDc($pension, $user);
    $now = now();

    $oldValues = [
        'current_fund_value_gbp' => $pension->current_fund_value_gbp,
        'projected_value_at_retirement_gbp' => $pension->projected_value_at_retirement_gbp,
        'annual_contribution_gbp' => $pension->annual_contribution_gbp,
    ];

    $pension->fill([
        'current_fund_value_gbp' => $derived['current_fund_value_gbp'],
        'current_fund_value_gbp_calculated_at' => $now,
        'projected_value_at_retirement_gbp' => $derived['projected_value_at_retirement_gbp'],
        'projected_value_at_retirement_gbp_calculated_at' => $now,
        'annual_contribution_gbp' => $derived['annual_contribution_gbp'],
        'annual_contribution_gbp_calculated_at' => $now,
        'years_to_drawdown' => $derived['years_to_drawdown'],
        'years_to_drawdown_calculated_at' => $now,
        'annual_allowance_used_gbp' => $derived['annual_allowance_used_gbp'],
        'annual_allowance_used_gbp_calculated_at' => $now,
    ])->save();

    $policies = [
        'current_fund_value_gbp' => \App\Services\Stores\Snapshots\SnapshotPolicies::dcPensionFundValue(),
        'projected_value_at_retirement_gbp' => \App\Services\Stores\Snapshots\SnapshotPolicies::dcPensionProjectedValue(),
    ];
    foreach ($policies as $column => $policy) {
        if (! $policy->shouldSnapshot($oldValues[$column], $derived[$column])) {
            continue;
        }
        \App\Models\DCPensionValueSnapshot::create([
            'dc_pension_id' => $pension->id,
            'column_name' => $column,
            'value' => $derived[$column] ?? 0,
            'currency' => 'GBP',
            'value_gbp' => $derived[$column],
            'taken_at' => $now,
            'trigger_reason' => $reason,
            'ingest_source' => $source->value,
        ]);
    }
}

private function recalculateDbDerived(\App\Models\DBPension $pension, User $user, IngestSource $source, string $reason): void
{
    $derived = $this->derivedCalc->calculateDb($pension, $user);
    $now = now();

    $oldValue = $pension->projected_annual_pension_at_nra_gbp;

    $pension->fill([
        'projected_annual_pension_at_nra_gbp' => $derived['projected_annual_pension_at_nra_gbp'],
        'projected_annual_pension_at_nra_gbp_calculated_at' => $now,
        'spouse_pension_projected_gbp' => $derived['spouse_pension_projected_gbp'],
        'spouse_pension_projected_gbp_calculated_at' => $now,
    ])->save();

    if (\App\Services\Stores\Snapshots\SnapshotPolicies::dbPensionAnnualValue()
        ->shouldSnapshot($oldValue, $derived['projected_annual_pension_at_nra_gbp'])) {
        \App\Models\DBPensionValueSnapshot::create([
            'db_pension_id' => $pension->id,
            'column_name' => 'projected_annual_pension_at_nra_gbp',
            'value' => $derived['projected_annual_pension_at_nra_gbp'] ?? 0,
            'currency' => 'GBP',
            'value_gbp' => $derived['projected_annual_pension_at_nra_gbp'],
            'taken_at' => $now,
            'trigger_reason' => $reason,
            'ingest_source' => $source->value,
        ]);
    }
}

private function recalculateStateDerived(\App\Models\StatePension $state, User $user, IngestSource $source, string $reason): void
{
    $derived = $this->derivedCalc->calculateState($state, $user);
    $now = now();

    $oldForecast = $state->state_pension_forecast_annual_gbp;

    $state->fill([
        'state_pension_forecast_annual_gbp' => $derived['state_pension_forecast_annual_gbp'],
        'state_pension_forecast_annual_gbp_calculated_at' => $now,
        'ni_completion_pct' => $derived['ni_completion_pct'],
        'ni_completion_pct_calculated_at' => $now,
        'years_to_state_pension_age' => $derived['years_to_state_pension_age'],
        'years_to_state_pension_age_calculated_at' => $now,
    ])->save();

    if (\App\Services\Stores\Snapshots\SnapshotPolicies::statePensionForecast()
        ->shouldSnapshot($oldForecast, $derived['state_pension_forecast_annual_gbp'])) {
        \App\Models\StatePensionValueSnapshot::create([
            'state_pension_id' => $state->id,
            'column_name' => 'state_pension_forecast_annual_gbp',
            'value' => $derived['state_pension_forecast_annual_gbp'] ?? 0,
            'currency' => 'GBP',
            'value_gbp' => $derived['state_pension_forecast_annual_gbp'],
            'taken_at' => $now,
            'trigger_reason' => $reason,
            'ingest_source' => $source->value,
        ]);
    }
}
```

### Step 6.9: Add derived-column assertion tests to PensionStoreTest

- [ ] **Add to `tests/Unit/Services/Stores/PensionStoreTest.php`:**

```php
it('createDc materialises current_fund_value_gbp + writes initial snapshot', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDc([
        'scheme_name' => 'Aviva',
        'current_fund_value' => 50000,
        'expected_return_percent' => 5,
        'retirement_age' => 65,
        'monthly_contribution_amount' => 0,
    ], $user, IngestSource::FORM);

    expect((float) $pension->current_fund_value_gbp)->toBe(50000.00);
    expect($pension->current_fund_value_gbp_calculated_at)->not->toBeNull();

    // Initial creation produces at least one snapshot row
    expect(\App\Models\DCPensionValueSnapshot::where('dc_pension_id', $pension->id)->count())
        ->toBeGreaterThanOrEqual(1);
});

it('updateDc fires snapshot only when policy threshold exceeded', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDc([
        'scheme_name' => 'Aviva', 'current_fund_value' => 50000,
        'expected_return_percent' => 5, 'retirement_age' => 65,
    ], $user, IngestSource::FORM);

    $initial = \App\Models\DCPensionValueSnapshot::where('dc_pension_id', $pension->id)->count();

    // Sub-threshold change — no new snapshot
    $store->updateDc($pension->id, ['current_fund_value' => 50300], $user, IngestSource::FORM);
    expect(\App\Models\DCPensionValueSnapshot::where('dc_pension_id', $pension->id)->count())
        ->toBe($initial);

    // Super-threshold change — snapshot fires
    $store->updateDc($pension->id, ['current_fund_value' => 70000], $user, IngestSource::FORM);
    expect(\App\Models\DCPensionValueSnapshot::where('dc_pension_id', $pension->id)->count())
        ->toBeGreaterThan($initial);
});
```

- [ ] **Run, confirm pass.**

### Step 6.10: Backfill existing rows

- [ ] **Create `app/Console/Commands/BackfillPensionDerivedColumns.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Services\Stores\Recalc\PensionDerivedColumnCalculator;
use Illuminate\Console\Command;

class BackfillPensionDerivedColumns extends Command
{
    protected $signature = 'pensions:backfill-derived';

    protected $description = 'One-off backfill of canonical derived columns for existing DC, DB, State pension rows';

    public function handle(PensionDerivedColumnCalculator $calc): int
    {
        $this->info('Backfilling DC pensions...');
        DCPension::chunkById(200, function ($chunk) use ($calc) {
            foreach ($chunk as $pension) {
                $derived = $calc->calculateDc($pension, $pension->user);
                $now = now();
                $pension->forceFill([
                    'current_fund_value_gbp' => $derived['current_fund_value_gbp'],
                    'current_fund_value_gbp_calculated_at' => $now,
                    'projected_value_at_retirement_gbp' => $derived['projected_value_at_retirement_gbp'],
                    'projected_value_at_retirement_gbp_calculated_at' => $now,
                    'annual_contribution_gbp' => $derived['annual_contribution_gbp'],
                    'annual_contribution_gbp_calculated_at' => $now,
                    'years_to_drawdown' => $derived['years_to_drawdown'],
                    'years_to_drawdown_calculated_at' => $now,
                    'annual_allowance_used_gbp' => $derived['annual_allowance_used_gbp'],
                    'annual_allowance_used_gbp_calculated_at' => $now,
                ])->saveQuietly();
            }
        });

        $this->info('Backfilling DB pensions...');
        DBPension::chunkById(200, function ($chunk) use ($calc) {
            foreach ($chunk as $pension) {
                $derived = $calc->calculateDb($pension, $pension->user);
                $now = now();
                $pension->forceFill([
                    'projected_annual_pension_at_nra_gbp' => $derived['projected_annual_pension_at_nra_gbp'],
                    'projected_annual_pension_at_nra_gbp_calculated_at' => $now,
                    'spouse_pension_projected_gbp' => $derived['spouse_pension_projected_gbp'],
                    'spouse_pension_projected_gbp_calculated_at' => $now,
                ])->saveQuietly();
            }
        });

        $this->info('Backfilling State pensions...');
        StatePension::chunkById(200, function ($chunk) use ($calc) {
            foreach ($chunk as $state) {
                $derived = $calc->calculateState($state, $state->user);
                $now = now();
                $state->forceFill([
                    'state_pension_forecast_annual_gbp' => $derived['state_pension_forecast_annual_gbp'],
                    'state_pension_forecast_annual_gbp_calculated_at' => $now,
                    'ni_completion_pct' => $derived['ni_completion_pct'],
                    'ni_completion_pct_calculated_at' => $now,
                    'years_to_state_pension_age' => $derived['years_to_state_pension_age'],
                    'years_to_state_pension_age_calculated_at' => $now,
                ])->saveQuietly();
            }
        });

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
```

- [ ] **Add a feature test `tests/Feature/Stores/PensionDerivedColumnsBackfillTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('pensions:backfill-derived populates DC derived columns on legacy rows', function () {
    $user = User::factory()->create();
    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 30000,
        'expected_return_percent' => 5,
        'retirement_age' => 65,
    ]);

    artisan('pensions:backfill-derived')->assertSuccessful();

    $pension = DCPension::first();
    expect((float) $pension->current_fund_value_gbp)->toBe(30000.00);
    expect($pension->current_fund_value_gbp_calculated_at)->not->toBeNull();
});
```

- [ ] **Run migrations + backfill locally:**

```bash
cd /Users/CSJ/Desktop/fynla && php artisan migrate
cd /Users/CSJ/Desktop/fynla && php artisan pensions:backfill-derived
```

### Step 6.11: Run full suite + commit + PR + csjones smoke

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

- [ ] **Commit + PR. The csjones smoke for PR 6:**
  1. Deploy migrations + backfill command to csjones.
  2. Run `php artisan migrate && php artisan pensions:backfill-derived` on csjones.
  3. Login → /retirement → verify the listing renders correctly (no errors from new columns).
  4. Inspect the DB via `php artisan tinker`: confirm `current_fund_value_gbp` and `current_fund_value_gbp_calculated_at` populated on all DC pension rows.
  5. Open Fyn chat → "Update my Aviva SIPP value to £55,000" → verify a snapshot row was written: `\App\Models\DCPensionValueSnapshot::latest()->first()`.

---

## Task 7 — PR 7: Tier-cap enforcement at store level

**PR title:** `feat(pensions): tier-cap enforcement at store level`

**Files:**
- Modify: `app/Services/Stores/StaticTierGate.php` (extend `LIMITS` with `pension_account`)
- Create: `tests/Feature/Stores/PensionTierCapTest.php`

### Step 7.1: Write the failing tier-cap test

- [ ] **Create `tests/Feature/Stores/PensionTierCapTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PensionStore;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('refuses to create a 6th pension (DC + DB combined) for a free-tier user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PensionStore::class);

    // Spec §13: free tier = 5 pension_account
    DCPension::factory(3)->create(['user_id' => $user->id]);
    DBPension::factory(2)->create(['user_id' => $user->id]);

    expect(fn () => $store->createDc(['scheme_name' => 'Sixth', 'current_fund_value' => 100], $user, IngestSource::FORM))
        ->toThrow(TierLimitExceededException::class);

    expect(DCPension::count() + DBPension::count())->toBe(5);
});

it('also refuses createDb when combined count is at the cap', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PensionStore::class);

    DCPension::factory(5)->create(['user_id' => $user->id]);

    expect(fn () => $store->createDb(['scheme_name' => 'Sixth', 'scheme_type' => 'final_salary'], $user, IngestSource::FORM))
        ->toThrow(TierLimitExceededException::class);
});

it('allows unlimited for tier1+', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PensionStore::class);

    DCPension::factory(10)->create(['user_id' => $user->id]);

    $store->createDc(['scheme_name' => 'Eleventh', 'current_fund_value' => 100], $user, IngestSource::FORM);

    expect(DCPension::count())->toBe(11);
});

it('state pension is not capped (one-per-user is the natural cap)', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PensionStore::class);

    DCPension::factory(5)->create(['user_id' => $user->id]);

    // upsertState should NOT enforce the pension_account cap — it's
    // semantically one-per-user, not a "pension account" entry.
    $store->upsertState(['ni_years_completed' => 10], $user, IngestSource::FORM);

    expect(\App\Models\StatePension::where('user_id', $user->id)->count())->toBe(1);
});
```

- [ ] **Run, confirm failures on the 6th-create cases (PermissiveTierGate / StaticTierGate without pension_account entry currently allows).**

### Step 7.2: Extend `StaticTierGate::LIMITS`

- [ ] **Edit `app/Services/Stores/StaticTierGate.php`. Add the `pension_account` entry to the `LIMITS` constant per spec §13:**

```php
private const LIMITS = [
    'savings_account' => ['free' => 3, 'tier1' => null, 'tier2' => null, 'tier3' => null],
    'pension_account' => ['free' => 5, 'tier1' => null, 'tier2' => null, 'tier3' => null],
    // (other entity keys added by their respective passes)
];
```

(If Pass 1 PR 7 didn't ship and `PermissiveTierGate` is still bound, this PR is a no-op — flag to CSJ. The Pest test will be RED until either `StaticTierGate` ships or this PR is paused. **Default if `StaticTierGate` isn't bound: ship the LIMITS extension anyway so it's ready when SP1 PR 7 backports.**)

### Step 7.3: Run + verify

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/Stores/PensionTierCapTest.php
```

Expected: PASS.

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: full suite green. **Note**: existing tests that create > 5 pensions on free-tier users may break. Either:
- Update the affected test factories to default `tier = tier1`
- Or set `tier = tier1` explicitly in the affected tests

Document the decision in the commit message.

### Step 7.4: Commit + PR + csjones smoke

- [ ] **Commit + PR.** csjones smoke:
  1. Create a free-tier test user
  2. Add 5 pensions via Fyn chat (mix of DC and DB)
  3. Try to add a 6th → confirm Fyn surfaces "you've reached your free-tier pension limit"
  4. (Optional, if admin upgrade path exists) upgrade user → confirm 6th adds successfully

- [ ] **If sub-project 2's tier model isn't ready and CSJ wants to defer enforcement, this PR can ship with the LIMITS extension but the binding unchanged (PermissiveTierGate keeps everything unlimited). Flag in the PR description.**

---

## Task 8 — PR 8: Final sweep — allowlist locked, audit ingest_source captured

**PR title:** `lock-down(pensions): boundary allowlist reduced to permanent entries, audit captures ingest_source`

**Files:**
- Modify: `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php` (final allowlist = permanent entries only)
- Modify: `app/Services/Stores/PensionStore.php` (pass `ingest_source` through to the audit when persisting — same hook pattern used by Pass 1 PR 8)
- Create: `tests/Feature/Stores/PensionAuditIngestSourceTest.php`

### Step 8.1: Confirm the allowlist can be reduced

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && grep -rln "DCPension::\|DBPension::\|StatePension::\|PensionInputHistory::" app/ database/ 2>/dev/null
```

Cross-reference with the expected permanent allowlist:
- `App\Services\Stores\PensionStore`
- `App\Services\Stores\Normalisers\PensionNormaliser`
- `App\Services\Stores\Recalc\PensionDerivedColumnCalculator`
- `App\Observers\DCPensionRiskObserver`
- `App\Models\DCPensionValueSnapshot`
- `App\Models\DBPensionValueSnapshot`
- `App\Models\StatePensionValueSnapshot`
- `App\Models\User` (relationship methods only — confirm read-only)
- `Database\Factories\DCPensionFactory`
- `Database\Factories\DBPensionFactory`
- `Database\Factories\StatePensionFactory`
- Migrations (Pest `arch()` ignores migrations by default — confirm)
- `App\Console\Commands\EncryptExistingData` (console-permitted; confirm it doesn't mutate)
- `App\Console\Commands\ResetPreviewData` (console-permitted; confirm it doesn't mutate)

If any file outside this set still references the pension models, audit it — it's either a missed read site (route through store) or a legitimate exception (add a documented comment in the allowlist).

### Step 8.2: Update arch test allowlist to the final shape

- [ ] **Edit `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php` — replace the `$pensionConsumers` array with the permanent-only set:**

```php
$pensionConsumers = [
    'App\Services\Stores\PensionStore',
    'App\Services\Stores\Normalisers\PensionNormaliser',
    'App\Services\Stores\Recalc\PensionDerivedColumnCalculator',
    'App\Observers\DCPensionRiskObserver',
    'App\Models\DCPensionValueSnapshot',
    'App\Models\DBPensionValueSnapshot',
    'App\Models\StatePensionValueSnapshot',
    'App\Models\User', // relationship methods only (verified at lockdown)
    'Database\Factories\DCPensionFactory',
    'Database\Factories\DBPensionFactory',
    'Database\Factories\StatePensionFactory',
    // Console commands legitimately direct-touch per spec §14.2 allowlist:
    'App\Console\Commands\EncryptExistingData',
    'App\Console\Commands\ResetPreviewData',
];
```

### Step 8.3: Capture ingest_source in audit rows

- [ ] **Read `app/Traits/Auditable.php` to find the metadata extension point. The `Auditable` trait pipeline writes `audit_logs` rows on model events. To attach `ingest_source` to the audit metadata, use whatever hook the trait exposes (likely `auditWith(['ingest_source' => $source->value])` or a static `Model::withAuditContext(...)` pattern; if neither, extend the trait minimally per Pass 1 PR 8's approach).**

- [ ] **In `PensionStore::createDc / updateDc / deleteDc / createDb / updateDb / deleteDb / upsertState / captureInputHistory`, wrap each persist call in the context block:**

```php
// Pattern:
\App\Models\DCPension::withAuditContext(['ingest_source' => $source->value], function () use ($payload) {
    return \App\Models\DCPension::create($payload);
});
```

(Adapt to the actual trait API — if it's a different shape, follow Pass 1 PR 8's implementation as the reference.)

### Step 8.4: Write the audit-ingest-source feature test

- [ ] **Create `tests/Feature/Stores/PensionAuditIngestSourceTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PensionStore;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('createDc writes an audit row with ingest_source', function () {
    $user = User::factory()->create();

    $pension = app(PensionStore::class)->createDc(
        ['scheme_name' => 'AuditTest', 'current_fund_value' => 100],
        $user,
        IngestSource::FYN_AI
    );

    $auditRow = \App\Models\AuditLog::where('auditable_type', DCPension::class)
        ->where('auditable_id', $pension->id)
        ->latest()->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('createDb writes an audit row with ingest_source', function () {
    $user = User::factory()->create();

    $pension = app(PensionStore::class)->createDb(
        ['scheme_name' => 'AuditDB', 'scheme_type' => 'final_salary', 'accrued_annual_pension' => 1000],
        $user,
        IngestSource::FORM
    );

    $auditRow = \App\Models\AuditLog::where('auditable_type', \App\Models\DBPension::class)
        ->where('auditable_id', $pension->id)
        ->latest()->first();

    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('form');
});

it('upsertState writes an audit row with ingest_source on first write', function () {
    $user = User::factory()->create();

    $state = app(PensionStore::class)->upsertState(
        ['ni_years_completed' => 10],
        $user,
        IngestSource::UPLOAD
    );

    $auditRow = \App\Models\AuditLog::where('auditable_type', \App\Models\StatePension::class)
        ->where('auditable_id', $state->id)
        ->latest()->first();

    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('upload');
});
```

(Confirm the actual audit model name — it may be `AuditLog`, `Audit`, etc. Read `app/Traits/Auditable.php` and `app/Models/AuditLog.php` to verify before running.)

### Step 8.5: Run + commit + PR + csjones smoke

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

- [ ] **Commit + PR. csjones smoke for PR 8:**
  1. Login → /retirement → create a DC pension via UI → verify `AuditLog::latest()->first()->metadata['ingest_source']` is `'form'`
  2. Open Fyn → "Add my Aviva SIPP £25k" → verify audit row's `ingest_source` is `'fyn_ai'`
  3. Upload a pension statement → verify the new DC pension's audit row has `ingest_source = 'upload'`
  4. Run the arch test: `./vendor/bin/pest --testsuite=Architecture` → confirm green with the locked-down allowlist.

- [ ] **Admin-merge after green. Pass 3 complete.**

---

## Acceptance gate for pass 3 closure

After PR 8 merges, verify pass-3 acceptance per spec §16.1 (every entity must pass before moving on):

1. [ ] **Single write path** — `PensionStoreBoundaryTest` green with locked-down allowlist (4 model arch assertions all green).
2. [ ] **Three-ingest parity** — `PensionThreeIngestParityTest` asserts identical rows from form / fyn / upload for DC (and form / fyn for DB + State, since DB has no upload path and State has no Fyn-direct path — handled per `PensionThreeIngestParityTest`'s docblock).
3. [ ] **Audit completeness** — every write produces an audit row with `ingest_source` populated (PR 8 test).
4. [ ] **Derived-column correctness** — `current_fund_value_gbp`, `projected_value_at_retirement_gbp`, `annual_contribution_gbp`, `projected_annual_pension_at_nra_gbp`, `state_pension_forecast_annual_gbp` match independent recomputation in tests.
5. [ ] **Snapshot policy applied** — thresholds fire; sub-threshold updates don't snapshot.
6. [ ] **Currency round-trip** — pass 3 ships GBP-native (full multi-currency lands when the broader spec §9 rollout reaches pensions). Confirm `current_fund_value_gbp == current_fund_value` and explicit GBP default in snapshots.
7. [ ] **Tier-cap enforcement** — `PensionTierCapTest` green (or PR 7 deferred per CSJ decision).
8. [ ] **Browser-tested via Playwright** — every PR has a recorded csjones smoke.

Only after every box is checked does pass 4 (Properties — per spec §15.3) start. The pass-4 plan will live at `docs/superpowers/plans/2026-MM-DD-sub-project-1-pass-4-properties-plan.md`.

---

## Out of scope (explicit)

The following are explicitly *not* in pass 3 (most are in later passes or out of scope for SP1 entirely):

- **Holdings** (the `holdings` table polymorphic to DC pensions). The DC pension store leaves holdings creation/update/delete to `DCPensionHoldingsController` and `RetirementController::storeDCPension`'s `seedHoldingsForDcPension` helper. Holdings get their own store in Pass 6 (Investments) — see spec §3.1 #2.
- **Pension transfers (DB → DC)**. Spec rule: not provided as advice; only captured for projection. No store API for transfers in pass 3.
- **Lifetime Allowance (LTA) tracking**. The LTA was abolished from 2024-25; existing tax-config code may still reference it, but pass 3 does not add LTA-specific derived columns. The Annual Allowance (AA) and Money Purchase Annual Allowance (MPAA) are tracked via the existing `salary_sacrifice` / `employer_ni_rebate_pct` / `has_flexibly_accessed` flags on `DCPension`, and via `PensionInputHistory` for AA carry-forward.
- **Multi-currency support for pension values**. Spec §9 rolls this out per user-data entity. Pass 3 ships `current_fund_value_gbp` as a 1:1 reflection of `current_fund_value` (all DC funds assumed GBP-native today). When the multi-currency rollout reaches pensions, a `current_fund_value_currency` column + rate-conversion path lands as a follow-up.
- **Cross-pension-type aggregation services**. `PensionStore::forUser($user)` returns the four typed collections; any "give me a single total annual income at retirement" calculation lives in the retirement domain (`RetirementIncomeService` / `PensionProjector`), not in the store.
- **Snapshot pruning job**. The nightly pruning job that enforces retention + max-rows policies is a sub-project-1-wide concern (per spec §10.3), not pass 3-specific. The snapshot tables ship with the right indexes; the pruning job is scheduled in a later, pass-independent PR.

---

## Risks and mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| `RetirementController` is large (~791 lines) — the PR 2 diff may exceed the 500-line auto-split threshold | Medium | Low | PR 2 auto-splits along the controller method clusters (DC / DB / State / portfolio analysis) per spec §15.1. Each sub-PR is mergeable independently and shrinks the arch allowlist incrementally. |
| Pension derived-column calculations depend on `User::date_of_birth` which may be null for some users | Medium | Medium | `PensionDerivedColumnCalculator` returns `null` for `years_to_drawdown` / `years_to_state_pension_age` when DOB is missing. Downstream consumers (charts, projection services) treat null as "unknown" — the existing code already handles this. Backfill command tolerates nulls cleanly. |
| Snapshot tables grow unboundedly for users who edit pension values frequently | Low | Medium | Snapshot policies use both threshold-based filtering AND `maxRowsHardCap: 5000` per column per pension. Sub-project-wide pruning job enforces retention. |
| `AnnualAllowanceChecker` and `PensionAACarryForwardStrategy` consume `PensionInputHistory` reads — PR 5d refactor must preserve their existing read semantics exactly | Medium | High | Each strategy gets a parity test in `PensionReadConsumerParityTest` before refactor. Pre-refactor baseline values captured first. CSJ verifies the strategy outputs unchanged via the live admin → tax-strategy explorer. |
| `CoordinatingAgent` Fyn `create_pension` tool envelope must stay byte-identical for the eval suite | High | High | PR 3 explicitly re-runs the existing `tests/Feature/AI/DirectWrite/CreatePensionTest.php` (and the Fyn eval cassette `pensions_2x_schemes.yaml`) before claiming PR 3 green. The envelope keys (`success`, `created`, `entity_type`, `entity_id`, `name`, `persisted_fields`) are preserved exactly. |
| `PreviewUserSeeder` line 1094 / 1106 has TWO `StatePension::create` calls for one persona — the `upsertState` swap reduces this to one row per user, which may break the persona's expected data | Medium | Low | Before PR 4, read the persona JSON for lines 1094 / 1106 to confirm whether the two State entries belong to two different users (one row each — fine) or one user (real persona-data bug). If a real bug, fix the persona JSON to a single State entry; document in the PR description. |
| `Auditable` trait may not yet support a per-write metadata hook for `ingest_source` (PR 8 dependency) | Medium | Medium | If Pass 1 PR 8 already extended `Auditable` with `withAuditContext` (or equivalent), reuse it. If not, this pass extends the trait minimally — the extension is shared across all SP1 stores and is non-breaking. |
| Pass 1's `TierGate` may still be bound to `PermissiveTierGate` when Pass 3 PR 7 ships (sub-project 2 not yet wired) | High | Low | PR 7's commit message documents the state. If `PermissiveTierGate` is still bound, the test currently asserts behaviour under `StaticTierGate` — wrap the test in `$this->app->bind(TierGate::class, StaticTierGate::class)` at the top to make it independent of the production binding. |

---

## Dependencies on other sub-projects

| Depends on | What we need from them | How pass 3 unblocks |
|------------|------------------------|----------------------|
| SP1 Pass 1 (Savings) | `IngestSource` enum, `TierGate` interface + `PermissiveTierGate`, `StoreValidationException`, `TierLimitExceededException`, `SnapshotPolicy`, `SnapshotPolicies`, `Auditable` audit-context extension (PR 8) | Pass 3 reuses every Pass 1 building block; the only Pass-3-specific shared infrastructure added is per-entity event classes and the per-entity arch test pattern. |
| SP1 Pass 2 (Reference data) | `TaxConfigStore` for AA / MPAA / state pension age lookups; `ActuarialLifeTableStore` for decumulation horizons | Pass 3 reads via these stores rather than the legacy `TaxConfigService::get*()` path where possible. If Pass 2 R3 (Actuarial) ships after pass 3 starts, the calculator falls back to `actuarial_life_tables` direct reads — flagged in PR 6's commit message and migrated when Pass 2 R3 lands. |
| SP2 (Freemium) | Real tier-cap numbers from the freemium tier model | Pass 3 PR 7 hardcodes the spec §13 default (`pension_account: free=5, tier1+=unlimited`) in `StaticTierGate`. SP2 swaps this for a database-backed `DbTierGate` later. |
| SP6 (Gamification) | List of pension-related achievements (e.g. "First pension added", "10k DC value reached") | Pass 3 emits the events; SP6 wires listeners. No upstream dependency. |

---

## Self-Review

**Spec coverage check:**

| Spec section | Covered by |
|---|---|
| §3.1 #6 Pensions | Entire plan |
| §4.1 one store per entity | PR 1 ships single `PensionStore` for all four pension models |
| §4.2 three ingest paths converge | PR 1 + PR 2 (form) + PR 3 (fyn) + PR 4 (upload + seeder) |
| §4.3 calcs in backend, materialised columns | PR 6 derived columns + snapshot tables |
| §4.4 history preserved, snapshot policy | PR 6 snapshot tables + `SnapshotPolicies::dcPension*` / `dbPension*` / `statePension*` |
| §4.5 boundary enforced in CI | PR 1 arch test ships hard-fail; PRs 2/3/4/5/8 shrink the allowlist |
| §5 store contract (read/write methods) | PR 1 ships `find`, `forUser`, `forUserByType`, `statePension`, `pensionInputHistory`, `createDc`, `updateDc`, `deleteDc`, `restoreDc`, `createDb`, `updateDb`, `deleteDb`, `restoreDb`, `upsertState`, `captureInputHistory`, `updateOrCreateDc` |
| §6 ingest paths (form / fyn / upload) | PR 2 / PR 3 / PR 4 |
| §6.4 normaliser layer | PR 1 ships `PensionNormaliser` with `fromFormDc`, `fromFormDb`, `fromFormState`, `fromFynPension`, `fromFynInputHistory`, `fromUploadDc`, `fromUploadDb` |
| §7 validation (outer + inner) | FormRequests stay (outer); PR 1's `validateDcCanonical` / `validateDbCanonical` / `validateStateCanonical` are inner |
| §8.1 audit (ingest_source captured) | PR 8 |
| §8.3 authorisation (ownership) | PR 1 store methods all `where('user_id', $user->id)->firstOrFail()` |
| §9 currency normalisation | Partially deferred — PR 6 ships `_gbp` columns as 1:1 GBP reflections; multi-currency rollout per spec §9 lands as a follow-up |
| §10 derived columns + snapshots | PR 6 |
| §11 storage events | PR 1 ships 12 event classes; consumers register via `EventServiceProvider` in PR 5 |
| §12 reference-data | N/A for pass 3 (covered by pass 2) |
| §13 tier-aware count caps | PR 7 ships `pension_account` LIMIT |
| §14 boundary enforcement | PR 1 ships arch test; allowlist shrinks per PR; PR 8 locks down |
| §15 migration strategy (8 PRs per entity) | This plan uses 8 PRs + PR 0 (audit) — total 9 |
| §16 acceptance criteria | "Acceptance gate" section |
| §17 out of scope | "Out of scope" section |

**Placeholder scan:**

- "TBD" / "TODO" / "fill in" → none remain in this plan. (Earlier draft had one TBD on the AssetCaptureEntityExtractor duplicate-check policy; resolved in PR 5g notes — duplicate prevention stays in the caller, no store-level uniqueness validator.)
- "Similar to PR X" appears in Step 6.5 (snapshot tables migration — DB and State migrations are described as "same shape as DC"). The DC migration code is shown in full; engineer mechanically swaps the FK column name. This is acceptable per the writing-plans skill's "DRY" guidance.
- All code blocks include complete code for the step they appear in.
- Test cases include exact expected output (e.g. `toBe(50000.00)`, `toBe('career_average')`).

**Type consistency:**

- `IngestSource` enum cases (`FORM`, `FYN_AI`, `UPLOAD`, `SEEDER`, `ADMIN`) used identically across all steps
- `PensionStore` method signatures consistent: `createDc(array $data, User $user, IngestSource $source): DCPension` pattern matches `createDb`, `upsertState`, `captureInputHistory`
- `PensionNormaliser` method signatures consistent: `fromXxxDc(array): array`, all return canonical-shape array with `type` discriminator
- Event class names follow `{Entity}{Verb}` pattern (`DCPensionCreated`, `DBPensionUpdated`, `StatePensionUpserted`, `PensionInputHistoryCaptured`)
- `SnapshotPolicy` factory method names follow `{entity}{Column}` pattern (`dcPensionFundValue`, `dcPensionProjectedValue`, `dbPensionAnnualValue`, `statePensionForecast`)

**No bugs caught in self-review.** Plan is ready for execution.

---

## Execution

Plan saved to `docs/superpowers/plans/2026-05-24-sub-project-1-pass-3-pensions-plan.md`.

**Two execution options:**

**1. Subagent-Driven (recommended)** — dispatch a fresh subagent per PR (PR 0, PR 1, PR 2, …), two-stage review per PR (spec-compliance + code-quality), admin-merge after CSJ approval. Best for the long PR cadence — keeps each PR's context fresh. Pension is the most complex single entity per spec §15.3, so fresh context per PR is high-value.

**2. Inline Execution** — execute PRs in-session using `superpowers:executing-plans`. Batched checkpoints at PR 4 (write paths done), PR 5 end (read consumers done), and PR 8 (lockdown). Faster but heavier on context budget.

**Which approach?**

---

*End of plan.*
