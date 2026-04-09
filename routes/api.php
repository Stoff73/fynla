<?php

use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BugReportController;
use App\Http\Controllers\Api\BusinessInterestController;
use App\Http\Controllers\Api\ChattelController;
use App\Http\Controllers\Api\ContactFormController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\Estate\GiftingController;
use App\Http\Controllers\Api\Estate\IHTController;
use App\Http\Controllers\Api\Estate\LetterValidationController;
use App\Http\Controllers\Api\Estate\LifePolicyController;
use App\Http\Controllers\Api\Estate\LpaController;
use App\Http\Controllers\Api\Estate\TrustController;
use App\Http\Controllers\Api\Estate\WillController;
use App\Http\Controllers\Api\Estate\WillDocumentController;
use App\Http\Controllers\Api\EstateController;
use App\Http\Controllers\Api\FamilyMembersController;
use App\Http\Controllers\Api\GDPRController;
use App\Http\Controllers\Api\GoalsController;
use App\Http\Controllers\Api\HolisticPlanningController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\IncomeDefinitionsController;
use App\Http\Controllers\Api\InfoGuideController;
use App\Http\Controllers\Api\Investment\AssetLocationController;
use App\Http\Controllers\Api\Investment\ContributionOptimizerController;
use App\Http\Controllers\Api\Investment\EfficientFrontierController;
use App\Http\Controllers\Api\Investment\FeeImpactController;
use App\Http\Controllers\Api\Investment\GoalProgressController;
use App\Http\Controllers\Api\Investment\InvestmentScenarioController;
use App\Http\Controllers\Api\Investment\ModelPortfolioController;
use App\Http\Controllers\Api\Investment\PerformanceAttributionController;
use App\Http\Controllers\Api\Investment\PortfolioStrategyController;
use App\Http\Controllers\Api\Investment\RebalancingActionsController;
use App\Http\Controllers\Api\Investment\RebalancingCalculationController;
use App\Http\Controllers\Api\Investment\RebalancingStrategiesController;
use App\Http\Controllers\Api\Investment\TaxOptimizationController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\InvestmentProjectionController;
use App\Http\Controllers\Api\JourneyController;
use App\Http\Controllers\Api\LetterToSpouseController;
use App\Http\Controllers\Api\LifeStageController;
use App\Http\Controllers\Api\MFAController;
use App\Http\Controllers\Api\MortgageController;
use App\Http\Controllers\Api\NetWorthController;
use App\Http\Controllers\Api\OccupationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PersonalAccountsController;
use App\Http\Controllers\Api\PortfolioOptimizationController;
use App\Http\Controllers\Api\PostcodeLookupController;
use App\Http\Controllers\Api\PreviewController;
use App\Http\Controllers\Api\ProfileCompletenessController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ProtectionController;
use App\Http\Controllers\Api\RecommendationsController;
use App\Http\Controllers\Api\Retirement\DCPensionHoldingsController;
use App\Http\Controllers\Api\Retirement\DecumulationController;
use App\Http\Controllers\Api\RetirementController;
use App\Http\Controllers\Api\RiskPreferenceController;
use App\Http\Controllers\Api\SavingsController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\Settings\AssumptionsController;
use App\Http\Controllers\Api\SpousePermissionController;
use App\Http\Controllers\Api\Tax\TaxOptimisationController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\WhatIfScenarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Contact form (public, rate-limited)
Route::post('/contact', [ContactFormController::class, 'submit'])->middleware('throttle:3,5');

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/verify-code', [AuthController::class, 'verifyCode'])->middleware('throttle:10,1');
    Route::post('/resend-code', [AuthController::class, 'resendCode'])->middleware('throttle:5,1');

    // Beacon logout - accepts token in body for browser/tab close handling
    // No auth middleware since sendBeacon cannot set Authorization header
    Route::post('/logout-beacon', [AuthController::class, 'logoutBeacon'])->middleware('throttle:10,1');

    // MFA verification during login (no auth required - user is partially authenticated)
    Route::post('/mfa/verify', [MFAController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('/mfa/recovery', [MFAController::class, 'useRecoveryCode'])->middleware('throttle:5,1');

    // Password reset routes (no auth required)
    Route::prefix('password-reset')->group(function () {
        Route::post('/request', [PasswordResetController::class, 'request'])->middleware('throttle:3,1');
        Route::post('/verify-email', [PasswordResetController::class, 'verifyEmail'])->middleware('throttle:10,1');
        Route::post('/resend-code', [PasswordResetController::class, 'resendCode'])->middleware('throttle:3,1');
        Route::post('/verify-mfa', [PasswordResetController::class, 'verifyMfa'])->middleware('throttle:10,1');
        Route::post('/mfa-recovery', [PasswordResetController::class, 'useMfaRecovery'])->middleware('throttle:3,1');
        Route::post('/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:3,1');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user'])->middleware('throttle:60,1');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');

        // MFA management (requires full authentication)
        Route::prefix('mfa')->group(function () {
            Route::get('/status', [MFAController::class, 'status']);
            Route::post('/setup', [MFAController::class, 'setup']);
            Route::post('/verify-setup', [MFAController::class, 'verifySetup']);
            Route::post('/disable', [MFAController::class, 'disable']);
            Route::post('/recovery-codes', [MFAController::class, 'regenerateRecoveryCodes']);
        });

        // Session management
        Route::prefix('sessions')->group(function () {
            Route::get('/', [SessionController::class, 'index']);
            Route::delete('/{id}', [SessionController::class, 'destroy']);
            Route::delete('/others/all', [SessionController::class, 'destroyOthers']);
        });

        // GDPR & Privacy routes
        Route::prefix('gdpr')->group(function () {
            // Consent management
            Route::get('/consents', [GDPRController::class, 'getConsents']);
            Route::put('/consents', [GDPRController::class, 'updateConsents']);
            Route::get('/consents/history', [GDPRController::class, 'getConsentHistory']);

            // Data export (right to portability) - rate limited to 3/hour
            Route::post('/export', [GDPRController::class, 'requestExport'])->middleware('throttle:export');
            Route::get('/export/status', [GDPRController::class, 'getExportStatus']);
            Route::get('/export/{id}/download', [GDPRController::class, 'downloadExport']);

            // Data erasure (right to be forgotten) - self-service immediate deletion
            Route::post('/erasure/initiate', [GDPRController::class, 'initiateErasure'])->middleware('throttle:sensitive');
            Route::post('/erasure/verify', [GDPRController::class, 'verifyErasure'])->middleware('throttle:sensitive');
            Route::post('/erasure/execute', [GDPRController::class, 'executeErasure'])->middleware('throttle:sensitive');
            Route::post('/erasure/resend-code', [GDPRController::class, 'resendDeletionCode'])->middleware('throttle:sensitive');

            // Legacy erasure endpoints (deprecated, kept for backwards compatibility)
            Route::post('/erasure', [GDPRController::class, 'requestErasure'])->middleware('throttle:sensitive');
            Route::get('/erasure/status', [GDPRController::class, 'getErasureStatus']);
            Route::post('/erasure/{id}/confirm', [GDPRController::class, 'confirmErasure'])->middleware('throttle:sensitive');
            Route::post('/erasure/{id}/cancel', [GDPRController::class, 'cancelErasure']);
        });
    });
});

// Preview Mode routes (allows unauthenticated preview access)
Route::prefix('preview')->group(function () {
    // Public routes - no auth required (rate limited)
    Route::get('/personas', [PreviewController::class, 'getPersonas']);
    Route::post('/login/{personaId}', [PreviewController::class, 'login'])->middleware('throttle:3,1');

    // Authenticated preview routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/switch/{personaId}', [PreviewController::class, 'switch'])->middleware('throttle:20,1');
        Route::post('/exit', [PreviewController::class, 'exit']);
    });
});

