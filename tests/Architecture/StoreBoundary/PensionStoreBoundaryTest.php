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

    // Domain events introduced by PR 1 — typed constructor params
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

    // Documented residual NON-QUERY references (post-PR-2).
    // All direct queries/mutations against the pension models have been
    // migrated to PensionStore. The references that remain are static
    // class-name uses for polymorphic holdable_type and type hints —
    // they are not statically-resolvable queries and they cannot be
    // removed until the holdings track lands in Pass 6 (HoldingsStore).
    //
    //  - RetirementController retains DCPension::class in the private
    //    seedHoldingsForDcPension helper as polymorphic holdable_type for
    //    Holding rows. All seven write methods + index reads now go
    //    through PensionStore (PR 2).
    'App\Http\Controllers\Api\RetirementController',
    //  - DCPensionHoldingsController retains DCPension::class in
    //    Holding::where('holdable_type', DCPension::class) polymorphic
    //    queries + a DCPension return-type hint on the
    //    pensionForUserOr404 ownership helper. All ownership reads now
    //    funnel through PensionStore::find (PR 2).
    'App\Http\Controllers\Api\Retirement\DCPensionHoldingsController',
    //  - DCPensionResource: @mixin DCPension docblock on the API
    //    resource. Permanent type hint; no query.
    'App\Http\Resources\DCPensionResource',

    // PR 3 removes: CoordinatingAgent (Fyn AI tool path)
    'App\Agents\CoordinatingAgent',
    // Documented residual NON-QUERY references (post-PR-4).
    //  - DocumentProcessor retains DCPension::class + DBPension::class as
    //    keys in the field-mapper registry and as a type-discriminator in
    //    the holdings-import branch. Non-query class-name references only;
    //    the lone write (DC create on upload) now funnels through
    //    PensionStore::createDc with IngestSource::UPLOAD.
    'App\Services\Documents\DocumentProcessor',
    // Seeders (preview/lifecycle persona fixtures) — §14.2 permanent.
    //  - PreviewUserSeeder retains DCPension::where/delete cleanup paths
    //    plus a DCPension::class polymorphic discriminator on Holding
    //    rows. All creates (DC, DB, State) now route through PensionStore
    //    with IngestSource::SEEDER.
    //  - LifecycleTestSeeder uses DCPension::factory()->create() which is
    //    a §14.2-permitted factory call (DCPensionFactory is on the
    //    permanent allowlist).
    'Database\Seeders\PreviewUserSeeder',
    'Database\Seeders\LifecycleTestSeeder',
    // PR 5b–5g removes: remaining read consumers
    'App\Http\Controllers\Api\Retirement\DecumulationController',
    // PR 5a documented residual NON-QUERY references (post-migration).
    // These files migrated all DCPension/DBPension/StatePension queries to
    // PensionStore but retain non-query type-hint references in method
    // signatures (e.g. `private function calculateAnnualisedPlatformFeePercent(DCPension $pension)`).
    //  - PensionProjector: DCPension/DBPension/StatePension type hints on 8
    //    public/private method signatures.
    //  - SalarySacrificeAnalyzer: DCPension type hints + closure callback
    //    (`fn (DCPension $pension) => ...`).
    //  - RetirementActionDefinitionService: DCPension type hints on 3 private
    //    fee/contribution helpers.
    //  - PensionContributionOptimizer: DCPension type hints on 2 helpers.
    'App\Services\Retirement\PensionProjector',
    'App\Services\Retirement\SalarySacrificeAnalyzer',
    'App\Services\Retirement\RetirementActionDefinitionService',
    'App\Services\Retirement\PensionContributionOptimizer',
    // PR 5b documented residual: RetirementPlanService retains DCPension
    // type hints on calculateMonthlyEmployeeContribution + calculateMonthlyEmployerContribution.
    'App\Services\Plans\RetirementPlanService',
    'App\Services\UserProfile\ModuleDataRequirementsService',
    'App\Services\Documents\HoldingsImportService',
    'App\Services\Documents\DocumentTypeDetector',
    'App\Services\Documents\FieldMappers\DBPensionMapper',
    'App\Services\Documents\FieldMappers\DCPensionMapper',
    'App\Services\Investment\AssetLocation\TaxDragCalculator',
    'App\Services\Eval\EvalHttpDriver',
    'App\Services\NetWorth\NetWorthService',
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
