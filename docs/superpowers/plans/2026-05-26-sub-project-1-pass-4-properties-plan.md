# Sub-Project 1, Pass 4 — Properties Canonical Store Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the `PropertyStore` service facade so every read and write of `App\Models\Property` goes through a single canonical API. Lock the boundary with a Pest architecture test that hard-fails CI on any direct model mutation outside the store. Materialise canonical derived columns (`current_value_gbp`, `equity_gbp`, `loan_to_value_pct`, etc.) with a snapshot table. Wire the tier-cap hook for `property`. Close the Pass 4 acceptance gates in spec §16 — including the per-entity `Store.md` and the three-ingest parity test — within PR 8 from the start (no after-the-fact close-out like Pass 3 needed).

**Architecture:** Service-facade-over-Eloquent (Approach A from the original SP1 brainstorm) — the canonical contract from `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md`. Properties is the second joint-aware entity to migrate (Savings was Pass 1's joint-aware proof-of-pattern); Pension (Pass 3) had no joint ownership so this pass restores the joint-aware read surface from the Savings template.

**Tech Stack:** Laravel 10, PHP 8.2, MySQL 8, Pest (PHPUnit-compatible), Sanctum auth, Vue/Vuex frontend (consumer surface only — touched in PR 5 for reads, no schema changes on the FE side).

---

## 0. Where this sits in the bigger picture

Pass 4 is the **fourth** of fourteen passes in Sub-Project 1 (Module Canonical Store-and-Retrieve Contract).

| Pass | Entity | Status |
|------|--------|--------|
| 1 | Savings (bank/cash) | ✅ DONE |
| 2 | Reference data (Tax / Currency / Actuarial / Savings rates) | ✅ DONE |
| 3 | Pensions (DC + DB + State + InputHistory) | ✅ DONE |
| **4** | **Properties** | **THIS PLAN** |
| 5 | Liabilities (incl. mortgages — pairs with Properties) | pending |
| 6 | Investments (multi-table) | pending |
| 7 | Income + Expenditure | pending |
| 8 | Protection | pending |
| 9 | Family members | pending |
| 10 | Goals + life events | pending |
| 11 | Chattels | pending |
| 12 | Business interests | pending |
| 13 | Trusts | pending |
| 14 | Wills + LPAs | pending |

**Important relationship — Mortgages are Pass 5, NOT Pass 4.** Per spec §3.1 (entity row 1 = "Properties | `properties`"; entity row 4 = "Liabilities | `liabilities`, mortgages"), mortgages belong to Pass 5's liabilities store. Pass 4 stops at the `properties` table boundary. Code that references `Mortgage::*` is left alone here; the `outstanding_mortgage` column on `properties` is treated as a denormalised cache today (the real source of truth being the `mortgages` table) — Pass 5 will reconcile this. The plan calls out where Property read paths touch Mortgage and how to handle that interim state.

---

## 1. Pre-pass audit (PR 0 baseline)

Completed 2026-05-26 against `dev` HEAD `eb3d091` post-Pass-3 close-out.

### 1.1 Files referencing `Property` (36 files)

| Category | Files |
|---|---|
| **Mutation sites** (8) | `PropertyController.php`, `PreviewController.php`, `CoordinatingAgent.php`, `DocumentProcessor.php`, `OnboardingService.php`, `MigrateEstateToNetWorth.php` (console), `ChrisUserSeeder.php`, `PreviewUserSeeder.php` |
| **Models + relationships** (3) | `Property.php` (self), `Household.php`, `User.php`, `Mortgage.php` (sibling) |
| **Observers + providers** (1) | `EventServiceProvider.php` |
| **Console commands** (3) | `EncryptExistingData.php`, `ResetPreviewData.php`, `MigrateEstateToNetWorth.php` |
| **Read consumers — Services** (~21) | `app/Services/AI/AdvicePromptBuilder.php`, `app/Services/AI/DuplicateAcknowledgement.php`, `app/Services/Coordination/HouseholdPlanningService.php`, `app/Services/Documents/DocumentProcessor.php`, `app/Services/Documents/DocumentTypeDetector.php`, `app/Services/Documents/FieldMappers/PropertyMapper.php`, `app/Services/Estate/EstateActionDefinitionService.php`, `app/Services/Estate/EstateAssetAggregatorService.php`, `app/Services/Estate/IHTCalculationService.php`, `app/Services/Estate/LetterEstateValidationService.php`, `app/Services/Mobile/MobileDashboardAggregator.php`, `app/Services/NetWorth/NetWorthService.php`, `app/Services/Onboarding/AssetCaptureEntityExtractor.php`, `app/Services/Onboarding/OnboardingService.php`, `app/Services/Shared/CrossModuleAssetAggregator.php`, `app/Services/Tax/IncomeDefinitionsService.php`, `app/Services/Trust/TrustAssetAggregatorService.php`, `app/Services/UserProfile/LetterToSpouseService.php`, `app/Services/UserProfile/PersonalAccountsService.php`, `app/Services/UserProfile/ProfileCompletenessChecker.php`, `app/Services/UserProfile/UserProfileService.php` |
| **HTTP controllers** (3) | `PropertyController.php`, `PreviewController.php`, `MortgageController.php` |
| **Resources** | (none currently — `PropertyResource` doesn't exist; HTTP returns Property models directly via Resource collections) |

### 1.2 Read pattern

Reads currently use:
- `Property::forUserOrJoint($userId)->...` — joint-aware scope from `HasJointOwnership` trait
- `$user->properties()->...` — `HasMany` relation
- `Property::where('id', $id)->where('user_id', $userId)->...` — single-id ownership lookup (PropertyController pattern)

There are **99 occurrences** of these patterns across `app/`. Migration via the store funnels them into `PropertyStore::find`, `forUser`, `forUserWithJointOwner`, `forUsers`, `findMany`, `forUserByType` (where `type` = `main_residence|secondary_residence|buy_to_let`).

### 1.3 Joint ownership

`Property` has the same joint-ownership shape as `SavingsAccount` plus extras:
- `ownership_type` ∈ `{individual, joint, tenants_in_common, trust}` — **Property is the ONLY entity where `tenants_in_common` is a valid `ownership_type`** (per memory `reference_tenants_in_common_is_property_only.md`). Savings/Pension/Investment/Goals/Estate all exclude it.
- `joint_ownership_type` ∈ `{joint_tenancy, tenants_in_common}` — additional second-level discriminator for `ownership_type='joint'` rows
- `joint_owner_id` (FK to users) — linked system user, NULL if not on Fynla
- `joint_owner_name` — free-text name when joint owner is not a system user
- `ownership_percentage` (decimal 5,2) — primary owner's share

Per CLAUDE.md Rule #7: joint properties use ONE row, not two. Spouse's share = `100 - ownership_percentage`. Reads use `WHERE user_id = ? OR joint_owner_id = ?`.

### 1.4 Tier-cap key

`TierConfigurationSeeder` currently has `count_caps` for: `savings_account` (free=3, tier1+=null), `investment` (free=2, tier1+=null), `pension_account` (free=5, tier1+=null). **`property` is NOT yet in `count_caps`.** Pass 4 adds it. Default `property` free=3 (sensible cap: main residence + 2 buy-to-let), tier1+=null (matches Pension pattern); the actual freemium number is an SP2 decision and can be adjusted in `TierConfigurationSeeder` later.

### 1.5 Existing factories + fixtures

- `database/factories/PropertyFactory.php` exists
- `database/seeders/PreviewUserSeeder.php` creates Property rows for personas (5+ direct `Property::create()` sites)
- `database/seeders/ChrisUserSeeder.php` creates 4 Property rows for CSJ's persona
- `database/seeders/LifecycleTestSeeder.php` — verify if it creates properties (audit in PR 4)

### 1.6 Mortgage relationship leak (Pass 5 boundary)

`StorePropertyRequest` accepts `mortgage_*` fields which `PropertyController::store` uses to ALSO create a `Mortgage` row in the same HTTP request. This is a cross-entity write that:
- Stays in `PropertyController` for Pass 4 (PropertyStore handles Property only; Mortgage create stays inline until Pass 5).
- The PropertyStore boundary test does NOT catch this (the test scopes to `Property::`, not `Mortgage::`).
- PR 5 (Mortgages) will route the mortgage-create through `MortgageStore` and PropertyController's store method will then call both `propertyStore->create()` + `mortgageStore->create()`.

This is documented behaviour; flag it in PropertyStore.md and the PR 8 boundary test docblock.

---

## 2. Scope

### 2.1 Files in scope

```
NEW
  app/Services/Stores/PropertyStore.php
  app/Services/Stores/Normalisers/PropertyNormaliser.php
  app/Services/Stores/Recalc/PropertyDerivedColumnCalculator.php  (PR 6)
  app/Events/Property/PropertyCreated.php
  app/Events/Property/PropertyUpdated.php
  app/Events/Property/PropertyDeleted.php
  app/Events/Property/PropertyRestored.php
  app/Console/Commands/BackfillPropertyDerivedColumns.php  (PR 6)
  app/Models/PropertyValueSnapshot.php  (PR 6)
  database/migrations/2026_05_27_*_add_derived_columns_to_properties.php  (PR 6)
  database/migrations/2026_05_27_*_create_property_value_snapshots_table.php  (PR 6)
  tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php  (PR 1, locked in PR 8)
  tests/Unit/Services/Stores/PropertyStoreTest.php  (PR 1, extended through pass)
  tests/Unit/Services/Stores/PropertyStoreEventsTest.php  (PR 1)
  tests/Unit/Services/Stores/Normalisers/PropertyNormaliserTest.php  (PR 1)
  tests/Unit/Services/Stores/Recalc/PropertyDerivedColumnCalculatorTest.php  (PR 6)
  tests/Feature/Stores/PropertyDerivedColumnsBackfillTest.php  (PR 6)
  tests/Feature/Stores/PropertyTierCapTest.php  (PR 7)
  tests/Feature/Stores/PropertyAuditIngestSourceTest.php  (PR 8)
  tests/Feature/Stores/PropertyThreeIngestParityTest.php  (PR 8)
  tests/Feature/Stores/PropertyUploadIngestTest.php  (PR 4)
  app/Services/Stores/PropertyStore.md  (PR 8)

MODIFIED
  app/Models/Property.php  (PR 6 — fillable + casts for derived columns)
  app/Services/Stores/Snapshots/SnapshotPolicies.php  (PR 6 — add property policies)
  app/Http/Controllers/Api/PropertyController.php  (PR 2 — route writes through store)
  app/Http/Controllers/Api/PreviewController.php  (PR 2 — preview property writes via store)
  app/Agents/CoordinatingAgent.php  (PR 3 — handleCreateProperty + handleUpdate + handleDelete through store)
  app/Services/Documents/DocumentProcessor.php  (PR 4 — Property upload path through store)
  app/Services/Onboarding/OnboardingService.php  (PR 4 — onboarding Property writes through store)
  database/seeders/PreviewUserSeeder.php  (PR 4 — Property creates via store)
  database/seeders/ChrisUserSeeder.php  (PR 4 — Property creates via store)
  database/seeders/LifecycleTestSeeder.php  (PR 4 — IF it creates Properties)
  database/seeders/TierConfigurationSeeder.php  (PR 1 — add 'property' to count_caps)
  app/Providers/AppServiceProvider.php  (PR 1 — IF a manual binding is needed; default auto-resolution should suffice)
  app/Providers/EventServiceProvider.php  (PR 1 — list the 4 Property events for transparency; listeners optional)
  [~21 read-consumer service files]  (PR 5, sub-clustered)
```

### 2.2 Files out of scope

- **`app/Models/Mortgage.php` + `MortgageController` + Mortgage seeders** — Pass 5.
- **`app/Services/Property/PropertyCalculationService.php`** — pure calculation helper. Not a store consumer (its only inputs are model instances passed by callers). Stays as-is; PR 6 may inline part of `calculateEquity` into the new `PropertyDerivedColumnCalculator`.
- **`app/Services/Property/PropertyTaxService.php`** (SDLT + CGT + rental tax) — pure calculation helper invoked from PropertyController endpoints. Stays as-is.
- **Front-end (Vue components)** — no Vue changes in this pass. HTTP API shape is preserved (Resource collections of Property models with same fields).
- **`PropertyResource`** — does not currently exist. Optional add in PR 8; not blocking.

---

## 3. Dependencies + bindings

| Dependency | Status |
|---|---|
| `App\Services\Stores\IngestSource` enum (with `FORM`, `FYN_AI`, `UPLOAD`, `SEEDER`, `ADMIN`) | EXISTS from Pass 1 |
| `App\Services\Stores\TierGate` interface | EXISTS from Pass 1 |
| `App\Services\Stores\TierConfigurationStore` + `DbTierGate` binding | EXISTS from SP2 PR 3 |
| `App\Services\Stores\Snapshots\SnapshotPolicies` + `SnapshotPolicy` | EXISTS from Pass 1; extend in PR 6 |
| `App\Models\AuditLog::withContext()` | EXISTS from Pass 1 PR 8 |
| `App\Traits\Auditable` on Property | EXISTS — `use Auditable, HasFactory, HasJointOwnership, SoftDeletes;` (Property.php:19) |

No new bindings. `AppServiceProvider` does not need changes unless `PropertyDerivedColumnCalculator` requires explicit DI for `TaxConfigService` / FX rate access — Laravel auto-resolution should handle it (see PR 6).

---

## 4. Cross-PR conventions

These apply across every PR in this pass:

### 4.1 IngestSource enum reuse

The shared `App\Services\Stores\IngestSource` enum is used. No new enum file.

### 4.2 Audit context

Every write method wraps its `DB::transaction` in `AuditLog::withContext(['ingest_source' => $source->value], …)` — pattern from Pass 1 PR 8 + Pass 3 PR 8 + Savings precedent. **The wrap is added in PR 1 from the start** (not deferred to PR 8 like Pass 3 was). This avoids the by-reference-capture bug Pass 3 hit when `fn () => DB::transaction(function() use (&$dirty))` was wrapped retroactively — for `update` (which captures `$dirty`), return a tuple `['fresh' => $fresh, 'dirty' => $dirty]` from the inner closure.

### 4.3 Joint ownership invariant

Every read method that returns rows visible to a user uses `WHERE user_id = ? OR joint_owner_id = ?`. The `HasJointOwnership` trait already provides `Property::forUserOrJoint($id)` — the store wraps this. **Joint owners get READ-ONLY access; mutations require `$user->id === property.user_id`** (matches Savings + Property's current controller behaviour).

### 4.4 `tenants_in_common` preservation

`PropertyStore::validateCanonical` MUST include `tenants_in_common` in the allowed `ownership_type` set (it's the only entity where this value is valid). PropertyNormaliser preserves it across `fromForm` / `fromFyn` / `fromUpload`. The PropertyThreeIngestParityTest (PR 8) covers a `tenants_in_common` case explicitly.

### 4.5 Pint hook strips unused imports

When wiring `AuditLog::withContext`, add `use App\Models\AuditLog;` **after** the body references exist (Pint will strip the import otherwise — Pass 3 hit this ~10 times). Same for `use App\Services\Stores\PensionStore;` style imports in tests.

### 4.6 Commit cadence

Each step ending in `git commit` represents one local commit. Push to remote at PR open time. Admin-merge per the established CSJ pattern (after explicit "merge it" instruction).

---

## 5. PR 1 — Introduce PropertyStore facade + boundary + normaliser + events

**PR title:** `feat(properties): introduce PropertyStore facade + arch boundary (SP1 Pass 4 PR 1)`

**Branch:** `feat/property-store-pr1` off `dev`.

**Files:**

| Action | Path |
|---|---|
| Create | `app/Services/Stores/PropertyStore.php` |
| Create | `app/Services/Stores/Normalisers/PropertyNormaliser.php` |
| Create | `app/Events/Property/PropertyCreated.php` |
| Create | `app/Events/Property/PropertyUpdated.php` |
| Create | `app/Events/Property/PropertyDeleted.php` |
| Create | `app/Events/Property/PropertyRestored.php` |
| Create | `tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php` |
| Create | `tests/Unit/Services/Stores/PropertyStoreTest.php` |
| Create | `tests/Unit/Services/Stores/PropertyStoreEventsTest.php` |
| Create | `tests/Unit/Services/Stores/Normalisers/PropertyNormaliserTest.php` |
| Modify | `database/seeders/TierConfigurationSeeder.php` (add `property` count_cap) |

### Step 1.1: Create the four event classes

- [ ] **Create `app/Events/Property/PropertyCreated.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Events\Property;

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Property $property,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
```

- [ ] **Create `app/Events/Property/PropertyUpdated.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Events\Property;

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $changes  Eloquent getDirty() diff captured pre-save.
     */
    public function __construct(
        public readonly Property $property,
        public readonly array $changes,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
```

- [ ] **Create `app/Events/Property/PropertyDeleted.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Events\Property;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $propertyId,
        public readonly User $user,
        public readonly string $reason,
    ) {}
}
```

- [ ] **Create `app/Events/Property/PropertyRestored.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Events\Property;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyRestored
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Property $property,
        public readonly User $user,
    ) {}
}
```

### Step 1.2: Create the PropertyNormaliser

- [ ] **Create `app/Services/Stores/Normalisers/PropertyNormaliser.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

class PropertyNormaliser
{
    private const ALLOWED_PROPERTY_TYPES = ['main_residence', 'secondary_residence', 'buy_to_let'];

    private const ALLOWED_OWNERSHIP_TYPES = ['individual', 'joint', 'tenants_in_common', 'trust'];

    private const ALLOWED_JOINT_OWNERSHIP_TYPES = ['joint_tenancy', 'tenants_in_common'];

    private const ALLOWED_TENURE_TYPES = ['freehold', 'leasehold'];

    /**
     * Form ingest — HTTP form-request validated payload.
     *
     * StorePropertyRequest / UpdatePropertyRequest already validates the
     * inbound shape (the outer layer per spec §7.2); the normaliser only
     * needs to canonicalise enum values, default ownership defaults, and
     * strip the mortgage_* fields that PropertyController handles via
     * direct Mortgage::create (Pass 5 will route those through MortgageStore).
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function fromForm(array $request): array
    {
        $data = $request;

        // mortgage_* fields stay in the controller (Pass 5).
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'mortgage_')) {
                unset($data[$key]);
            }
        }

        $data['property_type'] = $this->canonicalPropertyType($data['property_type'] ?? null);
        $data['ownership_type'] = $this->canonicalOwnershipType($data['ownership_type'] ?? null);
        $data['joint_ownership_type'] = $this->canonicalJointOwnershipType($data['joint_ownership_type'] ?? null);
        $data['tenure_type'] = $this->canonicalTenureType($data['tenure_type'] ?? null);

        // ownership_percentage defaults to 100 for individual / trust; required for joint*.
        if (in_array($data['ownership_type'], ['individual', 'trust'], true)) {
            $data['ownership_percentage'] = $data['ownership_percentage'] ?? 100.00;
        }

        return $data;
    }

    /**
     * Fyn AI ingest — tool-call parameters from CoordinatingAgent::handleCreateProperty.
     *
     * Fyn's vocabulary is looser than the form: it may pass `address` instead
     * of `address_line_1`, `value` instead of `current_value`, `is_joint` as a
     * boolean inferred from natural language. Map these onto the canonical
     * shape.
     *
     * @param  array<string, mixed>  $toolParams
     * @return array<string, mixed>
     */
    public function fromFyn(array $toolParams): array
    {
        $canonical = [];

        // Address — Fyn may pass `address` as a single string OR pre-split fields.
        if (isset($toolParams['address']) && ! isset($toolParams['address_line_1'])) {
            $canonical['address_line_1'] = (string) $toolParams['address'];
        } else {
            foreach (['address_line_1', 'address_line_2', 'city', 'county', 'postcode'] as $field) {
                if (isset($toolParams[$field])) {
                    $canonical[$field] = (string) $toolParams[$field];
                }
            }
        }

        // Financial — Fyn may use `value` as shorthand for `current_value`.
        if (isset($toolParams['value']) && ! isset($toolParams['current_value'])) {
            $canonical['current_value'] = (float) $toolParams['value'];
        }
        foreach (['current_value', 'purchase_price', 'outstanding_mortgage', 'monthly_rental_income', 'sdlt_paid'] as $field) {
            if (isset($toolParams[$field]) && is_numeric($toolParams[$field])) {
                $canonical[$field] = (float) $toolParams[$field];
            }
        }

        // Dates
        foreach (['purchase_date', 'valuation_date', 'lease_start_date', 'lease_end_date', 'lease_expiry_date'] as $field) {
            if (isset($toolParams[$field]) && $toolParams[$field] !== '') {
                $canonical[$field] = (string) $toolParams[$field];
            }
        }

        // Enums
        $canonical['property_type'] = $this->canonicalPropertyType($toolParams['property_type'] ?? null);
        $canonical['ownership_type'] = $this->canonicalOwnershipType($toolParams['ownership_type'] ?? null);
        $canonical['joint_ownership_type'] = $this->canonicalJointOwnershipType($toolParams['joint_ownership_type'] ?? null);
        $canonical['tenure_type'] = $this->canonicalTenureType($toolParams['tenure_type'] ?? null);

        // is_joint shorthand → ownership_type='joint'.
        if (! isset($toolParams['ownership_type']) && ! empty($toolParams['is_joint'])) {
            $canonical['ownership_type'] = 'joint';
            $canonical['joint_ownership_type'] = $canonical['joint_ownership_type'] ?? 'joint_tenancy';
        }

        // ownership_percentage
        if (isset($toolParams['ownership_percentage']) && is_numeric($toolParams['ownership_percentage'])) {
            $canonical['ownership_percentage'] = (float) $toolParams['ownership_percentage'];
        } elseif (in_array($canonical['ownership_type'], ['individual', 'trust'], true)) {
            $canonical['ownership_percentage'] = 100.00;
        }

        // Lease numeric
        if (isset($toolParams['lease_remaining_years']) && is_numeric($toolParams['lease_remaining_years'])) {
            $canonical['lease_remaining_years'] = (int) $toolParams['lease_remaining_years'];
        }

        // Country (UK default applied at the controller level historically; mirror that).
        if (isset($toolParams['country']) && $toolParams['country'] !== '') {
            $canonical['country'] = (string) $toolParams['country'];
        }

        // Joint-owner identity (linked or free-text)
        if (isset($toolParams['joint_owner_id']) && is_numeric($toolParams['joint_owner_id'])) {
            $canonical['joint_owner_id'] = (int) $toolParams['joint_owner_id'];
        }
        if (isset($toolParams['joint_owner_name']) && $toolParams['joint_owner_name'] !== '') {
            $canonical['joint_owner_name'] = (string) $toolParams['joint_owner_name'];
        }

        // Trust
        if (isset($toolParams['trust_id']) && is_numeric($toolParams['trust_id'])) {
            $canonical['trust_id'] = (int) $toolParams['trust_id'];
        }
        if (isset($toolParams['trust_name']) && $toolParams['trust_name'] !== '') {
            $canonical['trust_name'] = (string) $toolParams['trust_name'];
        }

        // Notes
        if (isset($toolParams['notes']) && $toolParams['notes'] !== '') {
            $canonical['notes'] = (string) $toolParams['notes'];
        }

        return $canonical;
    }

    /**
     * Upload ingest — document-extraction shape via DocumentProcessor + PropertyMapper.
     *
     * The mapper produces a fairly clean shape already; the normaliser is
     * mostly canonicalising enums + defaulting ownership.
     *
     * @param  array<string, mixed>  $extraction
     * @return array<string, mixed>
     */
    public function fromUpload(array $extraction): array
    {
        $canonical = [];

        foreach (['address_line_1', 'address_line_2', 'city', 'county', 'postcode'] as $field) {
            if (isset($extraction[$field]) && $extraction[$field] !== '') {
                $canonical[$field] = (string) $extraction[$field];
            }
        }
        foreach (['current_value', 'purchase_price', 'outstanding_mortgage', 'monthly_rental_income', 'sdlt_paid'] as $field) {
            if (isset($extraction[$field]) && is_numeric($extraction[$field])) {
                $canonical[$field] = (float) $extraction[$field];
            }
        }
        foreach (['purchase_date', 'valuation_date'] as $field) {
            if (isset($extraction[$field]) && $extraction[$field] !== '') {
                $canonical[$field] = (string) $extraction[$field];
            }
        }

        $canonical['property_type'] = $this->canonicalPropertyType($extraction['property_type'] ?? null);
        $canonical['ownership_type'] = $this->canonicalOwnershipType($extraction['ownership_type'] ?? null);
        $canonical['tenure_type'] = $this->canonicalTenureType($extraction['tenure_type'] ?? null);

        // Uploads default to individual ownership at 100% unless specified.
        if ($canonical['ownership_type'] === 'individual' && ! isset($canonical['ownership_percentage'])) {
            $canonical['ownership_percentage'] = 100.00;
        } elseif (isset($extraction['ownership_percentage']) && is_numeric($extraction['ownership_percentage'])) {
            $canonical['ownership_percentage'] = (float) $extraction['ownership_percentage'];
        }

        if (isset($extraction['country']) && $extraction['country'] !== '') {
            $canonical['country'] = (string) $extraction['country'];
        }

        if (isset($extraction['lease_remaining_years']) && is_numeric($extraction['lease_remaining_years'])) {
            $canonical['lease_remaining_years'] = (int) $extraction['lease_remaining_years'];
        }

        return $canonical;
    }

    private function canonicalPropertyType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return in_array($value, self::ALLOWED_PROPERTY_TYPES, true) ? $value : 'main_residence';
    }

    private function canonicalOwnershipType(?string $value): string
    {
        return in_array($value, self::ALLOWED_OWNERSHIP_TYPES, true) ? $value : 'individual';
    }

    private function canonicalJointOwnershipType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return in_array($value, self::ALLOWED_JOINT_OWNERSHIP_TYPES, true) ? $value : null;
    }

    private function canonicalTenureType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return in_array($value, self::ALLOWED_TENURE_TYPES, true) ? $value : 'freehold';
    }
}
```

### Step 1.3: Write the failing PropertyNormaliser test

- [ ] **Create `tests/Unit/Services/Stores/Normalisers/PropertyNormaliserTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Services\Stores\Normalisers\PropertyNormaliser;

it('fromForm strips mortgage_* keys and defaults ownership_percentage to 100 for individual', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromForm([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 500000,
        'mortgage_lender_name' => 'Halifax',
        'mortgage_monthly_payment' => 1200,
        'mortgage_type' => 'repayment',
    ]);

    expect($canonical)->toHaveKey('property_type', 'main_residence');
    expect($canonical)->toHaveKey('ownership_type', 'individual');
    expect($canonical)->toHaveKey('ownership_percentage', 100.00);
    expect($canonical)->toHaveKey('current_value', 500000);
    expect($canonical)->not->toHaveKey('mortgage_lender_name');
    expect($canonical)->not->toHaveKey('mortgage_monthly_payment');
    expect($canonical)->not->toHaveKey('mortgage_type');
});

it('fromForm preserves tenants_in_common ownership_type (property-only)', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromForm([
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 60,
        'joint_owner_name' => 'Jane Doe',
    ]);

    expect($canonical['ownership_type'])->toBe('tenants_in_common');
    expect($canonical['ownership_percentage'])->toBe(60);
});

it('fromFyn maps address shorthand to address_line_1 and is_joint to ownership_type', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromFyn([
        'address' => '10 Downing Street',
        'value' => 5_000_000,
        'is_joint' => true,
        'property_type' => 'main_residence',
    ]);

    expect($canonical['address_line_1'])->toBe('10 Downing Street');
    expect($canonical['current_value'])->toBe(5_000_000.0);
    expect($canonical['ownership_type'])->toBe('joint');
    expect($canonical['joint_ownership_type'])->toBe('joint_tenancy');
});

it('fromUpload defaults to individual ownership at 100% when extraction omits ownership', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromUpload([
        'address_line_1' => '5 Acacia Avenue',
        'current_value' => 350000,
        'property_type' => 'main_residence',
    ]);

    expect($canonical['ownership_type'])->toBe('individual');
    expect($canonical['ownership_percentage'])->toBe(100.00);
});

it('fromFyn preserves trust ownership with trust_name and trust_id', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromFyn([
        'address' => 'Trust House',
        'ownership_type' => 'trust',
        'trust_name' => 'Smith Family Trust',
    ]);

    expect($canonical['ownership_type'])->toBe('trust');
    expect($canonical['trust_name'])->toBe('Smith Family Trust');
    expect($canonical['ownership_percentage'])->toBe(100.00);
});
```

- [ ] **Run, confirm 5 PASS:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/Normalisers/PropertyNormaliserTest.php
```

### Step 1.4: Create the PropertyStore skeleton

- [ ] **Create `app/Services/Stores/PropertyStore.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Property\PropertyCreated;
use App\Events\Property\PropertyDeleted;
use App\Events\Property\PropertyRestored;
use App\Events\Property\PropertyUpdated;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PropertyStore
{
    public const ENTITY_KEY = 'property';

    public function __construct(
        private readonly PropertyNormaliser $normaliser,
        private readonly TierGate $tierGate,
    ) {}

    // ---------- Reads ----------

    public function find(int $id, User $user): ?Property
    {
        return Property::query()
            ->where('id', $id)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->first();
    }

    public function forUser(User $user): Collection
    {
        return Property::forUserOrJoint($user->id)->get();
    }

    public function forUserWithJointOwner(User $user): Collection
    {
        return Property::forUserOrJoint($user->id)->with('jointOwner')->get();
    }

    public function forUsers(array $userIds): Collection
    {
        if ($userIds === []) {
            return new Collection;
        }

        return Property::query()
            ->where(function ($q) use ($userIds) {
                $q->whereIn('user_id', $userIds)->orWhereIn('joint_owner_id', $userIds);
            })
            ->get();
    }

    public function findMany(array $ids, User $user): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Property::query()
            ->whereIn('id', $ids)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->get();
    }

    public function forUserByType(User $user, string $propertyType): Collection
    {
        return Property::forUserOrJoint($user->id)
            ->where('property_type', $propertyType)
            ->get();
    }

    // ---------- Writes ----------

    public function create(array $data, User $user, IngestSource $source): Property
    {
        $this->validateCanonical($data);
        $this->enforceTierCap($user);

        $attributes = array_merge($data, ['user_id' => $user->id]);

        $property = AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($attributes) {
            return Property::create($attributes);
        }));

        event(new PropertyCreated($property, $user, $source));

        return $property;
    }

    public function update(int $id, array $data, User $user, IngestSource $source): Property
    {
        $property = Property::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateCanonical($data, partial: true);

        $result = AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($property, $data) {
            $property->fill($data);
            $dirty = $property->getDirty();
            $property->save();

            return ['fresh' => $property->fresh(), 'dirty' => $dirty];
        }));

        event(new PropertyUpdated($result['fresh'], $result['dirty'], $user, $source));

        return $result['fresh'];
    }

    public function updateOrCreate(array $match, array $data, User $user, IngestSource $source): Property
    {
        $existing = Property::where('user_id', $user->id)->where($match)->first();

        if ($existing) {
            return $this->update($existing->id, $data, $user, $source);
        }

        return $this->create(array_merge($match, $data), $user, $source);
    }

    public function delete(int $id, User $user, string $reason): void
    {
        $property = Property::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $property->delete();

        event(new PropertyDeleted($id, $user, $reason));
    }

    public function restore(int $id, User $user): Property
    {
        $property = Property::withTrashed()->where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $property->restore();

        event(new PropertyRestored($property, $user));

        return $property;
    }

    // ---------- Internal ----------

    private function enforceTierCap(User $user): void
    {
        $count = Property::where('user_id', $user->id)->count();

        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $count)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $count,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY)
            );
        }
    }

    private function validateCanonical(array $data, bool $partial = false): void
    {
        $rules = [
            'property_type' => 'sometimes|nullable|in:main_residence,secondary_residence,buy_to_let',
            // Property is the ONLY entity that allows tenants_in_common as ownership_type.
            'ownership_type' => 'sometimes|in:individual,joint,tenants_in_common,trust',
            'joint_ownership_type' => 'sometimes|nullable|in:joint_tenancy,tenants_in_common',
            'joint_owner_id' => 'sometimes|nullable|integer|exists:users,id',
            'joint_owner_name' => 'sometimes|nullable|string|max:255',
            'household_id' => 'sometimes|nullable|integer|exists:households,id',
            'trust_id' => 'sometimes|nullable|integer|exists:trusts,id',
            'trust_name' => 'sometimes|nullable|string|max:255',
            'ownership_percentage' => 'sometimes|numeric|min:0|max:100',

            'tenure_type' => 'sometimes|nullable|in:freehold,leasehold',
            'lease_remaining_years' => 'sometimes|nullable|integer|min:0|max:999',
            'lease_expiry_date' => 'sometimes|nullable|date',
            'lease_start_date' => 'sometimes|nullable|date',
            'lease_end_date' => 'sometimes|nullable|date',

            'address_line_1' => 'sometimes|nullable|string|max:255',
            'address_line_2' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'county' => 'sometimes|nullable|string|max:255',
            'postcode' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:255',

            'purchase_date' => 'sometimes|nullable|date',
            'purchase_price' => 'sometimes|nullable|numeric|min:0|max:999999999.99',
            'current_value' => 'sometimes|numeric|min:0|max:999999999.99',
            'valuation_date' => 'sometimes|nullable|date',
            'sdlt_paid' => 'sometimes|nullable|numeric|min:0|max:999999999.99',
            'monthly_rental_income' => 'sometimes|nullable|numeric|min:0',
            'outstanding_mortgage' => 'sometimes|nullable|numeric|min:0|max:999999999.99',

            'tenant_name' => 'sometimes|nullable|string|max:255',
            'tenant_email' => 'sometimes|nullable|email|max:255',
            'managing_agent_name' => 'sometimes|nullable|string|max:255',
            'managing_agent_company' => 'sometimes|nullable|string|max:255',
            'managing_agent_email' => 'sometimes|nullable|email|max:255',
            'managing_agent_phone' => 'sometimes|nullable|string|max:255',
            'managing_agent_fee' => 'sometimes|nullable|numeric|min:0',

            'monthly_council_tax' => 'sometimes|nullable|numeric|min:0',
            'monthly_gas' => 'sometimes|nullable|numeric|min:0',
            'monthly_electricity' => 'sometimes|nullable|numeric|min:0',
            'monthly_water' => 'sometimes|nullable|numeric|min:0',
            'monthly_building_insurance' => 'sometimes|nullable|numeric|min:0',
            'monthly_contents_insurance' => 'sometimes|nullable|numeric|min:0',
            'monthly_service_charge' => 'sometimes|nullable|numeric|min:0',
            'monthly_maintenance_reserve' => 'sometimes|nullable|numeric|min:0',
            'other_monthly_costs' => 'sometimes|nullable|numeric|min:0',

            'notes' => 'sometimes|nullable|string|max:1000',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
```

### Step 1.5: Write the failing PropertyStore unit test (smoke)

- [ ] **Create `tests/Unit/Services/Stores/PropertyStoreTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('PropertyStore::create persists a Property row scoped to the user', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'current_value' => 350000,
        'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    expect($property->user_id)->toBe($user->id);
    expect($property->property_type)->toBe('main_residence');
    expect($property->ownership_type)->toBe('individual');
    expect((string) $property->ownership_percentage)->toBe('100.00');
    expect((string) $property->current_value)->toBe('350000.00');
});

it('PropertyStore::create accepts tenants_in_common ownership (property-only enum value)', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 60,
        'joint_owner_name' => 'Jane Doe',
        'current_value' => 500000,
    ], $user, IngestSource::FORM);

    expect($property->ownership_type)->toBe('tenants_in_common');
    expect((string) $property->ownership_percentage)->toBe('60.00');
});

