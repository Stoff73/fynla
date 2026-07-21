# Sub-Project 1, Pass 5 — Mortgages Canonical Store Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the `MortgageStore` service facade so every read and write of `App\Models\Mortgage` goes through a single canonical API. Lock the boundary with a Pest architecture test that hard-fails CI on any direct model mutation outside the store. Materialise canonical derived columns (`outstanding_balance_gbp`, `monthly_payment_gbp`, `current_ltv_pct`, etc.) with a snapshot table. Reconcile `properties.outstanding_mortgage` as a true derived column recomputed from the canonical `mortgages` table via cross-store recalc — closing the open question Pass 4 deferred. Wire the tier-cap hook for `mortgage`. Close the Pass 5 acceptance gates in spec §16 — including the per-entity `Store.md` and the three-ingest parity test — within PR 8 from the start (no after-the-fact close-out like Pass 3 needed).

**Architecture:** Service-facade-over-Eloquent (Approach A from the original SP1 brainstorm) — the canonical contract from `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md`. Mortgages are the third joint-aware entity to migrate (Savings was Pass 1's joint-aware proof-of-pattern; Properties Pass 4 was second). Unlike Properties, Mortgages do **not** support `tenants_in_common` — the `ownership_type` enum is `{individual, joint}` only (see `MortgageService::normalizeMortgageOwnershipType`).

Pass 5 introduces the **cross-store recalc** pattern: a write to MortgageStore for `property_id=X` triggers `PropertyStore::recalculateDerivedForPropertyId(X)` which recomputes `properties.outstanding_mortgage` (sum of all linked mortgages' outstanding balances). Two stores stay decoupled at the boundary; one event listener wires them together at the recalc layer. This is the unique architectural contribution of Pass 5.

**Tech Stack:** Laravel 10, PHP 8.2, MySQL 8, Pest (PHPUnit-compatible), Sanctum auth, Vue/Vuex frontend (consumer surface only — touched in PR 5 for reads, no schema changes on the FE side).

---

## 0. Where this sits in the bigger picture

Pass 5 is the **fifth** of fourteen passes in Sub-Project 1 (Module Canonical Store-and-Retrieve Contract).

| Pass | Entity | Status |
|------|--------|--------|
| 1 | Savings (bank/cash) | DONE |
| 2 | Reference data (Tax / Currency / Actuarial / Savings rates) | DONE |
| 3 | Pensions (DC + DB + State + InputHistory) | DONE |
| 4 | Properties | DONE — merge `c972fff` 2026-05-27 (12 PRs, boundary LOCKED) |
| **5** | **Mortgages** | **THIS PLAN** |
| 5b (future) | Estate Liabilities (`App\Models\Estate\Liability`, unsecured consumer debt) | not started — separate plan; see §0.1 |
| 6 | Investments (multi-table) | pending |
| 7 | Income + Expenditure | pending |
| 8 | Protection | pending |
| 9 | Family members | pending |
| 10 | Goals + life events | pending |
| 11 | Chattels | pending |
| 12 | Business interests | pending |
| 13 | Trusts | pending |
| 14 | Wills + LPAs | pending |

### 0.1 Scope decision — Mortgages only, Estate Liabilities deferred

The canonical spec §3.1 row 4 lists this pass as "Liabilities | `liabilities`, mortgages" — suggesting **two** entities (the `liabilities` table backing `App\Models\Estate\Liability`, plus the `mortgages` table backing `App\Models\Mortgage`). This plan **narrows the scope to Mortgages only** for the following reasons:

1. **Architectural separation.** `App\Models\Mortgage` lives in `app/Models/Mortgage.php`; `App\Models\Estate\Liability` lives under `app/Models/Estate/` (Estate-module-namespaced). They have separate controllers (`MortgageController` + `EstateController`), separate factories, separate Vue surfaces (`Estate/LiabilityForm.vue`), and no shared store conceptually. Pairing them in one pass would require two parallel store implementations, a shared boundary-test scope, and ~50% more PRs — diverging from the established one-entity-per-pass cadence (excluding Pass 3 which shipped 4 *facets of the same conceptual entity* under PensionStore, not two unrelated entities).
2. **Natural pairing with Pass 4 Properties.** Spec §15.3 row 5 itself reads: "Pairs with properties (mortgages — `properties.outstanding_mortgage` is currently a denormalised cache; Pass 5 reconciles against the `mortgages` table as the canonical source). Logical next after Properties." The reconciliation is the unique-to-Pass-5 architectural piece. Folding Estate Liabilities in would dilute that focus.
3. **Established cadence is 8 PRs per pass.** Doing two entities at parallel sub-streams would inflate Pass 5 to ~16 PRs / ~5000 plan lines and stretch the pass over a week. Splitting yields two manageable passes shipped sequentially.
4. **Re-numbering downstream is unnecessary.** Estate Liabilities becomes Pass 5b (or folds into Pass 13 Trusts which is also Estate-module-scoped — to be decided when Pass 5b plan opens). Pass 6 (Investments) onward keeps its existing numbering.

**Out of scope for Pass 5 (explicit):**
- `App\Models\Estate\Liability` — Pass 5b / future plan.
- `App\Http\Controllers\Api\EstateController::liabilities*` — Pass 5b.
- `resources/js/components/Estate/LiabilityForm.vue` — Pass 5b.
- `App\Constants\UpdateRecordAllowlist::estate_liability` write paths — Pass 5b.

If this scope decision turns out to be wrong, the spec doc §15.3 row 5 + §21.1 row 5 + §3.1 row 4 need updating to reflect the split; Pass 5 plan is the place that initiates that change.

---

## 1. Pre-pass audit (PR 0 baseline)

Completed 2026-05-27 against `dev` HEAD `eb260fc` (post-Pass-4 close-out + spec doc update).

### 1.1 Files referencing `Mortgage` (~40 files)

| Category | Files |
|---|---|
| **Mutation sites** (8) | `MortgageController.php`, `PreviewController.php` (preview mortgage writes), `CoordinatingAgent.php` (Fyn AI `handleCreateMortgage`), `MortgageService.php::createFromPropertyData` (called by `PropertyController::store`), `DocumentProcessor.php`, `OnboardingService.php`, `AssetCaptureEntityExtractor.php`, `ChrisUserSeeder.php` + `PreviewUserSeeder.php` |
| **Model + relationships** (3) | `Mortgage.php` (self), `Property.php` (`hasMany(Mortgage::class)`), `User.php` (relationship + scopes) |
| **Console commands** (3) | `EncryptExistingData.php`, `ResetPreviewData.php`, `SendMortgageRateAlerts.php` |
| **Read consumers — Services** (~24) | `AdvicePromptBuilder.php`, `DuplicateAcknowledgement.php`, `HouseholdPlanningService.php`, `DocumentProcessor.php`, `DocumentTypeDetector.php`, `MortgageMapper.php` (upload field-mapper), `EstateAssetAggregatorService.php`, `EstateActionDefinitionService.php`, `EstateDataReadinessService.php`, `IHTFormattingService.php`, `LetterEstateValidationService.php`, `ComprehensiveEstatePlanService.php`, `MobileDashboardAggregator.php`, `NetWorthService.php`, `CrossModuleAssetAggregator.php`, `GoalsProjectionService.php`, `LifeEventService.php`, `SavingsPlanService.php`, `InvestmentPlanService.php`, `UserContextBuilder.php` (Investment recommendation), `TaxDragCalculator.php` (Investment), `PropertyService.php`, `PropertyCalculationService.php`, `MortgageService.php` (own calc helpers), `DataExportService.php`, `ProtectionDataReadinessService.php`, `LetterToSpouseService.php`, `PersonalAccountsService.php`, `UserProfileService.php` |
| **HTTP controllers** (8 referencing) | `InvestmentController.php`, `BusinessInterestController.php`, `GoalsController.php`, `ChattelController.php`, `PropertyController.php`, `PreviewController.php`, `MortgageController.php`, `EstateController.php` |
| **Agents** (6 referencing) | `CoordinatingAgent.php` (write + read), `EstateAgent.php`, `GoalsAgent.php`, `RetirementAgent.php`, `SavingsAgent.php`, `ProtectionAgent.php` |
| **Resources** | (none — `MortgageResource` doesn't exist; HTTP returns Mortgage models directly) |
| **Factories + fixtures** | `MortgageFactory.php`, `PreviewUserSeeder.php` (multiple persona mortgages), `ChrisUserSeeder.php` (1 mortgage on chris's main residence) |

### 1.2 Read patterns

Reads currently use:
- `Mortgage::forUserOrJoint($userId)->...` — joint-aware scope from `HasJointOwnership` trait (used in `EstateAssetAggregatorService:225, :239` and elsewhere)
- `$property->mortgages()->...` — HasMany relation from Property (used in `PropertyService`, `PropertyCalculationService`)
- `Mortgage::where('property_id', $propertyId)->...` — by-property listing (used in `MortgageController::index`)
- `Mortgage::where('id', $id)->where('user_id', $userId)->...` — single-id ownership lookup (`MortgageController::show/update/destroy` pattern)

Migration via the store funnels these into:
- `MortgageStore::find(int $id, User $user): ?Mortgage` (ownership-scoped, single record)
- `MortgageStore::forUser(User $user): Collection` (joint-aware, all user's mortgages — primary OR joint)
- `MortgageStore::forUserPrimaryOnly(User $user): Collection` (primary-owner-only — see §1.4 joint-aware contract)
- `MortgageStore::forProperty(int $propertyId, ?User $user): Collection` (by-property listing, ownership-scoped)
- `MortgageStore::forUserByProperty(User $user): Collection` (joint-aware, grouped/keyed by property_id — supports the "list mortgages per property" pattern)

### 1.3 Joint ownership shape

`Mortgage` uses the same `HasJointOwnership` trait as `Property` and `SavingsAccount`, with one critical difference:
- `ownership_type` ∈ `{individual, joint}` — **`tenants_in_common` is NOT valid for Mortgages**. Per `app/Services/Property/MortgageService.php:86` `normalizeMortgageOwnershipType()`, `tenants_in_common` gets coerced to `joint` at write time. The mortgages table's enum check enforces this.
- `joint_owner_id` (FK to users) — linked system user, NULL if not on Fynla
- `joint_owner_name` — free-text name when joint owner is not a system user
- `ownership_percentage` (decimal 5,2) — primary owner's share

Per CLAUDE.md Rule #7: joint mortgages use ONE row, not two. Spouse's share = `100 - ownership_percentage`. Reads use `WHERE user_id = ? OR joint_owner_id = ?` for joint-aware patterns.

**Pass 4 5a-review-loop lesson carries over:** `MortgageStore::forUser` is joint-aware (returns `user_id = ? OR joint_owner_id = ?`). For consumers that originally used `Mortgage::where('user_id', $userId)` (primary-only semantics), they MUST use `MortgageStore::forUserPrimaryOnly($user)` OR chain `->where('user_id', $user->id)` onto the Collection. `MortgageReadConsumerParityTest` (PR 5a) locks the contract for all subsequent PR 5 sub-clusters — identical pattern to Pass 4's `PropertyReadConsumerParityTest`.

### 1.4 Tier-cap key

`TierConfigurationSeeder` does **not** currently have a `mortgage` count_caps key. Pass 5 adds it.

**Default cap semantics — open question (resolve in PR 0 / PR 1):**
- Option A: `mortgage` free=null, tier1+=null (no cap; mortgages are property-bounded — each property can have 0-N mortgages, and properties are already capped at free=3).
- Option B: `mortgage` free=5, tier1+=null (allows 5 mortgages across all properties for free tier; protects against extreme edge cases like a free user uploading 200 historic mortgage records).

**Recommendation: Option B with cap=10 on free tier** — generous enough to never bite a real user (3 properties × ~3 historic refinances max = 9), but stops obvious abuse. Adjustable in `TierConfigurationSeeder` post-hoc by SP2 owners.

The mechanical pattern (add key + boundary integration + `MortgageTierCapTest` in PR 7) is identical to Property regardless of which cap number is chosen.

### 1.5 Existing factories + fixtures

- `database/factories/MortgageFactory.php` exists — used by Pest tests.
- `database/seeders/PreviewUserSeeder.php` creates mortgages for personas with main residences (`peak_earners`, `young_family`, possibly others).
- `database/seeders/ChrisUserSeeder.php` creates 1 mortgage on chris's main residence (£250k outstanding @ 4.5%).
- `database/seeders/LifecycleTestSeeder.php` — verify if it creates mortgages (audit in PR 4).

### 1.6 The `properties.outstanding_mortgage` reconciliation (unique to Pass 5)

This is the architectural piece deferred from Pass 4 — see Pass 4 plan §0:
> "the `outstanding_mortgage` column on `properties` is treated as a denormalised cache today (the real source of truth being the `mortgages` table) — Pass 5 will reconcile this."

**Current state (post-Pass-4):**
- `properties.outstanding_mortgage` is a fillable `decimal:2` column on Property — written directly by `PropertyController::store` from `StorePropertyRequest`'s `outstanding_mortgage` field.
- `PropertyDerivedColumnCalculator` (Pass 4 PR 6) reads `$property->outstanding_mortgage` directly (not via mortgages relation) — see `app/Services/Stores/Recalc/PropertyDerivedColumnCalculator.php:14` docblock: "`equity_gbp` uses the denormalised `outstanding_mortgage` … (cross-store recalc deferred to Pass 5)".
- `PropertyService` + `PropertyCalculationService` have fallback chains: prefer mortgages-relation sum, fall back to denormalised column.
- This means: if a user adds a Property via form with `outstanding_mortgage=£250k`, then adds a Mortgage record for that property with `outstanding_balance=£200k`, the two values are inconsistent until something reconciles them.

**Pass 5 reconciliation contract:**
- `properties.outstanding_mortgage` becomes a **derived column** — recomputed from `SUM(mortgages.outstanding_balance WHERE property_id = X AND deleted_at IS NULL)` whenever any Mortgage for that property is created/updated/deleted/restored.
- New companion column: `properties.outstanding_mortgage_calculated_at` (timestamp, nullable) — matches the Pass 4 derived-column timestamp pattern.
- The recompute happens via cross-store recalc: MortgageStore write → `PropertyStore::recalculateDerivedForPropertyId($propertyId)` → PropertyDerivedColumnCalculator reads canonical mortgages sum → updates `outstanding_mortgage` + `outstanding_mortgage_calculated_at` + `equity_gbp` (which depends on it).
- The fillable on Property stays, but **only MortgageStore + PropertyDerivedColumnCalculator are allowed to write it.** The boundary test does NOT catch this (it scopes to direct `Property::` mutations, not specific columns) — enforced by convention and reviewer vigilance.
- One-off `BackfillPropertyOutstandingMortgageCommand` artisan command (PR 6) rebases all properties to their canonical mortgages sum.
- StorePropertyRequest's `outstanding_mortgage` field stays but becomes a "soft hint" — written to Property by PropertyController, then immediately overwritten by the cross-store recalc when `MortgageService::createFromPropertyData` (routed through MortgageStore in PR 4) creates the linked Mortgage. For properties created without mortgages, `outstanding_mortgage=0` stays as-is.

This is documented in §10 (PR 6) below and lockcd in `MortgageStore.md` (PR 8).

### 1.7 Mortgage-specific calc helpers (out of scope for store)

`app/Services/Property/MortgageService.php` (280 lines) does two things:
1. `createFromPropertyData(Property, array $validated, User): ?Mortgage` — write path called by PropertyController. **In scope** — routed through MortgageStore in PR 4.
2. `generateAmortisationSchedule(Mortgage): array`, `calculateMonthlyPayment(...)`, `remainingTerm(...)` etc — pure calculation helpers. **Out of scope** — stay as-is. These take a `Mortgage` instance as input and return arrays of computed values. They don't mutate anything.

The calc helpers are read-only consumers — they're listed in §1.1 read consumers and migrated through PR 5 to use `MortgageStore::find` instead of direct `Mortgage::where`.

### 1.8 Existing events/observers

There are **no** existing `MortgageCreated` / `MortgageUpdated` / `MortgageDeleted` event classes, and no `MortgageObserver`. Pass 5 introduces the 4-event pattern from Property (`MortgageCreated`, `MortgageUpdated`, `MortgageDeleted`, `MortgageRestored`) in PR 1. The cross-store recalc listener that fires `PropertyStore::recalculateDerivedForPropertyId` listens to all 4 events.

`Mortgage` model uses `Auditable` trait — audit logging is already wired via the Eloquent model events; no change needed.

---

## 2. Scope

### 2.1 Files in scope

```
NEW
  app/Services/Stores/MortgageStore.php
  app/Services/Stores/Normalisers/MortgageNormaliser.php
  app/Services/Stores/Recalc/MortgageDerivedColumnCalculator.php  (PR 6)
  app/Listeners/Mortgage/RecalculatePropertyOutstandingMortgage.php  (PR 6 — cross-store recalc listener)
  app/Events/Mortgage/MortgageCreated.php
  app/Events/Mortgage/MortgageUpdated.php
  app/Events/Mortgage/MortgageDeleted.php
  app/Events/Mortgage/MortgageRestored.php
  app/Console/Commands/BackfillMortgageDerivedColumns.php  (PR 6)
  app/Console/Commands/BackfillPropertyOutstandingMortgage.php  (PR 6 — reconciles existing Property rows)
  app/Models/MortgageValueSnapshot.php  (PR 6)
  database/migrations/2026_05_28_*_add_derived_columns_to_mortgages.php  (PR 6)
  database/migrations/2026_05_28_*_create_mortgage_value_snapshots_table.php  (PR 6)
  database/migrations/2026_05_28_*_add_outstanding_mortgage_calculated_at_to_properties.php  (PR 6)
  tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php  (PR 1, locked in PR 8)
  tests/Unit/Services/Stores/MortgageStoreTest.php  (PR 1, extended through pass)
  tests/Unit/Services/Stores/MortgageStoreEventsTest.php  (PR 1)
  tests/Unit/Services/Stores/Normalisers/MortgageNormaliserTest.php  (PR 1)
  tests/Unit/Services/Stores/Recalc/MortgageDerivedColumnCalculatorTest.php  (PR 6)
  tests/Unit/Listeners/Mortgage/RecalculatePropertyOutstandingMortgageTest.php  (PR 6)
  tests/Feature/Stores/MortgageDerivedColumnsBackfillTest.php  (PR 6)
  tests/Feature/Stores/MortgagePropertyReconciliationTest.php  (PR 6 — cross-store integration)
  tests/Feature/Stores/MortgageTierCapTest.php  (PR 7)
  tests/Feature/Stores/MortgageAuditIngestSourceTest.php  (PR 8)
  tests/Feature/Stores/MortgageThreeIngestParityTest.php  (PR 8)
  tests/Feature/Stores/MortgageUploadIngestTest.php  (PR 4)
  tests/Feature/Stores/MortgageReadConsumerParityTest.php  (PR 5a — locks joint-aware contract for 5b–5e)
  tests/Feature/Stores/MortgageHttpIntegrationTest.php  (PR 2)
  app/Services/Stores/MortgageStore.md  (PR 8)

MODIFIED
  app/Models/Mortgage.php  (PR 6 — fillable + casts for derived columns; PR 1 may add a `bootedFromStore` guard helper)
  app/Models/Property.php  (PR 6 — add `outstanding_mortgage_calculated_at` to fillable + casts)
  app/Services/Stores/PropertyStore.php  (PR 6 — expose `recalculateDerivedForPropertyId(int $propertyId): void` public method)
  app/Services/Stores/Recalc/PropertyDerivedColumnCalculator.php  (PR 6 — switch from denormalised read to canonical mortgages sum)
  app/Services/Stores/Snapshots/SnapshotPolicies.php  (PR 6 — add `mortgageBalance` + `mortgageRate` policies)
  app/Providers/EventServiceProvider.php  (PR 1 — list 4 Mortgage events; PR 6 — wire the cross-store listener)
  app/Providers/AppServiceProvider.php  (PR 1 — IF a manual binding is needed; default auto-resolution should suffice)
  app/Http/Controllers/Api/MortgageController.php  (PR 2 — route writes through store)
  app/Http/Controllers/Api/PreviewController.php  (PR 2 — preview mortgage writes via store)
  app/Agents/CoordinatingAgent.php  (PR 3 — handleCreateMortgage / handleUpdate / handleDelete through store)
  app/Services/Property/MortgageService.php  (PR 4 — createFromPropertyData routes through store; PR 5 — calc helpers use MortgageStore::find)
  app/Services/Documents/DocumentProcessor.php  (PR 4 — Mortgage upload path through store)
  app/Services/Onboarding/OnboardingService.php  (PR 4 — onboarding Mortgage writes through store)
  app/Services/Onboarding/AssetCaptureEntityExtractor.php  (PR 4 — capture-extractor Mortgage writes through store)
  database/seeders/PreviewUserSeeder.php  (PR 4 — Mortgage creates via store::updateOrCreate)
  database/seeders/ChrisUserSeeder.php  (PR 4 — Mortgage create via store::updateOrCreate)
  database/seeders/LifecycleTestSeeder.php  (PR 4 — IF it creates Mortgages)
  database/seeders/TierConfigurationSeeder.php  (PR 1 — add 'mortgage' to count_caps)
  [~24 read-consumer service files]  (PR 5, sub-clustered 5a–5e)
```

### 2.2 Files out of scope

- **`App\Models\Estate\Liability` + `EstateController::liabilities*` + `Estate/LiabilityForm.vue`** — Pass 5b (see §0.1).
- **`app/Services/Property/PropertyCalculationService.php` for non-mortgage logic** — only the mortgage-balance reads route through MortgageStore in PR 5; the rest of the service is Property's domain.
- **`PropertyController` write paths** — already through PropertyStore in Pass 4. PR 4 of Pass 5 only changes the inline `MortgageService::createFromPropertyData` call invoked from PropertyController, NOT the Property writes themselves.
- **Front-end (Vue components)** — no Vue changes in this pass. HTTP API shape is preserved (Mortgage models with same fields).
- **`MortgageResource`** — does not currently exist. Optional add in PR 8; not blocking.
- **`SendMortgageRateAlerts` console command** — read-only consumer; touched in PR 5 only if it uses `Mortgage::` direct (audit in PR 5).

---

## 3. Dependencies + bindings

| Dependency | Status |
|---|---|
| `App\Services\Stores\IngestSource` enum (`FORM`, `FYN_AI`, `UPLOAD`, `SEEDER`, `ADMIN`) | EXISTS from Pass 1 |
| `App\Services\Stores\TierGate` interface | EXISTS from Pass 1 |
| `App\Services\Stores\TierConfigurationStore` + `DbTierGate` binding | EXISTS from SP2 PR 3 |
| `App\Services\Stores\Snapshots\SnapshotPolicies` + `SnapshotPolicy` | EXISTS from Pass 1; extend in PR 6 |
| `App\Models\AuditLog::withContext()` | EXISTS from Pass 1 PR 8 |
| `App\Traits\Auditable` on Mortgage | EXISTS — Mortgage uses `Auditable, HasFactory, HasJointOwnership, SoftDeletes` (Mortgage.php:19) |
| `App\Services\Stores\PropertyStore::recalculateDerivedForPropertyId(int)` | NEW — added in PR 6 |
| `App\Services\Stores\Recalc\PropertyDerivedColumnCalculator` | EXISTS from Pass 4 PR 6 — modified in PR 6 of this pass |

No new global bindings. `AppServiceProvider` does not need changes unless `MortgageDerivedColumnCalculator` requires explicit DI for `TaxConfigService` / FX rate access — Laravel auto-resolution should handle it (see PR 6).

The cross-store recalc listener (`RecalculatePropertyOutstandingMortgage`) is registered in `EventServiceProvider::$listen` in PR 6 — one entry mapping all 4 Mortgage events to the listener.

---

## 4. Cross-PR conventions

These apply across every PR in this pass:

### 4.1 IngestSource enum reuse

The shared `App\Services\Stores\IngestSource` enum is used. No new enum file. The 5 values are: `FORM` (HTTP form requests), `FYN_AI` (Fyn AI chat tool), `UPLOAD` (document upload), `SEEDER` (database seeders / persona seeders), `ADMIN` (admin panel — N/A for mortgages, but included for enum-completeness).

### 4.2 Audit context

Every write method wraps its `DB::transaction` in `AuditLog::withContext(['ingest_source' => $source->value], …)` — established pattern from Pass 1 PR 8 + Pass 3 PR 8 + Pass 4 PR 1. **The wrap is added in PR 1 from the start** (not deferred to PR 8 like Pass 3 was). For `update` (which captures `$dirty` by reference), return a tuple `['fresh' => $fresh, 'dirty' => $dirty]` from the inner closure — avoids the by-reference-capture bug Pass 3 hit.

### 4.3 Joint ownership invariant

Every read method that returns rows visible to a user uses `WHERE user_id = ? OR joint_owner_id = ?`. The `HasJointOwnership` trait already provides `Mortgage::forUserOrJoint($id)` — the store wraps this. **Joint owners get READ-ONLY access; mutations require `$user->id === mortgage.user_id`** (matches Savings + Property + Mortgage's current controller behaviour).

### 4.4 `tenants_in_common` is NOT a Mortgage ownership_type

Unlike Property, Mortgage validation MUST reject `tenants_in_common` — the existing `MortgageService::normalizeMortgageOwnershipType` coerces it to `joint`, but at the **MortgageStore** boundary the validate-and-coerce happens in the normaliser (`MortgageNormaliser::fromForm` / `fromFyn` / `fromUpload`). The `MortgageStore::validateCanonical` enforces the enum strictly (no coercion at the store layer) — the normaliser is the only place that translates `tenants_in_common` from upstream into `joint` for mortgage records. This is documented in `MortgageStore.md` (PR 8) as quirk #1.

`MortgageThreeIngestParityTest` (PR 8) explicitly covers the case where upstream sends `ownership_type=tenants_in_common`: form, Fyn, and upload all produce a canonical mortgage row with `ownership_type=joint`.

### 4.5 Pint hook strips unused imports

When wiring `AuditLog::withContext`, add `use App\Models\AuditLog;` **after** the body references exist (Pint will strip the import otherwise — Pass 3 hit this ~10 times; Pass 4 subagent implementers hit it 3 times). Same for `use App\Services\Stores\MortgageStore;` style imports in tests + read-consumer services. **For sub-agent dispatch: instruct "add import AND use it in the constructor in the SAME edit so the formatter sees it as used."**

### 4.6 Commit cadence

Each step ending in `git commit` represents one local commit. Push to remote at PR open time. Admin-merge per the established CSJ pattern (after explicit "merge it" instruction). Branch convention: `feat/mortgage-store-prN` off `dev`.

### 4.7 Cross-store recalc invariant (Pass 5 unique)

When a Mortgage row is created/updated/deleted/restored for `property_id=X`:
1. The MortgageStore write completes (transaction committed, event dispatched).
2. The `RecalculatePropertyOutstandingMortgage` listener fires synchronously (not queued).
3. The listener calls `PropertyStore::recalculateDerivedForPropertyId($propertyId)`.
4. PropertyStore reads all non-deleted mortgages for that property via `MortgageStore::forProperty($propertyId, null)` (system-context, no user scoping — recalc is a system operation).
5. PropertyDerivedColumnCalculator recomputes `outstanding_mortgage` = `mortgages.outstanding_balance.sum()` + `outstanding_mortgage_calculated_at = now()` + `equity_gbp` (which depends on outstanding_mortgage).
6. Property row updated via `Property::saveQuietly()` — NO Property events fire (we don't want a recalc cascade).

**Loop prevention:** PropertyDerivedColumnCalculator does NOT call MortgageStore writes. Cross-store recalc is **one-way** (Mortgage → Property only). PropertyStore writes do NOT trigger MortgageStore writes — Property changes don't affect mortgages.

**Test coverage:** `MortgagePropertyReconciliationTest` (PR 6) integration-tests the full chain: create mortgage → assert property.outstanding_mortgage updated → update mortgage → assert property.outstanding_mortgage updated → delete mortgage → assert property.outstanding_mortgage updated. Also tests the no-loop case: create property → assert no Mortgage events fire.

### 4.8 Subagent dispatch pattern (lessons from Pass 4)

- Implementer (Sonnet) → spec reviewer (Opus `feature-dev:code-reviewer`) → code-quality reviewer (Opus `feature-dev:code-reviewer`) → CSJ admin-merge per PR.
- Pass 4 Sonnet implementers hit truncation 3 times on multi-file edits where formatter races with import additions. Mitigation: "add import AND use it in the constructor in the SAME edit".
- The spec reviewer has NO Bash tool — it can't run tests. Only the code-quality reviewer has Bash. Don't ask the spec reviewer to run a suite.
- After 2 SendMessage resumes, take over manually if the implementer stalls.

---

## 5. PR 1 — Introduce MortgageStore facade + boundary + normaliser + events

**PR title:** `feat(mortgages): introduce MortgageStore facade + arch boundary (SP1 Pass 5 PR 1)`

**Branch:** `feat/mortgage-store-pr1`

**What this PR delivers:**
- `MortgageStore` facade with 5 write methods (`create`, `update`, `updateOrCreate`, `delete`, `restore`) and 7 read methods (`find`, `forUser`, `forUserPrimaryOnly`, `forUserWithJointOwner`, `forProperty`, `forUserByProperty`, `findMany`).
- `MortgageNormaliser` with `fromForm` / `fromFyn` / `fromUpload` static methods.
- 4 event classes (`MortgageCreated`, `MortgageUpdated`, `MortgageDeleted`, `MortgageRestored`).
- `MortgageStoreBoundaryTest` — SOFT mode with full transition allowlist (every existing direct-write site listed; trimmed per-PR; LOCKED in PR 8).
- `MortgageStoreTest` (smoke), `MortgageStoreEventsTest`, `MortgageNormaliserTest`.
- `mortgage` key added to `TierConfigurationSeeder.count_caps`.
- `AuditLog::withContext` wraps all writes from the start.

### Step 1.1: Create the four event classes

**Files:**
- Create: `app/Events/Mortgage/MortgageCreated.php`
- Create: `app/Events/Mortgage/MortgageUpdated.php`
- Create: `app/Events/Mortgage/MortgageDeleted.php`
- Create: `app/Events/Mortgage/MortgageRestored.php`

- [ ] **Step 1.1.1: Create MortgageCreated event**

```php
<?php

declare(strict_types=1);

namespace App\Events\Mortgage;

use App\Models\Mortgage;
use Illuminate\Foundation\Events\Dispatchable;

final class MortgageCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Mortgage $mortgage,
        public readonly int $userId,
    ) {}
}
```

- [ ] **Step 1.1.2: Create MortgageUpdated event**

```php
<?php

declare(strict_types=1);

namespace App\Events\Mortgage;

use App\Models\Mortgage;
use Illuminate\Foundation\Events\Dispatchable;

final class MortgageUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $dirty  Map of changed field → [from, to]
     */
    public function __construct(
        public readonly Mortgage $mortgage,
        public readonly int $userId,
        public readonly array $dirty,
    ) {}
}
```

- [ ] **Step 1.1.3: Create MortgageDeleted event**

```php
<?php

declare(strict_types=1);

namespace App\Events\Mortgage;

use App\Models\Mortgage;
use Illuminate\Foundation\Events\Dispatchable;

final class MortgageDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly Mortgage $mortgage,
        public readonly int $userId,
        public readonly bool $force,  // true for forceDelete, false for soft-delete
    ) {}
}
```

- [ ] **Step 1.1.4: Create MortgageRestored event**

```php
<?php

declare(strict_types=1);

namespace App\Events\Mortgage;

use App\Models\Mortgage;
use Illuminate\Foundation\Events\Dispatchable;

final class MortgageRestored
{
    use Dispatchable;

    public function __construct(
        public readonly Mortgage $mortgage,
        public readonly int $userId,
    ) {}
}
```

### Step 1.2: Create the MortgageNormaliser

**File:** Create `app/Services/Stores/Normalisers/MortgageNormaliser.php`

- [ ] **Step 1.2.1: Implement the normaliser**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Models\User;

/**
 * MortgageNormaliser — translates upstream ingest shapes into a canonical
 * payload accepted by MortgageStore::create / ::update.
 *
 * Canonical schema (post-normalisation):
 *   property_id (int, required)
 *   user_id (int, required — set by caller, NOT from input)
 *   lender_name (string)
 *   mortgage_account_number (string|null — encrypted on save via Mortgage accessor)
 *   mortgage_type ('repayment' | 'interest_only' | 'mixed')
 *   repayment_percentage (decimal:2 | null)
 *   interest_only_percentage (decimal:2 | null)
 *   original_loan_amount (decimal:2 | null)
 *   outstanding_balance (decimal:2)
 *   interest_rate (decimal:4 — annual %, e.g. 4.5000 for 4.5%)
 *   rate_type ('fixed' | 'variable' | 'mixed')
 *   fixed_rate_percentage (decimal:2 | null)
 *   variable_rate_percentage (decimal:2 | null)
 *   fixed_interest_rate (decimal:4 | null)
 *   variable_interest_rate (decimal:4 | null)
 *   rate_fix_end_date (Y-m-d | null)
 *   monthly_payment (decimal:2)
 *   monthly_interest_portion (decimal:2 | null)
 *   start_date (Y-m-d)
 *   maturity_date (Y-m-d)
 *   remaining_term_months (int)
 *   ownership_type ('individual' | 'joint' — tenants_in_common coerced to joint)
 *   ownership_percentage (decimal:2 — primary owner's share; default 100.00 for individual, 50.00 for joint)
 *   joint_owner_id (int|null — FK to users; NULL if not on Fynla)
 *   joint_owner_name (string|null — free-text, used when joint_owner_id is null)
 *   notes (string|null)
 */
final class MortgageNormaliser
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromForm(array $data, User $user): array
    {
        return self::normalise($data, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromFyn(array $data, User $user): array
    {
        // Fyn AI passes user-friendly field names; map to canonical schema.
        $mapped = [
            'property_id' => $data['property_id'] ?? null,
            'lender_name' => $data['lender_name'] ?? $data['lender'] ?? 'To be completed',
            'mortgage_type' => $data['mortgage_type'] ?? 'repayment',
            'original_loan_amount' => $data['original_loan_amount'] ?? $data['original_amount'] ?? null,
            'outstanding_balance' => $data['outstanding_balance'] ?? $data['outstanding_mortgage'] ?? $data['balance'] ?? 0,
            'interest_rate' => $data['interest_rate'] ?? $data['rate'] ?? 0.0,
            'rate_type' => $data['rate_type'] ?? 'fixed',
            'monthly_payment' => $data['monthly_payment'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'maturity_date' => $data['maturity_date'] ?? null,
            'remaining_term_months' => $data['remaining_term_months'] ?? 300,
            'ownership_type' => $data['ownership_type'] ?? 'individual',
            'ownership_percentage' => $data['ownership_percentage'] ?? null,
            'joint_owner_id' => $data['joint_owner_id'] ?? null,
            'joint_owner_name' => $data['joint_owner_name'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        return self::normalise($mapped, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromUpload(array $data, User $user): array
    {
        // Upload field-mapper (MortgageMapper) produces canonical shape already.
        return self::normalise($data, $user);
    }

    /**
     * Shared normalisation: ownership_type coercion, percentage defaults, date casts.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalise(array $data, User $user): array
    {
        $data['user_id'] = $user->id;

        // tenants_in_common → joint coercion (mortgages don't support TIC)
        $ownership = $data['ownership_type'] ?? 'individual';
        if ($ownership === 'tenants_in_common') {
            $ownership = 'joint';
        }
        if (! in_array($ownership, ['individual', 'joint'], true)) {
            $ownership = 'individual';
        }
        $data['ownership_type'] = $ownership;

        // ownership_percentage default
        if (! isset($data['ownership_percentage']) || $data['ownership_percentage'] === null) {
            $data['ownership_percentage'] = $ownership === 'joint' ? 50.00 : 100.00;
        }

        // Cast numeric fields
        foreach (['outstanding_balance', 'original_loan_amount', 'monthly_payment', 'monthly_interest_portion', 'fixed_rate_percentage', 'variable_rate_percentage', 'repayment_percentage', 'interest_only_percentage'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = (float) $data[$field];
            }
        }
        foreach (['interest_rate', 'fixed_interest_rate', 'variable_interest_rate'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = round((float) $data[$field], 4);
            }
        }

        // remaining_term_months as int
        if (isset($data['remaining_term_months'])) {
            $data['remaining_term_months'] = (int) $data['remaining_term_months'];
        }

        return $data;
    }
}
```

### Step 1.3: Write the failing MortgageNormaliser test

**File:** Create `tests/Unit/Services/Stores/Normalisers/MortgageNormaliserTest.php`

- [ ] **Step 1.3.1: Write 5 test cases (form, Fyn, upload, TIC-coercion, joint-percentage default)**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\Normalisers\MortgageNormaliser;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('normalises form data into canonical shape', function () {
    $form = [
        'property_id' => 1,
        'lender_name' => 'Nationwide',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => '250000.00',
        'interest_rate' => '4.5',
        'rate_type' => 'fixed',
        'monthly_payment' => '1500',
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => '240',
        'ownership_type' => 'individual',
    ];

    $canonical = MortgageNormaliser::fromForm($form, $this->user);

    expect($canonical['user_id'])->toBe($this->user->id);
    expect($canonical['outstanding_balance'])->toBe(250000.0);
    expect($canonical['interest_rate'])->toBe(4.5);
    expect($canonical['remaining_term_months'])->toBe(240);
    expect($canonical['ownership_type'])->toBe('individual');
    expect($canonical['ownership_percentage'])->toBe(100.00);
});

it('normalises Fyn AI data with alternate field names', function () {
    $fyn = [
        'property_id' => 1,
        'lender' => 'Halifax',  // alternate name
        'balance' => 180000,    // alternate name
        'rate' => 5.25,         // alternate name
        'ownership_type' => 'individual',
    ];

    $canonical = MortgageNormaliser::fromFyn($fyn, $this->user);

    expect($canonical['lender_name'])->toBe('Halifax');
    expect($canonical['outstanding_balance'])->toBe(180000.0);
    expect($canonical['interest_rate'])->toBe(5.25);
});

it('coerces tenants_in_common to joint for mortgages', function () {
    $form = [
        'property_id' => 1,
        'lender_name' => 'Santander',
        'outstanding_balance' => 200000,
        'monthly_payment' => 1200,
        'ownership_type' => 'tenants_in_common',  // not valid for mortgages
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 300,
    ];

    $canonical = MortgageNormaliser::fromForm($form, $this->user);

    expect($canonical['ownership_type'])->toBe('joint');
});

it('defaults joint ownership_percentage to 50.00 when joint and unset', function () {
    $form = [
        'property_id' => 1,
        'lender_name' => 'Barclays',
        'outstanding_balance' => 300000,
        'monthly_payment' => 1800,
        'ownership_type' => 'joint',
        'joint_owner_id' => 2,
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 300,
    ];

    $canonical = MortgageNormaliser::fromForm($form, $this->user);

    expect($canonical['ownership_percentage'])->toBe(50.00);
    expect($canonical['joint_owner_id'])->toBe(2);
});

it('normalises upload data as canonical (field mapper already mapped)', function () {
    $upload = [
        'property_id' => 1,
        'lender_name' => 'Lloyds',
        'mortgage_type' => 'interest_only',
        'outstanding_balance' => 400000.0,
        'interest_rate' => 3.75,
        'rate_type' => 'variable',
        'monthly_payment' => 1250.0,
        'start_date' => '2018-06-01',
        'maturity_date' => '2043-06-01',
        'remaining_term_months' => 200,
        'ownership_type' => 'individual',
    ];

    $canonical = MortgageNormaliser::fromUpload($upload, $this->user);

    expect($canonical['user_id'])->toBe($this->user->id);
    expect($canonical['outstanding_balance'])->toBe(400000.0);
});
```

- [ ] **Step 1.3.2: Run test to verify it fails (normaliser class doesn't exist yet — but it does from Step 1.2)**

Run: `./vendor/bin/pest tests/Unit/Services/Stores/Normalisers/MortgageNormaliserTest.php`
Expected: PASS (normaliser was created in Step 1.2, so this is a verify-pass test)

- [ ] **Step 1.3.3: Commit normaliser + test**

```bash
git add app/Services/Stores/Normalisers/MortgageNormaliser.php tests/Unit/Services/Stores/Normalisers/MortgageNormaliserTest.php app/Events/Mortgage/
git commit -m "feat(mortgages): add MortgageNormaliser + 4 events (SP1 Pass 5 PR 1)"
```

### Step 1.4: Create the MortgageStore skeleton

**File:** Create `app/Services/Stores/MortgageStore.php`

- [ ] **Step 1.4.1: Implement the store**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageRestored;
use App\Events\Mortgage\MortgageUpdated;
use App\Models\AuditLog;
use App\Models\Mortgage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Canonical store for Mortgage entities. Every read and write of
 * App\Models\Mortgage MUST go through this class.
 *
 * Joint-ownership semantics: forUser returns user_id = ? OR joint_owner_id = ?
 * (joint-aware). For primary-only reads, use forUserPrimaryOnly. Pattern locked
 * by MortgageReadConsumerParityTest (PR 5a).
 *
 * Tier-cap key: 'mortgage' (free=10 by default, tier1+=null).
 *
 * Cross-store recalc: every write fires a Mortgage event which triggers
 * PropertyStore::recalculateDerivedForPropertyId. See §4.7 of the Pass 5 plan.
 */
final class MortgageStore
{
    public function __construct(
        private readonly TierGate $tierGate,
    ) {}

    // ─── READ METHODS ──────────────────────────────────────────────────────

    public function find(int $id, User $user): ?Mortgage
    {
        return Mortgage::forUserOrJoint($user->id)->find($id);
    }

    public function forUser(User $user): Collection
    {
        return Mortgage::forUserOrJoint($user->id)->get();
    }

    public function forUserPrimaryOnly(User $user): Collection
    {
        return Mortgage::where('user_id', $user->id)->get();
    }

    public function forUserWithJointOwner(User $user): Collection
    {
        return Mortgage::forUserOrJoint($user->id)->with('jointOwner')->get();
    }

    public function forProperty(int $propertyId, ?User $user = null): Collection
    {
        $query = Mortgage::where('property_id', $propertyId);
        if ($user !== null) {
            $query->forUserOrJoint($user->id);
        }
        return $query->get();
    }

    public function forUserByProperty(User $user): Collection
    {
        return $this->forUserWithJointOwner($user)->groupBy('property_id');
    }

    /**
     * @param  array<int>  $ids
     */
    public function findMany(array $ids, User $user): Collection
    {
        return Mortgage::forUserOrJoint($user->id)->whereIn('id', $ids)->get();
    }

    // ─── WRITE METHODS ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $canonical  Output of MortgageNormaliser::from*
     */
    public function create(array $canonical, User $user, IngestSource $source): Mortgage
    {
        $this->validateCanonical($canonical, partial: false);
        $this->tierGate->assertCanCreate($user, 'mortgage');

        return AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($canonical, $user) {
                $mortgage = Mortgage::create($canonical);
                MortgageCreated::dispatch($mortgage, $user->id);
                return $mortgage;
            })
        );
    }

    /**
     * @param  array<string, mixed>  $canonical  Partial — only changed fields
     */
    public function update(Mortgage $mortgage, array $canonical, User $user, IngestSource $source): Mortgage
    {
        if ($mortgage->user_id !== $user->id) {
            throw new RuntimeException('Cannot update a mortgage you do not own (joint owners are read-only)');
        }
        $this->validateCanonical($canonical, partial: true);

        $result = AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($mortgage, $canonical) {
                $mortgage->fill($canonical);
                $dirty = $mortgage->getDirty();
                $changes = [];
                foreach ($dirty as $field => $newValue) {
                    $changes[$field] = [$mortgage->getOriginal($field), $newValue];
                }
                $mortgage->save();
                return ['fresh' => $mortgage->fresh(), 'dirty' => $changes];
            })
        );

        MortgageUpdated::dispatch($result['fresh'], $user->id, $result['dirty']);
        return $result['fresh'];
    }

    /**
     * Find-or-create by (user_id, property_id, lender_name) — idempotent for seeders.
     *
     * @param  array<string, mixed>  $canonical
     */
    public function updateOrCreate(array $canonical, User $user, IngestSource $source): Mortgage
    {
        $this->validateCanonical($canonical, partial: false);

        return AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($canonical, $user) {
                $existing = Mortgage::where('user_id', $user->id)
                    ->where('property_id', $canonical['property_id'])
                    ->where('lender_name', $canonical['lender_name'])
                    ->first();

                if ($existing) {
                    $existing->fill($canonical);
                    $existing->save();
                    MortgageUpdated::dispatch($existing->fresh(), $user->id, []);
                    return $existing->fresh();
                }

                $this->tierGate->assertCanCreate($user, 'mortgage');
                $mortgage = Mortgage::create($canonical);
                MortgageCreated::dispatch($mortgage, $user->id);
                return $mortgage;
            })
        );
    }

    public function delete(Mortgage $mortgage, User $user, IngestSource $source, bool $force = false): void
    {
        if ($mortgage->user_id !== $user->id) {
            throw new RuntimeException('Cannot delete a mortgage you do not own');
        }

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($mortgage, $force) {
                $force ? $mortgage->forceDelete() : $mortgage->delete();
            })
        );

        MortgageDeleted::dispatch($mortgage, $user->id, $force);
    }

    public function restore(int $id, User $user, IngestSource $source): Mortgage
    {
        $mortgage = Mortgage::withTrashed()->where('user_id', $user->id)->findOrFail($id);
        if (! $mortgage->trashed()) {
            return $mortgage;
        }

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($mortgage) {
                $mortgage->restore();
            })
        );

        MortgageRestored::dispatch($mortgage->fresh(), $user->id);
        return $mortgage->fresh();
    }

    // ─── VALIDATION ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $canonical
     */
    private function validateCanonical(array $canonical, bool $partial): void
    {
        if (! $partial) {
            foreach (['property_id', 'user_id', 'lender_name', 'mortgage_type', 'outstanding_balance', 'monthly_payment', 'ownership_type', 'ownership_percentage'] as $required) {
                if (! array_key_exists($required, $canonical)) {
                    throw new InvalidArgumentException("Missing required field: {$required}");
                }
            }
        }

        if (isset($canonical['ownership_type']) && ! in_array($canonical['ownership_type'], ['individual', 'joint'], true)) {
            throw new InvalidArgumentException("Invalid ownership_type: {$canonical['ownership_type']} (mortgages do not support tenants_in_common)");
        }

        if (isset($canonical['mortgage_type']) && ! in_array($canonical['mortgage_type'], ['repayment', 'interest_only', 'mixed'], true)) {
            throw new InvalidArgumentException("Invalid mortgage_type: {$canonical['mortgage_type']}");
        }

        if (isset($canonical['outstanding_balance']) && (float) $canonical['outstanding_balance'] < 0) {
            throw new InvalidArgumentException('outstanding_balance must be >= 0');
        }

        if (isset($canonical['ownership_percentage'])) {
            $pct = (float) $canonical['ownership_percentage'];
            if ($pct < 0 || $pct > 100) {
                throw new InvalidArgumentException('ownership_percentage must be between 0 and 100');
            }
        }
    }
}
```

### Step 1.5: Write the failing MortgageStore unit test (smoke)

**File:** Create `tests/Unit/Services/Stores/MortgageStoreTest.php`

- [ ] **Step 1.5.1: Write the smoke test**

```php
<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
    $this->store = app(MortgageStore::class);
});

it('creates a mortgage via the store', function () {
    $canonical = [
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'lender_name' => 'Nationwide',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000.00,
        'interest_rate' => 4.5,
        'rate_type' => 'fixed',
        'monthly_payment' => 1500.00,
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 240,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $mortgage = $this->store->create($canonical, $this->user, IngestSource::FORM);

    expect($mortgage)->toBeInstanceOf(Mortgage::class);
    expect($mortgage->outstanding_balance)->toEqual(250000.00);
    expect($mortgage->user_id)->toBe($this->user->id);
});

it('rejects ownership_type=tenants_in_common', function () {
    $canonical = [
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'lender_name' => 'Nationwide',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000.00,
        'monthly_payment' => 1500.00,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 50.00,
    ];

    expect(fn () => $this->store->create($canonical, $this->user, IngestSource::FORM))
        ->toThrow(InvalidArgumentException::class, 'tenants_in_common');
});

it('returns joint-aware reads via forUser', function () {
    $spouse = User::factory()->create();
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'joint_owner_id' => $spouse->id,
        'property_id' => $this->property->id,
        'ownership_type' => 'joint',
    ]);

    expect($this->store->forUser($this->user)->count())->toBe(1);
    expect($this->store->forUser($spouse)->count())->toBe(1);  // joint-aware
    expect($this->store->forUserPrimaryOnly($spouse)->count())->toBe(0);  // primary-only
});

it('finds mortgages for a given property', function () {
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    expect($this->store->forProperty($this->property->id, $this->user)->count())->toBe(2);
});
```

- [ ] **Step 1.5.2: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Stores/MortgageStoreTest.php`
Expected: PASS (4 tests).

### Step 1.6: Write the MortgageStoreEventsTest

**File:** Create `tests/Unit/Services/Stores/MortgageStoreEventsTest.php`

- [ ] **Step 1.6.1: Test event dispatch for create/update/delete**

```php
<?php

declare(strict_types=1);

use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageUpdated;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
    $this->store = app(MortgageStore::class);
    Event::fake([MortgageCreated::class, MortgageUpdated::class, MortgageDeleted::class]);
});

it('dispatches MortgageCreated on create', function () {
    $canonical = [
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'lender_name' => 'Halifax',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 200000.00,
        'monthly_payment' => 1200.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $this->store->create($canonical, $this->user, IngestSource::FORM);

    Event::assertDispatched(MortgageCreated::class);
});

it('dispatches MortgageUpdated on update with dirty payload', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'outstanding_balance' => 200000,
    ]);

    $this->store->update($mortgage, ['outstanding_balance' => 195000], $this->user, IngestSource::FORM);

    Event::assertDispatched(MortgageUpdated::class, function ($event) {
        return array_key_exists('outstanding_balance', $event->dirty);
    });
});

it('dispatches MortgageDeleted on delete', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $this->store->delete($mortgage, $this->user, IngestSource::FORM);

    Event::assertDispatched(MortgageDeleted::class);
});
```

### Step 1.7: Seed the `mortgage` tier-cap

**File:** Modify `database/seeders/TierConfigurationSeeder.php`

- [ ] **Step 1.7.1: Add 'mortgage' to count_caps**

Find the existing `count_caps` array (look for `'property'` entry from Pass 4) and add `'mortgage'` with default cap=10 for free tier:

```php
'count_caps' => [
    // existing entries: savings_account, investment, pension_account, property, ...
    'mortgage' => ['free' => 10, 'tier1' => null, 'tier2' => null, 'tier3' => null],
],
```

- [ ] **Step 1.7.2: Run seeder + verify**

```bash
php artisan db:seed --class=TierConfigurationSeeder --force
```

Verify via tinker:

```bash
php artisan tinker --execute="echo json_encode(\App\Models\TierConfiguration::where('tier','free')->first()->count_caps);"
```

Expected: includes `"mortgage":10`.

### Step 1.8: Create the boundary architecture test (SOFT — full transition allowlist)

**File:** Create `tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php`

- [ ] **Step 1.8.1: Write the boundary test in SOFT mode**

```php
<?php

declare(strict_types=1);

/**
 * Boundary architecture test for MortgageStore.
 *
 * SP1 Pass 5 contract: every mutation of App\Models\Mortgage must go
 * through App\Services\Stores\MortgageStore. This test scans for direct
 * Mortgage::create / ::update / ::save / ::delete / ::forceDelete /
 * ::restore call sites outside the store + an allowlist that shrinks
 * each PR until LOCKED in PR 8.
 *
 * SOFT mode: allowlist contains every existing direct-write site for
 * incremental migration. LOCKED in PR 8 to a tight set of legitimate
 * exceptions (factory-based test setup, tinker, etc.).
 */

use PHPUnit\Framework\Assert;

it('enforces MortgageStore as the only write path for Mortgage', function () {
    $allowlist = [
        // PR 1 — initial transition allowlist (trimmed in subsequent PRs)
        'app/Http/Controllers/Api/MortgageController.php',       // PR 2 will trim
        'app/Http/Controllers/Api/PreviewController.php',        // PR 2 will trim
        'app/Agents/CoordinatingAgent.php',                      // PR 3 will trim
        'app/Services/Property/MortgageService.php',             // PR 4 will trim (createFromPropertyData)
        'app/Services/Documents/DocumentProcessor.php',          // PR 4 will trim
        'app/Services/Onboarding/OnboardingService.php',         // PR 4 will trim
        'app/Services/Onboarding/AssetCaptureEntityExtractor.php', // PR 4 will trim
        'database/seeders/PreviewUserSeeder.php',                // PR 4 will trim
        'database/seeders/ChrisUserSeeder.php',                  // PR 4 will trim
        'database/seeders/LifecycleTestSeeder.php',              // PR 4 will trim if used
        'app/Console/Commands/EncryptExistingData.php',          // PR 8 LOCKED — pre-existing migration command, document in MortgageStore.md
        'app/Console/Commands/ResetPreviewData.php',             // PR 8 LOCKED — admin reset, document
    ];

    $patterns = [
        '/\bMortgage::(create|insert|update|save|delete|forceDelete|restore|truncate)\b/',
        '/->mortgages\(\)->(create|insert|save|update|delete|forceDelete)\b/',
    ];

    $violations = [];
    $files = collect(File::allFiles(base_path('app')))
        ->merge(File::allFiles(base_path('database/seeders')))
        ->filter(fn ($f) => str_ends_with($f->getRelativePathname(), '.php'));

    foreach ($files as $file) {
        $relative = $file->getRelativePathname();
        $fullRel = $file->getPath() === base_path('app')
            ? "app/{$relative}"
            : "database/seeders/{$relative}";
        if (str_starts_with($fullRel, 'app/Services/Stores/')) {
            continue;  // store implementations are exempt
        }
        if (in_array($fullRel, $allowlist, true)) {
            continue;
        }
        $content = file_get_contents($file->getRealPath());
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $violations[] = $fullRel;
                break;
            }
        }
    }

    Assert::assertEmpty(
        $violations,
        "MortgageStore boundary violations (route through MortgageStore or add to allowlist): \n" . implode("\n", $violations)
    );
});
```

- [ ] **Step 1.8.2: Run boundary test — verify it passes in SOFT mode**

Run: `./vendor/bin/pest tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php`
Expected: PASS (allowlist covers all existing sites; trimmed per-PR).

### Step 1.9: Commit + open PR 1

- [ ] **Step 1.9.1: Run the targeted suites**

```bash
./vendor/bin/pest tests/Unit/Services/Stores/ tests/Unit/Services/Stores/Normalisers/ tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php
```

Expected: all PASS.

- [ ] **Step 1.9.2: Commit + push + open PR**

```bash
git add app/Services/Stores/MortgageStore.php app/Services/Stores/Normalisers/MortgageNormaliser.php app/Events/Mortgage/ tests/Unit/Services/Stores/MortgageStoreTest.php tests/Unit/Services/Stores/MortgageStoreEventsTest.php tests/Unit/Services/Stores/Normalisers/MortgageNormaliserTest.php tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php database/seeders/TierConfigurationSeeder.php
git commit -m "feat(mortgages): introduce MortgageStore facade + arch boundary + 4 events + tier-cap (SP1 Pass 5 PR 1)"
git push -u origin feat/mortgage-store-pr1
gh pr create --base dev --title "feat(mortgages): introduce MortgageStore facade + arch boundary (SP1 Pass 5 PR 1)" --body "..."
```

PR body should include:
- Link to plan: `docs/superpowers/plans/2026-05-27-sub-project-1-pass-5-mortgages-plan.md` §5
- What changes: store skeleton, normaliser, 4 events, boundary test (SOFT), tier-cap key
- Reviewer instructions: 1× spec reviewer (Opus `feature-dev:code-reviewer`), 1× code-quality reviewer (Opus `feature-dev:code-reviewer`), parallel dispatch

---

## 6. PR 2 — Point HTTP form requests at MortgageStore

**PR title:** `feat(mortgages): route HTTP form requests through MortgageStore (SP1 Pass 5 PR 2)`

**Branch:** `feat/mortgage-store-pr2` off `dev` post-PR-1-merge

**What this PR delivers:**
- `MortgageController::index/store/show/update/destroy` route through `MortgageStore`.
- `PreviewController` mortgage writes routed through store.
- Boundary allowlist drops `MortgageController` + `PreviewController`.
- `MortgageHttpIntegrationTest` covers the full happy path + error paths.

### Step 2.1: Audit the controller mutation sites

- [ ] **Step 2.1.1: List the call sites**

```bash
grep -n "Mortgage::create\|Mortgage::where\|->mortgages()\|Mortgage::find" app/Http/Controllers/Api/MortgageController.php app/Http/Controllers/Api/PreviewController.php
```

Expected output (from §1.1):
- `MortgageController.php:59` — index read
- `MortgageController.php:148` — store create
- `MortgageController.php:182` — show read
- `MortgageController.php:228` — update read+update
- `MortgageController.php:298` — destroy read
- `MortgageController.php:321` — restore (if exists)
- `PreviewController.php` — any preview-mortgage writes (audit needed)

### Step 2.2: Inject the normaliser + store into MortgageController

- [ ] **Step 2.2.1: Add constructor DI**

```php
// app/Http/Controllers/Api/MortgageController.php
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\Normalisers\MortgageNormaliser;

public function __construct(
    private readonly MortgageStore $mortgageStore,
    private readonly MortgageService $mortgageService,  // existing — calc helpers stay
) {}
```

### Step 2.3: Replace `store()` write site

- [ ] **Step 2.3.1: Route MortgageController::store through MortgageStore**

Replace the existing `Mortgage::create([...])` body at MortgageController.php:148 with:

```php
public function store(StoreMortgageRequest $request, int $propertyId): JsonResponse
{
    $user = $request->user();
    // Verify property ownership (existing pattern preserved)
    $property = Property::forUserOrJoint($user->id)->findOrFail($propertyId);

    $canonical = MortgageNormaliser::fromForm(
        array_merge($request->validated(), ['property_id' => $property->id]),
        $user
    );
    $mortgage = $this->mortgageStore->create($canonical, $user, IngestSource::FORM);

    return response()->json([
        'data' => $mortgage,
        'message' => 'Mortgage created successfully',
    ], 201);
}
```

### Step 2.4: Replace `update()` write site

- [ ] **Step 2.4.1: Route MortgageController::update through MortgageStore**

Replace MortgageController.php:228:

```php
public function update(UpdateMortgageRequest $request, ?int $propertyId = null, ?int $mortgageId = null): JsonResponse
{
    $user = $request->user();
    $id = $mortgageId ?? $propertyId;  // route param normalisation
    $mortgage = $this->mortgageStore->find($id, $user);
    abort_unless($mortgage !== null && $mortgage->user_id === $user->id, 404);

    $canonical = MortgageNormaliser::fromForm($request->validated(), $user);
    $fresh = $this->mortgageStore->update($mortgage, $canonical, $user, IngestSource::FORM);

    return response()->json([
        'data' => $fresh,
        'message' => 'Mortgage updated successfully',
    ]);
}
```

### Step 2.5: Replace `destroy()` write site

- [ ] **Step 2.5.1: Route MortgageController::destroy through MortgageStore**

```php
public function destroy(Request $request, ?int $propertyId = null, ?int $mortgageId = null): JsonResponse
{
    $user = $request->user();
    $id = $mortgageId ?? $propertyId;
    $mortgage = $this->mortgageStore->find($id, $user);
    abort_unless($mortgage !== null && $mortgage->user_id === $user->id, 404);

    $this->mortgageStore->delete($mortgage, $user, IngestSource::FORM);

    return response()->json(['message' => 'Mortgage deleted successfully']);
}
```

### Step 2.6: Audit `PreviewController` for mortgage writes

- [ ] **Step 2.6.1: Search for mortgage writes in PreviewController**

```bash
grep -n "Mortgage" app/Http/Controllers/Api/PreviewController.php
```

If any direct `Mortgage::create` / `Mortgage::update` / `->mortgages()->create` calls exist, route them through `$this->mortgageStore` with `IngestSource::FORM`. Pattern same as steps 2.3–2.5.

### Step 2.7: Remove MortgageController + PreviewController from the boundary allowlist

- [ ] **Step 2.7.1: Trim allowlist in `tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php`**

Remove these two lines from the `$allowlist` array:

```php
// REMOVE THESE TWO LINES
'app/Http/Controllers/Api/MortgageController.php',
'app/Http/Controllers/Api/PreviewController.php',
```

### Step 2.8: Write the HTTP integration test

**File:** Create `tests/Feature/Stores/MortgageHttpIntegrationTest.php`

- [ ] **Step 2.8.1: Test full CRUD via HTTP**

```php
<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
    Sanctum::actingAs($this->user);
});

it('creates a mortgage via POST', function () {
    $payload = [
        'lender_name' => 'Nationwide',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000.00,
        'interest_rate' => 4.5,
        'rate_type' => 'fixed',
        'monthly_payment' => 1500.00,
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 240,
        'ownership_type' => 'individual',
    ];

    $response = $this->postJson("/api/properties/{$this->property->id}/mortgages", $payload);

    $response->assertCreated();
    $this->assertDatabaseHas('mortgages', [
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'lender_name' => 'Nationwide',
    ]);
});

it('updates a mortgage via PUT', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'outstanding_balance' => 200000]);

    $response = $this->putJson("/api/mortgages/{$mortgage->id}", ['outstanding_balance' => 195000]);

    $response->assertOk();
    expect($mortgage->fresh()->outstanding_balance)->toEqual(195000.00);
});

it('deletes a mortgage via DELETE', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $response = $this->deleteJson("/api/mortgages/{$mortgage->id}");

    $response->assertOk();
    $this->assertSoftDeleted('mortgages', ['id' => $mortgage->id]);
});

it('rejects update from non-owner (joint owner is read-only)', function () {
    $spouse = User::factory()->create();
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'joint_owner_id' => $spouse->id,
        'property_id' => $this->property->id,
        'ownership_type' => 'joint',
    ]);

    Sanctum::actingAs($spouse);
    $response = $this->putJson("/api/mortgages/{$mortgage->id}", ['outstanding_balance' => 999999]);

    $response->assertStatus(404);  // joint owner can't find their way to update
});
```

### Step 2.9: Run the boundary test + targeted Mortgage tests

- [ ] **Step 2.9.1: Verify boundary passes after allowlist trim**

```bash
./vendor/bin/pest tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php tests/Feature/Stores/MortgageHttpIntegrationTest.php
```

Expected: PASS.

### Step 2.10: Commit + open PR 2

```bash
git add app/Http/Controllers/Api/MortgageController.php app/Http/Controllers/Api/PreviewController.php tests/Feature/Stores/MortgageHttpIntegrationTest.php tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php
git commit -m "feat(mortgages): route HTTP form requests through MortgageStore (SP1 Pass 5 PR 2)"
git push -u origin feat/mortgage-store-pr2
gh pr create --base dev --title "feat(mortgages): route HTTP form requests through MortgageStore (SP1 Pass 5 PR 2)" --body "..."
```

---

## 7. PR 3 — Point Fyn AI write tools at MortgageStore

**PR title:** `feat(mortgages): route Fyn AI write tools through MortgageStore (SP1 Pass 5 PR 3)`

**Branch:** `feat/mortgage-store-pr3` off `dev` post-PR-2-merge

**What this PR delivers:**
- `CoordinatingAgent::handleCreateMortgage` routes through `MortgageStore::create`.
- `handleUpdateMortgage` / `handleDeleteMortgage` routes through store (if they exist — audit in Step 3.1).
- `DB::transaction` wraps the store call atomically (already inside store, but caller-side guard for compound operations).
- Boundary allowlist drops `CoordinatingAgent.php`.
- `MortgageFynCaptureIntegrationTest` covers the Fyn write flow end-to-end.

### Step 3.1: Locate the Fyn mortgage handlers

- [ ] **Step 3.1.1: Find handlers in CoordinatingAgent**

```bash
grep -n "handleCreateMortgage\|handleUpdateMortgage\|handleDeleteMortgage\|create_mortgage\|update_mortgage\|delete_mortgage" app/Agents/CoordinatingAgent.php
```

Expected: `handleCreateMortgage` at line 2578 (from §1.1 audit), dispatcher entry at line 914. Audit whether `handleUpdateMortgage` / `handleDeleteMortgage` exist or if Fyn only creates.

### Step 3.2: Route `handleCreateMortgage` through MortgageStore

- [ ] **Step 3.2.1: Refactor the handler**

Replace the body of `handleCreateMortgage` (`app/Agents/CoordinatingAgent.php:2578`):

```php
private function handleCreateMortgage(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return ['success' => false, 'error' => 'Preview users cannot create records'];
    }

    $propertyId = $input['property_id'] ?? null;
    if (! $propertyId) {
        return ['success' => false, 'error' => 'property_id required'];
    }

    $property = app(\App\Services\Stores\PropertyStore::class)->find($propertyId, $user);
    if (! $property) {
        return ['success' => false, 'error' => 'Property not found'];
    }

    $canonical = \App\Services\Stores\Normalisers\MortgageNormaliser::fromFyn($input, $user);

    try {
        $mortgage = app(\App\Services\Stores\MortgageStore::class)->create(
            $canonical,
            $user,
            \App\Services\Stores\IngestSource::FYN_AI
        );

        return [
            'success' => true,
            'mortgage_id' => $mortgage->id,
            'summary' => "Added a {$mortgage->mortgage_type} mortgage with {$mortgage->lender_name} (£" . number_format((float) $mortgage->outstanding_balance, 0) . ' outstanding).',
        ];
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
```

### Step 3.3: Route `handleUpdateMortgage` / `handleDeleteMortgage` if they exist

- [ ] **Step 3.3.1: Apply the same pattern**

If found, follow the same pattern. If not present, skip — Fyn AI currently only creates mortgages and the read path returns existing data.

### Step 3.4: Trim the boundary allowlist

- [ ] **Step 3.4.1: Remove `app/Agents/CoordinatingAgent.php` from MortgageStoreBoundaryTest allowlist**

### Step 3.5: Write the Fyn capture integration test

**File:** Create `tests/Feature/Stores/MortgageFynCaptureIntegrationTest.php`

- [ ] **Step 3.5.1: Test the dispatch path**

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
});

