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
use App\Http\Resources\Estate\AssetResource;
use App\Http\Resources\Estate\GiftResource;
use App\Http\Resources\Estate\LiabilityResource;
use App\Http\Resources\Estate\TrustResource;
use App\Http\Traits\GatesEstateAccess;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\Liability;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Estate\CashFlowProjector;
use App\Services\Estate\NetWorthAnalyzer;
use App\Services\Goals\LifeEventIntegrationService;
use App\Services\Stores\Exceptions\GiftOwnedByTrustException;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\GiftStore;
use App\Services\Stores\IngestSource;
use App\Services\Stores\LiabilityStore;
use App\Services\Stores\TierConfigurationStore;
use App\Services\TaxConfigService;
use App\Services\Tiers\EstateIhtExposureDetector;
use App\Services\Tiers\TeaserGate;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstateController extends Controller
{
    use CalculatesOwnershipShare;
    use GatesEstateAccess;
    use SanitizedErrorResponse;

    public function __construct(
        private readonly NetWorthAnalyzer $netWorthAnalyzer,
        private readonly CashFlowProjector $cashFlowProjector,
        private readonly TaxConfigService $taxConfig,
        private readonly LifeEventIntegrationService $lifeEventIntegration,
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly TeaserGate $teaserGate,
        private readonly EstateIhtExposureDetector $ihtExposureDetector,
        private readonly TierConfigurationStore $tierStore,
        private readonly LiabilityStore $liabilityStore,
        private readonly GiftStore $giftStore,
    ) {}

    /**
     * Get all estate planning data for authenticated user.
     *
     * Server-side teaser gate (spec §10.2 / SP2 PR7): Free users
     * receive a cheap IHT-exposure signal rather than the full module.
     * The Vue view branches on `mode` for defence-in-depth, but this
     * response is authoritative.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Defence-in-depth: server is the authoritative gate (spec §10.2).
        if (! $this->teaserGate->isFull($user, 'estate')) {
            $teaser = $this->ihtExposureDetector->detect($user);

            // CTA label/target: cheapest tier that grants full Estate (plan §7.3 — not hardcoded).
            $targetTier = $this->tierStore->lowestTierWithCapability('estate', 'full');
            $ctaLabel = $targetTier
                ? "Upgrade to {$targetTier['display_name']} to unlock full Estate Planning"
                : 'Upgrade to unlock full Estate Planning';

            return response()->json([
                'mode' => 'teaser',
                'teaser' => $teaser,
                'cta' => [
                    'label' => $ctaLabel,
                    'target_tier' => $targetTier['tier'] ?? null,
                ],
            ]);
        }

        $assets = Asset::where('user_id', $user->id)->limit(100)->get();
        // Every debt this user is party to, as recorder or co-owner: the same
        // reach net worth uses (CSJ 2026-10-01).
        $liabilities = Liability::forUserOrJoint($user->id)->limit(100)->get();

        // Include mortgages as liabilities for net worth display
        $mortgages = Mortgage::whereHas('property', function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id);
        })->with('property')->limit(100)->get();

        // The share is applied HERE, not on the client. This used to hand the
        // frontend the securing property's ownership pair and leave it to work the
        // share out — a second implementation of the rule (Rule 20), and one that
        // nothing on the other end actually read: `LiabilitiesList.vue` summed
        // `current_balance` whole, so the Total Balance Owed included the 60% of a
        // tenants-in-common mortgage belonging to an off-platform co-owner
        // (W-0237). `calculateUserMortgageShare` resolves the property itself now
        // (W-0228), so the one reader answers it once, on the server.
        $mortgageLiabilities = $mortgages->map(function (Mortgage $mortgage) use ($user) {
            $property = $mortgage->property;

            return [
                'id' => 'mortgage_'.$mortgage->id,
                'source' => 'property_module',
                'liability_type' => 'mortgage',
                'liability_name' => 'Mortgage - '.($property->address_line_1 ?? 'Property'),
                'current_balance' => (float) ($mortgage->outstanding_balance ?? 0),
                'user_share' => round($this->calculateUserMortgageShare($mortgage, $user->id), 2),
                'monthly_payment' => (float) ($mortgage->monthly_payment ?? 0),
                'user_monthly_payment_share' => round(
                    $this->calculateUserMortgageMonthlyPaymentShare($mortgage, $user->id),
                    2
                ),
                'interest_rate' => (float) ($mortgage->interest_rate ?? 0),
                'notes' => ucfirst(str_replace('_', ' ', $mortgage->mortgage_type ?? 'repayment')).' mortgage',
                'ownership_type' => $property->ownership_type ?? 'individual',
                'ownership_percentage' => $property->ownership_percentage ?? 100,
            ];
        });

        $gifts = Gift::where('user_id', $user->id)->limit(100)->get();
        $trusts = Trust::where('user_id', $user->id)->limit(100)->get();
        $ihtProfile = IHTProfile::where('user_id', $user->id)->first();
        $will = Will::where('user_id', $user->id)->first();

        // Pull investment accounts and categorize for IHT
        $investmentAccounts = InvestmentAccount::where('user_id', $user->id)->limit(100)->get();
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

        $liabilityRows = collect(LiabilityResource::collection($liabilities)->resolve())
            ->merge($mortgageLiabilities)
            ->values();

        return response()->json([
            'mode' => 'full',
            'success' => true,
            'data' => [
                'assets' => AssetResource::collection($assets),
                'investment_accounts' => $investmentAccountsFormatted,
                'liabilities' => $liabilityRows->all(),
                // What the debts page totals, per type and in all, at the
                // viewer's share (CSJ 2026-10-01: the page added these up itself).
                'liability_totals' => $this->liabilityTotals($liabilityRows),
                // The estate pages' own-estate figures (NetWorthAnalyzer, the same
                // as /m, iOS and the dashboard estate card) and the gifts made in
                // the last seven years, so no screen adds them up.
                'summary' => $this->estateSummary($user, $gifts),
                'gifts' => GiftResource::collection($gifts),
                'trusts' => TrustResource::collection($trusts),
                'iht_profile' => $ihtProfile,
                'will_info' => $will ? [
                    'has_will' => (bool) $will->has_will,
                    'executor_name' => $will->executor_name,
                    'will_last_updated' => $will->will_last_updated,
                    'last_reviewed_date' => $will->last_reviewed_date,
                ] : null,
                'life_events' => rescue(fn () => $this->lifeEventIntegration->getEventsForModule($user->id, 'estate'), [], report: true),
                'life_event_impact' => rescue(fn () => $this->lifeEventIntegration->getModuleImpactSummary($user->id, 'estate'), null, report: true),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function estateSummary(User $user, $gifts): array
    {
        $estate = $this->netWorthAnalyzer->calculateNetWorth($user->id);
        $cutoff = now()->subYears(7)->startOfDay();
        $recent = $gifts->filter(fn ($gift): bool => $gift->gift_date !== null && $gift->gift_date->gte($cutoff));

        return [
            'total_assets' => $estate['total_assets'],
            'total_liabilities' => $estate['total_liabilities'],
            'net_worth' => $estate['net_worth'],
            'gifts_within_7_years' => [
                'count' => $recent->count(),
                'value' => round((float) $recent->sum('gift_value'), 2),
                'ids' => $recent->pluck('id')->values()->all(),
            ],
        ];
    }

    /**
     * Totals of the debts list at the viewer's share: `all`, then one entry per
     * debt type, each with what is owed and the monthly payments.
     *
     * @return array<string, array{balance: float, monthly_payments: float}>
     */
    private function liabilityTotals($rows): array
    {
        $sum = static fn ($group): array => [
            'balance' => round((float) $group->sum(fn ($r): float => (float) ($r['user_share'] ?? 0)), 2),
            'monthly_payments' => round((float) $group->sum(fn ($r): float => (float) ($r['user_monthly_payment_share'] ?? 0)), 2),
        ];

        return ['all' => $sum($rows)] + $rows->groupBy('liability_type')->map($sum)->all();
    }

    /**
     * Get net worth analysis
     */
    public function getNetWorth(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->requireFullEstate($user);

        try {
            $netWorth = $this->netWorthAnalyzer->generateSummary($user->id);

            return response()->json([
                'success' => true,
                'data' => $netWorth,
            ]);
        } catch (ModelNotFoundException $e) {
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
        $this->requireFullEstate($user);
        $taxYear = $request->query('taxYear', $this->taxConfig->getTaxYear());

        try {
            $cashFlow = $this->cashFlowProjector->createPersonalPL($user->id, $taxYear);

            return response()->json([
                'success' => true,
                'data' => $cashFlow,
            ]);
        } catch (ModelNotFoundException $e) {
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
            $this->cacheInvalidation->invalidateForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Asset created successfully',
                'data' => new AssetResource($asset),
            ], 201);
        } catch (ModelNotFoundException $e) {
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
            $this->cacheInvalidation->invalidateForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Asset updated successfully',
                'data' => new AssetResource($asset->fresh()),
            ]);
        } catch (ModelNotFoundException $e) {
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
            $this->cacheInvalidation->invalidateForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Asset deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
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
     * Return one liability through the same ownership rules used by the
     * canonical Net Worth detail surfaces.
     */
    public function showLiability(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $liability = Liability::query()
            ->whereKey($id)
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->with('jointOwner')
            ->firstOrFail();

        $data = (new LiabilityResource($liability))->resolve($request);
        $data['is_primary_owner'] = $liability->user_id === $user->id;

        return response()->json([
            'success' => true,
            'data' => ['liability' => $data],
        ]);
    }

    /**
     * Store a new liability
     */
    public function storeLiability(StoreLiabilityRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $liability = $this->liabilityStore->create($validated, $user, IngestSource::FORM);

            // Invalidate cache
            $this->cacheInvalidation->invalidateForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Liability created successfully',
                'data' => new LiabilityResource($liability),
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (StoreValidationException $e) {
            return $this->validationErrorResponse('Validation failed', $e->errors);
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
            $liability = $this->liabilityStore->update($id, $validated, $user, IngestSource::FORM);

            // Invalidate cache
            $this->cacheInvalidation->invalidateForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Liability updated successfully',
                'data' => new LiabilityResource($liability),
            ]);
        } catch (ModelNotFoundException $e) {
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
            $this->liabilityStore->delete($id, $user, IngestSource::FORM);

            // Invalidate cache
            $this->cacheInvalidation->invalidateForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Liability deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
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
        try {
            $gift = $this->giftStore->create($request->validated(), $request->user(), IngestSource::FORM);

            return response()->json([
                'success' => true,
                'message' => 'Gift created successfully',
                'data' => new GiftResource($gift),
            ], 201);
        } catch (StoreValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Gift creation');
        }
    }

    /**
     * Update a gift
     */
    public function updateGift(UpdateGiftRequest $request, int $id): JsonResponse
    {
        try {
            $gift = $this->giftStore->update($id, $request->validated(), $request->user(), IngestSource::FORM);

            return response()->json([
                'success' => true,
                'message' => 'Gift updated successfully',
                'data' => new GiftResource($gift),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gift not found or unauthorized',
            ], 404);
        } catch (GiftOwnedByTrustException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (StoreValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Gift update');
        }
    }

    /**
     * Delete a gift
     */
    public function destroyGift(Request $request, int $id): JsonResponse
    {
        try {
            $this->giftStore->delete($id, $request->user(), IngestSource::FORM);

            return response()->json([
                'success' => true,
                'message' => 'Gift deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gift not found or unauthorized',
            ], 404);
        } catch (GiftOwnedByTrustException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Gift deletion');
        }
    }
}