it('PropertyStore::create rejects invalid property_type via inner validator', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    expect(fn () => $store->create([
        'property_type' => 'not_a_valid_type',
        'ownership_type' => 'individual',
        'current_value' => 100,
    ], $user, IngestSource::FORM))->toThrow(StoreValidationException::class);
});

it('PropertyStore::find is joint-aware (joint owner sees the same property)', function () {
    $owner = User::factory()->create(['tier' => 'tier1']);
    $jointOwner = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'joint_ownership_type' => 'joint_tenancy',
        'joint_owner_id' => $jointOwner->id,
        'ownership_percentage' => 50,
        'current_value' => 400000,
    ], $owner, IngestSource::FORM);

    expect($store->find($property->id, $owner))->not->toBeNull();
    expect($store->find($property->id, $jointOwner)->id)->toBe($property->id);
});

it('PropertyStore::update is primary-owner-only — joint owner cannot mutate', function () {
    $owner = User::factory()->create(['tier' => 'tier1']);
    $jointOwner = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'joint_ownership_type' => 'joint_tenancy',
        'joint_owner_id' => $jointOwner->id,
        'ownership_percentage' => 50,
        'current_value' => 400000,
    ], $owner, IngestSource::FORM);

    expect(fn () => $store->update($property->id, ['current_value' => 999999], $jointOwner, IngestSource::FORM))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('PropertyStore::forUser returns properties where user is primary or joint owner', function () {
    $alice = User::factory()->create(['tier' => 'tier1']);
    $bob = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'joint_owner_id' => $bob->id,
        'ownership_percentage' => 50,
        'current_value' => 400000,
    ], $alice, IngestSource::FORM);

    $store->create([
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 200000,
    ], $alice, IngestSource::FORM);

    expect($store->forUser($alice)->count())->toBe(2);
    expect($store->forUser($bob)->count())->toBe(1);
});

