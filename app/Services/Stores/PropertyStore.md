# PropertyStore

Canonical write boundary for `App\Models\Property`. Every mutation of the `properties` table flows through this store, and every joint-aware read by Estate, Tax, NetWorth, Coordination, AI, Goals, Plans, Documents, Onboarding, Mobile, and User-profile consumers funnels through the store's typed read methods.

**Entity key:** `property` (constant `PropertyStore::ENTITY_KEY`)
**Table:** `properties`
**Model:** `App\Models\Property`
**Base:** standalone (user-data store; **does not** extend `ReferenceDataStore`)
**Normaliser:** `App\Services\Stores\Normalisers\PropertyNormaliser`
**Derived calculator:** `App\Services\Stores\Recalc\PropertyDerivedColumnCalculator`
**Snapshot policies:** `App\Services\Stores\Snapshots\SnapshotPolicies` — `propertyValue()`, `propertyEquity()` (PR 6)
**Snapshot table:** `property_value_snapshots` (PR 6)
**Backfill command:** `properties:backfill-derived-columns` — `App\Console\Commands\BackfillPropertyDerivedColumns` (PR 6)

## Boundary

Lock test: `tests/Architecture/StoreBoundary/PropertyStoreBoundaryTest.php` (SP1 Pass 4 PR 8 — locked).

Allowed direct consumers of `App\Models\Property`:

### Canonical write/read set
- `App\Services\Stores\PropertyStore` — the store itself
- `App\Services\Stores\Normalisers\PropertyNormaliser` — folds form / Fyn AI / upload vocabularies onto the canonical shape
- `App\Services\Stores\Recalc\PropertyDerivedColumnCalculator` — invoked only by `PropertyStore::recalculateDerived()`; conceptually an internal of the store (no queries, no mutations)
- `App\Console\Commands\BackfillPropertyDerivedColumns` — one-off migration-style backfill via `chunkById` + `forceFill`/`saveQuietly`; §14.2 console-command category

### Spec §14.2 permanent allowlist
- `App\Observers\PropertyRiskObserver` — risk recalculation side effects
- `Database\Factories\PropertyFactory` — test fixtures
- `Database\Factories\MortgageFactory` — sibling factory; sets `property_id` FK in test fixtures (not a query/mutation site)
- `App\Models\\` — self-references + relationship definitions in sibling models
- `App\Models\PropertyValueSnapshot` — snapshot model, written exclusively by `PropertyStore::recalculateDerived()` (PR 6)
- `App\Events\Property\PropertyCreated` / `PropertyUpdated` / `PropertyDeleted` / `PropertyRestored` — typed event constructor params; dispatched only from the store
- `App\Providers\EventServiceProvider` — listener registration (event wiring, §14.2)
- `App\Http\Resources\PropertyResource` — API resource (permanent type hint; no query)
- `App\Console\Commands\{EncryptExistingData,ResetPreviewData}` — controlled console commands
- `Database\Seeders\PreviewUserSeeder` — routes all `Property` creates through `PropertyStore::create` (`IngestSource::SEEDER`); `resetPersonaData()` retains a bulk-delete path (outside the ingest boundary)
- `Database\Seeders\LifecycleTestSeeder` — uses `Property` factory for test scaffolding; factory writes are §14.2-permitted

### Documented residual NON-QUERY references
Retain a `Property` reference that is NOT a statically-resolvable query/mutation (class-name args, type hints, polymorphic dispatch). The canonical contract still holds — none of these issue a `Property` query that bypasses the store:

