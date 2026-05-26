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

    // Domain events introduced by this PR — typed constructor params
    // reference the pension models. These are part of the canonical write
    // path (dispatched only from PensionStore) and stay on the allowlist
    // permanently per spec §14.2 (events).
    'App\Events\Pension\DCPensionCreated',
    'App\Events\Pension\DCPensionUpdated',
    'App\Events\Pension\DCPensionRestored',
    'App\Events\Pension\DBPensionCreated',
    'App\Events\Pension\DBPensionUpdated',
    'App\Events\Pension\DBPensionRestored',
    'App\Events\Pension\StatePensionUpserted',
    // EventServiceProvider listener registration — references event classes
    // which transitively reference the models; permanent per §14.2 (event wiring).
    'App\Providers\EventServiceProvider',

    // Transition allowlist — removed by subsequent PRs in pass 3.
    // PR 2 removes: HTTP controllers + form requests + API resource
    'App\Http\Controllers\Api\RetirementController',
    'App\Http\Controllers\Api\Retirement\DCPensionHoldingsController',
    'App\Http\Controllers\Api\Retirement\DecumulationController',
    'App\Http\Requests\Retirement\StoreDCPensionRequest',
    'App\Http\Requests\Retirement\StoreDBPensionRequest',
    'App\Http\Requests\Retirement\UpdateStatePensionRequest',
    'App\Http\Resources\DCPensionResource',
    // PR 3 removes: CoordinatingAgent (Fyn AI tool path)
    'App\Agents\CoordinatingAgent',
    // PR 4 removes: DocumentProcessor (upload path), PreviewController, seeders
    'App\Services\Documents\DocumentProcessor',
    'App\Http\Controllers\Api\PreviewController',
    'Database\Seeders\PreviewUserSeeder',
    'Database\Seeders\ChrisUserSeeder',
    'Database\Seeders\LifecycleTestSeeder',
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
    'App\Services\Estate\IHTFormattingService',
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
    'App\Services\UserProfile\ModuleDataRequirementsService',
    'App\Services\Documents\HoldingsImportService',
    'App\Services\Documents\DocumentTypeDetector',
    'App\Services\Documents\FieldMappers\DBPensionMapper',
    'App\Services\Documents\FieldMappers\DCPensionMapper',
    'App\Services\Investment\AssetLocation\TaxDragCalculator',
    'App\Services\Eval\EvalHttpDriver',
    'App\Services\NetWorth\NetWorthService',
    'App\Services\Risk\AutoRiskCalculator',
    'App\Services\Onboarding\AssetCaptureEntityExtractor',
    'App\Services\Account\RetentionPurgeService',
    'App\Services\GDPR\DataExportService',
    'App\Observers\NetWorthCacheObserver',
    'App\Observers\RecommendationCacheObserver',
    'App\Jobs\RecalculateRiskProfileJob',
    'App\Console\Commands\EncryptExistingData',
    'App\Console\Commands\ResetPreviewData',
    'App\Models\User', // relationships only — confirmed read-only at planning time
    'App\Models\Investment\Holding', // polymorphic holdable belongsTo DCPension
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