it('PropertyStore::delete soft-deletes; restore brings the row back', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 350000,
    ], $user, IngestSource::FORM);

    $store->delete($property->id, $user, 'sold');

    expect(Property::find($property->id))->toBeNull();
    expect(Property::withTrashed()->find($property->id))->not->toBeNull();

    $restored = $store->restore($property->id, $user);
    expect($restored->id)->toBe($property->id);
    expect(Property::find($property->id))->not->toBeNull();
});
```

- [ ] **Run, confirm 7 PASS:**

```bash
./vendor/bin/pest tests/Unit/Services/Stores/PropertyStoreTest.php
```

### Step 1.6: Write the PropertyStoreEventsTest

- [ ] **Create `tests/Unit/Services/Stores/PropertyStoreEventsTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Events\Property\PropertyCreated;
use App\Events\Property\PropertyDeleted;
use App\Events\Property\PropertyRestored;
use App\Events\Property\PropertyUpdated;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('create emits PropertyCreated with source', function () {
    Event::fake();
    $user = User::factory()->create(['tier' => 'tier1']);

    app(PropertyStore::class)->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 350000,
        'ownership_percentage' => 100,
    ], $user, IngestSource::FORM);

    Event::assertDispatched(PropertyCreated::class, fn ($e) =>
        $e->user->id === $user->id && $e->source === IngestSource::FORM
    );
});