// Onboarding routes
Route::middleware('auth:sanctum')->prefix('onboarding')->group(function () {
    Route::get('/status', [OnboardingController::class, 'getOnboardingStatus']);
    Route::post('/focus-area', [OnboardingController::class, 'setFocusArea']);
    Route::get('/steps', [OnboardingController::class, 'getSteps']);
    Route::get('/step/{step}', [OnboardingController::class, 'getStepData']);
    Route::post('/step', [OnboardingController::class, 'saveStepProgress']);
    Route::post('/skip-step', [OnboardingController::class, 'skipStep']);
    Route::get('/skip-reason/{step}', [OnboardingController::class, 'getSkipReason']);
    Route::post('/skip-to-dashboard', [OnboardingController::class, 'skipToDashboard']);
    Route::post('/complete', [OnboardingController::class, 'completeOnboarding']);
    Route::post('/complete-quick', [OnboardingController::class, 'completeQuickOnboarding']);
    Route::post('/restart', [OnboardingController::class, 'restartOnboarding']);
});

// Journey onboarding routes
Route::middleware('auth:sanctum')->prefix('journeys')->group(function () {
    Route::get('/selections', [JourneyController::class, 'getSelections']);
    Route::post('/selections', [JourneyController::class, 'saveSelections']);
    Route::get('/preview', [JourneyController::class, 'preview']);
    Route::get('/dashboard-prompts', [JourneyController::class, 'getDashboardPrompts']);
    Route::post('/dismiss-prompt', [JourneyController::class, 'dismissPrompt']);
    Route::get('/{journey}/steps', [JourneyController::class, 'getSteps']);
    Route::post('/{journey}/start', [JourneyController::class, 'startJourney']);
    Route::post('/{journey}/complete', [JourneyController::class, 'completeJourney']);
});

// Life Stage routes
Route::middleware('auth:sanctum')->prefix('life-stage')->group(function () {
    Route::get('/progress', [LifeStageController::class, 'progress']);
    Route::get('/completeness', [LifeStageController::class, 'completeness']);
    Route::post('/set', [LifeStageController::class, 'setStage']);
    Route::post('/complete-step', [LifeStageController::class, 'completeStep']);
});

// User Profile routes (Phase 2)
Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    // Profile endpoints
    Route::get('/profile', [UserProfileController::class, 'getProfile']);
    Route::put('/profile/personal', [UserProfileController::class, 'updatePersonalInfo']);
    Route::put('/profile/income-occupation', [UserProfileController::class, 'updateIncomeOccupation']);
    Route::put('/profile/expenditure', [UserProfileController::class, 'updateExpenditure']);
    Route::put('/profile/domicile', [UserProfileController::class, 'updateDomicileInfo']);
    Route::get('/profile/completeness', [ProfileCompletenessController::class, 'check']);
    Route::get('/financial-commitments', [UserProfileController::class, 'getFinancialCommitments']);
    Route::get('/spouse/financial-commitments', [UserProfileController::class, 'getSpouseFinancialCommitments']);
    Route::put('/dashboard-widget-order', [UserProfileController::class, 'updateDashboardWidgetOrder']);

    // Letter to Spouse
    Route::get('/letter-to-spouse', [LetterToSpouseController::class, 'show']);
    Route::get('/letter-to-spouse/exists', [LetterToSpouseController::class, 'exists']);
    Route::get('/letter-to-spouse/spouse', [LetterToSpouseController::class, 'showSpouse']);
    Route::put('/letter-to-spouse', [LetterToSpouseController::class, 'update'])->middleware('feature:standard');

    // Family Members CRUD
    Route::prefix('family-members')->group(function () {
        Route::get('/', [FamilyMembersController::class, 'index']);
        Route::post('/', [FamilyMembersController::class, 'store'])->middleware('feature:family');
        Route::get('/{id}', [FamilyMembersController::class, 'show']);
        Route::put('/{id}', [FamilyMembersController::class, 'update'])->middleware('feature:family');
        Route::delete('/{id}', [FamilyMembersController::class, 'destroy'])->middleware('feature:family');
    });

    // Personal Accounts (P&L, Cashflow, Balance Sheet)
    Route::prefix('personal-accounts')->group(function () {
        Route::get('/', [PersonalAccountsController::class, 'index']);
        Route::post('/calculate', [PersonalAccountsController::class, 'calculate']);
        Route::post('/line-item', [PersonalAccountsController::class, 'storeLineItem']);
        Route::put('/line-item/{id}', [PersonalAccountsController::class, 'updateLineItem']);
        Route::delete('/line-item/{id}', [PersonalAccountsController::class, 'deleteLineItem']);
    });

    // Preview/Guidance routes
    Route::post('/seed-persona-data', [PreviewController::class, 'seedPersonaData']);
    Route::get('/guidance-status', [PreviewController::class, 'getGuidanceStatus']);
    Route::post('/guidance-status', [PreviewController::class, 'updateGuidanceStatus']);
});

// Information Guide routes
Route::middleware('auth:sanctum')->prefix('info-guide')->group(function () {
    Route::get('/requirements', [InfoGuideController::class, 'getRequirements']);
    Route::get('/preference', [InfoGuideController::class, 'getPreference']);
    Route::put('/preference', [InfoGuideController::class, 'updatePreference']);
});

// Spouse Permission routes
Route::middleware('auth:sanctum')->prefix('spouse-permission')->group(function () {
    Route::get('/status', [SpousePermissionController::class, 'status']);
    Route::post('/request', [SpousePermissionController::class, 'request']);
    Route::post('/accept', [SpousePermissionController::class, 'accept']);
    Route::post('/reject', [SpousePermissionController::class, 'reject']);
    Route::delete('/revoke', [SpousePermissionController::class, 'revoke']);
});

// Spouse data access routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{userId}', [UserProfileController::class, 'getUserById']);
    Route::put('/users/{userId}/expenditure', [UserProfileController::class, 'updateSpouseExpenditure']);
});

// Net Worth routes (Phase 3)
Route::middleware('auth:sanctum')->prefix('net-worth')->group(function () {
    Route::get('/overview', [NetWorthController::class, 'getOverview']);
    Route::get('/breakdown', [NetWorthController::class, 'getBreakdown']);
    Route::get('/assets-summary', [NetWorthController::class, 'getAssetsSummary']);
    Route::get('/assets-summary-detailed', [NetWorthController::class, 'getAssetsSummaryWithDetails']);
    Route::get('/joint-assets', [NetWorthController::class, 'getJointAssets']);
    Route::post('/refresh', [NetWorthController::class, 'refresh']);
});

// Joint Account Logs routes
Route::middleware('auth:sanctum')->prefix('joint-account-logs')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\JointAccountLogController::class, 'index']);
});

// Property routes (Phase 4)
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('properties')->group(function () {
    // Property CRUD
    Route::get('/', [PropertyController::class, 'index']);
    Route::post('/', [PropertyController::class, 'store']);
    Route::get('/{id}', [PropertyController::class, 'show']);
    Route::put('/{id}', [PropertyController::class, 'update']);
    Route::delete('/{id}', [PropertyController::class, 'destroy']);

    // Tax calculations
    Route::post('/calculate-sdlt', [PropertyController::class, 'calculateSDLT']);
    Route::post('/{id}/calculate-cgt', [PropertyController::class, 'calculateCGT']);
    Route::post('/{id}/rental-income-tax', [PropertyController::class, 'calculateRentalIncomeTax']);

    // Mortgages for a property
    Route::prefix('{propertyId}/mortgages')->group(function () {
        Route::get('/', [MortgageController::class, 'index']);
        Route::post('/', [MortgageController::class, 'store']);
        Route::put('/{mortgageId}', [MortgageController::class, 'update']);
        Route::delete('/{mortgageId}', [MortgageController::class, 'destroy']);
    });
});

// Mortgage routes (Phase 4)
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('mortgages')->group(function () {
    Route::get('/{id}', [MortgageController::class, 'show']);
    Route::put('/{id}', [MortgageController::class, 'update']);
    Route::delete('/{id}', [MortgageController::class, 'destroy']);
    Route::get('/{id}/amortization-schedule', [MortgageController::class, 'amortizationSchedule']);
    Route::post('/calculate-payment', [MortgageController::class, 'calculatePayment']);
});