it('creates a mortgage via Fyn AI dispatch path', function () {
    $agent = app(CoordinatingAgent::class);

    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleCreateMortgage');
    $method->setAccessible(true);

    $input = [
        'property_id' => $this->property->id,
        'lender_name' => 'Halifax',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 180000,
        'interest_rate' => 5.25,
        'monthly_payment' => 1100,
        'start_date' => '2022-01-01',
        'maturity_date' => '2047-01-01',
        'remaining_term_months' => 300,
        'ownership_type' => 'individual',
    ];

    $result = $method->invoke($agent, $input, $this->user, false);

    expect($result['success'])->toBeTrue();
    $this->assertDatabaseHas('mortgages', [
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'lender_name' => 'Halifax',
    ]);

    // Verify ingest_source audit context
    $auditCount = \App\Models\AuditLog::where('ingest_source', 'fyn_ai')->count();
    expect($auditCount)->toBeGreaterThan(0);
});

it('rejects preview-user mortgage creates via Fyn', function () {
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('handleCreateMortgage');
    $method->setAccessible(true);

    $result = $method->invoke($agent, ['property_id' => $this->property->id], $this->user, true);

    expect($result['success'])->toBeFalse();
});
```

### Step 3.6: Commit + open PR 3

```bash
git add app/Agents/CoordinatingAgent.php tests/Feature/Stores/MortgageFynCaptureIntegrationTest.php tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php
git commit -m "feat(mortgages): route Fyn AI write tools through MortgageStore (SP1 Pass 5 PR 3)"
git push -u origin feat/mortgage-store-pr3
gh pr create --base dev --title "feat(mortgages): route Fyn AI write tools through MortgageStore (SP1 Pass 5 PR 3)"
```

---

## 8. PR 4 — Point upload + onboarding + seeders at MortgageStore

**PR title:** `feat(mortgages): route upload + onboarding + seeders through MortgageStore (SP1 Pass 5 PR 4)`

**Branch:** `feat/mortgage-store-pr4` off `dev` post-PR-3-merge

**What this PR delivers:**
- `DocumentProcessor` mortgage upload path through `MortgageStore::create` with `IngestSource::UPLOAD`.
- `MortgageMapper` (upload field-mapper) produces canonical payload consumed by `MortgageNormaliser::fromUpload`.
- `OnboardingService` mortgage capture routes through store with `IngestSource::FORM` (onboarding writes are form-equivalent).
- `AssetCaptureEntityExtractor::extractMortgage` (if it exists) routes through store.
- `PreviewUserSeeder` + `ChrisUserSeeder` mortgage creates via `MortgageStore::updateOrCreate` with `IngestSource::SEEDER`.
- `MortgageService::createFromPropertyData` (called by PropertyController::store) routes through store.
- Boundary allowlist drops all 7 sites.
- `MortgageUploadIngestTest` covers the upload path.

### Step 4.1: Route DocumentProcessor's mortgage upload through the store

- [ ] **Step 4.1.1: Find the upload code in DocumentProcessor**

```bash
grep -n "Mortgage::create\|MortgageMapper" app/Services/Documents/DocumentProcessor.php app/Services/Documents/FieldMappers/MortgageMapper.php
```

- [ ] **Step 4.1.2: Refactor to use store**

In `DocumentProcessor.php`, locate the Mortgage-creation branch (within the polymorphic dispatch). Replace the direct `Mortgage::create($mapped)` with:

```php
$canonical = \App\Services\Stores\Normalisers\MortgageNormaliser::fromUpload($mapped, $user);
$mortgage = $this->mortgageStore->create($canonical, $user, \App\Services\Stores\IngestSource::UPLOAD);
```

Add `MortgageStore` to the constructor (`private readonly MortgageStore $mortgageStore`).

### Step 4.2: Route OnboardingService mortgage writes through the store

- [ ] **Step 4.2.1: Find onboarding mortgage writes**

```bash
grep -n "Mortgage::create\|->mortgages()->" app/Services/Onboarding/OnboardingService.php app/Services/Onboarding/AssetCaptureEntityExtractor.php
```

- [ ] **Step 4.2.2: Refactor each site**

Replace each direct write with:

```php
$canonical = \App\Services\Stores\Normalisers\MortgageNormaliser::fromForm($mortgageData, $user);
$mortgage = app(\App\Services\Stores\MortgageStore::class)->create($canonical, $user, \App\Services\Stores\IngestSource::FORM);
```

Add a private readonly `MortgageStore` to the constructor where DI is established. If the service uses `app(...)` resolution (Pass 4 found OnboardingService used this anti-pattern), match the precedent for consistency.

### Step 4.3: Route MortgageService::createFromPropertyData through MortgageStore

This is the critical step that closes the indirect-write loop where PropertyController creates mortgages via MortgageService.

- [ ] **Step 4.3.1: Refactor MortgageService**

In `app/Services/Property/MortgageService.php:26`, refactor `createFromPropertyData`:

```php
public function createFromPropertyData(Property $property, array $validated, User $user): ?Mortgage
{
    // Only create if outstanding_mortgage is provided and > 0
    if (! isset($validated['outstanding_mortgage']) || $validated['outstanding_mortgage'] <= 0) {
        return null;
    }

    // Build the canonical payload from PropertyController form fields
    $payload = array_merge($validated, [
        'property_id' => $property->id,
        'outstanding_balance' => $validated['outstanding_mortgage'],  // map property-form field to canonical
    ]);

    $canonical = \App\Services\Stores\Normalisers\MortgageNormaliser::fromForm($payload, $user);
    return $this->mortgageStore->create($canonical, $user, \App\Services\Stores\IngestSource::FORM);
}
```

Add `private readonly MortgageStore $mortgageStore` to the constructor.

### Step 4.4: Route persona seeders through the store

- [ ] **Step 4.4.1: PreviewUserSeeder**

Find every `Mortgage::create([...])` call in `database/seeders/PreviewUserSeeder.php`. Replace each with:

```php
$canonical = \App\Services\Stores\Normalisers\MortgageNormaliser::fromForm($mortgageData, $user);
$mortgage = app(\App\Services\Stores\MortgageStore::class)->updateOrCreate($canonical, $user, \App\Services\Stores\IngestSource::SEEDER);
```

`updateOrCreate` is used (not `create`) so re-running the seeder is idempotent — matches the Pass 4 ChrisUserSeeder precedent.

- [ ] **Step 4.4.2: ChrisUserSeeder**

Same pattern in `database/seeders/ChrisUserSeeder.php`.

- [ ] **Step 4.4.3: LifecycleTestSeeder (audit-only)**

```bash
grep -n "Mortgage" database/seeders/LifecycleTestSeeder.php
```

If found, apply the same pattern. If not, no change.

### Step 4.5: Write the upload-ingest test

**File:** Create `tests/Feature/Stores/MortgageUploadIngestTest.php`

- [ ] **Step 4.5.1: Test upload path produces canonical row + UPLOAD audit source**

```php
<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\Normalisers\MortgageNormaliser;

