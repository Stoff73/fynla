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
use App\Traits\CalculatesOwnershipShare;
use App\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Investment Controller
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL account value in current_value
 * - user_id = primary owner (can edit/delete)
 * - joint_owner_id = secondary owner (view access)
 * - ownership_percentage = primary owner's share (default 50 for joint)
 */
class InvestmentController extends Controller
{
    use CalculatesOwnershipShare;
    use SanitizedErrorResponse;

    public function __construct(
        private InvestmentAgent $investmentAgent
    ) {}

    /**
     * Get all investment data for user
     *
     * Single-record pattern: Get accounts where user is owner OR joint_owner.
     * Includes calculated user_share and full_value fields.
     *
     * GET /api/investment
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Single-record pattern: Get accounts where user is owner OR joint_owner
        $accounts = InvestmentAccount::where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id)
            ->with('holdings')
            ->get();

        // Add calculated fields for each account
        $accounts = $accounts->map(function ($account) use ($user) {
            $accountData = $account->toArray();
            $accountData['user_share'] = $this->calculateUserShare($account, $user->id);
            $accountData['full_value'] = (float) $account->current_value;
            $accountData['is_primary_owner'] = $this->isPrimaryOwner($account, $user->id);
            $accountData['is_shared'] = $this->isSharedOwnership($account);

            return $accountData;
        });

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
     *
     * Single-record pattern: Store FULL value directly, no splitting.
     * Joint owner is linked via joint_owner_id field.
     *
     * POST /api/investment/accounts
     */
    public function storeAccount(Request $request): JsonResponse
    {
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
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
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

        // Single-record pattern: Store FULL value directly (no splitting)
        // For joint ownership, default to 50% if not specified
        if ($validated['ownership_type'] === 'joint' && isset($validated['joint_owner_id'])) {
            $validated['ownership_percentage'] = $validated['ownership_percentage'] ?? 50.00;
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

        // Single-record pattern: NO reciprocal account creation
        // Joint owner sees this account via the joint_owner_id query

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        // If joint owner, clear their cache too
        if (isset($validated['joint_owner_id'])) {
            $this->investmentAgent->clearCache($validated['joint_owner_id']);
        }

        // Add calculated fields to response
        $accountData = $account->load('holdings')->toArray();
        $accountData['user_share'] = $this->calculateUserShare($account, $user->id);
        $accountData['full_value'] = (float) $account->current_value;
        $accountData['is_primary_owner'] = true;

        return response()->json([
            'success' => true,
            'data' => $accountData,
        ], 201);
    }

    /**
     * Update an investment account
     *
     * Only primary owner (user_id) can update.
     * Single-record pattern: Update the single record directly.
     *
     * PUT /api/investment/accounts/{id}
     */
    public function updateAccount(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Only primary owner can update
        $account = InvestmentAccount::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

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

        // Log joint account update if applicable
        if ($this->isSharedOwnership($account) && $account->joint_owner_id && isset($validated['current_value'])) {
            $this->logJointAccountUpdate($user, $account, $validated);
        }

        // Single-record pattern: Update directly (no reciprocal update)
        $account->update($validated);

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        // If joint owner, clear their cache too
        if ($account->joint_owner_id) {
            $this->investmentAgent->clearCache($account->joint_owner_id);
        }

        // Add calculated fields to response
        $accountData = $account->fresh()->toArray();
        $accountData['user_share'] = $this->calculateUserShare($account, $user->id);
        $accountData['full_value'] = (float) $account->current_value;
        $accountData['is_primary_owner'] = true;

        return response()->json([
            'success' => true,
            'data' => $accountData,
        ]);
    }

    /**
     * Delete an investment account
     *
     * Only primary owner (user_id) can delete.
     * Single-record pattern: Delete the single record.
     *
     * DELETE /api/investment/accounts/{id}
     */
    public function destroyAccount(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Only primary owner can delete
        $account = InvestmentAccount::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $jointOwnerId = $account->joint_owner_id;

        // Single-record pattern: Just delete the one record
        $account->delete();

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        // If joint owner, clear their cache too
        if ($jointOwnerId) {
            $this->investmentAgent->clearCache($jointOwnerId);
        }

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
     * Log joint investment account update for audit trail
     */
    private function logJointAccountUpdate(\App\Models\User $user, InvestmentAccount $account, array $validated): void
    {
        $beforeValues = [
            'current_value' => [
                'full_value' => $account->current_value,
                'user_share' => $this->calculateUserShare($account, $user->id),
            ],
        ];

        $afterValues = [
            'current_value' => [
                'full_value' => $validated['current_value'],
                'user_share' => $validated['current_value'] * (($account->ownership_percentage ?? 100) / 100),
            ],
        ];

        \App\Models\JointAccountLog::logEdit(
            $user->id,
            $account->joint_owner_id,
            $account,
            [
                'before' => $beforeValues,
                'after' => $afterValues,
                'fields_changed' => ['current_value'],
            ],
            'update'
        );
    }
}