// Business Interest routes
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('business-interests')->group(function () {
    Route::get('/', [BusinessInterestController::class, 'index']);
    Route::post('/', [BusinessInterestController::class, 'store']);
    Route::get('/{id}', [BusinessInterestController::class, 'show']);
    Route::put('/{id}', [BusinessInterestController::class, 'update']);
    Route::delete('/{id}', [BusinessInterestController::class, 'destroy']);
    Route::get('/{id}/tax-deadlines', [BusinessInterestController::class, 'taxDeadlines']);
    Route::get('/{id}/exit-calculation', [BusinessInterestController::class, 'exitCalculation']);
});

// Chattel routes (personal property / chattels & valuables)
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('chattels')->group(function () {
    Route::get('/', [ChattelController::class, 'index']);
    Route::post('/', [ChattelController::class, 'store']);
    Route::get('/{id}', [ChattelController::class, 'show']);
    Route::put('/{id}', [ChattelController::class, 'update']);
    Route::delete('/{id}', [ChattelController::class, 'destroy']);
    Route::post('/{id}/calculate-cgt', [ChattelController::class, 'calculateCGT']);
});

// Dashboard routes (aggregated data from all modules)
Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/alerts', [DashboardController::class, 'alerts']);
    Route::post('/alerts/{id}/dismiss', [DashboardController::class, 'dismissAlert']);
    Route::post('/invalidate-cache', [DashboardController::class, 'invalidateCache']);
});

// Protection module routes
Route::middleware('auth:sanctum')->prefix('protection')->group(function () {
    // Main protection data and analysis
    Route::get('/', [ProtectionController::class, 'index']);
    Route::post('/analyze', [ProtectionController::class, 'analyze']);
    Route::get('/recommendations', [ProtectionController::class, 'recommendations']);
    Route::post('/scenarios', [ProtectionController::class, 'scenarios']);

    // Protection profile
    Route::post('/profile', [ProtectionController::class, 'storeProfile']);
    Route::patch('/profile/has-no-policies', [ProtectionController::class, 'updateHasNoPolicies']);

    // Life insurance policies
    Route::prefix('policies/life')->group(function () {
        Route::post('/', [ProtectionController::class, 'storeLifePolicy']);
        Route::put('/{id}', [ProtectionController::class, 'updateLifePolicy']);
        Route::delete('/{id}', [ProtectionController::class, 'destroyLifePolicy']);
    });

    // Critical illness policies
    Route::prefix('policies/critical-illness')->group(function () {
        Route::post('/', [ProtectionController::class, 'storeCriticalIllnessPolicy']);
        Route::put('/{id}', [ProtectionController::class, 'updateCriticalIllnessPolicy']);
        Route::delete('/{id}', [ProtectionController::class, 'destroyCriticalIllnessPolicy']);
    });

    // Income protection policies
    Route::prefix('policies/income-protection')->group(function () {
        Route::post('/', [ProtectionController::class, 'storeIncomeProtectionPolicy']);
        Route::put('/{id}', [ProtectionController::class, 'updateIncomeProtectionPolicy']);
        Route::delete('/{id}', [ProtectionController::class, 'destroyIncomeProtectionPolicy']);
    });

    // Disability policies
    Route::prefix('policies/disability')->group(function () {
        Route::post('/', [ProtectionController::class, 'storeDisabilityPolicy']);
        Route::put('/{id}', [ProtectionController::class, 'updateDisabilityPolicy']);
        Route::delete('/{id}', [ProtectionController::class, 'destroyDisabilityPolicy']);
    });

    // Sickness/Illness policies
    Route::prefix('policies/sickness-illness')->group(function () {
        Route::post('/', [ProtectionController::class, 'storeSicknessIllnessPolicy']);
        Route::put('/{id}', [ProtectionController::class, 'updateSicknessIllnessPolicy']);
        Route::delete('/{id}', [ProtectionController::class, 'destroySicknessIllnessPolicy']);
    });
});

// Savings module routes
Route::middleware('auth:sanctum')->prefix('savings')->group(function () {
    // Main savings data and analysis
    Route::get('/', [SavingsController::class, 'index']);
    Route::post('/analyze', [SavingsController::class, 'analyze']);
    Route::get('/recommendations', [SavingsController::class, 'recommendations']);
    Route::post('/scenarios', [SavingsController::class, 'scenarios']);

    // ISA allowance tracking
    Route::get('/isa-allowance/{taxYear}', [SavingsController::class, 'isaAllowance'])->where('taxYear', '.*');

    // Savings accounts
    Route::prefix('accounts')->group(function () {
        Route::post('/', [SavingsController::class, 'storeAccount']);
        Route::get('/{id}', [SavingsController::class, 'showAccount']);
        Route::put('/{id}', [SavingsController::class, 'updateAccount']);
        Route::delete('/{id}', [SavingsController::class, 'destroyAccount']);
        Route::patch('/{id}/toggle-retirement', [SavingsController::class, 'toggleRetirementInclusion']);
    });

    // Legacy savings goals - DEPRECATED since v0.7.0
    // Goals are now managed via unified Goals module: /api/goals?module=savings
    // See GoalsController for the unified API. Legacy routes removed in v0.8.1.
});

// Goals module routes (unified goals-based planning)
Route::middleware('auth:sanctum')->prefix('goals')->group(function () {
    // Main goals data and analysis
    Route::get('/', [GoalsController::class, 'index']);
    Route::get('/analysis', [GoalsController::class, 'analysis']);
    Route::get('/dashboard-overview', [GoalsController::class, 'dashboardOverview']);

    // Projection (net worth chart with events)
    Route::get('/projection', [GoalsController::class, 'getProjection']);
    Route::get('/household-summary', [GoalsController::class, 'getHouseholdSummary']);
    Route::get('/financial-forecast', [GoalsController::class, 'getFinancialForecast']);

    // Reference data
    Route::get('/types', [GoalsController::class, 'getGoalTypes']);
    Route::get('/risk-levels', [GoalsController::class, 'getRiskLevels']);

    // Property cost calculator
    Route::post('/calculate-property-costs', [GoalsController::class, 'calculatePropertyCosts']);

    // Goal CRUD
    Route::post('/', [GoalsController::class, 'store']);
    Route::get('/{id}', [GoalsController::class, 'show']);
    Route::put('/{id}', [GoalsController::class, 'update']);
    Route::delete('/{id}', [GoalsController::class, 'destroy']);

    // Goal-specific operations
    Route::post('/{id}/contribution', [GoalsController::class, 'recordContribution']);
    Route::get('/{id}/projections', [GoalsController::class, 'getProjections']);
    Route::get('/{id}/scenarios', [GoalsController::class, 'getScenarios']);
    Route::get('/{id}/contributions', [GoalsController::class, 'getContributionHistory']);

    // Goal dependencies
    Route::get('/{id}/dependencies', [GoalsController::class, 'getDependencies']);
    Route::post('/{id}/dependencies', [GoalsController::class, 'addDependency']);
    Route::delete('/{id}/dependencies/{dependsOnId}', [GoalsController::class, 'removeDependency']);
});

// Life Events routes (future occurrences impacting net worth)
Route::middleware('auth:sanctum')->prefix('life-events')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\LifeEventController::class, 'index']);
    Route::get('/types', [\App\Http\Controllers\Api\LifeEventController::class, 'getEventTypes']);
    Route::get('/by-age', [\App\Http\Controllers\Api\LifeEventController::class, 'getByAge']);
    Route::post('/', [\App\Http\Controllers\Api\LifeEventController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Api\LifeEventController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\LifeEventController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\LifeEventController::class, 'destroy']);
    Route::post('/{id}/complete', [\App\Http\Controllers\Api\LifeEventController::class, 'markCompleted']);
});