it('creates a mortgage via upload-equivalent path with UPLOAD audit context', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);

    $upload = [
        'property_id' => $property->id,
        'lender_name' => 'NatWest',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 220000.00,
        'interest_rate' => 4.75,
        'rate_type' => 'fixed',
        'monthly_payment' => 1350.00,
        'start_date' => '2021-03-01',
        'maturity_date' => '2046-03-01',
        'remaining_term_months' => 270,
        'ownership_type' => 'individual',
    ];

    $canonical = MortgageNormaliser::fromUpload($upload, $user);
    $mortgage = app(MortgageStore::class)->create($canonical, $user, IngestSource::UPLOAD);

    expect($mortgage->lender_name)->toBe('NatWest');

    $auditExists = AuditLog::where('ingest_source', 'upload')
        ->where('auditable_type', 'App\\Models\\Mortgage')
        ->where('auditable_id', $mortgage->id)
        ->exists();
    expect($auditExists)->toBeTrue();
});
```

### Step 4.6: Trim boundary allowlist

- [ ] **Step 4.6.1: Remove 7 entries**

In `MortgageStoreBoundaryTest`, remove from `$allowlist`:
- `app/Services/Property/MortgageService.php`
- `app/Services/Documents/DocumentProcessor.php`
- `app/Services/Onboarding/OnboardingService.php`
- `app/Services/Onboarding/AssetCaptureEntityExtractor.php`
- `database/seeders/PreviewUserSeeder.php`
- `database/seeders/ChrisUserSeeder.php`
- `database/seeders/LifecycleTestSeeder.php` (if no longer needed)

Remaining allowlist after PR 4: just `EncryptExistingData.php` + `ResetPreviewData.php` (PR 8 documents these as legitimate exceptions).

### Step 4.7: Commit + open PR 4

```bash
git add app/Services/Property/MortgageService.php app/Services/Documents/DocumentProcessor.php app/Services/Onboarding/OnboardingService.php app/Services/Onboarding/AssetCaptureEntityExtractor.php database/seeders/ tests/Feature/Stores/MortgageUploadIngestTest.php tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php
git commit -m "feat(mortgages): route upload + onboarding + seeders through MortgageStore (SP1 Pass 5 PR 4)"
git push -u origin feat/mortgage-store-pr4
gh pr create --base dev --title "feat(mortgages): route upload + onboarding + seeders through MortgageStore (SP1 Pass 5 PR 4)"
```

---

## 9. PR 5 — Point read consumers at MortgageStore

**Sub-clustered into 5a-5e per Pass 4 precedent.** ~24 read-consumer service files.

### 9.1 PR 5a — Estate + IHT reads + MortgageReadConsumerParityTest

**Files:**
- `app/Services/Estate/EstateAssetAggregatorService.php` (the canonical joint-aware site at :225, :239)
- `app/Services/Estate/EstateActionDefinitionService.php`
- `app/Services/Estate/EstateDataReadinessService.php`
- `app/Services/Estate/IHTFormattingService.php`
- `app/Services/Estate/LetterEstateValidationService.php`
- `app/Services/Estate/ComprehensiveEstatePlanService.php`
- `app/Agents/EstateAgent.php`

Create `tests/Feature/Stores/MortgageReadConsumerParityTest.php` (7 cases) **first** to lock the joint-aware contract — primary-only vs joint-aware patterns. This test is the contract for 5b-5e.

- [ ] **Step 5a.1: Write MortgageReadConsumerParityTest**

```php
<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\MortgageStore;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->spouse = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
    $this->store = app(MortgageStore::class);
});

