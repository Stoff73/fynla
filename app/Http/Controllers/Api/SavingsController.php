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
use App\Models\SavingsAccount;
use App\Models\SavingsGoal;
use App\Services\NetWorth\NetWorthService;
use App\Services\Savings\ISATracker;
use App\Traits\CalculatesOwnershipShare;
use App\Http\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
        private SavingsAgent $savingsAgent,
        private ISATracker $isaTracker,
        private NetWorthService $netWorthService
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
        $accounts = SavingsAccount::where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id)
            ->get();

        // Add calculated fields for each account
        $accounts = $accounts->map(function ($account) use ($user) {
            $accountData = $account->toArray();
            $accountData['user_share'] = $this->calculateUserShare($account, $user->id);
            $accountData['full_balance'] = (float) $account->current_balance;
            $accountData['is_primary_owner'] = $this->isPrimaryOwner($account, $user->id);
            $accountData['is_shared'] = $this->isSharedOwnership($account);

            return $accountData;
        });

        $goals = SavingsGoal::where('user_id', $user->id)->get();

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

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $accounts,
                'goals' => $goals,
                'expenditure_profile' => $expenditureProfile,
                'isa_allowance' => $isaAllowance,
                'analysis' => null, // Placeholder for analysis data
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
     * Get personalized recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $analysis = $this->savingsAgent->analyze($user->id);
            $recommendations = $this->savingsAgent->generateRecommendations($analysis);

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

            // Single-record pattern: Store FULL balance directly (no splitting)
            // current_balance already contains the full account balance from the form

            $account = SavingsAccount::create($data);

            // Invalidate cache
            Cache::forget("savings_analysis_{$user->id}");
            $this->netWorthService->invalidateCache($user->id);

            // Add calculated fields to response
            $accountData = $account->toArray();
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to create account: '.$e->getMessage(),
            ], 500);
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
            $account = SavingsAccount::where('id', $id)
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhere('joint_owner_id', $user->id);
                })
                ->firstOrFail();

            $accountData = $account->toArray();
            $accountData['user_share'] = $this->calculateUserShare($account, $user->id);
            $accountData['full_balance'] = (float) $account->current_balance;
            $accountData['is_primary_owner'] = $this->isPrimaryOwner($account, $user->id);
            $accountData['is_shared'] = $this->isSharedOwnership($account);

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
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch account: '.$e->getMessage(),
            ], 500);
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

            // Single-record pattern: Update directly (no reciprocal)
            $account->update($request->validated());

            // Invalidate cache
            Cache::forget("savings_analysis_{$user->id}");
            $this->netWorthService->invalidateCache($user->id);

            // Also invalidate cache for joint owner if applicable
            if ($account->joint_owner_id) {
                Cache::forget("savings_analysis_{$account->joint_owner_id}");
                $this->netWorthService->invalidateCache($account->joint_owner_id);
            }

            $accountData = $account->fresh()->toArray();
            $accountData['user_share'] = $this->calculateUserShare($account->fresh(), $user->id);
            $accountData['full_balance'] = (float) $account->fresh()->current_balance;
            $accountData['is_primary_owner'] = true;
            $accountData['is_shared'] = $this->isSharedOwnership($account->fresh());

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
            return response()->json([
                'success' => false,
                'message' => 'Failed to update account: '.$e->getMessage(),
            ], 500);
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

            // Invalidate cache
            Cache::forget("savings_analysis_{$user->id}");
            $this->netWorthService->invalidateCache($user->id);

            // Also invalidate cache for joint owner if applicable
            if ($jointOwnerId) {
                Cache::forget("savings_analysis_{$jointOwnerId}");
                $this->netWorthService->invalidateCache($jointOwnerId);
            }

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
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all goals for authenticated user
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
     */
    public function storeGoal(StoreSavingsGoalRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $data = $request->validated();
            $data['user_id'] = $user->id;
            $data['current_saved'] = $data['current_saved'] ?? 0.00;

            $goal = SavingsGoal::create($data);

            // Invalidate cache
            Cache::forget("savings_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Savings goal created successfully',
                'data' => $goal->load('linkedAccount'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create goal: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a savings goal
     */
    public function updateGoal(UpdateSavingsGoalRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $goal = SavingsGoal::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $goal->update($request->validated());

            // Invalidate cache
            Cache::forget("savings_analysis_{$user->id}");

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
            return response()->json([
                'success' => false,
                'message' => 'Failed to update goal: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a savings goal
     */
    public function destroyGoal(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $goal = SavingsGoal::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $goal->delete();

            // Invalidate cache
            Cache::forget("savings_analysis_{$user->id}");

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
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete goal: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update progress for a savings goal
     */
    public function updateGoalProgress(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        try {
            $goal = SavingsGoal::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $goal->current_saved = $request->input('amount');
            $goal->save();

            // Invalidate cache
            Cache::forget("savings_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Goal progress updated successfully',
                'data' => $goal->fresh()->load('linkedAccount'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update goal progress: '.$e->getMessage(),
            ], 500);
        }
    }
}