// Investment module routes
Route::middleware('auth:sanctum')->prefix('investment')->group(function () {
    // Main investment data and analysis
    Route::get('/', [InvestmentController::class, 'index']);
    Route::post('/analyze', [InvestmentController::class, 'analyze']);
    Route::get('/recommendations', [InvestmentController::class, 'recommendations']);
    Route::post('/scenarios', [InvestmentController::class, 'scenarios']);

    // Portfolio Strategy (aggregated recommendations)
    Route::get('/portfolio-strategy', [PortfolioStrategyController::class, 'index']);
    Route::get('/portfolio-strategy/account/{accountId}', [PortfolioStrategyController::class, 'forAccount']);

    // Monte Carlo simulation
    Route::post('/monte-carlo', [InvestmentController::class, 'startMonteCarlo'])->middleware('throttle:10,1');
    Route::get('/monte-carlo/{jobId}', [InvestmentController::class, 'getMonteCarloResults']);

    // Portfolio projections (Performance tab)
    Route::post('/projections', [InvestmentProjectionController::class, 'getProjections']);

    // Investment accounts
    Route::prefix('accounts')->group(function () {
        Route::post('/', [InvestmentController::class, 'storeAccount']);
        Route::put('/{id}', [InvestmentController::class, 'updateAccount']);
        Route::delete('/{id}', [InvestmentController::class, 'destroyAccount']);
        Route::get('/{id}/projections', [InvestmentController::class, 'getAccountProjections']);
        Route::get('/{id}/rebalancing', [RebalancingCalculationController::class, 'getAccountRebalancing']);
        Route::patch('/{id}/rebalancing-threshold', [RebalancingCalculationController::class, 'updateRebalancingThreshold']);
        Route::get('/{id}/diversification', [InvestmentController::class, 'getAccountDiversification']);
        Route::patch('/{id}/toggle-retirement', [InvestmentController::class, 'toggleRetirementInclusion']);
    });

    // Holdings
    Route::prefix('holdings')->group(function () {
        Route::post('/', [InvestmentController::class, 'storeHolding']);
        Route::put('/{id}', [InvestmentController::class, 'updateHolding']);
        Route::delete('/{id}', [InvestmentController::class, 'destroyHolding']);
    });

    // Legacy investment goals - DEPRECATED since v0.7.0
    // Goals are now managed via unified Goals module: /api/goals?module=investment
    // See GoalsController for the unified API. Legacy routes removed in v0.8.1.

    // Risk profile
    Route::post('/risk-profile', [InvestmentController::class, 'storeOrUpdateRiskProfile']);

    // Portfolio Optimization & Modern Portfolio Theory
    Route::prefix('optimization')->middleware('throttle:10,1')->group(function () {
        // Efficient frontier calculation
        Route::post('/efficient-frontier', [PortfolioOptimizationController::class, 'calculateEfficientFrontier']);
        Route::get('/current-position', [PortfolioOptimizationController::class, 'getCurrentPosition']);

        // Correlation analysis
        Route::get('/correlation-matrix', [PortfolioOptimizationController::class, 'getCorrelationMatrix']);

        // Optimization strategies
        Route::post('/minimize-variance', [PortfolioOptimizationController::class, 'optimizeMinimumVariance']);
        Route::post('/maximize-sharpe', [PortfolioOptimizationController::class, 'optimizeMaximumSharpe']);
        Route::post('/target-return', [PortfolioOptimizationController::class, 'optimizeTargetReturn']);
        Route::post('/risk-parity', [PortfolioOptimizationController::class, 'optimizeRiskParity']);

        // Cache management
        Route::delete('/clear-cache', [PortfolioOptimizationController::class, 'clearCache']);
    });

    // Portfolio Rebalancing with CGT Optimization
    Route::prefix('rebalancing')->group(function () {
        // Calculate rebalancing actions
        Route::post('/calculate', [RebalancingCalculationController::class, 'calculateRebalancing']);
        Route::post('/from-optimization', [RebalancingCalculationController::class, 'calculateFromOptimization']);

        // CGT-aware rebalancing
        Route::post('/compare-cgt', [RebalancingCalculationController::class, 'compareCGTStrategies']);
        Route::post('/within-cgt-allowance', [RebalancingCalculationController::class, 'rebalanceWithinCGTAllowance']);

        // Drift analysis (Phase 3.4)
        Route::post('/analyze-drift', [RebalancingCalculationController::class, 'analyzeDrift']);

        // Rebalancing strategies (Phase 3.4)
        Route::post('/evaluate-strategies', [RebalancingStrategiesController::class, 'evaluateStrategies']);
        Route::post('/threshold-strategy', [RebalancingStrategiesController::class, 'evaluateThresholdStrategy']);
        Route::post('/calendar-strategy', [RebalancingStrategiesController::class, 'evaluateCalendarStrategy']);
        Route::post('/opportunistic-strategy', [RebalancingStrategiesController::class, 'evaluateOpportunisticStrategy']);
        Route::post('/recommend-frequency', [RebalancingStrategiesController::class, 'recommendFrequency']);

        // Manage rebalancing actions
        Route::get('/actions', [RebalancingActionsController::class, 'getRebalancingActions']);
        Route::post('/save', [RebalancingActionsController::class, 'saveRebalancingActions']);
        Route::put('/actions/{id}', [RebalancingActionsController::class, 'updateRebalancingAction']);
        Route::delete('/actions/{id}', [RebalancingActionsController::class, 'deleteRebalancingAction']);
    });

    // Contribution Planning & Optimization (Phase 2.1)
    Route::prefix('contribution')->group(function () {
        // Optimize contribution strategy
        Route::post('/optimize', [ContributionOptimizerController::class, 'optimize']);

        // Affordability analysis
        Route::post('/affordability', [ContributionOptimizerController::class, 'affordability']);

        // Lump sum vs DCA comparison
        Route::post('/lump-sum-vs-dca', [ContributionOptimizerController::class, 'lumpSumVsDCA']);
    });

    // Tax Optimization Strategies
    Route::prefix('tax-optimization')->group(function () {
        // Comprehensive tax analysis
        Route::get('/analyze', [TaxOptimizationController::class, 'analyzeTaxPosition']);

        // ISA optimization
        Route::get('/isa-strategy', [TaxOptimizationController::class, 'getISAStrategy']);

        // CGT loss harvesting
        Route::get('/cgt-harvesting', [TaxOptimizationController::class, 'getCGTHarvestingOpportunities']);

        // Bed and ISA transfers
        Route::get('/bed-and-isa', [TaxOptimizationController::class, 'getBedAndISAOpportunities']);

        // Tax efficiency scoring
        Route::get('/efficiency-score', [TaxOptimizationController::class, 'getTaxEfficiencyScore']);

        // Recommendations
        Route::get('/recommendations', [TaxOptimizationController::class, 'getRecommendations']);

        // Savings calculator
        Route::post('/calculate-savings', [TaxOptimizationController::class, 'calculatePotentialSavings']);

        // Cache management
        Route::delete('/clear-cache', [TaxOptimizationController::class, 'clearCache']);
    });

    // Asset Location Optimization
    Route::prefix('asset-location')->group(function () {
        // Comprehensive analysis
        Route::get('/analyze', [AssetLocationController::class, 'analyzeAssetLocation']);

        // Placement recommendations
        Route::get('/recommendations', [AssetLocationController::class, 'getRecommendations']);

        // Tax drag calculation
        Route::get('/tax-drag', [AssetLocationController::class, 'calculateTaxDrag']);

        // Optimization score
        Route::get('/optimization-score', [AssetLocationController::class, 'getOptimizationScore']);

        // Compare account types
        Route::post('/compare-accounts', [AssetLocationController::class, 'compareAccountTypes']);

        // Cache management
        Route::delete('/clear-cache', [AssetLocationController::class, 'clearCache']);
    });

    // Performance Attribution & Benchmarking
    Route::prefix('performance')->group(function () {
        // Performance attribution analysis
        Route::get('/analyze', [PerformanceAttributionController::class, 'analyzePerformance']);

        // Benchmark comparison
        Route::get('/benchmark', [PerformanceAttributionController::class, 'compareWithBenchmark']);

        // Multi-benchmark comparison
        Route::get('/multi-benchmark', [PerformanceAttributionController::class, 'compareWithMultipleBenchmarks']);

        // Risk metrics
        Route::get('/risk-metrics', [PerformanceAttributionController::class, 'getRiskMetrics']);

        // Cache management
        Route::delete('/clear-cache', [PerformanceAttributionController::class, 'clearCache']);
    });

    // Goal Progress & Tracking
    Route::prefix('goals')->group(function () {
        // Progress analysis
        Route::get('/{goalId}/progress', [GoalProgressController::class, 'analyzeGoalProgress']);
        Route::get('/progress/all', [GoalProgressController::class, 'analyzeAllGoals']);

        // Shortfall analysis
        Route::get('/{goalId}/shortfall', [GoalProgressController::class, 'analyzeShortfall']);

        // What-if scenarios
        Route::post('/{goalId}/what-if', [GoalProgressController::class, 'generateWhatIfScenarios']);

        // Probability calculations
        Route::post('/calculate-probability', [GoalProgressController::class, 'calculateProbability']);
        Route::post('/required-contribution', [GoalProgressController::class, 'calculateRequiredContribution']);

        // Glide path recommendations
        Route::get('/glide-path', [GoalProgressController::class, 'getGlidePath']);

        // Cache management
        Route::delete('/clear-cache', [GoalProgressController::class, 'clearCache']);
    });

    // Fee Impact Analysis
    Route::prefix('fees')->group(function () {
        // Portfolio fee analysis
        Route::get('/analyze', [FeeImpactController::class, 'analyzePortfolioFees']);
        Route::get('/holdings', [FeeImpactController::class, 'analyzeHoldingFees']);

        // OCF impact
        Route::post('/ocf-impact', [FeeImpactController::class, 'calculateOCFImpact']);
        Route::get('/active-vs-passive', [FeeImpactController::class, 'compareActiveVsPassive']);
        Route::get('/alternatives/{holdingId}', [FeeImpactController::class, 'findAlternatives']);

        // Platform comparison
        Route::get('/compare-platforms', [FeeImpactController::class, 'comparePlatforms']);
        Route::post('/compare-specific', [FeeImpactController::class, 'compareSpecificPlatforms']);

        // Cache management
        Route::delete('/clear-cache', [FeeImpactController::class, 'clearCache']);
    });

    // Risk Preference (Self-select 5-level system)
    Route::prefix('risk')->group(function () {
        // Get all available risk levels with descriptions
        Route::get('/levels', [RiskPreferenceController::class, 'getLevels']);

        // User's main risk profile
        Route::get('/profile', [RiskPreferenceController::class, 'getProfile']);
        Route::post('/profile', [RiskPreferenceController::class, 'setProfile']);

        // Recalculate risk profile from financial factors
        Route::post('/recalculate', [RiskPreferenceController::class, 'recalculate']);

        // Allowed levels for product override (main level +/- 1)
        Route::get('/allowed-levels', [RiskPreferenceController::class, 'getAllowedLevels']);

        // Validate a product risk level
        Route::post('/validate-product-level', [RiskPreferenceController::class, 'validateProductLevel']);

        // Get configuration for a specific risk level
        Route::get('/config/{level}', [RiskPreferenceController::class, 'getRiskConfig']);
    });

    // Model Portfolio Builder
    Route::prefix('model-portfolio')->group(function () {
        // Model portfolios
        Route::get('/{riskLevel}', [ModelPortfolioController::class, 'getModelPortfolio']);
        Route::get('/all', [ModelPortfolioController::class, 'getAllPortfolios']);
        Route::post('/compare', [ModelPortfolioController::class, 'compareWithModel']);

        // Asset allocation optimization
        Route::get('/optimize-by-age', [ModelPortfolioController::class, 'optimizeByAge']);
        Route::post('/optimize-by-horizon', [ModelPortfolioController::class, 'optimizeByTimeHorizon']);
        Route::get('/glide-path', [ModelPortfolioController::class, 'getGlidePath']);

        // Fund recommendations
        Route::post('/funds', [ModelPortfolioController::class, 'getFundRecommendations']);
    });

    // Efficient Frontier / Modern Portfolio Theory (Phase 3.3)
    Route::prefix('efficient-frontier')->group(function () {
        // Calculate efficient frontier
        Route::post('/calculate', [EfficientFrontierController::class, 'calculateEfficientFrontier']);
        Route::get('/default', [EfficientFrontierController::class, 'calculateWithDefaults']);

        // Find optimal portfolios
        Route::post('/optimal-by-return', [EfficientFrontierController::class, 'findOptimalByReturn']);
        Route::post('/optimal-by-risk', [EfficientFrontierController::class, 'findOptimalByRisk']);

        // Portfolio analysis
        Route::post('/compare', [EfficientFrontierController::class, 'compareWithFrontier']);
        Route::post('/statistics', [EfficientFrontierController::class, 'calculateStatistics']);
        Route::get('/analyze-current', [EfficientFrontierController::class, 'analyzeCurrentPortfolio']);

        // Default assumptions
        Route::get('/default-assumptions', [EfficientFrontierController::class, 'getDefaultAssumptions']);
    });

    // Investment Scenarios (Phase 1.3)
    Route::prefix('scenarios')->group(function () {
        // Templates
        Route::get('/templates', [InvestmentScenarioController::class, 'templates']);

        // CRUD operations
        Route::get('/', [InvestmentScenarioController::class, 'index']);
        Route::post('/', [InvestmentScenarioController::class, 'store']);
        Route::get('/{id}', [InvestmentScenarioController::class, 'show']);
        Route::put('/{id}', [InvestmentScenarioController::class, 'update']);
        Route::delete('/{id}', [InvestmentScenarioController::class, 'destroy']);

        // Scenario operations
        Route::post('/{id}/run', [InvestmentScenarioController::class, 'run']);
        Route::get('/{id}/results', [InvestmentScenarioController::class, 'results']);
        Route::post('/compare', [InvestmentScenarioController::class, 'compare']);

        // Save/bookmark operations
        Route::post('/{id}/save', [InvestmentScenarioController::class, 'save']);
        Route::post('/{id}/unsave', [InvestmentScenarioController::class, 'unsave']);
    });
});