it('forUser returns user_id OR joint_owner_id matches', function () {
    // Primary-owner mortgage
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    // Joint-owner mortgage (user is joint_owner, not primary)
    $jointProperty = Property::factory()->create(['user_id' => $this->spouse->id]);
    Mortgage::factory()->create([
        'user_id' => $this->spouse->id,
        'joint_owner_id' => $this->user->id,
        'property_id' => $jointProperty->id,
        'ownership_type' => 'joint',
    ]);

    expect($this->store->forUser($this->user)->count())->toBe(2);
});

it('forUserPrimaryOnly returns only user_id matches', function () {
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    $jointProperty = Property::factory()->create(['user_id' => $this->spouse->id]);
    Mortgage::factory()->create([
        'user_id' => $this->spouse->id,
        'joint_owner_id' => $this->user->id,
        'property_id' => $jointProperty->id,
        'ownership_type' => 'joint',
    ]);

    expect($this->store->forUserPrimaryOnly($this->user)->count())->toBe(1);
});

it('forProperty returns mortgages scoped to property', function () {
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    $otherProp = Property::factory()->create(['user_id' => $this->user->id]);
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $otherProp->id]);

    expect($this->store->forProperty($this->property->id, $this->user)->count())->toBe(2);
});

it('forUserByProperty groups by property_id', function () {
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    $otherProp = Property::factory()->create(['user_id' => $this->user->id]);
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $otherProp->id]);

    $grouped = $this->store->forUserByProperty($this->user);
    expect($grouped)->toHaveCount(2);
    expect($grouped[$this->property->id])->toHaveCount(1);
});