it('update emits PropertyUpdated with changes diff', function () {
    Event::fake();
    $user = User::factory()->create(['tier' => 'tier1']);
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 350000]);

    app(PropertyStore::class)->update($property->id, ['current_value' => 425000], $user, IngestSource::FORM);

    Event::assertDispatched(PropertyUpdated::class, fn ($e) =>
        array_key_exists('current_value', $e->changes)
    );
});

it('delete emits PropertyDeleted with reason', function () {
    Event::fake();
    $user = User::factory()->create(['tier' => 'tier1']);
    $property = Property::factory()->create(['user_id' => $user->id]);

    app(PropertyStore::class)->delete($property->id, $user, 'sold');

    Event::assertDispatched(PropertyDeleted::class, fn ($e) => $e->reason === 'sold');
});

it('restore emits PropertyRestored', function () {
    Event::fake();
    $user = User::factory()->create(['tier' => 'tier1']);
    $property = Property::factory()->create(['user_id' => $user->id]);
    $property->delete();

    app(PropertyStore::class)->restore($property->id, $user);

    Event::assertDispatched(PropertyRestored::class);
});
```

- [ ] **Run, confirm 4 PASS:**

```bash
./vendor/bin/pest tests/Unit/Services/Stores/PropertyStoreEventsTest.php
```

### Step 1.7: Seed the `property` tier-cap

- [ ] **Edit `database/seeders/TierConfigurationSeeder.php`. Add `'property' => 3` to free tier's `count_caps`, and `'property' => null` to tier1/tier2/tier3:**

For each tier row, modify the `count_caps` value:

- free: `['savings_account' => 3, 'investment' => 2, 'pension_account' => 5, 'property' => 3]`
- tier1: `['savings_account' => null, 'investment' => null, 'pension_account' => null, 'property' => null]`
- tier2: `['savings_account' => null, 'investment' => null, 'pension_account' => null, 'property' => null]`
- tier3: `['savings_account' => null, 'investment' => null, 'pension_account' => null, 'property' => null]`

- [ ] **Reseed:**

```bash
php artisan db:seed --class=TierConfigurationSeeder --force
```

### Step 1.8: Create the boundary architecture test (SOFT — full transition allowlist)

The boundary test ships in PR 1 covering EVERY current `Property::` consumer in the audit. As PRs 2–7 migrate consumers off direct model access, entries get removed. PR 8 reframes the surviving entries as documented residuals.

- [ ] **Create `tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php`:**

```php
<?php

declare(strict_types=1);

/**
 * Sub-Project 1, Pass 4 — Property store boundary enforcement.
 *
 * Hard-fails CI on any direct mutation/query of App\Models\Property
 * outside the canonical write path (App\Services\Stores\PropertyStore).
 *
 * Allowlist (§14.2 of the spec): observers, migrations, seeders, console
 * commands, the store itself, and pre-existing direct-mutation sites that
 * subsequent PRs in this pass will migrate. Each entry below has a comment
 * naming the PR that removes it.
 */
$propertyConsumers = [
    // Permanent allowlist (per spec §14.2)
    'App\Services\Stores\PropertyStore',
    'App\Services\Stores\Normalisers\PropertyNormaliser',
    'Database\Factories\PropertyFactory',
    'App\Models\\',  // self-references in relationships

    // Domain events introduced by PR 1 — typed constructor params reference Property.
    'App\Events\Property\PropertyCreated',
    'App\Events\Property\PropertyUpdated',
    'App\Events\Property\PropertyRestored',
    'App\Providers\EventServiceProvider',

    // PR 2 removes: HTTP write paths
    'App\Http\Controllers\Api\PropertyController',
    'App\Http\Controllers\Api\PreviewController',
    // PR 3 removes: Fyn AI tool path
    'App\Agents\CoordinatingAgent',
    // PR 4 removes: upload + onboarding + seeders
    'App\Services\Documents\DocumentProcessor',
    'App\Services\Onboarding\OnboardingService',
    'App\Services\Onboarding\AssetCaptureEntityExtractor',
    'Database\Seeders\PreviewUserSeeder',
    'Database\Seeders\ChrisUserSeeder',
    'Database\Seeders\LifecycleTestSeeder',
    'App\Console\Commands\MigrateEstateToNetWorth',
    // PR 5 removes: read consumers (~21 services + Mortgage relationship reads)
    'App\Http\Controllers\Api\MortgageController',
    'App\Services\AI\AdvicePromptBuilder',
    'App\Services\AI\DuplicateAcknowledgement',
    'App\Services\Coordination\HouseholdPlanningService',
    'App\Services\Documents\DocumentTypeDetector',
    'App\Services\Documents\FieldMappers\PropertyMapper',
    'App\Services\Estate\EstateActionDefinitionService',
    'App\Services\Estate\EstateAssetAggregatorService',
    'App\Services\Estate\IHTCalculationService',
    'App\Services\Estate\LetterEstateValidationService',
    'App\Services\Mobile\MobileDashboardAggregator',
    'App\Services\NetWorth\NetWorthService',
    'App\Services\Shared\CrossModuleAssetAggregator',
    'App\Services\Tax\IncomeDefinitionsService',
    'App\Services\Trust\TrustAssetAggregatorService',
    'App\Services\UserProfile\LetterToSpouseService',
    'App\Services\UserProfile\PersonalAccountsService',
    'App\Services\UserProfile\ProfileCompletenessChecker',
    'App\Services\UserProfile\UserProfileService',

    // Sibling models + console commands — out-of-Pass-4 read/infra refs
    'App\Models\Household',
    'App\Models\Mortgage',
    'App\Models\User',  // relationships only
    'App\Console\Commands\EncryptExistingData',
    'App\Console\Commands\ResetPreviewData',
];

arch('Property is only used inside the property canonical set (plus transition allowlist)')
    ->expect('App\Models\Property')
    ->toOnlyBeUsedIn($propertyConsumers);
```

- [ ] **Run, confirm GREEN (it should be — every current consumer is allowlisted):**

```bash
./vendor/bin/pest tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php
```

### Step 1.9: Commit + open PR 1

- [ ] **Stage + commit:**

```bash
git checkout -b feat/property-store-pr1
git add app/Events/Property/ app/Services/Stores/PropertyStore.php app/Services/Stores/Normalisers/PropertyNormaliser.php tests/Unit/Services/Stores/PropertyStoreTest.php tests/Unit/Services/Stores/PropertyStoreEventsTest.php tests/Unit/Services/Stores/Normalisers/PropertyNormaliserTest.php tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php database/seeders/TierConfigurationSeeder.php
git commit -m "feat(properties): introduce PropertyStore facade + arch boundary (SP1 Pass 4 PR 1)

Adds PropertyStore facade, PropertyNormaliser (form/fyn/upload), four
domain events (Created/Updated/Deleted/Restored), and the soft boundary
arch test covering every current Property:: consumer in the audit.
TierConfigurationSeeder gains 'property' count_cap (free=3, tier1+=null).

No consumers wired yet — that lands in PR 2-5. The boundary test is GREEN
on landing because every current consumer is allowlisted; subsequent PRs
remove entries as paths route through PropertyStore.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin feat/property-store-pr1
```

- [ ] **Open PR via `gh pr create --base dev --head feat/property-store-pr1 --title \"feat(properties): introduce PropertyStore facade + arch boundary (SP1 Pass 4 PR 1)\" --body <full PR body>`.** Body should cover: scope, what's added, what's NOT wired yet, test counts (5 normaliser + 7 store + 4 events + 1 boundary = 17 cases), Pass 4 progress (1/8).

- [ ] **Wait for CSJ admin-merge before starting PR 2.**

---

## 6. PR 2 — Point HTTP form requests at PropertyStore

**PR title:** `refactor(properties): point HTTP form requests at PropertyStore (SP1 Pass 4 PR 2)`

**Branch:** `feat/property-store-pr2` off `dev` (after PR 1 merges).

**Files:**

| Action | Path |
|---|---|
| Modify | `app/Http/Controllers/Api/PropertyController.php` |
| Modify | `app/Http/Controllers/Api/PreviewController.php` (property-write paths) |
| Modify | `tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php` (remove `PropertyController` + `PreviewController` from allowlist) |
| Create | `tests/Feature/Stores/PropertyHttpIntegrationTest.php` |

### Step 2.1: Audit the controller mutation sites

- [ ] **Read `app/Http/Controllers/Api/PropertyController.php` end-to-end.** Identify every site that calls `Property::create`, `Property::find(...)->save()`, `Property::find(...)->delete()`, `Property::where(...)->update()`. Typical sites:
  - `store()` — line 146 area: `Property::create($validated)` + the mortgage_* sub-create
  - `update()` — `$property->fill($validated)->save()` or `Property::where(...)->update()`
  - `destroy()` — soft-delete via `$property->delete()`
  - `restore()` if present

- [ ] **Read `app/Http/Controllers/Api/PreviewController.php`** for any property write paths (PreviewMode interceptor blocks real writes, but the controller may still construct mock responses).

### Step 2.2: Inject the normaliser + store into the controller

- [ ] **Add to `PropertyController` constructor:**

