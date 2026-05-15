<?php

declare(strict_types=1);

/**
 * Sub-Project 1, Pass 1 — Savings store boundary enforcement (LOCKED).
 *
 * Pass 1 is complete. This allowlist is now FINAL: every direct mutation
 * and statically-resolvable query of App\Models\SavingsAccount flows through
 * the canonical write/read set (SavingsStore + its derived calculator),
 * with the remaining entries being spec §14.2 permanents (observers,
 * factory, events, model-relationship/self refs, console commands, seeders,
 * resource) and a small set of documented residual NON-QUERY references
 * (class-name args, type hints, relationship definitions, polymorphic
 * dynamic dispatch, and out-of-sub-project-1-scope read/infra consumers).
 *
 * No transition entries remain — the "PR 5x will remove" language has been
 * replaced with permanent/documented-residual framing. Each entry below is
 * classified. Removing any entry whose file still references SavingsAccount
 * will turn this suite RED: that means the entry is still load-bearing, not
 * that the rule should be weakened.
 */
arch('SavingsAccount mutations and reads only happen inside the savings canonical set + documented exceptions')
    ->expect('App\Models\SavingsAccount')
    ->toOnlyBeUsedIn([
        // ---- Canonical write/read set ----
        // The single canonical write path. All create/update/delete/restore
        // and all joint-aware reads funnel through here.
        'App\Services\Stores\SavingsStore',
        // Part of the canonical write path: invoked ONLY by
        // SavingsStore::recalculateDerived(), takes a SavingsAccount instance
        // and reads its properties to compute the materialised columns.
        // No queries, no mutations — conceptually an internal of the store.
        'App\Services\Stores\Recalc\SavingsAccountDerivedColumnCalculator',

        // ---- Spec §14.2 permanent allowlist ----
        // Observers (recalc / goal-link side effects), the factory, the
        // domain events, model self/relationship references, the API
        // resource, controlled console commands, and seeders. These are
        // permanent by design — never migrated behind the store.
        'App\Observers\SavingsAccountGoalObserver',
        'App\Observers\SavingsAccountRiskObserver',
        'App\Models\\',                     // self-references + relationship defs (Goal, SavingsGoal, User, SavingsAccountValueSnapshot belongsTo)
        'Database\Factories\SavingsAccountFactory',
        'App\Http\Resources\SavingsAccountResource',
        'App\Events\Savings\SavingsAccountCreated',
        'App\Events\Savings\SavingsAccountUpdated',
        'App\Events\Savings\SavingsAccountRestored',
        // Console commands — controlled one-shot / scheduled jobs, not a
        // runtime write path (spec §14.2 "console commands" category).
        'App\Console\Commands\ResetPreviewData',
        'App\Console\Commands\SendSavingsAlerts',
        'App\Console\Commands\EncryptExistingData',
        // One-off backfill of the canonical derived columns for existing
        // rows: reads via SavingsAccount::chunkById and forceFill/saveQuietly
        // the derived columns only — a migration-style backfill.
        'App\Console\Commands\BackfillSavingsDerivedColumns',
        // Seeders (test/preview/lifecycle persona fixtures) — §14.2 permanent.
        'Database\Seeders\PreviewUserSeeder',
        'Database\Seeders\LifecycleTestSeeder',

        // ---- Documented residual NON-QUERY references ----
        // These files retain a SavingsAccount reference that is NOT a
        // statically-resolvable query/mutation. Removing the residual would
        // require an out-of-scope signature/relationship refactor; the
        // canonical contract (no savings queries/mutations outside the
        // store) still holds because none of these issue a SavingsAccount
        // query that bypasses the store.
        //
        //  - CoordinatingAgent: SavingsAccount::class as a generic
        //    duplicate-checker argument (checkForDuplicate(SavingsAccount::class, …))
        //    and as an entity type-map value ('savings_account' => SavingsAccount::class).
        //    Non-query class-name references only; its prior read query
        //    (handleListRecords 'savings_account' arm) is migrated to
        //    SavingsStore::forUser().
        'App\Agents\CoordinatingAgent',
        //  - ISATracker: a SavingsAccount type hint on the public
        //    calculateProjectedSubscription(SavingsAccount $account) signature.
        //    Non-query type reference; all six of its direct query sites are
        //    migrated to SavingsStore::forUser().
        'App\Services\Savings\ISATracker',
        //  - Goal: SavingsAccount::class only in belongsTo / belongsToMany
        //    relationship definitions (relationship reads are allowed); no
        //    direct queries.
        'App\Models\Goal',
        //  - HouseholdPlanningService: a dynamically-dispatched cross-model
        //    sweep ($modelClass::where(...) over an $assetTypes list incl.
        //    SavingsAccount::class) the static analysis cannot resolve.
        //    Its two direct SavingsAccount::where sites are migrated; the
        //    polymorphic sweep is an out-of-scope follow-up.
        'App\Services\Coordination\HouseholdPlanningService',
        //  - DocumentProcessor: SavingsAccount::class as a field-mapper key.
        //    Non-query class-name reference.
        'App\Services\Documents\DocumentProcessor',
        //  - LifeEventAllocationService: a ?SavingsAccount return type on
        //    findCashAccountModel plus an instanceof check; all query sites
        //    migrated.
        'App\Services\Goals\LifeEventAllocationService',

        // ---- Out-of-sub-project-1-scope read / infra references ----
        // These were never in the 5x read-consumer migration scope: they are
        // read-only or framework-infra usages (event wiring, polymorphic
        // model maps, eval driver, plan controller, document detection,
        // net-worth/personal-accounts aggregation). Kept allowlisted as
        // out-of-scope read/infra references; a future sub-project may route
        // them through the store, but pass 1 does not.
        'App\Providers\EventServiceProvider',
        'App\Models\User',
        'App\Models\SavingsGoal',
        'App\Http\Controllers\Api\Plans\PlanController',
        'App\Services\Savings\RateComparator',
        'App\Services\Savings\LiquidityAnalyzer',
        'App\Services\UserProfile\PersonalAccountsService',
        'App\Services\UserProfile\UserProfileService',
        'App\Services\Documents\DocumentTypeDetector',
        'App\Services\Documents\FieldMappers\SavingsAccountMapper',
        'App\Services\Eval\EvalHttpDriver',
        'App\Services\NetWorth\NetWorthService',
        'App\Services\Risk\AutoRiskCalculator',
    ]);

arch('App\Services\Stores classes use strict types')
    ->expect('App\Services\Stores')
    ->toUseStrictTypes();