it('find returns ownership-scoped record or null', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    expect($this->store->find($mortgage->id, $this->user))->not->toBeNull();
    expect($this->store->find($mortgage->id, $this->spouse))->toBeNull();
});

it('forUserWithJointOwner eager-loads jointOwner relation', function () {
    Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'joint_owner_id' => $this->spouse->id,
        'property_id' => $this->property->id,
        'ownership_type' => 'joint',
    ]);

    $mortgages = $this->store->forUserWithJointOwner($this->user);
    expect($mortgages->first()->relationLoaded('jointOwner'))->toBeTrue();
});

it('forUserPrimaryOnly chained with sum yields primary-owner-only balance', function () {
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'outstanding_balance' => 100000]);
    $jointProperty = Property::factory()->create(['user_id' => $this->spouse->id]);
    Mortgage::factory()->create([
        'user_id' => $this->spouse->id,
        'joint_owner_id' => $this->user->id,
        'property_id' => $jointProperty->id,
        'ownership_type' => 'joint',
        'outstanding_balance' => 200000,
    ]);

    expect($this->store->forUserPrimaryOnly($this->user)->sum('outstanding_balance'))->toEqual(100000.0);
    expect($this->store->forUser($this->user)->sum('outstanding_balance'))->toEqual(300000.0);
});
```

- [ ] **Step 5a.2: Refactor Estate-side read consumers**

For each file in the 5a cluster, find the `Mortgage::*` patterns and replace per this rubric:

| Original pattern | Replacement |
|---|---|
| `Mortgage::forUserOrJoint($userId)->...` | `$mortgageStore->forUser($user)->...` |
| `Mortgage::where('user_id', $userId)->...` | `$mortgageStore->forUserPrimaryOnly($user)->...` OR `$mortgageStore->forUser($user)->where('user_id', $user->id)->...` |
| `Mortgage::where('property_id', $propertyId)->...` | `$mortgageStore->forProperty($propertyId, $user)->...` |
| `Mortgage::where('id', $id)->first()` | `$mortgageStore->find($id, $user)` |
| `$property->mortgages()->get()` | `$mortgageStore->forProperty($property->id, $user)` (or keep relation if Property is just being eager-loaded for display) |

Each service gains `private readonly MortgageStore $mortgageStore` constructor injection (NOT `app(...)` — per `app/Services/CLAUDE.md` convention).

**Per Pass 4 5a lesson:** for sites that originally used `Mortgage::where('user_id', $userId)` (primary-only semantics), MUST use `forUserPrimaryOnly` — silently broadening to joint-aware (just calling `forUser`) is the documented regression class. Locked by `MortgageReadConsumerParityTest`.

- [ ] **Step 5a.3: Commit 5a**

```bash
git add app/Services/Estate/ app/Agents/EstateAgent.php tests/Feature/Stores/MortgageReadConsumerParityTest.php
git commit -m "feat(mortgages): Estate/IHT reads through MortgageStore + parity test (SP1 Pass 5 PR 5a)"
```

### 9.2 PR 5b — NetWorth + Mobile + CrossModule reads

**Files:**
- `app/Services/NetWorth/NetWorthService.php`
- `app/Services/Mobile/MobileDashboardAggregator.php`
- `app/Services/Shared/CrossModuleAssetAggregator.php`

Apply the same rubric from 5a. NetWorthService likely uses joint-aware; MobileDashboardAggregator may need a `sumMortgageJointOwnerShares` helper mirroring the savings/property sibling pattern from Pass 4.

- [ ] **Step 5b.1: Refactor + commit**

```bash
git add app/Services/NetWorth/ app/Services/Mobile/ app/Services/Shared/
git commit -m "feat(mortgages): NetWorth/Mobile/CrossModule reads through MortgageStore (SP1 Pass 5 PR 5b)"
```

### 9.3 PR 5c — Coordination + AI + UserProfile reads

**Files:**
- `app/Services/Coordination/HouseholdPlanningService.php`
- `app/Services/AI/AdvicePromptBuilder.php`
- `app/Services/AI/DuplicateAcknowledgement.php`
- `app/Services/UserProfile/LetterToSpouseService.php`
- `app/Services/UserProfile/PersonalAccountsService.php`
- `app/Services/UserProfile/UserProfileService.php`

- [ ] **Step 5c.1: Refactor + commit**

```bash
git add app/Services/Coordination/ app/Services/AI/ app/Services/UserProfile/
git commit -m "feat(mortgages): Coordination/AI/UserProfile reads through MortgageStore (SP1 Pass 5 PR 5c)"
```

### 9.4 PR 5d — Goals + Plans + Investment reads

**Files:**
- `app/Services/Goals/GoalsProjectionService.php`
- `app/Services/Goals/LifeEventService.php`
- `app/Services/Plans/SavingsPlanService.php`
- `app/Services/Plans/InvestmentPlanService.php`
- `app/Services/Investment/Recommendation/UserContextBuilder.php`
- `app/Services/Investment/AssetLocation/TaxDragCalculator.php`
- `app/Agents/GoalsAgent.php`
- `app/Agents/RetirementAgent.php`
- `app/Agents/SavingsAgent.php`
- `app/Agents/ProtectionAgent.php`

- [ ] **Step 5d.1: Refactor + commit**

```bash
git add app/Services/Goals/ app/Services/Plans/ app/Services/Investment/ app/Agents/
git commit -m "feat(mortgages): Goals/Plans/Investment reads through MortgageStore (SP1 Pass 5 PR 5d)"
```

### 9.5 PR 5e — Property-internal + DataExport + Protection reads

**Files:**
- `app/Services/Property/PropertyService.php`
- `app/Services/Property/PropertyCalculationService.php`
- `app/Services/Property/MortgageService.php` (its OWN calc helpers — `generateAmortisationSchedule` etc — switch to `MortgageStore::find` for the Mortgage lookup)
- `app/Services/GDPR/DataExportService.php`
- `app/Services/Protection/ProtectionDataReadinessService.php`
- `app/Console/Commands/SendMortgageRateAlerts.php` (audit; refactor if direct reads)

**Important:** Property-internal services are TIGHTLY coupled to `$property->mortgages()` HasMany relation. Two valid patterns:
- **Pattern A** (preferred): keep the HasMany relation as-is for Property model API stability, but the SERVICE calls `$mortgageStore->forProperty($property->id, $user)` instead of `$property->mortgages()`. This decouples the service from the relation.
- **Pattern B**: use `$property->mortgages` (already-loaded relation) when the property was eager-loaded by the caller. Documented in `MortgageStore.md` quirk #2.

- [ ] **Step 5e.1: Refactor + commit + close PR 5**

```bash
git add app/Services/Property/ app/Services/GDPR/ app/Services/Protection/ app/Console/Commands/SendMortgageRateAlerts.php
git commit -m "feat(mortgages): Property-internal + DataExport + Protection reads through MortgageStore (SP1 Pass 5 PR 5e)"
git push -u origin feat/mortgage-store-pr5
gh pr create --base dev --title "feat(mortgages): route read consumers through MortgageStore (SP1 Pass 5 PR 5)"
```

Either bundle 5a-5e as one PR with 5 commits, OR open 5 separate PRs per Pass 4 precedent — CSJ's call at dispatch time.

---

## 10. PR 6 — Canonical derived columns + snapshot table + cross-store recalc

**PR title:** `feat(mortgages): canonical derived columns + snapshots + property reconciliation (SP1 Pass 5 PR 6)`

**Branch:** `feat/mortgage-store-pr6` off `dev` post-PR-5-merge

**This is the architecturally-significant PR of Pass 5.** Three deliverables:
1. Mortgage-side derived columns + snapshots.
2. Property-side `outstanding_mortgage` reconciliation via cross-store recalc listener.
3. Backfill commands for both.

### Step 6.1: Add derived columns to mortgages table

**File:** Create `database/migrations/2026_05_28_100000_add_derived_columns_to_mortgages.php`

- [ ] **Step 6.1.1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortgages', function (Blueprint $table) {
            $table->decimal('outstanding_balance_gbp', 15, 2)->nullable()->after('outstanding_balance');
            $table->decimal('monthly_payment_gbp', 15, 2)->nullable()->after('monthly_payment');
            $table->decimal('current_ltv_pct', 8, 4)->nullable()->after('monthly_payment_gbp');
            $table->timestamp('outstanding_balance_gbp_calculated_at')->nullable();
            $table->timestamp('monthly_payment_gbp_calculated_at')->nullable();
            $table->timestamp('current_ltv_pct_calculated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mortgages', function (Blueprint $table) {
            $table->dropColumn([
                'outstanding_balance_gbp',
                'monthly_payment_gbp',
                'current_ltv_pct',
                'outstanding_balance_gbp_calculated_at',
                'monthly_payment_gbp_calculated_at',
                'current_ltv_pct_calculated_at',
            ]);
        });
    }
};
```