- `App\Agents\CoordinatingAgent` — `Property::class` as a duplicate-checker argument and entity-type-map value; all property write tools and read calls route through `PropertyStore`
- `App\Services\Documents\DocumentTypeDetector` — `Property::class` as a dispatch key in the upload field-mapper registry; non-query class-name reference only
- `App\Services\Documents\FieldMappers\PropertyMapper` — `Property::class` as a field-mapper registry key; non-query class-name reference only
- `App\Services\Coordination\HouseholdPlanningService` — `:739` polymorphic joint-asset detection loop iterates over `Property::class` alongside `SavingsAccount` / `InvestmentAccount` / `CashAccount` / `Chattel`; routing one model out breaks loop symmetry. All three direct-query sites (`:273`/`:394`/`:922`) are migrated to `PropertyStore`; the polymorphic sweep is deferred to when all five entity stores exist (`JointAssetFinder` service refactor)
- `App\Services\Documents\DocumentProcessor` — `Property::class` used as an array key in `registerMappers()`; the write path (`confirmExcel`) routes through `PropertyStore::create` (`IngestSource::UPLOAD`)
- `App\Services\Onboarding\AssetCaptureEntityExtractor` — confirmed read-only; all reads migrated to `PropertyStore` in PR 5d
- `App\Http\Controllers\Api\PropertyController` — direct Property reads retained from PR 2 (`show`/`calculateCGT`/`calculateRentalIncomeTax`/`index`; `update`+`destroy` pre-fetch; `syncUserRentalIncome`); all write paths (POST/PUT/DELETE) route through `PropertyStore`
- `App\Http\Controllers\Api\PreviewController` — `seedMortgages` `Property::where` lookup to populate the mortgage FK; all property create paths route through `PropertyStore`
- `App\Services\Property\PropertyCalculationService`, `PropertyTaxService`, `PropertyService` — accept `Property` instances as parameters; do not issue queries that bypass the store

### Out-of-sub-project-1-scope read / infra references
Never in the Pass 4 read-consumer migration scope — sibling models, MortgageService (Pass 5 territory), MortgageController (Pass 5 territory):

- `App\Http\Controllers\Api\MortgageController`
- `App\Models\Household`, `App\Models\Mortgage`
- `App\Models\User` — property relationship methods only
- `App\Services\Property\MortgageService` — uses `Property` for `property_id` FK relationship lookups; Pass 5 territory (Liabilities store)

Adding a new direct-model consumer requires either routing through the store (preferred) or adding to this allowlist with written justification.

## Allowed ingest sources

| Source | Meaning |
|---|---|
| `IngestSource::FORM` | HTTP form-validated via `StorePropertyRequest` / `UpdatePropertyRequest` |
| `IngestSource::FYN_AI` | Fyn tool call (`CoordinatingAgent::handleCreateProperty` / `handleUpdateProperty`) |
| `IngestSource::UPLOAD` | Document extraction via `DocumentProcessor` + `PropertyMapper` |
| `IngestSource::SEEDER` | Preview / lifecycle persona fixture seeders only (`PreviewUserSeeder`, `LifecycleTestSeeder`) |
| `IngestSource::ADMIN` | Reserved; not currently used for properties |

The active `IngestSource` is attached to every audit row via `AuditLog::withContext(['ingest_source' => $source->value], …)` (spec §8.1, wired from PR 1 and locked in Pass 4 PR 8).

## Public API

### Writes

| Method | Purpose |
|---|---|
| `create(array $data, User $user, IngestSource $source): Property` | Validate canonical shape via `validateCanonical()`, check `TierGate::canCreate('property', …)` against user's property count, persist, recalculate derived columns (`current_value_gbp`, `equity_gbp`, `loan_to_value_pct`) + write value snapshots per policy, emit `PropertyCreated`. Wraps the transaction + snapshot writes in `AuditLog::withContext`. |
| `update(int $id, array $data, User $user, IngestSource $source): Property` | Primary-owner-only mutation (`user_id = $user->id`). Fill before `getDirty()` so the changes diff is captured, save, recalculate derived columns + snapshots, emit `PropertyUpdated` with `$changes` diff. Wraps in `AuditLog::withContext`. |
| `updateOrCreate(array $match, array $data, User $user, IngestSource $source): Property` | Find by `$match` for `$user`; route to `update` if found, otherwise to `create` with merged data. |
| `delete(int $id, User $user, string $reason): void` | Primary-owner-only soft delete (`SoftDeletes`). Emits `PropertyDeleted` with `$reason`. |
| `restore(int $id, User $user): Property` | Restore a soft-deleted row. Primary-owner-only. Emits `PropertyRestored`. |

### Reads

