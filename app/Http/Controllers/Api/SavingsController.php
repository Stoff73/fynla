<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\SavingsAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Savings\SavingsAnalysisRequest;
use App\Http\Requests\Savings\ScenarioRequest;
use App\Http\Requests\Savings\StoreSavingsAccountRequest;
use App\Http\Requests\Savings\StoreSavingsGoalRequest;
use App\Http\Requests\Savings\UpdateSavingsAccountRequest;
use App\Http\Requests\Savings\UpdateSavingsGoalRequest;
use App\Http\Resources\SavingsAccountResource;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\SavingsAccount;
use App\Models\SavingsGoal;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Goals\GoalStrategyService;
use App\Services\Goals\LifeEventIntegrationService;
use App\Services\NetWorth\NetWorthService;
use App\Services\Plans\SavingsPlanService;
use App\Services\Savings\FSCSAssessor;
use App\Services\Savings\ISATracker;
use App\Services\Savings\PSACalculator;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Savings Controller
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL balance in current_balance
 * - user_id = primary owner (can edit/delete)
 * - joint_owner_id = secondary owner (view access)
 * - ownership_percentage = primary owner's share (default 50 for joint)
 */
class SavingsController extends Controller
{
    use CalculatesOwnershipShare;
    use SanitizedErrorResponse;

    public function __construct(
        private readonly SavingsAgent $savingsAgent,
        private readonly ISATracker $isaTracker,
        private readonly NetWorthService $netWorthService,
        private readonly LifeEventIntegrationService $lifeEventIntegration,
        private readonly GoalStrategyService $goalStrategy,
        private readonly SavingsPlanService $savingsPlanService,
        private readonly PSACalculator $psaCalculator,
        private readonly FSCSAssessor $fscsAssessor,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Get all savings data for authenticated user
     *
     * Single-record pattern: Get accounts where user is owner OR joint_owner.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Single-record pattern: Get accounts where user is owner OR joint_owner
        $accounts = SavingsAccount::forUserOrJoint($user->id)
            ->limit(100)
            ->get();

        // Transform accounts using resource and add calculated fields
        $accounts = $accounts->map(function ($account) use ($user) {
            $resourceData = (new SavingsAccountResource($account))->toArray(request());
            $resourceData['user_share'] = $this->calculateUserShare($account, $user->id);
            $resourceData['full_balance'] = (float) $account->current_balance;
            $resourceData['is_primary_owner'] = $this->isPrimaryOwner($account, $user->id);
            $resourceData['is_shared'] = $this->isSharedOwnership($account);

            return $resourceData;
        });

        $goals = SavingsGoal::where('user_id', $user->id)->limit(100)->get();

        // Build expenditure profile from user data
        $expenditureProfile = [
            'total_monthly_expenditure' => $user->monthly_expenditure ?? 0,
            'total_annual_expenditure' => $user->annual_expenditure ?? 0,
            // Detailed breakdown
            'food_groceries' => $user->food_groceries ?? 0,
            'transport_fuel' => $user->transport_fuel ?? 0,
            'healthcare_medical' => $user->healthcare_medical ?? 0,
            'insurance' => $user->insurance ?? 0,
            'mobile_phones' => $user->mobile_phones ?? 0,
            'internet_tv' => $user->internet_tv ?? 0,
            'subscriptions' => $user->subscriptions ?? 0,
            'clothing_personal_care' => $user->clothing_personal_care ?? 0,
            'entertainment_dining' => $user->entertainment_dining ?? 0,
            'holidays_travel' => $user->holidays_travel ?? 0,
            'pets' => $user->pets ?? 0,
            'childcare' => $user->childcare ?? 0,
            'school_fees' => $user->school_fees ?? 0,
            'children_activities' => $user->children_activities ?? 0,
            'gifts_charity' => $user->gifts_charity ?? 0,
            'regular_savings' => $user->regular_savings ?? 0,
            'other_expenditure' => $user->other_expenditure ?? 0,
        ];

        // Get current tax year ISA allowance
        $currentTaxYear = $this->isaTracker->getCurrentTaxYear();
        $isaAllowance = $this->isaTracker->getISAAllowanceStatus($user->id, $currentTaxYear);

        // PSA position (Personal Savings Allowance)
        $psaPosition = null;
        try {
            $psaPosition = $this->psaCalculator->assessPSAPosition($user);
        } catch (\Throwable $e) {
            report($e);
        }

        // FSCS exposure summary
        $fscsExposure = null;
        $rawAccounts = SavingsAccount::forUserOrJoint($user->id)->get();
        if ($rawAccounts->isNotEmpty()) {
            try {
                $fscsExposure = $this->fscsAssessor->assessExposure($rawAccounts);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Employment-based emergency fund target
        $monthlyExpenditure = (float) ($user->monthly_expenditure ?? 0);
        $emergencyFundTarget = $this->buildEmergencyFundTarget($user, $monthlyExpenditure);

        // Per-child savings status
        $childrenSavings = $this->buildChildrenSavingsStatus($user, $rawAccounts);

        // Get life events and goal strategies relevant to savings/cash
        try {
            $lifeEvents = $this->lifeEventIntegration->getEventsForModule($user->id, 'savings');
            $lifeEventImpact = $this->lifeEventIntegration->getModuleImpactSummary($user->id, 'savings');
            $goalStrategies = $this->goalStrategy->getStrategiesForModule($user->id, 'savings');
            $goalsSummary = $this->goalStrategy->getModuleGoalsSummary($user->id, 'savings');
        } catch (\Throwable $e) {
            report($e);
            $lifeEvents = [];
            $lifeEventImpact = null;
            $goalStrategies = [];
            $goalsSummary = null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $accounts,
                'goals' => $goals,
                'expenditure_profile' => $expenditureProfile,
                'isa_allowance' => $isaAllowance,
                'psa_position' => $psaPosition,
                'fscs_exposure' => $fscsExposure,
                'emergency_fund_target' => $emergencyFundTarget,
                'children_savings' => $childrenSavings,
                'analysis' => null, // Placeholder for analysis data
                'life_events' => $lifeEvents,
                'life_event_impact' => $lifeEventImpact,
                'goal_strategies' => $goalStrategies,
                'goals_summary' => $goalsSummary,
            ],
        ]);
    }

