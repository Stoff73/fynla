<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BugReportController;
use App\Http\Controllers\Api\BusinessInterestController;
use App\Http\Controllers\Api\ChattelController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\Estate\GiftingController;
use App\Http\Controllers\Api\Estate\IHTController;
use App\Http\Controllers\Api\Estate\LifePolicyController;
use App\Http\Controllers\Api\Estate\TrustController;
use App\Http\Controllers\Api\Estate\WillController;
use App\Http\Controllers\Api\EstateController;
use App\Http\Controllers\Api\FamilyMembersController;
use App\Http\Controllers\Api\GDPRController;
use App\Http\Controllers\Api\GoalsController;
use App\Http\Controllers\Api\HolisticPlanningController;
use App\Http\Controllers\Api\InfoGuideController;
use App\Http\Controllers\Api\Investment\AssetLocationController;
use App\Http\Controllers\Api\Investment\ContributionOptimizerController;
use App\Http\Controllers\Api\Investment\EfficientFrontierController;
use App\Http\Controllers\Api\Investment\FeeImpactController;
use App\Http\Controllers\Api\Investment\GoalProgressController;
use App\Http\Controllers\Api\Investment\InvestmentPlanController;
use App\Http\Controllers\Api\Investment\InvestmentRecommendationController;
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
use App\Http\Controllers\Api\LetterToSpouseController;
use App\Http\Controllers\Api\MFAController;
use App\Http\Controllers\Api\MortgageController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PostcodeLookupController;
use App\Http\Controllers\Api\NetWorthController;
use App\Http\Controllers\Api\OccupationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PersonalAccountsController;
use App\Http\Controllers\Api\Plans\InvestmentSavingsPlanController;
use App\Http\Controllers\Api\PortfolioOptimizationController;
use App\Http\Controllers\Api\PreviewController;
use App\Http\Controllers\Api\ProfileCompletenessController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ProtectionController;
use App\Http\Controllers\Api\RecommendationsController;
use App\Http\Controllers\Api\Retirement\DCPensionHoldingsController;
use App\Http\Controllers\Api\RetirementController;
use App\Http\Controllers\Api\Settings\AssumptionsController;
use App\Http\Controllers\Api\RiskPreferenceController;
use App\Http\Controllers\Api\SavingsController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SpousePermissionController;
use App\Http\Controllers\Api\UKTaxesController;
use App\Http\Controllers\Api\UserProfileController;
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
        Route::post('/resend-code', [PasswordResetController::class, 'resendCode'])->middleware('throttle:5,1');
        Route::post('/verify-mfa', [PasswordResetController::class, 'verifyMfa'])->middleware('throttle:10,1');
        Route::post('/mfa-recovery', [PasswordResetController::class, 'useMfaRecovery'])->middleware('throttle:5,1');
        Route::post('/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
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
    Route::post('/login/{personaId}', [PreviewController::class, 'login'])->middleware('throttle:10,1');

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
    Route::post('/restart', [OnboardingController::class, 'restartOnboarding']);
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
    Route::put('/letter-to-spouse', [LetterToSpouseController::class, 'update']);

    // Family Members CRUD
    Route::prefix('family-members')->group(function () {
        Route::get('/', [FamilyMembersController::class, 'index']);
        Route::post('/', [FamilyMembersController::class, 'store']);
        Route::get('/{id}', [FamilyMembersController::class, 'show']);
        Route::put('/{id}', [FamilyMembersController::class, 'update']);
        Route::delete('/{id}', [FamilyMembersController::class, 'destroy']);
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
    Route::get('/trend', [NetWorthController::class, 'getTrend']);
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
Route::middleware('auth:sanctum')->prefix('properties')->group(function () {
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
Route::middleware('auth:sanctum')->prefix('mortgages')->group(function () {
    Route::get('/{id}', [MortgageController::class, 'show']);
    Route::put('/{id}', [MortgageController::class, 'update']);
    Route::delete('/{id}', [MortgageController::class, 'destroy']);
    Route::get('/{id}/amortization-schedule', [MortgageController::class, 'amortizationSchedule']);
    Route::post('/calculate-payment', [MortgageController::class, 'calculatePayment']);
});

// Business Interest routes
Route::middleware('auth:sanctum')->prefix('business-interests')->group(function () {
    Route::get('/', [BusinessInterestController::class, 'index']);
    Route::post('/', [BusinessInterestController::class, 'store']);
    Route::get('/{id}', [BusinessInterestController::class, 'show']);
    Route::put('/{id}', [BusinessInterestController::class, 'update']);
    Route::delete('/{id}', [BusinessInterestController::class, 'destroy']);
    Route::get('/{id}/tax-deadlines', [BusinessInterestController::class, 'taxDeadlines']);
    Route::get('/{id}/exit-calculation', [BusinessInterestController::class, 'exitCalculation']);
});

// Chattel routes (personal property / chattels & valuables)
Route::middleware('auth:sanctum')->prefix('chattels')->group(function () {
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
    Route::get('/financial-health-score', [DashboardController::class, 'financialHealthScore']);
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

    // Comprehensive Protection Plan
    Route::get('/comprehensive-plan', [ProtectionController::class, 'getComprehensiveProtectionPlan']);

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

    // Savings goals
    Route::prefix('goals')->group(function () {
        Route::get('/', [SavingsController::class, 'indexGoals']);
        Route::post('/', [SavingsController::class, 'storeGoal']);
        Route::put('/{id}', [SavingsController::class, 'updateGoal']);
        Route::delete('/{id}', [SavingsController::class, 'destroyGoal']);
        Route::patch('/{id}/progress', [SavingsController::class, 'updateGoalProgress']);
    });
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
    Route::post('/monte-carlo', [InvestmentController::class, 'startMonteCarlo']);
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

    // Investment goals
    Route::prefix('goals')->group(function () {
        Route::post('/', [InvestmentController::class, 'storeGoal']);
        Route::put('/{id}', [InvestmentController::class, 'updateGoal']);
        Route::delete('/{id}', [InvestmentController::class, 'destroyGoal']);
    });

    // Risk profile
    Route::post('/risk-profile', [InvestmentController::class, 'storeOrUpdateRiskProfile']);

    // Portfolio Optimization & Modern Portfolio Theory
    Route::prefix('optimization')->group(function () {
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

    // Investment Plan Generation (Phase 1.1)
    Route::prefix('plan')->group(function () {
        // Generate comprehensive plan
        Route::post('/generate', [InvestmentPlanController::class, 'generatePlan']);

        // Get plans
        Route::get('/', [InvestmentPlanController::class, 'getLatestPlan']);
        Route::get('/all', [InvestmentPlanController::class, 'getAllPlans']);
        Route::get('/{id}', [InvestmentPlanController::class, 'getPlanById']);

        // Delete plan
        Route::delete('/{id}', [InvestmentPlanController::class, 'deletePlan']);

        // Cache management
        Route::delete('/clear-cache', [InvestmentPlanController::class, 'clearCache']);
    });

    // Investment Recommendations (Phase 1.2)
    Route::prefix('recommendations')->group(function () {
        // Dashboard/summary
        Route::get('/dashboard', [InvestmentRecommendationController::class, 'dashboard']);

        // CRUD operations
        Route::get('/', [InvestmentRecommendationController::class, 'index']);
        Route::post('/', [InvestmentRecommendationController::class, 'store']);
        Route::get('/{id}', [InvestmentRecommendationController::class, 'show']);
        Route::put('/{id}', [InvestmentRecommendationController::class, 'update']);
        Route::delete('/{id}', [InvestmentRecommendationController::class, 'destroy']);

        // Status management
        Route::put('/{id}/status', [InvestmentRecommendationController::class, 'updateStatus']);
        Route::post('/bulk-update-status', [InvestmentRecommendationController::class, 'bulkUpdateStatus']);
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

// Estate Planning module routes
Route::middleware('auth:sanctum')->prefix('estate')->group(function () {
    // Main estate data
    Route::get('/', [EstateController::class, 'index']);

    // IHT calculation and net worth
    Route::post('/calculate-iht', [IHTController::class, 'calculateIHT']);
    Route::post('/calculate-surviving-spouse-iht', [IHTController::class, 'calculateSurvivingSpouseIHT']);
    Route::post('/calculate-second-death-iht-planning', [IHTController::class, 'calculateSecondDeathIHTPlanning']);
    Route::get('/net-worth', [EstateController::class, 'getNetWorth']);
    Route::get('/cash-flow', [EstateController::class, 'getCashFlow']);

    // Comprehensive Estate Plan
    Route::get('/comprehensive-plan', [EstateController::class, 'getComprehensiveEstatePlan']);

    // IHT Profile
    Route::post('/profile', [IHTController::class, 'storeOrUpdateIHTProfile']);

    // Assets
    Route::prefix('assets')->group(function () {
        Route::post('/', [EstateController::class, 'storeAsset']);
        Route::put('/{id}', [EstateController::class, 'updateAsset']);
        Route::delete('/{id}', [EstateController::class, 'destroyAsset']);
    });

    // Liabilities
    Route::prefix('liabilities')->group(function () {
        Route::post('/', [EstateController::class, 'storeLiability']);
        Route::put('/{id}', [EstateController::class, 'updateLiability']);
        Route::delete('/{id}', [EstateController::class, 'destroyLiability']);
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
    // Investment & Savings Plan
    Route::get('/investment-savings', [InvestmentSavingsPlanController::class, 'generate']);
    Route::delete('/investment-savings/clear-cache', [InvestmentSavingsPlanController::class, 'clearCache']);
});

// Holistic Planning routes (coordinating agent)
Route::middleware('auth:sanctum')->prefix('holistic')->group(function () {
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

// Payment routes
Route::middleware('auth:sanctum')->prefix('payment')->group(function () {
    Route::post('/create-order', [\App\Http\Controllers\Api\PaymentController::class, 'createOrder']);
    Route::get('/order/{id}/status', [\App\Http\Controllers\Api\PaymentController::class, 'orderStatus']);
    Route::get('/trial-status', [\App\Http\Controllers\Api\PaymentController::class, 'trialStatus']);
});

// Revolut Webhook (public, signature-verified)
Route::post('/webhooks/revolut', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'handle']);

// User Settings routes
Route::middleware('auth:sanctum')->prefix('settings')->group(function () {
    // Planning Assumptions
    Route::get('/assumptions', [AssumptionsController::class, 'index']);
    Route::put('/assumptions/{type}', [AssumptionsController::class, 'update']);
});

// UK Taxes & Allowances routes (requires tax config permission)
Route::middleware(['auth:sanctum', 'permission:admin.tax_config'])->prefix('uk-taxes')->group(function () {
    Route::get('/', [UKTaxesController::class, 'index']);
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

    // Subscription stats
    Route::get('/subscriptions/stats', [\App\Http\Controllers\Api\AdminController::class, 'getSubscriptionStats']);

    // Database backup and restore (requires admin.backup, rate limited)
    Route::middleware(['permission:admin.backup', 'throttle:3,1'])->group(function () {
        Route::post('/backup/create', [\App\Http\Controllers\Api\AdminController::class, 'createBackup']);
        Route::get('/backup/list', [\App\Http\Controllers\Api\AdminController::class, 'listBackups']);
        Route::post('/backup/restore', [\App\Http\Controllers\Api\AdminController::class, 'restoreBackup']);
        Route::delete('/backup/delete', [\App\Http\Controllers\Api\AdminController::class, 'deleteBackup']);
    });
});

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
    Route::post('/{id}/reprocess', [DocumentController::class, 'reprocess'])->middleware('throttle:5,1');
    Route::delete('/{id}', [DocumentController::class, 'destroy']);
});

// Postcode Lookup routes (UK address lookup via GetAddress.io)
Route::middleware(['auth:sanctum', 'throttle:30,1'])
    ->get('/postcode-lookup/{postcode}', [PostcodeLookupController::class, 'lookup']);

// Occupation search (SOC 2020)
Route::middleware('auth:sanctum')
    ->get('/occupations/search', [OccupationController::class, 'search']);

// Bug Report route (works for both authenticated and guest users)
Route::post('/bug-report', [BugReportController::class, 'store'])
    ->middleware('throttle:bug-reports');