| Method | Returns |
|---|---|
| `find(int $id, User $user): ?Property` | By id, **joint-aware** — scoped to `user_id = $user->id OR joint_owner_id = $user->id`. |
| `forUser(User $user): Collection` | All properties where the user is primary or joint owner. Uses `Property::forUserOrJoint($user->id)` scope. Joint-aware. |
| `forUserWithJointOwner(User $user): Collection` | Same as `forUser`, with `jointOwner` relation eager-loaded. |
| `forUsers(array $userIds): Collection` | Multi-user joint-aware read for household / multi-user contexts. Returns empty `Collection` for empty input. |
| `findMany(array $ids, User $user): Collection` | User-scoped joint-aware id-based read. Returns empty `Collection` for unknown ids or ids belonging to other users — prevents cross-user leakage when callers pass externally-supplied id arrays. |
| `forUserByType(User $user, string $propertyType): Collection` | Joint-aware read filtered by `property_type` (e.g. `'buy_to_let'`). Used by income services (rental income aggregation). |
| `forTrust(int $trustId): Collection` | Trust-scoped read filtered by `trust_id` FK. Used by `TrustAssetAggregatorService` and IHT trust-asset aggregation. NOT joint-aware — trust assets are scoped to the trust, not the individual user. |

### What the store does NOT expose

Per spec §5.5, the store does **not** expose:
- Raw model classes — every read returns Eloquent collections / models scoped to `$user` (or `$trustId`); consumers never call `Property::query()` directly
- Raw query builders — no `whereSomething()` chain exits the store
- Mutation methods that bypass derived-column recalc or event emission
- Primary-only reads via `forUser` — **`forUser` is JOINT-AWARE**; callers that need only the user's own records must chain `.where('user_id', $user->id)` after the returned collection, or use `Property::query()->where('user_id', $user->id)` — but the correct pattern is to use the store and filter the result. See `PropertyReadConsumerParityTest` as the locked contract.
- Soft-delete bypass (`forceDelete`, `withTrashed`-as-default) — `restore()` is the only entry point that sees trashed rows

## Per-entity quirks

