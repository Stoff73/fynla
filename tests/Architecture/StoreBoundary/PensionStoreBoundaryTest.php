<?php

declare(strict_types=1);

/**
 * Sub-Project 1, Pass 3 — Pensions store boundary enforcement (LOCKED).
 *
 * Pass 3 is complete. This allowlist is now FINAL: every direct mutation
 * and statically-resolvable query of App\Models\DCPension, App\Models\DBPension,
 * App\Models\StatePension, and App\Models\PensionInputHistory flows through
 * the canonical write/read set (PensionStore + its normaliser + derived
 * calculator + console backfill), with the remaining entries being spec §14.2
 * permanents (observers, factories, events, model-relationship/self refs, the
 * API resource, console commands, seeders) and a small set of documented
 * residual NON-QUERY references (class-name args, type hints, polymorphic
 * holdable_type discriminators, field-mapper registry keys) plus out-of-
 * sub-project-1-scope read/infra consumers.
 *
 * No transition entries remain — the "PR Nx will remove" language has been
 * replaced with permanent/documented-residual framing. Each entry below is
 * classified. Removing any entry whose file still references one of the
 * pension models will turn this suite RED: that means the entry is still
 * load-bearing, not that the rule should be weakened.
 */
$pensionConsumers = [
    // ---- Canonical write/read set ----
    // The single canonical write path. All create/update/delete/restore
    // (DC, DB, State) and all reads funnel through here.
    'App\Services\Stores\PensionStore',
    // Part of the canonical write path: normalises inbound payloads into
    // the store's canonical shape. No queries, no mutations.
    'App\Services\Stores\Normalisers\PensionNormaliser',
    // Part of the canonical write path: invoked ONLY by
    // PensionStore::recalculate{Dc,Db,State}Derived(), takes a pension
    // instance and reads its properties to compute the materialised
    // columns. No queries, no mutations — conceptually an internal of
    // the store.
    'App\Services\Stores\Recalc\PensionDerivedColumnCalculator',
    // One-off backfill of the canonical derived columns for existing
    // rows: reads via DCPension/DBPension/StatePension::chunkById and
    // forceFill/saveQuietly the derived columns only — a migration-style
    // backfill (spec §14.2 console-command category).
    'App\Console\Commands\BackfillPensionDerivedColumns',

    // ---- Spec §14.2 permanent allowlist ----
    // Observers (recalc / risk side effects), factories, domain events,
    // model self/relationship references, the API resource, controlled
    // console commands, and seeders. These are permanent by design —
    // never migrated behind the store.
    'App\Observers\DCPensionRiskObserver',
    'Database\Factories\DCPensionFactory',
    'Database\Factories\DBPensionFactory',
    'Database\Factories\StatePensionFactory',
    // Snapshot models — value-snapshot history tables for DC / DB / State
    // pensions, written exclusively by PensionStore::recalculate*Derived().
    'App\Models\DCPensionValueSnapshot',
    'App\Models\DBPensionValueSnapshot',
    'App\Models\StatePensionValueSnapshot',
    // Self-references in model files (relationship definitions + class-name
    // discriminators in sibling models).
    'App\Models\\',
    // Domain events — typed constructor params reference the pension
    // models. Dispatched only from PensionStore (canonical write path).
    'App\Events\Pension\DCPensionCreated',
    'App\Events\Pension\DCPensionUpdated',
    'App\Events\Pension\DCPensionRestored',
    'App\Events\Pension\DBPensionCreated',
    'App\Events\Pension\DBPensionUpdated',
    'App\Events\Pension\DBPensionRestored',
    'App\Events\Pension\StatePensionUpserted',
    // EventServiceProvider listener registration — references event classes
    // which transitively reference the models; permanent per §14.2 (event
    // wiring).
    'App\Providers\EventServiceProvider',
    // API resource — @mixin DCPension docblock on the resource. Permanent
    // type hint; no query.
    'App\Http\Resources\DCPensionResource',
    // Console commands — controlled one-shot / scheduled jobs, not a
    // runtime write path (spec §14.2 console-command category).
    'App\Console\Commands\EncryptExistingData',
    'App\Console\Commands\ResetPreviewData',
    // GamificationBackfill maps DCPension/DBPension::class => category for
    // read-only per-user counts when backfilling point awards; writes only
    // gamification tables.
    'App\Console\Commands\GamificationBackfill',
    // Seeders (preview / lifecycle persona fixtures) — §14.2 permanent.
    //  - PreviewUserSeeder retains DCPension::where/delete cleanup paths
    //    plus a DCPension::class polymorphic discriminator on Holding
    //    rows. All creates (DC, DB, State) now route through PensionStore
    //    with IngestSource::SEEDER.
    //  - LifecycleTestSeeder uses DCPension::factory()->create() which is
    //    a §14.2-permitted factory call (DCPensionFactory is on the
    //    permanent allowlist).
    //  - PreviewGamificationSeeder issues read-only per-persona counts
    //    (DCPension/DBPension::query()->count()) to derive seeded point
    //    awards; writes only gamification tables.
    'Database\Seeders\PreviewUserSeeder',
    'Database\Seeders\LifecycleTestSeeder',
    'Database\Seeders\PreviewGamificationSeeder',

    // ---- Documented residual NON-QUERY references ----
    // These files retain a pension-model reference that is NOT a
    // statically-resolvable query/mutation. Removing the residual would
    // require an out-of-scope signature/relationship refactor; the
    // canonical contract (no pension queries/mutations outside the store)
    // still holds because none of these issue a query that bypasses the
    // store.
    //
    //  - RetirementController retains DCPension::class in the private
    //    seedHoldingsForDcPension helper as polymorphic holdable_type for
    //    Holding rows. All seven write methods + index reads now go
    //    through PensionStore.
    'App\Http\Controllers\Api\RetirementController',
    //  - DCPensionHoldingsController retains DCPension::class in
    //    Holding::where('holdable_type', DCPension::class) polymorphic
    //    queries + a DCPension return-type hint on the
    //    pensionForUserOr404 ownership helper. All ownership reads now
    //    funnel through PensionStore::find.
    'App\Http\Controllers\Api\Retirement\DCPensionHoldingsController',
    //  - DecumulationController retains DCPension/DBPension/StatePension
    //    type hints on private projection helpers. All query sites now
    //    funnel through PensionStore.
    'App\Http\Controllers\Api\Retirement\DecumulationController',
    //  - CoordinatingAgent (Fyn AI write path): DC/DB/State::class as
    //    duplicate-checker arguments and entity-type-map values. The Fyn
    //    AI tool calls now route through PensionStore.
    'App\Agents\CoordinatingAgent',
    //  - DocumentProcessor retains DC/DBPension::class as keys in the
    //    field-mapper registry and as a type-discriminator in the
    //    holdings-import branch. Non-query class-name references only;
    //    the write (DC create on upload) now funnels through
    //    PensionStore::createDc with IngestSource::UPLOAD.
    'App\Services\Documents\DocumentProcessor',
    //  - PensionProjector / SalarySacrificeAnalyzer /
    //    RetirementActionDefinitionService / PensionContributionOptimizer:
    //    DCPension/DBPension/StatePension type hints on public/private
    //    method signatures and closure callbacks. All direct query sites
    //    are migrated to PensionStore.
    'App\Services\Retirement\PensionProjector',
    'App\Services\Retirement\SalarySacrificeAnalyzer',
    'App\Services\Retirement\RetirementActionDefinitionService',
    'App\Services\Retirement\PensionContributionOptimizer',
    //  - RetirementPlanService retains DCPension type hints on
    //    calculateMonthlyEmployeeContribution +
    //    calculateMonthlyEmployerContribution.
    'App\Services\Plans\RetirementPlanService',
    //  - ModuleDataRequirementsService retains pension type hints in the
    //    field-presence helpers; all reads migrated.
    'App\Services\UserProfile\ModuleDataRequirementsService',
    //  - Documents field-mapper registry / HoldingsImportService: type
    //    constant references in the mapper-supports-target methods plus a
    //    ?DCPension return-type hint on HoldingsImportService::matchPension.
    'App\Services\Documents\DocumentTypeDetector',
    'App\Services\Documents\FieldMappers\DBPensionMapper',
    'App\Services\Documents\FieldMappers\DCPensionMapper',
    'App\Services\Documents\HoldingsImportService',
    //  - User: pension-relation methods only (HasMany dcPensions /
    //    dbPensions / hasOne statePension). Confirmed read-only.
    'App\Models\User',
    //  - Holding: polymorphic holdable belongsTo DCPension — relationship
    //    definition only.
    'App\Models\Investment\Holding',

    // ---- Out-of-sub-project-1-scope read / infra references ----
    // These were never in the Pass 3 read-consumer migration scope: they
    // are read-only cache observers, async risk-recalc job, and an
    // encryption console command. Kept allowlisted as out-of-scope
    // read/infra references; a future pass may route them through the
    // store, but Pass 3 does not.
    'App\Observers\NetWorthCacheObserver',
    'App\Observers\RecommendationCacheObserver',
    'App\Jobs\RecalculateRiskProfileJob',
];

arch('DCPension is only used inside the pensions canonical set + documented exceptions')
    ->expect('App\Models\DCPension')
    ->toOnlyBeUsedIn($pensionConsumers);

arch('DBPension is only used inside the pensions canonical set + documented exceptions')
    ->expect('App\Models\DBPension')
    ->toOnlyBeUsedIn($pensionConsumers);

arch('StatePension is only used inside the pensions canonical set + documented exceptions')
    ->expect('App\Models\StatePension')
    ->toOnlyBeUsedIn($pensionConsumers);

arch('PensionInputHistory is only used inside the pensions canonical set + documented exceptions')
    ->expect('App\Models\PensionInputHistory')
    ->toOnlyBeUsedIn($pensionConsumers);