// Estate Liabilities (standard tier — part of Finances/Net Worth, not estate-only)
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('estate/liabilities')->group(function () {
    Route::post('/', [EstateController::class, 'storeLiability']);
    Route::put('/{id}', [EstateController::class, 'updateLiability']);
    Route::delete('/{id}', [EstateController::class, 'destroyLiability']);
});

// Estate read-only + IHT calculations (all tiers — used by dashboard)
Route::middleware(['auth:sanctum'])->prefix('estate')->group(function () {
    Route::get('/', [EstateController::class, 'index']);
    Route::post('/calculate-iht', [IHTController::class, 'calculateIHT']);
    Route::get('/net-worth', [EstateController::class, 'getNetWorth']);
    Route::get('/cash-flow', [EstateController::class, 'getCashFlow']);
});

// Estate Planning write operations (pro tier)
Route::middleware(['auth:sanctum', 'feature:pro'])->prefix('estate')->group(function () {

    // IHT Profile
    Route::post('/profile', [IHTController::class, 'storeOrUpdateIHTProfile']);

    // Assets
    Route::prefix('assets')->group(function () {
        Route::post('/', [EstateController::class, 'storeAsset']);
        Route::put('/{id}', [EstateController::class, 'updateAsset']);
        Route::delete('/{id}', [EstateController::class, 'destroyAsset']);
    });

    // Gifts (CRUD in EstateController, Strategy in GiftingController)
    Route::prefix('gifts')->group(function () {
        Route::get('/planned-strategy', [GiftingController::class, 'getPlannedGiftingStrategy']);
        Route::get('/personalized-strategy', [GiftingController::class, 'getPersonalizedGiftingStrategy']);
        Route::get('/trust-strategy', [GiftingController::class, 'getPersonalizedTrustStrategy']);
        Route::post('/', [EstateController::class, 'storeGift']);
        Route::put('/{id}', [EstateController::class, 'updateGift']);
        Route::delete('/{id}', [EstateController::class, 'destroyGift']);
    });

    // Life Policy Strategy
    Route::get('/life-policy-strategy', [LifePolicyController::class, 'getLifePolicyStrategy']);

    // Trusts
    Route::prefix('trusts')->group(function () {
        Route::get('/', [TrustController::class, 'getTrusts']);
        Route::post('/', [TrustController::class, 'createTrust']);
        Route::put('/{id}', [TrustController::class, 'updateTrust']);
        Route::delete('/{id}', [TrustController::class, 'deleteTrust']);
        Route::get('/{id}/analyze', [TrustController::class, 'analyzeTrust']);
        Route::get('/{id}/assets', [TrustController::class, 'getTrustAssets']);
        Route::post('/{id}/calculate-iht-impact', [TrustController::class, 'calculateTrustIHTImpact']);
    });

    // Trust planning and tax returns
    Route::get('/trust-recommendations', [TrustController::class, 'getTrustRecommendations']);
    Route::get('/trusts/upcoming-tax-returns', [TrustController::class, 'getUpcomingTaxReturns']);

    // Will Builder
    Route::prefix('will-builder')->group(function () {
        Route::get('/pre-populate', [WillDocumentController::class, 'prePopulate']);
        Route::get('/', [WillDocumentController::class, 'index']);
        Route::post('/', [WillDocumentController::class, 'store']);
        Route::get('/{id}', [WillDocumentController::class, 'show']);
        Route::put('/{id}', [WillDocumentController::class, 'update']);
        Route::post('/{id}/complete', [WillDocumentController::class, 'complete']);
        Route::post('/{id}/mirror', [WillDocumentController::class, 'generateMirror']);
        Route::get('/{id}/validate', [WillDocumentController::class, 'validateDocument']);
        Route::delete('/{id}', [WillDocumentController::class, 'destroy']);
    });

    // Will and Bequests
    Route::get('/will', [WillController::class, 'getWill']);
    Route::post('/will', [WillController::class, 'storeOrUpdateWill']);
    Route::post('/calculate-intestacy', [WillController::class, 'calculateIntestacy']);
    Route::prefix('bequests')->group(function () {
        Route::get('/', [WillController::class, 'getBequests']);
        Route::post('/', [WillController::class, 'storeBequest']);
        Route::put('/{id}', [WillController::class, 'updateBequest']);
        Route::delete('/{id}', [WillController::class, 'deleteBequest']);
    });
    Route::post('/calculate-discount', [GiftingController::class, 'calculateDiscountedGiftDiscount']);

    // Letter to Spouse cross-validation
    Route::get('/letter-validation', [LetterValidationController::class, 'checkConsistency']);

    // Lasting Powers of Attorney
    Route::prefix('lpa')->group(function () {
        Route::get('/', [LpaController::class, 'index']);
        Route::post('/', [LpaController::class, 'store']);
        Route::get('/donor-defaults', [LpaController::class, 'donorDefaults']);
        Route::post('/upload', [LpaController::class, 'upload']);
        Route::get('/{id}', [LpaController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/{id}', [LpaController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [LpaController::class, 'destroy'])->where('id', '[0-9]+');
        Route::get('/{id}/compliance', [LpaController::class, 'compliance'])->where('id', '[0-9]+');
        Route::post('/{id}/register', [LpaController::class, 'markRegistered'])->where('id', '[0-9]+');
    });
});

// Retirement module routes
Route::middleware('auth:sanctum')->prefix('retirement')->group(function () {
    // Main retirement data and analysis
    Route::get('/', [RetirementController::class, 'index']);
    Route::get('/projections', [RetirementController::class, 'getProjections']);
    Route::get('/required-capital', [RetirementController::class, 'getRequiredCapital']);
    Route::get('/dc-pensions/{id}/projections', [RetirementController::class, 'getDCPensionProjection']);
    Route::post('/analyze', [RetirementController::class, 'analyze']);
    Route::get('/recommendations', [RetirementController::class, 'recommendations']);
    Route::post('/scenarios', [RetirementController::class, 'scenarios']);

    // DC Pension Portfolio Analysis (advanced analytics)
    Route::get('/portfolio-analysis', [RetirementController::class, 'analyzeDCPensionPortfolio']);
    Route::get('/portfolio-analysis/{dcPensionId}', [RetirementController::class, 'analyzeDCPensionPortfolio']);

    // Annual allowance checking
    Route::get('/annual-allowance/{taxYear}', [RetirementController::class, 'checkAnnualAllowance'])->where('taxYear', '.*');

    // Retirement strategies
    Route::get('/strategies', [RetirementController::class, 'getStrategies']);
    Route::get('/strategies/impact', [RetirementController::class, 'calculateStrategyImpact']);

    // Retirement income (tax-optimized drawdown)
    Route::get('/income', [RetirementController::class, 'getRetirementIncome']);
    Route::post('/income/calculate', [RetirementController::class, 'calculateRetirementIncome']);
    Route::get('/income/accounts', [RetirementController::class, 'getIncomeAccounts']);

    // Decumulation analysis (drawdown strategies)
    Route::get('/decumulation-analysis', [DecumulationController::class, 'analysis']);

    // DC pensions
    Route::prefix('pensions/dc')->group(function () {
        Route::post('/', [RetirementController::class, 'storeDCPension']);
        Route::put('/{id}', [RetirementController::class, 'updateDCPension']);
        Route::delete('/{id}', [RetirementController::class, 'destroyDCPension']);

        // DC Pension Holdings (for portfolio optimization)
        Route::get('/{dcPensionId}/holdings', [DCPensionHoldingsController::class, 'index']);
        Route::post('/{dcPensionId}/holdings', [DCPensionHoldingsController::class, 'store']);
        Route::put('/{dcPensionId}/holdings/{holdingId}', [DCPensionHoldingsController::class, 'update']);
        Route::delete('/{dcPensionId}/holdings/{holdingId}', [DCPensionHoldingsController::class, 'destroy']);
        Route::post('/{dcPensionId}/holdings/bulk-update', [DCPensionHoldingsController::class, 'bulkUpdate']);
        Route::get('/{id}/diversification', [RetirementController::class, 'getDCPensionDiversification']);
    });

    // DB pensions
    Route::prefix('pensions/db')->group(function () {
        Route::post('/', [RetirementController::class, 'storeDBPension']);
        Route::put('/{id}', [RetirementController::class, 'updateDBPension']);
        Route::delete('/{id}', [RetirementController::class, 'destroyDBPension']);
    });

    // State pension
    Route::post('/state-pension', [RetirementController::class, 'updateStatePension']);
});

// Plans routes (comprehensive cross-module plans)
Route::middleware('auth:sanctum')->prefix('plans')->group(function () {
    // Plan system
    Route::get('/statuses', [\App\Http\Controllers\Api\Plans\PlanController::class, 'statuses']);
    Route::get('/goal/{goalId}', [\App\Http\Controllers\Api\Plans\PlanController::class, 'generateGoalPlan']);
    Route::post('/goal/{goalId}/recalculate', [\App\Http\Controllers\Api\Plans\PlanController::class, 'recalculateGoalPlan']);
    Route::get('/{type}', [\App\Http\Controllers\Api\Plans\PlanController::class, 'generate'])
        ->where('type', 'investment|protection|retirement|estate');
    Route::post('/{type}/recalculate', [\App\Http\Controllers\Api\Plans\PlanController::class, 'recalculate'])
        ->where('type', 'investment|protection|retirement|estate');
    Route::delete('/{type}/clear-cache', [\App\Http\Controllers\Api\Plans\PlanController::class, 'clearCache'])
        ->where('type', 'investment|protection|retirement|estate');
    Route::put('/{type}/funding-source', [\App\Http\Controllers\Api\Plans\PlanController::class, 'updateFundingSource'])
        ->where('type', 'investment|protection|retirement|estate');
});

// Household coordination routes (spousal planning)
Route::middleware('auth:sanctum')->prefix('household')->group(function () {
    Route::get('/net-worth', [HouseholdController::class, 'getNetWorth']);
    Route::get('/optimisations', [HouseholdController::class, 'getOptimisations']);
    Route::get('/death-scenario', [HouseholdController::class, 'getDeathScenario']);
});

// Holistic Planning routes (coordinating agent)
Route::middleware(['auth:sanctum', 'feature:pro'])->prefix('holistic')->group(function () {
    // Main holistic analysis and plan
    Route::post('/analyze', [HolisticPlanningController::class, 'analyze']);
    Route::post('/plan', [HolisticPlanningController::class, 'plan']);
    Route::get('/recommendations', [HolisticPlanningController::class, 'recommendations']);
    Route::get('/cash-flow-analysis', [HolisticPlanningController::class, 'cashFlowAnalysis']);

    // Recommendation tracking
    Route::post('/recommendations/{id}/mark-done', [HolisticPlanningController::class, 'markRecommendationDone']);
    Route::post('/recommendations/{id}/in-progress', [HolisticPlanningController::class, 'markRecommendationInProgress']);
    Route::post('/recommendations/{id}/dismiss', [HolisticPlanningController::class, 'dismissRecommendation']);
    Route::get('/recommendations/completed', [HolisticPlanningController::class, 'completedRecommendations']);
    Route::patch('/recommendations/{id}/notes', [HolisticPlanningController::class, 'updateRecommendationNotes']);
});

// Unified Recommendations routes (Phase 5)
Route::middleware('auth:sanctum')->prefix('recommendations')->group(function () {
    // Main recommendations endpoints
    Route::get('/', [RecommendationsController::class, 'index']);
    Route::get('/summary', [RecommendationsController::class, 'summary']);
    Route::get('/top', [RecommendationsController::class, 'top']);
    Route::get('/completed', [RecommendationsController::class, 'completed']);

    // Recommendation tracking actions
    Route::post('/{id}/mark-done', [RecommendationsController::class, 'markDone']);
    Route::post('/{id}/in-progress', [RecommendationsController::class, 'markInProgress']);
    Route::post('/{id}/dismiss', [RecommendationsController::class, 'dismiss']);
    Route::patch('/{id}/notes', [RecommendationsController::class, 'updateNotes']);
});

// Tax Product Information routes (Tax status for products)
Route::middleware('auth:sanctum')->prefix('tax-info')->group(function () {
    Route::get('/investment/{accountType}', [\App\Http\Controllers\Api\TaxProductInfoController::class, 'getInvestmentTaxInfo']);
    Route::get('/savings/{accountType}', [\App\Http\Controllers\Api\TaxProductInfoController::class, 'getSavingsTaxInfo']);
    Route::get('/summary', [\App\Http\Controllers\Api\TaxProductInfoController::class, 'getTaxSummary']);
});

// Tax Optimisation routes (cross-module tax strategies)
Route::middleware('auth:sanctum')->prefix('tax')->group(function () {
    Route::get('/optimisation-analysis', [TaxOptimisationController::class, 'getAnalysis']);
    Route::get('/strategies', [TaxOptimisationController::class, 'getStrategies']);
    Route::get('/income-definitions', [IncomeDefinitionsController::class, 'show']);
});

// Payment routes (public)
Route::prefix('payment')->group(function () {
    Route::get('/plans', [\App\Http\Controllers\Api\PaymentController::class, 'plans']);
});

// Payment routes (authenticated)
Route::middleware('auth:sanctum')->prefix('payment')->group(function () {
    Route::get('/trial-status', [\App\Http\Controllers\Api\PaymentController::class, 'trialStatus']);
    Route::get('/billing-history', [\App\Http\Controllers\Api\PaymentController::class, 'billingHistory']);
    Route::post('/create-order', [\App\Http\Controllers\Api\PaymentController::class, 'createOrder'])->middleware('throttle:10,1');
    Route::post('/confirm', [\App\Http\Controllers\Api\PaymentController::class, 'confirmPayment'])->middleware('throttle:10,1');
    Route::post('/upgrade', [\App\Http\Controllers\Api\PaymentController::class, 'upgradeSubscription'])->middleware('throttle:10,1');
    Route::post('/cancel-subscription', [\App\Http\Controllers\Api\PaymentController::class, 'cancelSubscription'])->middleware('throttle:1,1');
    Route::post('/delete-all-data', [\App\Http\Controllers\Api\PaymentController::class, 'deleteAllData'])->middleware('throttle:1,5');
    Route::post('/validate-discount', [\App\Http\Controllers\Api\PaymentController::class, 'validateDiscountCode'])->middleware('throttle:20,1');
    Route::get('/invoices/{invoice}/download', [\App\Http\Controllers\Api\PaymentController::class, 'downloadInvoice'])->middleware('throttle:10,1');
});

// Referral
Route::middleware('auth:sanctum')->prefix('referral')->group(function () {
    Route::get('/code', [\App\Http\Controllers\Api\ReferralController::class, 'getMyCode']);
    Route::post('/invite', [\App\Http\Controllers\Api\ReferralController::class, 'sendInvitation'])->middleware('throttle:10,1');
    Route::get('/list', [\App\Http\Controllers\Api\ReferralController::class, 'myReferrals']);
});

// Revolut webhook (no auth:sanctum — verified by HMAC signature)
Route::post('/webhooks/revolut', [\App\Http\Controllers\Api\WebhookController::class, 'handleRevolut'])->middleware('throttle:60,1');

// User Settings routes
Route::middleware('auth:sanctum')->prefix('settings')->group(function () {
    // Planning Assumptions
    Route::get('/assumptions', [AssumptionsController::class, 'index']);
    Route::put('/assumptions/{type}', [AssumptionsController::class, 'update']);
});

// Admin Panel routes (RBAC-protected)
Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Api\AdminController::class, 'dashboard']);

    // Roles list (for user management dropdowns)
    Route::get('/roles', [\App\Http\Controllers\Api\AdminController::class, 'getRoles']);

    // User management - view (support + admin via admin.access)
    Route::get('/users', [\App\Http\Controllers\Api\AdminController::class, 'getUsers']);

    // User management - create/update (requires users.edit)
    Route::middleware('permission:users.edit')->group(function () {
        Route::post('/users', [\App\Http\Controllers\Api\AdminController::class, 'createUser']);
        Route::put('/users/{id}', [\App\Http\Controllers\Api\AdminController::class, 'updateUser']);
    });

    // User management - delete (requires users.delete)
    Route::delete('/users/{id}', [\App\Http\Controllers\Api\AdminController::class, 'deleteUser'])
        ->middleware('permission:users.delete');

    // User module status tracking
    Route::get('users/{id}/module-status', [\App\Http\Controllers\Api\AdminController::class, 'moduleStatus']);

    // Subscription stats
    Route::get('/subscriptions/stats', [\App\Http\Controllers\Api\AdminController::class, 'getSubscriptionStats']);

    // AI provider management
    Route::get('/ai-provider', [\App\Http\Controllers\Api\AdminController::class, 'getAiProvider']);
    Route::post('/ai-provider', [\App\Http\Controllers\Api\AdminController::class, 'setAiProvider']);

    // AI Audit trail
    Route::prefix('ai-audit')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\AiAuditController::class, 'users']);
        Route::get('/users/{userId}/conversations', [\App\Http\Controllers\Api\AiAuditController::class, 'conversations']);
        Route::get('/conversations/{conversationId}/messages', [\App\Http\Controllers\Api\AiAuditController::class, 'messages']);
    });

    // Database backup - list (read-only, no rate limit)
    Route::middleware(['permission:admin.backup'])->group(function () {
        Route::get('/backup/list', [\App\Http\Controllers\Api\AdminController::class, 'listBackups']);
    });

    // Database backup - write operations (rate limited: 3 per minute)
    Route::middleware(['permission:admin.backup', 'throttle:3,1'])->group(function () {
        Route::post('/backup/create', [\App\Http\Controllers\Api\AdminController::class, 'createBackup']);
        Route::post('/backup/restore', [\App\Http\Controllers\Api\AdminController::class, 'restoreBackup']);
        Route::delete('/backup/delete', [\App\Http\Controllers\Api\AdminController::class, 'deleteBackup']);
    });

    // User Metrics
    Route::get('/user-metrics/snapshot', [\App\Http\Controllers\Api\UserMetricsController::class, 'snapshot']);
    Route::get('/user-metrics/trials', [\App\Http\Controllers\Api\UserMetricsController::class, 'trials']);
    Route::get('/user-metrics/plans', [\App\Http\Controllers\Api\UserMetricsController::class, 'plans']);
    Route::get('/user-metrics/activity', [\App\Http\Controllers\Api\UserMetricsController::class, 'activity']);
    Route::get('/user-metrics/engagement', [\App\Http\Controllers\Api\UserMetricsController::class, 'engagement']);

    // Discount Code Management
    Route::get('/discount-codes', [\App\Http\Controllers\Api\AdminController::class, 'listDiscountCodes']);
    Route::post('/discount-codes', [\App\Http\Controllers\Api\AdminController::class, 'createDiscountCode']);
    Route::put('/discount-codes/{id}', [\App\Http\Controllers\Api\AdminController::class, 'updateDiscountCode']);
    Route::delete('/discount-codes/{id}', [\App\Http\Controllers\Api\AdminController::class, 'deleteDiscountCode']);
    Route::patch('/discount-codes/{id}/toggle', [\App\Http\Controllers\Api\AdminController::class, 'toggleDiscountCode']);
});

