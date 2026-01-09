<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\InvestmentAgent;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Jobs\RunMonteCarloSimulation;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\InvestmentGoal;
use App\Models\Investment\RiskProfile;
use App\Services\Investment\DiversificationAnalyzer;
use App\Services\Investment\InvestmentProjectionService;
use App\Traits\CalculatesOwnershipShare;
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
        private InvestmentAgent $investmentAgent,
        private InvestmentProjectionService $projectionService,
        private DiversificationAnalyzer $diversificationAnalyzer
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

            // Calculate annualised return from holdings
            $accountData['annualised_return'] = $this->calculateAccountAnnualisedReturn($account);

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

            // Generate unique job ID
            $jobId = Str::uuid()->toString();

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

            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'queued',
                    'message' => 'Monte Carlo simulation started',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions to let Laravel handle them (422 response)
            throw $e;
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

        // Set polymorphic relationship fields
        $validated['holdable_type'] = InvestmentAccount::class;
        $validated['holdable_id'] = $validated['investment_account_id'];
        unset($validated['investment_account_id']);

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

    /**
     * Get Monte Carlo projections for an investment account.
     *
     * GET /api/investment/accounts/{id}/projections
     */
    public function getAccountProjections(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Validate user has access to this account
        $account = InvestmentAccount::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })->find($id);

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Investment account not found',
            ], 404);
        }

        try {
            $projections = $this->projectionService->getPortfolioProjections(
                $user,
                [5, 10, 20, 30],
                null,
                20
            );

            // Find this specific account's projections
            $accountProjection = collect($projections['accounts'])
                ->firstWhere('account_id', $id);

            if (! $accountProjection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not generate projections for this account',
                ], 500);
            }

            // Get the 20-year projection data for the chart
            $yearByYear = $accountProjection['projections'][20]['year_by_year'] ?? [];

            return response()->json([
                'success' => true,
                'message' => 'Investment account projections generated successfully',
                'data' => [
                    'account_id' => $accountProjection['account_id'],
                    'account_name' => $accountProjection['account_name'],
                    'account_type' => $accountProjection['account_type'],
                    'current_value' => $accountProjection['current_value'],
                    'monthly_contribution' => $accountProjection['estimated_monthly_contribution'],
                    'risk_level' => $accountProjection['risk_level'],
                    'expected_return' => $accountProjection['expected_return'],
                    'volatility' => $accountProjection['volatility'],
                    'projection_years' => 20,
                    'percentiles_at_end' => $accountProjection['projections'][20]['percentiles'] ?? [],
                    'year_by_year' => $yearByYear,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate projections: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get diversification analysis for an investment account.
     *
     * GET /api/investment/accounts/{id}/diversification
     */
    public function getAccountDiversification(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Get account where user is owner or joint owner
        $account = InvestmentAccount::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })
            ->with('holdings')
            ->find($id);

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Investment account not found',
            ], 404);
        }

        $holdings = $account->holdings;

        // Handle empty holdings
        if ($holdings->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'No holdings recorded for this account',
                    'has_holdings' => false,
                    'account_id' => $id,
                    'account_name' => $account->provider ?? 'Investment Account',
                ],
            ]);
        }

        // Get user's risk level (default to 3/medium if not set)
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();
        $userRiskLevel = $riskProfile ? $this->diversificationAnalyzer->normalizeRiskLevel($riskProfile->risk_level ?? $riskProfile->risk_tolerance) : 3;

        // Get account-level risk override if set
        $accountRiskLevel = null;
        if ($account->has_custom_risk && $account->risk_preference) {
            $accountRiskLevel = $this->diversificationAnalyzer->normalizeRiskLevel($account->risk_preference);
        }

        // Run full analysis
        $analysis = $this->diversificationAnalyzer->analyze($holdings, $userRiskLevel, $accountRiskLevel);

        return response()->json([
            'success' => true,
            'data' => array_merge($analysis, [
                'has_holdings' => true,
                'account_id' => $id,
                'account_name' => $account->provider ?? 'Investment Account',
                'account_type' => $account->account_type,
            ]),
        ]);
    }

    /**
     * Calculate annualized return for an account based on holdings.
     *
     * Uses purchase_date to calculate holding period, defaults to 3 years if not set.
     *
     * @param  InvestmentAccount  $account  Account with holdings loaded
     * @return float|null Annualized return percentage or null if cannot calculate
     */
    private function calculateAccountAnnualisedReturn(InvestmentAccount $account): ?float
    {
        $holdings = $account->holdings;

        if ($holdings->isEmpty()) {
            return null;
        }

        $totalCostBasis = 0;
        $totalCurrentValue = 0;
        $weightedYears = 0;

        foreach ($holdings as $holding) {
            $costBasis = (float) ($holding->cost_basis ?? 0);
            $currentValue = (float) ($holding->current_value ?? 0);

            if ($costBasis <= 0) {
                continue;
            }

            // Calculate years held (default 3 years if no purchase_date)
            $years = 3.0;
            if ($holding->purchase_date) {
                $purchaseDate = $holding->purchase_date instanceof \Carbon\Carbon
                    ? $holding->purchase_date
                    : \Carbon\Carbon::parse($holding->purchase_date);
                $years = max(0.25, $purchaseDate->diffInDays(now()) / 365.25); // Min 3 months
            }

            $totalCostBasis += $costBasis;
            $totalCurrentValue += $currentValue;
            $weightedYears += $costBasis * $years;
        }

        if ($totalCostBasis <= 0) {
            return null;
        }

        // Calculate weighted average holding period
        $avgYears = $weightedYears / $totalCostBasis;

        // Calculate total return
        $totalReturn = ($totalCurrentValue - $totalCostBasis) / $totalCostBasis;

        // Annualize the return: ((1 + total_return)^(1/years) - 1) * 100
        if ($totalReturn <= -1) {
            // Prevent math errors for total loss
            return -100.0;
        }

        $annualizedReturn = (pow(1 + $totalReturn, 1 / $avgYears) - 1) * 100;

        return round($annualizedReturn, 2);
    }
}