### Step 6.2: Create MortgageValueSnapshot table

**File:** Create `database/migrations/2026_05_28_100001_create_mortgage_value_snapshots_table.php`

- [ ] **Step 6.2.1: Write the migration (mirror PropertyValueSnapshot from Pass 4)**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mortgage_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mortgage_id')->constrained('mortgages')->cascadeOnDelete();
            $table->string('snapshot_type', 32);  // 'mortgageBalance' or 'mortgageRate'
            $table->decimal('value', 15, 4);       // store balance or rate per snapshot_type
            $table->timestamp('snapshotted_at');
            $table->timestamps();
            $table->index(['mortgage_id', 'snapshot_type', 'snapshotted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mortgage_value_snapshots');
    }
};
```

### Step 6.3: Add `outstanding_mortgage_calculated_at` to properties

**File:** Create `database/migrations/2026_05_28_100002_add_outstanding_mortgage_calculated_at_to_properties.php`

- [ ] **Step 6.3.1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->timestamp('outstanding_mortgage_calculated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('outstanding_mortgage_calculated_at');
        });
    }
};
```

### Step 6.4: Add MortgageValueSnapshot model

**File:** Create `app/Models/MortgageValueSnapshot.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MortgageValueSnapshot extends Model
{
    use HasFactory;

    protected $fillable = ['mortgage_id', 'snapshot_type', 'value', 'snapshotted_at'];

    protected $casts = [
        'value' => 'decimal:4',
        'snapshotted_at' => 'datetime',
    ];

    public function mortgage(): BelongsTo
    {
        return $this->belongsTo(Mortgage::class);
    }
}
```

### Step 6.5: Add snapshot policies

**File:** Modify `app/Services/Stores/Snapshots/SnapshotPolicies.php`

- [ ] **Step 6.5.1: Add `mortgageBalance` + `mortgageRate` policies**

Add two new entries to the policies map (mirror Pass 4 propertyValue/propertyEquity pattern):

```php
'mortgageBalance' => new SnapshotPolicy(
    'mortgage_value_snapshots',
    'mortgageBalance',
    triggers: fn (?float $old, ?float $new) => ($old === null) || ($new === null) || abs(($new ?? 0) - ($old ?? 0)) >= 1000.0 || ((($old ?? 0) > 0) && (abs(($new ?? 0) - ($old ?? 0)) / max(abs($old ?? 1), 1)) >= 0.005),
    retentionDays: 2555,
),
'mortgageRate' => new SnapshotPolicy(
    'mortgage_value_snapshots',
    'mortgageRate',
    // Rate-change policy: snapshot on any rate change >= 0.25%
    triggers: fn (?float $old, ?float $new) => ($old === null) || ($new === null) || abs(($new ?? 0) - ($old ?? 0)) >= 0.25,
    retentionDays: 2555,
),
```

### Step 6.6: Create MortgageDerivedColumnCalculator

**File:** Create `app/Services/Stores/Recalc/MortgageDerivedColumnCalculator.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Recalc;

use App\Models\Mortgage;

/**
 * Recomputes canonical derived columns on a Mortgage row:
 *  - outstanding_balance_gbp: outstanding_balance × FX-rate (GBP-only today, so === outstanding_balance)
 *  - monthly_payment_gbp: monthly_payment × FX-rate (GBP-only today, so === monthly_payment)
 *  - current_ltv_pct: (outstanding_balance / property.current_value) × 100
 *
 * Called from MortgageStore::create + ::update + via cross-store recalc.
 */
final class MortgageDerivedColumnCalculator
{
    public function recalculate(Mortgage $mortgage): array
    {
        $changes = [];

        // outstanding_balance_gbp: GBP-only today (mortgages are GBP-denominated by canonical contract)
        $newBalanceGbp = (float) ($mortgage->outstanding_balance ?? 0);
        if ((float) ($mortgage->outstanding_balance_gbp ?? 0) !== $newBalanceGbp) {
            $changes['outstanding_balance_gbp'] = $newBalanceGbp;
            $changes['outstanding_balance_gbp_calculated_at'] = now();
        }

        // monthly_payment_gbp: same — GBP-only
        $newPaymentGbp = (float) ($mortgage->monthly_payment ?? 0);
        if ((float) ($mortgage->monthly_payment_gbp ?? 0) !== $newPaymentGbp) {
            $changes['monthly_payment_gbp'] = $newPaymentGbp;
            $changes['monthly_payment_gbp_calculated_at'] = now();
        }

        // current_ltv_pct: requires the Property row
        $mortgage->loadMissing('property');
        if ($mortgage->property && (float) $mortgage->property->current_value > 0) {
            $newLtv = round(($newBalanceGbp / (float) $mortgage->property->current_value) * 100, 4);
            if ((float) ($mortgage->current_ltv_pct ?? -1) !== $newLtv) {
                $changes['current_ltv_pct'] = $newLtv;
                $changes['current_ltv_pct_calculated_at'] = now();
            }
        }

        if (! empty($changes)) {
            $mortgage->forceFill($changes)->saveQuietly();
        }

        return $changes;
    }
}
```

### Step 6.7: Hook calculator into MortgageStore

- [ ] **Step 6.7.1: Inject calculator + snapshot policies into MortgageStore constructor**

```php
// app/Services/Stores/MortgageStore.php — extend constructor
public function __construct(
    private readonly TierGate $tierGate,
    private readonly \App\Services\Stores\Recalc\MortgageDerivedColumnCalculator $calculator,
    private readonly \App\Services\Stores\Snapshots\SnapshotPolicies $snapshotPolicies,
) {}
```

- [ ] **Step 6.7.2: Call `recalculateDerived` from create + update**

Add a private `recalculateDerived(Mortgage $mortgage, ?Mortgage $previous): void` that:
1. Calls `$this->calculator->recalculate($mortgage)`.
2. Checks snapshot policies and inserts MortgageValueSnapshot rows where triggered.

Call it from `create()` (just before returning) and `update()` (just before returning the fresh model). Pattern mirrors Pass 4 PropertyStore.

### Step 6.8: Create the cross-store recalc listener

**File:** Create `app/Listeners/Mortgage/RecalculatePropertyOutstandingMortgage.php`

```php
<?php

declare(strict_types=1);

namespace App\Listeners\Mortgage;

use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageRestored;
use App\Events\Mortgage\MortgageUpdated;
use App\Services\Stores\PropertyStore;

/**
 * Cross-store recalc: when any Mortgage event fires for a mortgage linked
 * to a property, recompute properties.outstanding_mortgage from the canonical
 * mortgages.outstanding_balance sum.
 *
 * One-way recalc: Mortgage → Property. PropertyStore writes do NOT trigger
 * MortgageStore writes. Loop prevention by design.
 */
final class RecalculatePropertyOutstandingMortgage
{
    public function __construct(
        private readonly PropertyStore $propertyStore,
    ) {}

    public function handle(MortgageCreated|MortgageUpdated|MortgageDeleted|MortgageRestored $event): void
    {
        if ($event->mortgage->property_id === null) {
            return;
        }
        $this->propertyStore->recalculateDerivedForPropertyId($event->mortgage->property_id);
    }
}
```

### Step 6.9: Expose `recalculateDerivedForPropertyId` on PropertyStore

**File:** Modify `app/Services/Stores/PropertyStore.php`

- [ ] **Step 6.9.1: Add public method**

```php
/**
 * Public recalc entry point — called by cross-store recalc listeners
 * (e.g. RecalculatePropertyOutstandingMortgage). System-context: no
 * user scoping required because recalc is a back-end operation.
 */
public function recalculateDerivedForPropertyId(int $propertyId): void
{
    $property = \App\Models\Property::find($propertyId);
    if ($property === null) {
        return;
    }
    $this->recalculateDerived($property);  // existing private method from Pass 4 PR 6
}
```

### Step 6.10: Update PropertyDerivedColumnCalculator to use canonical mortgages sum

**File:** Modify `app/Services/Stores/Recalc/PropertyDerivedColumnCalculator.php`

- [ ] **Step 6.10.1: Switch from denormalised read to MortgageStore::forProperty**

Pre-Pass-5 (lines :30, :42-44):
```php
$mortgage = $property->outstanding_mortgage !== null ? (float) $property->outstanding_mortgage : 0.0;
```

Post-Pass-5:
```php
$canonicalMortgageSum = app(\App\Services\Stores\MortgageStore::class)
    ->forProperty($property->id, null)
    ->sum('outstanding_balance');
$mortgage = (float) $canonicalMortgageSum;

// Also update the denormalised cache on Property for backward compatibility
$changes['outstanding_mortgage'] = $canonicalMortgageSum;
$changes['outstanding_mortgage_calculated_at'] = now();
```

The denormalised `outstanding_mortgage` column on Property is now a **write-only-by-recalc derived column** — read consumers can still use it for performance (pre-Pass-5 callers still work), but the canonical source is the `mortgages` table.

### Step 6.11: Register the listener in EventServiceProvider

**File:** Modify `app/Providers/EventServiceProvider.php`

- [ ] **Step 6.11.1: Wire all 4 events to the listener**

```php
protected $listen = [
    // ... existing listeners ...
    \App\Events\Mortgage\MortgageCreated::class => [
        \App\Listeners\Mortgage\RecalculatePropertyOutstandingMortgage::class,
    ],
    \App\Events\Mortgage\MortgageUpdated::class => [
        \App\Listeners\Mortgage\RecalculatePropertyOutstandingMortgage::class,
    ],
    \App\Events\Mortgage\MortgageDeleted::class => [
        \App\Listeners\Mortgage\RecalculatePropertyOutstandingMortgage::class,
    ],
    \App\Events\Mortgage\MortgageRestored::class => [
        \App\Listeners\Mortgage\RecalculatePropertyOutstandingMortgage::class,
    ],
];
```

