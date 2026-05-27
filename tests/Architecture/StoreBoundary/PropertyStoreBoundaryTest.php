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

    // SP1 Pass 4 PR 3 documented residuals — handleCreateProperty now routes
    // through PropertyStore::create via fromFyn. Remaining Property:: refs in
    // CoordinatingAgent are non-write:
    //   - resolveModel() entity-type-map: `'property' => Property::class` (class-name ref only)
    //   - listEntities() 'property' case: Property::with('mortgages')->forUserOrJoint() (read — PR 5)
    //   - handleCreateMortgage(): Property::where()->{first,count}() (FK-resolution
    //     read + property-count guidance read — PR 5)
    //   - resolvePropertyId(): Property::where()->get() (read — PR 5)
    // PR 5 routes these reads through PropertyStore::find / forUser. At that
    // point CoordinatingAgent can be fully removed from this allowlist.
    'App\Agents\CoordinatingAgent',
    // PR 4 documented residuals (writes routed; non-write refs remain):
    //
    // DocumentProcessor: Property write in confirmExcel() now routes through
    // PropertyStore::create (IngestSource::UPLOAD). Residual: `Property::class`
    // used as an array key in registerMappers() — class-name-only reference,
    // not a mutation or query site. PR 5 will sweep read consumers in this file.
    'App\Services\Documents\DocumentProcessor',
    // AssetCaptureEntityExtractor: confirmed read-only (Property::query()
    // only — no create/update/delete calls). Deferred to PR 5 (read consumers).
    'App\Services\Onboarding\AssetCaptureEntityExtractor',
    // PreviewUserSeeder: Property write in createProperties() now routes through
    // PropertyStore::create (IngestSource::SEEDER). Residual: resetPersonaData()
    // uses Property::where($user->id)->delete() — a bulk-delete reset path
    // outside the ingest boundary. PR 5 will address remaining direct refs.
    'Database\Seeders\PreviewUserSeeder',
    // LifecycleTestSeeder uses Property factory for test scaffolding;
    // factory writes are not ingest events.
    'Database\Seeders\LifecycleTestSeeder',
    // PR 5 removes: read consumers (~21 services + Mortgage relationship reads).
    // Cluster 5a (PR #395, merged 262ad96) routed:
    //   EstateActionDefinitionService, IHTCalculationService,
    //   LetterEstateValidationService, LetterToSpouseService,
    //   UserProfileService Estate/IHT consumer paths.
    // Cluster 5b (PR #396, merged e718e23) routed:
    //   NetWorthService, MobileDashboardAggregator, CrossModuleAssetAggregator.
    // Cluster 5c (this PR) routes:
    //   HouseholdPlanningService (3 of 4 sites), TrustAssetAggregatorService.
    //   Added new PropertyStore::forTrust(int) read method for trust-scoped queries.
    'App\Http\Controllers\Api\MortgageController',
    'App\Services\AI\AdvicePromptBuilder',
    'App\Services\AI\DuplicateAcknowledgement',
    // HouseholdPlanningService — three read sites (273/394/922) routed through
    // PropertyStore in PR 5c. Residual: :739 polymorphic joint-asset detection
    // loop iterates over Property::class alongside SavingsAccount /
    // InvestmentAccount / CashAccount / Chattel. Routing one model out breaks
    // loop symmetry. Refactor to a JointAssetFinder service when all 5 entity
    // stores exist. Deferred from PR 5c.
    'App\Services\Coordination\HouseholdPlanningService',
    'App\Services\Documents\DocumentTypeDetector',
    'App\Services\Documents\FieldMappers\PropertyMapper',
    'App\Services\Tax\IncomeDefinitionsService',
    'App\Services\UserProfile\PersonalAccountsService',
    'App\Services\UserProfile\ProfileCompletenessChecker',
    'App\Services\UserProfile\UserProfileService',

    // SP1 Pass 4 PR 2 documented residuals — write paths now routed through
    // PropertyStore (POST/PUT/DELETE on /api/properties; PreviewController
    // seedProperties via IngestSource::SEEDER). These controllers still carry
    // direct Property reads (PropertyController::show / calculateCGT /
    // calculateRentalIncomeTax / index; PropertyController::update + destroy
    // pre-fetch via Property::where(...)->firstOrFail() for ownership-default
    // resolution + the mortgage cascade soft-delete respectively;
    // PropertyController::syncUserRentalIncome reads via
    // Property::forUserOrJoint; PreviewController::seedMortgages does a
    // Property::where lookup to populate the mortgage FK). PR 5 routes those
    // reads through PropertyStore::find / forUser; the destroy pre-fetch
    // moves into MortgageStore in Pass 5 once mortgage cascades live there.
    'App\Http\Controllers\Api\PropertyController',
    'App\Http\Controllers\Api\PreviewController',

    // Sibling models + console commands — out-of-Pass-4 read/infra refs
    'App\Models\Household',
    'App\Models\Mortgage',
    'App\Models\User',  // relationships only
    'App\Console\Commands\EncryptExistingData',
    'App\Console\Commands\ResetPreviewData',
    // MortgageFactory references Property::class to set the property_id FK
    // in test fixtures — sibling factory, not a query/mutation site.
    'Database\Factories\MortgageFactory',
    // PropertyResource — API resource for HTTP response transformation.
    // Permanent allowlist per spec §14.2 (resource classes, like SavingsAccountResource).
    'App\Http\Resources\PropertyResource',
    // Observer — side effects triggered by Eloquent lifecycle hooks.
    // Permanent allowlist per spec §14.2 (observers category).
    'App\Observers\PropertyRiskObserver',
    // Pure calculation helpers (spec §2.2 "files out of scope") — accept Property
    // instances as parameters; do not issue queries that bypass the store.
    'App\Services\Property\PropertyCalculationService',
    'App\Services\Property\PropertyTaxService',
    'App\Services\Property\PropertyService',
    // MortgageService — Pass 5 territory (Liabilities store). Uses Property
    // for the property_id FK relationship lookups, not a mutation site.
    'App\Services\Property\MortgageService',
];

arch('Property is only used inside the property canonical set (plus transition allowlist)')
    ->expect('App\Models\Property')
    ->toOnlyBeUsedIn($propertyConsumers);