```php
use App\Services\Stores\IngestSource;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use App\Services\Stores\PropertyStore;

public function __construct(
    private readonly PropertyAgent $propertyAgent,           // existing
    private readonly PropertyCalculationService $propertyService,  // existing
    private readonly PropertyTaxService $propertyTaxService,       // existing
    private readonly PropertyStore $propertyStore,
    private readonly PropertyNormaliser $propertyNormaliser,
) {}
```

(Adjust to match the actual current constructor — preserve existing deps.)

### Step 2.3: Replace `store()` write site

- [ ] **Find the line `$property = Property::create($validated);` in `store()`.** Replace with:

```php
$canonical = $this->propertyNormaliser->fromForm($request->validated());
$property = $this->propertyStore->create($canonical, $request->user(), IngestSource::FORM);
```

- [ ] **Leave the mortgage_* sub-create as-is** — that's Pass 5's surface. Document with an inline comment:

```php
// Pass 4 routes Property creates through PropertyStore. The mortgage_*
// fields below remain a direct Mortgage::create — Pass 5 will route them
// through MortgageStore.
```

### Step 2.4: Replace `update()` write site

- [ ] **Find the update-write site.** Replace with:

```php
$canonical = $this->propertyNormaliser->fromForm($request->validated());
$property = $this->propertyStore->update($id, $canonical, $request->user(), IngestSource::FORM);
```

### Step 2.5: Replace `destroy()` write site

- [ ] **Find `$property->delete()` or `Property::destroy($id)`.** Replace with:

```php
$this->propertyStore->delete((int) $id, $request->user(), 'user_requested');
```

(If the controller has a `restore()` method, mirror with `$this->propertyStore->restore(...)`.)

### Step 2.6: Audit `PreviewController` for property writes

- [ ] **Search for `Property::` in `PreviewController`.** If any mutation site exists (e.g. seeding a preview-mode property), route it through `PropertyStore::create(..., IngestSource::SEEDER)`. If reads only, no change needed but the boundary entry can still come off in PR 5.

### Step 2.7: Remove `PropertyController` + `PreviewController` from the boundary allowlist

- [ ] **Edit `tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php`.** Delete the lines:

```php
// PR 2 removes: HTTP write paths
'App\Http\Controllers\Api\PropertyController',
'App\Http\Controllers\Api\PreviewController',
```

**Caveat:** `PropertyController` still references `Property::class` (e.g. polymorphic `Holding::where('holdable_type', Property::class)` if it exists; controller may have a `?Property` return type on a private helper). If after PR 2 wiring the boundary still flags `PropertyController`, the residual is a documented NON-QUERY reference — re-add the entry under the "Documented residual" section with a comment.

### Step 2.8: Write the HTTP integration test

- [ ] **Create `tests/Feature/Stores/PropertyHttpIntegrationTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('POST /api/properties persists a Property via PropertyStore', function () {
    $user = User::factory()->create(['tier' => 'tier1']);

    $response = $this->actingAs($user)->postJson('/api/properties', [
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'current_value' => 350000,
        'country' => 'United Kingdom',
    ]);

    $response->assertCreated();
    expect(Property::where('user_id', $user->id)->count())->toBe(1);
});

it('PUT /api/properties/{id} updates a Property via PropertyStore', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 350000]);

    $response = $this->actingAs($user)->putJson("/api/properties/{$property->id}", [
        'current_value' => 425000,
    ]);

    $response->assertOk();
    expect((string) $property->fresh()->current_value)->toBe('425000.00');
});

it('DELETE /api/properties/{id} soft-deletes via PropertyStore', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $property = Property::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/properties/{$property->id}");

    $response->assertOk();
    expect(Property::find($property->id))->toBeNull();
    expect(Property::withTrashed()->find($property->id))->not->toBeNull();
});

it('rejects updates from a non-owner', function () {
    $owner = User::factory()->create(['tier' => 'tier1']);
    $stranger = User::factory()->create(['tier' => 'tier1']);
    $property = Property::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($stranger)->putJson("/api/properties/{$property->id}", [
        'current_value' => 999999,
    ]);

    $response->assertStatus(404);  // ModelNotFoundException → 404 per HandleApiExceptions
});
```

- [ ] **Run, confirm 4 PASS:**

```bash
./vendor/bin/pest tests/Feature/Stores/PropertyHttpIntegrationTest.php
```

### Step 2.9: Run the boundary test + targeted Property tests

- [ ] **Run:**

```bash
./vendor/bin/pest tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php tests/Unit/Services/Stores/PropertyStoreTest.php tests/Unit/Services/Stores/PropertyStoreEventsTest.php tests/Feature/Stores/PropertyHttpIntegrationTest.php
```

Expected: all green. If the boundary test fails because of a residual `PropertyController` reference, re-add it as a documented residual.

### Step 2.10: Commit + open PR 2

- [ ] **Commit + open PR following Step 1.9's pattern.** PR body covers: route changes, boundary trim, HTTP integration test count. Pass 4 progress: 2/8.

- [ ] **Wait for CSJ admin-merge.**

---

## 7. PR 3 — Point Fyn AI write tools at PropertyStore

**PR title:** `refactor(properties): point Fyn AI write tools at PropertyStore (SP1 Pass 4 PR 3)`

**Branch:** `feat/property-store-pr3` off `dev`.

**Files:**

| Action | Path |
|---|---|
| Modify | `app/Agents/CoordinatingAgent.php` (handleCreateProperty + handleUpdateProperty + handleDeleteProperty) |
| Modify | `tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php` (remove `CoordinatingAgent` if possible; else move to documented residual) |
| Create | `tests/Feature/Stores/PropertyFynCaptureTest.php` |

### Step 3.1: Locate the Fyn property handlers

- [ ] **Read `app/Agents/CoordinatingAgent.php:2436+` (`handleCreateProperty`).** Identify the current `Property::create(...)` call site.

- [ ] **Search for `handleUpdateProperty`, `handleDeleteProperty`** if they exist. If they don't, the Fyn surface for property may be create-only today.

### Step 3.2: Route `handleCreateProperty` through PropertyStore

- [ ] **In `CoordinatingAgent::handleCreateProperty`, replace the `Property::create` site with:**

```php
$canonical = app(\App\Services\Stores\Normalisers\PropertyNormaliser::class)->fromFyn($input);

try {
    $property = app(\App\Services\Stores\PropertyStore::class)->create(
        $canonical,
        $user,
        \App\Services\Stores\IngestSource::FYN_AI
    );
} catch (\App\Services\Stores\Exceptions\TierLimitExceededException $e) {
    return $this->tierLimitResponse('property', $e->hardLimit);
} catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
    return $this->validationErrorResponse('property', $e->errors());
}
```

(Match the exact response shape `CoordinatingAgent` already uses — check what handleCreateSavingsAccount does for the canonical pattern.)

### Step 3.3: Route `handleUpdateProperty` / `handleDeleteProperty` if they exist

- [ ] **For update:** mirror with `$store->update($input['id'], $canonical, $user, IngestSource::FYN_AI)`.
- [ ] **For delete:** `$store->delete((int) $input['id'], $user, $input['reason'] ?? 'fyn_user_requested')`.

### Step 3.4: Trim the boundary allowlist

- [ ] **Remove `'App\Agents\CoordinatingAgent'` from the boundary test.** Run the boundary test. If it fails because `CoordinatingAgent` still references `Property::class` (e.g. in a duplicate-checker call `checkForDuplicate(Property::class, ...)` or in the entity-type-map), keep the entry but move it under "Documented residual NON-QUERY references" with a comment naming the residual.

### Step 3.5: Write the Fyn capture integration test

- [ ] **Create `tests/Feature/Stores/PropertyFynCaptureTest.php`:** a Pest test that constructs the same payload Fyn would emit (via `CoordinatingAgent::executeToolCall` or the equivalent dispatcher), invokes the property-create handler, and asserts a `Property` row landed with `IngestSource::FYN_AI` on the audit row. Mirror `tests/Feature/Stores/SavingsFynCaptureTest.php` structurally if it exists; otherwise follow this shape:

```php
<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
});

it('persists a Property via the Fyn capture path with IngestSource::FYN_AI', function () {
    $user = User::factory()->create(['tier' => 'tier1']);

    // Mirror what CoordinatingAgent::handleCreateProperty does after the
    // Fyn tool-call validator passes:
    $canonical = (new PropertyNormaliser)->fromFyn([
        'address' => '5 Acacia Avenue',
        'property_type' => 'main_residence',
        'value' => 350000,
        'ownership_type' => 'individual',
    ]);

    $property = app(PropertyStore::class)->create($canonical, $user, IngestSource::FYN_AI);

    expect(Property::where('user_id', $user->id)->count())->toBe(1);
    expect($property->address_line_1)->toBe('5 Acacia Avenue');
    expect((string) $property->current_value)->toBe('350000.00');

    $auditRow = AuditLog::where('model_type', Property::class)
        ->where('model_id', $property->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});
```

- [ ] **Run, confirm 1 PASS:**

```bash
./vendor/bin/pest tests/Feature/Stores/PropertyFynCaptureTest.php
```

### Step 3.6: Commit + open PR 3

- [ ] **Commit + open PR.** Pass 4 progress: 3/8.

---

## 8. PR 4 — Point upload + onboarding + seeders at PropertyStore

**PR title:** `refactor(properties): point upload + onboarding + seeders at PropertyStore (SP1 Pass 4 PR 4)`

**Branch:** `feat/property-store-pr4` off `dev`.

**Files:**

| Action | Path |
|---|---|
| Modify | `app/Services/Documents/DocumentProcessor.php` (Property upload branch) |
| Modify | `app/Services/Onboarding/OnboardingService.php` (Property create paths) |
| Modify | `app/Services/Onboarding/AssetCaptureEntityExtractor.php` (if it creates Property rows) |
| Modify | `database/seeders/PreviewUserSeeder.php` |
| Modify | `database/seeders/ChrisUserSeeder.php` |
| Modify | `database/seeders/LifecycleTestSeeder.php` (if it creates properties) |
| Modify | `app/Console/Commands/MigrateEstateToNetWorth.php` (if it persists properties) |
| Create | `tests/Feature/Stores/PropertyUploadIngestTest.php` |
| Modify | `tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php` (trim accordingly) |

### Step 4.1: Route DocumentProcessor's property upload through the store

