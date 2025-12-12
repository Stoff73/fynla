<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\InvestmentAgent;
use App\Http\Controllers\Controller;
use App\Jobs\RunMonteCarloSimulation;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\InvestmentGoal;
use App\Models\Investment\RiskProfile;
use App\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvestmentController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private InvestmentAgent $investmentAgent
    ) {}

    /**
     * Get all investment data for user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get user's own accounts (includes their share of joint accounts via reciprocal records)
        $accounts = InvestmentAccount::where('user_id', $user->id)
            ->with('holdings')
            ->get();

        $goals = InvestmentGoal::where('user_id', $user->id)->get();
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'accounts' => $accounts,
                'goals' => $goals,
                'risk_profile' => $riskProfile,
            ],
        ]);
    }

    /**
     * Run comprehensive portfolio analysis
     */
    public function analyze(Request $request): JsonResponse
    {
        $user = $request->user();

        $analysis = $this->investmentAgent->analyze($user->id);

        if (isset($analysis['message'])) {
            return response()->json([
                'success' => true,
                'data' => $analysis,
            ]);
        }

        $recommendations = $this->investmentAgent->generateRecommendations($analysis);

        return response()->json([
            'success' => true,
            'data' => [
                'analysis' => $analysis,
                'recommendations' => $recommendations,
            ],
        ]);
    }

    /**
     * Get recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        $analysis = $this->investmentAgent->analyze($user->id);
        $recommendations = $this->investmentAgent->generateRecommendations($analysis);

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    /**
     * Build scenarios
     */
    public function scenarios(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monthly_contribution' => 'nullable|numeric|min:0',
        ]);

        $user = $request->user();
        $scenarios = $this->investmentAgent->buildScenarios($user->id, $validated);

        return response()->json([
            'success' => true,
            'data' => $scenarios,
        ]);
    }

    /**
     * Start Monte Carlo simulation (dispatch queue job)
     */
    public function startMonteCarlo(Request $request): JsonResponse
    {
        \Log::info('Monte Carlo request data:', $request->all());

        try {
            $validated = $request->validate([
                'start_value' => 'required|numeric|min:0',
                'monthly_contribution' => 'required|numeric|min:0',
                'expected_return' => 'required|numeric|min:0|max:0.5',
                'volatility' => 'required|numeric|min:0|max:1',
                'years' => 'required|integer|min:1|max:50',
                'iterations' => 'nullable|integer|min:100|max:10000',
                'goal_amount' => 'nullable|numeric|min:0',
            ]);

            \Log::info('Monte Carlo validation passed', $validated);

            // Generate unique job ID
            $jobId = Str::uuid()->toString();

            \Log::info('Generated job ID:', ['job_id' => $jobId]);

            // Dispatch job
            RunMonteCarloSimulation::dispatch(
                $jobId,
                $validated['start_value'],
                $validated['monthly_contribution'],
                $validated['expected_return'],
                $validated['volatility'],
                $validated['years'],
                $validated['iterations'] ?? 1000,
                $validated['goal_amount'] ?? null
            );

            \Log::info('Monte Carlo job dispatched successfully', ['job_id' => $jobId]);

            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'queued',
                    'message' => 'Monte Carlo simulation started',
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Monte Carlo error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start Monte Carlo simulation: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Monte Carlo simulation results
     */
    public function getMonteCarloResults(string $jobId): JsonResponse
    {
        \Log::info("Checking Monte Carlo results for job: {$jobId}");

        $status = Cache::get("monte_carlo_status_{$jobId}");

        \Log::info("Monte Carlo status for {$jobId}: ".($status ?? 'NULL'));

        if (! $status) {
            // List all cache keys to debug
            $allKeys = Cache::get('_all_monte_carlo_keys', []);
            \Log::warning("Job {$jobId} not found in cache. Status is NULL");

            return response()->json([
                'success' => false,
                'message' => 'Job not found',
            ], 404);
        }

        if ($status === 'running') {
            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'running',
                    'message' => 'Simulation in progress',
                ],
            ]);
        }

        if ($status === 'failed') {
            $error = Cache::get("monte_carlo_error_{$jobId}", 'Unknown error');

            return response()->json([
                'success' => false,
                'message' => 'Simulation failed: '.$error,
            ], 500);
        }

        // Status is 'completed'
        $results = Cache::get("monte_carlo_results_{$jobId}");

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $jobId,
                'status' => 'completed',
                'results' => $results,
            ],
        ]);
    }

    // ==================== Account CRUD ====================

    /**
     * Store a new investment account
     */
    public function storeAccount(Request $request): JsonResponse
    {
        // Log incoming request data for debugging
        \Log::info('Investment account creation attempt', ['data' => $request->all()]);

        $validated = $request->validate([
            'account_type' => ['required', Rule::in(['isa', 'gia', 'nsi', 'onshore_bond', 'offshore_bond', 'vct', 'eis', 'other'])],
            'account_type_other' => 'required_if:account_type,other|nullable|string|max:255',
            'provider' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:255',
            'current_value' => 'required|numeric|min:0',
            'contributions_ytd' => 'nullable|numeric|min:0',
            'tax_year' => 'required|string|max:10',
            'platform_fee_percent' => 'nullable|numeric|min:0|max:100',
            'isa_type' => ['nullable', Rule::in(['stocks_and_shares', 'lifetime', 'innovative_finance'])],
            'isa_subscription_current_year' => 'nullable|numeric|min:0|max:20000',
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'trust'])],
            'joint_owner_id' => 'nullable|exists:users,id',
            'trust_id' => 'nullable|exists:trusts,id',
        ]);

        $user = $request->user();
        $validated['user_id'] = $user->id;

        // Set default ownership type if not provided
        $validated['ownership_type'] = $validated['ownership_type'] ?? 'individual';

        // ISA validation: ISAs can only be individually owned (UK tax rule)
        if ($validated['account_type'] === 'isa' && $validated['ownership_type'] !== 'individual') {
            return response()->json([
                'success' => false,
                'message' => 'ISAs can only be individually owned. Joint or trust ownership is not permitted for ISAs under UK tax rules.',
            ], 422);
        }

        // IMPORTANT: For joint ownership, divide the current_value by 2 and store each user's share
        // This ensures consistency with properties and other assets
        if ($validated['ownership_type'] === 'joint' && isset($validated['joint_owner_id'])) {
            $validated['ownership_percentage'] = 50.00;
            $validated['current_value'] = $validated['current_value'] / 2;
            $validated['contributions_ytd'] = ($validated['contributions_ytd'] ?? 0) / 2;
            $validated['isa_subscription_current_year'] = ($validated['isa_subscription_current_year'] ?? 0) / 2;
        } else {
            $validated['ownership_percentage'] = $validated['ownership_percentage'] ?? 100.00;
        }

        $account = InvestmentAccount::create($validated);

        // Automatically create a Cash holding for 100% of the account value
        // This will be reduced as users add other holdings
        Holding::create([
            'holdable_id' => $account->id,
            'holdable_type' => InvestmentAccount::class,
            'asset_type' => 'cash',
            'security_name' => 'Cash',
            'allocation_percent' => 100.00,
            'current_value' => $account->current_value,
            'quantity' => null,
            'purchase_price' => null,
            'purchase_date' => null,
            'current_price' => null,
            'cost_basis' => null,
            'ocf_percent' => 0.00,
        ]);

        // If joint ownership, create reciprocal account for joint owner
        if (isset($validated['ownership_type']) && $validated['ownership_type'] === 'joint' && isset($validated['joint_owner_id'])) {
            $this->createJointInvestmentAccount($account, $validated['joint_owner_id']);
        }

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        return response()->json([
            'success' => true,
            'data' => $account->load('holdings'), // Include the cash holding in response
        ], 201);
    }

    /**
     * Update an investment account
     */
    public function updateAccount(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $account = InvestmentAccount::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'account_type' => ['nullable', Rule::in(['isa', 'gia', 'nsi', 'onshore_bond', 'offshore_bond', 'vct', 'eis', 'other'])],
            'account_type_other' => 'required_if:account_type,other|nullable|string|max:255',
            'provider' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:255',
            'current_value' => 'nullable|numeric|min:0',
            'contributions_ytd' => 'nullable|numeric|min:0',
            'tax_year' => 'nullable|string|max:10',
            'platform_fee_percent' => 'nullable|numeric|min:0|max:100',
            'isa_type' => ['nullable', Rule::in(['stocks_and_shares', 'lifetime', 'innovative_finance'])],
            'isa_subscription_current_year' => 'nullable|numeric|min:0|max:20000',
        ]);

        $account->update($validated);

        // If this is a joint account, also update the reciprocal account
        if ($account->joint_owner_id) {
            $reciprocalAccount = InvestmentAccount::where('user_id', $account->joint_owner_id)
                ->where('joint_owner_id', $user->id)
                ->where('provider', $account->provider)
                ->where('current_value', $account->current_value)
                ->first();

            if ($reciprocalAccount) {
                $reciprocalAccount->update($validated);
                // Clear cache for joint owner
                $this->investmentAgent->clearCache($account->joint_owner_id);
            }
        }

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        return response()->json([
            'success' => true,
            'data' => $account->fresh(),
        ]);
    }

    /**
     * Delete an investment account
     */
    public function destroyAccount(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $account = InvestmentAccount::where('user_id', $user->id)->findOrFail($id);

        // If this is a joint account, also delete the reciprocal account
        if ($account->joint_owner_id) {
            $reciprocalAccount = InvestmentAccount::where('user_id', $account->joint_owner_id)
                ->where('joint_owner_id', $user->id)
                ->where('provider', $account->provider)
                ->where('current_value', $account->current_value)
                ->first();

            if ($reciprocalAccount) {
                $reciprocalAccount->delete();
                // Clear cache for joint owner
                $this->investmentAgent->clearCache($account->joint_owner_id);
            }
        }

        $account->delete();

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }

    // ==================== Holding CRUD ====================

    /**
     * Store a new holding
     */
    public function storeHolding(Request $request): JsonResponse
    {
        $user = $request->user();

        \Log::info('Holding creation request data:', $request->all());

        $validated = $request->validate([
            'investment_account_id' => 'required|exists:investment_accounts,id',
            'asset_type' => ['required', Rule::in(['equity', 'bond', 'fund', 'etf', 'alternative', 'uk_equity', 'us_equity', 'international_equity', 'cash', 'property'])],
            'security_name' => 'required|string|max:255',
            'ticker' => 'nullable|string|max:50',
            'isin' => 'nullable|string|max:50',
            'allocation_percent' => 'required|numeric|min:0|max:100',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'current_price' => 'nullable|numeric|min:0',
            'current_value' => 'required|numeric|min:0',
            'dividend_yield' => 'nullable|numeric|min:0|max:100',
            'ocf_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        // Verify account belongs to user
        $account = InvestmentAccount::where('id', $validated['investment_account_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Calculate cost_basis if purchase price is provided
        if (isset($validated['purchase_price']) && isset($validated['current_price'])) {
            // Calculate quantity from current value and price if both prices are provided
            $validated['quantity'] = $validated['current_value'] / $validated['current_price'];
            $validated['cost_basis'] = $validated['quantity'] * $validated['purchase_price'];
        } else {
            // No price data, set quantity and cost_basis to null
            $validated['quantity'] = null;
            $validated['cost_basis'] = null;
        }

        $holding = Holding::create($validated);

        // Auto-adjust Cash holding allocation
        $this->adjustCashHolding($account);

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        // Clear optimization caches (efficient frontier, correlation matrix)
        PortfolioOptimizationController::clearUserOptimizationCache($user->id);

        return response()->json([
            'success' => true,
            'data' => $holding,
        ], 201);
    }

    /**
     * Automatically adjust the Cash holding allocation based on other holdings
     */
    private function adjustCashHolding(InvestmentAccount $account): void
    {
        // Find the cash holding for this account
        $cashHolding = Holding::where('holdable_type', InvestmentAccount::class)
            ->where('holdable_id', $account->id)
            ->where('asset_type', 'cash')
            ->first();

        if (! $cashHolding) {
            return; // No cash holding to adjust
        }

        // Calculate total allocation of non-cash holdings
        $nonCashAllocation = Holding::where('holdable_type', InvestmentAccount::class)
            ->where('holdable_id', $account->id)
            ->where('asset_type', '!=', 'cash')
            ->sum('allocation_percent');

        // Cash holding is the remaining allocation
        $cashAllocation = max(0, 100 - $nonCashAllocation);

        // Update cash holding
        $cashHolding->update([
            'allocation_percent' => $cashAllocation,
            'current_value' => ($account->current_value * $cashAllocation) / 100,
        ]);
    }

    /**
     * Update a holding
     */
    public function updateHolding(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Find holding through account ownership
        $holding = Holding::whereHas('investmentAccount', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($id);

        $validated = $request->validate([
            'asset_type' => ['nullable', Rule::in(['equity', 'bond', 'fund', 'etf', 'alternative', 'uk_equity', 'us_equity', 'international_equity', 'cash', 'property'])],
            'security_name' => 'nullable|string|max:255',
            'ticker' => 'nullable|string|max:50',
            'isin' => 'nullable|string|max:50',
            'allocation_percent' => 'nullable|numeric|min:0|max:100',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'current_price' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'dividend_yield' => 'nullable|numeric|min:0|max:100',
            'ocf_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        // Recalculate quantity and cost_basis if prices are provided
        if (isset($validated['current_value']) && isset($validated['current_price']) && $validated['current_price'] > 0) {
            $validated['quantity'] = $validated['current_value'] / $validated['current_price'];

            if (isset($validated['purchase_price'])) {
                $validated['cost_basis'] = $validated['quantity'] * $validated['purchase_price'];
            }
        }

        $holding->update($validated);

        // Auto-adjust Cash holding allocation if allocation changed
        if (isset($validated['allocation_percent'])) {
            $this->adjustCashHolding($holding->investmentAccount);
        }

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        // Clear optimization caches (efficient frontier, correlation matrix)
        PortfolioOptimizationController::clearUserOptimizationCache($user->id);

        return response()->json([
            'success' => true,
            'data' => $holding->fresh(),
        ]);
    }

    /**
     * Delete a holding
     */
    public function destroyHolding(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $holding = Holding::whereHas('investmentAccount', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($id);

        // Store the account before deleting holding
        $account = $holding->investmentAccount;

        $holding->delete();

        // Auto-adjust Cash holding allocation after deletion
        $this->adjustCashHolding($account);

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        // Clear optimization caches (efficient frontier, correlation matrix)
        PortfolioOptimizationController::clearUserOptimizationCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Holding deleted successfully',
        ]);
    }

    // ==================== Goal CRUD ====================

    /**
     * Store a new goal
     */
    public function storeGoal(Request $request): JsonResponse
    {
        \Log::info('Goal creation request data:', $request->all());

        $validated = $request->validate([
            'goal_name' => 'required|string|max:255',
            'goal_type' => ['required', Rule::in(['retirement', 'education', 'wealth', 'home'])],
            'target_amount' => 'required|numeric|min:0',
            'target_date' => 'required|date',
            'priority' => ['nullable', Rule::in(['high', 'medium', 'low'])],
            'is_essential' => 'nullable|boolean',
            'linked_account_ids' => 'nullable|array',
        ]);

        $user = $request->user();
        $validated['user_id'] = $user->id;

        $goal = InvestmentGoal::create($validated);

        return response()->json([
            'success' => true,
            'data' => $goal,
        ], 201);
    }

    /**
     * Update a goal
     */
    public function updateGoal(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $goal = InvestmentGoal::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'goal_name' => 'nullable|string|max:255',
            'goal_type' => ['nullable', Rule::in(['retirement', 'education', 'wealth', 'home'])],
            'target_amount' => 'nullable|numeric|min:0',
            'target_date' => 'nullable|date',
            'priority' => ['nullable', Rule::in(['high', 'medium', 'low'])],
            'is_essential' => 'nullable|boolean',
            'linked_account_ids' => 'nullable|array',
        ]);

        $goal->update($validated);

        return response()->json([
            'success' => true,
            'data' => $goal->fresh(),
        ]);
    }

    /**
     * Delete a goal
     */
    public function destroyGoal(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $goal = InvestmentGoal::where('user_id', $user->id)->findOrFail($id);

        $goal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Goal deleted successfully',
        ]);
    }

    // ==================== Risk Profile ====================

    /**
     * Store or update risk profile
     */
    public function storeOrUpdateRiskProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'risk_tolerance' => ['required', Rule::in(['cautious', 'balanced', 'adventurous'])],
            'capacity_for_loss_percent' => 'required|numeric|min:0|max:100',
            'time_horizon_years' => 'required|integer|min:0|max:100',
            'knowledge_level' => ['required', Rule::in(['novice', 'intermediate', 'experienced'])],
            'attitude_to_volatility' => 'nullable|string|max:255',
            'esg_preference' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $validated['user_id'] = $user->id;

        $riskProfile = RiskProfile::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        return response()->json([
            'success' => true,
            'data' => $riskProfile,
        ]);
    }

    /**
     * Create a reciprocal investment account record for joint owner
     *
     * IMPORTANT: The originalAccount should already have current_value divided by 2 (each user's share)
     * and ownership_percentage set to 50. We just copy these values to the joint owner's record.
     */
    private function createJointInvestmentAccount(InvestmentAccount $originalAccount, int $jointOwnerId): void
    {
        // Get joint owner
        $jointOwner = \App\Models\User::findOrFail($jointOwnerId);

        // Create the reciprocal account (values already divided in original account)
        $jointAccountData = $originalAccount->toArray();

        // Remove auto-generated fields
        unset($jointAccountData['id'], $jointAccountData['created_at'], $jointAccountData['updated_at']);

        // Update fields for joint owner
        $jointAccountData['user_id'] = $jointOwnerId;
        $jointAccountData['joint_owner_id'] = $originalAccount->user_id;

        // Values are already divided and ownership_percentage is already 50 from original account
        $jointAccount = InvestmentAccount::create($jointAccountData);

        // Create cash holding for the joint account (mirror of original)
        Holding::create([
            'holdable_id' => $jointAccount->id,
            'holdable_type' => InvestmentAccount::class,
            'asset_type' => 'cash',
            'security_name' => 'Cash',
            'allocation_percent' => 100.00,
            'current_value' => $jointAccount->current_value,
            'quantity' => null,
            'purchase_price' => null,
            'purchase_date' => null,
            'current_price' => null,
            'cost_basis' => null,
            'ocf_percent' => 0.00,
        ]);

        // Update original account with joint_owner_id
        $originalAccount->update(['joint_owner_id' => $jointOwnerId]);

        // Clear cache for joint owner
        $this->investmentAgent->clearCache($jointOwnerId);
    }
}