// Retirement Action Definitions (admin-configurable plan actions)
Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])->prefix('admin/retirement-actions')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\RetirementActionDefinitionController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\RetirementActionDefinitionController::class, 'show']);
    Route::post('/', [\App\Http\Controllers\Api\RetirementActionDefinitionController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\Api\RetirementActionDefinitionController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\RetirementActionDefinitionController::class, 'destroy']);
    Route::patch('/{id}/toggle', [\App\Http\Controllers\Api\RetirementActionDefinitionController::class, 'toggleEnabled']);
});

// Investment Action Definitions (admin-configurable plan actions)
Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])->prefix('admin/investment-actions')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\InvestmentActionDefinitionController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\InvestmentActionDefinitionController::class, 'show']);
    Route::post('/', [\App\Http\Controllers\Api\InvestmentActionDefinitionController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\Api\InvestmentActionDefinitionController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\InvestmentActionDefinitionController::class, 'destroy']);
    Route::patch('/{id}/toggle', [\App\Http\Controllers\Api\InvestmentActionDefinitionController::class, 'toggleEnabled']);
});

// Protection Action Definitions (admin-configurable plan actions)
Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])->prefix('admin/protection-actions')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ProtectionActionDefinitionController::class, 'index']);
    Route::get('/{id}', [\App\Http\Controllers\Api\ProtectionActionDefinitionController::class, 'show']);
    Route::post('/', [\App\Http\Controllers\Api\ProtectionActionDefinitionController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\Api\ProtectionActionDefinitionController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\ProtectionActionDefinitionController::class, 'destroy']);
    Route::patch('/{id}/toggle', [\App\Http\Controllers\Api\ProtectionActionDefinitionController::class, 'toggleEnabled']);
});