- [x] **Read `app/Services/Documents/DocumentProcessor.php`** — find the Property-extraction branch (similar shape to Pension's at line ~391).

- [x] **Replace** the existing `Property::create(...)` with:

```php
$canonical = app(\App\Services\Stores\Normalisers\PropertyNormaliser::class)->fromUpload($accountData);
$property = app(\App\Services\Stores\PropertyStore::class)->create(
    $canonical,
    $user,
    \App\Services\Stores\IngestSource::UPLOAD
);
```

### Step 4.2: Route OnboardingService property writes through the store

- [x] **Search OnboardingService for `Property::create` and `->properties()->create`.** Route each through `PropertyStore::create(..., IngestSource::FORM)` (onboarding payload is form-shape).

- [x] **If `AssetCaptureEntityExtractor` creates Property rows, route those too.** (Confirmed read-only — no writes to route.)

### Step 4.3: Route persona seeders through the store

- [x] **In `PreviewUserSeeder` + `ChrisUserSeeder`,** locate every `Property::create([...])` call. Replace with:

```php
app(\App\Services\Stores\PropertyStore::class)->create(
    [...],  // same canonical payload
    $user,
    \App\Services\Stores\IngestSource::SEEDER
);
```

- [x] **Reseed locally** to confirm no failures:

```bash
php artisan db:seed --class=PreviewUserSeeder --force
php artisan db:seed --class=ChrisUserSeeder --force
```

### Step 4.4: Route MigrateEstateToNetWorth (if applicable)

- [x] **Audit `app/Console/Commands/MigrateEstateToNetWorth.php`.** Persists Property rows — routed through `PropertyStore::create(..., IngestSource::ADMIN)`. The `assets` table exists with 0 rows (migration already run historically), so this is dormant code, but the store routing is correct for any future re-run.

### Step 4.5: Write the upload-ingest test

- [x] **Create `tests/Feature/Stores/PropertyUploadIngestTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('persists a Property extraction via PropertyStore with IngestSource::UPLOAD', function () {
    $user = User::factory()->create(['tier' => 'tier1']);

    // Mirror what DocumentProcessor::confirmExcel does for a property extraction.
    $canonical = (new PropertyNormaliser)->fromUpload([
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'property_type' => 'main_residence',
        'current_value' => 350000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $property = app(PropertyStore::class)->create($canonical, $user, IngestSource::UPLOAD);

    expect(Property::where('user_id', $user->id)->count())->toBe(1);
    expect($property->address_line_1)->toBe('5 Acacia Avenue');
    expect((string) $property->current_value)->toBe('350000.00');
});
```

- [x] **Run, confirm 1 PASS:**

```bash
./vendor/bin/pest tests/Feature/Stores/PropertyUploadIngestTest.php
```

### Step 4.6: Trim boundary allowlist

- [x] **Remove from `$propertyConsumers`:** Removed `OnboardingService`, `ChrisUserSeeder`, `MigrateEstateToNetWorth` (fully clean). Kept `DocumentProcessor` (residual `Property::class` array-key in registerMappers), `AssetCaptureEntityExtractor` (read-only, deferred PR 5), `PreviewUserSeeder` (residual `Property::where()->delete()` in reset path), `LifecycleTestSeeder` (factory scaffolding). All with PR-numbered comments.

- [x] **Re-run the boundary test.** Green (1 assertion, PHP 8.5 `ReflectionMethod::setAccessible()` deprecation is vendor noise, not a test failure).

### Step 4.7: Commit + open PR 4

- [x] **Commit + open PR.** Pass 4 progress: 4/8.

---

## 9. PR 5 — Point read consumers at PropertyStore

**PR title:** `refactor(properties): point read consumers at PropertyStore (SP1 Pass 4 PR 5)`

**Branch:** `feat/property-store-pr5` off `dev`.

**Auto-split rule (from spec §15.1):** If the diff exceeds ~500 lines, split by consumer cluster (5a, 5b, 5c, …) following Pass 3's precedent. Likely clusters for Properties (~21 consumer files):

| Cluster | Files |
|---|---|
| **5a — Estate / IHT** | `EstateActionDefinitionService`, `EstateAssetAggregatorService`, `IHTCalculationService`, `LetterEstateValidationService`, `LetterToSpouseService` |
| **5b — NetWorth + Mobile** | `NetWorthService`, `MobileDashboardAggregator`, `CrossModuleAssetAggregator` |
| **5c — Coordination + Trust** | `HouseholdPlanningService`, `TrustAssetAggregatorService` |
| **5d — AI + Profile** | `AdvicePromptBuilder`, `DuplicateAcknowledgement`, `PersonalAccountsService`, `UserProfileService`, `ProfileCompletenessChecker` |
| **5e — Tax + Documents** | `IncomeDefinitionsService`, `DocumentTypeDetector`, `FieldMappers\PropertyMapper` (latter two retain class-name refs only) |

### Step 5.x.1: For each cluster, repeat this pattern

For each service file in the cluster:

- [ ] **Locate every `Property::where(...)` / `$user->properties()->...` / `Property::forUserOrJoint(...)` call.** Replace with:

```php
// Joint-aware reads
app(\App\Services\Stores\PropertyStore::class)->forUser($user);
app(\App\Services\Stores\PropertyStore::class)->forUserWithJointOwner($user);

// Single-row by id
app(\App\Services\Stores\PropertyStore::class)->find($id, $user);

// By property type (main_residence / secondary_residence / buy_to_let)
app(\App\Services\Stores\PropertyStore::class)->forUserByType($user, 'main_residence');

// Multi-user (household)
app(\App\Services\Stores\PropertyStore::class)->forUsers([$user->id, $spouse->id]);
```

- [ ] **Inject the store via the constructor** where the service has a constructor with DI; for static-call style services use `app(PropertyStore::class)`.

- [ ] **Run the impacted suite per cluster** to catch regressions early:

```bash
./vendor/bin/pest tests/Unit/Services/<cluster-dir>/
```

- [ ] **Remove the cluster's entries from the boundary allowlist.** Re-run boundary test. Any residual NON-QUERY reference (type hints, polymorphic class refs) moves to the "Documented residual" section with a comment.

### Step 5.x.2: Cluster commit pattern

```bash
git checkout -b feat/property-store-pr5a
# (cluster 5a changes)
git commit -m "refactor(properties): point Estate/IHT reads at PropertyStore (PR 5a)"
git push -u origin feat/property-store-pr5a
gh pr create --base dev --head feat/property-store-pr5a --title "..." --body "..."
# wait for merge; repeat for 5b, 5c, ...
```

(Per Pass 3 precedent, the sub-cluster PRs can also be bundled into one branch with multiple commits and a single PR, depending on CSJ's pace preference at the time. Default to one PR per cluster for review surface.)

### Step 5.x.3: Sub-cluster wrap-up

- [ ] **After every cluster lands**, run the broader suite:

```bash
./vendor/bin/pest tests/Feature/Stores/ tests/Unit/Services/Stores/ tests/Architecture/
```

Expected: green.

### Step 5.99: Pass 4 progress after PR 5

5/8. Boundary allowlist should be roughly half its PR 1 size by now.

---

## 10. PR 6 — Canonical derived columns + snapshot table

**PR title:** `feat(properties): canonical derived columns + snapshot table (SP1 Pass 4 PR 6)`

**Branch:** `feat/property-store-pr6` off `dev`.

**Files:**

| Action | Path |
|---|---|
| Create | `database/migrations/2026_05_NN_NNNNNN_add_derived_columns_to_properties.php` |
| Create | `database/migrations/2026_05_NN_NNNNNN_create_property_value_snapshots_table.php` |
| Create | `app/Models/PropertyValueSnapshot.php` |
| Create | `app/Services/Stores/Recalc/PropertyDerivedColumnCalculator.php` |
| Create | `app/Console/Commands/BackfillPropertyDerivedColumns.php` |
| Create | `tests/Feature/Stores/PropertyDerivedColumnsBackfillTest.php` |
| Create | `tests/Unit/Services/Stores/Recalc/PropertyDerivedColumnCalculatorTest.php` |
| Modify | `app/Models/Property.php` (fillable + casts for derived columns) |
| Modify | `app/Services/Stores/Snapshots/SnapshotPolicies.php` (add 2 property policies) |
| Modify | `app/Services/Stores/PropertyStore.php` (inject calculator + snapshot policies; wire recalc into create/update) |
| Modify | `tests/Unit/Services/Stores/PropertyStoreTest.php` (4 new cases for derived columns + snapshots) |
| Modify | `tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php` (add `BackfillPropertyDerivedColumns` + `PropertyValueSnapshot` + `PropertyDerivedColumnCalculator` to allowlist) |

### Step 6.1: Decide which derived columns to materialise

Per spec §4.3, materialise calcs as columns with `*_calculated_at` timestamps. For Property:

| Column | Formula | Notes |
|---|---|---|
| `current_value_gbp` | `current_value` (today Pass 4 stores everything in GBP) | Currency conversion deferred to a later sub-project pass — same as Pension. |
| `equity_gbp` | `current_value - outstanding_mortgage` (using the denormalised `outstanding_mortgage` column on `properties`) | The model's `getEquityAttribute()` currently calls `PropertyCalculationService::calculateEquity` which sums Mortgage rows. **For Pass 4, materialise based on the denormalised column** — Pass 5 will refactor to sum from MortgageStore.read on a transaction boundary. Document this in the calculator file. |
| `loan_to_value_pct` | `(outstanding_mortgage / current_value) * 100` | Null if `current_value <= 0`. |

(`monthly_costs_total_gbp` is a potential 4th column but requires summing 9 monthly_* fields; defer if marginal. Keep PR 6 small.)

### Step 6.2: Write the derived-column migration

- [x] **Create `database/migrations/2026_05_NN_NNNNNN_add_derived_columns_to_properties.php`:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('current_value_gbp', 15, 2)->nullable()->after('current_value');
            $table->timestamp('current_value_gbp_calculated_at')->nullable()->after('current_value_gbp');

            $table->decimal('equity_gbp', 15, 2)->nullable()->after('current_value_gbp_calculated_at');
            $table->timestamp('equity_gbp_calculated_at')->nullable()->after('equity_gbp');

            $table->decimal('loan_to_value_pct', 5, 2)->nullable()->after('equity_gbp_calculated_at');
            $table->timestamp('loan_to_value_pct_calculated_at')->nullable()->after('loan_to_value_pct');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'current_value_gbp', 'current_value_gbp_calculated_at',
                'equity_gbp', 'equity_gbp_calculated_at',
                'loan_to_value_pct', 'loan_to_value_pct_calculated_at',
            ]);
        });
    }
};
```

### Step 6.3: Write the snapshot table migration

- [x] **Create `database/migrations/2026_05_NN_NNNNNN_create_property_value_snapshots_table.php`:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('property_value_snapshots')) {
            return;
        }

        Schema::create('property_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('column_name', 64);
            $table->decimal('value', 15, 2);
            $table->string('currency', 3)->default('GBP');
            $table->decimal('value_gbp', 15, 2);
            $table->timestamp('taken_at');
            $table->string('trigger_reason', 16);  // 'create' | 'update'
            $table->string('ingest_source', 16);

            // MySQL 64-char identifier limit — use a short explicit index name
            // (`property_value_snapshots_property_id_column_name_taken_at_index`
            // would be 60 chars but bumping into the limit on extension fields
            // is a Pass-3 lesson).
            $table->index(['property_id', 'column_name', 'taken_at'], 'pvs_id_column_taken_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_value_snapshots');
    }
};
```

### Step 6.4: Create the PropertyValueSnapshot model

- [x] **Create `app/Models/PropertyValueSnapshot.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyValueSnapshot extends Model
{
    use HasFactory;

    protected $table = 'property_value_snapshots';  // explicit — defensive

    public $timestamps = false;

    protected $fillable = [
        'property_id', 'column_name', 'value', 'currency', 'value_gbp',
        'taken_at', 'trigger_reason', 'ingest_source',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'value_gbp' => 'decimal:2',
        'taken_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
```

### Step 6.5: Create the derived-column calculator

- [x] **Create `app/Services/Stores/Recalc/PropertyDerivedColumnCalculator.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Recalc;

use App\Models\Property;
use App\Models\User;

class PropertyDerivedColumnCalculator
{
    /**
     * Pass 4 stores all property values in GBP — currency conversion lands in a
     * later sub-project pass. Equity uses the denormalised `outstanding_mortgage`
     * column on `properties`; Pass 5 will reconcile this against MortgageStore
     * reads.
     *
     * @return array{
     *     current_value_gbp: ?float,
     *     equity_gbp: ?float,
     *     loan_to_value_pct: ?float
     * }
     */
    public function calculate(Property $property, User $user): array
    {
        $currentValue = $property->current_value !== null ? (float) $property->current_value : null;
        $mortgage = $property->outstanding_mortgage !== null ? (float) $property->outstanding_mortgage : 0.0;

        $equity = $currentValue !== null ? round($currentValue - $mortgage, 2) : null;

        $ltv = null;
        if ($currentValue !== null && $currentValue > 0) {
            $ltv = round($mortgage / $currentValue * 100, 2);
        }

        return [
            'current_value_gbp' => $currentValue !== null ? round($currentValue, 2) : null,
            'equity_gbp' => $equity,
            'loan_to_value_pct' => $ltv,
        ];
    }
}
```

### Step 6.6: Write the calculator test

