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
        'App\Services\Onboarding\AssetCaptureEntityExtractor', // reads only — kept on read consumers list
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
        'App\Services\Coordination\HouseholdPlanningService',
        'App\Services\Coordination\CashFlowCoordinator',
        'App\Services\Tax\Strategies\JointSavingsStrategy',
        'App\Services\Tax\Strategies\AssetShiftingBundleStrategy',
        'App\Services\Tax\Strategies\PensionAACarryForwardStrategy',
        'App\Services\Tax\Strategies\IsaTopUpStrategy',
        'App\Services\Tax\TaxOptimisationService',
        'App\Services\Tax\TaxStrategyMath',
        'App\Services\Tax\TaxActionDefinitionService',
        'App\Services\Savings\ISATracker',
        'App\Services\Savings\SavingsActionDefinitionService',
        'App\Services\Investment\Tax\ISAAllowanceOptimizer',
        'App\Services\Investment\Tax\TaxOptimizationAnalyzer',
        'App\Services\Investment\Recommendation\UserContextBuilder',
        'App\Services\Goals\LifeEventAllocationService',
        'App\Services\AI\AdvicePromptBuilder',
        'App\Services\AI\DuplicateAcknowledgement',
        'App\Services\UserProfile\ProfileCompletenessChecker',
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