// Generic action definition routes (for Decision Matrix)
Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])
    ->prefix('admin/action-definitions/{module}')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'store']);
        Route::patch('/{id}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'destroy']);
        Route::patch('/{id}/toggle', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'toggleEnabled']);
    });

Route::middleware(['auth:sanctum', 'permission:admin.access'])
    ->get('admin/decision-matrix/{module}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'decisionMatrix']);

// Lightweight active tax year endpoint — any authenticated user can read this.
// Returns just the tax year label and effective dates so the frontend knows
// which year to display and calculate allowances against. No sensitive admin
// config is exposed here (that stays behind permission:admin.tax_config below).
Route::middleware('auth:sanctum')->get('tax-year/current', [\App\Http\Controllers\Api\TaxYearController::class, 'current']);

// Tax Settings routes (requires tax config permission)
Route::middleware(['auth:sanctum', 'permission:admin.tax_config'])->prefix('tax-settings')->group(function () {
    Route::get('/current', [\App\Http\Controllers\Api\TaxSettingsController::class, 'getCurrent']);
    Route::get('/all', [\App\Http\Controllers\Api\TaxSettingsController::class, 'getAll']);
    Route::get('/calculations', [\App\Http\Controllers\Api\TaxSettingsController::class, 'getCalculations']);
    Route::post('/create', [\App\Http\Controllers\Api\TaxSettingsController::class, 'create']);
    Route::put('/{id}', [\App\Http\Controllers\Api\TaxSettingsController::class, 'update']);
    Route::post('/{id}/activate', [\App\Http\Controllers\Api\TaxSettingsController::class, 'setActive']);
    Route::post('/{id}/duplicate', [\App\Http\Controllers\Api\TaxSettingsController::class, 'duplicate']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\TaxSettingsController::class, 'delete']);
});