    /**
     * Run comprehensive savings analysis
     */
    public function analyze(SavingsAnalysisRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $analysis = $this->savingsAgent->analyze($user->id);

            return response()->json([
                'success' => true,
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Savings analysis', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get personalized recommendations.
     *
     * Uses SavingsPlanService for the full DB-driven evaluation path.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $recommendations = $this->savingsPlanService->getRecommendations($user->id);

            return response()->json([
                'success' => true,
                'data' => $recommendations,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Savings recommendations', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Build what-if scenarios
     */
    public function scenarios(ScenarioRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $scenarios = $this->savingsAgent->buildScenarios($user->id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $scenarios,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Savings scenarios', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get ISA allowance status for a tax year
     */
    public function isaAllowance(Request $request, string $taxYear): JsonResponse
    {
        $user = $request->user();

        try {
            $allowanceStatus = $this->isaTracker->getISAAllowanceStatus($user->id, $taxYear);

            return response()->json([
                'success' => true,
                'data' => $allowanceStatus,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'ISA allowance retrieval', 500, ['user_id' => $user->id, 'tax_year' => $taxYear]);
        }
    }

    /**
     * Store a new savings account
     *
     * Single-record pattern: Store FULL balance directly, no splitting.
     */
    public function storeAccount(StoreSavingsAccountRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $data = $request->validated();
            $data['user_id'] = $user->id;

            // Set default ownership type if not provided
            $data['ownership_type'] = $data['ownership_type'] ?? 'individual';

            // Set default ownership percentage if not provided
            if (! isset($data['ownership_percentage'])) {
                $data['ownership_percentage'] = 100.00;
            }

            // For joint ownership, default to 50/50 split if not specified or 100
            if ($data['ownership_type'] === 'joint' && $data['ownership_percentage'] == 100.00) {
                $data['ownership_percentage'] = 50.00;
            }

            // ISA accounts must always be United Kingdom
            // Non-ISA accounts default to United Kingdom if not provided
            if (isset($data['is_isa']) && $data['is_isa']) {
                $data['country'] = 'United Kingdom';
            } elseif (! isset($data['country']) || $data['country'] === null) {
                $data['country'] = 'United Kingdom';
            }

            // Single-record pattern: Store FULL balance directly (no splitting)
            // current_balance already contains the full account balance from the form

            $account = SavingsAccount::create($data);

            $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $account->joint_owner_id);

            // Add calculated fields to response using resource
            $accountData = (new SavingsAccountResource($account))->toArray(request());
            $accountData['user_share'] = $this->calculateUserShare($account, $user->id);
            $accountData['full_balance'] = (float) $account->current_balance;
            $accountData['is_primary_owner'] = true;
            $accountData['is_shared'] = $this->isSharedOwnership($account);

            return response()->json([
                'success' => true,
                'message' => 'Savings account created successfully',
                'data' => $accountData,
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Creating savings account');
        }
    }

    /**
     * Get a single savings account
     *
     * Allows access if user is owner OR joint_owner.
     */
    public function showAccount(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            // Single-record pattern: Allow access if user is owner OR joint_owner
            // Load owner and joint_owner relationships
            $account = SavingsAccount::with(['user:id,first_name,surname', 'jointOwner:id,first_name,surname'])
                ->where('id', $id)
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhere('joint_owner_id', $user->id);
                })
                ->firstOrFail();

            $accountData = (new SavingsAccountResource($account))->toArray(request());
            $accountData['user_share'] = $this->calculateUserShare($account, $user->id);
            $accountData['full_balance'] = (float) $account->current_balance;
            $accountData['is_primary_owner'] = $this->isPrimaryOwner($account, $user->id);
            $accountData['is_shared'] = $this->isSharedOwnership($account);
            // Build names from first_name and surname
            $owner = $account->user;
            $jointOwner = $account->jointOwner;
            $accountData['owner_name'] = $owner ? trim(($owner->first_name ?? '').' '.($owner->surname ?? '')) : null;
            $accountData['joint_owner_name'] = $jointOwner ? trim(($jointOwner->first_name ?? '').' '.($jointOwner->surname ?? '')) : null;

            return response()->json([
                'success' => true,
                'data' => $accountData,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching savings account');
        }
    }

    /**
     * Update a savings account
     *
     * Only primary owner (user_id) can update.
     * Single-record pattern: Update the single record directly.
     */
    public function updateAccount(UpdateSavingsAccountRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            // Only primary owner can update
            $account = SavingsAccount::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Get validated data and enforce ISA country rule
            $data = $request->validated();
            if (isset($data['is_isa']) && $data['is_isa']) {
                $data['country'] = 'United Kingdom';
            }

            // Single-record pattern: Handle ownership percentage when changing to/from joint
            $ownershipType = $data['ownership_type'] ?? $account->ownership_type;
            $jointOwnerId = $data['joint_owner_id'] ?? $account->joint_owner_id;

            if ($ownershipType === 'joint' && $jointOwnerId) {
                // Switching to joint or already joint - default to 50% if not specified
                if (! isset($data['ownership_percentage'])) {
                    $data['ownership_percentage'] = 50.00;
                }
            } elseif ($ownershipType === 'individual') {
                // Switching to individual - reset to 100%
                $data['ownership_percentage'] = 100.00;
                $data['joint_owner_id'] = null;
            }

            // Single-record pattern: Update directly (no reciprocal)
            $account->update($data);

            $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $account->joint_owner_id);

            $freshAccount = $account->fresh();
            $accountData = (new SavingsAccountResource($freshAccount))->toArray(request());
            $accountData['user_share'] = $this->calculateUserShare($freshAccount, $user->id);
            $accountData['full_balance'] = (float) $freshAccount->current_balance;
            $accountData['is_primary_owner'] = true;
            $accountData['is_shared'] = $this->isSharedOwnership($freshAccount);

            return response()->json([
                'success' => true,
                'message' => 'Savings account updated successfully',
                'data' => $accountData,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Updating savings account');
        }
    }

    /**
     * Delete a savings account
     *
     * Only primary owner (user_id) can delete.
     * Single-record pattern: Delete the single record.
     */
    public function destroyAccount(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            // Only primary owner can delete
            $account = SavingsAccount::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $jointOwnerId = $account->joint_owner_id;

            // Single-record pattern: Just delete the one record
            $account->delete();

            $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $jointOwnerId);

            return response()->json([
                'success' => true,
                'message' => 'Savings account deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Deleting savings account');
        }
    }

    /**
     * Toggle include_in_retirement flag for a savings account.
     *
     * Allows users to include/exclude savings accounts from retirement income planning.
     */
    public function toggleRetirementInclusion(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            // Allow toggle if user is owner OR joint_owner
            $account = SavingsAccount::where('id', $id)
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhere('joint_owner_id', $user->id);
                })
                ->firstOrFail();

            // Toggle the flag
            $account->include_in_retirement = ! $account->include_in_retirement;
            $account->save();

            $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $account->joint_owner_id);

            return response()->json([
                'success' => true,
                'message' => $account->include_in_retirement
                    ? 'Account included in retirement planning'
                    : 'Account excluded from retirement planning',
                'data' => [
                    'id' => $account->id,
                    'include_in_retirement' => $account->include_in_retirement,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Toggling retirement inclusion');
        }
    }

    /**
     * Get all goals for authenticated user
     *
     * @deprecated Since v0.7.0. Use Goals module (GoalsController) instead. Remove by v1.0.0
     */
    public function indexGoals(Request $request): JsonResponse
    {
        $user = $request->user();
        $goals = SavingsGoal::where('user_id', $user->id)->with('linkedAccount')->get();

        return response()->json([
            'success' => true,
            'data' => $goals,
        ]);
    }

    /**
     * Store a new savings goal
     *
     * @deprecated Since v0.7.0. Use Goals module (GoalsController) instead. Remove by v1.0.0
     */
    public function storeGoal(StoreSavingsGoalRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $data = $request->validated();
            $data['user_id'] = $user->id;
            $data['current_saved'] = $data['current_saved'] ?? 0.00;

            $goal = SavingsGoal::create($data);

            $this->cacheInvalidation->invalidateForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Savings goal created successfully',
                'data' => $goal->load('linkedAccount'),
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Creating savings goal');
        }
    }

    /**
     * Update a savings goal
     *
     * @deprecated Since v0.7.0. Use Goals module (GoalsController) instead. Remove by v1.0.0
     */
    public function updateGoal(UpdateSavingsGoalRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $goal = SavingsGoal::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $goal->update($request->validated());

            $this->cacheInvalidation->invalidateForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Savings goal updated successfully',
                'data' => $goal->fresh()->load('linkedAccount'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Updating savings goal');
        }
    }

    /**
     * Delete a savings goal
     *
     * @deprecated Since v0.7.0. Use Goals module (GoalsController) instead. Remove by v1.0.0
     */
    public function destroyGoal(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $goal = SavingsGoal::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $goal->delete();

            $this->cacheInvalidation->invalidateForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Savings goal deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Deleting savings goal');
        }
    }

    /**
     * Build employment-based emergency fund target.
     */
    private function buildEmergencyFundTarget($user, float $monthlyExpenditure): array
    {
        $baseMonths = 6;

        if (! empty($user->employment_status)) {
            $targetMonths = match ($user->employment_status) {
                'self_employed', 'contractor', 'freelance' => 9,
                'unemployed', 'career_break' => 12,
                default => $baseMonths,
            };
        } else {
            $targetMonths = $baseMonths;
        }

        return [
            'target_months' => $targetMonths,
            'target_amount' => round($monthlyExpenditure * $targetMonths, 2),
            'employment_status' => $user->employment_status ?? null,
            'rationale' => match ($targetMonths) {
                9 => 'Self-employed and contractor income can be irregular, so a larger buffer is recommended.',
                12 => 'During periods without employment, a 12-month fund provides essential security.',
                default => 'The standard recommendation is 6 months of essential expenditure.',
            },
        ];
    }

    /**
     * Build per-child savings status including Junior ISA details.
     */
    private function buildChildrenSavingsStatus($user, $accounts): array
    {
        $children = $user->familyMembers()
            ->where('relationship', 'child')
            ->get();

        if ($children->isEmpty()) {
            return [];
        }

        $jisaAllowance = 9000.0;
        try {
            $isaAllowances = app(\App\Services\TaxConfigService::class)->getISAAllowances();
            $jisaAllowance = (float) ($isaAllowances['junior_isa']['annual_allowance'] ?? 9000);
        } catch (\Throwable $e) {
            // Use default
        }

        return $children->map(function ($child) use ($accounts, $jisaAllowance) {
            $dob = $child->date_of_birth;
            $age = $dob ? (int) \Carbon\Carbon::parse($dob)->age : null;
            $isUnder18 = $age !== null && $age < 18;

            // Find JISA accounts for this child
            $jisaAccounts = $accounts->filter(
                fn ($a) => $a->is_isa && $a->isa_type === 'junior_isa' && $a->beneficiary_id === $child->id
            );

            $totalJisaBalance = $jisaAccounts->sum('current_balance');
            $totalJisaSubscription = $jisaAccounts->sum('isa_subscription_amount');
            $jisaRemaining = max(0, $jisaAllowance - (float) $totalJisaSubscription);

            // Find non-JISA savings for this child
            $otherAccounts = $accounts->filter(
                fn ($a) => $a->beneficiary_id === $child->id && (! $a->is_isa || $a->isa_type !== 'junior_isa')
            );
            $totalOtherBalance = $otherAccounts->sum('current_balance');

            return [
                'child_id' => $child->id,
                'child_name' => $child->name,
                'age' => $age,
                'is_under_18' => $isUnder18,
                'has_jisa' => $jisaAccounts->isNotEmpty(),
                'jisa_balance' => round((float) $totalJisaBalance, 2),
                'jisa_allowance' => $jisaAllowance,
                'jisa_used' => round((float) $totalJisaSubscription, 2),
                'jisa_remaining' => round($jisaRemaining, 2),
                'other_savings_balance' => round((float) $totalOtherBalance, 2),
                'total_savings' => round((float) $totalJisaBalance + (float) $totalOtherBalance, 2),
            ];
        })->values()->toArray();
    }
}
