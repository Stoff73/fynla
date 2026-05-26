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