- [x] **Create `tests/Unit/Services/Stores/Recalc/PropertyDerivedColumnCalculatorTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Recalc\PropertyDerivedColumnCalculator;

it('materialises current_value_gbp + equity_gbp + loan_to_value_pct', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 500000,
        'outstanding_mortgage' => 200000,
    ]);

    $derived = (new PropertyDerivedColumnCalculator)->calculate($property, $user);

    expect($derived['current_value_gbp'])->toBe(500000.00);
    expect($derived['equity_gbp'])->toBe(300000.00);
    expect($derived['loan_to_value_pct'])->toBe(40.00);
});

it('returns null current_value_gbp + equity_gbp when current_value is null', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => null,
        'outstanding_mortgage' => 100000,
    ]);

    $derived = (new PropertyDerivedColumnCalculator)->calculate($property, $user);

    expect($derived['current_value_gbp'])->toBeNull();
    expect($derived['equity_gbp'])->toBeNull();
    expect($derived['loan_to_value_pct'])->toBeNull();
});

it('treats null outstanding_mortgage as zero (equity = current_value)', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 350000,
        'outstanding_mortgage' => null,
    ]);

    $derived = (new PropertyDerivedColumnCalculator)->calculate($property, $user);

    expect($derived['equity_gbp'])->toBe(350000.00);
    expect($derived['loan_to_value_pct'])->toBe(0.00);
});

it('handles current_value = 0 by setting loan_to_value_pct = null', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 0,
        'outstanding_mortgage' => 100000,
    ]);

    $derived = (new PropertyDerivedColumnCalculator)->calculate($property, $user);

    expect($derived['loan_to_value_pct'])->toBeNull();
});
```

- [x] **Run, confirm 4 PASS:**

```bash
php artisan migrate --force
./vendor/bin/pest tests/Unit/Services/Stores/Recalc/PropertyDerivedColumnCalculatorTest.php
```

### Step 6.7: Add property to fillable + casts

- [x] **Edit `app/Models/Property.php`:** add to `$fillable`:

```php
'current_value_gbp',
'current_value_gbp_calculated_at',
'equity_gbp',
'equity_gbp_calculated_at',
'loan_to_value_pct',
'loan_to_value_pct_calculated_at',
```

And to `$casts`:

```php
'current_value_gbp' => 'decimal:2',
'current_value_gbp_calculated_at' => 'datetime',
'equity_gbp' => 'decimal:2',
'equity_gbp_calculated_at' => 'datetime',
'loan_to_value_pct' => 'decimal:2',
'loan_to_value_pct_calculated_at' => 'datetime',
```

### Step 6.8: Add snapshot policies

- [x] **Edit `app/Services/Stores/Snapshots/SnapshotPolicies.php`:** add two new policy methods:

```php
public function propertyValue(): SnapshotPolicy
{
    // Threshold: any change >£1,000 or >0.5% (whichever larger). Retain 365 days.
    return new SnapshotPolicy(
        thresholdAbsolute: 1000.00,
        thresholdRelativePct: 0.5,
        retentionDays: 365,
    );
}

public function propertyEquity(): SnapshotPolicy
{
    // Equity moves with both value and mortgage paydown; same threshold shape.
    return new SnapshotPolicy(
        thresholdAbsolute: 1000.00,
        thresholdRelativePct: 0.5,
        retentionDays: 365,
    );
}
```

