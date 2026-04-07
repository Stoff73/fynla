<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\InvestmentAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\AccountProjectionsRequest;
use App\Http\Requests\Investment\ScenarioRequest;
use App\Http\Requests\Investment\StartMonteCarloRequest;
use App\Http\Requests\Investment\StoreHoldingRequest;
use App\Http\Requests\Investment\StoreInvestmentGoalRequest;
use App\Http\Requests\Investment\StoreRiskProfileRequest;
use App\Http\Requests\Investment\UpdateHoldingRequest;
use App\Http\Requests\Investment\UpdateInvestmentGoalRequest;
use App\Http\Requests\StoreInvestmentAccountRequest;
use App\Http\Requests\UpdateInvestmentAccountRequest;
use App\Http\Resources\HoldingResource;
use App\Http\Resources\InvestmentAccountResource;
use App\Http\Traits\SanitizedErrorResponse;
use App\Jobs\RunMonteCarloSimulation;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\InvestmentGoal;
use App\Models\Investment\RiskProfile;
use App\Services\Goals\GoalStrategyService;
use App\Services\Goals\LifeEventIntegrationService;
use App\Services\Investment\DiversificationAnalyzer;
use App\Services\Investment\InvestmentProjectionService;
use App\Services\Investment\ReturnCalculationService;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        private readonly InvestmentAgent $investmentAgent,
        private readonly InvestmentProjectionService $projectionService,
        private readonly DiversificationAnalyzer $diversificationAnalyzer,
        private readonly ReturnCalculationService $returnCalculationService,
        private readonly LifeEventIntegrationService $lifeEventIntegration,
        private readonly GoalStrategyService $goalStrategy
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
        $accounts = InvestmentAccount::forUserOrJoint($user->id)
            ->with(['holdings', 'user', 'jointOwner'])
            ->get();

        // Transform accounts using resource and add calculated fields
        $accountsData = $accounts->map(function ($account) use ($user) {
            $resourceData = (new InvestmentAccountResource($account))->toArray(request());

            // Add user-specific calculated fields
            $resourceData['user_share'] = $this->calculateUserShare($account, $user->id);
            $resourceData['full_value'] = (float) $account->current_value;
            $resourceData['is_primary_owner'] = $this->isPrimaryOwner($account, $user->id);
            $resourceData['is_shared'] = $this->isSharedOwnership($account);

            // Calculate annualised return from holdings
            $resourceData['annualised_return'] = $this->returnCalculationService->calculateAnnualisedReturn($account);

            // Add owner names for joint accounts
            $owner = $account->user;
            $jointOwner = $account->jointOwner;
            $resourceData['owner_name'] = $owner ? trim(($owner->first_name ?? '').' '.($owner->surname ?? '')) : null;
            $resourceData['joint_owner_name'] = $jointOwner ? trim(($jointOwner->first_name ?? '').' '.($jointOwner->surname ?? '')) : null;

            return $resourceData;
        });

        $goals = InvestmentGoal::where('user_id', $user->id)->get();
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();

        // Get life events and goal strategies relevant to investments
        try {
            $lifeEvents = $this->lifeEventIntegration->getEventsForModule($user->id, 'investment');
            $lifeEventImpact = $this->lifeEventIntegration->getModuleImpactSummary($user->id, 'investment');
            $goalStrategies = $this->goalStrategy->getStrategiesForModule($user->id, 'investment');
            $goalsSummary = $this->goalStrategy->getModuleGoalsSummary($user->id, 'investment');
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
                'accounts' => $accountsData,
                'goals' => $goals,
                'risk_profile' => $riskProfile,
                'life_events' => $lifeEvents,
                'life_event_impact' => $lifeEventImpact,
                'goal_strategies' => $goalStrategies,
                'goals_summary' => $goalsSummary,
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
    public function scenarios(ScenarioRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
    public function startMonteCarlo(StartMonteCarloRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Generate unique job ID
            $jobId = Str::uuid()->toString();

            $user = $request->user();

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

            // Store user ownership of this job for IDOR protection
            Cache::put("monte_carlo_status_{$jobId}", [
                'status' => 'queued',
                'user_id' => $user->id,
            ], 3600);

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
            return $this->errorResponse($e, 'Monte Carlo simulation start');
        }
    }

    /**
     * Get Monte Carlo simulation results
     */
    public function getMonteCarloResults(Request $request, string $jobId): JsonResponse
    {
        $status = Cache::get("monte_carlo_status_{$jobId}");

        if (! $status || (is_array($status) && ($status['user_id'] ?? null) !== $request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found',
            ], 404);
        }

        // Extract status string if stored as array with user_id
        $statusValue = is_array($status) ? ($status['status'] ?? 'unknown') : $status;

        if ($statusValue === 'running') {
            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'running',
                    'message' => 'Simulation in progress',
                ],
            ]);
        }

        if ($statusValue === 'failed') {
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
    public function storeAccount(StoreInvestmentAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

        // Auto-assign main risk level if user has a risk profile
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();
        if ($riskProfile && $riskProfile->risk_level) {
            $validated['risk_preference'] = $riskProfile->risk_level;
        }

        // Auto-calculate disposal restriction date for EIS/SEIS (3-year holding period)
        if (in_array($validated['account_type'], ['private_company', 'crowdfunding'])
            && isset($validated['tax_relief_type'])
            && in_array($validated['tax_relief_type'], ['eis', 'seis', 'sitr'])
            && isset($validated['investment_date'])) {
            $investmentDate = \Carbon\Carbon::parse($validated['investment_date']);
            $validated['disposal_restriction_date'] = $investmentDate->addYears(3)->format('Y-m-d');
        }

        // Auto-calculate CSOP three-year date (grant_date + 3 years)
        if ($validated['account_type'] === 'csop' && isset($validated['grant_date'])) {
            $grantDate = \Carbon\Carbon::parse($validated['grant_date']);
            $validated['csop_three_year_date'] = $grantDate->copy()->addYears(3)->format('Y-m-d');
        }

        // Auto-calculate SAYE maturity date (scheme_start_date + scheme_duration_months)
        if ($validated['account_type'] === 'saye'
            && isset($validated['scheme_start_date'])
            && isset($validated['scheme_duration_months'])) {
            $startDate = \Carbon\Carbon::parse($validated['scheme_start_date']);
            $validated['saye_maturity_date'] = $startDate->copy()->addMonths($validated['scheme_duration_months'])->format('Y-m-d');
        }

        // Set default tax treatment for employee share schemes
        if (in_array($validated['account_type'], ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'])) {
            if (! isset($validated['tax_treatment'])) {
                $validated['tax_treatment'] = in_array($validated['account_type'], ['saye', 'csop', 'emi'])
                    ? 'tax_advantaged'
                    : 'unapproved';
            }
        }

        // Set default current_value to 0 for account types that don't use it
        if (in_array($validated['account_type'], ['private_company', 'crowdfunding', 'saye', 'csop', 'emi', 'unapproved_options', 'rsu'])) {
            $validated['current_value'] = $validated['current_value'] ?? 0;
        }

        // Extract holdings before creating account (not a model field)
        $holdings = $validated['holdings'] ?? [];
        unset($validated['holdings']);

        $account = null;

        DB::transaction(function () use ($validated, $holdings, &$account) {
            $account = InvestmentAccount::create($validated);

            if (! empty($holdings)) {
                $hasCashHolding = false;

                foreach ($holdings as $holdingData) {
                    $currentValue = ($account->current_value * $holdingData['allocation_percent']) / 100;

                    if (($holdingData['asset_type'] ?? '') === 'cash') {
                        $hasCashHolding = true;
                    }

                    $account->holdings()->create([
                        'holdable_type' => InvestmentAccount::class,
                        'holdable_id' => $account->id,
                        'security_name' => $holdingData['security_name'],
                        'asset_type' => $holdingData['asset_type'],
                        'allocation_percent' => $holdingData['allocation_percent'],
                        'cost_basis' => $holdingData['cost_basis'] ?? null,
                        'ocf_percent' => $holdingData['ocf_percent'] ?? 0,
                        'current_value' => $currentValue,
                    ]);
                }

                // Auto-create cash holding for remainder — only if user didn't already add one
                $totalAllocated = collect($holdings)->sum('allocation_percent');
                if ($totalAllocated < 100 && ! $hasCashHolding) {
                    $remainderPercent = 100 - $totalAllocated;
                    $account->holdings()->create([
                        'holdable_type' => InvestmentAccount::class,
                        'holdable_id' => $account->id,
                        'security_name' => 'Cash',
                        'asset_type' => 'cash',
                        'allocation_percent' => $remainderPercent,
                        'current_value' => ($account->current_value * $remainderPercent) / 100,
                    ]);
                }
            }
        });

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        // If joint owner, clear their cache too
        if (isset($validated['joint_owner_id'])) {
            $this->investmentAgent->clearCache($validated['joint_owner_id']);
        }

        // Load holdings for response
        $account->load('holdings');

        // Transform using resource and add calculated fields
        $resourceData = (new InvestmentAccountResource($account))->toArray(request());
        $resourceData['user_share'] = $this->calculateUserShare($account, $user->id);
        $resourceData['full_value'] = (float) $account->current_value;
        $resourceData['is_primary_owner'] = true;

        return response()->json([
            'success' => true,
            'data' => $resourceData,
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
    public function updateAccount(UpdateInvestmentAccountRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Only primary owner can update
        $account = InvestmentAccount::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validated();

        // Log joint account update if applicable
        if ($this->isSharedOwnership($account) && $account->joint_owner_id && isset($validated['current_value'])) {
            $this->logJointAccountUpdate($user, $account, $validated);
        }

        // Track old joint owner before update (for cache clearing if ownership changes)
        $oldJointOwnerId = $account->joint_owner_id;

        // Single-record pattern: Handle ownership percentage when changing to/from joint
        // Default to 50% when switching to joint ownership (if not explicitly set)
        $ownershipType = $validated['ownership_type'] ?? $account->ownership_type;
        $jointOwnerId = $validated['joint_owner_id'] ?? $account->joint_owner_id;

        if ($ownershipType === 'joint' && $jointOwnerId) {
            // Switching to joint or already joint - default to 50% if not specified
            if (! isset($validated['ownership_percentage'])) {
                $validated['ownership_percentage'] = 50.00;
            }
        } elseif ($ownershipType === 'individual') {
            // Switching to individual - reset to 100%
            $validated['ownership_percentage'] = 100.00;
            $validated['joint_owner_id'] = null;
        }

        // ISA validation: ISAs can only be individually owned (UK tax rule)
        $newType = $validated['account_type'] ?? $account->account_type;
        $newOwnership = $validated['ownership_type'] ?? $account->ownership_type;
        if ($newType === 'isa' && $newOwnership !== 'individual') {
            return response()->json([
                'success' => false,
                'message' => 'ISAs can only be individually owned. Joint or trust ownership is not permitted for ISAs under UK tax rules.',
            ], 422);
        }

        // Auto-calculate CSOP three-year date on update if grant_date changes
        $accountType = $validated['account_type'] ?? $account->account_type;
        if ($accountType === 'csop' && isset($validated['grant_date'])) {
            $grantDate = \Carbon\Carbon::parse($validated['grant_date']);
            $validated['csop_three_year_date'] = $grantDate->copy()->addYears(3)->format('Y-m-d');
        }

        // Auto-calculate SAYE maturity date on update
        if ($accountType === 'saye') {
            $startDate = $validated['scheme_start_date'] ?? $account->scheme_start_date;
            $duration = $validated['scheme_duration_months'] ?? $account->scheme_duration_months;
            if ($startDate && $duration) {
                $startDateCarbon = \Carbon\Carbon::parse($startDate);
                $validated['saye_maturity_date'] = $startDateCarbon->copy()->addMonths($duration)->format('Y-m-d');
            }
        }

        // Extract holdings before updating (not a model field)
        $holdings = $validated['holdings'] ?? null;
        unset($validated['holdings']);

        // Single-record pattern: Update directly (no reciprocal update)
        $account->update($validated);

        // Sync holdings if provided
        if ($holdings !== null) {
            $account->holdings()->delete();

            foreach ($holdings as $holdingData) {
                $currentValue = ($account->current_value * $holdingData['allocation_percent']) / 100;

                $account->holdings()->create([
                    'holdable_type' => InvestmentAccount::class,
                    'holdable_id' => $account->id,
                    'security_name' => $holdingData['security_name'],
                    'asset_type' => $holdingData['asset_type'],
                    'allocation_percent' => $holdingData['allocation_percent'],
                    'cost_basis' => $holdingData['cost_basis'] ?? null,
                    'ocf_percent' => $holdingData['ocf_percent'] ?? 0,
                    'current_value' => $currentValue,
                ]);
            }

            // Auto-create cash holding for remainder
            $totalAllocated = collect($holdings)->sum('allocation_percent');
            if ($totalAllocated < 100) {
                $remainderPercent = 100 - $totalAllocated;
                $account->holdings()->create([
                    'holdable_type' => InvestmentAccount::class,
                    'holdable_id' => $account->id,
                    'security_name' => 'Cash',
                    'asset_type' => 'cash',
                    'allocation_percent' => $remainderPercent,
                    'current_value' => ($account->current_value * $remainderPercent) / 100,
                ]);
            }
        }

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        // If joint owner, clear their cache too
        if ($account->joint_owner_id) {
            $this->investmentAgent->clearCache($account->joint_owner_id);
        }

        // If old joint owner was removed, clear their cache too
        if ($oldJointOwnerId && $oldJointOwnerId !== $account->joint_owner_id) {
            $this->investmentAgent->clearCache($oldJointOwnerId);
        }

        // Refresh account and load relationships for response
        $account = $account->fresh()->load('holdings');

        // Transform using resource and add calculated fields
        $resourceData = (new InvestmentAccountResource($account))->toArray(request());
        $resourceData['user_share'] = $this->calculateUserShare($account, $user->id);
        $resourceData['full_value'] = (float) $account->current_value;
        $resourceData['is_primary_owner'] = true;

        return response()->json([
            'success' => true,
            'data' => $resourceData,
        ]);
    }

    /**
     * Toggle include_in_retirement flag for an investment account.
     *
     * This flag determines whether the account appears in the Retirement
     * Income Planner. Only primary owner can toggle.
     *
     * PATCH /api/investment/accounts/{id}/toggle-retirement
     */
    public function toggleRetirementInclusion(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Only primary owner can toggle
        $account = InvestmentAccount::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        // Toggle the flag
        $account->include_in_retirement = ! $account->include_in_retirement;
        $account->save();

        // Clear caches
        $this->investmentAgent->clearCache($user->id);

        // If joint owner, clear their cache too
        if ($account->joint_owner_id) {
            $this->investmentAgent->clearCache($account->joint_owner_id);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $account->id,
                'include_in_retirement' => $account->include_in_retirement,
            ],
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

        // Soft-delete holdings first (polymorphic — no SQL CASCADE on soft-delete)
        $account->holdings()->delete();

        // Then soft-delete the account
        $account->delete();

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        // If joint owner, clear their cache too
        if ($jointOwnerId) {
            $this->investmentAgent->clearCache($jointOwnerId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Investment account deleted successfully',
        ]);
    }

    // ==================== Holding CRUD ====================

    /**
     * Store a new holding
     */
    public function storeHolding(StoreHoldingRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

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

        // If joint owner, clear their cache too
        if ($account->joint_owner_id) {
            $this->investmentAgent->clearCache($account->joint_owner_id);
        }

        // Clear optimization caches (efficient frontier, correlation matrix)
        PortfolioOptimizationController::clearUserOptimizationCache($user->id);

        return response()->json([
            'success' => true,
            'data' => new HoldingResource($holding),
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
    public function updateHolding(UpdateHoldingRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Find holding through account ownership
        $holding = Holding::whereHas('investmentAccount', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->findOrFail($id);

        $validated = $request->validated();

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

        // If joint owner, clear their cache too
        $holdingAccount = $holding->investmentAccount;
        if ($holdingAccount && $holdingAccount->joint_owner_id) {
            $this->investmentAgent->clearCache($holdingAccount->joint_owner_id);
        }

        // Clear optimization caches (efficient frontier, correlation matrix)
        PortfolioOptimizationController::clearUserOptimizationCache($user->id);

        return response()->json([
            'success' => true,
            'data' => new HoldingResource($holding->fresh()),
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

        // If joint owner, clear their cache too
        if ($account->joint_owner_id) {
            $this->investmentAgent->clearCache($account->joint_owner_id);
        }

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
    public function storeGoal(StoreInvestmentGoalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();
        $validated['user_id'] = $user->id;

        $goal = InvestmentGoal::create($validated);

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        return response()->json([
            'success' => true,
            'data' => $goal,
        ], 201);
    }

    /**
     * Update a goal
     */
    public function updateGoal(UpdateInvestmentGoalRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $goal = InvestmentGoal::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validated();

        $goal->update($validated);

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

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

        // Clear cache
        $this->investmentAgent->clearCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Investment goal deleted successfully',
        ]);
    }

    // ==================== Risk Profile ====================

    /**
     * Store or update risk profile
     */
    public function storeOrUpdateRiskProfile(StoreRiskProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
     * Accepts optional risk_level query parameter for "what-if" scenarios.
     *
     * GET /api/investment/accounts/{id}/projections?risk_level=high
     */
    public function getAccountProjections(AccountProjectionsRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $riskLevelOverride = $validated['risk_level'] ?? null;

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
            // Use the risk-override method for direct account projection
            $accountProjection = $this->projectionService->getAccountProjectionWithRiskOverride(
                $account,
                $user,
                $riskLevelOverride,
                [5, 10, 20, 30]
            );

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
                    'projections' => $accountProjection['projections'],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Investment account projections');
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
     * Calculate annualised return for an account based on holdings.
     *
     * @deprecated Use ReturnCalculationService::calculateAnnualisedReturn() directly
     */
    private function calculateAccountAnnualisedReturn(InvestmentAccount $account): ?float
    {
        return $this->returnCalculationService->calculateAnnualisedReturn($account);
    }
}