### Step 6.12: Update Property + Mortgage models for new columns

**File:** Modify `app/Models/Mortgage.php` — add the 6 derived columns to `$fillable` + `$casts`:

```php
'outstanding_balance_gbp',
'monthly_payment_gbp',
'current_ltv_pct',
'outstanding_balance_gbp_calculated_at',
'monthly_payment_gbp_calculated_at',
'current_ltv_pct_calculated_at',
```

```php
'outstanding_balance_gbp' => 'decimal:2',
'monthly_payment_gbp' => 'decimal:2',
'current_ltv_pct' => 'decimal:4',
'outstanding_balance_gbp_calculated_at' => 'datetime',
'monthly_payment_gbp_calculated_at' => 'datetime',
'current_ltv_pct_calculated_at' => 'datetime',
```

**File:** Modify `app/Models/Property.php` — add `outstanding_mortgage_calculated_at` to fillable + casts.

### Step 6.13: Backfill commands

**File:** Create `app/Console/Commands/BackfillMortgageDerivedColumns.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Mortgage;
use App\Services\Stores\Recalc\MortgageDerivedColumnCalculator;
use Illuminate\Console\Command;

final class BackfillMortgageDerivedColumns extends Command
{
    protected $signature = 'mortgages:backfill-derived-columns {--chunk=200}';

    protected $description = 'Recompute outstanding_balance_gbp / monthly_payment_gbp / current_ltv_pct for all mortgages';

    public function handle(MortgageDerivedColumnCalculator $calculator): int
    {
        $count = 0;
        Mortgage::with('property')->chunkById((int) $this->option('chunk'), function ($mortgages) use ($calculator, &$count) {
            foreach ($mortgages as $mortgage) {
                $calculator->recalculate($mortgage);
                $count++;
            }
            $this->info("Processed {$count}");
        });
        $this->info("Backfilled {$count} mortgages.");
        return Command::SUCCESS;
    }
}
```

**File:** Create `app/Console/Commands/BackfillPropertyOutstandingMortgage.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Stores\PropertyStore;
use Illuminate\Console\Command;

final class BackfillPropertyOutstandingMortgage extends Command
{
    protected $signature = 'properties:backfill-outstanding-mortgage {--chunk=200}';

    protected $description = 'Recompute properties.outstanding_mortgage from canonical mortgages sum';

    public function handle(PropertyStore $propertyStore): int
    {
        $count = 0;
        Property::chunkById((int) $this->option('chunk'), function ($properties) use ($propertyStore, &$count) {
            foreach ($properties as $property) {
                $propertyStore->recalculateDerivedForPropertyId($property->id);
                $count++;
            }
            $this->info("Processed {$count}");
        });
        $this->info("Backfilled {$count} properties.");
        return Command::SUCCESS;
    }
}
```

### Step 6.14: Write tests

- [ ] **Step 6.14.1: Calculator unit test**

**File:** Create `tests/Unit/Services/Stores/Recalc/MortgageDerivedColumnCalculatorTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Recalc\MortgageDerivedColumnCalculator;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id, 'current_value' => 500000]);
    $this->calculator = app(MortgageDerivedColumnCalculator::class);
});

it('recomputes outstanding_balance_gbp + LTV when outstanding_balance changes', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
    ]);

    $changes = $this->calculator->recalculate($mortgage);

    expect($changes)->toHaveKey('outstanding_balance_gbp');
    expect($changes['outstanding_balance_gbp'])->toBe(250000.0);
    expect($changes)->toHaveKey('current_ltv_pct');
    expect($changes['current_ltv_pct'])->toBe(50.0);  // 250k / 500k * 100 = 50%
    expect($mortgage->fresh()->outstanding_balance_gbp_calculated_at)->not->toBeNull();
});

it('handles property.current_value=0 gracefully (LTV is not set)', function () {
    $this->property->update(['current_value' => 0]);
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
    ]);

    $changes = $this->calculator->recalculate($mortgage);

    expect($changes)->not->toHaveKey('current_ltv_pct');
    expect($mortgage->fresh()->current_ltv_pct)->toBeNull();
});

it('skips fields that did not change', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'outstanding_balance' => 250000,
        'outstanding_balance_gbp' => 250000,
        'monthly_payment' => 1500,
        'monthly_payment_gbp' => 1500,
    ]);

    $changes = $this->calculator->recalculate($mortgage);

    expect($changes)->not->toHaveKey('outstanding_balance_gbp');
    expect($changes)->not->toHaveKey('monthly_payment_gbp');
});
```

- [ ] **Step 6.14.2: Listener unit test**

**File:** Create `tests/Unit/Listeners/Mortgage/RecalculatePropertyOutstandingMortgageTest.php`

```php
<?php

declare(strict_types=1);

use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageRestored;
use App\Events\Mortgage\MortgageUpdated;
use App\Listeners\Mortgage\RecalculatePropertyOutstandingMortgage;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\PropertyStore;
use Mockery;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
});

afterEach(function () {
    Mockery::close();
});

it('triggers PropertyStore::recalculateDerivedForPropertyId on MortgageCreated', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $propertyStore = Mockery::mock(PropertyStore::class);
    $propertyStore->shouldReceive('recalculateDerivedForPropertyId')->once()->with($this->property->id);

    $listener = new RecalculatePropertyOutstandingMortgage($propertyStore);
    $listener->handle(new MortgageCreated($mortgage, $this->user->id));
});

it('handles MortgageUpdated', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $propertyStore = Mockery::mock(PropertyStore::class);
    $propertyStore->shouldReceive('recalculateDerivedForPropertyId')->once()->with($this->property->id);

    $listener = new RecalculatePropertyOutstandingMortgage($propertyStore);
    $listener->handle(new MortgageUpdated($mortgage, $this->user->id, ['outstanding_balance' => [100000, 95000]]));
});

it('handles MortgageDeleted', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $propertyStore = Mockery::mock(PropertyStore::class);
    $propertyStore->shouldReceive('recalculateDerivedForPropertyId')->once()->with($this->property->id);

    $listener = new RecalculatePropertyOutstandingMortgage($propertyStore);
    $listener->handle(new MortgageDeleted($mortgage, $this->user->id, false));
});

it('handles MortgageRestored', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $propertyStore = Mockery::mock(PropertyStore::class);
    $propertyStore->shouldReceive('recalculateDerivedForPropertyId')->once()->with($this->property->id);

    $listener = new RecalculatePropertyOutstandingMortgage($propertyStore);
    $listener->handle(new MortgageRestored($mortgage, $this->user->id));
});

it('does nothing when property_id is null (orphan mortgage)', function () {
    $mortgage = Mortgage::factory()->make(['user_id' => $this->user->id, 'property_id' => null]);

    $propertyStore = Mockery::mock(PropertyStore::class);
    $propertyStore->shouldNotReceive('recalculateDerivedForPropertyId');

    $listener = new RecalculatePropertyOutstandingMortgage($propertyStore);
    $listener->handle(new MortgageCreated($mortgage, $this->user->id));
});
```

- [ ] **Step 6.14.3: Cross-store integration test**

```php
// tests/Feature/Stores/MortgagePropertyReconciliationTest.php
it('creates mortgage → property.outstanding_mortgage updates', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 500000, 'outstanding_mortgage' => 0]);

    $mortgage = app(MortgageStore::class)->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Test Bank',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::FORM);

    expect($property->fresh()->outstanding_mortgage)->toEqual(250000.00);
    expect($property->fresh()->outstanding_mortgage_calculated_at)->not->toBeNull();
});

it('updates mortgage → property.outstanding_mortgage updates', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 500000]);
    $mortgage = app(MortgageStore::class)->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Test Bank',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::FORM);

    app(MortgageStore::class)->update($mortgage, ['outstanding_balance' => 200000], $user, IngestSource::FORM);

    expect($property->fresh()->outstanding_mortgage)->toEqual(200000.00);
});

it('deletes mortgage → property.outstanding_mortgage updates', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 500000]);
    $mortgage = app(MortgageStore::class)->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Test Bank',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::FORM);

    app(MortgageStore::class)->delete($mortgage, $user, IngestSource::FORM);

    expect($property->fresh()->outstanding_mortgage)->toEqual(0.00);
});

it('multiple mortgages on one property sum correctly', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 1000000]);

    $base = [
        'property_id' => $property->id,
        'user_id' => $user->id,
        'mortgage_type' => 'repayment',
        'monthly_payment' => 1000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    app(MortgageStore::class)->create(array_merge($base, ['lender_name' => 'Bank A', 'outstanding_balance' => 200000]), $user, IngestSource::FORM);
    app(MortgageStore::class)->create(array_merge($base, ['lender_name' => 'Bank B', 'outstanding_balance' => 150000]), $user, IngestSource::FORM);

    expect($property->fresh()->outstanding_mortgage)->toEqual(350000.00);
});

it('no Mortgage events fire when Property is updated alone (loop prevention)', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 500000]);

    Event::fake([\App\Events\Mortgage\MortgageCreated::class, \App\Events\Mortgage\MortgageUpdated::class]);

    // PropertyStore::recalculateDerivedForPropertyId should NOT cause Mortgage events
    app(PropertyStore::class)->recalculateDerivedForPropertyId($property->id);

    Event::assertNotDispatched(\App\Events\Mortgage\MortgageCreated::class);
    Event::assertNotDispatched(\App\Events\Mortgage\MortgageUpdated::class);
});
```

- [ ] **Step 6.14.4: Backfill test**

**File:** Create `tests/Feature/Stores/MortgageDerivedColumnsBackfillTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

it('backfills derived columns for all existing mortgages', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 500000]);

    // Create mortgage WITHOUT going through MortgageStore (simulates pre-Pass-5 row)
    $mortgage = Mortgage::create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'lender_name' => 'Legacy',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 300000,
        'monthly_payment' => 1800,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    expect($mortgage->outstanding_balance_gbp)->toBeNull();

    Artisan::call('mortgages:backfill-derived-columns');

    $fresh = $mortgage->fresh();
    expect($fresh->outstanding_balance_gbp)->toEqual(300000.00);
    expect($fresh->current_ltv_pct)->toEqual(60.0);  // 300k / 500k * 100
});

it('reconciles property.outstanding_mortgage from canonical mortgages sum', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 500000,
        'outstanding_mortgage' => 9999.99,  // intentionally wrong — pre-Pass-5 drift
    ]);

    Mortgage::create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'lender_name' => 'Legacy',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    Artisan::call('properties:backfill-outstanding-mortgage');

    expect($property->fresh()->outstanding_mortgage)->toEqual(250000.00);
});
```

### Step 6.15: Commit + open PR 6

```bash
git add app/Services/Stores/MortgageStore.php app/Services/Stores/Recalc/ app/Services/Stores/Snapshots/SnapshotPolicies.php app/Services/Stores/PropertyStore.php app/Listeners/Mortgage/ app/Models/Mortgage.php app/Models/Property.php app/Models/MortgageValueSnapshot.php app/Console/Commands/BackfillMortgageDerivedColumns.php app/Console/Commands/BackfillPropertyOutstandingMortgage.php app/Providers/EventServiceProvider.php database/migrations/2026_05_28_10000* tests/Unit/Services/Stores/Recalc/ tests/Unit/Listeners/Mortgage/ tests/Feature/Stores/MortgageDerivedColumnsBackfillTest.php tests/Feature/Stores/MortgagePropertyReconciliationTest.php
git commit -m "feat(mortgages): canonical derived columns + snapshots + Property reconciliation (SP1 Pass 5 PR 6)"
git push -u origin feat/mortgage-store-pr6
gh pr create --base dev --title "feat(mortgages): canonical derived columns + snapshots + Property reconciliation (SP1 Pass 5 PR 6)"
```

**Deploy gate:** PR 6 introduces 3 migrations — csjones MUST run `php artisan migrate --force` on next deploy.

---

## 11. PR 7 — Tier-cap test for mortgage

**PR title:** `feat(mortgages): tier-cap enforcement test (SP1 Pass 5 PR 7)`

**Branch:** `feat/mortgage-store-pr7` off `dev` post-PR-6-merge

### Step 7.1: Write MortgageTierCapTest

**File:** Create `tests/Feature/Stores/MortgageTierCapTest.php`

- [ ] **Step 7.1.1: 5 test cases (mirror PropertyTierCapTest precedent)**

```php
<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\TierConfigurationStore;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
    $this->store = app(MortgageStore::class);
});

it('allows mortgage create within free-tier cap (cap=10)', function () {
    for ($i = 1; $i <= 9; $i++) {
        \App\Models\Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    }
    // Create 10th — at the cap, still allowed
    expect(fn () => $this->store->create($this->minimalCanonical(), $this->user, IngestSource::FORM))
        ->not->toThrow(\Throwable::class);
});

it('rejects mortgage create when free-tier cap exceeded', function () {
    for ($i = 1; $i <= 10; $i++) {
        \App\Models\Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    }
    expect(fn () => $this->store->create($this->minimalCanonical(), $this->user, IngestSource::FORM))
        ->toThrow(\App\Services\Stores\TierCapExceededException::class);
});

it('allows unlimited mortgages for tier1 users', function () {
    $this->user->subscriptions()->create(['plan_id' => Subscription::TIER_1, 'status' => 'active', 'started_at' => now()]);
    for ($i = 1; $i <= 25; $i++) {
        \App\Models\Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    }
    expect(fn () => $this->store->create($this->minimalCanonical(), $this->user, IngestSource::FORM))
        ->not->toThrow(\Throwable::class);
});

it('tier-cap counts joint mortgages on user_id only (joint owner does not count toward cap)', function () {
    $primaryUser = User::factory()->create();
    for ($i = 1; $i <= 10; $i++) {
        $prop = Property::factory()->create(['user_id' => $primaryUser->id]);
        \App\Models\Mortgage::factory()->create([
            'user_id' => $primaryUser->id,
            'joint_owner_id' => $this->user->id,  // this->user is joint owner, not primary
            'property_id' => $prop->id,
            'ownership_type' => 'joint',
        ]);
    }
    // this->user has 0 primary-owned mortgages — should be allowed to create
    expect(fn () => $this->store->create($this->minimalCanonical(), $this->user, IngestSource::FORM))
        ->not->toThrow(\Throwable::class);
});

it('tier-cap respects tier-configuration override (TierConfigurationStore::set())', function () {
    app(TierConfigurationStore::class)->setCap('mortgage', 'free', 2);
    \App\Models\Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    \App\Models\Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    expect(fn () => $this->store->create($this->minimalCanonical(), $this->user, IngestSource::FORM))
        ->toThrow(\App\Services\Stores\TierCapExceededException::class);
});

function minimalCanonical(): array
{
    return [
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'lender_name' => 'Test',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 100000,
        'monthly_payment' => 600,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];
}
```

### Step 7.2: Commit + open PR 7

```bash
git add tests/Feature/Stores/MortgageTierCapTest.php
git commit -m "feat(mortgages): tier-cap enforcement test (SP1 Pass 5 PR 7)"
git push -u origin feat/mortgage-store-pr7
gh pr create --base dev --title "feat(mortgages): tier-cap enforcement test (SP1 Pass 5 PR 7)"
```

---

## 12. PR 8 — Lock-down + parity test + audit + MortgageStore.md (SP1 §16 close-out IN-LINE)

**PR title:** `lock-down(mortgages): allowlist LOCKED + audit ingest_source + parity + Store.md (SP1 Pass 5 PR 8)`

**Branch:** `feat/mortgage-store-pr8` off `dev` post-PR-7-merge

**Deliverables:**
- Boundary test allowlist **LOCKED** — final set is `EncryptExistingData.php` + `ResetPreviewData.php` only (both are pre-existing migration/admin commands, documented in Store.md).
- `MortgageAuditIngestSourceTest` — 5 cases verifying each `IngestSource` value flows through to the audit log.
- `MortgageThreeIngestParityTest` — 2 cases: form vs Fyn vs upload all produce byte-identical canonical rows (excluding `id`, `timestamps`, `ingest_source`). Includes `tenants_in_common` coercion case (form sends TIC, store coerces to joint, all three paths produce `ownership_type=joint`).
- `MortgageStore.md` — full per-entity docs (target ~200 lines, mirror PropertyStore.md structure).
- §16.1 acceptance gates 1-7 closed inline; gate 8 (Playwright browser-smoke) opens for csjones re-deploy after merge.

