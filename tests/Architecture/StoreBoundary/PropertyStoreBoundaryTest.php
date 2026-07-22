<?php

declare(strict_types=1);

/**
 * Sub-Project 1, Pass 4 — Property store boundary enforcement (LOCKED).
 *
 * Pass 4 is complete. This allowlist is now FINAL: every direct mutation
 * and statically-resolvable query of App\Models\Property flows through
 * the canonical write/read set (PropertyStore + PropertyNormaliser +
 * PropertyDerivedColumnCalculator + BackfillPropertyDerivedColumns),
 * with the remaining entries being spec §14.2 permanents (observers,
 * factory, events, model-relationship/self refs, the API resource,
 * console commands, seeders) and a small set of documented residual
 * NON-QUERY references (class-name args, type hints, polymorphic dispatch
 * loop at HouseholdPlanningService:739, controller residual reads from
 * PR 2) plus out-of-sub-project-1-scope references.
 *
 * No transition entries remain — the "PR Nx removes" language has been
 * replaced with permanent/documented-residual framing. Each entry below is
 * classified. Removing any entry whose file still references Property
 * will turn this suite RED: that means the entry is still load-bearing,
 * not that the rule should be weakened.
 */
$propertyConsumers = [
    // ---- Canonical write/read set ----
    // The single canonical write path. All create/update/delete/restore
    // and all joint-aware reads funnel through here.
    'App\Services\Stores\PropertyStore',
    // Part of the canonical write path: normalises inbound payloads from
    // form / Fyn AI / upload vocabularies onto the canonical shape.
    // No queries, no mutations.
    'App\Services\Stores\Normalisers\PropertyNormaliser',
    // Part of the canonical write path: invoked ONLY by
    // PropertyStore::recalculateDerived(), takes a Property instance
    // and reads its fields to compute the materialised columns
    // (current_value_gbp, equity_gbp, loan_to_value_pct). No queries,
    // no mutations — conceptually an internal of the store.
    'App\Services\Stores\Recalc\PropertyDerivedColumnCalculator',
    // One-off backfill of the canonical derived columns for existing
    // rows: reads via Property::chunkById and forceFill/saveQuietly
    // the derived columns only — a migration-style backfill
    // (spec §14.2 console-command category).
    'App\Console\Commands\BackfillPropertyDerivedColumns',
    // Pass 5 PR 6 — one-off backfill that reconciles
    // properties.outstanding_mortgage from canonical MortgageStore sum.
    // Reads via Property::chunkById then funnels each id through
    // PropertyStore::recalculateDerivedForPropertyId (spec §14.2
    // console-command category).
    'App\Console\Commands\BackfillPropertyOutstandingMortgage',

    // ---- Spec §14.2 permanent allowlist ----
    // Observer (risk recalculation side effects), the factory, domain events,
    // model self/relationship references, the API resource, controlled
    // console commands, and seeders. These are permanent by design —
    // never migrated behind the store.
    'App\Observers\PropertyRiskObserver',
    'Database\Factories\PropertyFactory',
    // MortgageFactory references Property::class to set the property_id FK
    // in test fixtures — sibling factory, not a query/mutation site.
    'Database\Factories\MortgageFactory',
    // Self-references in model files (relationship definitions + class-name
    // discriminators in sibling models).
    'App\Models\\',
    // Domain events — typed constructor params reference the Property model.
    // Dispatched only from PropertyStore (canonical write path).
    'App\Events\Property\PropertyCreated',
    'App\Events\Property\PropertyUpdated',
    'App\Events\Property\PropertyDeleted',
    'App\Events\Property\PropertyRestored',
    // EventServiceProvider listener registration — references event classes
    // which transitively reference the model; permanent per §14.2 (event
    // wiring).
    'App\Providers\EventServiceProvider',
    // API resource — permanent type hint; no query.
    'App\Http\Resources\PropertyResource',
    // Console commands — controlled one-shot / scheduled jobs, not a
    // runtime write path (spec §14.2 console-command category).
    'App\Console\Commands\EncryptExistingData',
    'App\Console\Commands\ResetPreviewData',
    // GamificationBackfill maps Property::class => category for read-only
    // per-user counts when backfilling point awards; writes only
    // gamification tables.
    'App\Console\Commands\GamificationBackfill',
    // Seeders (preview / lifecycle persona fixtures) — §14.2 permanent.
    //  - PreviewUserSeeder routes all Property creates through
    //    PropertyStore::create (IngestSource::SEEDER); resetPersonaData()
    //    retains a bulk-delete path outside the ingest boundary.
    //  - LifecycleTestSeeder uses Property factory for test scaffolding;
    //    factory writes are §14.2-permitted.
    //  - PreviewGamificationSeeder issues read-only per-persona counts
    //    (Property::query()->count()) to derive seeded point awards;
    //    writes only gamification tables.
    'Database\Seeders\PreviewUserSeeder',
    'Database\Seeders\LifecycleTestSeeder',
    'Database\Seeders\PreviewGamificationSeeder',
    // Snapshot model — value-snapshot history table for property valuations,
    // written exclusively by PropertyStore::recalculateDerived() (PR 6).
    'App\Models\PropertyValueSnapshot',

    // ---- Documented residual NON-QUERY references ----
    // These files retain a Property reference that is NOT a
    // statically-resolvable query/mutation. Removing the residual would
    // require an out-of-scope signature/relationship refactor; the
    // canonical contract (no property queries/mutations outside the store)
    // still holds because none of these issue a Property query that
    // bypasses the store.
    //
    //  - CoordinatingAgent: Property::class as a generic
    //    duplicate-checker argument and as an entity-type-map value
    //    ('property' => Property::class). Non-query class-name references
    //    only; all property write tools and read calls now route through
    //    PropertyStore.
    'App\Agents\CoordinatingAgent',
    //  - DocumentTypeDetector + PropertyMapper: Property::class used as
    //    a dispatch key in the upload field-mapper registry. Non-query
    //    class-name references only.
    'App\Services\Documents\DocumentTypeDetector',
    'App\Services\Documents\FieldMappers\PropertyMapper',
    //  - HouseholdPlanningService: :739 polymorphic joint-asset detection
    //    loop iterates over Property::class alongside SavingsAccount /
    //    InvestmentAccount / CashAccount / Chattel. Routing one model out
    //    breaks loop symmetry. All three direct-query sites (:273/:394/:922)
    //    are migrated to PropertyStore; the polymorphic sweep is deferred
    //    to when all five entity stores exist (JointAssetFinder service).
    'App\Services\Coordination\HouseholdPlanningService',
    //  - DocumentProcessor: Property::class used as an array key in
    //    registerMappers(); the write path (confirmExcel) now routes through
    //    PropertyStore::create (IngestSource::UPLOAD). Non-query class-name
    //    reference only.
    'App\Services\Documents\DocumentProcessor',
    //  - AssetCaptureEntityExtractor: confirmed read-only
    //    (Property::query() only — no create/update/delete calls). All
    //    reads have been migrated to PropertyStore in PR 5d.
    'App\Services\Onboarding\AssetCaptureEntityExtractor',
    //  - PropertyController + PreviewController: direct Property reads
    //    retained from PR 2 (PropertyController::show / calculateCGT /
    //    calculateRentalIncomeTax / index; PropertyController::update +
    //    destroy pre-fetch via Property::where()->firstOrFail(); syncUserRentalIncome
    //    via Property::forUserOrJoint; PreviewController::seedMortgages
    //    Property::where lookup to populate the mortgage FK). All write
    //    paths (POST/PUT/DELETE) route through PropertyStore. The residual
    //    direct reads are kept here; Pass 5 (MortgageStore) will move the
    //    destroy pre-fetch; a subsequent pass will sweep the remaining reads.
    'App\Http\Controllers\Api\PropertyController',
    'App\Http\Controllers\Api\PreviewController',
    //  - Pure calculation helpers accept Property instances as parameters;
    //    do not issue queries that bypass the store.
    'App\Services\Property\PropertyCalculationService',
    'App\Services\Property\PropertyTaxService',
    'App\Services\Property\PropertyService',

    // ---- Out-of-sub-project-1-scope read / infra references ----
    // These were never in the Pass 4 read-consumer migration scope: sibling
    // models (Household, Mortgage, User relationships), the MortgageService
    // (Pass 5 territory — uses Property for property_id FK relationship
    // lookups), and the MortgageController (Pass 5 territory). Kept
    // allowlisted as out-of-scope read/infra references.
    'App\Http\Controllers\Api\MortgageController',
    'App\Models\Household',
    'App\Models\Mortgage',
    'App\Models\User',
    'App\Services\Property\MortgageService',
];

arch('Property is only used inside the property canonical set + documented exceptions')
    ->expect('App\Models\Property')
    ->toOnlyBeUsedIn($propertyConsumers);