// Document Upload & AI Extraction routes (rate limited for security)
Route::middleware(['auth:sanctum', 'throttle:30,1'])->prefix('documents')->group(function () {
    Route::get('/', [DocumentController::class, 'index']);
    Route::get('/types', [DocumentController::class, 'types']);
    Route::post('/upload', [DocumentController::class, 'upload'])->middleware('throttle:10,1');
    Route::post('/upload-only', [DocumentController::class, 'uploadOnly'])->middleware('throttle:10,1');
    Route::get('/{id}', [DocumentController::class, 'show']);
    Route::get('/{id}/extraction', [DocumentController::class, 'getExtraction']);
    Route::post('/{id}/confirm', [DocumentController::class, 'confirm']);
    Route::post('/{id}/confirm-excel', [DocumentController::class, 'confirmExcel']);
    Route::post('/{id}/reprocess', [DocumentController::class, 'reprocess'])->middleware('throttle:5,1');
    Route::delete('/{id}', [DocumentController::class, 'destroy']);
});

// Postcode Lookup routes (UK address lookup via GetAddress.io)
Route::middleware(['auth:sanctum', 'throttle:30,1'])
    ->get('/postcode-lookup/{postcode}', [PostcodeLookupController::class, 'lookup']);

// Occupation search (SOC 2020)
Route::middleware('auth:sanctum')
    ->get('/occupations/search', [OccupationController::class, 'search']);

// What-If Scenarios
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('what-if-scenarios')->group(function () {
    Route::get('/', [WhatIfScenarioController::class, 'index']);
    Route::get('/count', [WhatIfScenarioController::class, 'count']);
    Route::get('/{id}', [WhatIfScenarioController::class, 'show']);
    Route::post('/', [WhatIfScenarioController::class, 'store']);
    Route::put('/{id}', [WhatIfScenarioController::class, 'update']);
    Route::delete('/{id}', [WhatIfScenarioController::class, 'destroy']);
});

// AI Chat routes
Route::middleware(['auth:sanctum', 'throttle:20,1'])->prefix('ai-chat')->group(function () {
    Route::get('/token-usage', [AiChatController::class, 'tokenUsage']);
    Route::get('/conversations', [AiChatController::class, 'index']);
    Route::post('/conversations', [AiChatController::class, 'create']);
    Route::get('/conversations/{id}', [AiChatController::class, 'show']);
    Route::delete('/conversations/{id}', [AiChatController::class, 'destroy']);
    Route::post('/conversations/{id}/messages', [AiChatController::class, 'sendMessage']);
});

// Internal Agent API routes (Python Agent SDK sidecar callbacks)
Route::prefix('internal/agent')->middleware('agent.token')->group(function () {
    Route::get('/analysis/{module}', [\App\Http\Controllers\Api\AgentInternalController::class, 'moduleAnalysis']);
    Route::get('/tax/{topic}', [\App\Http\Controllers\Api\AgentInternalController::class, 'taxInformation']);
    Route::post('/scenario', [\App\Http\Controllers\Api\AgentInternalController::class, 'scenario']);
    Route::post('/prerequisite-check', [\App\Http\Controllers\Api\AgentInternalController::class, 'prerequisiteCheck']);
    Route::get('/user-context/{userId}', [\App\Http\Controllers\Api\AgentInternalController::class, 'userContext']);
    Route::get('/recommendations', [\App\Http\Controllers\Api\AgentInternalController::class, 'recommendations']);
});

// ===========================
// Advisor Routes
// ===========================
Route::middleware(['auth:sanctum', 'advisor'])
    ->prefix('advisor')
    ->controller(\App\Http\Controllers\Api\AdvisorController::class)
    ->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('clients', 'clients');
        Route::get('clients/{id}', 'clientDetail');
        Route::get('clients/{id}/modules', 'clientModuleStatus');
        Route::post('clients/{id}/enter', 'enterClient');
        Route::post('exit', 'exitClient');
        Route::get('activities', 'activities');
        Route::post('activities', 'storeActivity');
        Route::put('activities/{id}', 'updateActivity');
        Route::get('reviews-due', 'reviewsDue');
        Route::get('reports', 'reports');
    });

// Bug Report route (works for both authenticated and guest users)
Route::post('/bug-report', [BugReportController::class, 'store'])
    ->middleware('throttle:bug-reports');