1. **Joint ownership uses a SINGLE record.** `joint_owner_id` identifies the second owner, `ownership_percentage` is the primary owner's share; the spouse's share is `(100 - ownership_percentage)`. There is no second `Property` row for the joint owner. Every read funnels through `forUserOrJoint` to surface properties where the user is either `user_id` or `joint_owner_id`. (CLAUDE.md Rule #7.)

2. **`tenants_in_common` is property-only.** `validateCanonical()` explicitly allows `tenants_in_common` as an `ownership_type`. No other entity store (Savings, Investment, Estate, Goals) permits this value. The `SavingsStore::validateCanonical()` rule deliberately excludes it. (CLAUDE.md Rule #5; locked by `PropertyThreeIngestParityTest` case 2.)

3. **`ownership_percentage` defaults to 100 for `individual` / `trust`.** Both `fromForm` and `fromFyn` apply this default. `fromUpload` applies it for `individual` ownership. For `joint` / `tenants_in_common` the percentage is required and must be supplied by the ingest source.

4. **`outstanding_mortgage` is DENORMALISED.** `equity_gbp = current_value_gbp - outstanding_mortgage` is calculated from this column, not from the live sum of `mortgages.outstanding_balance`. If a mortgage payment is made and the mortgage record is updated but the property's `outstanding_mortgage` is not, `equity_gbp` will drift. Pass 5 (MortgageStore) is the reconciliation point — the store will be updated to recalculate `outstanding_mortgage` from the mortgage records at that stage.

5. **`mortgage_*` fields are handled in the controller, NOT the store.** `PropertyNormaliser::fromForm()` strips `mortgage_*` keys from the payload. Mortgage creation/update is handled directly by `PropertyController` today; it moves to `MortgageStore` in Pass 5.

6. **`PropertyStore::forUser` is JOINT-AWARE.** It returns rows where `user_id = $user->id OR joint_owner_id = $user->id`. Consumers that only need properties the user personally owns must filter the result. `PropertyReadConsumerParityTest` is the locked contract for all read consumers.

7. **All valuations GBP-only in Pass 4.** `current_value_gbp` equals `current_value`; currency conversion is deferred to a later sub-project pass. The calculator comment documents: `$currentGbp = (float) $property->current_value`. Single source of truth for FX rates when this lands is `CurrencyRateStore::latestFor()`.

8. **Derived columns are materialised inside the write transaction.** `recalculateDerived` runs inside `DB::transaction(...)` in every write path so a snapshot-write failure rolls back the whole create.

9. **Per-column snapshot policy.** `SnapshotPolicies::propertyValue()` and `propertyEquity()` decide whether a `PropertyValueSnapshot` row is written. Null-derived means the metric is not applicable to the row — these are NOT snapshotted, otherwise the old-is-null → `shouldSnapshot=true` short-circuit would fire on every write.

10. **Tier-cap is enforced at `create()` only.** `TierGate::canCreate($user, 'property', $currentCount)` runs before persistence; on failure throws `TierLimitExceededException`. The free tier cap is 3 properties (seeded by `TierConfigurationSeeder`). `update()` / `delete()` / `restore()` do not re-check. (Spec §13; `PropertyTierCapTest` locked in PR 7.)

11. **Upload ingest does not surface `joint_owner_name`.** `PropertyNormaliser::fromUpload`'s whitelist (extraction → canonical) covers address / value / dates / enums / ownership_percentage / country / lease_remaining_years but NOT `joint_owner_name`. The document-extraction pipeline (DocumentProcessor + the AI excerpt parser) does not currently extract joint-owner identity text from uploaded statements. Form and Fyn ingest paths DO carry `joint_owner_name` (form via the manual modal; Fyn via the LLM tool call). Upload-created joint property rows have `joint_owner_name = NULL` until the user edits via form or Fyn. `PropertyThreeIngestParityTest` Case 2 asserts this asymmetry explicitly. Pass 5 candidate: extend the upload field-mapper + normaliser whitelist to support joint-owner extraction once the document parser can identify spouse/co-owner mentions reliably.

## Events

Per spec §11.1, four events are dispatched (each on its own write path):

```php
PropertyCreated(Property $property, User $user, IngestSource $source)
PropertyUpdated(Property $property, array $changes, User $user, IngestSource $source)
PropertyDeleted(int $propertyId, User $user, string $reason)
PropertyRestored(Property $property, User $user)
```

Events were introduced in PR 1 alongside the store. Consumers register listeners in `EventServiceProvider`. Per spec §11.3, listeners do **not** call store write methods on the entity that triggered them.

## Validation policy

Two-layer (spec §7):

- **Outer (HTTP):** `StorePropertyRequest`, `UpdatePropertyRequest` (form-request rules; user-facing error messages)
- **Inner (store):** `PropertyStore::validateCanonical()` (canonical-shape sanity; throws `StoreValidationException` with field-keyed error arrays; never user-facing)

For the three non-HTTP ingest paths (`FYN_AI`, `UPLOAD`, `SEEDER`), the normaliser is responsible for producing a payload that satisfies the inner rules — there is no outer HTTP layer to lean on. The store treats every ingest path identically once the canonical shape is in hand.

## Audit, encryption, security

- **Audit (spec §8.1):** every write happens inside `AuditLog::withContext(['ingest_source' => $source->value], …)` so audit rows capture the originating ingest path. Audit row schema: `user_id`, `model_type`, `model_id`, `action` (`created|updated|deleted|restored`), `old_values`, `new_values`, `metadata['ingest_source']`. Locked by `PropertyAuditIngestSourceTest` (PR 8).
- **Encryption at rest:** `Property` has no encrypted columns currently; encryption is a §8.2 concern.
- **Authorisation:** primary-owner-only writes; joint-aware reads; `forUsers(array)` is the only explicit multi-user entry point. `forTrust(int)` is trust-scoped (not user-scoped). Preview-user isolation is a route-level concern (`PreviewWriteInterceptor`), not a store responsibility (CLAUDE.md Rule #8).

## Currency normalisation

Per spec §9, Pass 4 stores property values in GBP only — `current_value` and `current_value_gbp` carry the same number. Multi-currency properties and FX-aware derived columns are deferred to a later sub-project pass. Single source of truth for FX rates when this lands is `CurrencyRateStore::latestFor()` — the same hook `SavingsStore` already uses.

## Migration history (SP1 Pass 4 — all merged to `dev`)

| PR | Number | Merge SHA | Description |
|---|---|---|---|
| PR 1 | #387 | `9da1590` | Introduce `PropertyStore` facade + arch boundary + `PropertyNormaliser` + four events + tier-cap enforcement seam + `TierConfigurationSeeder` property cap |
| PR 2 | #388 | `b8cbec5` | Point HTTP form requests at `PropertyStore` (`PropertyController` write paths); Option A tier-limit response shape |
| PR 3 | #389 | `ba42683` | Point Fyn AI write tools at `PropertyStore` (`CoordinatingAgent::handleCreateProperty` / `handleUpdateProperty` / `handleDeleteProperty`); `DB::transaction` atomicity |
| PR 4 | #390 | `df357e9` | Point upload + onboarding + seeders at `PropertyStore` (`DocumentProcessor` Property write + `PreviewUserSeeder` + `AssetCaptureEntityExtractor`) |
| PR 5a | #395 | `262ad96` | Estate / IHT read consumers via `PropertyStore` (`EstateActionDefinitionService`, `IHTCalculationService`, `LetterEstateValidationService`, `LetterToSpouseService`, `UserProfileService` Estate/IHT paths) |
| PR 5b | #396 | `e718e23` | NetWorth / Mobile / CrossModule read consumers via `PropertyStore` (`NetWorthService`, `MobileDashboardAggregator`, `CrossModuleAssetAggregator`) |
| PR 5c | #397 | `97c4365` | Coordination / Trust read consumers + new `forTrust(int)` read method (`HouseholdPlanningService` 3 of 4 sites, `TrustAssetAggregatorService`) |
| PR 5d | #398 | `02a9711` | AI / Profile read consumers (`AdvicePromptBuilder`, `DuplicateAcknowledgement`, `PersonalAccountsService`, `ProfileCompletenessChecker`, `UserProfileService`) |
| PR 5e | #399 | `d76e809` | Tax / Documents read consumers + `PropertyReadConsumerParityTest` (`IncomeDefinitionsService` buy-to-let rental income via `forUserByType`) |
| PR 6 | #400 | `84a55ac` | Canonical derived columns (`current_value_gbp`, `equity_gbp`, `loan_to_value_pct`) + `property_value_snapshots` table + per-column `SnapshotPolicy` + `BackfillPropertyDerivedColumns` console command + `PropertyDerivedColumnCalculator` |
| PR 7 | #401 | `8c59a2b` | Tier-cap test (`PropertyTierCapTest`) locking the spec §13 contract |
| PR 8 | (this PR) | — | Boundary allowlist LOCKED (transition language dropped; entries recategorised) + `PropertyAuditIngestSourceTest` (5 cases) + `PropertyThreeIngestParityTest` (2 cases) + `PropertyStore.md` |

## Acceptance criteria (spec §16)

| Gate | Met by |
|---|---|
| §16.1.1 Single write path (Pest boundary locked) | PR 8 (`PropertyStoreBoundaryTest` — LOCKED framing, no transition entries) |
| §16.1.2 Three-ingest parity | PR 8 (`PropertyThreeIngestParityTest` — individual parity + `tenants_in_common` invariant) |
| §16.1.3 Audit completeness — `ingest_source` on every write | PR 1 (wraps from the start) + PR 8 (`PropertyAuditIngestSourceTest` — 5 cases) |
| §16.1.4 Derived-column correctness | PR 6 (`PropertyDerivedColumnCalculator` + tests) |
| §16.1.5 Snapshot policy applied | PR 6 (per-column `propertyValue()` / `propertyEquity()` policies + tests) |
| §16.1.6 Currency round-trip | n/a — GBP-only in Pass 4; deferred to later pass (mirrors Pension pattern) |
| §16.1.7 Tier-cap enforcement | PR 1 (enforcement seam) + PR 7 (`PropertyTierCapTest` — 5 cases) |
| §16.1.8 Browser-tested via Playwright | csjones smoke after PR 8 merge (CSJ-driven) |
| §16.2.1 All entity stores | 7/19 after Pass 4 (Savings + Pensions + 4 ref-data + Properties) |
| §16.2.2 Boundary tests green | `PropertyStoreBoundaryTest` LOCKED in PR 8 |
| §16.2.5 Per-entity Store.md | PR 8 (this document) |

**Pass 4 is complete. PropertyStore joins Savings, R1–R4 reference data, and Pensions as a fully shipped entity store. SP1 progress: 7 of 19 entity stores done.**
