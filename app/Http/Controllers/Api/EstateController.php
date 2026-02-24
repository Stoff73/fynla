<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estate\StoreAssetRequest;
use App\Http\Requests\Estate\StoreGiftRequest;
use App\Http\Requests\Estate\StoreLiabilityRequest;
use App\Http\Requests\Estate\UpdateAssetRequest;
use App\Http\Requests\Estate\UpdateGiftRequest;
use App\Http\Requests\Estate\UpdateLiabilityRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\Liability;
use App\Models\Estate\Trust;
use App\Models\Investment\InvestmentAccount;
use App\Services\Estate\CashFlowProjector;
use App\Services\Estate\NetWorthAnalyzer;
use App\Services\Goals\LifeEventIntegrationService;
use App\Services\TaxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EstateController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly NetWorthAnalyzer $netWorthAnalyzer,
        private readonly CashFlowProjector $cashFlowProjector,
        private readonly \App\Services\Estate\ComprehensiveEstatePlanService $comprehensiveEstatePlan,
        private readonly TaxConfigService $taxConfig,
        private readonly LifeEventIntegrationService $lifeEventIntegration
    ) {}

    /**
     * Get all estate planning data for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $assets = Asset::where('user_id', $user->id)->get();
        $liabilities = Liability::where('user_id', $user->id)->get();
        $gifts = Gift::where('user_id', $user->id)->get();
        $trusts = Trust::where('user_id', $user->id)->get();
        $ihtProfile = IHTProfile::where('user_id', $user->id)->first();

        // Pull investment accounts and categorize for IHT
        $investmentAccounts = InvestmentAccount::where('user_id', $user->id)->get();
        $investmentAccountsFormatted = $investmentAccounts->map(function ($account) {
            // Determine IHT exemption status based on account type
            // VCT and EIS may qualify for Business Relief if held 2+ years
            // For now, we'll mark them as potentially exempt with a note
            $isIhtExempt = false;
            $exemptionReason = null;

            if (in_array($account->account_type, ['vct', 'eis'])) {
                $exemptionReason = 'May qualify for Business Relief if held for 2+ years (manual verification required)';
            }

            return [
                'id' => 'investment_'.$account->id,
                'source' => 'investment_module',
                'investment_account_id' => $account->id,
                'asset_type' => 'investment',
                'asset_name' => $account->provider.' - '.strtoupper($account->account_type).($account->platform ? ' ('.$account->platform.')' : ''),
                'account_type' => $account->account_type,
                'current_value' => $account->current_value,
                'is_iht_exempt' => $isIhtExempt,
                'exemption_reason' => $exemptionReason,
                'valuation_date' => $account->updated_at->format('Y-m-d'),
                'ownership_type' => 'individual', // Default, user can change if joint
                'provider' => $account->provider,
                'platform' => $account->platform,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'assets' => $assets,
                'investment_accounts' => $investmentAccountsFormatted,
                'liabilities' => $liabilities,
                'gifts' => $gifts,
                'trusts' => $trusts,
                'iht_profile' => $ihtProfile,
                'life_events' => rescue(fn () => $this->lifeEventIntegration->getEventsForModule($user->id, 'estate'), [], report: true),
                'life_event_impact' => rescue(fn () => $this->lifeEventIntegration->getModuleImpactSummary($user->id, 'estate'), null, report: true),
            ],
        ]);
    }

    /**
     * Generate comprehensive estate plan combining all strategies
     */
    public function getComprehensiveEstatePlan(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Eager load relationships needed for IHT calculations
            $user->load(['investmentAccounts', 'mortgages', 'properties', 'liabilities']);

            // Also load spouse relationships if spouse is involved
            $spouse = ($user->marital_status === 'married' && $user->spouse_id)
                ? \App\Models\User::find($user->spouse_id)
                : null;
            if ($spouse) {
                $spouse->load(['investmentAccounts', 'mortgages', 'properties', 'liabilities']);
            }

            $plan = $this->comprehensiveEstatePlan->generateComprehensiveEstatePlan($user);

            return response()->json([
                'success' => true,
                'data' => $plan,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Comprehensive estate plan generation');
        }
    }

    /**
     * Get net worth analysis
     */
    public function getNetWorth(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $netWorth = $this->netWorthAnalyzer->generateSummary($user->id);

            return response()->json([
                'success' => true,
                'data' => $netWorth,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Net worth calculation');
        }
    }

    /**
     * Get cash flow for a tax year
     */
    public function getCashFlow(Request $request): JsonResponse
    {
        $user = $request->user();
        $taxYear = $request->query('taxYear', '2025/26');

        try {
            $cashFlow = $this->cashFlowProjector->createPersonalPL($user->id, $taxYear);

            return response()->json([
                'success' => true,
                'data' => $cashFlow,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Cash flow retrieval');
        }
    }

    // ============ ASSET CRUD ============

    /**
     * Store a new asset
     */
    public function storeAsset(StoreAssetRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $validated['user_id'] = $user->id;
            $asset = Asset::create($validated);

            // Invalidate cache
            Cache::forget("estate_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Asset created successfully',
                'data' => $asset,
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Asset creation');
        }
    }

    /**
     * Update an asset
     */
    public function updateAsset(UpdateAssetRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $asset = Asset::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $asset->update($validated);

            // Invalidate cache
            Cache::forget("estate_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Asset updated successfully',
                'data' => $asset->fresh(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Asset update');
        }
    }

    /**
     * Delete an asset
     */
    public function destroyAsset(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $asset = Asset::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $asset->delete();

            // Invalidate cache
            Cache::forget("estate_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Asset deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Asset deletion');
        }
    }

    // ============ LIABILITY CRUD ============

    /**
     * Store a new liability
     */
    public function storeLiability(StoreLiabilityRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $validated['user_id'] = $user->id;
            $liability = Liability::create($validated);

            // Invalidate cache
            Cache::forget("estate_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Liability created successfully',
                'data' => $liability,
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Liability creation');
        }
    }

    /**
     * Update a liability
     */
    public function updateLiability(UpdateLiabilityRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $liability = Liability::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $liability->update($validated);

            // Invalidate cache
            Cache::forget("estate_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Liability updated successfully',
                'data' => $liability->fresh(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Liability not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Liability update');
        }
    }

    /**
     * Delete a liability
     */
    public function destroyLiability(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $liability = Liability::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $liability->delete();

            // Invalidate cache
            Cache::forget("estate_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Liability deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Liability not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Liability deletion');
        }
    }

    // ============ GIFT CRUD ============

    /**
     * Store a new gift
     */
    public function storeGift(StoreGiftRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $validated['user_id'] = $user->id;
            $gift = Gift::create($validated);

            // Invalidate cache
            Cache::forget("estate_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Gift created successfully',
                'data' => $gift,
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Gift creation');
        }
    }

    /**
     * Update a gift
     */
    public function updateGift(UpdateGiftRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $gift = Gift::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $gift->update($validated);

            // Invalidate cache
            Cache::forget("estate_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Gift updated successfully',
                'data' => $gift->fresh(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gift not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Gift update');
        }
    }

    /**
     * Delete a gift
     */
    public function destroyGift(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $gift = Gift::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $gift->delete();

            // Invalidate cache
            Cache::forget("estate_analysis_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Gift deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gift not found or unauthorized',
            ], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Gift deletion');
        }
    }
}
