<?php

declare(strict_types=1);

/**
 * Sub-Project 1, Pass 1 — Savings store boundary enforcement.
 *
 * Hard-fails CI on any direct mutation of App\Models\SavingsAccount
 * outside the canonical write path (App\Services\Stores\SavingsStore).
 *
 * Allowlist (§14.2 of the spec): observers, migrations, seeders, console
 * commands, the store itself, and pre-existing direct-mutation sites
 * that subsequent PRs in this pass will migrate. Each entry below has a
 * comment naming the PR that removes it.
 */
arch('SavingsAccount mutations only happen inside SavingsStore (plus transition allowlist)')
    ->expect('App\Models\SavingsAccount')
    ->toOnlyBeUsedIn([
        // Permanent allowlist
        'App\Services\Stores\SavingsStore',
        'App\Observers\SavingsAccountGoalObserver',
        'App\Observers\SavingsAccountRiskObserver',
        'App\Models\\',                     // self-references in relationships
        'Database\Factories\SavingsAccountFactory',

        // Transition allowlist — removed by subsequent PRs in pass 1.
        // PR 3 removed write path from CoordinatingAgent + OnboardingService;
        // remaining read usages in CoordinatingAgent removed by PR 5.
        'App\Agents\CoordinatingAgent',
        // PR 4 removed write path from PreviewController + ChrisUserSeeder (imports cleaned).
        // DocumentProcessor (SavingsAccount::class mapper key at line 483) and
        // PreviewUserSeeder (delete + linkGoalsToAccounts reads) retain read-only usages
        // removed in PR 5.
        'App\Services\Documents\DocumentProcessor',
        'Database\Seeders\PreviewUserSeeder',
        'Database\Seeders\LifecycleTestSeeder',
        'App\Console\Commands\ResetPreviewData',
        // PR 5 removes: read consumers (all listed in plan §"Modified files")
        'App\Agents\SavingsAgent',
        'App\Agents\InvestmentAgent',
        // PR 5d removed: Tax strategies cluster (JointSavingsStrategy,
        // AssetShiftingBundleStrategy, PensionAACarryForwardStrategy,
        // IsaTopUpStrategy, TaxOptimisationService, TaxStrategyMath,
        // TaxActionDefinitionService) — now read via SavingsStore.
        // PR 5e removed: Investment ISA consumers cluster
        // (ISAAllowanceOptimizer, TaxOptimizationAnalyzer,
        // UserContextBuilder) — now read via SavingsStore.
        // PR 5f removed: CashFlowCoordinator — fully migrated, no residual
        // SavingsAccount reference (import dropped).
        // PR 5g removed: AI prompt + profile cluster — AdvicePromptBuilder,
        // DuplicateAcknowledgement, ProfileCompletenessChecker, and
        // AssetCaptureEntityExtractor — all four fully cleared (import
        // dropped, sole query site migrated to SavingsStore::forUser());
        // AssetCaptureEntityExtractor lost its "// reads only" line too.
        //
        // Residual reference — STAYS. The store boundary bans savings
        // *queries/mutations* outside SavingsStore; the arch static analysis
        // can only see statically-resolvable SavingsAccount::where(...) calls.
        // Both files below had ALL their statically-visible query sites
        // migrated in PR 5f but retain a SavingsAccount reference that cannot
        // be removed without an out-of-scope refactor:
        //  - HouseholdPlanningService retains a dynamically-dispatched cross-model
        //    query in calculateJointAssetsPassingToSurvivor: it iterates an
        //    $assetTypes list (incl. SavingsAccount::class) and calls
        //    $modelClass::where(...)->get(). This IS a savings query, but via static
        //    dispatch the arch check cannot see it. Extracting that polymorphic
        //    joint-asset sweep into a store-aware helper is out of scope for PR 5f;
        //    HPS stays allowlisted until a dedicated follow-up PR. Its two direct
        //    SavingsAccount::where query sites (gatherAssetsForUser, calculateISAUsage)
        //    ARE migrated in PR 5f.
        //  - LifeEventAllocationService retains the ?SavingsAccount return
        //    type on findCashAccountModel (plus the instanceof check at the
        //    determineISAAllocation result builder) — genuine non-query
        //    references; all of its query sites are migrated in PR 5f.
        'App\Services\Coordination\HouseholdPlanningService',
        'App\Services\Goals\LifeEventAllocationService',
        'App\Services\Savings\ISATracker',
        'App\Services\Savings\SavingsActionDefinitionService',
        'App\Models\Goal',
        // Additional pre-existing consumers not listed in plan — added to allowlist at PR 1 discovery.
        // These are read-only or infrastructure usages; migrated in later PRs.
        'App\Providers\EventServiceProvider',
        'App\Models\User',
        'App\Models\SavingsGoal',
        'App\Http\Resources\SavingsAccountResource',
        'App\Http\Controllers\Api\Plans\PlanController',
        'App\Events\Savings\SavingsAccountCreated',
        'App\Events\Savings\SavingsAccountUpdated',
        'App\Events\Savings\SavingsAccountRestored',
        'App\Services\Savings\RateComparator',
        'App\Services\Savings\LiquidityAnalyzer',
        'App\Services\UserProfile\PersonalAccountsService',
        'App\Services\UserProfile\UserProfileService',
        'App\Services\Documents\DocumentTypeDetector',
        'App\Services\Documents\FieldMappers\SavingsAccountMapper',
        'App\Services\Eval\EvalHttpDriver',
        'App\Services\NetWorth\NetWorthService',
        'App\Services\Risk\AutoRiskCalculator',
        'App\Console\Commands\SendSavingsAlerts',
        'App\Console\Commands\EncryptExistingData',
    ]);

arch('App\Services\Stores classes use strict types')
    ->expect('App\Services\Stores')
    ->toUseStrictTypes();