(Adjust thresholds to match `SnapshotPolicy`'s actual constructor — copy from the Savings or Pension policy signature; the values are illustrative.)

### Step 6.9: Wire the calculator + snapshots into PropertyStore

- [x] **Edit `app/Services/Stores/PropertyStore.php`:** extend the constructor:

```php
public function __construct(
    private readonly PropertyNormaliser $normaliser,
    private readonly TierGate $tierGate,
    private readonly \App\Services\Stores\Recalc\PropertyDerivedColumnCalculator $derivedCalc,
    private readonly \App\Services\Stores\Snapshots\SnapshotPolicies $snapshotPolicies,
) {}
```

- [ ] **Add a `recalculateDerived` private method** (mirror PensionStore's `recalculateDcDerived`):

```php
private function recalculateDerived(Property $property, User $user, IngestSource $source, string $reason): void
{
    $derived = $this->derivedCalc->calculate($property, $user);
    $now = now();

    $oldValues = [
        'current_value_gbp' => $property->current_value_gbp !== null ? (float) $property->current_value_gbp : null,
        'equity_gbp' => $property->equity_gbp !== null ? (float) $property->equity_gbp : null,
    ];

    $property->fill([
        'current_value_gbp' => $derived['current_value_gbp'],
        'current_value_gbp_calculated_at' => $now,
        'equity_gbp' => $derived['equity_gbp'],
        'equity_gbp_calculated_at' => $now,
        'loan_to_value_pct' => $derived['loan_to_value_pct'],
        'loan_to_value_pct_calculated_at' => $now,
    ])->save();

    $policies = [
        'current_value_gbp' => $this->snapshotPolicies->propertyValue(),
        'equity_gbp' => $this->snapshotPolicies->propertyEquity(),
    ];

    foreach ($policies as $column => $policy) {
        if ($derived[$column] === null) {
            continue;
        }
        if (! $policy->shouldSnapshot($oldValues[$column], $derived[$column])) {
            continue;
        }

        \App\Models\PropertyValueSnapshot::create([
            'property_id' => $property->id,
            'column_name' => $column,
            'value' => $derived[$column],
            'currency' => 'GBP',
            'value_gbp' => $derived[$column],
            'taken_at' => $now,
            'trigger_reason' => $reason,
            'ingest_source' => $source->value,
        ]);
    }
}
```

- [x] **Invoke `recalculateDerived` inside the create + update transactions:**

In `create()`:

```php
$property = AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($attributes, $user, $source) {
    $property = Property::create($attributes);
    $this->recalculateDerived($property, $user, $source, 'create');
    return $property;
}));
```

In `update()` (return tuple):

```php
$result = AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($property, $data, $user, $source) {
    $property->fill($data);
    $dirty = $property->getDirty();
    $property->save();
    $fresh = $property->fresh();
    $this->recalculateDerived($fresh, $user, $source, 'update');

    return ['fresh' => $fresh, 'dirty' => $dirty];
}));
```

### Step 6.10: Add store-recalc test cases

- [x] **Append to `tests/Unit/Services/Stores/PropertyStoreTest.php`:**

```php
it('create materialises current_value_gbp + writes initial snapshot', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 350000,
        'outstanding_mortgage' => 100000,
    ], $user, IngestSource::FORM);

    expect((string) $property->fresh()->current_value_gbp)->toBe('350000.00');
    expect((string) $property->fresh()->equity_gbp)->toBe('250000.00');
    expect((string) $property->fresh()->loan_to_value_pct)->toBe('28.57');

    expect(\App\Models\PropertyValueSnapshot::where('property_id', $property->id)->count())
        ->toBeGreaterThanOrEqual(2);  // current_value_gbp + equity_gbp
});

it('update fires snapshot only when policy threshold exceeded', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 350000,
        'outstanding_mortgage' => 100000,
    ], $user, IngestSource::FORM);

    $initialSnapshots = \App\Models\PropertyValueSnapshot::where('property_id', $property->id)->count();

    // Small change: shouldn't fire policy.
    $store->update($property->id, ['current_value' => 350500], $user, IngestSource::FORM);
    expect(\App\Models\PropertyValueSnapshot::where('property_id', $property->id)->count())
        ->toBe($initialSnapshots);

    // Big change: should fire.
    $store->update($property->id, ['current_value' => 425000], $user, IngestSource::FORM);
    expect(\App\Models\PropertyValueSnapshot::where('property_id', $property->id)->count())
        ->toBeGreaterThan($initialSnapshots);
});
```

### Step 6.11: Write the backfill console command

- [x] **Create `app/Console/Commands/BackfillPropertyDerivedColumns.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Recalc\PropertyDerivedColumnCalculator;
use Illuminate\Console\Command;

class BackfillPropertyDerivedColumns extends Command
{
    protected $signature = 'properties:backfill-derived-columns';

    protected $description = 'Backfill canonical derived columns (current_value_gbp, equity_gbp, loan_to_value_pct) on existing properties.';

    public function handle(PropertyDerivedColumnCalculator $calc): int
    {
        $count = 0;
        Property::chunkById(200, function ($chunk) use ($calc, &$count) {
            foreach ($chunk as $property) {
                $user = User::find($property->user_id);
                if ($user === null) {
                    continue;
                }
                $derived = $calc->calculate($property, $user);
                $property->forceFill([
                    'current_value_gbp' => $derived['current_value_gbp'],
                    'current_value_gbp_calculated_at' => now(),
                    'equity_gbp' => $derived['equity_gbp'],
                    'equity_gbp_calculated_at' => now(),
                    'loan_to_value_pct' => $derived['loan_to_value_pct'],
                    'loan_to_value_pct_calculated_at' => now(),
                ])->saveQuietly();
                $count++;
            }
        });

        $this->info("Backfilled {$count} properties.");

        return self::SUCCESS;
    }
}
```

### Step 6.12: Write the backfill feature test

- [x] **Create `tests/Feature/Stores/PropertyDerivedColumnsBackfillTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('properties:backfill-derived populates derived columns on legacy rows', function () {
    $user = User::factory()->create();

    // Insert via factory (bypasses store) with derived columns null.
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 500000,
        'outstanding_mortgage' => 150000,
        'current_value_gbp' => null,
        'equity_gbp' => null,
        'loan_to_value_pct' => null,
    ]);

    $this->artisan('properties:backfill-derived-columns')
        ->expectsOutput('Backfilled 1 properties.')
        ->assertExitCode(0);

    $fresh = $property->fresh();
    expect((string) $fresh->current_value_gbp)->toBe('500000.00');
    expect((string) $fresh->equity_gbp)->toBe('350000.00');
    expect((string) $fresh->loan_to_value_pct)->toBe('30.00');
});
```

- [x] **Run, confirm PASS:**

```bash
./vendor/bin/pest tests/Feature/Stores/PropertyDerivedColumnsBackfillTest.php
```

### Step 6.13: Update the boundary allowlist

- [x] **Add to the canonical write/read set:**
  - `App\Services\Stores\Recalc\PropertyDerivedColumnCalculator`
  - `App\Console\Commands\BackfillPropertyDerivedColumns`
- [x] **Add to permanent allowlist:**
  - `App\Models\PropertyValueSnapshot`

### Step 6.14: Run + commit + open PR 6

- [x] **Run targeted Property suite:**

```bash
./vendor/bin/pest tests/Feature/Stores/Property*Test.php tests/Unit/Services/Stores/Property*Test.php tests/Unit/Services/Stores/Recalc/Property*Test.php tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php
```

- [ ] **Commit + open PR 6.** Pass 4 progress: 6/8.

---

## 11. PR 7 — Tier-cap test

**PR title:** `feat(properties): tier-cap test for property (SP1 Pass 4 PR 7)`

**Branch:** `feat/property-store-pr7` off `dev`.

**Files:**

| Action | Path |
|---|---|
| Create | `tests/Feature/Stores/PropertyTierCapTest.php` |

The enforcement seam was wired in PR 1 (`enforceTierCap` called inside `create()`); the cap value was seeded in PR 1 (`TierConfigurationSeeder` got `'property' => 3` for free). This PR locks the contract with a test.

### Step 7.1: Write the failing tier-cap test

- [ ] **Create `tests/Feature/Stores/PropertyTierCapTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\TierGate;
use App\Services\Tiers\DbTierGate;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('refuses to create a 4th property for a free-tier user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PropertyStore::class);

    Property::factory(3)->create(['user_id' => $user->id]);

    expect(fn () => $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 100000,
    ], $user, IngestSource::FORM))->toThrow(TierLimitExceededException::class);
});

it('carries the entity key, current count and hard limit on the thrown exception', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PropertyStore::class);

    Property::factory(3)->create(['user_id' => $user->id]);

    try {
        $store->create([
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'current_value' => 100000,
        ], $user, IngestSource::FORM);
        $this->fail('Expected TierLimitExceededException was not thrown');
    } catch (TierLimitExceededException $e) {
        expect($e->entityKey)->toBe(PropertyStore::ENTITY_KEY);
        expect($e->currentCount)->toBe(3);
        expect($e->hardLimit)->toBe(3);
    }
});

it('allows the first three properties for a free-tier user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PropertyStore::class);

    foreach (range(1, 3) as $i) {
        $store->create([
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'current_value' => 100000 + ($i * 10000),
            'address_line_1' => "{$i} Test Street",
        ], $user, IngestSource::FORM);
    }

    expect(Property::where('user_id', $user->id)->count())->toBe(3);
});

it('does NOT enforce the cap for a tier1 user (unlimited)', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    Property::factory(3)->create(['user_id' => $user->id]);

    $store->create([
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'current_value' => 200000,
    ], $user, IngestSource::FORM);

    expect(Property::where('user_id', $user->id)->count())->toBe(4);
});

it('enforces the cap under the global DbTierGate binding', function () {
    expect(app(TierGate::class))->toBeInstanceOf(DbTierGate::class);

    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PropertyStore::class);

    Property::factory(3)->create(['user_id' => $user->id]);

    expect(fn () => $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 100000,
    ], $user, IngestSource::FORM))->toThrow(TierLimitExceededException::class);
});
```

- [ ] **Run, confirm 5 PASS:**

```bash
./vendor/bin/pest tests/Feature/Stores/PropertyTierCapTest.php
```

### Step 7.2: Commit + open PR 7

- [ ] **Commit + open PR.** Pass 4 progress: 7/8.

---

## 12. PR 8 — Lock-down + parity test + Store.md (SP1 §16 close-out IN-LINE)

**PR title:** `lock-down(properties): allowlist LOCKED + audit ingest_source + parity + Store.md (SP1 Pass 4 PR 8)`

**Branch:** `feat/property-store-pr8` off `dev`.

**Files:**

| Action | Path |
|---|---|
| Modify | `tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php` (LOCKED framing) |
| Create | `tests/Feature/Stores/PropertyAuditIngestSourceTest.php` |
| Create | `tests/Feature/Stores/PropertyThreeIngestParityTest.php` |
| Create | `app/Services/Stores/PropertyStore.md` |

(Note: the `AuditLog::withContext` wraps were added in PR 1 from the start — no store changes here.)

### Step 8.1: Reword the boundary test to LOCKED framing

- [ ] **Rewrite the boundary test docblock + sections** to mirror `SavingsStoreBoundaryTest`'s structure: Canonical / §14.2 permanent / Documented residual / Out-of-sub-project-1-scope. Drop all "PR Nx removes" transition comments.

(The exact content depends on the residual set surviving PR 2–6. Follow the Savings template structurally; reuse comment justifications from the per-PR documentation residual additions.)

### Step 8.2: Write the audit-ingest-source test

- [ ] **Create `tests/Feature/Stores/PropertyAuditIngestSourceTest.php`** — mirror `tests/Feature/Stores/SavingsAuditIngestSourceTest.php`. 5 cases: create-fyn / create-form / create-upload / update / context-cleared. Each asserts `AuditLog::where('model_type', Property::class)->...->metadata['ingest_source']` matches the source value.

(See `tests/Feature/Stores/PensionAuditIngestSourceTest.php` for the most recent precedent.)

### Step 8.3: Write the three-ingest parity test

- [ ] **Create `tests/Feature/Stores/PropertyThreeIngestParityTest.php`:** mirror `tests/Feature/Stores/SavingsThreeIngestParityTest.php` + `PensionThreeIngestParityTest.php`. Two cases:

**Case 1 — Individual property parity:**

```php
it('persists field-identical canonical rows for the same individual property via form, fyn and upload', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'tier' => 'tier1']);
    $normaliser = new PropertyNormaliser;
    $store = app(PropertyStore::class);

    // Form ingest
    $form = $store->create($normaliser->fromForm([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'country' => 'United Kingdom',
        'current_value' => 350000,
        'outstanding_mortgage' => 100000,
    ]), $user, IngestSource::FORM);

    // Fyn ingest — uses shorthand vocabulary
    $fyn = $store->create($normaliser->fromFyn([
        'address' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'country' => 'United Kingdom',
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'value' => 350000,
        'outstanding_mortgage' => 100000,
    ]), $user, IngestSource::FYN_AI);

    // Upload ingest
    $upload = $store->create($normaliser->fromUpload([
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'country' => 'United Kingdom',
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 350000,
        'outstanding_mortgage' => 100000,
    ]), $user, IngestSource::UPLOAD);

    $snap = fn (Property $p) => [
        'property_type' => $p->property_type,
        'ownership_type' => $p->ownership_type,
        'ownership_percentage' => (string) $p->ownership_percentage,
        'address_line_1' => $p->address_line_1,
        'city' => $p->city,
        'postcode' => $p->postcode,
        'country' => $p->country,
        'current_value' => (string) $p->current_value,
        'current_value_gbp' => (string) $p->current_value_gbp,
        'outstanding_mortgage' => (string) $p->outstanding_mortgage,
        'equity_gbp' => (string) $p->equity_gbp,
        'loan_to_value_pct' => (string) $p->loan_to_value_pct,
    ];

    $expected = [
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => '100.00',
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'country' => 'United Kingdom',
        'current_value' => '350000.00',
        'current_value_gbp' => '350000.00',
        'outstanding_mortgage' => '100000.00',
        'equity_gbp' => '250000.00',
        'loan_to_value_pct' => '28.57',
    ];

    expect($snap($form->fresh()))->toBe($expected);
    expect($snap($fyn->fresh()))->toBe($expected);
    expect($snap($upload->fresh()))->toBe($expected);
});
```

**Case 2 — `tenants_in_common` parity (the property-only invariant):**

```php
it('persists field-identical canonical rows for a tenants_in_common property via form, fyn and upload', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'tier' => 'tier1']);
    $normaliser = new PropertyNormaliser;
    $store = app(PropertyStore::class);

    $base = [
        'property_type' => 'main_residence',
        'ownership_type' => 'tenants_in_common',
        'joint_ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 60,
        'joint_owner_name' => 'Jane Doe',
        'address_line_1' => '10 Tenancy Road',
        'city' => 'Manchester',
        'postcode' => 'M1 1AA',
        'country' => 'United Kingdom',
        'current_value' => 500000,
    ];

    $form = $store->create($normaliser->fromForm($base), $user, IngestSource::FORM);
    $fyn = $store->create($normaliser->fromFyn($base), $user, IngestSource::FYN_AI);
    $upload = $store->create($normaliser->fromUpload($base), $user, IngestSource::UPLOAD);

    foreach ([$form, $fyn, $upload] as $p) {
        $fresh = $p->fresh();
        expect($fresh->ownership_type)->toBe('tenants_in_common');
        expect((string) $fresh->ownership_percentage)->toBe('60.00');
        expect($fresh->joint_owner_name)->toBe('Jane Doe');
        expect((string) $fresh->current_value)->toBe('500000.00');
        expect((string) $fresh->current_value_gbp)->toBe('500000.00');
    }
});
```

### Step 8.4: Write the Store.md

- [ ] **Create `app/Services/Stores/PropertyStore.md`** — mirror `app/Services/Stores/PensionStore.md` (the most recent precedent). Document:
  - Entity key / table / model / base / normaliser / calculator / snapshot policies
  - Boundary allowlist (classified)
  - Allowed ingest sources
  - Public API tables (writes + reads)
  - Per-entity quirks (joint-ownership invariant; `tenants_in_common` is property-only; ownership_percentage default; `outstanding_mortgage` is denormalised — Pass 5 reconciles; mortgage_* fields handled in controller; GBP-only valuations Pass 4)
  - 4 events
  - Validation policy
  - Audit / security
  - Currency normalisation
  - Migration history (PR 1 → PR 8)
  - Acceptance criteria mapping

### Step 8.5: Final sweep

- [ ] **Run:**

```bash
./vendor/bin/pest tests/Feature/Stores/Property*Test.php tests/Unit/Services/Stores/Property*Test.php tests/Unit/Services/Stores/Recalc/Property*Test.php tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php
./vendor/bin/pest
```

Expected: full Pest green.

### Step 8.6: Commit + open PR 8

- [ ] **Commit + open PR.** Pass 4 progress: **8/8 — COMPLETE.**

---

## 13. Acceptance criteria mapping (spec §16)

| Gate | Met by |
|---|---|
| §16.1.1 Single write path (Pest boundary) | PR 8 (locked) |
| §16.1.2 Three-ingest parity | PR 8 (`PropertyThreeIngestParityTest`) |
| §16.1.3 Audit completeness — ingest_source on every write | PR 1 (wraps from the start) + PR 8 (`PropertyAuditIngestSourceTest`) |
| §16.1.4 Derived-column correctness | PR 6 (calculator + tests) |
| §16.1.5 Snapshot policy applied | PR 6 (per-column policies + tests) |
| §16.1.6 Currency round-trip | n/a (GBP-only in Pass 4 — deferred to later pass; mirrors Pension) |
| §16.1.7 Tier-cap enforcement | PR 1 (seam) + PR 7 (test) |
| §16.1.8 Browser-tested via Playwright | csjones smoke after PR 8 merge (CSJ-driven) |
| §16.2.1 All entity stores | 7/19 after Pass 4 (Savings + Pensions + 4 ref-data + Properties) |
| §16.2.2 Boundary tests green | PropertyStoreBoundaryTest LOCKED in PR 8 |
| §16.2.5 Per-entity Store.md | PR 8 |

---

## 14. Risks + mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| `Property::class` polymorphic-discriminator references (Holding-style `holdable_type`) prevent dropping `PropertyController` / `CoordinatingAgent` from allowlist | High | Low | Re-categorise as documented residual; the canonical contract (no queries outside the store) still holds. |
| `outstanding_mortgage` denormalised column drifts from `mortgages.outstanding_balance` sum, making `equity_gbp` wrong | Medium | Medium | Document the drift risk in `PropertyStore.md`; flag Pass 5 to reconcile via MortgageStore reads. Add a note to Pass 5 plan. |
| Read-consumer migration (PR 5) bigger than expected (~21 files) and ships as one diff | Medium | Medium | Auto-split by cluster per spec §15.1 (Estate / NetWorth / Coordination / AI / Tax). |
| Tier-cap of 3 free-tier properties is too tight for users with main + holiday + BTL | Low | Low | The cap value is in `TierConfigurationSeeder` and can be raised by CSJ via the admin Tier Settings UI without code change. |
| `OnboardingService` re-uses Property-create code paths across capture flows, complicating PR 4 | Medium | Medium | Audit OnboardingService end-to-end in PR 4 step 4.2; route every Property write through `PropertyStore`. |
| Mortgage tests fail because Property reads return store-shaped collections that Mortgage code doesn't expect | Medium | Medium | The store returns Eloquent `Collection<Property>` — identical to current `forUserOrJoint()->get()` output. No shape change. If a test fails, the diagnosis is real coupling, not a parity defect. |
| Vue/HTTP API shape changes inadvertently | Low | High | The store returns Eloquent models; controllers transform via existing Resource collections. No FE changes needed; verify via PropertyHttpIntegrationTest (PR 2). |

---

## 15. Open questions — resolve in PR 0 / PR 1

- [ ] **Q1: `monthly_costs_total_gbp` derived column — include in PR 6 or defer?** Defer; PR 6 sticks to value/equity/LTV. Marginal benefit.
- [ ] **Q2: Does `MigrateEstateToNetWorth` console command still need to run?** Check git history — if it was a one-shot historical migration that's already run, leave its `Property::*` references untouched and mark as documented residual (out-of-Pass-4-scope console command).
- [ ] **Q3: Add `PropertyResource` in PR 8?** Optional. Pass 4 doesn't require it (spec §15.4 lists it as a per-entity deliverable only "where applicable"; Property currently returns raw models). Skip unless it surfaces during PR 5 read-consumer work.
- [ ] **Q4: `LifecycleTestSeeder` Property usage** — audit in PR 4 step 4.3.

---

## 16. Pass 4 progress tracker

- [ ] PR 1 — facade + boundary + normaliser + events + tier-cap seed (this plan)
- [ ] PR 2 — HTTP form requests
- [ ] PR 3 — Fyn AI write tools
- [ ] PR 4 — upload + onboarding + seeders
- [ ] PR 5 — read consumers (sub-cluster as needed)
- [ ] PR 6 — derived columns + snapshot table + backfill command
- [ ] PR 7 — tier-cap test
- [ ] PR 8 — lock-down + parity + audit ingest_source test + Store.md (§16 close-out IN-LINE — no after-the-fact PR like Pass 3 needed)

On full completion: Sub-Project 1 progress → **7 of 19 entity stores shipped**.