### Step 8.1: Rewrite the boundary test in LOCKED framing

- [ ] **Step 8.1.1: Update the test docblock**

Rewrite the docblock + allowlist comments to LOCKED framing — no "PR N will trim" language. Final state:

```php
/**
 * Boundary architecture test for MortgageStore — LOCKED (SP1 Pass 5 PR 8).
 *
 * Every mutation of App\Models\Mortgage MUST go through App\Services\Stores\MortgageStore.
 * The allowlist below documents the only legitimate exceptions, each with a justification.
 */

$allowlist = [
    // Pre-existing data-migration command — operates on encrypted_data column directly,
    // not a logical mortgage mutation. Documented in MortgageStore.md quirk #10.
    'app/Console/Commands/EncryptExistingData.php',
    // Admin preview-reset — operates on the soft-deletes layer to bulk-reset preview-user state.
    // Not a user-facing write path. Documented in MortgageStore.md quirk #11.
    'app/Console/Commands/ResetPreviewData.php',
];
```

### Step 8.2: Write MortgageAuditIngestSourceTest

**File:** Create `tests/Feature/Stores/MortgageAuditIngestSourceTest.php`

- [ ] **Step 8.2.1: 5 cases — one per IngestSource value**

```php
<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
    $this->store = app(MortgageStore::class);
});

it('records FORM ingest_source on form-driven creates', function () {
    $mortgage = $this->store->create($this->canonical(), $this->user, IngestSource::FORM);
    $audit = AuditLog::where('auditable_id', $mortgage->id)->where('auditable_type', Mortgage::class)->first();
    expect($audit->ingest_source)->toBe('form');
});

it('records FYN_AI ingest_source on Fyn-driven creates', function () {
    $mortgage = $this->store->create($this->canonical(), $this->user, IngestSource::FYN_AI);
    $audit = AuditLog::where('auditable_id', $mortgage->id)->first();
    expect($audit->ingest_source)->toBe('fyn_ai');
});

it('records UPLOAD ingest_source on document-upload creates', function () {
    $mortgage = $this->store->create($this->canonical(), $this->user, IngestSource::UPLOAD);
    $audit = AuditLog::where('auditable_id', $mortgage->id)->first();
    expect($audit->ingest_source)->toBe('upload');
});

it('records SEEDER ingest_source on seeder-driven creates', function () {
    $mortgage = $this->store->create($this->canonical(), $this->user, IngestSource::SEEDER);
    $audit = AuditLog::where('auditable_id', $mortgage->id)->first();
    expect($audit->ingest_source)->toBe('seeder');
});

it('records ADMIN ingest_source on admin-driven creates', function () {
    $mortgage = $this->store->create($this->canonical(), $this->user, IngestSource::ADMIN);
    $audit = AuditLog::where('auditable_id', $mortgage->id)->first();
    expect($audit->ingest_source)->toBe('admin');
});

function canonical(): array
{
    return [
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'lender_name' => 'Test',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 100000,
        'monthly_payment' => 600,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];
}
```

### Step 8.3: Write MortgageThreeIngestParityTest

**File:** Create `tests/Feature/Stores/MortgageThreeIngestParityTest.php`

- [ ] **Step 8.3.1: 2 cases — vanilla parity + tenants_in_common coercion**

```php
<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\Normalisers\MortgageNormaliser;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
    $this->store = app(MortgageStore::class);
});

it('produces byte-identical canonical mortgage rows from form / fyn / upload paths', function () {
    $shared = [
        'property_id' => $this->property->id,
        'lender_name' => 'Halifax',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000.00,
        'interest_rate' => 4.5,
        'rate_type' => 'fixed',
        'monthly_payment' => 1500.00,
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 240,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $fromForm = $this->store->create(MortgageNormaliser::fromForm($shared, $this->user), $this->user, IngestSource::FORM);
    $fromFyn = $this->store->create(MortgageNormaliser::fromFyn($shared, $this->user), $this->user, IngestSource::FYN_AI);
    $fromUpload = $this->store->create(MortgageNormaliser::fromUpload($shared, $this->user), $this->user, IngestSource::UPLOAD);

    $compareKeys = ['user_id', 'property_id', 'lender_name', 'mortgage_type', 'outstanding_balance', 'interest_rate', 'rate_type', 'monthly_payment', 'ownership_type', 'ownership_percentage'];
    foreach ($compareKeys as $key) {
        expect($fromForm->{$key})->toEqual($fromFyn->{$key}, "Form vs Fyn diverge on {$key}");
        expect($fromForm->{$key})->toEqual($fromUpload->{$key}, "Form vs Upload diverge on {$key}");
    }
});

it('coerces tenants_in_common to joint identically across all three ingest paths', function () {
    $shared = [
        'property_id' => $this->property->id,
        'lender_name' => 'Santander',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 300000,
        'interest_rate' => 4.0,
        'monthly_payment' => 1800,
        'start_date' => '2021-01-01',
        'maturity_date' => '2046-01-01',
        'remaining_term_months' => 300,
        'ownership_type' => 'tenants_in_common',  // not valid for mortgages — coerced to 'joint'
    ];

    $fromForm = $this->store->create(MortgageNormaliser::fromForm($shared, $this->user), $this->user, IngestSource::FORM);
    $fromFyn = $this->store->create(MortgageNormaliser::fromFyn($shared, $this->user), $this->user, IngestSource::FYN_AI);
    $fromUpload = $this->store->create(MortgageNormaliser::fromUpload($shared, $this->user), $this->user, IngestSource::UPLOAD);

    expect($fromForm->ownership_type)->toBe('joint');
    expect($fromFyn->ownership_type)->toBe('joint');
    expect($fromUpload->ownership_type)->toBe('joint');
});
```

### Step 8.4: Write MortgageStore.md

**File:** Create `app/Services/Stores/MortgageStore.md`

- [ ] **Step 8.4.1: Mirror PropertyStore.md structure**

Target ~200 lines. Sections (matching Pass 4 PropertyStore.md):
1. Overview — what MortgageStore is, when to use it.
2. Public API — full method signatures with brief descriptions for all 12 methods (7 read + 5 write).
3. Joint-aware contract — explicit list of joint-aware vs primary-only methods.
4. What the store does NOT expose — direct relation traversal, raw queries, etc.
5. Derived columns — outstanding_balance_gbp / monthly_payment_gbp / current_ltv_pct + recalc lifecycle.
6. Snapshot policies — mortgageBalance + mortgageRate with thresholds + retention.
7. Tier-cap — mortgage key with cap defaults.
8. Cross-store recalc contract — Mortgage → Property reconciliation, one-way, loop prevention.
9. IngestSource conventions — when to use FORM vs FYN_AI vs UPLOAD vs SEEDER vs ADMIN.
10. Quirks — 11 entries:
    1. tenants_in_common NOT supported as ownership_type
    2. PropertyService.mortgages relation is allowed for already-eager-loaded reads (Pattern B from PR 5e)
    3. `properties.outstanding_mortgage` is a derived column from PR 6 onwards — write-only-by-recalc
    4. updateOrCreate keys on (user_id, property_id, lender_name)
    5. Joint owners are READ-ONLY — mutations require user_id === mortgage.user_id
    6. forUser is joint-aware (returns user_id OR joint_owner_id matches) — for primary-only semantics, use forUserPrimaryOnly
    7. ownership_percentage defaults to 100.00 for individual, 50.00 for joint
    8. mortgage_account_number is encrypted via Mortgage model accessor (no store-side handling required)
    9. Cross-store recalc fires synchronously — large bulk-mortgage operations trigger N Property recalcs
    10. EncryptExistingData.php is in the boundary allowlist — pre-existing data-migration command
    11. ResetPreviewData.php is in the boundary allowlist — admin preview-reset operation

### Step 8.5: Run the full suite

- [ ] **Step 8.5.1: Verify everything passes**

```bash
./vendor/bin/pest tests/Unit/Services/Stores/ tests/Feature/Stores/ tests/Architecture/StoreBoundary/
```

Expected: all PASS.

### Step 8.6: Commit + open PR 8

```bash
git add tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php tests/Feature/Stores/MortgageAuditIngestSourceTest.php tests/Feature/Stores/MortgageThreeIngestParityTest.php app/Services/Stores/MortgageStore.md
git commit -m "lock-down(mortgages): allowlist LOCKED + audit ingest_source + parity + Store.md (SP1 Pass 5 PR 8)"
git push -u origin feat/mortgage-store-pr8
gh pr create --base dev --title "lock-down(mortgages): allowlist LOCKED + audit ingest_source + parity + Store.md (SP1 Pass 5 PR 8)"
```

---

## 13. Acceptance criteria mapping (spec §16)

Mapping Pass 5 close-out to spec §16.1 per-entity gates:

| §16.1 gate | Closed by |
|---|---|
| 1. Single write path (Pest boundary) | PR 8 — `MortgageStoreBoundaryTest` LOCKED |
| 2. Three-ingest parity | PR 8 — `MortgageThreeIngestParityTest` (2 cases incl. `tenants_in_common` coercion) |
| 3. Audit completeness — ingest_source | PR 1 (`AuditLog::withContext` wraps) + PR 8 — `MortgageAuditIngestSourceTest` (5 cases) |
| 4. Derived-column correctness | PR 6 — `MortgageDerivedColumnCalculator` + tests |
| 5. Snapshot policy applied | PR 6 — `mortgageBalance` + `mortgageRate` policies + tests |
| 6. Currency round-trip | n/a — Pass 5 GBP-only (mortgages are GBP-denominated; deferred to a future sub-project pass) |
| 7. Tier-cap enforcement | PR 1 (seam) + PR 7 — `MortgageTierCapTest` (5 cases) |
| 8. Browser-tested via Playwright | csjones smoke after PR 8 merge + csjones migrate + cache-clear — outstanding, requires csjones re-deploy |

§16.2 progress after Pass 5:
- 8 of 19 fully shipped (Savings + 4 ref-data + Pensions + Properties + Mortgages).
- 9 boundary tests passing (adds `MortgageStoreBoundaryTest`).
- 4 parity tests shipped (Savings + Pension + Property + Mortgage).
- 8 Store.md docs landed (adds `MortgageStore.md`).

---

## 14. Risks + mitigations

| Risk | Mitigation |
|---|---|
| **Cross-store recalc loops** — Mortgage write triggers Property recalc, which mistakenly triggers Mortgage recalc | One-way contract documented in §4.7 + Store.md quirk #9. PropertyStore does NOT call MortgageStore writes (only PropertyDerivedColumnCalculator reads via MortgageStore::forProperty). Test coverage in `MortgagePropertyReconciliationTest`. |
| **N+1 recalc on bulk operations** — backfilling 10k mortgages triggers 10k Property recalcs | Backfill commands use `chunkById(200)` + `saveQuietly` so events don't fire during backfill; cross-store recalc only fires for live writes from user actions (form/Fyn/upload). For one-off recalcs, use `BackfillPropertyOutstandingMortgage` command which iterates Properties (not Mortgages). |
| **`tenants_in_common` data already in the wild on mortgage rows** | Pre-Pass-5 DB cannot have `tenants_in_common` mortgages (the existing `MortgageService::normalizeMortgageOwnershipType` already coerces at write time; DB enum constraint also forbids it). Verify in PR 0 / PR 1 with: `SELECT COUNT(*) FROM mortgages WHERE ownership_type = 'tenants_in_common';` — expected 0. |
| **PropertyController::store calls MortgageService::createFromPropertyData inline** — joint-creation atomicity | PR 4 keeps the inline call but routes it through `MortgageStore::create`. Both PropertyStore::create AND MortgageStore::create are in their own transactions; PropertyController is responsible for handling partial failure (the same as today's pattern). If a future plan wants atomic property+mortgage, add a `CompositeCreatePropertyWithMortgage` orchestrator service — deferred. |
| **Sub-agent dispatch truncation on multi-file edits** | Mitigation from Pass 4 carries over: "add import AND use it in the constructor in the SAME edit". After 2 SendMessage resumes, take over manually. |
| **`MortgageService::createFromPropertyData` was the indirect-write site for years** — coverage might be sparse | Pre-Pass-5 audit: check if any tests cover `PropertyController::store` → `MortgageService::createFromPropertyData` → Mortgage::create chain. If thin, add an HTTP integration test in PR 2 covering POST `/api/properties/{id}` with `outstanding_mortgage > 0` and asserting both Property + Mortgage rows. |
| **The denormalised `properties.outstanding_mortgage` column drift** — pre-existing data may be inconsistent with mortgages sum | `BackfillPropertyOutstandingMortgage` command (PR 6) reconciles all properties. Run on csjones + prod as part of PR 6 deploy. Tier-1 acceptance test: post-backfill, `properties.outstanding_mortgage == SUM(mortgages.outstanding_balance WHERE property_id = X)` for every property. |
| **Joint-mortgage `joint_owner_id` integrity** — joint owner gets deleted (soft) | `Mortgage::jointOwner()` already uses `->withTrashed()` — handles soft-deleted joint owners gracefully. No change needed. |

---

## 15. Open questions — resolve in PR 0 / PR 1

| Q | Notes / proposed resolution |
|---|---|
| **Q1 — Tier-cap default for `mortgage`** | Proposed: `mortgage` free=10, tier1+=null. CSJ confirms at PR 1 dispatch (see §1.4). Adjustable via TierConfigurationSeeder later. |
| **Q2 — `MortgageStore::forUserByProperty` return shape** | Proposed: `Collection<int, Collection<int, Mortgage>>` keyed by property_id, values are Collections of Mortgage. Confirm in PR 1 design review. |
| **Q3 — Does PropertyController need to switch to a `CompositeStore` for atomic property+mortgage create?** | Proposed: NO for Pass 5 — keep the existing chain (PropertyStore::create then MortgageService::createFromPropertyData which now calls MortgageStore::create). Atomicity at the Property+Mortgage compound level is deferred. Reviewer-flaggable if it bites. |
| **Q4 — Should `MortgageResource` ship in PR 8?** | Proposed: NO — defer to a separate housekeeping PR if/when frontend needs it. Pass 5 preserves HTTP API shape. |
| **Q5 — `properties.outstanding_mortgage` — keep or drop?** | Proposed: KEEP as a derived column (write-only-by-recalc). Drop is breaking — many read consumers reference it for performance. The reconciliation contract (§1.6) keeps the value canonical without forcing readers to migrate. |
| **Q6 — Does the cross-store listener need queue-based async dispatch?** | Proposed: NO for Pass 5 — synchronous dispatch is fine because mortgage writes are infrequent (a user adds ~1 mortgage per session). If bulk operations become a perf issue, switch to `ShouldQueue` listener in a follow-up. |
| **Q7 — Estate Liabilities (`App\Models\Estate\Liability`) — defer to Pass 5b or fold into a future pass?** | Proposed: separate Pass 5b plan (see §0.1). Confirm with CSJ before opening Pass 5 PR 1. |

---

## 16. Pass 5 progress tracker

| PR | Title | Status | Branch |
|----|-------|--------|--------|
| 1 | MortgageStore facade + boundary + normaliser + events | pending | `feat/mortgage-store-pr1` |
| 2 | HTTP form requests through MortgageStore | pending | `feat/mortgage-store-pr2` |
| 3 | Fyn AI write tools through MortgageStore | pending | `feat/mortgage-store-pr3` |
| 4 | Upload + onboarding + seeders through MortgageStore | pending | `feat/mortgage-store-pr4` |
| 5 | Read consumers (sub-clustered 5a-5e) | pending | `feat/mortgage-store-pr5` |
| 6 | Canonical derived columns + snapshots + Property reconciliation | pending | `feat/mortgage-store-pr6` |
| 7 | Tier-cap test | pending | `feat/mortgage-store-pr7` |
| 8 | Lock-down + parity + audit + Store.md | pending | `feat/mortgage-store-pr8` |

**Sub-Project 1 progress after Pass 5 close-out:** 8 of 19 entity stores fully shipped (Savings + 4 ref-data + Pensions + Properties + Mortgages).

---

*End of Pass 5 — Mortgages plan.*
