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
use App\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SavingsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private SavingsAgent $savingsAgent,
        private ISATracker $isaTracker,
        private NetWorthService $netWorthService
    ) {}

    /**
     * Get all savings data for authenticated user
     *
     * Note: Joint accounts are already handled via reciprocal records.
     * Each spouse has their own record with their share, so we only
     * need to fetch the user's own accounts.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get user's own accounts (includes their joint account records)
        $accounts = SavingsAccount::where('user_id', $user->id)->get();

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

            // Split the current_balance based on ownership percentage
            $totalBalance = $data['current_balance'];
            $userOwnershipPercentage = $data['ownership_percentage'];
            $data['current_balance'] = $totalBalance * ($userOwnershipPercentage / 100);

            $account = SavingsAccount::create($data);

            // If joint ownership, create reciprocal account for joint owner
            if (isset($data['ownership_type']) && $data['ownership_type'] === 'joint' && isset($data['joint_owner_id'])) {
                $this->createJointSavingsAccount($account, $data['joint_owner_id'], $userOwnershipPercentage, $totalBalance);
            }

            // Invalidate cache
            Cache::forget("savings_analysis_{$user->id}");
            $this->netWorthService->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Savings account created successfully',
                'data' => $account,
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
     */
    public function showAccount(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $account = SavingsAccount::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $account,
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
     */
    public function updateAccount(UpdateSavingsAccountRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $account = SavingsAccount::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $account->update($request->validated());

            // If this is a joint account, also update the reciprocal account
            if ($account->joint_owner_id) {
                $reciprocalAccount = SavingsAccount::where('user_id', $account->joint_owner_id)
                    ->where('joint_owner_id', $user->id)
                    ->where('institution', $account->institution)
                    ->where('current_balance', $account->current_balance)
                    ->first();

                if ($reciprocalAccount) {
                    $reciprocalAccount->update($request->validated());
                    // Invalidate cache for joint owner
                    Cache::forget("savings_analysis_{$account->joint_owner_id}");
                    $this->netWorthService->invalidateCache($account->joint_owner_id);
                }
            }

            // Invalidate cache
            Cache::forget("savings_analysis_{$user->id}");
            $this->netWorthService->invalidateCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Savings account updated successfully',
                'data' => $account->fresh(),
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
     */
    public function destroyAccount(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $account = SavingsAccount::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // If this is a joint account, also delete the reciprocal account
            if ($account->joint_owner_id) {
                $reciprocalAccount = SavingsAccount::where('user_id', $account->joint_owner_id)
                    ->where('joint_owner_id', $user->id)
                    ->where('institution', $account->institution)
                    ->where('current_balance', $account->current_balance)
                    ->first();

                if ($reciprocalAccount) {
                    $reciprocalAccount->delete();
                    // Invalidate cache for joint owner
                    Cache::forget("savings_analysis_{$account->joint_owner_id}");
                    $this->netWorthService->invalidateCache($account->joint_owner_id);
                }
            }

            $account->delete();

            // Invalidate cache
            Cache::forget("savings_analysis_{$user->id}");
            $this->netWorthService->invalidateCache($user->id);

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

    /**
     * Create a reciprocal savings account record for joint owner
     * Follows the same pattern as Property joint ownership
     */
    private function createJointSavingsAccount(SavingsAccount $originalAccount, int $jointOwnerId, float $ownershipPercentage, float $totalBalance): void
    {
        // Calculate the reciprocal ownership percentage
        $reciprocalPercentage = 100.00 - $ownershipPercentage;

        // Get joint owner
        $jointOwner = \App\Models\User::findOrFail($jointOwnerId);

        // Create the reciprocal account
        $jointAccountData = $originalAccount->toArray();

        // Remove auto-generated fields
        unset($jointAccountData['id'], $jointAccountData['created_at'], $jointAccountData['updated_at']);

        // Update fields for joint owner
        $jointAccountData['user_id'] = $jointOwnerId;
        $jointAccountData['joint_owner_id'] = $originalAccount->user_id;
        $jointAccountData['ownership_percentage'] = $reciprocalPercentage;

        // Calculate joint owner's share of the total balance
        $jointAccountData['current_balance'] = $totalBalance * ($reciprocalPercentage / 100);

        $jointAccount = SavingsAccount::create($jointAccountData);

        // Update original account with joint_owner_id
        $originalAccount->update(['joint_owner_id' => $jointOwnerId]);

        // Invalidate cache for joint owner
        Cache::forget("savings_analysis_{$jointOwnerId}");
        $this->netWorthService->invalidateCache($jointOwnerId);
    }
}
